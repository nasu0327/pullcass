<?php
/**
 * バナー表示/非表示切り替え（インデックスページ用）
 */

require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? 0;

if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'IDが指定されていません']);
    exit;
}

try {
    // 現在の状態を取得
    $stmt = $pdo->prepare("SELECT is_visible FROM index_layout_banners WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$id, $tenantId]);
    $banner = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$banner) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'バナーが見つかりません']);
        exit;
    }
    
    $newVisibility = $banner['is_visible'] ? 0 : 1;
    
    $stmt = $pdo->prepare("UPDATE index_layout_banners SET is_visible = ? WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$newVisibility, $id, $tenantId]);
    
    echo json_encode(['success' => true, 'is_visible' => (bool)$newVisibility]);
    
} catch (PDOException $e) {
    error_log("Toggle banner visibility error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
