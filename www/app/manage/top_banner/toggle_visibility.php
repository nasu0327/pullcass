<?php
/**
 * トップバナー表示/非表示切り替えAPI
 */

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

requireTenantAdminLogin();

$input = json_decode(file_get_contents('php://input'), true);
$tenantSlug = $input['tenant'] ?? $_SESSION['manage_tenant_slug'] ?? null;
$id = (int)($input['id'] ?? 0);

if (!$tenantSlug || !$id) {
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
    
    // 現在の表示状態を取得
    $stmt = $pdo->prepare("SELECT is_visible FROM top_banners WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$id, $tenant['id']]);
    $currentState = $stmt->fetchColumn();
    
    if ($currentState === false) {
        echo json_encode(['success' => false, 'message' => 'バナーが見つかりません']);
        exit;
    }
    
    // 表示状態を反転
    $newState = !$currentState;
    $stmt = $pdo->prepare("UPDATE top_banners SET is_visible = ? WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$newState, $id, $tenant['id']]);
    
    echo json_encode(['success' => true, 'is_visible' => $newState]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'データベースエラー']);
}
