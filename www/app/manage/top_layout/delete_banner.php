<?php
/**
 * バナー削除API
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

if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'IDが指定されていません']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // バナー情報を取得（削除前に画像パスを取得）
    $stmt = $pdo->prepare("
        SELECT image_path FROM top_layout_banners 
        WHERE id = ? AND tenant_id = ?
    ");
    $stmt->execute([$id, $tenantId]);
    $banner = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$banner) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'バナーが見つかりません']);
        exit;
    }
    
    // ファイルを削除
    $imagePath = $_SERVER['DOCUMENT_ROOT'] . $banner['image_path'];
    if (file_exists($imagePath)) {
        unlink($imagePath);
    }
    
    // データベースから削除
    $stmt = $pdo->prepare("
        DELETE FROM top_layout_banners 
        WHERE id = ? AND tenant_id = ?
    ");
    $stmt->execute([$id, $tenantId]);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'バナーを削除しました'
    ]);
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Delete banner error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'データベースエラー: ' . $e->getMessage()
    ]);
}
