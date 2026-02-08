<?php
/**
 * メニュー管理 - メニュー保存
 * メニュー項目の追加・編集処理
 */

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/menu_functions.php';

// ログイン認証
requireTenantAdminLogin();

// JSONレスポンス用のヘッダー
header('Content-Type: application/json');

try {
    $pdo = getPlatformDb();
    
    // POSTデータを取得
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'message' => '不正なリクエストです']);
        exit;
    }
    
    // テナントIDを追加
    $input['tenant_id'] = $tenantId;
    
    $result = saveMenuItem($pdo, $input);
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("メニュー保存エラー: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'エラーが発生しました']);
}
