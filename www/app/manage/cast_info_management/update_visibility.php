<?php
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// ログインチェック
if (!isset($_SESSION['tenant_admin_logged_in']) || $_SESSION['tenant_admin_logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$pdo = getPlatformDb();
$tenantId = $tenant['id'];

// JSONデータの取得
$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!isset($data['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

try {
    $pdo->beginTransaction();
    require_once __DIR__ . '/functions.php';
    $tableName = getActiveCastTable($pdo, $tenantId);

    // 現在の状態を取得
    $stmt = $pdo->prepare("SELECT checked FROM {$tableName} WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$data['id'], $tenantId]);
    $current = $stmt->fetchColumn();

    if ($current === false) {
        throw new Exception('Cast not found');
    }

    // 反転させる (1 -> 0, 0 -> 1)
    $newState = ($current == 1) ? 0 : 1;

    $stmt = $pdo->prepare("UPDATE {$tableName} SET checked = ? WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$newState, $data['id'], $tenantId]);

    $pdo->commit();

    echo json_encode(['success' => true, 'newState' => $newState]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
