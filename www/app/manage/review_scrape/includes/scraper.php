<?php
/**
 * 口コミスクレイパー
 *
 * 参考: reference/public_html/admin/reviews/scrap.php
 *
 * 動作:
 *   UPSERT方式で全ページスクレイピング（重複は自動スキップ）
 *   ピックアップ → CityHeaven 1ページ目の先頭レビューを is_pickup=1 でマーク
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

    /**
     * curl を使った HTTP GET（CURLOPT_TIMEOUT でトータルタイムアウト制御）
     * file_get_contents は read timeout のみでトリクルレスポンスに対応できない
     */
    private function fetch($url) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            CURLOPT_ENCODING       => '',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $html = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        $time = round(curl_getinfo($ch, CURLINFO_TOTAL_TIME), 2);
        curl_close($ch);

        if ($html === false || $code !== 200) {
            $this->log("取得失敗: {$url} (HTTP {$code}, {$time}秒, {$err})");
            return false;
        }
        $this->log("取得成功: {$url} ({$code}, " . strlen($html) . " bytes, {$time}秒)");
        return $html;
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

            $baseUrl  = rtrim($this->settings['reviews_base_url'], '/') . '/';
            $delay    = max(1.0, (float)($this->settings['request_delay'] ?? 1.0));
            $maxPages = 200;

            $this->pdo->prepare("UPDATE reviews SET is_pickup=0 WHERE tenant_id=? AND is_pickup=1")->execute([$this->tenantId]);

            $upsert = $this->pdo->prepare("
                INSERT INTO reviews
                    (tenant_id, user_name, cast_name, cast_id, review_date, rating, title, content, shop_comment, source_url, source_id, is_pickup)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE
                    is_pickup  = VALUES(is_pickup),
                    updated_at = NOW()
            ");

            $fetchFailCount = 0;
            $emptyPageCount = 0;

            for ($page = 1; $page <= $maxPages; $page++) {
                if ($this->shouldStop()) { $this->log('手動停止'); break; }

                $url  = ($page === 1) ? $baseUrl : $baseUrl . $page . '/';
                $html = $this->fetch($url);

                if ($html === false) {
                    $this->stats['errors_count']++;
                    $fetchFailCount++;
                    if ($fetchFailCount >= 10) {
                        $this->log("累計10回取得失敗 → 終了");
                        break;
                    }
                    sleep((int)ceil($delay));
                    continue;
                }

                $fetchFailCount = 0;

                libxml_use_internal_errors(true);
                $doc = new \DOMDocument();
                $doc->loadHTML($html);
                $xp = new \DOMXPath($doc);

                $nodes = $xp->query('//ul/li[contains(@class, "review-item")]');
                if (!$nodes || $nodes->length === 0) {
                    $this->log("ページ {$page}: review-item なし (" . strlen($html) . " bytes)");
                    $emptyPageCount++;
                    if ($emptyPageCount >= 5) {
                        $this->log("累計5ページ空 → 全ページ完了と判断");
                        break;
                    }
                    $this->stats['pages_processed']++;
                    $this->updateProgress();
                    sleep((int)ceil($delay));
                    continue;
                }

                $emptyPageCount = 0;
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
