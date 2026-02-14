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
            header('Location: index?tenant=' . urlencode($tenantSlug));
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
    /* 既存のCSSと競合しないように調整しつつ移植 */
    /* スライダーのアクセント色 */
    input[type="range"] {
        accent-color: var(--primary);
    }

    /* キャスト一覧表示用のスタイル */
    .cast-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .banner-upload-area {
        border: 2px dashed var(--border-color);
        border-radius: 10px;
        padding: 30px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        margin-bottom: 15px;
        background: var(--bg-body);
    }

    .banner-upload-area:hover,
    .banner-upload-area.dragover {
        border-color: var(--primary);
        background: var(--primary-bg);
    }

    .banner-upload-area.dragover {
        border-style: solid;
        transform: scale(1.02);
    }

    .banner-upload-area.dragover i {
        color: var(--primary);
    }

    .banner-upload-area i {
        font-size: 48px;
        color: var(--text-muted);
        margin-bottom: 10px;
        transition: color 0.3s ease;
    }

    .banner-upload-area:hover i {
        color: var(--primary);
    }

    .banner-upload-text {
        color: var(--text-secondary);
        font-size: 1rem;
        font-weight: 500;
    }

    .banner-upload-subtext {
        color: var(--text-muted);
        font-size: 0.85rem;
        margin-top: 5px;
    }
    .cast-card {
        background: var(--bg-card);
        border-radius: 12px;
        padding: 15px;
        border: none;
        box-shadow: var(--shadow-card);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        text-align: center;
        display: flex;
        flex-direction: column;
        align-items: center;
        cursor: pointer;
        text-decoration: none;
        color: var(--text-primary);
    }

    .cast-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-card-hover);
        border-color: var(--primary-border);
    }

    .cast-image {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        object-fit: cover;
        margin-bottom: 10px;
        border: 2px solid var(--border-color);
    }

    .cast-initial {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: var(--text-muted);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 10px;
        border: 2px solid var(--border-color);
        font-size: 1.2rem;
        font-weight: 600;
        color: var(--text-inverse);
    }

    .cast-name {
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 6px;
        color: var(--text-primary);
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
        background: var(--success-bg);
        color: var(--success);
        border: 1px solid var(--success-border);
    }

    .status-unregistered {
        background: var(--danger-bg);
        color: var(--danger);
        border: 1px solid var(--danger-border);
    }

    .section-header {
        margin: 30px 0 20px 0;
    }

    .section-header h2 {
        color: var(--text-primary);
        font-size: 1.4rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .section-divider {
        height: 2px;
        background: var(--primary-gradient);
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
        border: none;
        box-shadow: var(--shadow-card);
        border-radius: 15px;
        padding: 20px;
        background: var(--bg-card);
        transition: all 0.3s ease;
    }

    .movie-column:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-card-hover);
    }

    .movie-column h3 {
        margin-top: 0;
        margin-bottom: 15px;
        color: var(--text-primary);
        text-align: center;
        font-weight: 600;
    }

    .file-input-group {
        margin-bottom: 15px;
    }

    .file-input {
        width: 100%;
        padding: 12px;
        border: 1px solid var(--border-color);
        border-radius: 10px;
        background: var(--bg-body);
        color: var(--text-primary);
        box-sizing: border-box;
        transition: all 0.3s ease;
    }

    .file-input:focus {
        outline: none;
        border-color: var(--primary);
        background: var(--bg-card);
    }

    .file-label {
        display: block;
        margin-bottom: 8px;
        color: var(--text-secondary);
        text-align: center;
        font-weight: 500;
    }

    .file-name {
        font-size: 0.8em;
        color: var(--text-muted);
        margin-top: 5px;
        display: block;
        text-align: center;
    }

    .preview-container {
        margin-top: 15px;
        min-height: 150px;
        background: var(--bg-body);
        border-radius: 10px;
        padding: 10px;
        border: 1px solid var(--border-color);
    }

    .video-info {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
        margin-bottom: 15px;
    }

    .video-info p {
        color: var(--text-secondary);
        font-size: 0.9em;
    }

    /* SEO説明テキスト専用スタイル */
    .seo-text-container {
        font-size: 12px !important;
        color: var(--text-muted) !important;
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
        border: 1px solid var(--border-color);
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
        border-bottom: 1px solid var(--border-color);
    }

    .cast-header-image {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid var(--primary-border);
        margin-bottom: 15px;
        box-shadow: var(--shadow-card);
    }

    .cast-header-name {
        font-size: 1.6rem;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0;
    }

    .cast-header-sub {
        font-size: 0.9rem;
        color: var(--text-muted);
        margin-top: 5px;
    }



    /* 成功メッセージ */
    .success-message {
        background: var(--success-bg);
        color: var(--success);
        padding: 14px 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        font-size: 0.95rem;
        text-align: center;
        border: 1px solid var(--success-border);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }

    /* サムネイル設定・動画削除ボタン（横並び・等幅） */
    .movie-thumb-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 15px;
        justify-content: center;
    }
    .movie-thumb-actions .btn-icon {
        justify-content: center;
    }

    /* アップロードカード下のアクションバー */
    .movie-action-bar {
        display: flex;
        justify-content: center;
        gap: 10px;
        margin-top: 10px;
        min-height: 36px;
    }

    /* btn-iconスタイル（price_manage統一） */
    .movie-column .btn-icon {
        padding: 8px 16px;
        border-radius: 20px;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        font-size: 13px;
        background: var(--primary-gradient);
        color: var(--text-inverse);
    }

    .movie-column .btn-icon:hover {
        background: var(--primary-gradient-hover);
        transform: translateY(-2px);
        box-shadow: var(--shadow-primary);
    }

    .movie-column .btn-icon.btn-icon-danger {
        background: var(--danger-bg);
        border: 2px solid var(--danger-border);
        color: var(--danger);
    }

    .movie-column .btn-icon.btn-icon-danger:hover {
        background: var(--danger-bg);
        border-color: var(--danger);
        transform: translateY(-2px);
    }

    /* アップロードエリア内のサムネイルプレビュー */
    .upload-preview-thumb {
        margin-top: 10px;
    }

    .upload-preview-thumb canvas {
        max-width: 100%;
        max-height: 120px;
        border-radius: 8px;
        border: 1px solid var(--border-color);
    }

    .banner-upload-area.has-preview .upload-icon,
    .banner-upload-area.has-preview .banner-upload-text,
    .banner-upload-area.has-preview .banner-upload-subtext {
        display: none;
    }

    .banner-upload-area.has-preview {
        padding: 15px;
    }
</style>

<?php
require_once __DIR__ . '/../includes/breadcrumb.php';
if ($cast_id && $existing_data) {
    $breadcrumbs = [
        ['label' => 'ダッシュボード', 'url' => '/app/manage/?tenant=' . $tenantSlug, 'icon' => 'fas fa-chart-pie'],
        ['label' => '動画管理', 'url' => '/app/manage/movie_management/?tenant=' . $tenantSlug],
        ['label' => htmlspecialchars($existing_data['name']) . ' の動画編集']
    ];
} else {
    $breadcrumbs = [
        ['label' => 'ダッシュボード', 'url' => '/app/manage/?tenant=' . $tenantSlug, 'icon' => 'fas fa-chart-pie'],
        ['label' => '動画管理']
    ];
}
renderBreadcrumb($breadcrumbs);
?>

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
                <a href="index?tenant=<?php echo urlencode($tenantSlug); ?>&cast_id=<?= $cast['id'] ?>" class="cast-card"
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
                <a href="index?tenant=<?php echo urlencode($tenantSlug); ?>&cast_id=<?= $cast['id'] ?>" class="cast-card"
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
        <form action="upload?tenant=<?php echo urlencode($tenantSlug); ?>" method="post" enctype="multipart/form-data"
            onsubmit="return validateUpload()">
            <input type="hidden" name="cast_id" value="<?php echo $cast_id; ?>">

            <!-- キャストヘッダー -->
            <div class="cast-header">
                <?php if (!empty($existing_data['img1'])): ?>
                    <img src="<?= htmlspecialchars($existing_data['img1']) ?>"
                        alt="<?= htmlspecialchars($existing_data['name']) ?>" class="cast-header-image">
                <?php endif; ?>
                <h2 class="cast-header-name">
                    <i class="fas fa-video" style="color: var(--primary); margin-right: 10px;"></i>
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
                        <div class="banner-upload-area" onclick="document.getElementById('movie_1').click()">
                            <i class="fas fa-cloud-upload-alt upload-icon"></i>
                            <div class="banner-upload-text">クリックして動画を選択</div>
                            <div class="banner-upload-subtext">またはドラッグ＆ドロップ (20MB以下)</div>
                            <div class="upload-preview-thumb" id="upload_thumb_1" style="display: none;">
                                <canvas id="upload_canvas_1"></canvas>
                            </div>
                        </div>
                        <input type="file" name="movie_1" id="movie_1" accept="video/*" style="display: none;"
                            onchange="handleVideoSelect(this, 1)">
                        <div class="movie-action-bar">
                            <?php if ($existing_data && $existing_data['movie_1']): ?>
                            <button type="button" onclick="clearVideo(1)" class="btn-icon btn-icon-danger" data-tooltip="動画を削除">
                                <i class="fas fa-trash"></i>
                            </button>
                            <?php endif; ?>
                        </div>

                        <!-- 登録済み動画 -->
                        <div id="video_container_1"
                            style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border-color); <?php echo (!$existing_data || !$existing_data['movie_1']) ? 'display: none;' : ''; ?>">
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
                                                    style="width: 100%; max-height: 200px; display: flex; align-items: center; justify-content: center; background: var(--bg-body); border-radius: 8px; color: var(--text-muted); font-size: 12px; aspect-ratio: 16/9;">
                                                    サムネイル未作成
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- サムネイル画像作成機能 -->
                                    <div
                                        style="margin-top: 15px; padding: 20px; background: var(--primary-bg); border-radius: 12px; border: 1px solid var(--primary-border);">
                                        <p
                                            style="text-align: center; color: var(--text-secondary); font-size: 13px; margin-bottom: 15px;">
                                            💡 スライダーを動かして好きなフレームを選択してください
                                        </p>
                                        <input type="range" id="thumbnail_slider_1_<?php echo $cast_id; ?>" min="0" max="100"
                                            value="5" step="0.1"
                                            style="width: 100%; margin: 10px 0; height: 8px; border-radius: 5px; background: var(--border-color); outline: none; cursor: pointer;"
                                            oninput="updateThumbnailTimeDisplay(1, <?php echo $cast_id; ?>)">
                                        <div id="thumbnail_time_display_1_<?php echo $cast_id; ?>"
                                            style="text-align: center; color: var(--primary); font-weight: bold; font-size: 16px; margin: 10px 0;">
                                            0:05</div>
                                        <div class="movie-thumb-actions">
                                            <button type="button"
                                                onclick="generateThumbnailFromVideo(1, <?php echo $cast_id; ?>)"
                                                class="btn-icon" data-tooltip="サムネイルに設定">
                                                <i class="fas fa-save"></i>
                                            </button>
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
                            <i class="fas fa-cloud-upload-alt upload-icon"></i>
                            <div class="banner-upload-text">クリックして動画を選択</div>
                            <div class="banner-upload-subtext">またはドラッグ＆ドロップ (20MB以下)</div>
                            <div class="upload-preview-thumb" id="upload_thumb_2" style="display: none;">
                                <canvas id="upload_canvas_2"></canvas>
                            </div>
                        </div>
                        <input type="file" name="movie_2" id="movie_2" accept="video/*" style="display: none;"
                            onchange="handleVideoSelect(this, 2)">
                        <div class="movie-action-bar">
                            <?php if ($existing_data && $existing_data['movie_2']): ?>
                            <button type="button" onclick="clearVideo(2)" class="btn-icon btn-icon-danger" data-tooltip="動画を削除">
                                <i class="fas fa-trash"></i>
                            </button>
                            <?php endif; ?>
                        </div>

                        <div id="video_container_2"
                            style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border-color); <?php echo (!$existing_data || !$existing_data['movie_2']) ? 'display: none;' : ''; ?>">
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
                                                    style="width: 100%; max-height: 200px; display: flex; align-items: center; justify-content: center; background: var(--bg-body); border-radius: 8px; color: var(--text-muted); font-size: 12px; aspect-ratio: 16/9;">
                                                    サムネイル未作成
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div
                                        style="margin-top: 15px; padding: 20px; background: var(--primary-bg); border-radius: 12px; border: 1px solid var(--primary-border);">
                                        <p
                                            style="text-align: center; color: var(--text-secondary); font-size: 13px; margin-bottom: 15px;">
                                            💡 スライダーを動かして好きなフレームを選択してください
                                        </p>
                                        <input type="range" id="thumbnail_slider_2_<?php echo $cast_id; ?>" min="0" max="100"
                                            value="5" step="0.1"
                                            style="width: 100%; margin: 10px 0; height: 8px; border-radius: 5px; background: var(--border-color); outline: none; cursor: pointer;"
                                            oninput="updateThumbnailTimeDisplay(2, <?php echo $cast_id; ?>)">
                                        <div id="thumbnail_time_display_2_<?php echo $cast_id; ?>"
                                            style="text-align: center; color: var(--primary); font-weight: bold; font-size: 16px; margin: 10px 0;">
                                            0:05</div>
                                        <div class="movie-thumb-actions">
                                            <button type="button"
                                                onclick="generateThumbnailFromVideo(2, <?php echo $cast_id; ?>)"
                                                class="btn-icon" data-tooltip="サムネイルに設定">
                                                <i class="fas fa-save"></i>
                                            </button>
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
    // 更新成功アラート
    document.addEventListener('DOMContentLoaded', function () {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('success') === '1') {
            alert('動画を更新しました。');
            // URLからsuccessパラメータを除去
            urlParams.delete('success');
            const newUrl = window.location.pathname + (urlParams.toString() ? '?' + urlParams.toString() : '');
            window.history.replaceState({}, '', newUrl);
        }
    });

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

        // ドラッグ＆ドロップ対応
        document.querySelectorAll('.banner-upload-area').forEach(area => {
            const fileInput = area.nextElementSibling; // 直後の<input type="file">

            area.addEventListener('dragover', function (e) {
                e.preventDefault();
                e.stopPropagation();
                this.classList.add('dragover');
            });

            area.addEventListener('dragenter', function (e) {
                e.preventDefault();
                e.stopPropagation();
                this.classList.add('dragover');
            });

            area.addEventListener('dragleave', function (e) {
                e.preventDefault();
                e.stopPropagation();
                this.classList.remove('dragover');
            });

            area.addEventListener('drop', function (e) {
                e.preventDefault();
                e.stopPropagation();
                this.classList.remove('dragover');

                if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                    const file = e.dataTransfer.files[0];

                    // 動画ファイルかチェック
                    if (!file.type.startsWith('video/')) {
                        alert('動画ファイルを選択してください。');
                        return;
                    }

                    // file inputにファイルをセット
                    const dt = new DataTransfer();
                    dt.items.add(file);
                    fileInput.files = dt.files;

                    // onchangeイベントを発火させる
                    fileInput.dispatchEvent(new Event('change'));
                }
            });
        });

        // スライダー位置初期化など
        const videos = document.querySelectorAll('video');
        videos.forEach(video => {
            video.addEventListener('loadedmetadata', function () {
                // 必要あれば初期化
            });
        });
    });

    // 動画選択時の処理（サムネイルプレビュー + コンテナ表示）
    function handleVideoSelect(input, videoNum) {
        if (!input.files || !input.files[0]) return;
        const file = input.files[0];

        // ファイルサイズチェック（20MB制限）
        const maxSize = 20 * 1024 * 1024;
        if (file.size > maxSize) {
            alert('ファイルサイズを20MB以下にして下さい。');
            input.value = '';
            return;
        }

        const fileURL = URL.createObjectURL(file);

        // アップロードエリア内に3秒時点のサムネイルを表示
        const thumbContainer = document.getElementById('upload_thumb_' + videoNum);
        const canvas = document.getElementById('upload_canvas_' + videoNum);
        const uploadArea = input.previousElementSibling; // banner-upload-area

        const tempVideo = document.createElement('video');
        tempVideo.src = fileURL;
        tempVideo.muted = true;
        tempVideo.playsInline = true;
        tempVideo.preload = 'metadata';

        tempVideo.addEventListener('loadeddata', function () {
            // 3秒地点にシーク（動画が3秒未満なら0秒）
            tempVideo.currentTime = Math.min(3, tempVideo.duration || 0);
        });

        tempVideo.addEventListener('seeked', function () {
            // canvasにフレームを描画
            canvas.width = 320;
            canvas.height = 180;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(tempVideo, 0, 0, canvas.width, canvas.height);

            thumbContainer.style.display = 'block';
            uploadArea.classList.add('has-preview');

            URL.revokeObjectURL(fileURL);
        });

        // アクションバーに削除ボタンを動的追加（まだ無い場合）
        const actionBar = input.parentElement.querySelector('.movie-action-bar');
        if (actionBar && !actionBar.querySelector('.btn-icon-danger')) {
            const deleteBtn = document.createElement('button');
            deleteBtn.type = 'button';
            deleteBtn.className = 'btn-icon btn-icon-danger';
            deleteBtn.setAttribute('data-tooltip', '動画を削除');
            deleteBtn.innerHTML = '<i class="fas fa-trash"></i>';
            deleteBtn.onclick = function () { clearVideo(videoNum); };
            actionBar.appendChild(deleteBtn);
        }

        // 動画コンテナを表示して動画プレビューを更新
        const container = document.getElementById('video_container_' + videoNum);
        container.style.display = 'block';

        const previewArea = document.getElementById('video_preview_' + videoNum);
        previewArea.innerHTML = '';

        const castId = <?php echo $cast_id ?: 'null'; ?>;

        // 動画プレビュー
        const video = document.createElement('video');
        video.src = URL.createObjectURL(file);
        video.controls = true;
        video.style.width = '100%';
        video.style.maxHeight = '200px';
        video.id = 'video_' + videoNum + '_' + (castId || 'new');

        const previewContainer = document.createElement('div');
        previewContainer.className = 'video-preview-container';

        const videoSection = document.createElement('div');
        videoSection.className = 'video-section';
        videoSection.appendChild(video);

        const thumbSection = document.createElement('div');
        thumbSection.className = 'thumbnail-section';
        thumbSection.id = 'thumbnail_display_' + videoNum;
        thumbSection.innerHTML = '<div style="width: 100%; max-height: 200px; display: flex; align-items: center; justify-content: center; background: var(--bg-body); border-radius: 8px; color: var(--text-muted); font-size: 12px; aspect-ratio: 16/9;">サムネイル未作成</div>';

        previewContainer.appendChild(videoSection);
        previewContainer.appendChild(thumbSection);
        previewArea.appendChild(previewContainer);

        // サムネイル生成UI
        const tools = document.createElement('div');
        tools.style.cssText = 'margin-top: 15px; padding: 20px; background: var(--primary-bg); border-radius: 12px; border: 1px solid var(--primary-border);';
        tools.innerHTML = `
            <p style="text-align: center; color: var(--text-secondary); font-size: 13px; margin-bottom: 15px;">💡 スライダーを動かして好きなフレームを選択してください</p>
            <input type="range" id="thumbnail_slider_${videoNum}_${castId}" min="0" max="100" value="5" step="0.1" style="width: 100%; margin: 10px 0; height: 8px; border-radius: 5px; background: var(--border-color); outline: none; cursor: pointer;" oninput="updateThumbnailTimeDisplay(${videoNum}, ${castId})">
            <div id="thumbnail_time_display_${videoNum}_${castId}" style="text-align: center; color: var(--primary); font-weight: bold; font-size: 16px; margin: 10px 0;">0:05</div>
            <div class="movie-thumb-actions">
                <button type="button" onclick="generateThumbnailFromVideo(${videoNum}, ${castId})" class="btn-icon" data-tooltip="サムネイルに設定"><i class="fas fa-save"></i></button>
            </div>
            <div id="thumbnail_status_${videoNum}_${castId}" style="margin-top: 15px; text-align: center; font-size: 13px;"></div>
        `;
        previewArea.appendChild(tools);
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

                statusDiv.innerHTML = '<span style="color: var(--warning);">⏳ 処理中...</span>';

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
            const response = await fetch('api_save_thumbnail?tenant=<?php echo urlencode($tenantSlug); ?>', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                statusDiv.innerHTML = '<span style="color: var(--success);">✅ 作成完了！</span>';
                const thumbDisplay = document.getElementById('thumbnail_display_' + videoNum);
                if (thumbDisplay) {
                    thumbDisplay.innerHTML = `<img src="${result.thumbnail_url}?t=${Date.now()}" alt="サムネイル" style="width: 100%; max-height: 200px; object-fit: cover; border-radius: 8px;">`;
                }
            } else {
                statusDiv.innerHTML = '<span style="color: var(--danger);">❌ ' + result.message + '</span>';
            }
        } catch (e) {
            console.error(e);
            statusDiv.innerHTML = '<span style="color: var(--danger);">❌ エラーが発生しました</span>';
        }
    }

    function clearVideo(videoNum) {
        if (!confirm('この動画を削除対象にしますか？\n（更新ボタンを押すまで確定しません）')) return;

        // コンテナ非表示
        const container = document.getElementById('video_container_' + videoNum);
        if (container) container.style.display = 'none';

        // アクションバーの削除ボタンを非表示
        const movieColumn = container ? container.closest('.movie-column') : null;
        if (movieColumn) {
            const actionBar = movieColumn.querySelector('.movie-action-bar');
            if (actionBar) {
                const deleteBtn = actionBar.querySelector('.btn-icon-danger');
                if (deleteBtn) deleteBtn.style.display = 'none';
            }
        }

        // アップロードエリアのサムネイルプレビューをリセット
        const thumbContainer = document.getElementById('upload_thumb_' + videoNum);
        if (thumbContainer) thumbContainer.style.display = 'none';
        const uploadArea = document.getElementById('movie_' + videoNum)?.previousElementSibling;
        if (uploadArea) uploadArea.classList.remove('has-preview');

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