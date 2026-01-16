<?php
/**
 * ヘブンネット キャストスクレイピング
 * 
 * 機能:
 * - 複数ページ対応（girllist/2/ 等）
 * - 全キャストスクレイピング
 * - tenant_cast_data_heaven テーブルへ保存
 * - CLI実行対応（バックグラウンド処理）
 * 
 * 使用法:
 * php scraper_heaven.php <tenant_id>
 */

// タイムアウト設定
set_time_limit(1200); // 20分
ini_set('memory_limit', '512M');
date_default_timezone_set('Asia/Tokyo');

// テナントIDの取得
$tenantId = null;
if (php_sapi_name() === 'cli') {
    $tenantId = isset($argv[1]) ? (int)$argv[1] : null;
} else {
    $tenantId = isset($_POST['tenant_id']) ? (int)$_POST['tenant_id'] : null;
}

if (!$tenantId) {
    die("テナントIDが指定されていません\n");
}

// DB接続
require_once __DIR__ . '/../../../includes/bootstrap.php';
$pdo = getPlatformDb();

// ログ出力関数
$logFile = __DIR__ . "/scraping_heaven_{$tenantId}.log";

function logOutput($message, $level = 'info') {
    global $logFile, $pdo, $tenantId;
    $maxSize = 100 * 1024;
    
    if (file_exists($logFile) && filesize($logFile) > $maxSize) {
        $all = file_get_contents($logFile);
        $tail = substr($all, -$maxSize);
        file_put_contents($logFile, $tail);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message";
    file_put_contents($logFile, "$logMessage\n", FILE_APPEND);
    
    try {
        $stmt = $pdo->prepare("INSERT INTO tenant_scraping_logs (tenant_id, scraping_type, log_level, message) VALUES (?, 'heaven', ?, ?)");
        $stmt->execute([$tenantId, $level, $message]);
    } catch (Exception $e) {}
    
    if (php_sapi_name() === 'cli') {
        echo "$logMessage\n";
    }
}

function updateStatus($status, $successCount = null, $errorCount = null, $lastError = null) {
    global $pdo, $tenantId;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO tenant_scraping_status (tenant_id, scraping_type, status, start_time, success_count, error_count)
            VALUES (?, 'heaven', ?, NOW(), 0, 0)
            ON DUPLICATE KEY UPDATE status = VALUES(status), 
                start_time = IF(VALUES(status) = 'running', NOW(), start_time), 
                end_time = IF(VALUES(status) IN ('completed', 'error'), NOW(), end_time)
        ");
        $stmt->execute([$tenantId, $status]);
        
        if ($successCount !== null || $errorCount !== null) {
            $stmt = $pdo->prepare("UPDATE tenant_scraping_status SET success_count = ?, error_count = ?, last_error = ? WHERE tenant_id = ? AND scraping_type = 'heaven'");
            $stmt->execute([$successCount ?? 0, $errorCount ?? 0, $lastError, $tenantId]);
        }
    } catch (Exception $e) {
        logOutput("ステータス更新エラー: " . $e->getMessage(), 'error');
    }
}

// ユーティリティ関数
function nullIfEmpty($val) {
    return ($val === '' || $val === null) ? null : $val;
}

function parseTimeRangeToNowStatus($time) {
    if (!$time || $time === '---') return null;
    $t = trim(str_replace('～', '~', $time));
    if (!preg_match('/^(\d{1,2}):(\d{2})\s*[~]\s*(?:翌)?(\d{1,2}):(\d{2})$/u', $t, $m)) {
        return null;
    }
    list(, $sh, $sm, $eh, $em) = $m;
    $endNext = strpos($t, '翌') !== false;
    $tz = new DateTimeZone('Asia/Tokyo');
    $today = (new DateTime('now', $tz))->format('Y-m-d');
    $start = DateTime::createFromFormat('Y-m-d H:i', "$today {$sh}:{$sm}", $tz);
    $end = DateTime::createFromFormat('Y-m-d H:i', "$today {$eh}:{$em}", $tz);
    if ($endNext) $end->modify('+1 day');
    $now = new DateTime('now', $tz);
    return ($now >= $start && $now <= $end) ? '案内中' : null;
}

function parseTimeRangeToClosedStatus($time) {
    if (!$time || $time === '---') return null;
    $t = trim(str_replace('～', '~', $time));
    if (!preg_match('/^(\d{1,2}):(\d{2})\s*[~]\s*(?:翌)?(\d{1,2}):(\d{2})$/u', $t, $m)) {
        return null;
    }
    list(, $sh, $sm, $eh, $em) = $m;
    $endNext = strpos($t, '翌') !== false;
    $tz = new DateTimeZone('Asia/Tokyo');
    $today = (new DateTime('now', $tz))->format('Y-m-d');
    $end = DateTime::createFromFormat('Y-m-d H:i', "$today {$eh}:{$em}", $tz);
    if ($endNext) $end->modify('+1 day');
    $now = new DateTime('now', $tz);
    return ($now > $end) ? '受付終了' : null;
}

// =====================================================
// メイン処理開始
// =====================================================

try {
    logOutput("========================================");
    logOutput("ヘブンネット スクレイピング開始 (tenant_id: $tenantId)");
    logOutput("========================================");
    
    updateStatus('running');
    
    // 停止状態チェック
    $stmt = $pdo->prepare("SELECT config_value FROM tenant_scraping_config WHERE tenant_id = ? AND config_key = 'heaven_enabled'");
    $stmt->execute([$tenantId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result && $result['config_value'] === '0') {
        logOutput("ヘブンネットは停止中のためスキップ");
        updateStatus('completed', 0, 0);
        exit;
    }
    
    // URL取得
    $stmt = $pdo->prepare("SELECT config_value FROM tenant_scraping_config WHERE tenant_id = ? AND config_key = 'heaven_list_url'");
    $stmt->execute([$tenantId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $configuredUrl = $result ? $result['config_value'] : '';
    
    if (empty($configuredUrl)) {
        logOutput("URLが未設定のためスキップ");
        updateStatus('completed', 0, 0);
        exit;
    }
    
    // URLからbaseUrlとshopPathを抽出
    $baseUrl = 'https://www.cityheaven.net';
    $parsedUrl = parse_url($configuredUrl);
    $shopPath = isset($parsedUrl['path']) ? rtrim($parsedUrl['path'], '/') : '';
    // girllist/などが含まれている場合は除去
    $shopPath = preg_replace('#/girllist.*$#', '', $shopPath);
    
    logOutput("設定URL: $configuredUrl");
    logOutput("shopPath: $shopPath");
    
    $ctx = stream_context_create([
        'http' => [
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'timeout' => 30,
            'ignore_errors' => true
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);
    
    // =====================================================
    // 1) 全ページからキャストURL収集
    // =====================================================
    $allCastUrls = [];
    $page = 1;
    $maxPages = 15; // 安全のため上限設定
    
    while ($page <= $maxPages) {
        $listUrl = $page === 1 
            ? "$baseUrl$shopPath/girllist/"
            : "$baseUrl$shopPath/girllist/{$page}/";
        
        logOutput("一覧ページ取得: $listUrl");
        
        $html = @file_get_contents($listUrl, false, $ctx);
        
        if ($html === false) {
            logOutput("一覧ページ取得失敗: $listUrl");
            break;
        }
        
        logOutput("HTML取得成功: " . strlen($html) . " bytes");
        
        // キャストURLを抽出
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        @$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xp = new DOMXPath($doc);
        
        $links = $xp->query('//a[contains(@href, "/girlid-")]');
        $foundCount = 0;
        
        foreach ($links as $link) {
            if (!($link instanceof DOMElement)) continue;
            $href = $link->getAttribute('href');
            if (preg_match('#/girlid-(\d+)/?$#', $href, $m)) {
                $girlId = $m[1];
                if (!isset($allCastUrls[$girlId])) {
                    $allCastUrls[$girlId] = $baseUrl . $href;
                    $foundCount++;
                }
            }
        }
        
        logOutput("  ページ{$page}: {$foundCount}人発見（累計: " . count($allCastUrls) . "人）");
        
        // 次のページがあるか確認
        $nextPage = $page + 1;
        if (strpos($html, "girllist/{$nextPage}/") === false) {
            logOutput("  次ページなし → 収集完了");
            break;
        }
        
        $page++;
        sleep(1); // サーバー負荷軽減
    }
    
    $totalCasts = count($allCastUrls);
    logOutput("キャスト総数: {$totalCasts}人");
    
    if ($totalCasts === 0) {
        logOutput("キャストが見つかりません。処理を終了します。");
        updateStatus('completed', 0, 0);
        exit;
    }
    
    // =====================================================
    // 2) スクレイピング開始時刻を記録（完了後に古いデータを削除するため）
    // =====================================================
    $scrapingStartTime = date('Y-m-d H:i:s');
    logOutput("スクレイピング開始時刻: $scrapingStartTime");
    
    // =====================================================
    // 3) 全キャスト詳細ページをキャッシュ取得し、日付を収集
    // =====================================================
    logOutput("全キャスト詳細ページをキャッシュ中...");
    
    $cache = [];
    $default_days = array_fill(1, 7, null);
    $cacheIdx = 0;
    
    foreach ($allCastUrls as $girlId => $detailUrl) {
        $cacheIdx++;
        sleep(1); // サーバー負荷軽減
        
        $html2 = @file_get_contents($detailUrl, false, $ctx);
        $cache[$girlId] = $html2;
        
        if ($html2 === false) {
            logOutput("  [{$cacheIdx}/{$totalCasts}] girlid-{$girlId}: 取得失敗");
            continue;
        }
        
        logOutput("  [{$cacheIdx}/{$totalCasts}] girlid-{$girlId}: キャッシュOK");
        
        // 日付を収集
        if (preg_match_all('/<dl[^>]*>\s*<dt[^>]*>\s*(\d{1,2}\/\d{1,2}\([^)]+\))\s*<\/dt>/i', $html2, $dlMatches)) {
            for ($j = 0; $j < min(7, count($dlMatches[1])); $j++) {
                $dayIdx = $j + 1;
                if ($default_days[$dayIdx] === null) {
                    $default_days[$dayIdx] = trim($dlMatches[1][$j]);
                }
            }
        }
    }
    
    // 基準日付の確認（収集できなかった場合は自動生成）
    $weekdays = array('日', '月', '火', '水', '木', '金', '土');
    for ($i = 1; $i <= 7; $i++) {
        if ($default_days[$i] === null) {
            $date = new DateTime('now', new DateTimeZone('Asia/Tokyo'));
            $date->modify('+' . ($i - 1) . ' days');
            $month = $date->format('n');
            $day = $date->format('j');
            $wday = $weekdays[(int)$date->format('w')];
            $default_days[$i] = "{$month}/{$day}({$wday})";
        }
    }
    
    logOutput("基準日付確定: {$default_days[1]} 〜 {$default_days[7]}");
    
    // =====================================================
    // 4) キャッシュからデータ抽出・DB保存
    // =====================================================
    $sort = 1;
    $successCount = 0;
    $errorCount = 0;
    
    // INSERT文を準備
    $sql = "
        INSERT INTO tenant_cast_data_heaven (
            tenant_id, heaven_id, name, name_romaji, sort_order,
            cup, age, height, size,
            pr_title, pr_text, `new`,
            today, `now`, closed,
            img1, img2, img3, img4, img5,
            day1, day2, day3, day4, day5, day6, day7,
            checked, missing_count, source_url, updated_at
        ) VALUES (
            :tenant_id, :heaven_id, :name, :name_romaji, :sort_order,
            :cup, :age, :height, :size,
            :pr_title, :pr_text, :new,
            :today, :now, :closed,
            :img1, :img2, :img3, :img4, :img5,
            :day1, :day2, :day3, :day4, :day5, :day6, :day7,
            1, 0, :source_url, NOW()
        )
        ON DUPLICATE KEY UPDATE
            name_romaji = VALUES(name_romaji),
            sort_order = VALUES(sort_order),
            cup = VALUES(cup),
            age = VALUES(age),
            height = VALUES(height),
            size = VALUES(size),
            pr_title = VALUES(pr_title),
            pr_text = VALUES(pr_text),
            `new` = VALUES(`new`),
            today = VALUES(today),
            `now` = VALUES(`now`),
            closed = VALUES(closed),
            img1 = VALUES(img1),
            img2 = VALUES(img2),
            img3 = VALUES(img3),
            img4 = VALUES(img4),
            img5 = VALUES(img5),
            day1 = VALUES(day1),
            day2 = VALUES(day2),
            day3 = VALUES(day3),
            day4 = VALUES(day4),
            day5 = VALUES(day5),
            day6 = VALUES(day6),
            day7 = VALUES(day7),
            checked = 1,
            missing_count = 0,
            source_url = VALUES(source_url),
            updated_at = NOW()
    ";
    $stmt = $pdo->prepare($sql);
    
    foreach ($allCastUrls as $girlId => $detailUrl) {
        $name = null;
        
        try {
            $html2 = isset($cache[$girlId]) ? $cache[$girlId] : false;
            
            if ($html2 === false) {
                throw new Exception("詳細ページ取得失敗（キャッシュなし）");
            }
            
            $d2 = new DOMDocument();
            @$d2->loadHTML(mb_convert_encoding($html2, 'HTML-ENTITIES', 'UTF-8'));
            $xp2 = new DOMXPath($d2);
            
            // === 名前を取得 ===
            $name = '';
            if (preg_match('/<h2[^>]*>[\s\S]*?([ぁ-んァ-ヶー一-龠a-zA-Z]+)〔(\d+)歳〕/u', $html2, $m)) {
                $name = trim($m[1]);
            }
            
            if (empty($name)) {
                $nameNode = $xp2->query('//th[text()="名前"]/following-sibling::td')->item(0);
                $name = $nameNode ? trim($nameNode->textContent) : '';
            }
            
            if (empty($name)) {
                throw new Exception("名前が取得できませんでした");
            }
            
            // === 年齢を取得 ===
            $age = null;
            if (preg_match('/〔(\d+)歳〕/', $html2, $m)) {
                $age = (int)$m[1];
            }
            
            // === 3サイズを取得 ===
            $height = null;
            $cup = null;
            $size = null;
            
            if (preg_match('/<th[^>]*>3サイズ<\/th>\s*<td[^>]*>(.*?)<\/td>/s', $html2, $m)) {
                $sizeText = preg_replace('/[\s\t\n\r]+/', ' ', strip_tags($m[1]));
                $sizeText = trim($sizeText);
                
                if (preg_match('/T(\d+)/', $sizeText, $m2)) {
                    $height = (int)$m2[1];
                }
                if (preg_match('/\(([A-Z]+)\)/', $sizeText, $m2)) {
                    $cup = $m2[1];
                }
                if (preg_match('/(\d+)\s*\([A-Z]+\)\s*[・･]\s*(\d+)\s*[・･]\s*(\d+)/u', $sizeText, $m2)) {
                    $size = "B:{$m2[1]} W:{$m2[2]} H:{$m2[3]}";
                }
            }
            
            // === PRタイトルを取得 ===
            $pr_title = '';
            if (preg_match('/&nbsp;(?:&nbsp;)+([^<\r\n]+)/i', $html2, $m)) {
                $pr_title = trim(html_entity_decode($m[1]));
                $pr_title = preg_replace('/\s+/', '', $pr_title);
                if ($pr_title === '新人') {
                    $pr_title = '';
                }
            }
            
            // === PR本文を取得 ===
            $pr_text = '';
            if (preg_match('/店長からのコメント[\s\S]*?<p[^>]*>([\s\S]*?)<\/p>/i', $html2, $m)) {
                $pr_text = $m[1];
                $pr_text = preg_replace('/<br\s*\/?>/i', "\n", $pr_text);
                $pr_text = strip_tags($pr_text);
                $pr_text = preg_replace('/[\r\n]+/', "\n", $pr_text);
                $lines = explode("\n", $pr_text);
                $lines = array_map('trim', $lines);
                $lines = array_filter($lines, function($line) { return $line !== ''; });
                $pr_text = implode("\n", $lines);
                $pr_text = trim($pr_text);
            }
            
            // === 新人フラグ ===
            $new = (strpos($html2, 'alt="新人"') !== false) ? '新人' : null;
            
            // === 出勤スケジュール（7日分）===
            $times = [];
            for ($d = 1; $d <= 7; $d++) {
                $times[$d] = null;
            }
            
            if (preg_match_all('/<dl[^>]*>\s*<dt[^>]*>\s*(\d{1,2}\/\d{1,2}\([^)]+\))\s*<\/dt>\s*<dd[^>]*>([\s\S]*?)<\/dd>\s*<\/dl>/i', $html2, $dlMatches, PREG_SET_ORDER)) {
                $dayIdx = 0;
                foreach ($dlMatches as $dlMatch) {
                    $dayIdx++;
                    if ($dayIdx > 7) break;
                    
                    $ddHtml = $dlMatch[2];
                    $ddText = strip_tags(str_replace('<br />', ' ', $ddHtml));
                    $ddText = preg_replace('/[\s\t\n\r]+/', ' ', $ddText);
                    $ddText = trim($ddText);
                    
                    if (empty($ddText) || strpos($ddText, '休み') !== false) {
                        $times[$dayIdx] = null;
                    } elseif (preg_match('/(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})/', $ddText, $timeMatch)) {
                        $startTime = $timeMatch[1];
                        $endTime = $timeMatch[2];
                        
                        list($startH, $startM) = explode(':', $startTime);
                        list($endH, $endM) = explode(':', $endTime);
                        
                        if ((int)$endH < (int)$startH) {
                            $endTime = '翌' . $endTime;
                        }
                        
                        $times[$dayIdx] = $startTime . '~' . $endTime;
                    }
                }
            }
            
            // === 案内中／受付終了判定 ===
            $time_1 = $times[1];
            $today = ($time_1 && $time_1 !== '---') ? '本日出勤' : null;
            $now_status = parseTimeRangeToNowStatus($time_1);
            $closed = parseTimeRangeToClosedStatus($time_1) ? 1 : 0;
            
            // === 画像URL（最大5枚）===
            $images = ['img1' => null, 'img2' => null, 'img3' => null, 'img4' => null, 'img5' => null];
            if (preg_match_all('/data-echo="([^"]*\/\/img2\.cityheaven\.net\/img\/girls[^"]*)"/i', $html2, $imgMatches)) {
                $imgIdx = 1;
                foreach ($imgMatches[1] as $src) {
                    if ($imgIdx > 5) break;
                    if (strpos($src, '//') === 0) {
                        $src = 'https:' . $src;
                    }
                    $images['img' . $imgIdx] = $src;
                    $imgIdx++;
                }
            }
            
            // === 名前をローマ字に変換 ===
            $name_romaji = mb_convert_kana($name, 'r', 'UTF-8');
            $name_romaji = str_replace(
                ['あ', 'い', 'う', 'え', 'お', 'か', 'き', 'く', 'け', 'こ', 'さ', 'し', 'す', 'せ', 'そ', 'た', 'ち', 'つ', 'て', 'と', 'な', 'に', 'ぬ', 'ね', 'の', 'は', 'ひ', 'ふ', 'へ', 'ほ', 'ま', 'み', 'む', 'め', 'も', 'や', 'ゆ', 'よ', 'ら', 'り', 'る', 'れ', 'ろ', 'わ', 'を', 'ん'],
                ['a', 'i', 'u', 'e', 'o', 'ka', 'ki', 'ku', 'ke', 'ko', 'sa', 'shi', 'su', 'se', 'so', 'ta', 'chi', 'tsu', 'te', 'to', 'na', 'ni', 'nu', 'ne', 'no', 'ha', 'hi', 'fu', 'he', 'ho', 'ma', 'mi', 'mu', 'me', 'mo', 'ya', 'yu', 'yo', 'ra', 'ri', 'ru', 're', 'ro', 'wa', 'wo', 'n'],
                $name_romaji
            );
            $name_romaji = strtolower($name_romaji);
            $name_romaji = preg_replace('/[^a-z0-9_-]/', '', $name_romaji);
            
            // === DB保存 ===
            $params = [
                ':tenant_id' => $tenantId,
                ':heaven_id' => $girlId,
                ':name' => $name,
                ':name_romaji' => $name_romaji,
                ':sort_order' => $sort,
                ':cup' => nullIfEmpty($cup),
                ':age' => nullIfEmpty($age),
                ':height' => nullIfEmpty($height),
                ':size' => nullIfEmpty($size),
                ':pr_title' => nullIfEmpty($pr_title),
                ':pr_text' => nullIfEmpty($pr_text),
                ':new' => $new,
                ':today' => $today,
                ':now' => $now_status,
                ':closed' => $closed,
                ':img1' => $images['img1'],
                ':img2' => $images['img2'],
                ':img3' => $images['img3'],
                ':img4' => $images['img4'],
                ':img5' => $images['img5'],
                ':day1' => nullIfEmpty($times[1]),
                ':day2' => nullIfEmpty($times[2]),
                ':day3' => nullIfEmpty($times[3]),
                ':day4' => nullIfEmpty($times[4]),
                ':day5' => nullIfEmpty($times[5]),
                ':day6' => nullIfEmpty($times[6]),
                ':day7' => nullIfEmpty($times[7]),
                ':source_url' => $detailUrl
            ];
            
            $stmt->execute($params);
            
            $successCount++;
            logOutput("✅ [{$sort}/{$totalCasts}] {$name} 保存OK");
            
        } catch (Exception $e) {
            $errorCount++;
            $failedName = $name ? $name : "girlid-{$girlId}";
            logOutput("❌ [{$sort}/{$totalCasts}] {$failedName}: " . $e->getMessage(), 'error');
        }
        
        $sort++;
    }
    
    // =====================================================
    // 5) 取得できなかったキャストのmissing_countをインクリメント
    // =====================================================
    logOutput("取得できなかったキャストをチェック中...");
    
    // 今回更新されなかったキャスト（updated_at < スクレイピング開始時刻）のmissing_count++
    $stmt = $pdo->prepare("
        UPDATE tenant_cast_data_heaven 
        SET missing_count = missing_count + 1 
        WHERE tenant_id = ? AND updated_at < ?
    ");
    $stmt->execute([$tenantId, $scrapingStartTime]);
    $missingCount = $stmt->rowCount();
    logOutput("取得失敗カウント更新: {$missingCount}件");
    
    // missing_count >= 3 のキャストを非表示に（データは残す）
    $stmt = $pdo->prepare("
        UPDATE tenant_cast_data_heaven 
        SET checked = 0 
        WHERE tenant_id = ? AND missing_count >= 3
    ");
    $stmt->execute([$tenantId]);
    $hiddenCount = $stmt->rowCount();
    logOutput("非表示に変更: {$hiddenCount}件（3回連続取得失敗）");
    
    // =====================================================
    // 6) 完了ログ
    // =====================================================
    logOutput("");
    logOutput("========================================");
    logOutput("スクレイピング完了");
    logOutput("成功: {$successCount}件 / エラー: {$errorCount}件");
    logOutput("取得失敗カウント: {$missingCount}件 / 非表示: {$hiddenCount}件");
    logOutput("========================================");
    
    updateStatus('completed', $successCount, $errorCount);
    
} catch (Exception $e) {
    logOutput("❌ 致命的エラー: " . $e->getMessage(), 'error');
    updateStatus('error', $successCount ?? 0, $errorCount ?? 0, $e->getMessage());
    exit(1);
}
