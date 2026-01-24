<?php
/**
 * 動画アップロード処理
 */

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../../includes/VideoThumbnailHelper.php';

// ログイン認証チェック
requireTenantAdminLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // post_max_size 超過チェック
    if (empty($_POST) && empty($_FILES) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
        $postMax = ini_get('post_max_size');
        $displayMax = $postMax;
        $msg = "送信されたデータが大きすぎます。サーバー設定(post_max_size=$displayMax)を確認してください。";
        echo "<script>alert('" . addslashes($msg) . "'); window.history.back();</script>";
        exit;
    }

    try {
        // cast_idの検証
        if (!isset($_POST['cast_id']) || empty($_POST['cast_id'])) {
            throw new Exception('キャストIDが指定されていません。');
        }

        $cast_id = (int) $_POST['cast_id'];

        // ※重要: このキャストが現在のテナントに所属しているか確認
        $stmt = $pdo->prepare("SELECT id, tenant_id, movie_1, movie_2, movie_1_thumbnail, movie_2_thumbnail, movie_1_seo_thumbnail, movie_2_seo_thumbnail FROM tenant_casts WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$cast_id, $tenantId]);
        $current_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$current_data) {
            throw new Exception('不正なアクセスです。指定されたキャストは存在しないか、権限がありません。');
        }

        // アップロードディレクトリ設定（テナントごとに分離）
        $relative_upload_dir = '/img/tenants/' . $tenantId . '/movie/';
        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . $relative_upload_dir;

        // ディレクトリ作成
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                throw new Exception('アップロードディレクトリの作成に失敗しました。');
            }
        }

        // 既存のファイルを削除する関数
        function deleteExistingFile($current_data, $field)
        {
            if (!empty($current_data[$field])) {
                $file_path = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($current_data[$field], '/');
                if (file_exists($file_path) && is_file($file_path)) {
                    unlink($file_path);
                }
            }
        }

        // クリア処理（削除フラグがある場合）
        if (isset($_POST['clear_movie_1']) && $_POST['clear_movie_1'] == '1') {
            deleteExistingFile($current_data, 'movie_1');
            deleteExistingFile($current_data, 'movie_1_thumbnail');
            // DBから削除
            $sql = "UPDATE tenant_casts SET movie_1 = NULL, movie_1_thumbnail = NULL, movie_1_seo_thumbnail = NULL WHERE id = ?";
            $pdo->prepare($sql)->execute([$cast_id]);
            $current_data['movie_1'] = null;
        }

        if (isset($_POST['clear_movie_2']) && $_POST['clear_movie_2'] == '1') {
            deleteExistingFile($current_data, 'movie_2');
            deleteExistingFile($current_data, 'movie_2_thumbnail');
            // DBから削除
            $sql = "UPDATE tenant_casts SET movie_2 = NULL, movie_2_thumbnail = NULL, movie_2_seo_thumbnail = NULL WHERE id = ?";
            $pdo->prepare($sql)->execute([$cast_id]);
            $current_data['movie_2'] = null;
        }

        // アップロード処理を行う関数
        function handleUpload($file, $upload_dir, $relative_upload_dir, $prefix, $current_data, $field)
        {
            if ($file['error'] === UPLOAD_ERR_OK) {
                // 既存のファイルを削除
                deleteExistingFile($current_data, $field);

                $tmp_name = $file['tmp_name'];
                $name = $file['name'];
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

                $allowed_video = ['mp4', 'webm', 'ogg', 'mov', 'm4v'];
                $allowed_image = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $is_video = strpos($prefix, 'movie') !== false;

                if ($is_video && !in_array($ext, $allowed_video)) {
                    throw new Exception("許可されていない動画形式です: $ext");
                }
                if (!$is_video && !in_array($ext, $allowed_image)) {
                    throw new Exception("許可されていない画像形式です: $ext");
                }

                $new_name = $prefix . '_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
                $destination = $upload_dir . $new_name;

                if (move_uploaded_file($tmp_name, $destination)) {
                    return $relative_upload_dir . $new_name;
                } else {
                    throw new Exception("ファイルの移動に失敗しました。ディレクトリ権限を確認してください。");
                }
            }
            return null;
        }

        // ファイルサイズチェック（アプリケーション制限）
        $maxSizeCode = 100 * 1024 * 1024; // 100MB

        if (isset($_FILES['movie_1']) && $_FILES['movie_1']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['movie_1']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('動画1アップロードエラー: コード ' . $_FILES['movie_1']['error']);
            }
            if ($_FILES['movie_1']['size'] > $maxSizeCode) {
                throw new Exception('動画1のサイズが大きすぎます(100MB以下にしてください)');
            }
        }

        // アップロード実行
        $movie_1 = isset($_FILES['movie_1']) && $_FILES['movie_1']['error'] !== UPLOAD_ERR_NO_FILE
            ? handleUpload($_FILES['movie_1'], $upload_dir, $relative_upload_dir, 'movie1', $current_data, 'movie_1')
            : null;

        $movie_1_thumbnail = isset($_FILES['movie_1_thumbnail']) && $_FILES['movie_1_thumbnail']['error'] !== UPLOAD_ERR_NO_FILE
            ? handleUpload($_FILES['movie_1_thumbnail'], $upload_dir, $relative_upload_dir, 'thumb1', $current_data, 'movie_1_thumbnail')
            : null;

        $movie_2 = isset($_FILES['movie_2']) && $_FILES['movie_2']['error'] !== UPLOAD_ERR_NO_FILE
            ? handleUpload($_FILES['movie_2'], $upload_dir, $relative_upload_dir, 'movie2', $current_data, 'movie_2')
            : null;

        $movie_2_thumbnail = isset($_FILES['movie_2_thumbnail']) && $_FILES['movie_2_thumbnail']['error'] !== UPLOAD_ERR_NO_FILE
            ? handleUpload($_FILES['movie_2_thumbnail'], $upload_dir, $relative_upload_dir, 'thumb2', $current_data, 'movie_2_thumbnail')
            : null;

        // データベース更新
        $updates = [];
        $params = [];

        if ($movie_1 !== null) {
            $updates[] = "movie_1 = ?";
            $params[] = $movie_1;
        }
        if ($movie_1_thumbnail !== null) {
            $updates[] = "movie_1_thumbnail = ?";
            $params[] = $movie_1_thumbnail;
        }
        if ($movie_2 !== null) {
            $updates[] = "movie_2 = ?";
            $params[] = $movie_2;
        }
        if ($movie_2_thumbnail !== null) {
            $updates[] = "movie_2_thumbnail = ?";
            $params[] = $movie_2_thumbnail;
        }

        if (!empty($updates)) {
            $params[] = $cast_id;
            $params[] = $tenantId;
            $sql = "UPDATE tenant_casts SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ? AND tenant_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }

        // SEOサムネイル更新処理（動画パスが変わった場合などに自動実行）
        try {
            $thumbnailHelper = new VideoThumbnailHelper($pdo);
            // 動画1
            $current_m1 = $movie_1 ?: $current_data['movie_1'];
            if ($current_m1) {
                // ヘルパー内部でSEOサムネイルURLを取得（生成できる場合）
                // DB保存はHelper::saveSeoThumbnail等を呼ぶ必要があるが、ここでは生成のみ試行
                // ※サムネイル生成が重い場合があるため、本来は非同期が望ましい
                $thumbHelperUrl = $thumbnailHelper->getSeoThumbnailUrl(
                    $current_m1,
                    $movie_1_thumbnail ?: $current_data['movie_1_thumbnail'],
                    $cast_id,
                    'movie_1'
                );
                if ($thumbHelperUrl) {
                    // ローカルパスなら保存
                    $p = parse_url($thumbHelperUrl);
                    if (isset($p['path']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $p['path'])) {
                        $thumbnailHelper->saveSeoThumbnail($cast_id, 'movie_1', $p['path']);
                    }
                }
            }
            // 動画2
            $current_m2 = $movie_2 ?: $current_data['movie_2'];
            if ($current_m2) {
                $thumbHelperUrl = $thumbnailHelper->getSeoThumbnailUrl(
                    $current_m2,
                    $movie_2_thumbnail ?: $current_data['movie_2_thumbnail'],
                    $cast_id,
                    'movie_2'
                );
                if ($thumbHelperUrl) {
                    $p = parse_url($thumbHelperUrl);
                    if (isset($p['path']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $p['path'])) {
                        $thumbnailHelper->saveSeoThumbnail($cast_id, 'movie_2', $p['path']);
                    }
                }
            }
        } catch (Exception $e) {
            error_log('SEO Thumbnail Auto Gen Error: ' . $e->getMessage());
        }

        // 成功
        header('Location: index.php?tenant=' . urlencode($tenantSlug) . '&cast_id=' . $cast_id . '&success=1');
        exit;

    } catch (PDOException $e) {
        error_log('movie_management/upload DB error: ' . $e->getMessage());
        $msg = 'データベースエラー: ' . $e->getMessage();
        echo "<script>alert('" . addslashes($msg) . "'); window.history.back();</script>";
        exit;
    } catch (Exception $e) {
        error_log('movie_management/upload error: ' . $e->getMessage());
        $msg = 'エラー: ' . $e->getMessage();

        // PHP設定値のヒントを追加
        if (strpos($e->getMessage(), 'サイズ') !== false || strpos($e->getMessage(), 'アップロードエラー') !== false) {
            $postMax = ini_get('post_max_size');
            $uploadMax = ini_get('upload_max_filesize');
            $msg .= "\\n(現在のサーバー設定: post_max_size=$postMax, upload_max_filesize=$uploadMax)";
        }

        echo "<script>alert('" . addslashes($msg) . "'); window.history.back();</script>";
        exit;
    }
} else {
    // POST以外できた場合
    // post_max_size 超過時は $_POST も $_FILES も空になりここに来る可能性がある
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES) && $_SERVER['CONTENT_LENGTH'] > 0) {
        $postMax = ini_get('post_max_size');
        $msg = "送信されたデータが大きすぎます。サーバー設定(post_max_size=$postMax)を確認してください。";
        echo "<script>alert('" . addslashes($msg) . "'); window.history.back();</script>";
        exit;
    }

    // 通常のGETアクセス
    $tenantSlug = $_GET['tenant'] ?? '';
    header('Location: index.php' . ($tenantSlug ? '?tenant=' . urlencode($tenantSlug) : ''));
    exit;
}
