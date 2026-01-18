<?php
/**
 * Hero Text保存API
 */

// 認証チェック（$tenant, $tenantIdが自動設定される）
require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();


// POSTデータを取得
$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? 0;
$h1_title = $input['h1_title'] ?? '';
$intro_text = $input['intro_text'] ?? '';

if (!$id || !$h1_title || !$intro_text) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'すべての項目は必須です']);
    exit;
}

try {
    // configをJSON形式で保存
    $config = json_encode([
        'h1_title' => $h1_title,
        'intro_text' => $intro_text
    ], JSON_UNESCAPED_UNICODE);
    
    $stmt = $pdo->prepare("
        UPDATE top_layout_sections 
        SET config = ?, updated_at = CURRENT_TIMESTAMP 
        WHERE id = ? AND tenant_id = ? AND section_key = 'hero_text'
    ");
    $stmt->execute([$config, $id, $tenantId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Hero Textを更新しました'
    ]);
    
} catch (PDOException $e) {
    error_log("Save hero text error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'データベースエラー: ' . $e->getMessage()
    ]);
}
