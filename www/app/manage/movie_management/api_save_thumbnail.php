<?php
/**
 * サムネイル画像保存API (CanvasからのBlob受け取り用)
 */

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

// JSONレスポンス用ヘッダー
header('Content-Type: application/json');

try {
    // ログイン認証チェック
    if (!isset($_SESSION['manage_tenant_id'])) {
        // auth.php で設定されるはずだが、念の為
    }

    // auth.php をインクルード済みなので、$tenantId, $tenantSlug, $pdo が使えるはず
    if (!isset($pdo) || !isset($tenantId)) {
        throw new Exception('認証セッションが無効です。再ログインしてください。');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('不正なリクエストメソッドです。');
    }

    // パラメータ取得
    $castId = isset($_POST['cast_id']) ? (int) $_POST['cast_id'] : 0;
    $videoType = isset($_POST['video_type']) ? $_POST['video_type'] : '';

    if (!$castId) {
        throw new Exception('キャストIDが指定されていません。');
    }

    if (!in_array($videoType, ['movie_1_thumbnail', 'movie_2_thumbnail'])) {
        throw new Exception('無効なサムネイルタイプです。');
    }

    // キャストの存在とテナント権限チェック
    $stmt = $pdo->prepare("SELECT id FROM tenant_casts WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$castId, $tenantId]);
    if (!$stmt->fetch()) {
        throw new Exception('指定されたキャストが見つからないか、権限がありません。');
    }

    // 画像データの受け取り
    if (!isset($_FILES['thumbnail']) || $_FILES['thumbnail']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('画像データの受信に失敗しました。');
    }

    // 保存ディレクトリ作成
    $relative_dir = '/img/tenants/' . $tenantId . '/movie/';
    $upload_dir = $_SERVER['DOCUMENT_ROOT'] . $relative_dir;

    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            throw new Exception('ディレクトリの作成に失敗しました。');
        }
    }

    // ファイル名生成 (timestamp + random)
    $ext = 'jpg';
    $filename = ($videoType === 'movie_1_thumbnail' ? 'thumb1_' : 'thumb2_') . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
    $filepath = $upload_dir . $filename;
    $db_path = $relative_dir . $filename;

    // ファイル移動
    if (!move_uploaded_file($_FILES['thumbnail']['tmp_name'], $filepath)) {
        throw new Exception('ファイルの保存に失敗しました。');
    }

    // 既存ファイルを削除
    $stmt = $pdo->prepare("SELECT $videoType FROM tenant_casts WHERE id = ?");
    $stmt->execute([$castId]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($current && !empty($current[$videoType])) {
        $old_file = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($current[$videoType], '/');
        if (file_exists($old_file) && is_file($old_file)) {
            unlink($old_file);
        }
    }

    // DB更新
    $sql = "UPDATE tenant_casts SET $videoType = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$db_path, $castId]);

    // SEOサムネイル用カラムも更新
    $seoColumn = str_replace('_thumbnail', '_seo_thumbnail', $videoType);
    $sqlSeo = "UPDATE tenant_casts SET $seoColumn = ? WHERE id = ?";
    $stmtSeo = $pdo->prepare($sqlSeo);
    $stmtSeo->execute([$db_path, $castId]);

    echo json_encode([
        'success' => true,
        'message' => 'サムネイルを保存しました。',
        'thumbnail_url' => $db_path
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
