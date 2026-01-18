<?php
/**
 * バナー情報取得API
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

$id = $_GET['id'] ?? 0;

if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'IDが指定されていません']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT * FROM top_layout_banners 
        WHERE id = ? AND tenant_id = ?
    ");
    $stmt->execute([$id, $tenantId]);
    $banner = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$banner) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'バナーが見つかりません']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'banner' => $banner
    ]);
    
} catch (PDOException $e) {
    error_log("Get banner error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'データベースエラー: ' . $e->getMessage()
    ]);
}
