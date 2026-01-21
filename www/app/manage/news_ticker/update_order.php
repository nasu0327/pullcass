<?php
/**
 * pullcass - ニュースティッカー 並び順更新
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
$order = $input['order'] ?? [];

if (empty($order)) {
    echo json_encode(['success' => false, 'message' => 'Order is required']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("UPDATE news_tickers SET display_order = ? WHERE id = ? AND tenant_id = ?");
    
    foreach ($order as $index => $id) {
        $stmt->execute([$index, $id, $tenantId]);
    }
    
    $pdo->commit();
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'message' => APP_DEBUG ? $e->getMessage() : 'Database error'
    ]);
}
