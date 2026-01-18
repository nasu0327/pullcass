<?php
/**
 * バナー管理画面
 * バナー画像の追加・編集・削除・並び替え
 */

// 認証チェック（$tenant, $tenantIdが自動設定される）
require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

// セクションキーを取得
$sectionKey = $_GET['section'] ?? '';

if (empty($sectionKey)) {
    header('Location: index.php');
    exit;
}

// セクション情報を取得
try {
    $stmt = $pdo->prepare("
        SELECT * FROM top_layout_sections 
        WHERE section_key = ? AND tenant_id = ? 
        LIMIT 1
    ");
    $stmt->execute([$sectionKey, $tenantId]);
    $section = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$section || $section['section_type'] !== 'banner') {
        header('Location: index.php');
        exit;
    }
    
    // バナー一覧を取得
    $stmtBanners = $pdo->prepare("
        SELECT * FROM top_layout_banners 
        WHERE section_id = ? AND tenant_id = ?
        ORDER BY display_order ASC
    ");
    $stmtBanners->execute([$section['id'], $tenantId]);
    $banners = $stmtBanners->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("エラー: " . $e->getMessage());
}

$error = '';
$success = '';

// 新規登録処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $link_url = $_POST['link_url'] ?? '';
    $alt_text = $_POST['alt_text'] ?? '';
    $target = $_POST['target'] ?? '_self';
    $nofollow = isset($_POST['nofollow']) ? 1 : 0;
    
    // 画像アップロード
    if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/top_layout_banners/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // ファイルサイズチェック（2MB）
        $max_file_size = 2 * 1024 * 1024;
        if ($_FILES['banner_image']['size'] > $max_file_size) {
            $error = 'ファイルサイズを2MB以下にして下さい';
        }
        
        // ファイル拡張子チェック
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $file_extension = strtolower(pathinfo($_FILES['banner_image']['name'], PATHINFO_EXTENSION));
        
        if (empty($error) && !in_array($file_extension, $allowed_extensions)) {
            $error = '許可されていないファイル形式です。（jpg, jpeg, png, gif, webp のみ）';
        }
        
        if (empty($error)) {
            $new_filename = $sectionKey . '_' . uniqid() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['banner_image']['tmp_name'], $upload_path)) {
                $image_path = '/uploads/top_layout_banners/' . $new_filename;
                
                // display_orderを取得（最大値+1）
                $stmt = $pdo->prepare("
                    SELECT COALESCE(MAX(display_order), 0) + 1 as next_order 
                    FROM top_layout_banners 
                    WHERE section_id = ? AND tenant_id = ?
                ");
                $stmt->execute([$section['id'], $tenantId]);
                $next_order = $stmt->fetchColumn();
                
                // データベースに保存
                $stmt = $pdo->prepare("
                    INSERT INTO top_layout_banners 
                    (tenant_id, section_id, image_path, link_url, target, nofollow, alt_text, display_order) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                if ($stmt->execute([$tenantId, $section['id'], $image_path, $link_url, $target, $nofollow, $alt_text, $next_order])) {
                    $success = 'バナーを追加しました！';
                    // リロード
                    header('Location: banner_manage.php?section=' . $sectionKey . '&success=1');
                    exit;
                } else {
                    $error = 'データベースへの保存に失敗しました。';
                    unlink($upload_path);
                }
            } else {
                $error = 'ファイルのアップロードに失敗しました。';
            }
        }
    } else {
        $error = '画像ファイルを選択してください。';
    }
}

// ページタイトル
$pageTitle = '画像管理 - ' . h($section['admin_title']);
?>

<?php include __DIR__ . '/../includes/header.php'; ?>

<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<style>
    .container {
        max-width: 900px;
        margin: 0 auto;
        padding: 40px 20px;
    }

    .form-container {
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(20px);
        border-radius: 20px;
        padding: 30px;
        margin-bottom: 40px;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .form-container h2 {
        color: white;
        margin-bottom: 20px;
        font-size: 1.3rem;
    }

    .form-group {
        margin-bottom: 25px;
    }

    label {
        display: block;
        margin-bottom: 8px;
        color: rgba(255, 255, 255, 0.9);
        font-weight: 500;
    }

    input[type="text"],
    input[type="url"],
    input[type="file"],
    select {
        width: 100%;
        padding: 12px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.1);
        color: #ffffff;
        box-sizing: border-box;
        transition: all 0.3s ease;
    }

    input:focus,
    select:focus {
        outline: none;
        border-color: #27a3eb;
        background: rgba(255, 255, 255, 0.15);
    }

    .btn {
        background: #27a3eb;
        color: white;
        padding: 15px 30px;
        border: none;
        border-radius: 25px;
        cursor: pointer;
        font-size: 1rem;
        font-weight: 600;
        width: 100%;
        max-width: 400px;
        margin: 0 auto;
        display: block;
        transition: all 0.3s ease;
    }

    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(39, 163, 235, 0.3);
    }

    .banner-list {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .banner-item {
        display: flex;
        align-items: center;
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 15px;
        padding: 20px;
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(20px);
        transition: all 0.3s ease;
    }

    .banner-item:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        border-color: rgba(39, 163, 235, 0.3);
    }

    .banner-item.hidden {
        opacity: 0.5;
    }

    .drag-handle {
        color: rgba(255, 255, 255, 0.4);
        cursor: grab;
        margin-right: 15px;
    }

    .banner-img {
        height: 60px;
        max-width: 200px;
        object-fit: contain;
        border-radius: 8px;
        margin-right: 20px;
    }

    .banner-info {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .banner-actions {
        display: flex;
        gap: 10px;
    }

    .visibility-btn,
    .edit-btn,
    .delete-btn {
        padding: 10px 16px;
        border-radius: 20px;
        font-size: 14px;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .visibility-btn {
        background: linear-gradient(45deg, #4CAF50, #45a049);
        color: white;
    }

    .visibility-btn.hidden {
        background: linear-gradient(45deg, #9E9E9E, #757575);
    }

    .edit-btn {
        background: #27a3eb;
        color: white;
    }

    .delete-btn {
        background: #f44336;
        color: white;
    }

    .visibility-btn:hover,
    .edit-btn:hover,
    .delete-btn:hover {
        transform: translateY(-2px);
    }

    .error {
        color: #ff6b6b;
        background: rgba(255, 107, 107, 0.1);
        padding: 12px;
        border-radius: 10px;
        margin-bottom: 20px;
    }

    .success {
        color: #4CAF50;
        background: rgba(76, 175, 80, 0.1);
        padding: 12px;
        border-radius: 10px;
        margin-bottom: 20px;
    }

    .preview-container {
        margin-top: 15px;
        text-align: center;
    }

    .preview-container img {
        max-width: 300px;
        max-height: 150px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }

    /* モーダルスタイル */
    .modal {
        display: none;
        position: fixed;
        z-index: 10000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        backdrop-filter: blur(5px);
    }

    .modal-content {
        background: #2d2d2d;
        margin: 5% auto;
        padding: 30px;
        border: 2px solid rgba(39, 163, 235, 0.3);
        border-radius: 20px;
        width: 90%;
        max-width: 600px;
        max-height: 80vh;
        overflow-y: auto;
        box-shadow: 0 15px 50px rgba(0, 0, 0, 0.5);
    }

    .modal-content h2 {
        margin: 0 0 25px 0;
        color: #27a3eb;
        font-size: 1.8rem;
    }

    .close-modal {
        color: rgba(255, 255, 255, 0.6);
        float: right;
        font-size: 32px;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .close-modal:hover {
        color: #f44336;
    }

    .modal-form-group {
        margin-bottom: 20px;
    }

    .modal-form-group input,
    .modal-form-group select {
        width: 100%;
        padding: 12px 15px;
        background: rgba(255, 255, 255, 0.08);
        border: 2px solid rgba(255, 255, 255, 0.15);
        border-radius: 10px;
        color: white;
        font-size: 1rem;
        box-sizing: border-box;
    }

    .modal-form-group input:focus,
    .modal-form-group select:focus {
        outline: none;
        border-color: #27a3eb;
    }

    .modal-buttons {
        display: flex;
        gap: 15px;
        margin-top: 25px;
    }

    .modal-btn {
        flex: 1;
        padding: 12px 24px;
        border: none;
        border-radius: 10px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .modal-btn.primary {
        background: linear-gradient(135deg, #27a3eb 0%, #1e88c7 100%);
        color: white;
    }

    .modal-btn.secondary {
        background: rgba(255, 255, 255, 0.1);
        color: white;
        border: 2px solid rgba(255, 255, 255, 0.2);
    }

    .modal-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(39, 163, 235, 0.3);
    }
</style>

<div class="container">
    <div class="header">
        <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 10px;">
            <button type="button" onclick="window.location.href='index.php?tenant=<?php echo urlencode($tenantSlug); ?>'" class="btn" style="background: rgba(255, 255, 255, 0.1); color: rgba(255, 255, 255, 0.9); border: 2px solid rgba(255, 255, 255, 0.2); padding: 10px 20px;">
                <span class="material-icons" style="font-size: 16px; vertical-align: middle; margin-right: 5px;">arrow_back</span>
                戻る
            </button>
            <h1 style="margin: 0;">画像管理</h1>
        </div>
        <p>※対応拡張子：jpg, jpeg, png, gif, webp / アップロード制限：最大2MB</p>
    </div>

    <!-- タイトル設定フォーム -->
    <div class="form-container">
        <h2>セクション設定</h2>
        <form id="titleForm">
            <div class="form-group">
                <label for="adminTitle">管理名（必須）:</label>
                <input type="text" id="adminTitle" value="<?php echo h($section['admin_title']); ?>" placeholder="管理画面で表示される名前" required>
            </div>
            <div class="form-group">
                <label for="titleEn">メインタイトル（任意）:</label>
                <input type="text" id="titleEn" value="<?php echo h($section['title_en']); ?>" placeholder="例: About Our Shop">
            </div>
            <div class="form-group">
                <label for="titleJa">サブタイトル（任意）:</label>
                <input type="text" id="titleJa" value="<?php echo h($section['title_ja']); ?>" placeholder="例: お店紹介">
            </div>
            <button type="button" onclick="saveTitles()" class="btn" style="background: #FF9800;">
                <span class="material-icons" style="font-size: 16px; vertical-align: middle; margin-right: 5px;">save</span>
                タイトルを保存
            </button>
        </form>
    </div>

    <!-- 新規登録フォーム -->
    <div class="form-container">
        <h2>新規画像追加</h2>
        <?php if ($error): ?>
            <div class="error"><?php echo h($error); ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['success'])): ?>
            <div class="success">バナーを追加しました！</div>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="banner_image">画像を選択:</label>
                <input type="file" id="banner_image" name="banner_image" accept="image/*" required onchange="previewImage(this)">
                <small style="color: rgba(255, 255, 255, 0.6); display: block; margin-top: 5px;">
                    推奨サイズ: 幅600px以上（jpg, png, gif, webp）
                </small>
                <div id="preview" class="preview-container" style="display: none;">
                    <img src="" alt="プレビュー">
                </div>
            </div>
            <div class="form-group">
                <label for="link_url">リンク先URL（任意）:</label>
                <input type="url" id="link_url" name="link_url" placeholder="https://example.com">
            </div>
            <div class="form-group">
                <label for="target">リンクの開き方:</label>
                <select id="target" name="target">
                    <option value="_self">同じタブで開く (_self)</option>
                    <option value="_blank">新しいタブで開く (_blank)</option>
                </select>
            </div>
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" id="nofollow" name="nofollow" style="width: auto; cursor: pointer;">
                    <span>nofollow属性を付与（SEO評価を渡さない）</span>
                </label>
            </div>
            <div class="form-group">
                <label for="alt_text">alt属性（画像の説明）:</label>
                <input type="text" id="alt_text" name="alt_text" placeholder="例: <?php echo h($section['admin_title']); ?>">
            </div>
            <button type="submit" name="register" class="btn">
                追加する
            </button>
        </form>
    </div>

    <!-- バナー一覧 -->
    <div class="form-container">
        <h2>登録済み画像</h2>
        <div class="banner-list" id="bannerList">
            <?php if (count($banners) > 0): ?>
                <?php foreach ($banners as $banner): ?>
                <div class="banner-item <?php echo $banner['is_visible'] ? '' : 'hidden'; ?>" data-id="<?php echo $banner['id']; ?>" draggable="true">
                    <span class="material-icons drag-handle">drag_indicator</span>
                    <img src="<?php echo h($banner['image_path']); ?>" alt="<?php echo h($banner['alt_text']); ?>" class="banner-img">
                    <div class="banner-info">
                        <?php if (!empty($banner['link_url'])): ?>
                        <div style="color: rgba(255, 255, 255, 0.9);">
                            リンク先: <a href="<?php echo h($banner['link_url']); ?>" target="_blank" style="color: #27a3eb;"><?php echo h($banner['link_url']); ?></a>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($banner['alt_text'])): ?>
                        <div style="color: rgba(255, 255, 255, 0.7); font-size: 0.9rem;">
                            alt: <?php echo h($banner['alt_text']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="banner-actions">
                        <button onclick="toggleBannerVisibility(<?php echo $banner['id']; ?>, this)" class="visibility-btn <?php echo $banner['is_visible'] ? '' : 'hidden'; ?>">
                            <?php echo $banner['is_visible'] ? '表示中' : '非表示'; ?>
                        </button>
                        <button class="edit-btn" onclick="editBanner(<?php echo $banner['id']; ?>)">
                            編集
                        </button>
                        <button class="delete-btn" onclick="deleteBanner(<?php echo $banner['id']; ?>)">
                            削除
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: rgba(255, 255, 255, 0.6);">
                    登録されているバナーはありません
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 編集モーダル -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close-modal">&times;</span>
        <h2>画像編集</h2>
        <form id="editForm">
            <input type="hidden" id="editBannerId">
            <div class="modal-form-group">
                <label style="color: rgba(255, 255, 255, 0.9); margin-bottom: 8px; display: block;">リンク先URL:</label>
                <input type="url" id="editLinkUrl" placeholder="https://example.com">
            </div>
            <div class="modal-form-group">
                <label style="color: rgba(255, 255, 255, 0.9); margin-bottom: 8px; display: block;">リンクの開き方:</label>
                <select id="editTarget">
                    <option value="_self">同じタブで開く (_self)</option>
                    <option value="_blank">新しいタブで開く (_blank)</option>
                </select>
            </div>
            <div class="modal-form-group">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; color: rgba(255, 255, 255, 0.9);">
                    <input type="checkbox" id="editNofollow" style="width: auto; cursor: pointer;">
                    <span>nofollow属性を付与（SEO評価を渡さない）</span>
                </label>
            </div>
            <div class="modal-form-group">
                <label style="color: rgba(255, 255, 255, 0.9); margin-bottom: 8px; display: block;">alt属性（画像の説明）:</label>
                <input type="text" id="editAltText" placeholder="画像の説明">
            </div>
            <div class="modal-buttons">
                <button type="button" class="modal-btn primary" onclick="saveBannerEdit()">
                    保存
                </button>
                <button type="button" class="modal-btn secondary" onclick="closeModal()">
                    キャンセル
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // タイトル保存
    function saveTitles() {
        const adminTitle = document.getElementById('adminTitle').value.trim();
        const titleEn = document.getElementById('titleEn').value.trim();
        const titleJa = document.getElementById('titleJa').value.trim();
        const sectionId = <?php echo $section['id']; ?>;
        
        if (!adminTitle) {
            alert('管理名は必須です。');
            return;
        }
        
        fetch('edit_title.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                id: sectionId,
                admin_title: adminTitle,
                title_en: titleEn,
                title_ja: titleJa
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('保存しました');
            } else {
                alert('保存に失敗しました: ' + (data.message || '不明なエラー'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('保存に失敗しました');
        });
    }
    
    // 画像プレビュー
    function previewImage(input) {
        const preview = document.getElementById('preview');
        const previewImg = preview.querySelector('img');
        
        if (input.files && input.files[0]) {
            // ファイルサイズチェック（2MB）
            const maxFileSize = 2 * 1024 * 1024;
            if (input.files[0].size > maxFileSize) {
                alert('ファイルサイズを2MB以下にして下さい');
                input.value = '';
                preview.style.display = 'none';
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImg.src = e.target.result;
                preview.style.display = 'block';
            }
            reader.readAsDataURL(input.files[0]);
        } else {
            preview.style.display = 'none';
        }
    }

    // Sortable.js初期化
    const bannerList = new Sortable(document.getElementById('bannerList'), {
        animation: 150,
        handle: '.drag-handle',
        onEnd: function() {
            updateBannerOrder();
        }
    });

    // 順序更新
    function updateBannerOrder() {
        const items = Array.from(document.querySelectorAll('.banner-item'));
        const order = items.map(item => item.dataset.id);
        
        fetch('update_banner_order.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ order: order })
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                alert('順序の更新に失敗しました');
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    }

    // 表示/非表示切り替え
    function toggleBannerVisibility(id, button) {
        fetch('toggle_banner_visibility.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const item = button.closest('.banner-item');
                if (data.is_visible) {
                    button.textContent = '表示中';
                    button.classList.remove('hidden');
                    item.classList.remove('hidden');
                } else {
                    button.textContent = '非表示';
                    button.classList.add('hidden');
                    item.classList.add('hidden');
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('表示状態の更新に失敗しました');
        });
    }

    // モーダル操作
    const modal = document.getElementById('editModal');
    const closeBtn = document.querySelector('.close-modal');

    function editBanner(id) {
        fetch(`get_banner.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('editBannerId').value = id;
                    document.getElementById('editLinkUrl').value = data.banner.link_url || '';
                    document.getElementById('editTarget').value = data.banner.target || '_self';
                    document.getElementById('editNofollow').checked = data.banner.nofollow == 1;
                    document.getElementById('editAltText').value = data.banner.alt_text || '';
                    modal.style.display = 'block';
                } else {
                    alert('バナー情報の取得に失敗しました');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('バナー情報の取得に失敗しました');
            });
    }

    function closeModal() {
        modal.style.display = 'none';
    }

    function saveBannerEdit() {
        const id = document.getElementById('editBannerId').value;
        const linkUrl = document.getElementById('editLinkUrl').value;
        const target = document.getElementById('editTarget').value;
        const nofollow = document.getElementById('editNofollow').checked ? 1 : 0;
        const altText = document.getElementById('editAltText').value;
        
        fetch('edit_banner.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                id: id,
                link_url: linkUrl,
                target: target,
                nofollow: nofollow,
                alt_text: altText
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('保存しました');
                location.reload();
            } else {
                alert('更新に失敗しました: ' + (data.message || '不明なエラー'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('更新に失敗しました。');
        });
    }

    closeBtn.addEventListener('click', closeModal);
    
    window.addEventListener('click', function(e) {
        if (e.target === modal) {
            closeModal();
        }
    });

    // 削除
    function deleteBanner(id) {
        if (!confirm('本当にこのバナーを削除しますか？')) {
            return;
        }
        
        fetch('delete_banner.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('削除しました');
                location.reload();
            } else {
                alert('削除に失敗しました');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('削除に失敗しました');
        });
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
