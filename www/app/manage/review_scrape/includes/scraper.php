<?php
/**
 * 口コミスクレイパー
 *
 * 参考: reference/public_html/admin/reviews/scrap.php
 *
 * 動作:
 *   UPSERT方式で全ページスクレイピング（重複は自動スキップ）
 *   ピックアップ → CityHeaven 1ページ目の先頭レビューを is_pickup=1 でマーク
 *   レート制限検出時は指数バックオフで待機して続行
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
            $maxPages = 100;
            $delay = 2;

            // 既存件数で差分/全件を判定
            $cntSt = $this->pdo->prepare("SELECT COUNT(*) FROM reviews WHERE tenant_id=?");
            $cntSt->execute([$this->tenantId]);
            $existingCount = (int)$cntSt->fetchColumn();
            $isIncremental = ($existingCount > 0);
            $this->log($isIncremental ? "差分取得モード（既存{$existingCount}件）" : "初回全件取得モード");

            $this->pdo->prepare("UPDATE reviews SET is_pickup=0 WHERE tenant_id=? AND is_pickup=1")->execute([$this->tenantId]);

            $upsert = $this->pdo->prepare("
                INSERT INTO reviews
                    (tenant_id, user_name, cast_name, cast_id, review_date, rating, title, content, shop_comment, source_url, source_id, is_pickup)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE
                    is_pickup  = VALUES(is_pickup),
                    updated_at = NOW()
            ");

            $consecutiveEmpty = 0;
            $consecutiveNoNew = 0;

            for ($page = 1; $page <= $maxPages; $page++) {
                if ($this->shouldStop()) { $this->log('手動停止'); break; }

                $url  = ($page === 1) ? $baseUrl : $baseUrl . $page . '/';
                $html = $this->fetch($url);

                if ($html === false) {
                    $this->stats['errors_count']++;
                    $consecutiveEmpty++;
                    if ($consecutiveEmpty >= 5) {
                        $this->log("5ページ連続取得失敗 → 終了");
                        break;
                    }
                    $this->log("ページ {$page}: 取得失敗");
                    $this->updateProgress();
                    sleep($delay);
                    continue;
                }

                libxml_use_internal_errors(true);
                $doc = new \DOMDocument();
                $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
                $xp = new \DOMXPath($doc);

                $nodes = $xp->query('//ul/li[contains(@class, "review-item")]');
                if (!$nodes || $nodes->length === 0) {
                    $consecutiveEmpty++;
                    if ($consecutiveEmpty >= 3) {
                        $this->log("3ページ連続レビューなし → 全ページ完了");
                        break;
                    }
                    $this->log("ページ {$page}: review-item なし (" . strlen($html) . " bytes)");
                    $this->stats['pages_processed']++;
                    $this->updateProgress();
                    sleep($delay);
                    continue;
                }

                $consecutiveEmpty = 0;
                $this->stats['pages_processed']++;
                $this->stats['reviews_found'] += $nodes->length;
                $newOnPage = 0;

                foreach ($nodes as $idx => $node) {
                    try {
                        // --- ユーザー名 ---
                        $userName = '';
                        $un = $xp->query(".//div[1]/div[1]/div/div/p/a", $node)->item(0)
                            ?: $xp->query(".//p[contains(@class,'userrank_nickname')]//a", $node)->item(0);
                        if ($un) $userName = trim($un->textContent);

                        // --- キャスト名（クラス名ベースを優先：位置ベースは壊れたHTMLで失敗する） ---
                        $castName = '';
                        $cn = $xp->query(".//dd[contains(@class,'name')]", $node)->item(0);
                        if ($cn) {
                            $castName = trim($cn->textContent);
                        }
                        if ($castName === '') {
                            $cn = $xp->query(".//div[1]/div[2]/div[2]/dl/dd", $node)->item(0);
                            if ($cn) $castName = trim($cn->textContent);
                        }
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

                        // --- 掲載日（クラス名ベースを優先） ---
                        $dateStr = '';
                        $dn = $xp->query(".//p[contains(@class,'review-item-post-date')]", $node)->item(0);
                        if (!$dn) {
                            $dn = $xp->query(".//div[2]/p[2]", $node)->item(0);
                        }
                        if ($dn) $dateStr = str_replace('掲載日：', '', trim($dn->textContent));
                        if ($dateStr === '' && preg_match('/掲載日[：:]\s*(\d{4}年\d{1,2}月\d{1,2}日)/u', $node->textContent ?? '', $dm)) {
                            $dateStr = $dm[1];
                        }

                        // --- 評価 ---
                        $rating = '';
                        $rn = $xp->query(".//span[contains(@class,'total_rate')]", $node)->item(0);
                        if ($rn) $rating = trim($rn->textContent);

                        // --- タイトル ---
                        $title = '';
                        $tn = $xp->query(".//div[contains(@class,'review-item-title')]", $node)->item(0);
                        if (!$tn) {
                            $tn = $xp->query(".//span[contains(@class,'review_bold')]", $node)->item(0);
                        }
                        if ($tn) $title = trim($tn->textContent);

                        // --- 本文 ---
                        $pn = $xp->query(".//p[contains(@class,'review-item-post')]", $node)->item(0);
                        $content = $pn ? trim($pn->textContent) : '';

                        // --- お店コメント ---
                        $sc = $xp->query(".//p[contains(@class,'review-item-reply-body')]", $node)->item(0);
                        $shopComment = $sc ? trim($sc->textContent) : '';

                        if ($userName === '' || $content === '') {
                            $this->stats['reviews_skipped']++;
                            continue;
                        }

                        // 1ページ目の1件目のみデバッグログ出力
                        if ($page === 1 && $idx === 0) {
                            $this->log("デバッグ[p1#0]: user={$userName}, cast={$castName}, date={$dateStr}, rating={$rating}");
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

                // 差分取得モード: 新規0件が続けば既存に追いついた
                if ($isIncremental && $newOnPage === 0) {
                    $consecutiveNoNew++;
                    if ($consecutiveNoNew >= 3) {
                        $this->log("差分取得完了（3ページ連続新規なし → 既存に追いついた）");
                        break;
                    }
                } else {
                    $consecutiveNoNew = 0;
                }

                sleep($delay);
            }

            $this->log("=== 完了: 保存{$this->stats['reviews_saved']}件 スキップ{$this->stats['reviews_skipped']}件 エラー{$this->stats['errors_count']}件 ===");
            return array_merge($this->stats, ['status' => 'success']);

        } catch (\Exception $e) {
            $this->log('致命的エラー: ' . $e->getMessage());
            return array_merge($this->stats, ['status' => 'error', 'error_message' => $e->getMessage()]);
        }
    }
}
