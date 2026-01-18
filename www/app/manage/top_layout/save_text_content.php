<?php
/**
 * テキストコンテンツ保存API
 */

// 認証チェック（$tenant, $tenantIdが自動設定される）
require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();


// POSTデータを取得
$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? 0;
$admin_title = $input['admin_title'] ?? '';
$title_en = $input['title_en'] ?? '';
$title_ja = $input['title_ja'] ?? '';
$html_content = $input['html_content'] ?? '';

if (!$id || !$admin_title) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'IDと管理名は必須です']);
    exit;
}

try {
    // configをJSON形式で保存
    $config = json_encode([
        'html_content' => $html_content
    ], JSON_UNESCAPED_UNICODE);
    
    $stmt = $pdo->prepare("
        UPDATE top_layout_sections 
        SET admin_title = ?, title_en = ?, title_ja = ?, config = ?, updated_at = CURRENT_TIMESTAMP 
        WHERE id = ? AND tenant_id = ? AND section_type = 'text_content'
    ");
    $stmt->execute([$admin_title, $title_en, $title_ja, $config, $id, $tenantId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'テキストコンテンツを更新しました'
    ]);
    
} catch (PDOException $e) {
    error_log("Save text content error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'データベースエラー: ' . $e->getMessage()
    ]);
}
