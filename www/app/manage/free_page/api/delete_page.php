<?php
/**
 * フリーページ削除API
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

if (!isset($input['id']) || !is_numeric($input['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ページIDが無効です']);
    exit;
}

$pageId = (int) $input['id'];
$pdo = getPlatformDb();

// ページの存在確認
$page = getFreePage($pdo, $pageId, $tenantId);
if (!$page) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'ページが見つかりません']);
    exit;
}

try {
    $basePath = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') : (__DIR__ . '/../../../../');

    // アイキャッチ画像の削除
    if (!empty($page['featured_image'])) {
        $imagePath = $basePath . '/' . ltrim($page['featured_image'], '/');
        if (is_file($imagePath)) {
            @unlink($imagePath);
        }
    }

    // コンテンツ内に挿入された画像（TinyMCE経由）の削除
    if (!empty($page['content'])) {
        $tenantCode = $tenant['code'] ?? '';
        if ($tenantCode !== '') {
            deleteFreePageContentImages($page['content'], $tenantCode, $basePath);
        }
    }

    // ページを削除
    deleteFreePage($pdo, $pageId, $tenantId);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => '削除に失敗しました']);
}
