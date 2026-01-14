<?php
/**
 * 相互リンク削除
 */

require_once __DIR__ . '/../../../includes/bootstrap.php';

session_start();

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
    
    // リンク情報を取得
    $stmt = $pdo->prepare("SELECT banner_image FROM reciprocal_links WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$id, $tenant['id']]);
    $link = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($link) {
        // 画像ファイルを削除
        if ($link['banner_image']) {
            $base_dir = __DIR__ . '/../../../';
            if (file_exists($base_dir . $link['banner_image'])) {
                @unlink($base_dir . $link['banner_image']);
            }
        }
        
        // データベースから削除
        $stmt = $pdo->prepare("DELETE FROM reciprocal_links WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenant['id']]);
    }
    
    header('Location: index.php?tenant=' . urlencode($tenantSlug) . '&success=3');
    exit;
} catch (PDOException $e) {
    header('Location: index.php?tenant=' . urlencode($tenantSlug));
    exit;
}
