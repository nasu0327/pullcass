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
        // ドキュメントルート基準のパス
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
            // 動画1と関連ファイルを削除
            deleteExistingFile($current_data, 'movie_1');
            deleteExistingFile($current_data, 'movie_1_thumbnail');
            deleteExistingFile($current_data, 'movie_1_mini');
            deleteExistingFile($current_data, 'movie_1_seo_thumbnail');

            // DBから削除
            $sql = "UPDATE tenant_casts SET movie_1 = NULL, movie_1_thumbnail = NULL, movie_1_mini = NULL, movie_1_seo_thumbnail = NULL WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$cast_id]);

            // データ更新
            $current_data['movie_1'] = null;
            $current_data['movie_1_thumbnail'] = null;
        }

        if (isset($_POST['clear_movie_2']) && $_POST['clear_movie_2'] == '1') {
            // 動画2と関連ファイルを削除
            deleteExistingFile($current_data, 'movie_2');
            deleteExistingFile($current_data, 'movie_2_thumbnail');
            deleteExistingFile($current_data, 'movie_2_mini');
            deleteExistingFile($current_data, 'movie_2_seo_thumbnail');

            // DBから削除
            $sql = "UPDATE tenant_casts SET movie_2 = NULL, movie_2_thumbnail = NULL, movie_2_mini = NULL, movie_2_seo_thumbnail = NULL WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$cast_id]);

            // データ更新
            $current_data['movie_2'] = null;
            $current_data['movie_2_thumbnail'] = null;
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

                // 拡張子チェック（簡易）
                $allowed_video = ['mp4', 'webm', 'ogg', 'mov', 'm4v'];
                $allowed_image = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $is_video = strpos($prefix, 'movie') !== false;

                if ($is_video && !in_array($ext, $allowed_video)) {
                    throw new Exception("許可されていない動画形式です: $ext");
                }
                if (!$is_video && !in_array($ext, $allowed_image)) {
                    throw new Exception("許可されていない画像形式です: $ext");
                }

                $new_name = $prefix . '_' . time() . '.' . $ext;
                $destination = $upload_dir . $new_name;

                if (move_uploaded_file($tmp_name, $destination)) {
                    return $relative_upload_dir . $new_name;
                }
            }
            return null;
        }

        // ファイルサイズチェック（合計で制限を超えないように注意が必要だが、ここでは個別にチェック）
        // php.iniの設定値も考慮する必要があるが、アプリケーション側での制限
        $maxSize = 100 * 1024 * 1024; // 100MB (リファレンスは20MBだったが少し緩和)

        if (isset($_FILES['movie_1']) && $_FILES['movie_1']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['movie_1']['error'] !== UPLOAD_ERR_OK) {
                if ($_FILES['movie_1']['error'] === UPLOAD_ERR_INI_SIZE) {
                    throw new Exception('動画1のサイズがサーバーの制限を超えています。');
                }
                throw new Exception('動画1のアップロードエラー: ' . $_FILES['movie_1']['error']);
            }
            if ($_FILES['movie_1']['size'] > $maxSize) {
                throw new Exception('動画1のファイルサイズが大きすぎます。');
            }
        }

        if (isset($_FILES['movie_2']) && $_FILES['movie_2']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['movie_2']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('動画2のアップロードエラー');
            }
            if ($_FILES['movie_2']['size'] > $maxSize) {
                throw new Exception('動画2のファイルサイズが大きすぎます。');
            }
        }

        // 各ファイルのアップロード処理
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

        // データベースの更新
        // COALESCE等は使わず、アップロードされた場合のみ更新するロジックにする（NULLで上書きされるのを防ぐため）
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
            $params[] = $tenantId; // 安全のため再度tenant_idチェック
            $sql = "UPDATE tenant_casts SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ? AND tenant_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }

        // 自動処理（動画がアップロードされた場合、または動画が存在する場合）
        try {
            $thumbnailHelper = new VideoThumbnailHelper($pdo);

            // 動画1のSEOサムネイル処理
            $target_movie_1 = $movie_1 ?: $current_data['movie_1'];
            if ($target_movie_1) {
                // サムネイルとIDを渡してSEOサムネイルのURLを取得（生成されればDB保存等の処理はHelper内で行われる...わけではなく、URLを返すだけ）
                // リファレンスではここでログを出しているだけだが、実際は generateThumbnailFromVideo 内でファイル生成は行われる
                // DB保存は別途必要？ VideoThumbnailHelper::getSeoThumbnailUrl はURLを返すだけ

                // 今回実装した saveSeoThumbnail を使用して保存する
                $seoThumbUrl = $thumbnailHelper->getSeoThumbnailUrl(
                    $target_movie_1,
                    $movie_1_thumbnail ?: $current_data['movie_1_thumbnail'],
                    $cast_id,
                    'movie_1'
                );

                // 相対パスを取得してDB保存
                // getSeoThumbnailUrl は絶対URLを返すことがあるため、パス部分を抽出
                $parsedUrl = parse_url($seoThumbUrl);
                $seoThumbPath = $parsedUrl['path'] ?? '';

                if ($seoThumbPath) {
                    $thumbnailHelper->saveSeoThumbnail($cast_id, 'movie_1', $seoThumbPath);
                }
            }

            // 動画2のSEOサムネイル処理
            $target_movie_2 = $movie_2 ?: $current_data['movie_2'];
            if ($target_movie_2) {
                $seoThumbUrl = $thumbnailHelper->getSeoThumbnailUrl(
                    $target_movie_2,
                    $movie_2_thumbnail ?: $current_data['movie_2_thumbnail'],
                    $cast_id,
                    'movie_2'
                );

                $parsedUrl = parse_url($seoThumbUrl);
                $seoThumbPath = $parsedUrl['path'] ?? '';

                if ($seoThumbPath) {
                    $thumbnailHelper->saveSeoThumbnail($cast_id, 'movie_2', $seoThumbPath);
                }
            }

        } catch (Exception $e) {
            error_log('Auto processing error: ' . $e->getMessage());
        }

        // 成功メッセージを表示して同じキャストの画面に戻る
        header('Location: index.php?tenant=' . urlencode($tenantSlug) . '&cast_id=' . $cast_id . '&success=1');
        exit;

    } catch (PDOException $e) {
        error_log('movie_management/upload DB error: ' . $e->getMessage());
        // エラー時は前のページに戻す（可能なら）
        $redirectUrl = 'index.php?tenant=' . urlencode($tenantSlug);
        if (isset($_POST['cast_id'])) {
            $redirectUrl .= '&cast_id=' . $_POST['cast_id'];
        }
        // エラーパラメータ付きでリダイレクトすべきだが、簡易的にアラート用スクリプトを出力して終了
        echo "<script>alert('システムエラーが発生しました。'); window.history.back();</script>";
        exit;
    } catch (Exception $e) {
        error_log('movie_management/upload error: ' . $e->getMessage());
        echo "<script>alert('エラー: " . addslashes($e->getMessage()) . "'); window.history.back();</script>";
        exit;
    }
} else {
    // GETアクセスは許可しない
    header('Location: index.php?tenant=' . urlencode($tenantSlug));
    exit;
}
