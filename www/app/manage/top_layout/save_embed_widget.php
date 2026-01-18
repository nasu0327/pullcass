<?php
/**
 * 埋め込みウィジェット保存API
 */

// 認証チェック
require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

// 共通ファイル読み込み
require_once __DIR__ . '/../../includes/database.php';

// JSON形式で返す
header('Content-Type: application/json');

// テナント情報取得
$tenantAdmin = getCurrentTenantAdmin();
$tenantId = $tenantAdmin['tenant_id'];

// POSTデータを取得
$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? 0;
$admin_title = $input['admin_title'] ?? '';
$title_en = $input['title_en'] ?? '';
$title_ja = $input['title_ja'] ?? '';
$embed_code = $input['embed_code'] ?? '';
$embed_height = $input['embed_height'] ?? '400';

if (!$id || !$admin_title || !$embed_code) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID、管理名、埋め込みコードは必須です']);
    exit;
}

try {
    // configをJSON形式で保存
    $config = json_encode([
        'embed_code' => $embed_code,
        'embed_height' => $embed_height
    ], JSON_UNESCAPED_UNICODE);
    
    $stmt = $pdo->prepare("
        UPDATE top_layout_sections 
        SET admin_title = ?, title_en = ?, title_ja = ?, config = ?, updated_at = CURRENT_TIMESTAMP 
        WHERE id = ? AND tenant_id = ? AND section_type = 'embed_widget'
    ");
    $stmt->execute([$admin_title, $title_en, $title_ja, $config, $id, $tenantId]);
    
    echo json_encode([
        'success' => true,
        'message' => '埋め込みウィジェットを更新しました'
    ]);
    
} catch (PDOException $e) {
    error_log("Save embed widget error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'データベースエラー: ' . $e->getMessage()
    ]);
}
