<?php
/**
 * フリーページ並び順更新API
 */

// 認証チェック（$tenant, $tenantIdが自動設定される）
require_once __DIR__ . '/../../includes/auth.php';
requireTenantAdminLogin();

require_once __DIR__ . '/../../../../includes/free_page_helpers.php';

header('Content-Type: application/json');

// POSTメソッドのみ許可
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => '無効なリクエストです']);
    exit;
}

// リクエストボディを取得
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['orders']) || !is_array($input['orders'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => '並び順データが無効です']);
    exit;
}

$pdo = getPlatformDb();

try {
    updateFreePageOrder($pdo, $input['orders'], $tenantId);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => '更新に失敗しました']);
}
