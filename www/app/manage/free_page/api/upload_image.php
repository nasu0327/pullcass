<?php
/**
 * フリーページ用画像アップロードAPI
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

// ファイルがアップロードされているか確認
if (!isset($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ファイルが選択されていません']);
    exit;
}

// テナントコードを取得
$tenantCode = $tenant['code'] ?? 'default';

// 画像をアップロード
$result = uploadFreePageImage($_FILES['file'], $tenantCode);

if ($result['success']) {
    // TinyMCE互換のレスポンス
    echo json_encode([
        'success' => true,
        'url' => $result['url'],
        'location' => $result['url']  // TinyMCE標準フォーマット
    ]);
} else {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $result['error']
    ]);
}
