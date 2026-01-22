<?php
/**
 * pullcass - マスター管理画面
 * 料金表管理 - 料金表行追加API
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

if (!$tableId) {
    echo json_encode(['success' => false, 'message' => 'テーブルIDが指定されていません']);
    exit;
}

try {
    // 最大の display_order を取得
    $stmt = $pdo->prepare("SELECT MAX(display_order) FROM price_rows WHERE table_id = ?");
    $stmt->execute([$tableId]);
    $maxOrder = intval($stmt->fetchColumn()) + 1;
    
    // 行を追加
    $stmt = $pdo->prepare("
        INSERT INTO price_rows (table_id, time_label, price_label, display_order)
        VALUES (?, '', '', ?)
    ");
    $stmt->execute([$tableId, $maxOrder]);
    
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
    
} catch (PDOException $e) {
    error_log('Add row error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'データベースエラーが発生しました']);
}
