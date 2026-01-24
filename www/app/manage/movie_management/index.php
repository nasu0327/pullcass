<?php
/**
 * 動画管理 - メイン画面
 */

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../../includes/VideoThumbnailHelper.php';

// ログイン認証チェック
requireTenantAdminLogin();

// 選択されたキャストのID取得
$cast_id = isset($_GET['cast_id']) ? (int) $_GET['cast_id'] : null;
$existing_data = null;

try {
    // キャスト一覧を取得（動画登録状況も取得）
    // tenant_id でフィルタリング
    $sql = "SELECT id, name, img1, movie_1, movie_2 FROM tenant_casts WHERE tenant_id = ? ORDER BY sort_order ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tenantId]);
    $casts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 選択されたキャストの既存データを取得
    if ($cast_id) {
        $sql = "SELECT id, name, movie_1, movie_1_thumbnail, movie_2, movie_2_thumbnail, movie_1_seo_thumbnail, movie_2_seo_thumbnail, movie_1_mini, movie_2_mini FROM tenant_casts WHERE id = ? AND tenant_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$cast_id, $tenantId]);
        $existing_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing_data) {
            // 存在しないか、他テナントのデータの場合
            header('Location: index.php?tenant=' . urlencode($tenantSlug));
            exit;
        }

        // SEOサムネイルヘルパーを初期化（必要であれば）
        //$thumbnailHelper = new VideoThumbnailHelper($pdo);
    }

} catch (PDOException $e) {
    error_log('movie_management/index DB error: ' . $e->getMessage());
    $error = APP_DEBUG ? $e->getMessage() : 'システムエラーが発生しました。';
}

$pageTitle = '動画管理';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    /* 追加スタイル */
    .cast-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 15px;
        margin-bottom: 30px;
    }

    .cast-card {
        background: rgba(255, 255, 255, 0.05);
        border-radius: 12px;
        padding: 15px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        transition: all 0.3s ease;
        text-align: center;
        text-decoration: none;
        color: inherit;
        display: block;
    }

    .cast-card:hover {
        transform: translateY(-5px);
        background: rgba(255, 255, 255, 0.1);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
    }

    .cast-image {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        object-fit: cover;
        margin: 0 auto 10px;
        border: 2px solid rgba(255, 255, 255, 0.2);
        display: block;
    }

    .cast-initial {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 10px;
        font-size: 24px;
        color: white;
        font-weight: bold;
    }

    .cast-name {
        font-weight: bold;
        margin-bottom: 5px;
        display: block;
    }

    .status-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 11px;
    }

    .status-registered {
        background: #4caf50;
        color: white;
    }

    .status-unregistered {
        background: #9e9e9e;
        color: white;
    }

    /* 編集画面用 */
    .movie-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .movie-column {
        background: rgba(255, 255, 255, 0.05);
        padding: 20px;
        border-radius: 10px;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .file-input-group {
        margin-bottom: 15px;
        text-align: center;
    }

    /* ファイル選択ボタンスタイル */
    input[type="file"]::file-selector-button {
        background-color: var(--primary);
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 20px;
        cursor: pointer;
        margin-right: 10px;
        transition: background-color 0.3s;
    }

    input[type="file"]::file-selector-button:hover {
        opacity: 0.9;
    }

    .video-preview-container {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-top: 10px;
    }

    .thumbnail-section img,
    .video-section video {
        max-width: 100%;
        border-radius: 8px;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .upload-button {
        width: 100%;
        padding: 15px;
        font-size: 18px;
        border-radius: 30px;
    }
</style>

<?php
require_once __DIR__ . '/../includes/breadcrumb.php';
// パンくずリスト
if ($cast_id) {
    $breadcrumbs = [
        ['label' => 'ホーム', 'url' => '/app/manage/?tenant=' . $tenantSlug, 'icon' => 'fas fa-home'],
        ['label' => '動画管理', 'url' => 'index.php?tenant=' . $tenantSlug],
        ['label' => $existing_data['name']]
    ];
} else {
    $breadcrumbs = [
        ['label' => 'ホーム', 'url' => '/app/manage/?tenant=' . $tenantSlug, 'icon' => 'fas fa-home'],
        ['label' => '動画管理']
    ];
}
renderBreadcrumb($breadcrumbs);
?>

<div class="header">
    <h1><i class="fas fa-video"></i>
        <?php echo h($pageTitle); ?>
    </h1>
    <?php if ($cast_id): ?>
        <p>キャスト:
            <?php echo h($existing_data['name']); ?> の動画を編集しています。
        </p>
    <?php else: ?>
        <p>キャストの動画コンテンツを管理します。</p>
    <?php endif; ?>
</div>

<?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> 動画情報を更新しました。
    </div>
<?php endif; ?>

<div class="container-fluid">
    <?php if (!$cast_id): ?>
        <!-- 一覧画面 -->

        <!-- 検索ボックス -->
        <div class="mb-4">
            <input type="text" id="castSearch" class="form-control" placeholder="キャスト名で検索..." style="max-width: 400px;">
        </div>

        <?php
        $registered_casts = array_filter($casts, function ($cast) {
            return !empty($cast['movie_1']) || !empty($cast['movie_2']);
        });
        $unregistered_casts = array_filter($casts, function ($cast) {
            return empty($cast['movie_1']) && empty($cast['movie_2']);
        });
        ?>

        <!-- 登録済み -->
        <?php if (!empty($registered_casts)): ?>
            <h3 class="mb-3 border-bottom pb-2"><i class="fas fa-check"></i> 登録済み (
                <?php echo count($registered_casts); ?>名)
            </h3>
            <div class="cast-grid">
                <?php foreach ($registered_casts as $cast): ?>
                    <a href="index.php?tenant=<?php echo h($tenantSlug); ?>&cast_id=<?php echo $cast['id']; ?>" class="cast-card"
                        data-cast-name="<?php echo h($cast['name']); ?>">
                        <?php if ($cast['img1']): ?>
                            <img src="<?php echo h($cast['img1']); ?>" alt="<?php echo h($cast['name']); ?>" class="cast-image">
                        <?php else: ?>
                            <div class="cast-initial">
                                <?php echo mb_substr($cast['name'], 0, 1); ?>
                            </div>
                        <?php endif; ?>
                        <span class="cast-name">
                            <?php echo h($cast['name']); ?>
                        </span>
                        <span class="status-badge status-registered">登録済み</span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- 未登録 -->
        <?php if (!empty($unregistered_casts)): ?>
            <h3 class="mb-3 border-bottom pb-2"><i class="fas fa-minus"></i> 未登録 (
                <?php echo count($unregistered_casts); ?>名)
            </h3>
            <div class="cast-grid">
                <?php foreach ($unregistered_casts as $cast): ?>
                    <a href="index.php?tenant=<?php echo h($tenantSlug); ?>&cast_id=<?php echo $cast['id']; ?>" class="cast-card"
                        data-cast-name="<?php echo h($cast['name']); ?>">
                        <?php if ($cast['img1']): ?>
                            <img src="<?php echo h($cast['img1']); ?>" alt="<?php echo h($cast['name']); ?>" class="cast-image">
                        <?php else: ?>
                            <div class="cast-initial">
                                <?php echo mb_substr($cast['name'], 0, 1); ?>
                            </div>
                        <?php endif; ?>
                        <span class="cast-name">
                            <?php echo h($cast['name']); ?>
                        </span>
                        <span class="status-badge status-unregistered">未登録</span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- 編集画面 -->
        <div class="mb-3">
            <a href="index.php?tenant=<?php echo h($tenantSlug); ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> 一覧に戻る
            </a>
        </div>

        <form action="upload.php?tenant=<?php echo h($tenantSlug); ?>" method="post" enctype="multipart/form-data">
            <input type="hidden" name="cast_id" value="<?php echo $cast_id; ?>">

            <div class="movie-grid">
                <!-- 動画1 -->
                <div class="movie-column">
                    <h3 class="text-center mb-3">動画 1</h3>

                    <div class="file-input-group">
                        <input type="file" name="movie_1" id="movie_1" accept="video/*" class="form-control mb-2"
                            onchange="previewVideo(this, 1)">
                        <small class="text-muted">MP4推奨 (最大100MB)</small>
                    </div>

                    <div id="preview_area_1">
                        <?php if (!empty($existing_data['movie_1'])): ?>
                            <div class="video-preview-container">
                                <h5>現在の動画</h5>
                                <div class="video-section">
                                    <video src="<?php echo h($existing_data['movie_1']); ?>" controls
                                        style="width:100%;"></video>
                                </div>

                                <div class="mt-2">
                                    <h5>現在のサムネイル</h5>
                                    <div class="thumbnail-section">
                                        <?php if (!empty($existing_data['movie_1_thumbnail'])): ?>
                                            <img src="<?php echo h($existing_data['movie_1_thumbnail']); ?>" alt="サムネイル">
                                        <?php else: ?>
                                            <div class="alert alert-warning p-2 text-center">サムネイル未設定</div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="mt-2">
                                        <label>
                                            <input type="checkbox" name="clear_movie_1" value="1"> この動画とサムネイルを削除する
                                        </label>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 動画2 -->
                <div class="movie-column">
                    <h3 class="text-center mb-3">動画 2</h3>

                    <div class="file-input-group">
                        <input type="file" name="movie_2" id="movie_2" accept="video/*" class="form-control mb-2"
                            onchange="previewVideo(this, 2)">
                        <small class="text-muted">MP4推奨 (最大100MB)</small>
                    </div>

                    <div id="preview_area_2">
                        <?php if (!empty($existing_data['movie_2'])): ?>
                            <div class="video-preview-container">
                                <h5>現在の動画</h5>
                                <div class="video-section">
                                    <video src="<?php echo h($existing_data['movie_2']); ?>" controls
                                        style="width:100%;"></video>
                                </div>

                                <div class="mt-2">
                                    <h5>現在のサムネイル</h5>
                                    <div class="thumbnail-section">
                                        <?php if (!empty($existing_data['movie_2_thumbnail'])): ?>
                                            <img src="<?php echo h($existing_data['movie_2_thumbnail']); ?>" alt="サムネイル">
                                        <?php else: ?>
                                            <div class="alert alert-warning p-2 text-center">サムネイル未設定</div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="mt-2">
                                        <label>
                                            <input type="checkbox" name="clear_movie_2" value="1"> この動画とサムネイルを削除する
                                        </label>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="text-center mt-4 mb-5">
                <button type="submit" class="btn btn-primary upload-button">
                    <i class="fas fa-cloud-upload-alt"></i> 動画を保存・更新する
                </button>
                <p class="text-muted mt-2">※保存時にサムネイルが自動生成されます（サーバー環境による）</p>
            </div>
        </form>

    <?php endif; ?>
</div>

<script>
    // 簡易検索
    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('castSearch');
        if (searchInput) {
            searchInput.addEventListener('input', function (e) {
                const term = e.target.value.toLowerCase();
                const cards = document.querySelectorAll('.cast-card');

                cards.forEach(card => {
                    const name = card.dataset.castName.toLowerCase();
                    if (name.includes(term)) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        }
    });

    // 動画プレビュー
    function previewVideo(input, num) {
        if (input.files && input.files[0]) {
            const file = input.files[0];
            const previewArea = document.getElementById('preview_area_' + num);

            // ファイルサイズチェック (100MB)
            if (file.size > 100 * 1024 * 1024) {
                alert('ファイルサイズが大きすぎます（100MB以下にしてください）');
                input.value = '';
                return;
            }

            const reader = new FileReader();
            reader.onload = function (e) {
                previewArea.innerHTML = `
                <div class="video-preview-container mt-3">
                    <div class="alert alert-info">アップロード予定の動画: ${file.name}</div>
                    <div class="video-section">
                        <video src="${e.target.result}" controls style="width:100%;"></video>
                    </div>
                    <p class="text-muted small mt-1">※保存後にサムネイルが生成されます</p>
                </div>
            `;
            };
            reader.readAsDataURL(file);
        }
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>