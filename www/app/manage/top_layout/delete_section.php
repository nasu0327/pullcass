<?php
/**
 * セクション削除処理
 * 関連するバナー画像・テキストコンテンツ内の画像も削除
 */

// 認証チェック
require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

// 共通ファイル読み込み
require_once __DIR__ . '/../../../includes/bootstrap.php';

// JSON形式で返す
header('Content-Type: application/json');

// テナント情報取得
$tenantAdmin = getCurrentTenantAdmin();
$tenantId = $tenantAdmin['tenant_id'];

// POSTデータを取得
$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? 0;

if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'IDが指定されていません']);
    exit;
}

/**
 * HTMLコンテンツから画像パスを抽出する
 */
function extractImagePathsFromHtml($html) {
    $paths = [];
    // src属性から画像パスを抽出
    if (preg_match_all('/src=["\']([^"\']+)["\']/', $html, $matches)) {
        foreach ($matches[1] as $src) {
            // top_layout/api/uploads内の画像のみ対象
            if (strpos($src, '/manage/top_layout/api/uploads/') !== false) {
                $paths[] = $src;
            }
        }
    }
    return $paths;
}

try {
    $pdo->beginTransaction();
    
    // セクション情報を取得（削除前に画像情報を取得するため）
    $stmt = $pdo->prepare("SELECT section_type, config FROM top_layout_sections WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$id, $tenantId]);
    $section = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$section) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'セクションが見つかりません']);
        exit;
    }
    
    $deletedImages = 0;
    
    // text_contentセクションの場合、HTMLから画像を抽出して削除
    if ($section['section_type'] === 'text_content') {
        $config = json_decode($section['config'], true) ?: [];
        $htmlContent = $config['html_content'] ?? '';
        
        if ($htmlContent) {
            $imagePaths = extractImagePathsFromHtml($htmlContent);
            foreach ($imagePaths as $imagePath) {
                $image_file = $_SERVER['DOCUMENT_ROOT'] . $imagePath;
                if (file_exists($image_file)) {
                    unlink($image_file);
                    $deletedImages++;
                }
            }
        }
    }
    
    // 関連するバナー画像を取得
    $stmt = $pdo->prepare("SELECT image_path FROM top_layout_banners WHERE section_id = ? AND tenant_id = ?");
    $stmt->execute([$id, $tenantId]);
    $banners = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // バナー画像ファイルを削除
    foreach ($banners as $banner) {
        $image_file = $_SERVER['DOCUMENT_ROOT'] . $banner['image_path'];
        if (file_exists($image_file)) {
            unlink($image_file);
            $deletedImages++;
        }
    }
    
    // 関連するバナーレコードを削除
    $stmt = $pdo->prepare("DELETE FROM top_layout_banners WHERE section_id = ? AND tenant_id = ?");
    $stmt->execute([$id, $tenantId]);
    $deletedBanners = $stmt->rowCount();
    
    // セクションを削除
    $stmt = $pdo->prepare("DELETE FROM top_layout_sections WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$id, $tenantId]);
    
    $pdo->commit();
    
    $message = '削除しました';
    if ($deletedImages > 0) {
        $message .= "（画像{$deletedImages}件も削除）";
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'deleted_banners' => $deletedBanners,
        'deleted_images' => $deletedImages
    ]);
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Delete section error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'データベースエラー: ' . $e->getMessage()
    ]);
}
