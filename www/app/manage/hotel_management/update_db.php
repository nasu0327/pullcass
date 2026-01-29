<?php
/**
 * 緊急データベース移行スクリプト
 * hotelsテーブルにtenant_idカラムを追加します。
 */
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

// ログインチェック
requireTenantAdminLogin();

$pdo = getPlatformDb();

try {
    echo "Checking hotels table structure...<br>";

    // カラムが存在するか確認
    $stmt = $pdo->query("DESCRIBE hotels");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('tenant_id', $columns)) {
        echo "Adding tenant_id column...<br>";
        $pdo->exec("ALTER TABLE hotels ADD COLUMN tenant_id INT NOT NULL AFTER id");
        $pdo->exec("ALTER TABLE hotels ADD INDEX (tenant_id)");
        echo "Column added successfully.<br>";

        // メインテナントIDの取得（暫定的に1、または現在のログインID）
        $tid = $tenantId ?? 1;
        $pdo->exec("UPDATE hotels SET tenant_id = $tid");
        echo "Existing data assigned to tenant_id: $tid<br>";
    } else {
        echo "Column 'tenant_id' already exists.<br>";
    }

    echo "<strong>Migration completed successfully.</strong>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
