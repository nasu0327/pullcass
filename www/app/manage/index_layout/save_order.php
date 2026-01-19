<?php
/**
 * 順序保存処理（インデックスページ用）
 * 1カラム構成なのでdisplay_orderのみ管理
 */

// 認証チェック（$tenant, $tenantIdが自動設定される）
require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

header('Content-Type: application/json');

// POSTデータを取得
$input = json_decode(file_get_contents('php://input'), true);
$order = $input['order'] ?? [];
$autoSave = $input['autoSave'] ?? false;

if (empty($order)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '順序データが指定されていません']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // 順序を更新（heroセクションは除外されているはず）
    foreach ($order as $index => $id) {
        // display_orderは2から開始（1はheroセクション用に予約）
        $stmt = $pdo->prepare("UPDATE index_layout_sections SET display_order = ? WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$index + 2, $id, $tenantId]);
    }
    
    // 手動保存の場合のみ、下書きスナップショットを保存
    if (!$autoSave) {
        // 当該テナントの下書きをクリア
        $stmt = $pdo->prepare("DELETE FROM index_layout_sections_saved WHERE tenant_id = ?");
        $stmt->execute([$tenantId]);
        
        // 当該テナントの現在の状態をスナップショット
        $stmt = $pdo->prepare("
            INSERT INTO index_layout_sections_saved 
            SELECT * FROM index_layout_sections 
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
