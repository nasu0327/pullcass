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
$cast_id = isset($_GET['cast_id']) ? (int)$_GET['cast_id'] : null;
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
        gap: 15px;
        margin-top: 10px;
    }

    .thumbnail-section img, .video-section video {
        max-width: 100%;
        border-radius: 8px;
        border: 1px solid rgba(255,255,255,0.1);
    }
    
    .upload-button {
        width: 100%;
        padding: 15px;
        font-size: 18px;
        border-radius: 30px;
    }

    /* サムネイル生成UI */
    .thumbnail-generator {
        margin-top: 15px; 
        padding: 20px; 
        background: rgba(39, 163, 235, 0.05); 
        border-radius: 12px; 
        border: 1px solid rgba(39, 163, 235, 0.2);
    }

    .thumbnail-slider {
        width: 100%; 
        margin: 10px 0; 
        height: 8px; 
        border-radius: 5px; 
        background: rgba(255, 255, 255, 0.2); 
        outline: none; 
        cursor: pointer;
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
    <h1><i class="fas fa-video"></i> <?php echo h($pageTitle); ?></h1>
    <?php if ($cast_id): ?>
        <p>キャスト: <?php echo h($existing_data['name']); ?> の動画を編集しています。</p>
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
        <div class="mb-4">
            <input type="text" id="castSearch" class="form-control" placeholder="キャスト名で検索..." style="max-width: 400px;">
        </div>
        
        <?php
        $registered_casts = array_filter($casts, function($cast) { 
            return !empty($cast['movie_1']) || !empty($cast['movie_2']); 
        });
        $unregistered_casts = array_filter($casts, function($cast) { 
            return empty($cast['movie_1']) && empty($cast['movie_2']); 
        });
        ?>
        
        <?php if (!empty($registered_casts)): ?>
            <h3 class="mb-3 border-bottom pb-2"><i class="fas fa-check"></i> 登録済み (<?php echo count($registered_casts); ?>名)</h3>
            <div class="cast-grid">
                <?php foreach ($registered_casts as $cast): ?>
                    <a href="index.php?tenant=<?php echo h($tenantSlug); ?>&cast_id=<?php echo $cast['id']; ?>" class="cast-card" data-cast-name="<?php echo h($cast['name']); ?>">
                        <?php if ($cast['img1']): ?>
                            <img src="<?php echo h($cast['img1']); ?>" alt="<?php echo h($cast['name']); ?>" class="cast-image">
                        <?php else: ?>
                            <div class="cast-initial"><?php echo mb_substr($cast['name'], 0, 1); ?></div>
                        <?php endif; ?>
                        <span class="cast-name"><?php echo h($cast['name']); ?></span>
                        <span class="status-badge status-registered">登録済み</span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($unregistered_casts)): ?>
            <h3 class="mb-3 border-bottom pb-2"><i class="fas fa-minus"></i> 未登録 (<?php echo count($unregistered_casts); ?>名)</h3>
            <div class="cast-grid">
                <?php foreach ($unregistered_casts as $cast): ?>
                    <a href="index.php?tenant=<?php echo h($tenantSlug); ?>&cast_id=<?php echo $cast['id']; ?>" class="cast-card" data-cast-name="<?php echo h($cast['name']); ?>">
                        <?php if ($cast['img1']): ?>
                            <img src="<?php echo h($cast['img1']); ?>" alt="<?php echo h($cast['name']); ?>" class="cast-image">
                        <?php else: ?>
                            <div class="cast-initial"><?php echo mb_substr($cast['name'], 0, 1); ?></div>
                        <?php endif; ?>
                        <span class="cast-name"><?php echo h($cast['name']); ?></span>
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
        
        <form action="upload.php?tenant=<?php echo h($tenantSlug); ?>" method="post" enctype="multipart/form-data" onsubmit="return validateUpload()">
            <input type="hidden" name="cast_id" value="<?php echo $cast_id; ?>">
            
            <div class="movie-grid">
                <!-- 動画1 -->
                <div class="movie-column">
                    <h3 class="text-center mb-3">動画 1</h3>
                    
                    <div class="file-input-group">
                        <input type="file" name="movie_1" id="movie_1" accept="video/*" class="form-control mb-2" onchange="previewVideo(this, 1)">
                        <small class="text-muted">MP4推奨 (最大100MB)</small>
                        <span id="movie_1_name" class="d-block mt-1 text-info"></span>
                    </div>
                    
                    <div id="video_container_1" style="<?php echo empty($existing_data['movie_1']) ? 'display:none;' : ''; ?>">
                        <div class="video-preview-container">
                            <div class="video-section">
                                <?php if (!empty($existing_data['movie_1'])): ?>
                                    <video id="video_1_<?php echo $cast_id; ?>" src="<?php echo h($existing_data['movie_1']); ?>" controls style="width:100%;"></video>
                                <?php else: ?>
                                    <video id="video_1_new" controls style="width:100%;"></video>
                                <?php endif; ?>
                            </div>
                            
                            <div class="thumbnail-section" id="thumbnail_display_1">
                                <?php if (!empty($existing_data['movie_1_thumbnail'])): ?>
                                    <img src="<?php echo h($existing_data['movie_1_thumbnail']); ?>" alt="サムネイル">
                                <?php else: ?>
                                    <div class="alert alert-warning p-2 text-center">サムネイル未設定</div>
                                <?php endif; ?>
                            </div>

                            <!-- サムネイル生成ツール (動画がある場合のみ表示) -->
                            <div class="thumbnail-generator">
                                <p class="text-center small text-white mb-2">💡 スライダーを動かして好きなフレームを選択してください</p>
                                <input type="range" id="thumbnail_slider_1_<?php echo $cast_id; ?>" min="0" max="100" value="5" step="0.1" class="thumbnail-slider" oninput="updateThumbnailTimeDisplay(1, <?php echo $cast_id; ?>)">
                                <div id="thumbnail_time_display_1_<?php echo $cast_id; ?>" class="text-center text-accent font-weight-bold my-2">0:00</div>
                                
                                <div class="d-flex justify-content-center gap-2 mt-3">
                                    <button type="button" class="btn btn-primary btn-sm" onclick="generateThumbnailFromVideo(1, <?php echo $cast_id; ?>)">
                                        <i class="fas fa-camera"></i> サムネイル作成
                                    </button>
                                    <button type="button" class="btn btn-danger btn-sm" onclick="clearVideo(1)">
                                        <i class="fas fa-trash"></i> 動画削除
                                    </button>
                                </div>
                                <div id="thumbnail_status_1_<?php echo $cast_id; ?>" class="text-center mt-2 small"></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 動画2 -->
                <div class="movie-column">
                    <h3 class="text-center mb-3">動画 2</h3>
                    
                    <div class="file-input-group">
                        <input type="file" name="movie_2" id="movie_2" accept="video/*" class="form-control mb-2" onchange="previewVideo(this, 2)">
                        <small class="text-muted">MP4推奨 (最大100MB)</small>
                         <span id="movie_2_name" class="d-block mt-1 text-info"></span>
                    </div>
                    
                    <div id="video_container_2" style="<?php echo empty($existing_data['movie_2']) ? 'display:none;' : ''; ?>">
                         <div class="video-preview-container">
                            <div class="video-section">
                                <?php if (!empty($existing_data['movie_2'])): ?>
                                    <video id="video_2_<?php echo $cast_id; ?>" src="<?php echo h($existing_data['movie_2']); ?>" controls style="width:100%;"></video>
                                <?php else: ?>
                                    <video id="video_2_new" controls style="width:100%;"></video>
                                <?php endif; ?>
                            </div>
                            
                            <div class="thumbnail-section" id="thumbnail_display_2">
                                <?php if (!empty($existing_data['movie_2_thumbnail'])): ?>
                                    <img src="<?php echo h($existing_data['movie_2_thumbnail']); ?>" alt="サムネイル">
                                <?php else: ?>
                                    <div class="alert alert-warning p-2 text-center">サムネイル未設定</div>
                                <?php endif; ?>
                            </div>

                            <!-- サムネイル生成ツール -->
                            <div class="thumbnail-generator">
                                <p class="text-center small text-white mb-2">💡 スライダーを動かして好きなフレームを選択してください</p>
                                <input type="range" id="thumbnail_slider_2_<?php echo $cast_id; ?>" min="0" max="100" value="5" step="0.1" class="thumbnail-slider" oninput="updateThumbnailTimeDisplay(2, <?php echo $cast_id; ?>)">
                                <div id="thumbnail_time_display_2_<?php echo $cast_id; ?>" class="text-center text-accent font-weight-bold my-2">0:00</div>
                                
                                <div class="d-flex justify-content-center gap-2 mt-3">
                                    <button type="button" class="btn btn-primary btn-sm" onclick="generateThumbnailFromVideo(2, <?php echo $cast_id; ?>)">
                                        <i class="fas fa-camera"></i> サムネイル作成
                                    </button>
                                    <button type="button" class="btn btn-danger btn-sm" onclick="clearVideo(2)">
                                        <i class="fas fa-trash"></i> 動画削除
                                    </button>
                                </div>
                                <div id="thumbnail_status_2_<?php echo $cast_id; ?>" class="text-center mt-2 small"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-4 mb-5">
                <button type="submit" class="btn btn-primary upload-button">
                    <i class="fas fa-cloud-upload-alt"></i> 動画を保存・更新する
                </button>
                <p class="text-muted mt-2">※サムネイルを作成しても「保存・更新」ボタンを押すまで確定されません（動画ファイル自体は別途アップロードが必要です）</p>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
// 簡易検索
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('castSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
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
    
    // スライダー位置初期化など
    const videos = document.querySelectorAll('video');
    videos.forEach(video => {
         video.addEventListener('loadedmetadata', function() {
             // 関連するスライダーなどを更新するロジックがあればここに
         });
    });
});

// 動画プレビュー表示
function previewVideo(input, num) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const container = document.getElementById('video_container_' + num);
        
        // ファイル名表示
        const nameSpan = document.getElementById('movie_' + num + '_name');
        if (nameSpan) nameSpan.textContent = file.name;
        
        // 動画表示用ID
        let videoId = 'video_' + num + '_<?php echo $cast_id; ?>';
        let video = document.getElementById(videoId);
        
        if (!video) {
            videoId = 'video_' + num + '_new';
            video = document.getElementById(videoId);
        }
        
        if (video) {
            const fileUrl = URL.createObjectURL(file);
            video.src = fileUrl;
            video.load();
            container.style.display = 'block';
        }
    }
}

// サムネイル時間表示更新
function updateThumbnailTimeDisplay(videoNum, castId) {
    const slider = document.getElementById('thumbnail_slider_' + videoNum + '_' + castId);
    const display = document.getElementById('thumbnail_time_display_' + videoNum + '_' + castId);
    
    // 動画要素を取得（既存または新規）
    let video = document.getElementById('video_' + videoNum + '_' + castId);
    if (!video) video = document.getElementById('video_' + videoNum + '_new');
    
    if (slider && display && video) {
        const duration = video.duration || 100;
        const currentTime = (parseFloat(slider.value) / 100) * duration;
        
        const minutes = Math.floor(currentTime / 60);
        const seconds = Math.floor(currentTime % 60);
        display.textContent = minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
        
        // 動画をシーク
        if (isFinite(currentTime)) {
            video.currentTime = currentTime;
        }
    }
}

// サムネイル生成実行
async function generateThumbnailFromVideo(videoNum, castId) {
    const slider = document.getElementById('thumbnail_slider_' + videoNum + '_' + castId);
    const statusDiv = document.getElementById('thumbnail_status_' + videoNum + '_' + castId);
    
    let video = document.getElementById('video_' + videoNum + '_' + castId);
    if (!video) video = document.getElementById('video_' + videoNum + '_new');
    
    if (!slider || !statusDiv || !video) return;
    
    statusDiv.innerHTML = '<span style="color: yellow;">⏳ 処理中...</span>';
    
    try {
        // Canvasキャプチャ
        const canvas = document.createElement('canvas');
        canvas.width = 640;
        canvas.height = 360; // 16:9
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        
        const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', 0.8));
        
        // サーバーへ送信
        const formData = new FormData();
        formData.append('thumbnail', blob, 'thumbnail.jpg');
        formData.append('cast_id', castId);
        formData.append('video_type', 'movie_' + videoNum + '_thumbnail');
        
        const response = await fetch('api_save_thumbnail.php?tenant=<?php echo urlencode($tenantSlug); ?>', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            statusDiv.innerHTML = '<span style="color: #4caf50;">✅ 作成完了！</span>';
            // 表示更新
            const thumbDisplay = document.getElementById('thumbnail_display_' + videoNum);
            if (thumbDisplay) {
                thumbDisplay.innerHTML = `<img src="${result.thumbnail_url}?t=${Date.now()}" alt="サムネイル">`;
            }
        } else {
            statusDiv.innerHTML = '<span style="color: red;">❌ ' + result.message + '</span>';
        }
        
    } catch (e) {
        console.error(e);
        statusDiv.innerHTML = '<span style="color: red;">❌ エラーが発生しました</span>';
    }
}

// 動画削除
function clearVideo(videoNum) {
    if (!confirm('この動画を削除対象にしますか？\n（更新ボタンを押すまで確定しません）')) return;
    
    const container = document.getElementById('video_container_' + videoNum);
    if (container) container.style.display = 'none';
    
    const input = document.getElementById('movie_' + videoNum);
    if (input) input.value = '';
    
    // 削除フラグinputを追加
    const form = document.querySelector('form');
    // 既存削除フラグがあれば削除してから追加（重複防止）
    const existing = form.querySelector(`input[name="clear_movie_${videoNum}"]`);
    if (existing) existing.remove();
    
    const hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = 'clear_movie_' + videoNum;
    hidden.value = '1';
    form.appendChild(hidden);
}


// アップロード検証
function validateUpload() {
    const movie1 = document.getElementById('movie_1');
    const movie2 = document.getElementById('movie_2');
    // PHP側制限より少し小さめに設定（余裕を持たせる）
    const maxSize = 100 * 1024 * 1024; // 100MB
    
    if (movie1 && movie1.files[0] && movie1.files[0].size > maxSize) {
        alert('動画1のサイズが大きすぎます(100MB以下にしてください)');
        return false;
    }
    
    if (movie2 && movie2.files[0] && movie2.files[0].size > maxSize) {
        alert('動画2のサイズが大きすぎます(100MB以下にしてください)');
        return false;
    }
    
    // 送信ボタンを無効化して二重送信防止
    const btn = document.querySelector('.upload-button');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> アップロード中...';
    }
    
    return true;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>