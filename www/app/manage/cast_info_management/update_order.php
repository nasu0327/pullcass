<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/functions.php';
// JSONレスポンスのみ返すため、HTMLヘッダー等は不要だが認証は必要

header('Content-Type: application/json');

// ログイン状態のチェック（AJAX用なのでリダイレクトせずJSONエラーを返す）
if (!isTenantAdminLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$pdo = getPlatformDb();
$tenantId = $tenant['id'];
$tableName = getActiveCastTable($pdo, $tenantId);

// JSONデータの取得
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['order']) || !is_array($input['order'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 各キャストのsort_orderを更新
    // sort_orderは小さい順に1, 2, 3...
    $stmt = $pdo->prepare("UPDATE {$tableName} SET sort_order = ? WHERE id = ? AND tenant_id = ?");

    foreach ($input['order'] as $index => $cast_id) {
        $sort_order = $index + 1;
        // tenant_idも条件に加えて、他テナントのデータ書き換えを防止
        $stmt->execute([$sort_order, $cast_id, $tenantId]);
    }

    $pdo->commit();

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
