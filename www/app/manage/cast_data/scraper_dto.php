<?php
/**
 * デリヘルタウン キャストスクレイピング
 * 
 * 機能:
 * - dto.jp から全キャストスクレイピング
 * - tenant_cast_data_dto テーブルへ保存
 * - CLI実行対応（バックグラウンド処理）
 * 
 * 使用法:
 * php scraper_dto.php <tenant_id>
 */

// タイムアウト設定
set_time_limit(1200); // 20分
ini_set('memory_limit', '512M');
date_default_timezone_set('Asia/Tokyo');

// テナントIDの取得
$tenantId = null;
if (php_sapi_name() === 'cli') {
    $tenantId = isset($argv[1]) ? (int) $argv[1] : null;
} else {
    $tenantId = isset($_POST['tenant_id']) ? (int) $_POST['tenant_id'] : null;
}

if (!$tenantId) {
    die("テナントIDが指定されていません\n");
}

// DB接続
require_once __DIR__ . '/../../../includes/bootstrap.php';
$pdo = getPlatformDb();

// ログ出力関数
$logFile = __DIR__ . "/scraping_dto_{$tenantId}.log";

function logOutput($message, $level = 'info')
{
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
        $stmt = $pdo->prepare("INSERT INTO tenant_scraping_logs (tenant_id, scraping_type, log_level, message) VALUES (?, 'dto', ?, ?)");
        $stmt->execute([$tenantId, $level, $message]);
    } catch (Exception $e) {
    }

    if (php_sapi_name() === 'cli') {
        echo "$logMessage\n";
    }
}

function updateStatus($status, $successCount = null, $errorCount = null, $lastError = null)
{
    global $pdo, $tenantId;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO tenant_scraping_status (tenant_id, scraping_type, status, start_time, success_count, error_count)
            VALUES (?, 'dto', ?, NOW(), 0, 0)
            ON DUPLICATE KEY UPDATE status = VALUES(status), 
                start_time = IF(VALUES(status) = 'running', NOW(), start_time), 
                end_time = IF(VALUES(status) IN ('completed', 'error'), NOW(), end_time)
        ");
        $stmt->execute([$tenantId, $status]);

        if ($successCount !== null || $errorCount !== null) {
            $stmt = $pdo->prepare("UPDATE tenant_scraping_status SET success_count = ?, error_count = ?, last_error = ? WHERE tenant_id = ? AND scraping_type = 'dto'");
            $stmt->execute([$successCount ?? 0, $errorCount ?? 0, $lastError, $tenantId]);
        }
    } catch (Exception $e) {
        logOutput("ステータス更新エラー: " . $e->getMessage(), 'error');
    }
}

// ユーティリティ関数
function nullIfEmpty($val)
{
    return ($val === '' || $val === null || $val === '---' || $val === '-') ? null : $val;
}

function parseTimeRangeToNowStatus($time)
{
    if (!$time || $time === '---' || $time === '-')
        return null;
    $t = trim(str_replace(['～', '〜'], '~', $time));
    if (!preg_match('/^(\d{1,2}):(\d{2})\s*[~]\s*(?:翌)?(\d{1,2}):(\d{2})$/u', $t, $m)) {
        return null;
    }
    list(, $sh, $sm, $eh, $em) = $m;
    $endNext = strpos($t, '翌') !== false;
    $tz = new DateTimeZone('Asia/Tokyo');
    $today = (new DateTime('now', $tz))->format('Y-m-d');
    $start = DateTime::createFromFormat('Y-m-d H:i', "$today {$sh}:{$sm}", $tz);
    $end = DateTime::createFromFormat('Y-m-d H:i', "$today {$eh}:{$em}", $tz);
    if ($endNext)
        $end->modify('+1 day');
    $now = new DateTime('now', $tz);
    return ($now >= $start && $now <= $end) ? '案内中' : null;
}

function parseTimeRangeToClosedStatus($time)
{
    if (!$time || $time === '---' || $time === '-')
        return null;
    $t = trim(str_replace(['～', '〜'], '~', $time));
    if (!preg_match('/^(\d{1,2}):(\d{2})\s*[~]\s*(?:翌)?(\d{1,2}):(\d{2})$/u', $t, $m)) {
        return null;
    }
    list(, $sh, $sm, $eh, $em) = $m;
    $endNext = strpos($t, '翌') !== false;
    $tz = new DateTimeZone('Asia/Tokyo');
    $today = (new DateTime('now', $tz))->format('Y-m-d');
    $end = DateTime::createFromFormat('Y-m-d H:i', "$today {$eh}:{$em}", $tz);
    if ($endNext)
        $end->modify('+1 day');
    $now = new DateTime('now', $tz);
    return ($now > $end) ? '受付終了' : null;
}

// =====================================================
// メイン処理開始
// =====================================================

try {
    logOutput("========================================");
    logOutput("デリヘルタウン スクレイピング開始 (tenant_id: $tenantId)");
    logOutput("========================================");

    // 排他制御: 既に実行中かチェック
    $stmt = $pdo->prepare("
        SELECT status, start_time 
        FROM tenant_scraping_status 
        WHERE tenant_id = ? AND scraping_type = 'dto'
    ");
    $stmt->execute([$tenantId]);
    $currentStatus = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($currentStatus && $currentStatus['status'] === 'running') {
        $startTime = strtotime($currentStatus['start_time']);
        $elapsed = time() - $startTime;

        // 30分未満なら実行中とみなして終了
        if ($elapsed < 1800) {
            logOutput("⚠️ 既に別のプロセスが実行中のため終了します（経過時間: " . round($elapsed / 60) . "分）");
            exit;
        } else {
            logOutput("⚠️ 前回のプロセスが長時間（30分以上）終了していないため、強制的に再実行します");
        }
    }

    updateStatus('running');

    // 停止状態チェック
    $stmt = $pdo->prepare("SELECT config_value FROM tenant_scraping_config WHERE tenant_id = ? AND config_key = 'dto_enabled'");
    $stmt->execute([$tenantId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result && $result['config_value'] === '0') {
        logOutput("デリヘルタウンは停止中のためスキップ");
        updateStatus('completed', 0, 0);
        exit;
    }

    // URL取得
    $stmt = $pdo->prepare("SELECT config_value FROM tenant_scraping_config WHERE tenant_id = ? AND config_key = 'dto_list_url'");
    $stmt->execute([$tenantId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $configuredUrl = $result ? $result['config_value'] : '';

    if (empty($configuredUrl)) {
        logOutput("URLが未設定のためスキップ");
        updateStatus('completed', 0, 0);
        exit;
    }

    $baseUrl = 'https://www.dto.jp';
    // URLからshopIdを抽出
    $shopId = '';
    if (preg_match('#/shop/(\d+)#', $configuredUrl, $matches)) {
        $shopId = $matches[1];
    }

    if (empty($shopId)) {
        throw new Exception("URLからショップIDを取得できません: $configuredUrl");
    }

    logOutput("設定URL: $configuredUrl");
    logOutput("shopId: $shopId");

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
    // 1) キャスト一覧からURL収集
    // =====================================================
    $listUrl = "$baseUrl/shop/$shopId/gals";
    logOutput("一覧ページ取得: $listUrl");

    $html = @file_get_contents($listUrl, false, $ctx);

    if ($html === false) {
        throw new Exception("一覧ページ取得失敗");
    }

    logOutput("HTML取得成功: " . strlen($html) . " bytes");

    // キャストURLを抽出（/gal/数字 形式）
    $allCastUrls = [];
    if (preg_match_all('#href="/gal/(\d+)"#', $html, $matches)) {
        foreach ($matches[1] as $galId) {
            if (!isset($allCastUrls[$galId])) {
                $allCastUrls[$galId] = "$baseUrl/gal/$galId";
            }
        }
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

    foreach ($allCastUrls as $galId => $detailUrl) {
        $cacheIdx++;
        sleep(1);

        $html2 = @file_get_contents($detailUrl, false, $ctx);
        $cache[$galId] = $html2;

        if ($html2 === false) {
            logOutput("  [{$cacheIdx}/{$totalCasts}] galid-{$galId}: 取得失敗");
            continue;
        }

        logOutput("  [{$cacheIdx}/{$totalCasts}] galid-{$galId}: キャッシュOK");

        // 日付を収集（テーブルのヘッダー行から）
        if (preg_match_all('/<t[hd][^>]*>\s*(\d{1,2}\/\d{1,2}\([日月火水木金土]\))\s*<\/t[hd]>/i', $html2, $dateMatches)) {
            for ($j = 0; $j < min(7, count($dateMatches[1])); $j++) {
                $dayIdx = $j + 1;
                if ($default_days[$dayIdx] === null) {
                    $default_days[$dayIdx] = trim($dateMatches[1][$j]);
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
            $wday = $weekdays[(int) $date->format('w')];
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
        INSERT INTO tenant_cast_data_dto (
            tenant_id, dto_id, name, name_romaji, sort_order,
            cup, age, height, size,
            pr_title, pr_text, `new`,
            today, `now`, closed,
            img1, img2, img3, img4, img5,
            day1, day2, day3, day4, day5, day6, day7,
            checked, missing_count, source_url, updated_at
        ) VALUES (
            :tenant_id, :dto_id, :name, :name_romaji, :sort_order,
            :cup, :age, :height, :size,
            :pr_title, :pr_text, :new,
            :today, :now, :closed,
            :img1, :img2, :img3, :img4, :img5,
            :day1, :day2, :day3, :day4, :day5, :day6, :day7,
            1, 0, :source_url, NOW()
        )
        ON DUPLICATE KEY UPDATE
            dto_id = VALUES(dto_id),
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

    foreach ($allCastUrls as $galId => $detailUrl) {
        $name = null;

        try {
            $html2 = isset($cache[$galId]) ? $cache[$galId] : false;

            if ($html2 === false) {
                throw new Exception("詳細ページ取得失敗（キャッシュなし）");
            }

            $d2 = new DOMDocument();
            @$d2->loadHTML(mb_convert_encoding($html2, 'HTML-ENTITIES', 'UTF-8'));
            $xp2 = new DOMXPath($d2);

            // === 名前を取得（h2要素）===
            $nameNode = $xp2->query('//h2')->item(0);
            $name = $nameNode ? trim($nameNode->textContent) : '';

            if (empty($name)) {
                throw new Exception("名前が取得できませんでした");
            }

            // === PRタイトル（キャッチコピー）を取得 ===
            $pr_title = '';
            if (preg_match('/<h2[^>]*>[^<]*<\/h2>\s*<[^>]+>([^<]+)</', $html2, $m)) {
                $pr_title = trim($m[1]);
            }

            // === 年齢・身長・サイズを取得 ===
            // 例: 28歳／T157cm／B124(K)-W85-H120
            $age = null;
            $height = null;
            $cup = null;
            $size = null;

            if (preg_match('/(\d+)歳／T(\d+)cm／B?(\d+)\(([A-Z]+)\)-W?(\d+)-H?(\d+)/u', $html2, $m)) {
                $age = (int) $m[1];
                $height = (int) $m[2];
                $cup = $m[4];
                $size = "B:{$m[3]} W:{$m[5]} H:{$m[6]}";
            }

            // === PR本文（メッセージ）を取得 ===
            $pr_text = '';
            if (preg_match('/メッセージ<\/[^>]+>\s*<p[^>]*>([\s\S]*?)<\/p>/i', $html2, $m)) {
                $pr_text = $m[1];
                $pr_text = preg_replace('/<br\s*\/?>/i', "\n", $pr_text);
                $pr_text = strip_tags($pr_text);
                $pr_text = preg_replace('/[\r\n]+/', "\n", $pr_text);
                $lines = explode("\n", $pr_text);
                $lines = array_map('trim', $lines);
                $lines = array_filter($lines, function ($line) {
                    return $line !== '';
                });
                $pr_text = implode("\n", $lines);
                $pr_text = trim($pr_text);
            }

            // === 新人フラグ（入店日から判定）===
            $new = null;
            if (preg_match('/入店日[\s\S]*?(\d{4})年(\d{1,2})月(\d{1,2})日/u', $html2, $m)) {
                $entryDate = new DateTime("{$m[1]}-{$m[2]}-{$m[3]}");
                $nowDate = new DateTime();
                $diff = $nowDate->diff($entryDate)->days;
                if ($diff <= 30) {
                    $new = '新人';
                }
            }

            // === 出勤スケジュール（7日分）===
            $times = [];
            for ($d = 1; $d <= 7; $d++) {
                $times[$d] = null;
            }

            // 出勤テーブルから時間を取得
            if (preg_match('/<table[^>]*>[\s\S]*?<tr[^>]*>([\s\S]*?)<\/tr>[\s\S]*?<tr[^>]*>([\s\S]*?)<\/tr>/i', $html2, $tableMatch)) {
                // 時間行（タグを含めて全体を取得し、後でstrip_tags）
                if (preg_match_all('/<t[hd][^>]*>([\s\S]*?)<\/t[hd]>/i', $tableMatch[2], $timeMatches)) {
                    for ($j = 0; $j < min(7, count($timeMatches[1])); $j++) {
                        $timeVal = trim(strip_tags($timeMatches[1][$j]));
                        if ($timeVal === '-' || $timeVal === '' || $timeVal === '―') {
                            $times[$j + 1] = null;
                        } else {
                            $timeVal = str_replace(['～', '〜'], '~', $timeVal);
                            if (preg_match('/(\d{1,2}:\d{2})~(\d{1,2}:\d{2})/', $timeVal, $tm)) {
                                $startTime = $tm[1];
                                $endTime = $tm[2];
                                list($startH) = explode(':', $startTime);
                                list($endH) = explode(':', $endTime);
                                if ((int) $endH < (int) $startH) {
                                    $endTime = '翌' . $endTime;
                                }
                                $times[$j + 1] = $startTime . '~' . $endTime;
                            }
                        }
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
            if (preg_match_all('/src="(https?:\/\/img\.dto\.jp\/gal\/[^"]+\.(?:jpg|jpeg|png|gif|webp))"/i', $html2, $imgMatches)) {
                $allImgs = [];
                foreach ($imgMatches[1] as $src) {
                    if (strpos($src, 'logo') !== false)
                        continue;
                    if (!in_array($src, $allImgs)) {
                        $allImgs[] = $src;
                    }
                }
                // 画像が10枚ある場合、後半5枚（フルサイズ）を使用
                $totalImgs = count($allImgs);
                if ($totalImgs > 5) {
                    $fullSizeImgs = array_slice($allImgs, $totalImgs - 5, 5);
                } else {
                    $fullSizeImgs = $allImgs;
                }

                foreach ($fullSizeImgs as $idx => $src) {
                    if ($idx >= 5)
                        break;
                    $images['img' . ($idx + 1)] = $src;
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
                ':dto_id' => $galId,
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
            $failedName = $name ? $name : "galid-{$galId}";
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
        UPDATE tenant_cast_data_dto 
        SET missing_count = missing_count + 1 
        WHERE tenant_id = ? AND updated_at < ?
    ");
    $stmt->execute([$tenantId, $scrapingStartTime]);
    $missingCount = $stmt->rowCount();
    logOutput("取得失敗カウント更新: {$missingCount}件");

    // missing_count >= 3 のキャストを非表示に（データは残す）
    $stmt = $pdo->prepare("
        UPDATE tenant_cast_data_dto 
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

    // =====================================================
    // 7) 自動統合処理: active_sourceが'dto'の場合のみ
    // =====================================================
    try {
        // active_sourceを確認
        $stmt = $pdo->prepare("SELECT config_value FROM tenant_scraping_config WHERE tenant_id = ? AND config_key = 'active_source'");
        $stmt->execute([$tenantId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $activeSource = $result ? $result['config_value'] : 'ekichika'; // デフォルトは駅ちか

        if ($activeSource === 'dto') {
            logOutput("");
            logOutput("========================================");
            logOutput("自動統合処理開始: tenant_castsテーブルへの統合");
            logOutput("========================================");

            // 統合元のデータ数を確認
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tenant_cast_data_dto WHERE tenant_id = ? AND checked = 1");
            $stmt->execute([$tenantId]);
            $sourceCount = (int) $stmt->fetchColumn();

            if ($sourceCount === 0) {
                logOutput("⚠️ 統合元データが0件のためスキップ");
            } else {
                logOutput("統合元データ数: {$sourceCount}件");

                $pdo->beginTransaction();

                // 1. tenant_castsの全キャストをchecked=0に
                $pdo->prepare("UPDATE tenant_casts SET checked = 0 WHERE tenant_id = ?")->execute([$tenantId]);
                logOutput("既存データをuncheckedに設定");

                // 2. 同期元に存在するキャスト → データ上書き + checked=1
                $updateSql = "
                    UPDATE tenant_casts c
                    INNER JOIN tenant_cast_data_dto s ON c.name = s.name AND c.tenant_id = s.tenant_id
                    SET 
                        c.name_romaji = s.name_romaji,
                        c.age = s.age,
                        c.height = s.height,
                        c.cup = s.cup,
                        c.size = s.size,
                        c.pr_title = s.pr_title,
                        c.pr_text = s.pr_text,
                        c.new = s.new,
                        c.today = s.today,
                        c.now = s.now,
                        c.closed = s.closed,
                        c.img1 = s.img1,
                        c.img2 = s.img2,
                        c.img3 = s.img3,
                        c.img4 = s.img4,
                        c.img5 = s.img5,
                        c.day1 = s.day1, c.time1 = s.time1,
                        c.day2 = s.day2, c.time2 = s.time2,
                        c.day3 = s.day3, c.time3 = s.time3,
                        c.day4 = s.day4, c.time4 = s.time4,
                        c.day5 = s.day5, c.time5 = s.time5,
                        c.day6 = s.day6, c.time6 = s.time6,
                        c.day7 = s.day7, c.time7 = s.time7,
                        c.sort_order = s.sort_order,
                        c.checked = 1
                    WHERE c.tenant_id = ? AND s.checked = 1
                ";
                $stmt = $pdo->prepare($updateSql);
                $stmt->execute([$tenantId]);
                $updatedCount = $stmt->rowCount();
                logOutput("既存キャストを更新: {$updatedCount}件");

                // 3. 同期元にのみ存在する新規キャスト → INSERT
                $insertSql = "
                    INSERT INTO tenant_casts (tenant_id, name, name_romaji, age, height, cup, size, pr_title, pr_text,
                        new, today, now, closed, img1, img2, img3, img4, img5,
                        day1, time1, day2, time2, day3, time3, day4, time4,
                        day5, time5, day6, time6, day7, time7, sort_order, checked, missing_count)
                    SELECT s.tenant_id, s.name, s.name_romaji, s.age, s.height, s.cup, s.size, s.pr_title, s.pr_text,
                        s.new, s.today, s.now, s.closed, s.img1, s.img2, s.img3, s.img4, s.img5,
                        s.day1, s.time1, s.day2, s.time2, s.day3, s.time3, s.day4, s.time4,
                        s.day5, s.time5, s.day6, s.time6, s.day7, s.time7, s.sort_order, 1, 0
                    FROM tenant_cast_data_dto s
                    WHERE s.tenant_id = ? AND s.checked = 1
                        AND NOT EXISTS (SELECT 1 FROM tenant_casts c WHERE c.name = s.name AND c.tenant_id = s.tenant_id)
                ";
                $stmt = $pdo->prepare($insertSql);
                $stmt->execute([$tenantId]);
                $insertedCount = $stmt->rowCount();
                logOutput("新規キャストを追加: {$insertedCount}件");

                $pdo->commit();

                // 統合後のキャスト数を確認
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM tenant_casts WHERE tenant_id = ? AND checked = 1");
                $stmt->execute([$tenantId]);
                $finalCount = (int) $stmt->fetchColumn();

                logOutput("");
                logOutput("✅ 自動統合完了: tenant_castsに{$finalCount}件のキャストが反映されました");
                logOutput("========================================");
            }
        } else {
            logOutput("⚠️ active_sourceが'{$activeSource}'のため自動統合をスキップ");
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logOutput("❌ 自動統合エラー: " . $e->getMessage(), 'error');
        // 統合エラーでもスクレイピング自体は成功しているため、処理は継続
    }

} catch (Exception $e) {
    logOutput("❌ 致命的エラー: " . $e->getMessage(), 'error');
    updateStatus('error', $successCount ?? 0, $errorCount ?? 0, $e->getMessage());
    exit(1);
}
