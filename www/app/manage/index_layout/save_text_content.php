<?php
/**
 * テキストコンテンツ保存（インデックスページ用）
 */

require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? 0;
$admin_title = $input['admin_title'] ?? '';
$title_en = $input['title_en'] ?? '';
$title_ja = $input['title_ja'] ?? '';
$html_content = $input['html_content'] ?? '';

if (!$id || !$admin_title) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '必須項目が不足しています']);
    exit;
}

try {
    // 現在のconfigを取得
    $stmt = $pdo->prepare("SELECT config FROM index_layout_sections WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$id, $tenantId]);
    $section = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$section) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'セクションが見つかりません']);
        exit;
    }
    
    $config = json_decode($section['config'], true) ?: [];
    $config['html_content'] = $html_content;
    
    $stmt = $pdo->prepare("
        UPDATE index_layout_sections 
        SET admin_title = ?, title_en = ?, title_ja = ?, config = ?
        WHERE id = ? AND tenant_id = ?
    ");
    $stmt->execute([$admin_title, $title_en, $title_ja, json_encode($config), $id, $tenantId]);
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    error_log("Save text content error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
