<?php
/**
 * 表示/非表示切り替え（インデックスページ用）
 */

// 認証チェック（$tenant, $tenantIdが自動設定される）
require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

header('Content-Type: application/json');

// POSTデータを取得
$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? null;

if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'IDが指定されていません']);
    exit;
}

try {
    // 現在の表示状態を取得（編集中テーブルのみ対象）
    $stmt = $pdo->prepare("SELECT is_visible FROM index_layout_sections WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$id, $tenantId]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$current) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'セクションが見つかりません']);
        exit;
    }
    
    // 表示状態をトグル
    $newVisibility = $current['is_visible'] ? 0 : 1;
    
    // 更新
    $stmt = $pdo->prepare("UPDATE index_layout_sections SET is_visible = ? WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$newVisibility, $id, $tenantId]);
    
    $message = $newVisibility ? '表示します' : '非表示にしました';
    
    echo json_encode([
        'success' => true,
        'is_visible' => (bool)$newVisibility,
        'message' => $message
    ]);
    
} catch (PDOException $e) {
    error_log("Toggle visibility error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'データベースエラー: ' . $e->getMessage()
    ]);
}
