<?php
/**
 * pullcass - マスター管理者認証
 */

/**
 * マスター管理者ログインを要求
 */
function requireSuperAdminLogin() {
    if (!isSuperAdminLoggedIn()) {
        redirect('/admin/login.php');
    }
}

/**
 * マスター管理者としてログインしているか確認
 */
function isSuperAdminLoggedIn() {
    return isset($_SESSION['super_admin_id']) && $_SESSION['super_admin_id'] > 0;
}

/**
 * 現在のマスター管理者情報を取得
 */
function getCurrentSuperAdmin() {
    if (!isSuperAdminLoggedIn()) {
        return null;
    }
    
    return [
        'id' => $_SESSION['super_admin_id'],
        'name' => $_SESSION['super_admin_name'] ?? '',
        'username' => $_SESSION['super_admin_username'] ?? ''
    ];
}
