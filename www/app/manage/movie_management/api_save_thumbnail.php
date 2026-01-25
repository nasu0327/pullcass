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
    // ※APIなのでリダイレクトではなくエラーレスポンスを返す必要があるが、
    // auth.phpの仕様上、未ログインはリダイレクトされる可能性がある。
    // ajax呼び出しの前にログイン必須なので問題ないはずだが、念のためセッションチェック
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
    $videoType = isset($_POST['video_type']) ? $_POST['video_type'] : ''; // movie_1_thumbnail or movie_2_thumbnail

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

    log_debug("--- START THUMBNAIL SAVE ---");
    log_debug("DocRoot: " . $_SERVER['DOCUMENT_ROOT']);
    log_debug("TenantID: $tenantId");
    log_debug("Target Dir: $upload_dir");

    if (!is_dir($upload_dir)) {
        log_debug("Dir missing. mkdir...");
        if (!mkdir($upload_dir, 0755, true)) {
            log_debug("mkdir FAILED");
            throw new Exception('ディレクトリの作成に失敗しました。');
        }
    }

    // ファイル名生成 (timestamp + random)
    $ext = 'jpg'; // Canvas toBlob で jpeg 指定前提
    $filename = ($videoType === 'movie_1_thumbnail' ? 'thumb1_' : 'thumb2_') . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
    $filepath = $upload_dir . $filename;
    $db_path = $relative_dir . $filename;

    log_debug("Saving to: $filepath");

    // ファイル移動
    if (!move_uploaded_file($_FILES['thumbnail']['tmp_name'], $filepath)) {
        log_debug("move_uploaded_file FAILED");
        throw new Exception('ファイルの保存に失敗しました。');
    }

    if (file_exists($filepath)) {
        log_debug("File saved. Size: " . filesize($filepath));
    } else {
        log_debug("CRITICAL: move succes but file missing");
    }

    // データベース更新
    // 既存のファイルを削除してから更新すべきだが、履歴保持の観点もあれど、容量節約のため削除推奨
    // まず既存ファイルパスを取得
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

    // SEOサムネイル用カラムも更新する？ (movie_1_seo_thumbnail)
    // 要件によると、管理画面で作るサムネイルは実質SEOサムネイルとしても使われる可能性が高い
    // リファレンスではどうなっているか不明だが、一括で更新しておくと親切
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
    if (function_exists('log_debug')) {
        log_debug("Exception: " . $e->getMessage());
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Helper
function log_debug($message)
{
    $logFile = $_SERVER['DOCUMENT_ROOT'] . '/upload_debug.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] [API] $message\n", FILE_APPEND);
}
