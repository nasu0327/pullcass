<?php
/**
 * トップバナー情報取得API
 */

require_once __DIR__ . '/../../../includes/bootstrap.php';

header('Content-Type: application/json');

session_start();

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
    
    // バナー情報を取得
    $stmt = $pdo->prepare("SELECT * FROM top_banners WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$id, $tenant['id']]);
    $banner = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($banner) {
        echo json_encode(['success' => true, 'banner' => $banner]);
    } else {
        echo json_encode(['success' => false, 'message' => 'バナーが見つかりません']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'データベースエラー']);
}
