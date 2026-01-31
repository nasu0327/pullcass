<?php
/**
 * pullcass - キャストの出勤スケジュールAPI
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
        echo json_encode([
            'success' => false,
            'error' => 'テナント情報が取得できません'
        ]);
        exit;
    }
    $tenantId = $tenant['id'];

    // キャストIDを取得
    $castId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$castId) {
        echo json_encode([
            'success' => false,
            'error' => '無効なキャストIDです'
        ]);
        exit;
    }

    // データベース接続を取得
    $pdo = getPlatformDb();
    if (!$pdo) {
        echo json_encode([
            'success' => false,
            'error' => 'データベース接続エラー'
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
            'error' => 'キャストが見つかりません'
        ]);
        exit;
    }

    // スケジュール情報を配列に整理
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
            
            // 表示用の日付文字列
            $displayDay = $date->format('n/j') . '(' . $dayOfWeekNames[$date->format('w')] . ')';
            // 正規化された日付（計算用）
            $normalizedDay = $date->format('Y-m-d');
            
            $schedule[] = [
                'day' => $displayDay,
                'normalized_day' => $normalizedDay,
                'time' => $time
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'schedule' => $schedule
    ]);

} catch (Exception $e) {
    error_log('get_cast_schedule error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'エラーが発生しました'
    ]);
}
