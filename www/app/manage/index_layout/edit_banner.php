<?php
/**
 * バナー編集（インデックスページ用）
 */

require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? 0;
$link_url = $input['link_url'] ?? '';
$target = $input['target'] ?? '_self';
$nofollow = $input['nofollow'] ?? 0;
$alt_text = $input['alt_text'] ?? '';

if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'IDが指定されていません']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE index_layout_banners 
        SET link_url = ?, target = ?, nofollow = ?, alt_text = ?
        WHERE id = ? AND tenant_id = ?
    ");
    $stmt->execute([$link_url, $target, $nofollow, $alt_text, $id, $tenantId]);
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    error_log("Edit banner error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
