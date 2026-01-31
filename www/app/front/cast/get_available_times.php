<?php
/**
 * pullcass - 利用可能時間API
 */

// エラー表示を抑制（JSON出力のため）
ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

ob_start();
require_once __DIR__ . '/../../../includes/bootstrap.php';
ob_clean();

try {
    // テナント情報を取得
    $tenant = getTenantFromRequest();
    if (!$tenant) {
        echo json_encode(['times' => []]);
        exit;
    }
    $tenantId = $tenant['id'];

    // パラメータを取得
    $castId = filter_input(INPUT_GET, 'cast_id', FILTER_VALIDATE_INT);
    $date = filter_input(INPUT_GET, 'date', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    if (!$castId || !$date) {
        echo json_encode(['times' => []]);
        exit;
    }

    // データベース接続を取得
    $pdo = getPlatformDb();
    if (!$pdo) {
        echo json_encode(['times' => []]);
        exit;
    }

    // キャストの出勤情報を取得
    $stmt = $pdo->prepare("
        SELECT day1, day2, day3, day4, day5, day6, day7
        FROM tenant_casts
        WHERE id = :id AND tenant_id = :tenant_id AND checked = 1
    ");
    $stmt->execute(['id' => $castId, 'tenant_id' => $tenantId]);
    $cast = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cast) {
        echo json_encode(['times' => []]);
        exit;
    }

    // 指定された日付に対応する時間を取得
    $targetTime = null;
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    
    for ($i = 1; $i <= 7; $i++) {
        $dayKey = "day{$i}";
        $time = $cast[$dayKey] ?? '';
        
        if (!empty($time) && $time !== '---' && trim($time) !== '') {
            // 日付を計算（day1=今日, day2=明日, ...）
            $checkDate = new DateTime();
            $checkDate->modify('+' . ($i - 1) . ' days');
            $normalizedDay = $checkDate->format('Y-m-d');
            
            if ($normalizedDay === $date) {
                $targetTime = $time;
                break;
            }
        }
    }

    if (!$targetTime) {
        echo json_encode(['times' => []]);
        exit;
    }

    // 時間文字列を解析して利用可能時間を生成
    $times = generateAvailableTimes($targetTime);

    echo json_encode(['times' => $times]);

} catch (Exception $e) {
    error_log('get_available_times error: ' . $e->getMessage());
    echo json_encode(['times' => []]);
}

/**
 * 時間文字列から利用可能時間を生成
 * 例: "11:00~20:00", "21:00~翌2:00", "15:30~24:00"
 */
function generateAvailableTimes($timeString) {
    $times = [];
    
    // 時間文字列の例: "11:00~20:00", "21:00~翌2:00", "15:30~24:00", "14:00~21:00"
    if (preg_match('/(\d{1,2}):(\d{2})~翌?(\d{1,2}):(\d{2})/', $timeString, $matches)) {
        $startHour = (int)$matches[1];
        $startMinute = (int)$matches[2];
        $endHour = (int)$matches[3];
        $endMinute = (int)$matches[4];
        
        // 翌日の場合
        $isNextDay = strpos($timeString, '翌') !== false;
        if ($isNextDay) {
            $endHour += 24;
        }
        
        // 終了時間が開始時間より小さい場合（深夜をまたぐ場合）
        if ($endHour < $startHour) {
            $endHour += 24;
        }
        
        // 終了時間の1時間前まで
        $endHour -= 1;
        
        // 30分単位で時間を生成
        $currentHour = $startHour;
        $currentMinute = $startMinute;
        
        $loopCount = 0;
        while (($currentHour < $endHour) || 
               ($currentHour == $endHour && $currentMinute <= $endMinute)) {
            
            // 無限ループ防止
            $loopCount++;
            if ($loopCount > 100) break;
            
            $timeStr = sprintf('%02d:%02d', $currentHour, $currentMinute);
            $times[] = $timeStr;
            
            // 30分進める
            $currentMinute += 30;
            if ($currentMinute >= 60) {
                $currentMinute = 0;
                $currentHour += 1;
            }
        }
    } else {
        // 代替の正規表現を試す（ハイフン区切り）
        if (preg_match('/(\d{1,2}):(\d{2})\s*[-~]\s*(\d{1,2}):(\d{2})/', $timeString, $matches)) {
            $startHour = (int)$matches[1];
            $startMinute = (int)$matches[2];
            $endHour = (int)$matches[3];
            $endMinute = (int)$matches[4];
            
            // 終了時間が開始時間より小さい場合（深夜をまたぐ場合）
            if ($endHour < $startHour) {
                $endHour += 24;
            }
            
            // 終了時間の1時間前まで
            $endHour -= 1;
            
            // 30分単位で時間を生成
            $currentHour = $startHour;
            $currentMinute = $startMinute;
            
            $loopCount = 0;
            while (($currentHour < $endHour) || 
                   ($currentHour == $endHour && $currentMinute <= $endMinute)) {
                
                // 無限ループ防止
                $loopCount++;
                if ($loopCount > 100) break;
                
                $timeStr = sprintf('%02d:%02d', $currentHour, $currentMinute);
                $times[] = $timeStr;
                
                // 30分進める
                $currentMinute += 30;
                if ($currentMinute >= 60) {
                    $currentMinute = 0;
                    $currentHour += 1;
                }
            }
        }
    }
    
    return $times;
}
