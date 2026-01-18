<?php
/**
 * トップバナー並び順更新API
 */

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

requireTenantAdminLogin();

$input = json_decode(file_get_contents('php://input'), true);
$tenantSlug = $input['tenant'] ?? $_SESSION['manage_tenant_slug'] ?? null;
$order = $input['order'] ?? [];

if (!$tenantSlug || empty($order)) {
    echo json_encode(['success' => false, 'message' => 'パラメータが不正です']);
    exit;
}

try {
    $pdo = getPlatformDb();
    
    // テナントIDを取得
    $stmt = $pdo->prepare("SELECT id FROM tenants WHERE code = ?");
    $stmt->execute([$tenantSlug]);
    $tenant = $stmt->fetch();
    
    if (!$tenant) {
        echo json_encode(['success' => false, 'message' => 'テナントが見つかりません']);
        exit;
    }
    
    $pdo->beginTransaction();
    
    // 並び順を更新
    foreach ($order as $index => $id) {
        $stmt = $pdo->prepare("UPDATE top_banners SET display_order = ? WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$index, $id, $tenant['id']]);
    }
    
    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'データベースエラー']);
}
