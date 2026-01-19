<?php
/**
 * バナー情報取得（インデックスページ用）
 */

require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

header('Content-Type: application/json');

$id = $_GET['id'] ?? 0;

if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'IDが指定されていません']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM index_layout_banners WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$id, $tenantId]);
    $banner = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($banner) {
        echo json_encode(['success' => true, 'banner' => $banner]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'バナーが見つかりません']);
    }
    
} catch (PDOException $e) {
    error_log("Get banner error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
