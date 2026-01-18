<?php
/**
 * 画像アップロードAPI（TinyMCE用）
 */

// 認証チェック
require_once __DIR__ . '/../../includes/auth.php';
requireTenantAdminLogin();

// JSON形式で返す
header('Content-Type: application/json');

// テナント情報取得
$tenantAdmin = getCurrentTenantAdmin();
$tenantId = $tenantAdmin['tenant_id'];

// ファイルがアップロードされているか確認
if (!isset($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ファイルが選択されていません']);
    exit;
}

$file = $_FILES['file'];

// エラーチェック
if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'ファイルのアップロードに失敗しました']);
    exit;
}

// ファイルサイズチェック（5MB以下）
$maxSize = 5 * 1024 * 1024; // 5MB
if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['error' => 'ファイルサイズが大きすぎます（最大5MB）']);
    exit;
}

// ファイルタイプチェック
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['error' => '画像ファイルのみアップロード可能です（JPEG、PNG、GIF、WebP）']);
    exit;
}

// 拡張子を取得
$extension = '';
switch ($mimeType) {
    case 'image/jpeg':
    case 'image/jpg':
        $extension = 'jpg';
        break;
    case 'image/png':
        $extension = 'png';
        break;
    case 'image/gif':
        $extension = 'gif';
        break;
    case 'image/webp':
        $extension = 'webp';
        break;
}

// アップロードディレクトリ作成
$uploadDir = __DIR__ . '/../uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// ファイル名を生成（ユニーク）
$fileName = 'text_content_' . uniqid() . '_' . time() . '.' . $extension;
$uploadPath = $uploadDir . $fileName;

// ファイルを移動
if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'ファイルの保存に失敗しました']);
    exit;
}

// 成功レスポンス
$fileUrl = '/app/manage/top_layout/uploads/' . $fileName;
echo json_encode([
    'location' => $fileUrl
]);
