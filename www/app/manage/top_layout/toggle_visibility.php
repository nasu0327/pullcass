<?php
/**
 * 表示/非表示切り替え
 * PC版・スマホ版別々に管理
 */

// 認証チェック（$tenant, $tenantIdが自動設定される）
require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();


// POSTデータを取得
$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? null;
$type = $input['type'] ?? 'pc'; // 'pc' or 'mobile'

if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'IDが指定されていません']);
    exit;
}

// カラム名を決定
$column = ($type === 'mobile') ? 'mobile_visible' : 'is_visible';

try {
    // 現在の表示状態を取得（編集中テーブルのみ対象）
    $stmt = $pdo->prepare("SELECT {$column} FROM top_layout_sections WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$id, $tenantId]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$current) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'セクションが見つかりません']);
        exit;
    }
    
    // 表示状態をトグル
    $newVisibility = $current[$column] ? 0 : 1;
    
    // 更新
    $stmt = $pdo->prepare("UPDATE top_layout_sections SET {$column} = ? WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$newVisibility, $id, $tenantId]);
    
    $message = $type === 'mobile' 
        ? ($newVisibility ? 'スマホで表示します' : 'スマホで非表示にしました')
        : ($newVisibility ? 'PCで表示します' : 'PCで非表示にしました');
    
    echo json_encode([
        'success' => true,
        'is_visible' => (bool)$newVisibility,
        'type' => $type,
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
