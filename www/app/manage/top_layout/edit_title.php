<?php
/**
 * タイトル編集API
 */

// 認証チェック
require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

// 共通ファイル読み込み
require_once __DIR__ . '/../../../includes/bootstrap.php';

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

if (!$id || !$admin_title) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'IDと管理名は必須です']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE top_layout_sections 
        SET admin_title = ?, title_en = ?, title_ja = ?, updated_at = CURRENT_TIMESTAMP 
        WHERE id = ? AND tenant_id = ?
    ");
    $stmt->execute([$admin_title, $title_en, $title_ja, $id, $tenantId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'タイトルを更新しました'
    ]);
    
} catch (PDOException $e) {
    error_log("Edit title error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'データベースエラー: ' . $e->getMessage()
    ]);
}
