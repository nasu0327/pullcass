<?php
/**
 * 相互リンク情報取得API
 */

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

requireTenantAdminLogin();

$tenantSlug = $_GET['tenant'] ?? $_SESSION['manage_tenant_slug'] ?? null;
$id = (int)($_GET['id'] ?? 0);

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
    
    // リンク情報を取得
    $stmt = $pdo->prepare("SELECT * FROM reciprocal_links WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$id, $tenant['id']]);
    $link = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($link) {
        echo json_encode(['success' => true, 'data' => $link]);
    } else {
        echo json_encode(['success' => false, 'message' => 'リンクが見つかりません']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'データベースエラー']);
}
