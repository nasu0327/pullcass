<?php
/**
 * 口コミスクレイパー（クリーン書き直し版）
 *
 * 参考: reference/public_html/admin/reviews/scrap.php
 *
 * 動作:
 *   初回 → 全ページスクレイピング
 *   2回目以降 → 新しいページから取得し、既存レビューに到達したら停止（差分取得）
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
        'reviews_skipped'  => 0,
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

    /* ─── ヘルパー ─── */

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

    /** リトライ付き HTTP GET */
    private function fetch($url, $ctx) {
        for ($i = 1; $i <= 3; $i++) {
            $html = @file_get_contents($url, false, $ctx);
            if ($html !== false && $html !== '') return $html;
            if ($i < 3) { $this->log("リトライ {$i}: {$url}"); sleep($i * 3); }
        }
        return false;
    }

    /* ─── スキーマ保証 ─── */

    private function ensureSchema() {
        try {
            $this->pdo->exec("ALTER TABLE reviews ADD COLUMN is_pickup TINYINT(1) NOT NULL DEFAULT 0 AFTER source_id");
        } catch (\Exception $e) { /* already exists */ }
        try {
            $this->pdo->exec("CREATE INDEX idx_pickup ON reviews (tenant_id, is_pickup)");
        } catch (\Exception $e) { /* already exists */ }
    }

    /* ─── メイン実行 ─── */

    public function execute() {
        try {
            $this->log('=== 口コミスクレイピング開始 ===');
            $this->ensureSchema();

            $baseUrl = rtrim($this->settings['reviews_base_url'], '/') . '/';
            $timeout = (int)($this->settings['timeout'] ?? 30);
            $delay   = max(2.0, (float)($this->settings['request_delay'] ?? 2.0));

            $ctx = stream_context_create(['http' => [
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'timeout'    => $timeout,
                'header'     => "Accept: text/html\r\nAccept-Language: ja\r\n",
            ]]);

            // 既存件数で初回 or 差分を判定
            $cntSt = $this->pdo->prepare("SELECT COUNT(*) FROM reviews WHERE tenant_id=?");
            $cntSt->execute([$this->tenantId]);
            $existingCount = (int)$cntSt->fetchColumn();
            $isIncremental = ($existingCount > 0);
            $this->log($isIncremental ? "差分取得モード（既存{$existingCount}件）" : "初回全件取得モード");

            // ピックアップフラグをリセット
            $this->pdo->prepare("UPDATE reviews SET is_pickup=0 WHERE tenant_id=? AND is_pickup=1")->execute([$this->tenantId]);

            // INSERT ... ON DUPLICATE KEY UPDATE（差分対応）
            $upsert = $this->pdo->prepare("
                INSERT INTO reviews
                    (tenant_id, user_name, cast_name, cast_id, review_date, rating, title, content, shop_comment, source_url, source_id, is_pickup)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE
                    is_pickup  = VALUES(is_pickup),
                    updated_at = NOW()
            ");

            $consecutiveOld = 0;

            for ($page = 1; $page <= 500; $page++) {
                if ($this->shouldStop()) { $this->log('手動停止'); break; }

                $url  = ($page === 1) ? $baseUrl : $baseUrl . $page . '/';
                $html = $this->fetch($url, $ctx);

                if ($html === false) {
                    $this->log("ページ {$page}: 取得失敗");
                    $this->stats['errors_count']++;
                    $consecutiveOld++;
                    if ($consecutiveOld >= 10) { $this->log('10ページ連続空/失敗 → 終了'); break; }
                    sleep((int)ceil($delay));
                    continue;
                }

                // DOM 解析（UTF-8 明示）
                libxml_use_internal_errors(true);
                $doc = new \DOMDocument();
                $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
                $xp = new \DOMXPath($doc);

                $nodes = $xp->query('//ul/li[contains(@class, "review-item")]');
                if (!$nodes || $nodes->length === 0) {
                    $this->log("ページ {$page}: review-item なし (HTML " . strlen($html) . " bytes)");
                    $consecutiveOld++;
                    if ($consecutiveOld >= 10) { $this->log('10ページ連続レビューなし → 終了'); break; }
                    $this->stats['pages_processed']++;
                    $this->updateProgress();
                    sleep((int)ceil($delay));
                    continue;
                }

                $consecutiveOld = 0;
                $this->stats['pages_processed']++;
                $this->stats['reviews_found'] += $nodes->length;
                $newOnPage = 0;

                foreach ($nodes as $idx => $node) {
                    try {
                        $ri = $idx + 1; // 1-based

                        // --- ユーザー名 ---
                        $userName = '';
                        $un = $xp->query(".//div[1]/div[1]/div/div/p/a", $node)->item(0)
                            ?: $xp->query(".//p[contains(@class,'userrank_nickname_shogo')]//a", $node)->item(0);
                        if ($un) $userName = trim($un->textContent);

                        // --- キャスト名（参考: scrap.php XPath + regex フォールバック） ---
                        $castName = '';
                        $cn = $xp->query(".//div[1]/div[2]/div[2]/dl/dd", $node)->item(0)
                            ?: $xp->query(".//dd[contains(@class,'name')]", $node)->item(0);
                        if ($cn) $castName = trim($cn->textContent);
                        // dt「遊んだ女の子」→ 隣接 dd
                        if ($castName === '') {
                            $dts = $xp->query(".//dt", $node);
                            for ($d = 0; $dts && $d < $dts->length; $d++) {
                                if (mb_strpos($dts->item($d)->textContent, '遊んだ女の子') !== false) {
                                    $dd = $xp->query("following-sibling::dd", $dts->item($d))->item(0);
                                    if ($dd) { $castName = trim($dd->textContent); break; }
                                }
                            }
                        }
                        // textContent regex
                        if ($castName === '') {
                            $txt = $node->textContent ?? '';
                            if (preg_match('/遊んだ女の子\s*([^\[\n\r]{1,30})/u', $txt, $cm)) {
                                $castName = trim($cm[1]);
                            }
                        }
                        // raw HTML regex（エンコーディング問題の最終手段）
                        if ($castName === '' && preg_match('/遊んだ女の子.*?<dd[^>]*>\s*([^<\[]{1,30})/us', $html, $rm)) {
                            $castName = trim($rm[1]);
                        }

                        // --- 掲載日 ---
                        $dateStr = '';
                        $dn = $xp->query(".//div[2]/p[2]", $node)->item(0)
                            ?: $xp->query(".//p[contains(@class,'review-item-post-date')]", $node)->item(0);
                        if ($dn) $dateStr = str_replace('掲載日：', '', trim($dn->textContent));
                        if ($dateStr === '' && preg_match('/掲載日[：:]\s*(\d{4}年\d{1,2}月\d{1,2}日)/u', $node->textContent ?? '', $dm)) {
                            $dateStr = $dm[1];
                        }

                        // --- 評価 ---
                        $rating = '';
                        $rn = $xp->query(".//span[contains(@class,'total_rate')]", $node)->item(0)
                            ?: $xp->query(".//div[2]/div[1]/span", $node)->item(0);
                        if ($rn) $rating = trim($rn->textContent);

                        // --- タイトル ---
                        $title = '';
                        $tn = $xp->query(".//div[2]/div[2]", $node)->item(0)
                            ?: $xp->query(".//div[contains(@class,'review-item-title')]", $node)->item(0);
                        if ($tn) $title = trim($tn->textContent);

                        // --- 本文（ピックアップ/通常で異なるXPath: 参考 scrap.php 準拠） ---
                        if ($ri === 1) {
                            $pn = $xp->query(".//p[contains(@class,'review-item-post')]", $node)->item(0);
                        } else {
                            $pn = $xp->query(".//div[2]/p[1]", $node)->item(0);
                        }
                        if (!$pn) $pn = $xp->query(".//p[contains(@class,'review-item-post')]", $node)->item(0);
                        $content = $pn ? trim($pn->textContent) : '';

                        // --- お店コメント ---
                        $sc = $xp->query(".//div[2]/div[5]/div/p", $node)->item(0)
                            ?: $xp->query(".//p[contains(@class,'review-item-reply-body')]", $node)->item(0);
                        $shopComment = $sc ? trim($sc->textContent) : '';

                        // スキップ判定
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

                        // rowCount: 1=新規INSERT, 2=UPDATE(既存)
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

                // 差分取得: このページに新規が0件 → 既存に追いついた
                if ($isIncremental && $newOnPage === 0) {
                    $this->log("差分取得完了（既存レビューに到達）");
                    break;
                }

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
