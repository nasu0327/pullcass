<?php
/**
 * トップバナー削除
 */

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

$tenantSlug = $_GET['tenant'] ?? $_SESSION['manage_tenant_slug'] ?? null;
$id = (int)($_GET['id'] ?? 0);

if (!$tenantSlug || !$id) {
    header('Location: index.php');
    exit;
}

try {
    $pdo = getPlatformDb();
    
    // テナントIDを取得
    $stmt = $pdo->prepare("SELECT id FROM tenants WHERE code = ?");
    $stmt->execute([$tenantSlug]);
    $tenant = $stmt->fetch();
    
    if (!$tenant) {
        header('Location: index.php?tenant=' . urlencode($tenantSlug));
        exit;
    }
    
    // バナー情報を取得
    $stmt = $pdo->prepare("SELECT pc_image, sp_image FROM top_banners WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$id, $tenant['id']]);
    $banner = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($banner) {
        // 画像ファイルを削除
        $base_dir = __DIR__ . '/../../../';
        if ($banner['pc_image'] && file_exists($base_dir . $banner['pc_image'])) {
            @unlink($base_dir . $banner['pc_image']);
        }
        if ($banner['sp_image'] && file_exists($base_dir . $banner['sp_image'])) {
            @unlink($base_dir . $banner['sp_image']);
        }
        
        // データベースから削除
        $stmt = $pdo->prepare("DELETE FROM top_banners WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenant['id']]);
    }
    
    header('Location: index.php?tenant=' . urlencode($tenantSlug) . '&success=3');
    exit;
} catch (PDOException $e) {
    header('Location: index.php?tenant=' . urlencode($tenantSlug));
    exit;
}
