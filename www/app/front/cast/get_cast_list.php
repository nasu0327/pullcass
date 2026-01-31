<?php
/**
 * pullcass - 出勤情報があるキャスト一覧API
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
        echo json_encode([]);
        exit;
    }
    $tenantId = $tenant['id'];

    // データベース接続を取得
    $pdo = getPlatformDb();
    if (!$pdo) {
        echo json_encode([]);
        exit;
    }

    // 出勤情報があるキャスト一覧を取得
    // day1〜day7のいずれかに有効な出勤時間が設定されているキャスト
    $stmt = $pdo->prepare("
        SELECT id, name 
        FROM tenant_casts 
        WHERE tenant_id = :tenant_id 
        AND checked = 1 
        AND (
            (day1 IS NOT NULL AND day1 != '' AND day1 != '---' AND TRIM(day1) != '') OR
            (day2 IS NOT NULL AND day2 != '' AND day2 != '---' AND TRIM(day2) != '') OR
            (day3 IS NOT NULL AND day3 != '' AND day3 != '---' AND TRIM(day3) != '') OR
            (day4 IS NOT NULL AND day4 != '' AND day4 != '---' AND TRIM(day4) != '') OR
            (day5 IS NOT NULL AND day5 != '' AND day5 != '---' AND TRIM(day5) != '') OR
            (day6 IS NOT NULL AND day6 != '' AND day6 != '---' AND TRIM(day6) != '') OR
            (day7 IS NOT NULL AND day7 != '' AND day7 != '---' AND TRIM(day7) != '')
        )
        ORDER BY sort_order ASC, id DESC
    ");
    $stmt->execute(['tenant_id' => $tenantId]);
    $casts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($casts);

} catch (Exception $e) {
    error_log('get_cast_list error: ' . $e->getMessage());
    echo json_encode([]);
}
