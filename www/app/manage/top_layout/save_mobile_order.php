<?php
/**
 * スマホ表示順序の保存処理
 * mobile_orderを更新
 */

// 認証チェック（$tenant, $tenantIdが自動設定される）
require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();


// POSTデータを取得
$input = json_decode(file_get_contents('php://input'), true);
$mobileOrder = $input['mobileOrder'] ?? [];

if (empty($mobileOrder)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '順序データが指定されていません']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // スマホの順序を更新
    foreach ($mobileOrder as $index => $id) {
        $stmt = $pdo->prepare("UPDATE top_layout_sections SET mobile_order = ? WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$index + 1, $id, $tenantId]);
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'スマホ順序を更新しました'
    ]);
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Save mobile order error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'データベースエラー: ' . $e->getMessage()
    ]);
}
