<?php
/**
 * 店舗管理画面 - テナント認証
 * ※HTMLを出力しない。POST処理前に読み込む用
 */

// セッション開始
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// テナント情報の取得
$tenantSlug = $_GET['tenant'] ?? $_SESSION['manage_tenant_slug'] ?? null;

if (!$tenantSlug) {
    header('Location: /app/manage/');
    exit;
}

// bootstrap読み込み（まだの場合）
if (!function_exists('getPlatformDb')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
}

// テナント情報をセッションから取得、なければDBから取得
if (!isset($_SESSION['manage_tenant']) || $_SESSION['manage_tenant']['code'] !== $tenantSlug) {
    $pdo = getPlatformDb();
    $stmt = $pdo->prepare("SELECT * FROM tenants WHERE code = ? AND is_active = 1");
    $stmt->execute([$tenantSlug]);
    $tenant = $stmt->fetch();
    
    if (!$tenant) {
        die("店舗が見つかりません。");
    }
    
    $_SESSION['manage_tenant_slug'] = $tenantSlug;
    $_SESSION['manage_tenant'] = $tenant;
} else {
    $tenant = $_SESSION['manage_tenant'];
}

$shopName = $tenant['name'];
$tenantId = $tenant['id'];
