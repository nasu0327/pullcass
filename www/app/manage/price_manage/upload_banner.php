<?php
/**
 * pullcass - マスター管理画面
 * 料金表管理 - バナー画像アップロードAPI
 */

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// ログイン認証チェック
requireTenantAdminLogin();

$pdo = getPlatformDb();
if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'データベースに接続できません']);
    exit;
}

$contentId = intval($_POST['content_id'] ?? 0);

if (!$contentId) {
    echo json_encode(['success' => false, 'message' => 'コンテンツIDが指定されていません']);
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'ファイルのアップロードに失敗しました']);
    exit;
}

$file = $_FILES['file'];
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

if (!in_array($file['type'], $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => '許可されていないファイル形式です']);
    exit;
}

// アップロード先ディレクトリ
$uploadDir = __DIR__ . '/../../img/price_banners/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// ファイル名を生成
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'banner_' . $contentId . '_' . time() . '.' . $extension;
$uploadPath = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
    echo json_encode(['success' => false, 'message' => 'ファイルの保存に失敗しました']);
    exit;
}

$relativePath = '/img/price_banners/' . $filename;

try {
    // DBを更新
    $stmt = $pdo->prepare("
        UPDATE price_banners 
        SET image_path = ?
        WHERE content_id = ?
    ");
    $stmt->execute([$relativePath, $contentId]);
    
    echo json_encode(['success' => true, 'path' => $relativePath]);
    
} catch (PDOException $e) {
    error_log('Upload banner error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'データベースエラーが発生しました']);
}
