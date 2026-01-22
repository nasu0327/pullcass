<?php
/**
 * pullcass - マスター管理画面
 * 料金表管理用画像アップロードAPI
 * TinyMCEエディタからの画像アップロードに対応
 */

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/auth.php';

// ログイン状態のチェック
if (!isSuperAdminLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ファイルが選択されていません']);
    exit;
}

$file = $_FILES['file'];

// ファイルタイプチェック
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($file['type'], $allowedTypes)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => '画像ファイルのみアップロード可能です']);
    exit;
}

// ファイルサイズチェック（5MB制限）
$maxSize = 5 * 1024 * 1024; // 5MB
if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'ファイルサイズが大きすぎます（5MB以下）']);
    exit;
}

try {
    $uploadDir = '/admin/price_manage/uploads/';
    $uploadPath = $_SERVER['DOCUMENT_ROOT'] . $uploadDir;

    // ディレクトリがなければ作成
    if (!is_dir($uploadPath)) {
        mkdir($uploadPath, 0755, true);
    }

    // ファイル名生成
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'price_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($extension);
    
    $fullPath = $uploadPath . $filename;

    // ファイル移動
    if (move_uploaded_file($file['tmp_name'], $fullPath)) {
        $url = $uploadDir . $filename;
        
        echo json_encode([
            'success' => true,
            'url' => $url,
            'location' => $url  // TinyMCE互換
        ]);
    } else {
        throw new Exception('ファイルの保存に失敗しました');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
