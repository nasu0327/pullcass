<?php
/**
 * バナー表示/非表示切り替えAPI
 */

// 認証チェック（$tenant, $tenantIdが自動設定される）
require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

// JSON形式で返す
header('Content-Type: application/json');

// POSTデータを取得
$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? 0;

if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'IDが指定されていません']);
    exit;
}

try {
    // 現在の表示状態を取得
    $stmt = $pdo->prepare("
        SELECT is_visible FROM top_layout_banners 
        WHERE id = ? AND tenant_id = ?
    ");
    $stmt->execute([$id, $tenantId]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$current) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'バナーが見つかりません']);
        exit;
    }
    
    // 表示状態をトグル
    $newVisibility = $current['is_visible'] ? 0 : 1;
    
    // 更新
    $stmt = $pdo->prepare("
        UPDATE top_layout_banners 
        SET is_visible = ? 
        WHERE id = ? AND tenant_id = ?
    ");
    $stmt->execute([$newVisibility, $id, $tenantId]);
    
    echo json_encode([
        'success' => true,
        'is_visible' => (bool)$newVisibility,
        'message' => $newVisibility ? '表示にしました' : '非表示にしました'
    ]);
    
} catch (PDOException $e) {
    error_log("Toggle banner visibility error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'データベースエラー: ' . $e->getMessage()
    ]);
}
