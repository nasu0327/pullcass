<?php
/**
 * メニュー管理 - 並び順保存
 * ドラッグ&ドロップ後の並び順を保存
 */

require_once __DIR__ . '/../../../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../includes/menu_functions.php';

// ログイン認証
requireTenantAdminLogin();

// JSONレスポンス用のヘッダー
header('Content-Type: application/json');

try {
    $pdo = getPlatformDb();
    
    // POSTデータを取得
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['orders']) || !is_array($input['orders'])) {
        echo json_encode(['success' => false, 'message' => '不正なリクエストです']);
        exit;
    }
    
    $result = updateMenuOrder($pdo, $input['orders'], $tenantId);
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("並び順保存エラー: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'エラーが発生しました']);
}
