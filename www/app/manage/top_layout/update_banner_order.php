<?php
/**
 * バナー順序更新API
 */

// 認証チェック（$tenant, $tenantIdが自動設定される）
require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

// JSON形式で返す
header('Content-Type: application/json');

// POSTデータを取得
$input = json_decode(file_get_contents('php://input'), true);
$order = $input['order'] ?? [];

if (empty($order)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '順序データが指定されていません']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // 各バナーの表示順を更新
    foreach ($order as $index => $id) {
        $stmt = $pdo->prepare("
            UPDATE top_layout_banners 
            SET display_order = ? 
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([$index + 1, $id, $tenantId]);
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => '順序を更新しました'
    ]);
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Update banner order error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'データベースエラー: ' . $e->getMessage()
    ]);
}
