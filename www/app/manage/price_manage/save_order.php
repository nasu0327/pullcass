<?php
/**
 * pullcass - マスター管理画面
 * 料金表管理 - コンテンツ順序保存API
 */

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// ログイン認証チェック
requireTenantAdminLogin();

$pdo = getPlatformDb();
if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'データベースに接続できません']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$order = $input['order'] ?? [];

if (empty($order)) {
    echo json_encode(['success' => false, 'message' => '順序が指定されていません']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE price_contents SET display_order = ? WHERE id = ?");
    
    foreach ($order as $index => $id) {
        $stmt->execute([$index, intval($id)]);
    }
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    error_log('Save order error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'データベースエラーが発生しました']);
}
