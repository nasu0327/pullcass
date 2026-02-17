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

            for ($page = 1; $page <= $maxPages; $page++) {
                if ($this->shouldStop()) {
                    $this->log('手動停止を検知しました');
                    break;
                }

                $url = ($page === 1) ? $baseUrl : $baseUrl . $page . '/';
                $this->log("ページ {$page}: {$url}");

                $html = @file_get_contents($url, false, $ctx);
                if ($html === false) {
                    $this->log("ページ取得失敗: {$url}");
                    $this->stats['errors_count']++;
                    continue;
                }

                libxml_use_internal_errors(true);
                $doc = new DOMDocument();
                $doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
                $xp = new DOMXPath($doc);

                $reviewNodes = $xp->query('//ul/li[contains(@class, "review-item")]');
                if ($reviewNodes->length === 0) {
                    $this->log("ページ {$page}: レビュー要素なし");
                    $this->stats['pages_processed']++;
                    $this->updateProgress();
                    sleep((int)ceil($delay));
                    continue;
                }

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

                        $userNameNode = $xp->query(".//div[1]/div[1]/div/div/p/a", $reviewNode)->item(0)
                            ?: $xp->query(".//p[contains(@class, 'userrank_nickname_shogo')]//a", $reviewNode)->item(0);
                        if ($userNameNode) $userName = trim($userNameNode->textContent);

                        $castNameNode = $xp->query(".//div[1]/div[2]/div[2]/dl/dd", $reviewNode)->item(0)
                            ?: $xp->query(".//dd[contains(@class, 'name')]", $reviewNode)->item(0);
                        if ($castNameNode) $castName = trim($castNameNode->textContent);

                        $dateNode = $xp->query(".//div[2]/p[2]", $reviewNode)->item(0)
                            ?: $xp->query(".//p[contains(@class, 'review-item-post-date')]", $reviewNode)->item(0);
                        if ($dateNode) {
                            $reviewDate = str_replace('掲載日：', '', trim($dateNode->textContent));
                        }

                        $ratingNode = $xp->query(".//span[contains(@class, 'total_rate')]", $reviewNode)->item(0)
                            ?: $xp->query(".//div[2]/div[1]/span", $reviewNode)->item(0);
                        if ($ratingNode) $rating = trim($ratingNode->textContent);

                        $titleNode = $xp->query(".//div[2]/div[2]", $reviewNode)->item(0)
                            ?: $xp->query(".//div[contains(@class, 'review-item-title')]", $reviewNode)->item(0);
                        if ($titleNode) $title = trim($titleNode->textContent);

                        $contentNode = $xp->query(".//p[contains(@class, 'review-item-post')]", $reviewNode)->item(0)
                            ?: $xp->query(".//div[2]/p[1]", $reviewNode)->item(0);
                        if ($contentNode) $content = trim($contentNode->textContent);

                        $commentNode = $xp->query(".//div[2]/div[5]/div/p", $reviewNode)->item(0)
                            ?: $xp->query(".//p[contains(@class, 'review-item-reply-body')]", $reviewNode)->item(0);
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
