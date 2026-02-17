<?php
/**
 * 口コミスクレイピング停止API
 */

require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

header('Content-Type: application/json');

try {
    $platformPdo = getPlatformDb();
    $tenantId = $tenant['id'];

    $stmt = $platformPdo->prepare("
        UPDATE review_scrape_logs SET
            status = 'error',
            finished_at = NOW(),
            execution_time = TIMESTAMPDIFF(SECOND, started_at, NOW()),
            error_message = '手動停止'
        WHERE tenant_id = ? AND status = 'running'
    ");
    $stmt->execute([$tenantId]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
