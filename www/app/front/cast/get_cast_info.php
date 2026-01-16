<?php
/**
 * キャスト情報取得API（閲覧履歴用）
 */

require_once __DIR__ . '/../../../includes/bootstrap.php';

// キャッシュ制御
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid ID']);
    exit;
}

// テナント情報を取得
$tenant = getTenantFromRequest();
if (!$tenant) {
    http_response_code(400);
    echo json_encode(['error' => 'Tenant not found']);
    exit;
}

$tenantId = $tenant['id'];

try {
    $pdo = getPlatformDb();
    
    // テナントのスクレイピング設定を取得
    $stmt = $pdo->prepare("SELECT active_source FROM tenant_scraping_settings WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $settings = $stmt->fetch();
    
    $activeSource = $settings['active_source'] ?? 'ekichika';
    
    // データソースに応じたテーブル名
    $tableMap = [
        'ekichika' => 'tenant_cast_data_ekichika',
        'heaven' => 'tenant_cast_data_heaven',
        'dto' => 'tenant_cast_data_dto'
    ];
    $tableName = $tableMap[$activeSource] ?? 'tenant_cast_data_ekichika';
    
    // キャスト情報を取得
    $stmt = $pdo->prepare("
        SELECT id, name, age, img1 as image, cup, pr_title
        FROM {$tableName}
        WHERE id = :id AND tenant_id = :tenant_id AND checked = 1
    ");
    $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
    $cast = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cast) {
        http_response_code(404);
        echo json_encode(['error' => 'Cast not found']);
        exit;
    }

    echo json_encode($cast);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
