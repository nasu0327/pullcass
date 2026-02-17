<?php
/**
 * 口コミスクレイパークラス
 * 参考: reference/public_html/admin/reviews/scrap.php
 *
 * 改善点:
 * - レート制限対策: リクエスト間隔2秒 + リトライ（最大3回、指数バックオフ）
 * - 全ページ取得: max_pages設定を無視し、レビューが存在する限り継続
 * - キャスト名抽出強化: 複数XPath + girlid近傍テキスト + regex
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

    /** キャスト名から年齢を除去 */
    private static function cleanCastName($castName) {
        return trim(preg_replace('/\[.*?\]/', '', trim($castName)));
    }

    /** 日付文字列をDATE型に変換 */
    private static function parseReviewDate($dateStr) {
        if (preg_match('/(\d{4})年(\d{1,2})月(\d{1,2})日/', $dateStr, $m)) {
            return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        }
        return null;
    }

    /** 評価点を数値に変換 */
    private static function parseRating($ratingStr) {
        return floatval(trim($ratingStr));
    }

    /** tenant_castsテーブルからキャストIDを取得 */
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
     * HTTP GETリクエスト（リトライ付き）
     * CityHeavenのレート制限対策: 失敗時に指数バックオフで最大3回リトライ
     */
    private function fetchUrl($url, $ctx) {
        $maxRetries = 3;
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            $html = @file_get_contents($url, false, $ctx);
            if ($html !== false && $html !== '') {
                return $html;
            }
            if ($attempt < $maxRetries) {
                $wait = $attempt * 3;
                $this->log("ページ取得失敗 (試行{$attempt}/{$maxRetries}): {$url} → {$wait}秒待機してリトライ");
                sleep($wait);
            }
        }
        return false;
    }

    /**
     * レビューノードからキャスト名を抽出（複数手法で確実に取得）
     */
    private function extractCastName(DOMXPath $xp, DOMNode $reviewNode) {
        // 手法1: 参考コードと同じXPath（dl/dd構造）
        $castNameNode = $xp->query(".//div[1]/div[2]/div[2]/dl/dd", $reviewNode)->item(0);
        if ($castNameNode) {
            $name = trim($castNameNode->textContent);
            if ($name !== '') return $name;
        }

        // 手法2: クラス名ベース
        $castNameNode = $xp->query(".//dd[contains(@class, 'name')]", $reviewNode)->item(0);
        if ($castNameNode) {
            $name = trim($castNameNode->textContent);
            if ($name !== '') return $name;
        }

        // 手法3: dt要素で「遊んだ女の子」を探し、隣接ddを取得
        $dtNodes = $xp->query(".//dt", $reviewNode);
        if ($dtNodes) {
            for ($i = 0; $i < $dtNodes->length; $i++) {
                $dt = $dtNodes->item($i);
                if (strpos($dt->textContent, '遊んだ女の子') !== false) {
                    $dd = $xp->query("following-sibling::dd", $dt)->item(0);
                    if ($dd) {
                        $name = trim($dd->textContent);
                        if ($name !== '') return $name;
                    }
                }
            }
        }

        // 手法4: girlid リンクの近傍テキストから取得
        $girlLinks = $xp->query(".//a[contains(@href, 'girlid-')]", $reviewNode);
        if ($girlLinks && $girlLinks->length > 0) {
            $girlLink = $girlLinks->item(0);
            $parent = $girlLink->parentNode;
            if ($parent) {
                $parentText = trim($parent->textContent);
                if (preg_match('/^(.+?)(?:\[|\d{2,3}歳|T\d{3}|プロフィール)/u', $parentText, $m)) {
                    $name = trim($m[1]);
                    if ($name !== '' && $name !== 'プロフィールを見る') return $name;
                }
            }
        }

        // 手法5: テキスト全体から正規表現で抽出
        $nodeText = $reviewNode->textContent ?? '';
        if (preg_match('/遊んだ女の子\s*([^\[\n\r]{1,30})/u', $nodeText, $castM)) {
            $name = trim($castM[1]);
            if ($name !== '') return $name;
        }

        return '';
    }

    public function execute() {
        try {
            $this->log('=== 口コミスクレイピング開始 ===');
            $this->log("テナントID: {$this->tenantId}");
            $baseUrl = rtrim($this->settings['reviews_base_url'], '/') . '/';
            $timeout = (int)($this->settings['timeout'] ?? 30);

            // レート制限対策: 最低2秒のリクエスト間隔
            $delay = max(2.0, (float)($this->settings['request_delay'] ?? 2.0));

            // 口コミは無制限: 全ページ取得（上限500ページ = 約5000件）
            $absoluteMaxPages = 500;

            // コンテキスト設定
            $ctx = stream_context_create([
                'http' => [
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'timeout' => $timeout,
                    'header' => "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8\r\nAccept-Language: ja,en-US;q=0.7,en;q=0.3\r\n"
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

            $this->log("全ページ無制限スクレイピング開始（リクエスト間隔: {$delay}秒）");

            $consecutiveEmptyPages = 0;

            for ($page = 1; $page <= $absoluteMaxPages; $page++) {
                if ($this->shouldStop()) {
                    $this->log('手動停止を検知しました');
                    break;
                }

                // ページネーション（参考と同じ）
                $url = ($page == 1) ? $baseUrl : $baseUrl . $page . '/';

                $this->log("ページ {$page} を処理中: {$url}");

                // リトライ付きHTTP取得
                $html = $this->fetchUrl($url, $ctx);
                if ($html === false) {
                    $this->log("ページ {$page}: 取得失敗（リトライ全失敗）");
                    $this->stats['errors_count']++;
                    $consecutiveEmptyPages++;
                    if ($consecutiveEmptyPages >= 10) {
                        $this->log("10ページ連続で取得失敗/空ページ → 終了");
                        break;
                    }
                    sleep((int)ceil($delay));
                    continue;
                }

                // DOM解析（参考と同じ: mb_convert_encoding を使わない）
                libxml_use_internal_errors(true);
                $doc = new DOMDocument();
                $doc->loadHTML($html);
                $xp = new DOMXPath($doc);

                // レビュー要素を取得（参考と同じXPath）
                $reviewNodes = $xp->query('//ul/li[contains(@class, "review-item")]');

                if ($reviewNodes->length === 0) {
                    $this->log("ページ {$page}: レビュー要素なし（HTML長さ: " . strlen($html) . "）");
                    // HTMLに「review」や「口コミ」が含まれるかチェック（レート制限判定）
                    $hasReviewContent = (strpos($html, '掲載日') !== false);
                    if (!$hasReviewContent) {
                        $this->log("ページ {$page}: レビューコンテンツなし（レート制限の可能性）→ 5秒追加待機");
                        sleep(5);
                    }
                    $consecutiveEmptyPages++;
                    if ($consecutiveEmptyPages >= 10) {
                        $this->log("10ページ連続でレビューなし → 全ページ取得完了と判断");
                        break;
                    }
                    $this->stats['pages_processed']++;
                    $this->updateProgress();
                    sleep((int)ceil($delay));
                    continue;
                }

                // レビューが見つかったのでカウンタリセット
                $consecutiveEmptyPages = 0;
                $this->log("ページ {$page}: {$reviewNodes->length}件のレビューを発見");

                $this->stats['pages_processed']++;
                $this->stats['reviews_found'] += $reviewNodes->length;

                foreach ($reviewNodes as $index => $reviewNode) {
                    try {
                        $userName = '';
                        $castName = '';
                        $reviewDate = '';
                        $rating = '';
                        $title = '';
                        $content = '';
                        $shopComment = '';

                        $reviewIndex = $index + 1;

                        // ユーザー名
                        $userNameNode = $xp->query(".//div[1]/div[1]/div/div/p/a", $reviewNode)->item(0);
                        if (!$userNameNode) {
                            $userNameNode = $xp->query(".//p[contains(@class, 'userrank_nickname_shogo')]//a", $reviewNode)->item(0);
                        }
                        if ($userNameNode) {
                            $userName = trim($userNameNode->textContent);
                        }

                        // キャスト名（強化版: 複数手法）
                        $castName = $this->extractCastName($xp, $reviewNode);
                        if ($reviewIndex <= 2) {
                            $this->log("  レビュー{$reviewIndex} キャスト名抽出結果: '{$castName}'");
                        }

                        // 掲載日
                        $dateNode = $xp->query(".//div[2]/p[2]", $reviewNode)->item(0);
                        if (!$dateNode) {
                            $dateNode = $xp->query(".//p[contains(@class, 'review-item-post-date')]", $reviewNode)->item(0);
                        }
                        if ($dateNode) {
                            $reviewDate = trim($dateNode->textContent);
                            $reviewDate = str_replace('掲載日：', '', $reviewDate);
                        }
                        // 正規表現フォールバック
                        if ($reviewDate === '') {
                            $nodeText = $reviewNode->textContent ?? '';
                            if (preg_match('/掲載日[：:]\s*(\d{4}年\d{1,2}月\d{1,2}日)/u', $nodeText, $d)) {
                                $reviewDate = $d[1];
                            }
                        }

                        // 評価点
                        $ratingNode = $xp->query(".//span[contains(@class, 'total_rate')]", $reviewNode)->item(0);
                        if (!$ratingNode) {
                            $ratingNode = $xp->query(".//div[2]/div[1]/span", $reviewNode)->item(0);
                        }
                        if ($ratingNode) {
                            $rating = trim($ratingNode->textContent);
                        }

                        // タイトル
                        $titleNode = $xp->query(".//div[2]/div[2]", $reviewNode)->item(0);
                        if (!$titleNode) {
                            $titleNode = $xp->query(".//div[contains(@class, 'review-item-title')]", $reviewNode)->item(0);
                        }
                        if ($titleNode) {
                            $title = trim($titleNode->textContent);
                        }

                        // 本文（ピックアップと通常で異なる構造）
                        if ($reviewIndex == 1) {
                            $contentNode = $xp->query(".//p[contains(@class, 'review-item-post')]", $reviewNode)->item(0);
                        } else {
                            $contentNode = $xp->query(".//div[2]/p[1]", $reviewNode)->item(0);
                        }
                        if (!$contentNode) {
                            $contentNode = $xp->query(".//p[contains(@class, 'review-item-post')]", $reviewNode)->item(0);
                        }
                        if ($contentNode) {
                            $content = trim($contentNode->textContent);
                        }

                        // お店からのコメント
                        $commentNode = $xp->query(".//div[2]/div[5]/div/p", $reviewNode)->item(0);
                        if (!$commentNode) {
                            $commentNode = $xp->query(".//p[contains(@class, 'review-item-reply-body')]", $reviewNode)->item(0);
                        }
                        if ($commentNode) {
                            $shopComment = trim($commentNode->textContent);
                        }

                        // データ検証
                        if ($userName === '' || $content === '') {
                            $this->stats['reviews_skipped']++;
                            continue;
                        }

                        // データ正規化
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

                // レート制限対策: ページ間の待機
                sleep((int)ceil($delay));
            }

            $this->log('=== 口コミスクレイピング完了 ===');
            $this->log("処理ページ数: {$this->stats['pages_processed']}, 保存: {$this->stats['reviews_saved']}件, スキップ: {$this->stats['reviews_skipped']}件, エラー: {$this->stats['errors_count']}件");
            return array_merge($this->stats, ['status' => 'success']);

        } catch (Exception $e) {
            $this->log('致命的エラー: ' . $e->getMessage());
            $this->stats['errors_count']++;
            return array_merge($this->stats, [
                'status' => 'error',
                'error_message' => $e->getMessage()
            ]);
        }
    }
}
