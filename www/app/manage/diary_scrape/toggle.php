<?php
/**
 * 自動取得ON/OFF切替API
 */

require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $enabled = !empty($input['enabled']);
    
    $platformPdo = getPlatformDb();
    $tenantId = $tenant['id'];
    
    $stmt = $platformPdo->prepare("
        UPDATE diary_scrape_settings 
        SET is_enabled = ?, updated_at = NOW()
        WHERE tenant_id = ?
    ");
    $stmt->execute([$enabled ? 1 : 0, $tenantId]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
