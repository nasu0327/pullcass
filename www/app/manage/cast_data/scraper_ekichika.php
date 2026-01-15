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
set_time_limit(600); // 10分
ini_set('memory_limit', '256M');
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
$logFile = __DIR__ . "/scraping_ekichika_{$tenantId}.log";

function logOutput($message, $level = 'info') {
    global $logFile, $pdo, $tenantId;
    $maxSize = 100 * 1024; // 100KB
    
    // ファイルサイズ制限
    if (file_exists($logFile) && filesize($logFile) > $maxSize) {
        $all = file_get_contents($logFile);
        $tail = substr($all, -$maxSize);
        file_put_contents($logFile, $tail);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$level] $message";
    file_put_contents($logFile, "$logMessage\n", FILE_APPEND);
    
    // DBにも記録
    try {
        $stmt = $pdo->prepare("
            INSERT INTO tenant_scraping_logs (tenant_id, scraping_type, log_level, message)
            VALUES (?, 'ekichika', ?, ?)
        ");
        $stmt->execute([$tenantId, $level, $message]);
    } catch (Exception $e) {
        // ログテーブルへの書き込み失敗は無視
    }
    
    // CLI出力
    if (php_sapi_name() === 'cli') {
        echo "$logMessage\n";
    }
}

// ステータス更新関数
function updateStatus($status, $successCount = null, $errorCount = null, $lastError = null) {
    global $pdo, $tenantId;
    
    try {
        $fields = ['status = ?'];
        $params = [$status];
        
        if ($status === 'running') {
            $fields[] = 'start_time = NOW()';
            $fields[] = 'end_time = NULL';
            $fields[] = 'success_count = 0';
            $fields[] = 'error_count = 0';
        }
        
        if ($status === 'completed' || $status === 'error') {
            $fields[] = 'end_time = NOW()';
        }
        
        if ($successCount !== null) {
            $fields[] = 'success_count = ?';
            $params[] = $successCount;
        }
        
        if ($errorCount !== null) {
            $fields[] = 'error_count = ?';
            $params[] = $errorCount;
        }
        
        if ($lastError !== null) {
            $fields[] = 'last_error = ?';
            $params[] = $lastError;
        }
        
        $params[] = $tenantId;
        
        $sql = "INSERT INTO tenant_scraping_status (tenant_id, scraping_type, " . implode(', ', array_map(function($f) {
            return explode(' = ', $f)[0];
        }, $fields)) . ") VALUES (?, 'ekichika', " . implode(', ', array_fill(0, count($fields), '?')) . ")
        ON DUPLICATE KEY UPDATE " . implode(', ', $fields);
        
        // シンプルなUPSERT
        $stmt = $pdo->prepare("
            INSERT INTO tenant_scraping_status (tenant_id, scraping_type, status, start_time, success_count, error_count)
            VALUES (?, 'ekichika', ?, NOW(), 0, 0)
            ON DUPLICATE KEY UPDATE status = VALUES(status), start_time = IF(VALUES(status) = 'running', NOW(), start_time), end_time = IF(VALUES(status) IN ('completed', 'error'), NOW(), end_time)
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

// =====================================================
// メイン処理開始
// =====================================================

try {
    logOutput("========================================");
    logOutput("駅ちか スクレイピング開始 (tenant_id: $tenantId)");
    logOutput("========================================");
    
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
    logOutput("キャスト一覧ページを取得中...");
    
    $html = @file_get_contents($listUrl, false, $ctx);
    if ($html === false) {
        throw new Exception("一覧ページの取得に失敗しました");
    }
    
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    @$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
    libxml_clear_errors();
    
    $xpath = new DOMXPath($doc);
    
    // キャスト詳細ページのURLを収集
    $urls = [];
    $linkNodes = $xpath->query("//a[contains(@href, '/girl/')]");
    
    foreach ($linkNodes as $node) {
        $href = $node->getAttribute('href');
        if (strpos($href, '/girl/') !== false && strpos($href, '#') === false) {
            $fullUrl = strpos($href, 'http') === 0 ? $href : $baseUrl . $href;
            if (!in_array($fullUrl, $urls)) {
                $urls[] = $fullUrl;
            }
        }
    }
    
    $totalCasts = count($urls);
    logOutput("キャスト数: {$totalCasts}人");
    
    if ($totalCasts === 0) {
        logOutput("キャストが見つかりませんでした");
        updateStatus('completed', 0, 0);
        exit;
    }
    
    // =====================================================
    // 2) 既存データをチェック解除
    // =====================================================
    logOutput("既存データをチェック解除...");
    $stmt = $pdo->prepare("UPDATE tenant_cast_data_ekichika SET checked = 0 WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    
    // =====================================================
    // 3) 各キャストの詳細を取得
    // =====================================================
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($urls as $i => $detailUrl) {
        $castName = null;
        
        try {
            logOutput(sprintf("[%d/%d] 取得中: %s", $i + 1, $totalCasts, basename($detailUrl)));
            
            // 詳細ページ取得
            $detailHtml = @file_get_contents($detailUrl, false, $ctx);
            if ($detailHtml === false) {
                throw new Exception("詳細ページの取得に失敗");
            }
            
            $doc2 = new DOMDocument();
            @$doc2->loadHTML(mb_convert_encoding($detailHtml, 'HTML-ENTITIES', 'UTF-8'));
            $xpath2 = new DOMXPath($doc2);
            
            // キャスト名
            $nameNode = $xpath2->query("//h1[contains(@class, 'girl-name')]")->item(0);
            if (!$nameNode) {
                $nameNode = $xpath2->query("//div[contains(@class, 'girl-block-title')]//span")->item(0);
            }
            $castName = $nameNode ? trim($nameNode->textContent) : null;
            
            if (!$castName) {
                throw new Exception("キャスト名が取得できません");
            }
            
            // プロフィール情報
            $age = null;
            $height = null;
            $cup = null;
            $size = null;
            
            // 年齢
            $ageNode = $xpath2->query("//*[contains(text(), '歳')]")->item(0);
            if ($ageNode && preg_match('/(\d{2})歳/', $ageNode->textContent, $m)) {
                $age = (int)$m[1];
            }
            
            // 身長
            $heightNode = $xpath2->query("//*[contains(text(), 'cm')]")->item(0);
            if ($heightNode && preg_match('/T?(\d{3})cm?/i', $heightNode->textContent, $m)) {
                $height = (int)$m[1];
            }
            
            // カップ
            $cupNode = $xpath2->query("//*[contains(text(), 'カップ')]")->item(0);
            if ($cupNode && preg_match('/([A-Z])カップ/i', $cupNode->textContent, $m)) {
                $cup = strtoupper($m[1]);
            }
            
            // サイズ (B/W/H)
            $sizeNode = $xpath2->query("//*[contains(text(), 'B:') or contains(text(), 'B ')]")->item(0);
            if ($sizeNode && preg_match('/B\s*:?\s*(\d+)[^\d]+W\s*:?\s*(\d+)[^\d]+H\s*:?\s*(\d+)/i', $sizeNode->textContent, $m)) {
                $size = "B{$m[1]} W{$m[2]} H{$m[3]}";
            }
            
            // PR文
            $prTitle = '';
            $prText = '';
            $prNode = $xpath2->query("//div[contains(@class, 'girl-comment') or contains(@class, 'pr-text')]")->item(0);
            if ($prNode) {
                $prText = trim($prNode->textContent);
            }
            
            // 画像
            $images = [];
            $imgNodes = $xpath2->query("//img[contains(@src, '/girl/') or contains(@class, 'girl-photo')]");
            foreach ($imgNodes as $imgNode) {
                $src = $imgNode->getAttribute('src');
                if ($src && strpos($src, 'noimage') === false) {
                    if (strpos($src, 'http') !== 0) {
                        $src = $baseUrl . $src;
                    }
                    if (!in_array($src, $images) && count($images) < 5) {
                        $images[] = $src;
                    }
                }
            }
            
            // 出勤情報（7日分）
            $schedule = [];
            for ($d = 1; $d <= 7; $d++) {
                $schedule["day{$d}"] = null;
            }
            
            // 出勤スケジュールテーブルを探す
            $scheduleNodes = $xpath2->query("//table[contains(@class, 'schedule')]//tr | //div[contains(@class, 'schedule-item')]");
            $dayIndex = 1;
            foreach ($scheduleNodes as $scheduleNode) {
                if ($dayIndex > 7) break;
                $timeText = trim($scheduleNode->textContent);
                if (preg_match('/(\d{1,2}:\d{2})\s*[~～]\s*(\d{1,2}:\d{2})/', $timeText, $m)) {
                    $schedule["day{$dayIndex}"] = "{$m[1]}~{$m[2]}";
                }
                $dayIndex++;
            }
            
            // 本日出勤チェック
            $today = null;
            $now = null;
            $closed = 0;
            $todayNode = $xpath2->query("//*[contains(@class, 'today') or contains(@class, 'attend')]")->item(0);
            if ($todayNode) {
                $todayText = trim($todayNode->textContent);
                if (preg_match('/(\d{1,2}:\d{2})\s*[~～]\s*(\d{1,2}:\d{2})/', $todayText, $m)) {
                    $today = "{$m[1]}~{$m[2]}";
                }
            }
            
            // 新人フラグ
            $new = 0;
            $newNode = $xpath2->query("//*[contains(@class, 'new') or contains(text(), '新人')]")->item(0);
            if ($newNode) {
                $new = 1;
            }
            
            // ローマ字名
            $nameRomaji = null;
            $romajiNode = $xpath2->query("//*[contains(@class, 'romaji') or contains(@class, 'en-name')]")->item(0);
            if ($romajiNode) {
                $nameRomaji = trim($romajiNode->textContent);
            }
            
            // =====================================================
            // データベース保存
            // =====================================================
            $stmt = $pdo->prepare("
                INSERT INTO tenant_cast_data_ekichika (
                    tenant_id, name, name_romaji, sort_order,
                    cup, age, height, size,
                    pr_title, pr_text, `new`,
                    today, `now`, closed,
                    img1, img2, img3, img4, img5,
                    day1, day2, day3, day4, day5, day6, day7,
                    checked, source_url
                ) VALUES (
                    :tenant_id, :name, :name_romaji, :sort_order,
                    :cup, :age, :height, :size,
                    :pr_title, :pr_text, :new,
                    :today, :now, :closed,
                    :img1, :img2, :img3, :img4, :img5,
                    :day1, :day2, :day3, :day4, :day5, :day6, :day7,
                    1, :source_url
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
                    source_url = VALUES(source_url)
            ");
            
            $stmt->execute([
                ':tenant_id' => $tenantId,
                ':name' => $castName,
                ':name_romaji' => $nameRomaji,
                ':sort_order' => $i + 1,
                ':cup' => $cup,
                ':age' => $age,
                ':height' => $height,
                ':size' => $size,
                ':pr_title' => $prTitle ?: null,
                ':pr_text' => $prText ?: null,
                ':new' => $new,
                ':today' => $today,
                ':now' => $now,
                ':closed' => $closed,
                ':img1' => $images[0] ?? null,
                ':img2' => $images[1] ?? null,
                ':img3' => $images[2] ?? null,
                ':img4' => $images[3] ?? null,
                ':img5' => $images[4] ?? null,
                ':day1' => $schedule['day1'],
                ':day2' => $schedule['day2'],
                ':day3' => $schedule['day3'],
                ':day4' => $schedule['day4'],
                ':day5' => $schedule['day5'],
                ':day6' => $schedule['day6'],
                ':day7' => $schedule['day7'],
                ':source_url' => $detailUrl
            ]);
            
            $successCount++;
            logOutput("  ✓ {$castName} を保存しました");
            
            // サーバー負荷軽減
            usleep(500000); // 0.5秒待機
            
        } catch (Exception $e) {
            $errorCount++;
            logOutput("  ✗ エラー: " . $e->getMessage(), 'error');
        }
    }
    
    // =====================================================
    // 完了処理
    // =====================================================
    logOutput("========================================");
    logOutput("スクレイピング完了");
    logOutput("成功: {$successCount}件, エラー: {$errorCount}件");
    logOutput("========================================");
    
    updateStatus('completed', $successCount, $errorCount);
    
} catch (Exception $e) {
    logOutput("致命的エラー: " . $e->getMessage(), 'error');
    updateStatus('error', $successCount ?? 0, $errorCount ?? 0, $e->getMessage());
    exit(1);
}
