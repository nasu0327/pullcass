<?php
/**
 * pullcass - マスター管理画面
 * 料金表管理 - 料金セット削除API
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
    // 平常期間は削除不可
    $stmt = $pdo->prepare("SELECT set_type FROM price_sets WHERE id = ?");
    $stmt->execute([$id]);
    $set = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$set) {
        echo json_encode(['success' => false, 'message' => '指定された料金セットが見つかりません']);
        exit;
    }
    
    if ($set['set_type'] === 'regular') {
        echo json_encode(['success' => false, 'message' => '平常期間料金は削除できません']);
        exit;
    }
    
    // 削除（CASCADE設定により関連データも自動削除）
    $stmt = $pdo->prepare("DELETE FROM price_sets WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    error_log('Price set delete error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'データベースエラーが発生しました']);
}
