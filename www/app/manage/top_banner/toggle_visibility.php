<?php
/**
 * トップバナー表示/非表示切り替えAPI
 */

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!isTenantAdminLoggedIn()) {
    echo json_encode(['success' => false, 'message' => '認証エラー']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$id = (int)($input['id'] ?? 0);

if (!$id || !isset($tenantId)) {
    echo json_encode(['success' => false, 'message' => 'パラメータが不正です']);
    exit;
}

try {
    $pdo = getPlatformDb();
    
    // 現在の表示状態を取得
    $stmt = $pdo->prepare("SELECT is_visible FROM top_banners WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$id, $tenantId]);
    $currentState = $stmt->fetchColumn();
    
    if ($currentState === false) {
        echo json_encode(['success' => false, 'message' => 'バナーが見つかりません']);
        exit;
    }
    
    // 表示状態を反転（明示的にint型で扱う）
    $newState = $currentState ? 0 : 1;
    $stmt = $pdo->prepare("UPDATE top_banners SET is_visible = ? WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$newState, $id, $tenantId]);
    
    echo json_encode(['success' => true, 'is_visible' => (bool)$newState]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'データベースエラー']);
}
