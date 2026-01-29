<?php
/**
 * ホテルデータの所有権修正スクリプト
 * 現在のホテルデータを全て「豊満倶楽部(houman-club)」に紐付け直します。
 */
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

// ログインチェック
requireTenantAdminLogin();

$pdo = getPlatformDb();

try {
    echo "Starting data correction...<br>";

    // 豊満倶楽部のテナント情報を取得
    $stmt = $pdo->prepare("SELECT id FROM tenants WHERE code = ?");
    $stmt->execute(['houman-club']);
    $tenant = $stmt->fetch();

    if ($tenant) {
        $targetTenantId = $tenant['id'];
        echo "Found 'houman-club' (ID: {$targetTenantId})<br>";

        // 全てのホテルデータのtenant_idを更新
        $stmt = $pdo->prepare("UPDATE hotels SET tenant_id = ?");
        $stmt->execute([$targetTenantId]);

        echo "Updated all hotel data to belong to 'houman-club'.<br>";
        echo "<strong> Correction completed successfully.</strong>";
    } else {
        echo "Error: Tenant 'houman-club' not found.<br>";

        // フォールバック：現在のテナントIDを使用するオプション
        echo "If you want to assign data to the CURRENT tenant, please verify your tenant ID.<br>";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
