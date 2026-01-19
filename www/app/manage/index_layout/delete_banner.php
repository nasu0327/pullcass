<?php
/**
 * バナー削除（インデックスページ用）
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
    // バナー情報を取得
    $stmt = $pdo->prepare("SELECT image_path FROM index_layout_banners WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$id, $tenantId]);
    $banner = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$banner) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'バナーが見つかりません']);
        exit;
    }
    
    // 画像ファイルを削除
    $image_file = $_SERVER['DOCUMENT_ROOT'] . $banner['image_path'];
    if (file_exists($image_file)) {
        unlink($image_file);
    }
    
    // レコードを削除
    $stmt = $pdo->prepare("DELETE FROM index_layout_banners WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$id, $tenantId]);
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    error_log("Delete banner error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
