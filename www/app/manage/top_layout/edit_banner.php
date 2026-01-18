<?php
/**
 * バナー編集API
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
        UPDATE top_layout_banners 
        SET link_url = ?, target = ?, nofollow = ?, alt_text = ?, updated_at = CURRENT_TIMESTAMP
        WHERE id = ? AND tenant_id = ?
    ");
    $stmt->execute([$link_url, $target, $nofollow, $alt_text, $id, $tenantId]);
    
    echo json_encode([
        'success' => true,
        'message' => 'バナーを更新しました'
    ]);
    
} catch (PDOException $e) {
    error_log("Edit banner error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'データベースエラー: ' . $e->getMessage()
    ]);
}
