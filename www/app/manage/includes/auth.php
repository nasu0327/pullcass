<?php
/**
 * 店舗管理画面 - 認証・テナント取得
 * ※HTMLを出力しない。POST処理前に読み込む用
 */

// セッション開始
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// bootstrap読み込み（まだの場合）
if (!function_exists('getPlatformDb')) {
    require_once __DIR__ . '/../../../includes/bootstrap.php';
}

/**
 * 店舗管理者ログインを要求
 * ログインしていない場合はログインページにリダイレクト
 */
function requireTenantAdminLogin() {
    if (!isTenantAdminLoggedIn()) {
        $tenantSlug = $_GET['tenant'] ?? $_SESSION['manage_tenant_slug'] ?? null;
        $loginUrl = $tenantSlug 
            ? '/app/manage/login.php?tenant=' . urlencode($tenantSlug)
            : '/app/manage/login.php';
        redirect($loginUrl);
    }
}

/**
 * 店舗管理者としてログインしているか確認
 */
function isTenantAdminLoggedIn() {
    return isset($_SESSION['manage_admin_id']) 
        && $_SESSION['manage_admin_id'] > 0
        && isset($_SESSION['manage_tenant_slug']);
}

/**
 * 現在の店舗管理者情報を取得
 */
function getCurrentTenantAdmin() {
    if (!isTenantAdminLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['manage_admin_id'],
        'name' => $_SESSION['manage_admin_name'] ?? '',
        'username' => $_SESSION['manage_admin_username'] ?? ''
    ];
}

// ============================================
// テナント情報の取得
// ============================================

// テナント情報の取得（URLパラメータを優先）
$tenantSlugFromUrl = $_GET['tenant'] ?? null;
$tenantSlugFromSession = $_SESSION['manage_tenant_slug'] ?? null;
$tenantSlug = $tenantSlugFromUrl ?? $tenantSlugFromSession;

if (!$tenantSlug) {
    header('Location: /app/manage/');
    exit;
}

// グローバルで$pdoを設定
global $pdo;
$pdo = getPlatformDb();

// URLパラメータで指定された場合、または セッションと異なる場合は必ずDBから再取得
$needRefresh = !isset($_SESSION['manage_tenant']) 
    || ($tenantSlugFromUrl && $tenantSlugFromUrl !== ($_SESSION['manage_tenant']['code'] ?? ''))
    || ($_SESSION['manage_tenant']['code'] ?? '') !== $tenantSlug;

if ($needRefresh) {
    $stmt = $pdo->prepare("SELECT * FROM tenants WHERE code = ? AND is_active = 1");
    $stmt->execute([$tenantSlug]);
    $tenant = $stmt->fetch();
    
    if (!$tenant) {
        die("店舗が見つかりません。");
    }
    
    $_SESSION['manage_tenant_slug'] = $tenantSlug;
    $_SESSION['manage_tenant'] = $tenant;
    
    // current_tenantも設定（テーマプレビュー用）
    $_SESSION['current_tenant'] = $tenant;
} else {
    $tenant = $_SESSION['manage_tenant'];
}

$shopName = $tenant['name'];
$tenantId = $tenant['id'];
