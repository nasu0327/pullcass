<?php
/**
 * セクションタイトル編集（インデックスページ用）
 */

require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? 0;
$admin_title = $input['admin_title'] ?? '';
$title_en = $input['title_en'] ?? '';
$title_ja = $input['title_ja'] ?? '';

if (!$id || !$admin_title) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '必須項目が不足しています']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE index_layout_sections 
        SET admin_title = ?, title_en = ?, title_ja = ?
        WHERE id = ? AND tenant_id = ?
    ");
    $stmt->execute([$admin_title, $title_en, $title_ja, $id, $tenantId]);
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    error_log("Edit title error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
