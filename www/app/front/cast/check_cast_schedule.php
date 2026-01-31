<?php
/**
 * pullcass - キャストの出勤データチェックAPI
 * 参考: reference/public_html/cast/check_cast_schedule.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../../../includes/bootstrap.php';

try {
    // テナント情報を取得
    $tenant = getTenantFromRequest();
    if (!$tenant) {
        echo json_encode([
            'success' => false,
            'message' => 'テナント情報が取得できません'
        ]);
        exit;
    }
    $tenantId = $tenant['id'];

    // キャストIDを取得
    $castId = filter_input(INPUT_GET, 'cast_id', FILTER_VALIDATE_INT);
    
    if (!$castId) {
        echo json_encode([
            'success' => false,
            'message' => '無効なキャストIDです'
        ]);
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
        echo json_encode([
            'success' => false,
            'message' => 'キャストが見つかりません'
        ]);
        exit;
    }

    // 今日の日付を取得
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    
    // 出勤情報を配列に整理（当日含む）
    $schedule = [];
    $dayOfWeekNames = ['日', '月', '火', '水', '木', '金', '土'];
    
    for ($i = 1; $i <= 7; $i++) {
        $dayKey = "day{$i}";
        $time = $cast[$dayKey] ?? '';
        
        // 出勤時間が設定されていて、'---'でない場合
        if (!empty($time) && $time !== '---' && trim($time) !== '') {
            // 日付を計算（day1=今日, day2=明日, ...）
            $date = new DateTime();
            $date->modify('+' . ($i - 1) . ' days');
            $date->setTime(0, 0, 0);
            
            $schedule[] = [
                'day' => $date->format('n/j') . '(' . $dayOfWeekNames[$date->format('w')] . ')',
                'time' => $time,
                'normalized_date' => $date->format('Y-m-d')
            ];
        }
    }

    // 結果を返す
    echo json_encode([
        'success' => true,
        'has_schedule' => count($schedule) > 0,
        'schedule_count' => count($schedule),
        'schedule' => $schedule,
        'message' => count($schedule) > 0 ? '出勤予定があります' : '出勤予定がありません'
    ]);

} catch (PDOException $e) {
    error_log('check_cast_schedule error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'データベースエラーが発生しました'
    ]);
} catch (Exception $e) {
    error_log('check_cast_schedule error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'エラーが発生しました'
    ]);
}
