<?php
/**
 * メニュー管理 - ステータス切り替え
 * メニュー項目の有効/無効を切り替え
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
    
    if (!isset($input['id'])) {
        echo json_encode(['success' => false, 'message' => '不正なリクエストです']);
        exit;
    }
    
    $result = toggleMenuStatus($pdo, $input['id'], $tenantId);
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("ステータス切り替えエラー: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'エラーが発生しました']);
}
