<?php
/**
 * pullcass - マスター管理画面
 * 料金表管理 - 料金表行順序保存API
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!isSuperAdminLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'ログインが必要です']);
    exit;
}

$pdo = getPlatformDb();
if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'データベースに接続できません']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$tableId = intval($input['table_id'] ?? 0);
$order = $input['order'] ?? [];

if (!$tableId || empty($order)) {
    echo json_encode(['success' => false, 'message' => 'パラメータが不足しています']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE price_rows SET display_order = ? WHERE id = ? AND table_id = ?");
    
    foreach ($order as $index => $id) {
        $stmt->execute([$index, intval($id), $tableId]);
    }
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    error_log('Save row order error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'データベースエラーが発生しました']);
}
