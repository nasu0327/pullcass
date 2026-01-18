<?php
/**
 * pullcass - 店舗管理画面
 * ログアウト処理
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

// テナントコードを保存（ログアウト後のリダイレクト用）
$tenantSlug = $_SESSION['manage_tenant_slug'] ?? null;

// ログイン関連のセッション変数をクリア
unset($_SESSION['manage_admin_id']);
unset($_SESSION['manage_admin_name']);
unset($_SESSION['manage_admin_username']);
unset($_SESSION['manage_tenant_slug']);
unset($_SESSION['manage_tenant']);

// ログインページにリダイレクト
if ($tenantSlug) {
    redirect('/app/manage/login.php?tenant=' . $tenantSlug);
} else {
    redirect('/app/manage/login.php');
}
