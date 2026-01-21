<?php
/**
 * pullcass - ニュースティッカー 表示/非表示切り替え
 */

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

// ログイン認証チェック
requireTenantAdminLogin();

// JSONリクエストのみ受け付け
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

// JSONデータを取得
$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? null;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID is required']);
    exit;
}

try {
    // 現在の状態を取得
    $stmt = $pdo->prepare("SELECT is_visible FROM news_tickers WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$id, $tenantId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        echo json_encode(['success' => false, 'message' => 'Item not found']);
        exit;
    }
    
    // 状態を反転
    $newVisibility = $item['is_visible'] ? 0 : 1;
    
    $updateStmt = $pdo->prepare("UPDATE news_tickers SET is_visible = ? WHERE id = ? AND tenant_id = ?");
    $updateStmt->execute([$newVisibility, $id, $tenantId]);
    
    echo json_encode([
        'success' => true,
        'is_visible' => (bool)$newVisibility
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => APP_DEBUG ? $e->getMessage() : 'Database error'
    ]);
}
