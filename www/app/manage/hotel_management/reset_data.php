<?php
/**
 * ホテルデータ全削除スクリプト
 * ユーザーリクエストにより、hotelsテーブルを空にします。
 */
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

// ログインチェック
requireTenantAdminLogin();

$pdo = getPlatformDb();

try {
    echo "Resetting hotel data...<br>";

    // 全データを削除
    $pdo->exec("TRUNCATE TABLE hotels");

    echo "<strong>All hotel data has been deleted.</strong><br>";
    echo "Please upload your Excel file to register new data.";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
