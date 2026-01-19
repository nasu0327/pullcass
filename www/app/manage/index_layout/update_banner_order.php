<?php
/**
 * バナー順序更新（インデックスページ用）
 */

require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$order = $input['order'] ?? [];

if (empty($order)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '順序データが指定されていません']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    foreach ($order as $index => $id) {
        $stmt = $pdo->prepare("UPDATE index_layout_banners SET display_order = ? WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$index + 1, $id, $tenantId]);
    }
    
    $pdo->commit();
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Update banner order error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
