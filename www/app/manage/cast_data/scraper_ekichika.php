<?php
/**
 * 駅ちか キャストスクレイピング
 * 
 * 機能:
 * - ranking-deli.jp から全キャストスクレイピング
 * - tenant_cast_data_ekichika テーブルへ保存
 * - CLI実行対応（バックグラウンド処理）
 * 
 * 使用法:
 * php scraper_ekichika.php <tenant_id>
 */

// タイムアウト設定
set_time_limit(600);
ini_set('memory_limit', '256M');
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
$logFile = __DIR__ . "/scraping_ekichika_{$tenantId}.log";

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
        $stmt = $pdo->prepare("INSERT INTO tenant_scraping_logs (tenant_id, scraping_type, log_level, message) VALUES (?, 'ekichika', ?, ?)");
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
            VALUES (?, 'ekichika', ?, NOW(), 0, 0)
            ON DUPLICATE KEY UPDATE status = VALUES(status), 
                start_time = IF(VALUES(status) = 'running', NOW(), start_time), 
                end_time = IF(VALUES(status) IN ('completed', 'error'), NOW(), end_time)
        ");
        $stmt->execute([$tenantId, $status]);

        if ($successCount !== null || $errorCount !== null) {
            $stmt = $pdo->prepare("UPDATE tenant_scraping_status SET success_count = ?, error_count = ?, last_error = ? WHERE tenant_id = ? AND scraping_type = 'ekichika'");
            $stmt->execute([$successCount ?? 0, $errorCount ?? 0, $lastError, $tenantId]);
        }
    } catch (Exception $e) {
        logOutput("ステータス更新エラー: " . $e->getMessage(), 'error');
    }
}

// ユーティリティ関数
function nullIfEmpty($val)
{
    return ($val === '' || $val === null) ? null : $val;
}

function getTextPreservingLineBreaks($node)
{
    $text = '';
    foreach ($node->childNodes as $child) {
        if ($child->nodeName === 'span')
            continue;
        if ($child->nodeType === XML_TEXT_NODE || $child->nodeType === XML_CDATA_SECTION_NODE) {
            $text .= $child->nodeValue;
        } else {
            $text .= getTextPreservingLineBreaks($child);
        }
    }
    return trim($text);
}

function parseTimeRangeToNowStatus($time)
{
    if (!$time || $time === '---')
        return null;
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
    if ($endNext)
        $end->modify('+1 day');
    $now = new DateTime('now', $tz);
    return ($now >= $start && $now <= $end) ? '案内中' : null;
}

function parseTimeRangeToClosedStatus($time)
{
    if (!$time || $time === '---')
        return null;
    $t = trim(str_replace('～', '~', $time));
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

function formatDate($date)
{
    if (empty($date))
        return $date;
    if (preg_match('/(\d+)\/(\d+)\(([^)]+)\)/', $date, $matches)) {
        $month = (int) $matches[1];
        $day = (int) $matches[2];
        $weekday = $matches[3];
        return "{$month}/{$day}({$weekday})";
    }
    return $date;
}

// =====================================================
// メイン処理開始
// =====================================================

try {
    logOutput("========================================");
    logOutput("駅ちか スクレイピング開始 (tenant_id: $tenantId)");
    logOutput("========================================");

    // 排他制御: 既に実行中かチェック
    $stmt = $pdo->prepare("
        SELECT status, start_time 
        FROM tenant_scraping_status 
        WHERE tenant_id = ? AND scraping_type = 'ekichika'
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

    // ステータスをrunningに更新（開始時間を現在時刻に）
    updateStatus('running');

    // 停止状態チェック
    $stmt = $pdo->prepare("SELECT config_value FROM tenant_scraping_config WHERE tenant_id = ? AND config_key = 'ekichika_enabled'");
    $stmt->execute([$tenantId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result && $result['config_value'] === '0') {
        logOutput("駅ちかは停止中のためスキップ");
        updateStatus('completed', 0, 0);
        exit;
    }

    // URL取得
    $stmt = $pdo->prepare("SELECT config_value FROM tenant_scraping_config WHERE tenant_id = ? AND config_key = 'ekichika_list_url'");
    $stmt->execute([$tenantId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $listUrl = $result ? $result['config_value'] : '';

    if (empty($listUrl)) {
        logOutput("URLが未設定のためスキップ");
        updateStatus('completed', 0, 0);
        exit;
    }

    logOutput("スクレイピングURL: $listUrl");

    $baseUrl = 'https://ranking-deli.jp';
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
    logOutput("一覧ページ取得中...");

    $html = @file_get_contents($listUrl, false, $ctx);
    if ($html === false) {
        throw new Exception("一覧ページの取得に失敗しました");
    }

    logOutput("HTML取得成功: " . strlen($html) . " bytes");

    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $doc->loadHTML($html);
    $xp = new DOMXPath($doc);

    // 参考サイトと同じセレクタを使用
    $nodes = $xp->query('//li[contains(@class,"girl-box")]');
    logOutput("girl-box要素数: " . $nodes->length);

    $urls = [];
    foreach ($nodes as $n) {
        $a = $xp->query('.//a', $n)->item(0);
        if (!$a)
            continue;
        $hrefAttr = $a->attributes->getNamedItem('href');
        if (!$hrefAttr)
            continue;
        $h = $hrefAttr->nodeValue;
        if (strpos($h, 'http') !== 0)
            $h = $baseUrl . $h;
        // 数字で終わるURLをキャスト詳細ページとみなす
        if (preg_match('#/\d+/?$#', $h) && !in_array($h, $urls)) {
            $urls[] = $h;
        }
    }

    $totalCasts = count($urls);
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
    // 3) 詳細ページをキャッシュ取得
    // =====================================================
    logOutput("全キャスト詳細ページをキャッシュ中...");

    $default_days = array_fill(0, 7, null);
    $cache = [];

    foreach ($urls as $i => $u) {
        $cache[$i] = @file_get_contents($u, false, $ctx);

        if ($cache[$i] === false) {
            logOutput("  [{$i}] キャッシュ失敗: $u");
            continue;
        }

        // 日付を収集
        if (preg_match_all('/<tr[^>]*>(.*?)<\/tr>/s', $cache[$i], $trs)) {
            for ($j = 0; $j < 7; $j++) {
                if (!empty($trs[1][$j]) && preg_match('/<th>([^<]*)<\/th>/', $trs[1][$j], $m)) {
                    if ($default_days[$j] === null) {
                        $default_days[$j] = formatDate(trim($m[1]));
                    }
                }
            }
        }

        // サーバー負荷軽減
        usleep(200000); // 0.2秒
    }

    logOutput("基準日付: " . implode(', ', array_filter($default_days)));

    // =====================================================
    // 4) キャストデータ抽出・DB保存
    // =====================================================
    $sort = 1;
    $successCount = 0;
    $errorCount = 0;

    // INSERT文を準備
    $sql = "
        INSERT INTO tenant_cast_data_ekichika (
            tenant_id, name, name_romaji, sort_order,
            cup, age, height, size,
            pr_title, pr_text, `new`,
            today, `now`, closed,
            img1, img2, img3, img4, img5,
            day1, day2, day3, day4, day5, day6, day7,
            checked, missing_count, source_url, updated_at
        ) VALUES (
            :tenant_id, :name, :name_romaji, :sort,
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

    foreach ($urls as $i => $detailUrl) {
        $name = null;

        try {
            $html2 = isset($cache[$i]) ? $cache[$i] : false;

            if ($html2 === false) {
                throw new Exception("詳細ページ取得失敗（キャッシュなし）");
            }

            // エラーページ判定（HTML全体）
            if (
                stripos($html2, '<title>502 Bad Gateway</title>') !== false ||
                stripos($html2, '<title>503 Service Unavailable</title>') !== false ||
                stripos($html2, '<title>500 Internal Server Error</title>') !== false
            ) {
                throw new Exception("エラーページが返されました（HTML判定）");
            }

            $d2 = new DOMDocument();
            @$d2->loadHTML($html2);
            $xp2 = new DOMXPath($d2);

            // === 名前を取得 ===
            $name = trim($xp2->evaluate('string(//div[@id="main-l"]//h2)'));
            if ($name === '') {
                // 代替セレクタを試す
                $name = trim($xp2->evaluate('string(//h2[contains(@class,"girl-name")])'));
            }
            if ($name === '') {
                $name = trim($xp2->evaluate('string(//h1)'));
            }

            // エラーページ判定（502 Bad Gatewayなどが名前として取得されるのを防ぐ）
            if (stripos($name, 'Bad Gateway') !== false || stripos($name, 'Service Unavailable') !== false || stripos($name, 'Internal Server Error') !== false) {
                throw new Exception("エラーページが返されました: {$name}");
            }

            if ($name === '') {
                throw new Exception('名前が取得できませんでした');
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

            // === カップ・サイズ・年齢・身長 ===
            $cup = trim($xp2->evaluate('string(//p[contains(@class,"bust-size")])'));
            $sizeRaw = preg_replace('/\s+/', '', trim($xp2->evaluate('string(//p[contains(@class,"data-size")])')));
            $age = $height = $size = '';
            if (preg_match('/(\d+)歳/', $sizeRaw, $m))
                $age = $m[1];
            if (preg_match('/(\d+)cm/', $sizeRaw, $m))
                $height = $m[1];
            if (preg_match('/B:?(\d+).*?W:?(\d+).*?H:?(\d+)/i', $sizeRaw, $m)) {
                $size = "B{$m[1]} W{$m[2]} H{$m[3]}";
            }

            // === PRタイトル・本文 ===
            $pr_title = '';
            $pr_text = '';

            // 駅ちかでは <p class="catch"> にキャッチコピーがある
            $catchNode = $xp2->query('//p[contains(@class,"catch")]')->item(0);
            if ($catchNode) {
                $pr_title = trim($catchNode->textContent);
            }

            // PRタイトルが空の場合、代替セレクタを試す
            if (empty($pr_title)) {
                // 一覧ページの画像altから【】で囲まれた部分を抽出
                $imgNode = $xp2->query('//img[contains(@alt,"【")]/@alt')->item(0);
                if ($imgNode) {
                    $alt = $imgNode->nodeValue;
                    if (preg_match('/【([^】]+)】/', $alt, $matches)) {
                        $pr_title = $matches[1];
                    }
                }
            }

            // お店コメント本文を取得
            $prTextNode = $xp2->query('//section[contains(@class,"shopmessage-body")]//p')->item(0);
            if ($prTextNode) {
                $pr_text = getTextPreservingLineBreaks($prTextNode);
            }

            // === 新人判定 ===
            // 参考コード: $isNew = $xp2->evaluate('string(/html/body/div[1]/div[4]/div/div[2]/div[2]/div[1]/ul/li[1]/p/span)');
            $isNew = $xp2->evaluate('string(/html/body/div[1]/div[4]/div/div[2]/div[2]/div[1]/ul/li[1]/p/span)');
            if (empty($isNew)) {
                // フォールバック: span要素で「新人」を含むものを探す
                $isNew = $xp2->evaluate('string(//span[contains(text(),"新人")])');
            }
            $new = (strpos($isNew, '新人') !== false) ? '新人' : null;

            // === 出勤スケジュール ===
            $times = [];
            for ($d = 1; $d <= 7; $d++) {
                $times[$d] = null;
            }

            if (preg_match_all('/<tr[^>]*>(.*?)<\/tr>/s', $html2, $trs2)) {
                for ($j = 0; $j < min(7, count($trs2[1])); $j++) {
                    if (preg_match('/<li class="start">([^<]*)<\/li>.*?<li class="end">([^<]*)<\/li>/s', $trs2[1][$j], $mm)) {
                        $times[$j + 1] = trim($mm[1]) . '~' . trim($mm[2]);
                    }
                }
            }

            // === 案内中／受付終了判定 ===
            $time_1 = $times[1];
            $today = ($time_1 && $time_1 !== '---') ? '本日出勤' : null;
            $now_status = parseTimeRangeToNowStatus($time_1);
            $closed = parseTimeRangeToClosedStatus($time_1) ? 1 : 0;

            // === 画像URL ===
            $images = ['img1' => null, 'img2' => null, 'img3' => null, 'img4' => null, 'img5' => null];
            if (preg_match('/<ul class="thum-list">([\s\S]*?)<\/ul>/', $html2, $ulMatch)) {
                preg_match_all('/<img[^>]+src="([^"]+)"/', $ulMatch[1], $allImgs);
                $urlsImg = isset($allImgs[1]) ? $allImgs[1] : [];
                for ($k = 1; $k <= 5; $k++) {
                    $images["img{$k}"] = isset($urlsImg[$k - 1]) ? $urlsImg[$k - 1] : null;
                }
            }

            // === DB保存 ===
            $params = [
                ':tenant_id' => $tenantId,
                ':name' => $name,
                ':name_romaji' => $name_romaji,
                ':sort' => $sort,
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
            $failedName = $name ? $name : "URL-{$i}";
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
        UPDATE tenant_cast_data_ekichika 
        SET missing_count = missing_count + 1 
        WHERE tenant_id = ? AND updated_at < ?
    ");
    $stmt->execute([$tenantId, $scrapingStartTime]);
    $missingCount = $stmt->rowCount();
    logOutput("取得失敗カウント更新: {$missingCount}件");

    // missing_count >= 3 のキャストを非表示に（データは残す）
    $stmt = $pdo->prepare("
        UPDATE tenant_cast_data_ekichika 
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
