<?php
/**
 * 口コミスクレイパー
 *
 * 参考: reference/public_html/admin/reviews/scrap.php
 *
 * 動作:
 *   UPSERT方式で全ページスクレイピング（重複は自動スキップ）
 *   ピックアップ → CityHeaven 1ページ目の先頭レビューを is_pickup=1 でマーク
 *   3ページ連続取得失敗で終了（次回実行時にUPSERTで残りを取得可能）
 */

class ReviewScraper {
    private $tenantId;
    private $settings;
    private $pdo;
    private $logId;
    private $logFile;
    private $stats = [
        'pages_processed' => 0,
        'reviews_found'   => 0,
        'reviews_saved'   => 0,
        'reviews_skipped' => 0,
        'errors_count'    => 0,
    ];

    public function __construct($tenantId, $settings, $platformPdo, $logId = null) {
        $this->tenantId = $tenantId;
        $this->settings = $settings;
        $this->pdo      = $platformPdo;
        $this->logId    = $logId;

        $logDir = dirname(dirname(__DIR__)) . '/../../logs/review_scrape/';
        if (!is_dir($logDir)) @mkdir($logDir, 0777, true);
        $this->logFile = $logDir . "tenant_{$tenantId}_" . date('Ymd') . '.log';
    }

    private function log($msg) {
        $ts = date('Y-m-d H:i:s');
        @file_put_contents($this->logFile, "[{$ts}] {$msg}\n", FILE_APPEND);
    }

    private static function clean($name) {
        return trim(preg_replace('/\[.*?\]/', '', trim($name)));
    }

    private static function parseDate($s) {
        return preg_match('/(\d{4})年(\d{1,2})月(\d{1,2})日/', $s, $m)
            ? sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3])
            : null;
    }

    private function getCastId($name) {
        $clean = self::clean($name);
        if ($clean === '') return null;
        $st = $this->pdo->prepare(
            "SELECT id FROM tenant_casts WHERE tenant_id=? AND name=? AND checked=1 LIMIT 1"
        );
        $st->execute([$this->tenantId, $clean]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r ? (int)$r['id'] : null;
    }

    private function makeSourceId($userName, $castName, $date, $content) {
        return md5($this->tenantId . ':' . $userName . ':' . $castName . ':' . $date . ':' . mb_substr($content, 0, 200));
    }

    private function shouldStop() {
        if (!$this->logId) return false;
        $st = $this->pdo->prepare("SELECT status FROM review_scrape_logs WHERE id=?");
        $st->execute([$this->logId]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
        return $r && $r['status'] === 'error';
    }

    private function updateProgress() {
        if (!$this->logId) return;
        $st = $this->pdo->prepare("UPDATE review_scrape_logs SET pages_processed=?, reviews_found=?, reviews_saved=?, reviews_skipped=?, errors_count=? WHERE id=?");
        $st->execute([$this->stats['pages_processed'], $this->stats['reviews_found'], $this->stats['reviews_saved'], $this->stats['reviews_skipped'], $this->stats['errors_count'], $this->logId]);
    }

    /** リトライ付き HTTP GET（最大2回試行） */
    private function fetch($url, $ctx) {
        for ($i = 1; $i <= 2; $i++) {
            $html = @file_get_contents($url, false, $ctx);
            if ($html !== false && $html !== '') return $html;
            if ($i < 2) {
                $this->log("リトライ: {$url}");
                sleep(3);
            }
        }
        return false;
    }

    private function ensureSchema() {
        try {
            $this->pdo->exec("ALTER TABLE reviews ADD COLUMN is_pickup TINYINT(1) NOT NULL DEFAULT 0 AFTER source_id");
        } catch (\Exception $e) {}
        try {
            $this->pdo->exec("CREATE INDEX idx_pickup ON reviews (tenant_id, is_pickup)");
        } catch (\Exception $e) {}
    }

    public function execute() {
        try {
            $this->log('=== 口コミスクレイピング開始 ===');
            $this->ensureSchema();

            $baseUrl = rtrim($this->settings['reviews_base_url'], '/') . '/';
            $timeout = min(15, (int)($this->settings['timeout'] ?? 15));
            $delay   = max(3.0, (float)($this->settings['request_delay'] ?? 3.0));

            ini_set('default_socket_timeout', (string)$timeout);

            $ctx = stream_context_create(['http' => [
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'timeout'    => $timeout,
                'header'     => "Accept: text/html\r\nAccept-Language: ja\r\n",
            ]]);

            $this->pdo->prepare("UPDATE reviews SET is_pickup=0 WHERE tenant_id=? AND is_pickup=1")->execute([$this->tenantId]);

            $upsert = $this->pdo->prepare("
                INSERT INTO reviews
                    (tenant_id, user_name, cast_name, cast_id, review_date, rating, title, content, shop_comment, source_url, source_id, is_pickup)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE
                    is_pickup  = VALUES(is_pickup),
                    updated_at = NOW()
            ");

            $consecutiveFail = 0;

            for ($page = 1; $page <= 500; $page++) {
                if ($this->shouldStop()) { $this->log('手動停止'); break; }

                $url  = ($page === 1) ? $baseUrl : $baseUrl . $page . '/';
                $html = $this->fetch($url, $ctx);

                if ($html === false) {
                    $this->log("ページ {$page}: 取得失敗");
                    $this->stats['errors_count']++;
                    $consecutiveFail++;
                    if ($consecutiveFail >= 3) {
                        $this->log('3ページ連続取得失敗 → 終了（次回実行で続行可能）');
                        break;
                    }
                    $this->log("レート制限の可能性 → 20秒待機後に続行");
                    $this->updateProgress();
                    sleep(20);
                    continue;
                }

                libxml_use_internal_errors(true);
                $doc = new \DOMDocument();
                $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
                $xp = new \DOMXPath($doc);

                $nodes = $xp->query('//ul/li[contains(@class, "review-item")]');
                if (!$nodes || $nodes->length === 0) {
                    $this->log("ページ {$page}: review-item なし (HTML " . strlen($html) . " bytes)");
                    $consecutiveFail++;
                    if ($consecutiveFail >= 3) {
                        $this->log('3ページ連続レビューなし → 終了');
                        break;
                    }
                    $this->stats['pages_processed']++;
                    $this->updateProgress();
                    sleep((int)ceil($delay));
                    continue;
                }

                $consecutiveFail = 0;
                $this->stats['pages_processed']++;
                $this->stats['reviews_found'] += $nodes->length;
                $newOnPage = 0;

                foreach ($nodes as $idx => $node) {
                    try {
                        $ri = $idx + 1;

                        $userName = '';
                        $un = $xp->query(".//div[1]/div[1]/div/div/p/a", $node)->item(0)
                            ?: $xp->query(".//p[contains(@class,'userrank_nickname_shogo')]//a", $node)->item(0);
                        if ($un) $userName = trim($un->textContent);

                        $castName = '';
                        $cn = $xp->query(".//div[1]/div[2]/div[2]/dl/dd", $node)->item(0)
                            ?: $xp->query(".//dd[contains(@class,'name')]", $node)->item(0);
                        if ($cn) $castName = trim($cn->textContent);
                        if ($castName === '') {
                            $dts = $xp->query(".//dt", $node);
                            for ($d = 0; $dts && $d < $dts->length; $d++) {
                                if (mb_strpos($dts->item($d)->textContent, '遊んだ女の子') !== false) {
                                    $dd = $xp->query("following-sibling::dd", $dts->item($d))->item(0);
                                    if ($dd) { $castName = trim($dd->textContent); break; }
                                }
                            }
                        }
                        if ($castName === '') {
                            $txt = $node->textContent ?? '';
                            if (preg_match('/遊んだ女の子\s*([^\[\n\r]{1,30})/u', $txt, $cm)) {
                                $castName = trim($cm[1]);
                            }
                        }

                        $dateStr = '';
                        $dn = $xp->query(".//div[2]/p[2]", $node)->item(0)
                            ?: $xp->query(".//p[contains(@class,'review-item-post-date')]", $node)->item(0);
                        if ($dn) $dateStr = str_replace('掲載日：', '', trim($dn->textContent));
                        if ($dateStr === '' && preg_match('/掲載日[：:]\s*(\d{4}年\d{1,2}月\d{1,2}日)/u', $node->textContent ?? '', $dm)) {
                            $dateStr = $dm[1];
                        }

                        $rating = '';
                        $rn = $xp->query(".//span[contains(@class,'total_rate')]", $node)->item(0)
                            ?: $xp->query(".//div[2]/div[1]/span", $node)->item(0);
                        if ($rn) $rating = trim($rn->textContent);

                        $title = '';
                        $tn = $xp->query(".//div[2]/div[2]", $node)->item(0)
                            ?: $xp->query(".//div[contains(@class,'review-item-title')]", $node)->item(0);
                        if ($tn) $title = trim($tn->textContent);

                        if ($ri === 1) {
                            $pn = $xp->query(".//p[contains(@class,'review-item-post')]", $node)->item(0);
                        } else {
                            $pn = $xp->query(".//div[2]/p[1]", $node)->item(0);
                        }
                        if (!$pn) $pn = $xp->query(".//p[contains(@class,'review-item-post')]", $node)->item(0);
                        $content = $pn ? trim($pn->textContent) : '';

                        $sc = $xp->query(".//div[2]/div[5]/div/p", $node)->item(0)
                            ?: $xp->query(".//p[contains(@class,'review-item-reply-body')]", $node)->item(0);
                        $shopComment = $sc ? trim($sc->textContent) : '';

                        if ($userName === '' || $content === '') {
                            $this->stats['reviews_skipped']++;
                            continue;
                        }

                        $cleanCast  = self::clean($castName);
                        $parsedDate = self::parseDate($dateStr);
                        $parsedRate = floatval(trim($rating));
                        $castId     = $this->getCastId($castName);
                        $sourceId   = $this->makeSourceId($userName, $cleanCast, $parsedDate ?? '', $content);
                        $isPickup   = ($page === 1 && $idx === 0) ? 1 : 0;

                        $upsert->execute([
                            $this->tenantId, $userName, $cleanCast ?: null, $castId,
                            $parsedDate, $parsedRate ?: null, $title ?: null, $content,
                            $shopComment ?: null, $url, $sourceId, $isPickup
                        ]);

                        if ($upsert->rowCount() === 1) {
                            $newOnPage++;
                            $this->stats['reviews_saved']++;
                        } else {
                            $this->stats['reviews_skipped']++;
                        }

                    } catch (\Exception $e) {
                        $this->stats['errors_count']++;
                        $this->log("レビュー処理エラー(p{$page} #{$idx}): " . $e->getMessage());
                    }
                }

                $this->log("ページ {$page}: {$nodes->length}件中 新規{$newOnPage}件");
                $this->updateProgress();

                sleep((int)ceil($delay));
            }

            $this->log("=== 完了: 保存{$this->stats['reviews_saved']}件 スキップ{$this->stats['reviews_skipped']}件 エラー{$this->stats['errors_count']}件 ===");
            return array_merge($this->stats, ['status' => 'success']);

        } catch (\Exception $e) {
            $this->log('致命的エラー: ' . $e->getMessage());
            return array_merge($this->stats, ['status' => 'error', 'error_message' => $e->getMessage()]);
        }
    }
}
