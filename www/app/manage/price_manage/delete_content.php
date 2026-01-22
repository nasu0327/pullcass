<?php
/**
 * pullcass - マスター管理画面
 * 料金表管理 - コンテンツ削除API
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
$id = intval($input['id'] ?? 0);

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'IDが指定されていません']);
    exit;
}

try {
    // CASCADE設定により関連データも自動削除
    $stmt = $pdo->prepare("DELETE FROM price_contents WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    error_log('Delete content error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'データベースエラーが発生しました']);
}
