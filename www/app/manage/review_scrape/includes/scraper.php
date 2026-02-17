<?php
/**
 * 口コミスクレイパークラス
 * 参考: reference/public_html/admin/reviews/scrap.php
 * ログイン不要で公開口コミページを取得
 */

class ReviewScraper {
    private $tenantId;
    private $settings;
    private $platformPdo;
    private $logId;
    private $logFile;

    private $stats = [
        'pages_processed' => 0,
        'reviews_found' => 0,
        'reviews_saved' => 0,
        'reviews_skipped' => 0,
        'errors_count' => 0,
    ];

    public function __construct($tenantId, $settings, $platformPdo, $logId = null) {
        $this->tenantId = $tenantId;
        $this->settings = $settings;
        $this->platformPdo = $platformPdo;
        $this->logId = $logId;

        $logDir = dirname(dirname(__DIR__)) . '/../../logs/review_scrape/';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        $this->logFile = $logDir . "tenant_{$tenantId}_" . date('Ymd') . '.log';
    }

    private function log($message) {
        $ts = date('Y-m-d H:i:s');
        @file_put_contents($this->logFile, "[{$ts}] {$message}\n", FILE_APPEND);
    }

    private static function cleanCastName($castName) {
        return preg_replace('/\[.*?\]/', '', trim($castName));
    }

    private static function parseReviewDate($dateStr) {
        if (preg_match('/(\d{4})年(\d{1,2})月(\d{1,2})日/', $dateStr, $m)) {
            return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        }
        return null;
    }

    private static function parseRating($ratingStr) {
        return floatval(trim($ratingStr));
    }

    private function getCastId($castName) {
        $cleanName = self::cleanCastName($castName);
        if ($cleanName === '') return null;
        try {
            $stmt = $this->platformPdo->prepare("
                SELECT id FROM tenant_casts
                WHERE tenant_id = ? AND name = ? AND checked = 1
                LIMIT 1
            ");
            $stmt->execute([$this->tenantId, $cleanName]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? (int)$row['id'] : null;
        } catch (Exception $e) {
            return null;
        }
    }

    private function shouldStop() {
        if (!$this->logId) return false;
        try {
            $stmt = $this->platformPdo->prepare("SELECT status, error_message FROM review_scrape_logs WHERE id = ?");
            $stmt->execute([$this->logId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row && $row['status'] === 'error' && !empty($row['error_message']);
        } catch (Exception $e) {
            return false;
        }
    }

    private function updateProgress() {
        if (!$this->logId) return;
        try {
            $stmt = $this->platformPdo->prepare("
                UPDATE review_scrape_logs SET
                    pages_processed = ?,
                    reviews_found = ?,
                    reviews_saved = ?,
                    reviews_skipped = ?,
                    errors_count = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $this->stats['pages_processed'],
                $this->stats['reviews_found'],
                $this->stats['reviews_saved'],
                $this->stats['reviews_skipped'],
                $this->stats['errors_count'],
                $this->logId
            ]);
        } catch (Exception $e) {
            // ignore
        }
    }

    /**
     * ノード内の girlid- リンク数を返す（XPath）
     */
    private function countGirlLinksInNode(DOMXPath $xp, \DOMNode $node) {
        $links = $xp->query('.//a[contains(@href, "girlid-")]', $node);
        return $links ? $links->length : 0;
    }

    /**
     * HTML を「遊んだ女の子」の出現位置で分割し、掲載日を含むブロックを返す（正規表現フォールバック）
     * @return array 各要素は1ブロックのHTML文字列
     */
    private function collectReviewBlocksByRegex($html, DOMXPath $xp, \DOMDocument $doc) {
        $blocks = preg_split('/(?=遊んだ女の子)/u', $html);
        $out = [];
        foreach ($blocks as $block) {
            $block = trim($block);
            if ($block === '' || strpos($block, '遊んだ女の子') !== 0) continue;
            if (strpos($block, '掲載日') === false) continue;
            $out[] = $block;
        }
        return $out;
    }

    /**
     * CityHeaven 等: girlid- リンクごとに、その口コミ1件だけを含むブロックを収集
     * 「このノード内に girlid- が1本だけ」になる最小の祖先を採用（ピックアップ・一覧の大コンテナを避ける）
     * @return \DOMNode[]
     */
    private function collectReviewNodesFromGirlLinks(DOMXPath $xp, \DOMNodeList $girlLinks) {
        $nodes = [];
        $seen = new \SplObjectStorage();
        for ($i = 0; $i < $girlLinks->length; $i++) {
            $link = $girlLinks->item($i);
            $n = $link->parentNode; // リンク自身は <a> なので、その親から探す
            while ($n && $n instanceof \DOMNode) {
                $count = $this->countGirlLinksInNode($xp, $n);
                $text = $n->textContent ?? '';
                $hasReview = (strpos($text, '掲載日') !== false || strpos($text, '遊んだ女の子') !== false);
                if ($count === 1 && $hasReview) {
                    // このノード内に girlid- が1本だけ かつ 口コミらしい文言あり＝1件の口コミブロック
                    if (!isset($seen[$n])) {
                        $seen[$n] = true;
                        $nodes[] = $n;
                    }
                    break;
                }
                $n = $n->parentNode;
            }
        }
        return $nodes;
    }

    public function execute() {
        try {
            $this->log('=== 口コミスクレイピング開始 ===');
            $this->log("テナントID: {$this->tenantId}");
            $baseUrl = rtrim($this->settings['reviews_base_url'], '/') . '/';
            $maxPages = (int)($this->settings['max_pages'] ?? 50);
            $timeout = (int)($this->settings['timeout'] ?? 30);
            $delay = (float)($this->settings['request_delay'] ?? 1.0);

            $ctx = stream_context_create([
                'http' => [
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'timeout' => $timeout
                ]
            ]);

            // 既存口コミ削除（テナント単位）
            $del = $this->platformPdo->prepare("DELETE FROM reviews WHERE tenant_id = ?");
            $del->execute([$this->tenantId]);
            $this->log("既存データ削除: " . $del->rowCount() . "件");

            $insertStmt = $this->platformPdo->prepare("
                INSERT INTO reviews (
                    tenant_id, user_name, cast_name, cast_id, review_date, rating,
                    title, content, shop_comment, source_url, source_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            // 1ページ目を取得してページネーションリンクを抽出（全ページURLを取得）
            $pageUrls = [$baseUrl];
            $firstHtml = @file_get_contents($baseUrl, false, $ctx);
            if ($firstHtml !== false && $firstHtml !== '' && preg_match_all('!/(reviews)/(\d+)/!', $firstHtml, $m)) {
                $pageNumbers = array_unique(array_map('intval', $m[2]));
                $pageNumbers = array_filter($pageNumbers, function ($n) { return $n >= 2; });
                sort($pageNumbers, SORT_NUMERIC);
                $basePath = parse_url($baseUrl, PHP_URL_PATH);
                $basePath = preg_replace('#/reviews/?.*$#', '/reviews/', $basePath);
                $origin = (parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https') . '://' . (parse_url($baseUrl, PHP_URL_HOST) ?: '');
                foreach (array_slice($pageNumbers, 0, $maxPages - 1) as $n) {
                    $pageUrls[] = $origin . $basePath . $n . '/';
                }
                $this->log('ページネーション検出: ' . count($pageUrls) . 'ページ');
            }

            $pageIndex = 0;
            foreach ($pageUrls as $url) {
                $pageIndex++;
                $page = $pageIndex;

                if ($this->shouldStop()) {
                    $this->log('手動停止を検知しました');
                    break;
                }

                $html = ($pageIndex === 1 && $firstHtml !== false && $firstHtml !== '') ? $firstHtml : @file_get_contents($url, false, $ctx);
                if ($html === false) {
                    $this->log("ページ取得失敗: {$url}");
                    $this->stats['errors_count']++;
                    continue;
                }

                $this->log("ページ {$page}: {$url}");

                libxml_use_internal_errors(true);
                $doc = new DOMDocument();
                $doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
                $xp = new DOMXPath($doc);

                $reviewNodes = $xp->query('//ul/li[contains(@class, "review-item")]');
                $reviewList = [];
                if ($reviewNodes->length > 0) {
                    foreach ($reviewNodes as $node) {
                        $reviewList[] = $node;
                    }
                } else {
                    // CityHeaven 等: girlid- リンクを含むブロックを1件の口コミとして取得
                    $girlLinks = $xp->query('//a[contains(@href, "girlid-")]');
                    $this->log("ページ {$page}: girlid- リンク数 = " . ($girlLinks->length));
                    if ($girlLinks->length > 0) {
                        $reviewList = $this->collectReviewNodesFromGirlLinks($xp, $girlLinks);
                        $this->log("ページ {$page}: 収集ノード数 = " . count($reviewList));
                    }
                    // ノードが1件以下なのにHTMLに「掲載日」が複数ある＝DOMで分割できていない → 正規表現でブロック分割
                    if (count($reviewList) <= 1 && substr_count($html, '掲載日') > 1) {
                        $reviewList = $this->collectReviewBlocksByRegex($html, $xp, $doc);
                        $this->log("ページ {$page}: 正規表現フォールバックでブロック数 = " . count($reviewList));
                    }
                }
                if (count($reviewList) === 0) {
                    $this->log("ページ {$page}: レビュー要素なし");
                    $this->stats['pages_processed']++;
                    $this->updateProgress();
                    sleep((int)ceil($delay));
                    continue;
                }

                $this->stats['pages_processed']++;
                $this->stats['reviews_found'] += count($reviewList);

                foreach ($reviewList as $index => $reviewNode) {
                    $curXp = $xp;
                    $curNode = $reviewNode;
                    if (is_string($reviewNode)) {
                        $tmpDoc = new \DOMDocument();
                        @$tmpDoc->loadHTML(mb_convert_encoding('<div id="rb">' . $reviewNode . '</div>', 'HTML-ENTITIES', 'UTF-8'));
                        $wrap = $tmpDoc->getElementById('rb') ?: $tmpDoc->getElementsByTagName('div')->item(0);
                        if ($wrap) {
                            $curXp = new DOMXPath($tmpDoc);
                            $curNode = $wrap;
                        }
                    }
                    try {
                        $userName = '';
                        $castName = '';
                        $reviewDate = '';
                        $rating = '';
                        $title = '';
                        $content = '';
                        $shopComment = '';

                        $userNameNode = $curXp->query(".//div[1]/div[1]/div/div/p/a", $curNode)->item(0)
                            ?: $curXp->query(".//p[contains(@class, 'userrank_nickname_shogo')]//a", $curNode)->item(0);
                        if ($userNameNode) $userName = trim($userNameNode->textContent);
                        // 正規表現ブロック等: girlid 以外の最初のリンクを投稿者名として使う
                        if ($userName === '') {
                            $allLinks = $curXp->query(".//a[@href]", $curNode);
                            for ($j = 0; $allLinks && $j < $allLinks->length; $j++) {
                                $a = $allLinks->item($j);
                                if ($a instanceof \DOMElement && strpos($a->getAttribute('href'), 'girlid') === false) {
                                    $userName = trim($a->textContent);
                                    break;
                                }
                            }
                        }

                        $castNameNode = $curXp->query(".//div[1]/div[2]/div[2]/dl/dd", $curNode)->item(0)
                            ?: $curXp->query(".//dd[contains(@class, 'name')]", $curNode)->item(0);
                        if ($castNameNode) $castName = trim($castNameNode->textContent);
                        // CityHeaven: 「遊んだ女の子 名前[年齢]」形式のテキストからキャスト名を抽出
                        $nodeText = $curNode->textContent ?? '';
                        if ($castName === '' && preg_match('/遊んだ女の子\s*([^\[]+)/u', $nodeText, $castM)) {
                            $castName = trim($castM[1]);
                        }

                        $dateNode = $curXp->query(".//div[2]/p[2]", $curNode)->item(0)
                            ?: $curXp->query(".//p[contains(@class, 'review-item-post-date')]", $curNode)->item(0);
                        if ($dateNode) {
                            $reviewDate = str_replace('掲載日：', '', trim($dateNode->textContent));
                        }
                        // CityHeaven: ノード内の「掲載日：YYYY年M月D日」をフォールバック
                        if ($reviewDate === '' && preg_match('/掲載日[：:]\s*(\d{4})年(\d{1,2})月(\d{1,2})日/u', $nodeText, $d)) {
                            $reviewDate = sprintf('%d年%d月%d日', (int)$d[1], (int)$d[2], (int)$d[3]);
                        }

                        $ratingNode = $curXp->query(".//span[contains(@class, 'total_rate')]", $curNode)->item(0)
                            ?: $curXp->query(".//div[2]/div[1]/span", $curNode)->item(0);
                        if ($ratingNode) $rating = trim($ratingNode->textContent);
                        if ($rating === '' && preg_match('/(\d+\.\d+)\s*[\s\S]*?掲載日/u', $nodeText, $r)) {
                            $rating = $r[1];
                        }

                        $titleNode = $curXp->query(".//div[2]/div[2]", $curNode)->item(0)
                            ?: $curXp->query(".//div[contains(@class, 'review-item-title')]", $curNode)->item(0);
                        if ($titleNode) $title = trim($titleNode->textContent);

                        $contentNode = $curXp->query(".//p[contains(@class, 'review-item-post')]", $curNode)->item(0)
                            ?: $curXp->query(".//div[2]/p[1]", $curNode)->item(0);
                        if ($contentNode) $content = trim($contentNode->textContent);
                        // CityHeaven: ノード本文から「掲載日」直前の長文を口コミ本文としてフォールバック
                        if ($content === '' && preg_match('/^(?:.*?)(.{100,})掲載日/us', $nodeText, $contentM)) {
                            $content = trim(preg_replace('/\s+/u', ' ', $contentM[1]));
                        }

                        $commentNode = $curXp->query(".//div[2]/div[5]/div/p", $curNode)->item(0)
                            ?: $curXp->query(".//p[contains(@class, 'review-item-reply-body')]", $curNode)->item(0);
                        if ($commentNode) $shopComment = trim($commentNode->textContent);

                        if ($userName === '' || $content === '') {
                            $this->stats['reviews_skipped']++;
                            continue;
                        }

                        $cleanCastName = self::cleanCastName($castName);
                        $parsedDate = self::parseReviewDate($reviewDate);
                        $parsedRating = self::parseRating($rating);
                        $castId = $this->getCastId($castName);
                        $sourceId = md5($userName . $cleanCastName . $parsedDate . $content . $page . $index);

                        $insertStmt->execute([
                            $this->tenantId,
                            $userName,
                            $cleanCastName ?: null,
                            $castId,
                            $parsedDate,
                            $parsedRating ?: null,
                            $title ?: null,
                            $content,
                            $shopComment ?: null,
                            $url,
                            $sourceId
                        ]);
                        $this->stats['reviews_saved']++;
                    } catch (Exception $e) {
                        $this->stats['errors_count']++;
                        $this->stats['reviews_skipped']++;
                        $this->log("レビュー処理エラー: " . $e->getMessage());
                    }
                }

                $this->updateProgress();
                sleep((int)ceil($delay));
            }

            $this->log('=== 口コミスクレイピング完了 ===');
            $this->log("保存: {$this->stats['reviews_saved']}件");
            return array_merge($this->stats, ['status' => 'success']);

        } catch (Exception $e) {
            $this->log('エラー: ' . $e->getMessage());
            $this->stats['errors_count']++;
            return array_merge($this->stats, [
                'status' => 'error',
                'error_message' => $e->getMessage()
            ]);
        }
    }
}
