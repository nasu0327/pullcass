<?php
/**
 * pullcass - スーパー管理画面
 * 店舗ステータス切り替え
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

requireSuperAdminLogin();

$id = (int)($_GET['id'] ?? 0);
$csrf = $_GET['csrf'] ?? '';

// CSRFチェック
if (!verifyCsrfToken($csrf)) {
    setFlash('error', '不正なリクエストです。');
    redirect('/admin/tenants/');
}

if ($id <= 0) {
    setFlash('error', '店舗IDが指定されていません。');
    redirect('/admin/tenants/');
}

try {
    $pdo = getPlatformDb();
    
    // 現在のステータスを取得
    $stmt = $pdo->prepare("SELECT * FROM tenants WHERE id = ?");
    $stmt->execute([$id]);
    $tenant = $stmt->fetch();
    
    if (!$tenant) {
        setFlash('error', '店舗が見つかりません。');
        redirect('/admin/tenants/');
    }
    
    // ステータスを切り替え
    $newStatus = $tenant['status'] === 'active' ? 'inactive' : 'active';
    
    $stmt = $pdo->prepare("UPDATE tenants SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $id]);
    
    $statusLabel = $newStatus === 'active' ? '有効' : '停止';
    setFlash('success', "店舗「{$tenant['name']}」のステータスを{$statusLabel}に変更しました。");
    
} catch (PDOException $e) {
    setFlash('error', 'エラーが発生しました。');
}

redirect('/admin/tenants/');
