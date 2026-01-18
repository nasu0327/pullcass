<?php
/**
 * 順序保存処理
 * autoSave=true: top_layout_sections の順序のみ更新（ドラッグ&ドロップ時）
 * autoSave=false: 上記 + top_layout_sections_saved にスナップショットを保存（手動保存時）
 */

// 認証チェック（$tenant, $tenantIdが自動設定される）
require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

// JSON形式で返す
header('Content-Type: application/json');

// POSTデータを取得
$input = json_decode(file_get_contents('php://input'), true);
$leftOrder = $input['leftOrder'] ?? [];
$rightOrder = $input['rightOrder'] ?? [];
$autoSave = $input['autoSave'] ?? false; // 自動保存フラグ

if (empty($leftOrder) && empty($rightOrder)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '順序データが指定されていません']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // まず、全セクションの pc_left_order と pc_right_order を NULL にリセット
    $stmt = $pdo->prepare("UPDATE top_layout_sections SET pc_left_order = NULL, pc_right_order = NULL WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    
    // 左カラムの順序を更新
    foreach ($leftOrder as $index => $id) {
        $stmt = $pdo->prepare("UPDATE top_layout_sections SET pc_left_order = ? WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$index + 1, $id, $tenantId]);
    }
    
    // 右カラムの順序を更新
    foreach ($rightOrder as $index => $id) {
        $stmt = $pdo->prepare("UPDATE top_layout_sections SET pc_right_order = ? WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$index + 1, $id, $tenantId]);
    }
    
    // 手動保存の場合のみ、下書きスナップショットを保存
    if (!$autoSave) {
        // 当該テナントの下書きをクリア
        $stmt = $pdo->prepare("DELETE FROM top_layout_sections_saved WHERE tenant_id = ?");
        $stmt->execute([$tenantId]);
        
        // 当該テナントの現在の状態をスナップショット
        $stmt = $pdo->prepare("
            INSERT INTO top_layout_sections_saved 
            SELECT * FROM top_layout_sections 
            WHERE tenant_id = ?
        ");
        $stmt->execute([$tenantId]);
    }
    
    $pdo->commit();
    
    $message = $autoSave ? '順序を更新しました' : '下書きを保存しました';
    echo json_encode([
        'success' => true,
        'message' => $message
    ]);
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Save order error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'データベースエラー: ' . $e->getMessage()
    ]);
}
