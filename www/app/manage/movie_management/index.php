<?php
/**
 * 動画管理 - メイン画面 (リファレンス準拠)
 */

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../../includes/VideoThumbnailHelper.php';

// ログイン認証チェック
requireTenantAdminLogin();

// CSP設定（管理画面用）
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' \'unsafe-eval\' https://cdn.jsdelivr.net https://fonts.googleapis.com https://cdnjs.cloudflare.com; style-src \'self\' \'unsafe-inline\' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; font-src \'self\' https://fonts.gstatic.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; img-src \'self\' data: https:; media-src \'self\' data: blob:; connect-src \'self\'; frame-src \'self\' *;');

// 管理画面のキャッシュを完全に無効化
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

try {
    // キャスト一覧を取得
    $sql = "SELECT id, name, img1, movie_1, movie_2 FROM tenant_casts WHERE tenant_id = ? ORDER BY sort_order ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tenantId]);
    $casts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 選択されたキャストの既存データを取得
    $cast_id = isset($_GET['cast_id']) ? (int) $_GET['cast_id'] : null;
    $existing_data = null;

    if ($cast_id) {
        $sql = "SELECT id, name, movie_1, movie_1_thumbnail, movie_2, movie_2_thumbnail, movie_1_seo_thumbnail, movie_2_seo_thumbnail, movie_1_mini, movie_2_mini FROM tenant_casts WHERE id = ? AND tenant_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$cast_id, $tenantId]);
        $existing_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing_data) {
            // 権限なし等は一覧へ
            header('Location: index.php?tenant=' . urlencode($tenantSlug));
            exit;
        }
    }

} catch (PDOException $e) {
    error_log('movie_management/index DB error: ' . $e->getMessage());
    $error = APP_DEBUG ? $e->getMessage() : 'システムエラーが発生しました。';
}

$pageTitle = 'HC 動画管理';
// ヘッダー読み込み (pullcass共通)
require_once __DIR__ . '/../includes/header.php';
?>

<!-- リファレンス準拠のCSS -->
<style>
    /* 既存のadmin.cssと競合しないように調整しつつ移植 */
    /* キャスト一覧表示用のスタイル */

    /* キャスト一覧表示用のスタイル */
    .cast-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .banner-upload-area {
        border: 2px dashed rgba(255, 255, 255, 0.2);
        border-radius: 10px;
        padding: 30px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        margin-bottom: 15px;
        background: rgba(255, 255, 255, 0.02);
    }

    .banner-upload-area:hover {
        border-color: #27a3eb;
        background: rgba(39, 163, 235, 0.1);
    }

    .banner-upload-area i {
        font-size: 48px;
        color: rgba(255, 255, 255, 0.5);
        margin-bottom: 10px;
        transition: color 0.3s ease;
    }

    .banner-upload-area:hover i {
        color: #27a3eb;
    }

    .banner-upload-text {
        color: rgba(255, 255, 255, 0.8);
        font-size: 1rem;
        font-weight: 500;
    }

    .banner-upload-subtext {
        color: rgba(255, 255, 255, 0.5);
        font-size: 0.85rem;
        margin-top: 5px;
    }
    .cast-card {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(20px);
        border-radius: 12px;
        padding: 15px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        text-align: center;
        display: flex;
        flex-direction: column;
        align-items: center;
        cursor: pointer;
        text-decoration: none;
        color: white;
    }

    .cast-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
        border-color: rgba(39, 163, 235, 0.3);
    }

    .cast-image {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        object-fit: cover;
        margin-bottom: 10px;
        border: 2px solid rgba(255, 255, 255, 0.2);
    }

    .cast-initial {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: linear-gradient(45deg, #6b7280, #9ca3af);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 10px;
        border: 2px solid rgba(255, 255, 255, 0.2);
        font-size: 1.2rem;
        font-weight: 600;
        color: white;
    }

    .cast-name {
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 6px;
        color: #ffffff;
    }

    .status-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 15px;
        font-size: 0.75rem;
        font-weight: 500;
        margin-top: 5px;
    }

    .status-registered {
        background: rgba(34, 197, 94, 0.2);
        color: #22c55e;
        border: 1px solid rgba(34, 197, 94, 0.3);
    }

    .status-unregistered {
        background: rgba(239, 68, 68, 0.2);
        color: #ef4444;
        border: 1px solid rgba(239, 68, 68, 0.3);
    }

    .section-header {
        margin: 30px 0 20px 0;
    }

    .section-header h2 {
        color: #ffffff;
        font-size: 1.4rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .section-divider {
        height: 2px;
        background: linear-gradient(45deg, #27a3eb, #1e8bc3);
        border-radius: 1px;
        margin-top: 8px;
    }

    /* 編集画面用のスタイル */
    /* .form-container, .form-group 等は header.php のスタイルを使用するが、
       レイアウト調整のために一部上書きが必要な場合はここに記述 */


    .movie-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
        margin-bottom: 30px;
    }

    @media (max-width: 768px) {
        .movie-grid {
            grid-template-columns: 1fr;
        }
    }

    .movie-column {
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 15px;
        padding: 20px;
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(20px);
        transition: all 0.3s ease;
    }

    .movie-column:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        border-color: rgba(39, 163, 235, 0.3);
    }

    .movie-column h3 {
        margin-top: 0;
        margin-bottom: 15px;
        color: #ffffff;
        text-align: center;
        font-weight: 600;
    }

    .file-input-group {
        margin-bottom: 15px;
    }

    .file-input {
        width: 100%;
        padding: 12px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.1);
        color: #ffffff;
        box-sizing: border-box;
        transition: all 0.3s ease;
    }

    .file-input:focus {
        outline: none;
        border-color: #27a3eb;
        background: rgba(255, 255, 255, 0.15);
    }

    .file-label {
        display: block;
        margin-bottom: 8px;
        color: rgba(255, 255, 255, 0.9);
        text-align: center;
        font-weight: 500;
    }

    .file-name {
        font-size: 0.8em;
        color: rgba(255, 255, 255, 0.7);
        margin-top: 5px;
        display: block;
        text-align: center;
    }

    .preview-container {
        margin-top: 15px;
        min-height: 150px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 10px;
        padding: 10px;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .video-info {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
        margin-bottom: 15px;
    }

    .video-info p {
        color: rgba(255, 255, 255, 0.9);
        font-size: 0.9em;
    }

    /* SEO説明テキスト専用スタイル */
    .seo-text-container {
        font-size: 12px !important;
        color: rgba(255, 255, 255, 0.7) !important;
        text-align: left !important;
        margin: 0 !important;
        padding: 0 !important;
        line-height: 1.1 !important;
    }

    .seo-text-line {
        margin: 0 !important;
        padding: 0 !important;
        line-height: 1.1 !important;
        display: block !important;
    }

    .thumbnail-preview {
        max-width: 100%;
        margin-top: 10px;
        text-align: center;
    }

    .thumbnail-preview img,
    .thumbnail-preview video {
        max-width: 100%;
        height: auto;
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 8px;
    }

    .video-preview-container {
        display: flex;
        gap: 20px;
        margin-top: 10px;
    }

    .video-section {
        flex: 1;
    }

    .thumbnail-section {
        flex: 1;
        display: block;
    }

    .upload-button-container {
        text-align: center;
        margin-top: 30px;
    }



    /* キャスト名ヘッダー */
    .cast-header {
        text-align: center;
        margin-bottom: 30px;
        padding-bottom: 25px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .cast-header-image {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid rgba(39, 163, 235, 0.5);
        margin-bottom: 15px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
    }

    .cast-header-name {
        font-size: 1.6rem;
        font-weight: 700;
        color: #fff;
        margin: 0;
    }

    .cast-header-sub {
        font-size: 0.9rem;
        color: rgba(255, 255, 255, 0.6);
        margin-top: 5px;
    }



    /* 成功メッセージ */
    .success-message {
        background: rgba(34, 197, 94, 0.15);
        color: #4ade80;
        padding: 14px 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        font-size: 0.95rem;
        text-align: center;
        border: 1px solid rgba(34, 197, 94, 0.3);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }
</style>

<?php
require_once __DIR__ . '/../includes/breadcrumb.php';
if ($cast_id && $existing_data) {
    $breadcrumbs = [
        ['label' => 'ダッシュボード', 'url' => '/app/manage/?tenant=' . $tenantSlug, 'icon' => 'fas fa-home'],
        ['label' => '動画管理', 'url' => '/app/manage/movie_management/?tenant=' . $tenantSlug],
        ['label' => htmlspecialchars($existing_data['name']) . ' の動画編集']
    ];
} else {
    $breadcrumbs = [
        ['label' => 'ダッシュボード', 'url' => '/app/manage/?tenant=' . $tenantSlug, 'icon' => 'fas fa-home'],
        ['label' => '動画管理']
    ];
}
renderBreadcrumb($breadcrumbs);
?>

<?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> 動画を更新しました！
    </div>
<?php endif; ?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-video"></i> 動画管理</h1>
        <p>キャスト動画の登録・管理</p>
    </div>
</div>

<?php if (!$cast_id): ?>
    <!-- キャスト一覧表示 -->

    <div class="form-container" style="padding: 20px;">
        <div class="form-group" style="margin-bottom: 0;">
            <label for="castSearch"><i class="fas fa-search"></i> キャスト検索</label>
            <input type="text" id="castSearch" class="form-control" placeholder="キャスト名を入力してフィルタリング...">
        </div>
    </div>

    <?php
    $registered_casts = array_filter($casts, function ($cast) {
        return !empty($cast['movie_1']) || !empty($cast['movie_2']);
    });
    $unregistered_casts = array_filter($casts, function ($cast) {
        return empty($cast['movie_1']) && empty($cast['movie_2']);
    });
    ?>

    <!-- 動画登録済みキャスト -->
    <?php if (!empty($registered_casts)): ?>
        <div class="section-header">
            <h2>
                <i class="fas fa-video"></i> 動画登録済み (<?= count($registered_casts) ?>名)
            </h2>
            <div class="section-divider"></div>
        </div>

        <div class="cast-grid">
            <?php foreach ($registered_casts as $cast):
                $first_letter = mb_substr($cast['name'], 0, 1, 'UTF-8');
                ?>
                <a href="index.php?tenant=<?php echo urlencode($tenantSlug); ?>&cast_id=<?= $cast['id'] ?>" class="cast-card"
                    data-cast-name="<?= htmlspecialchars($cast['name']) ?>">
                    <?php if ($cast['img1']): ?>
                        <img src="<?= htmlspecialchars($cast['img1']) ?>" alt="<?= htmlspecialchars($cast['name']) ?>"
                            class="cast-image">
                    <?php else: ?>
                        <div class="cast-initial"><?= htmlspecialchars($first_letter) ?></div>
                    <?php endif; ?>

                    <div class="cast-name"><?= htmlspecialchars($cast['name']) ?></div>
                    <div class="status-badge status-registered">動画登録済み</div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- 動画未登録キャスト -->
    <?php if (!empty($unregistered_casts)): ?>
        <div class="section-header">
            <h2>
                <i class="fas fa-video-slash"></i> 動画未登録 (<?= count($unregistered_casts) ?>名)
            </h2>
            <div class="section-divider"></div>
        </div>

        <div class="cast-grid">
            <?php foreach ($unregistered_casts as $cast):
                $first_letter = mb_substr($cast['name'], 0, 1, 'UTF-8');
                ?>
                <a href="index.php?tenant=<?php echo urlencode($tenantSlug); ?>&cast_id=<?= $cast['id'] ?>" class="cast-card"
                    data-cast-name="<?= htmlspecialchars($cast['name']) ?>">
                    <?php if ($cast['img1']): ?>
                        <img src="<?= htmlspecialchars($cast['img1']) ?>" alt="<?= htmlspecialchars($cast['name']) ?>"
                            class="cast-image">
                    <?php else: ?>
                        <div class="cast-initial"><?= htmlspecialchars($first_letter) ?></div>
                    <?php endif; ?>

                    <div class="cast-name"><?= htmlspecialchars($cast['name']) ?></div>
                    <div class="status-badge status-unregistered">動画未登録</div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php else: ?>
    <!-- キャスト編集画面 -->

    <div class="form-container">
        <form action="upload.php?tenant=<?php echo urlencode($tenantSlug); ?>" method="post" enctype="multipart/form-data"
            onsubmit="return validateUpload()">
            <input type="hidden" name="cast_id" value="<?php echo $cast_id; ?>">

            <!-- キャストヘッダー -->
            <div class="cast-header">
                <?php if (!empty($existing_data['img1'])): ?>
                    <img src="<?= htmlspecialchars($existing_data['img1']) ?>"
                        alt="<?= htmlspecialchars($existing_data['name']) ?>" class="cast-header-image">
                <?php endif; ?>
                <h2 class="cast-header-name">
                    <i class="fas fa-video" style="color: #27a3eb; margin-right: 10px;"></i>
                    <?= htmlspecialchars($existing_data['name']) ?>
                </h2>
                <p class="cast-header-sub">動画・サムネイルの管理</p>
            </div>

            <div class="registered-section">
                <div class="movie-grid">
                    <!-- 動画1 -->
                    <div class="movie-column">
                        <h3>動画1</h3>

                        <!-- 新規アップロード・更新 -->
                        <!-- 新規アップロード・更新 -->
                        <div class="banner-upload-area" onclick="document.getElementById('movie_1').click()">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <div class="banner-upload-text">クリックして動画を選択</div>
                            <div class="banner-upload-subtext">またはドラッグ＆ドロップ (20MB以下)</div>
                            <div id="movie_1_name" style="margin-top: 10px; color: #27a3eb; font-weight: bold;"></div>
                        </div>
                        <input type="file" name="movie_1" id="movie_1" accept="video/*" style="display: none;"
                            onchange="updateFileName(this, 'movie_1_name'); replaceVideoPreview(this, 1)">

                        <!-- 登録済み動画 -->
                        <div id="video_container_1"
                            style="margin-top: 20px; padding-top: 20px; border-top: 1px solid rgba(255, 255, 255, 0.1); <?php echo (!$existing_data || !$existing_data['movie_1']) ? 'display: none;' : ''; ?>">
                            <div class="video-info">
                                <p id="video_info_1" style="font-size: 24px; font-weight: 600; margin-bottom: 10px;">
                                    サムネイル画像作成</p>
                                <div class="seo-text-container">
                                    <div class="seo-text-line">※ ここで作成するサムネイルはgoogle検索（SEO）用の画像です。</div>
                                    <div class="seo-text-line">※この画像はHPには表示されません。動画がそのまま表示されます。</div>
                                    <div class="seo-text-line">※googleの動画検索でサムネイルとして表示されます。</div>
                                </div>
                            </div>
                            <div id="video_preview_1" class="thumbnail-preview">
                                <?php if ($existing_data && $existing_data['movie_1']): ?>
                                    <div class="video-preview-container">
                                        <div class="video-section">
                                            <video id="video_1_<?php echo $cast_id; ?>"
                                                src="<?php echo htmlspecialchars($existing_data['movie_1']); ?>" controls
                                                style="width: 100%; max-height: 200px;"></video>
                                        </div>
                                        <div class="thumbnail-section" id="thumbnail_display_1">
                                            <?php
                                            $thumb1 = $existing_data['movie_1_thumbnail'];
                                            if (empty($thumb1) && !empty($existing_data['movie_1_seo_thumbnail'])) {
                                                $thumb1 = $existing_data['movie_1_seo_thumbnail'];
                                            }
                                            if ($existing_data && !empty($thumb1)):
                                                ?>
                                                <img src="<?php echo htmlspecialchars($thumb1); ?>" alt="サムネイル1"
                                                    style="width: 100%; max-height: 200px; object-fit: cover; border-radius: 8px;">
                                            <?php else: ?>
                                                <div
                                                    style="width: 100%; max-height: 200px; display: flex; align-items: center; justify-content: center; background: rgba(0, 0, 0, 0.2); border-radius: 8px; color: rgba(255, 255, 255, 0.6); font-size: 12px; aspect-ratio: 16/9;">
                                                    サムネイル未作成
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- サムネイル画像作成機能 -->
                                    <div
                                        style="margin-top: 15px; padding: 20px; background: rgba(39, 163, 235, 0.05); border-radius: 12px; border: 1px solid rgba(39, 163, 235, 0.2);">
                                        <p
                                            style="text-align: center; color: rgba(255, 255, 255, 0.8); font-size: 13px; margin-bottom: 15px;">
                                            💡 スライダーを動かして好きなフレームを選択してください
                                        </p>
                                        <input type="range" id="thumbnail_slider_1_<?php echo $cast_id; ?>" min="0" max="100"
                                            value="5" step="0.1"
                                            style="width: 100%; margin: 10px 0; height: 8px; border-radius: 5px; background: rgba(255, 255, 255, 0.2); outline: none; cursor: pointer;"
                                            oninput="updateThumbnailTimeDisplay(1, <?php echo $cast_id; ?>)">
                                        <div id="thumbnail_time_display_1_<?php echo $cast_id; ?>"
                                            style="text-align: center; color: #27a3eb; font-weight: bold; font-size: 16px; margin: 10px 0;">
                                            0:05</div>
                                        <div style="display: flex; gap: 10px; margin-top: 15px; justify-content: center;">
                                            <button type="button"
                                                onclick="generateThumbnailFromVideo(1, <?php echo $cast_id; ?>)"
                                                class="edit-title-btn">
                                                <i class="fas fa-image"></i> このフレームをサムネイルに設定</button>
                                            <button type="button" onclick="clearVideo(1)" class="delete-section-btn">
                                                <i class="fas fa-trash"></i> 動画削除</button>
                                        </div>
                                        <div id="thumbnail_status_1_<?php echo $cast_id; ?>"
                                            style="margin-top: 15px; text-align: center; font-size: 13px;"></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- 動画2 -->
                    <div class="movie-column">
                        <h3>動画2</h3>

                        <!-- 新規アップロード・更新 -->
                        <div class="banner-upload-area" onclick="document.getElementById('movie_2').click()">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <div class="banner-upload-text">クリックして動画を選択</div>
                            <div class="banner-upload-subtext">またはドラッグ＆ドロップ (20MB以下)</div>
                            <div id="movie_2_name" style="margin-top: 10px; color: #27a3eb; font-weight: bold;"></div>
                        </div>
                        <input type="file" name="movie_2" id="movie_2" accept="video/*" style="display: none;"
                            onchange="updateFileName(this, 'movie_2_name'); replaceVideoPreview(this, 2)">

                        <div id="video_container_2"
                            style="margin-top: 20px; padding-top: 20px; border-top: 1px solid rgba(255, 255, 255, 0.1); <?php echo (!$existing_data || !$existing_data['movie_2']) ? 'display: none;' : ''; ?>">
                            <div class="video-info">
                                <p id="video_info_2" style="font-size: 24px; font-weight: 600; margin-bottom: 10px;">
                                    サムネイル画像作成</p>
                                <div class="seo-text-container">
                                    <div class="seo-text-line">※ ここで作成するサムネイルはgoogle検索（SEO）用の画像です。</div>
                                    <div class="seo-text-line">※この画像はHPには表示されません。動画がそのまま表示されます。</div>
                                    <div class="seo-text-line">※googleの動画検索でサムネイルとして表示されます。</div>
                                </div>
                            </div>
                            <div id="video_preview_2" class="thumbnail-preview">
                                <?php if ($existing_data && $existing_data['movie_2']): ?>
                                    <div class="video-preview-container">
                                        <div class="video-section">
                                            <video id="video_2_<?php echo $cast_id; ?>"
                                                src="<?php echo htmlspecialchars($existing_data['movie_2']); ?>" controls
                                                style="width: 100%; max-height: 200px;"></video>
                                        </div>
                                        <div class="thumbnail-section" id="thumbnail_display_2">
                                            <?php
                                            $thumb2 = $existing_data['movie_2_thumbnail'];
                                            if (empty($thumb2) && !empty($existing_data['movie_2_seo_thumbnail'])) {
                                                $thumb2 = $existing_data['movie_2_seo_thumbnail'];
                                            }
                                            if ($existing_data && !empty($thumb2)):
                                                ?>
                                                <img src="<?php echo htmlspecialchars($thumb2); ?>" alt="サムネイル2"
                                                    style="width: 100%; max-height: 200px; object-fit: cover; border-radius: 8px;">
                                            <?php else: ?>
                                                <div
                                                    style="width: 100%; max-height: 200px; display: flex; align-items: center; justify-content: center; background: rgba(0, 0, 0, 0.2); border-radius: 8px; color: rgba(255, 255, 255, 0.6); font-size: 12px; aspect-ratio: 16/9;">
                                                    サムネイル未作成
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div
                                        style="margin-top: 15px; padding: 20px; background: rgba(39, 163, 235, 0.05); border-radius: 12px; border: 1px solid rgba(39, 163, 235, 0.2);">
                                        <p
                                            style="text-align: center; color: rgba(255, 255, 255, 0.8); font-size: 13px; margin-bottom: 15px;">
                                            💡 スライダーを動かして好きなフレームを選択してください
                                        </p>
                                        <input type="range" id="thumbnail_slider_2_<?php echo $cast_id; ?>" min="0" max="100"
                                            value="5" step="0.1"
                                            style="width: 100%; margin: 10px 0; height: 8px; border-radius: 5px; background: rgba(255, 255, 255, 0.2); outline: none; cursor: pointer;"
                                            oninput="updateThumbnailTimeDisplay(2, <?php echo $cast_id; ?>)">
                                        <div id="thumbnail_time_display_2_<?php echo $cast_id; ?>"
                                            style="text-align: center; color: #27a3eb; font-weight: bold; font-size: 16px; margin: 10px 0;">
                                            0:05</div>
                                        <div style="display: flex; gap: 10px; margin-top: 15px; justify-content: center;">
                                            <button type="button"
                                                onclick="generateThumbnailFromVideo(2, <?php echo $cast_id; ?>)"
                                                class="edit-title-btn">
                                                <i class="fas fa-image"></i> このフレームをサムネイルに設定</button>
                                            <button type="button" onclick="clearVideo(2)" class="delete-section-btn">
                                                <i class="fas fa-trash"></i> 動画削除</button>
                                        </div>
                                        <div id="thumbnail_status_2_<?php echo $cast_id; ?>"
                                            style="margin-top: 15px; text-align: center; font-size: 13px;"></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- アップロードボタン -->
                <div style="margin-top: 30px; text-align: center;">
                    <button type="submit" class="btn btn-primary" style="padding: 15px 40px; font-size: 1rem;">
                        <i class="fas fa-upload"></i> 動画を更新
                    </button>
                </div>
            </div>
        </form>
    </div>
<?php endif; ?>
</div>

<script>
    // 検索ロジック
    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('castSearch');
        if (searchInput) {
            searchInput.addEventListener('input', function (e) {
                const term = e.target.value.toLowerCase();
                const cards = document.querySelectorAll('.cast-card');

                cards.forEach(card => {
                    const name = card.dataset.castName.toLowerCase();
                    if (name.includes(term)) {
                        card.style.display = 'flex';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        }

        // スライダー位置初期化など
        const videos = document.querySelectorAll('video');
        videos.forEach(video => {
            video.addEventListener('loadedmetadata', function () {
                // 必要あれば初期化
            });
        });
    });

    // ファイル選択時のプレビュー
    function replaceVideoPreview(input, videoNum) {
        if (input.files && input.files[0]) {
            const file = input.files[0];

            // ファイルサイズチェック（20MB制限）
            const maxSize = 20 * 1024 * 1024; // 20MB
            if (file.size > maxSize) {
                alert('ファイルサイズを20MB以下にして下さい。');
                input.value = '';
                // ファイル名クリア
                const fileNameElem = document.getElementById('movie_' + videoNum + '_name');
                if (fileNameElem) fileNameElem.textContent = '';
                return;
            }

            // ファイル名表示
            const fileNameElem = document.getElementById('movie_' + videoNum + '_name');
            if (fileNameElem) fileNameElem.textContent = file.name;

            const reader = new FileReader();
            reader.onload = function (e) {
                // 動画コンテナを表示
                const container = document.getElementById('video_container_' + videoNum);
                container.style.display = 'block';

                // 既存のプレビューエリアをクリアして再構築
                const previewArea = document.getElementById('video_preview_' + videoNum);
                previewArea.innerHTML = '';

                // 動画要素
                const video = document.createElement('video');
                video.src = e.target.result;
                video.controls = true;
                video.style.width = '100%';
                video.style.maxHeight = '200px';
                video.id = 'video_' + videoNum + '_<?php echo $cast_id ?: "new"; ?>';

                // レイアウト構築
                const previewContainer = document.createElement('div');
                previewContainer.className = 'video-preview-container';

                const videoSection = document.createElement('div');
                videoSection.className = 'video-section';
                videoSection.appendChild(video);

                const thumbSection = document.createElement('div');
                thumbSection.className = 'thumbnail-section';
                thumbSection.id = 'thumbnail_display_' + videoNum;
                thumbSection.innerHTML = '<div style="width: 100%; max-height: 200px; display: flex; align-items: center; justify-content: center; background: rgba(0, 0, 0, 0.2); border-radius: 8px; color: rgba(255, 255, 255, 0.6); font-size: 12px; aspect-ratio: 16/9;">サムネイル未作成</div>';

                previewContainer.appendChild(videoSection);
                previewContainer.appendChild(thumbSection);

                previewArea.appendChild(previewContainer);

                // サムネイル生成UI
                const tools = document.createElement('div');
                tools.style.marginTop = '15px';
                tools.style.padding = '20px';
                tools.style.background = 'rgba(39, 163, 235, 0.05)';
                tools.style.borderRadius = '12px';
                tools.style.border = '1px solid rgba(39, 163, 235, 0.2)';

                const castId = <?php echo $cast_id ?: 'null'; ?>;

                tools.innerHTML = `
                <p style="text-align: center; color: rgba(255, 255, 255, 0.8); font-size: 13px; margin-bottom: 15px;">💡 スライダーを動かして好きなフレームを選択してください</p>
                <input type="range" id="thumbnail_slider_${videoNum}_${castId}" min="0" max="100" value="5" step="0.1" style="width: 100%; margin: 10px 0; height: 8px; border-radius: 5px; background: rgba(255, 255, 255, 0.2); outline: none; cursor: pointer;" oninput="updateThumbnailTimeDisplay(${videoNum}, ${castId})">
                <div id="thumbnail_time_display_${videoNum}_${castId}" style="text-align: center; color: #27a3eb; font-weight: bold; font-size: 16px; margin: 10px 0;">0:05</div>
                <div style="display: flex; gap: 10px; margin-top: 15px; justify-content: center;">
                    <button type="button" onclick="generateThumbnailFromVideo(${videoNum}, ${castId})" style="padding: 10px 30px; background: #27a3eb; color: white; border: none; border-radius: 25px; cursor: pointer; font-weight: 600; font-size: 14px; transition: all 0.3s ease; box-shadow: 0 4px 10px rgba(39, 163, 235, 0.3);">このフレームをサムネイルに設定</button>
                    <button type="button" onclick="clearVideo(${videoNum})" style="padding: 10px 20px; background: #f44336; color: white; border: none; border-radius: 25px; cursor: pointer; font-size: 13px; transition: all 0.3s ease;">動画削除</button>
                </div>
                <div id="thumbnail_status_${videoNum}_${castId}" style="margin-top: 15px; text-align: center; font-size: 13px;"></div>
            `;

                previewArea.appendChild(tools);
            };
            reader.readAsDataURL(file);
        }
    }

    function updateThumbnailTimeDisplay(videoNum, castId) {
        const slider = document.getElementById(`thumbnail_slider_${videoNum}_${castId}`);
        const display = document.getElementById(`thumbnail_time_display_${videoNum}_${castId}`);

        // 動画要素取得 (IDは色々試す)
        let video = document.getElementById(`video_${videoNum}_${castId}`);

        // 新規アップロードの場合など、IDが変わる可能性
        if (!video) {
            // castIdがnullの場合はプレースホルダーを見る
            video = document.querySelector(`#video_preview_${videoNum} video`);
        }

        if (slider && display && video) {
            const duration = video.duration || 100;
            const currentTime = (parseFloat(slider.value) / 100) * duration;

            const minutes = Math.floor(currentTime / 60);
            const seconds = Math.floor(currentTime % 60);
            display.textContent = minutes + ':' + (seconds < 10 ? '0' : '') + seconds;

            if (isFinite(currentTime)) {
                video.currentTime = currentTime;
            }
        }
    }

    async function generateThumbnailFromVideo(videoNum, castId) {
        const statusDiv = document.getElementById(`thumbnail_status_${videoNum}_${castId}`);
        // 動画要素
        let video = document.getElementById(`video_${videoNum}_${castId}`);
        if (!video) video = document.querySelector(`#video_preview_${videoNum} video`);

        if (!statusDiv || !video) return;

        statusDiv.innerHTML = '<span style="color: yellow;">⏳ 処理中...</span>';

        try {
            const canvas = document.createElement('canvas');
            canvas.width = 640;
            canvas.height = 360;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

            const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', 0.8));

            const formData = new FormData();
            formData.append('thumbnail', blob, 'thumbnail.jpg');
            formData.append('cast_id', castId);
            formData.append('video_type', 'movie_' + videoNum + '_thumbnail');

            // 修正したAPIへ送信
            const response = await fetch('api_save_thumbnail.php?tenant=<?php echo urlencode($tenantSlug); ?>', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                statusDiv.innerHTML = '<span style="color: #4caf50;">✅ 作成完了！</span>';
                const thumbDisplay = document.getElementById('thumbnail_display_' + videoNum);
                if (thumbDisplay) {
                    thumbDisplay.innerHTML = `<img src="${result.thumbnail_url}?t=${Date.now()}" alt="サムネイル" style="width: 100%; max-height: 200px; object-fit: cover; border-radius: 8px;">`;
                }
            } else {
                statusDiv.innerHTML = '<span style="color: red;">❌ ' + result.message + '</span>';
            }
        } catch (e) {
            console.error(e);
            statusDiv.innerHTML = '<span style="color: red;">❌ エラーが発生しました</span>';
        }
    }

    function clearVideo(videoNum) {
        if (!confirm('この動画を削除対象にしますか？\n（更新ボタンを押すまで確定しません）')) return;

        // コンテナ非表示
        const container = document.getElementById('video_container_' + videoNum);
        if (container) container.style.display = 'none';

        // inputクリア
        const input = document.getElementById('movie_' + videoNum);
        if (input) input.value = '';

        const form = document.querySelector('form');
        // フラグ追加
        const existing = form.querySelector(`input[name="clear_movie_${videoNum}"]`);
        if (existing) existing.remove();

        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'clear_movie_' + videoNum;
        hidden.value = '1';
        form.appendChild(hidden);
    }

    // ファイル名表示
    function updateFileName(input, targetId) {
        const target = document.getElementById(targetId);
        if (input.files && input.files.length > 0) {
            target.textContent = input.files[0].name;
            target.style.display = 'block';
        } else {
            target.textContent = '';
        }
    }

    // 動画プレビュー置き換え
    function replaceVideoPreview(input, videoNum) {
        const container = document.getElementById('video_container_' + videoNum);
        
        if (input.files && input.files[0]) {
            const file = input.files[0];
            const fileURL = URL.createObjectURL(file);
            
            // コンテナを表示
            container.style.display = 'block';
            
            // 既存のプレビューを探す
            let previewArea = document.getElementById('video_preview_' + videoNum);
            
            // 無ければ作成（既存のPHP出力構造に合わせて調整）
            // ※既存構造が複雑なため、ここではシンプルにvideoタグを書き換える
            
            // 既存のvideoタグを探す
            let video = container.querySelector('video');
            
            if (!video) {
                // video要素が無い場合（新規の場合など）、video_info_X の後ろに挿入したい
                // 現在のDOM構造: #video_info_X -> .seo-text-container -> video wrapper
                
                // 既存の構造を維持しつつ、video要素を更新または作成するのが安全
                // ここでは簡易的に、video_info_X のラベルを変更し、プレビューエリアをクリアして再生成
                document.getElementById('video_info_' + videoNum).textContent = 'プレビュー: ' + file.name;
            }

            // 新しいVideo要素を作成して既存のと置き換え、または既存のを更新
            // ただしキャストIDなどが必要なため、既存の構造を取得してsrcだけ変える
            if (video) {
                video.src = fileURL;
                video.load();
            } else {
                // video要素が見つからない場合、動的に追加が必要だが
                // 既存PHPコードとの兼ね合いで複雑になるため、ここではリロードを促すか、
                // または video_container 内の特定の場所に video タグを挿入する
                
                // 既存の #video_preview_X があればそこへ
                if (previewArea) {
                    previewArea.innerHTML = `
                        <video id="video_${videoNum}_NEW" src="${fileURL}" controls style="width: 100%; border-radius: 8px;" preload="metadata"></video>
                    `;
                } else {
                    // 何も無い場合は、video_info_X の後にdivを作って入れる
                    const info = document.getElementById('video_info_' + videoNum);
                    const wrapper = document.createElement('div');
                    wrapper.id = 'video_preview_' + videoNum;
                    wrapper.style.marginTop = '15px';
                    wrapper.innerHTML = `
                        <video id="video_${videoNum}_NEW" src="${fileURL}" controls style="width: 100%; border-radius: 8px;" preload="metadata"></video>
                    `;
                    // seo-text-containerの後ろあたりに追加したい
                    const seo = container.querySelector('.seo-text-container');
                    if(seo) {
                        seo.parentNode.insertBefore(wrapper, seo.nextSibling);
                    } else {
                        container.appendChild(wrapper);
                    }
                }
            }
        }
    }

    // 送信前バリデーション
    function validateUpload() {
        const movie1 = document.getElementById('movie_1');
        const movie2 = document.getElementById('movie_2');
        const maxSize = 20 * 1024 * 1024; // 20MB

        if (movie1 && movie1.files[0] && movie1.files[0].size > maxSize) {
            alert('動画1のサイズが大きすぎます(20MB以下にしてください)');
            return false;
        }

        if (movie2 && movie2.files[0] && movie2.files[0].size > maxSize) {
            alert('動画2のサイズが大きすぎます(20MB以下にしてください)');
            return false;
        }

        const btn = document.querySelector('.btn-primary');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 更新中...';
        }

        return true;
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>