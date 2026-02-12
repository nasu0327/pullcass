<?php
/**
 * バナー管理画面（インデックスページ用）
 */

// 認証チェック（$tenant, $tenantIdが自動設定される）
require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

// セクションキーを取得
$sectionKey = $_GET['section'] ?? '';

if (empty($sectionKey)) {
    header('Location: index.php?tenant=' . urlencode($tenantSlug));
    exit;
}

// セクション情報を取得
try {
    $stmt = $pdo->prepare("
        SELECT * FROM index_layout_sections 
        WHERE section_key = ? AND tenant_id = ? 
        LIMIT 1
    ");
    $stmt->execute([$sectionKey, $tenantId]);
    $section = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$section || $section['section_type'] !== 'banner') {
        header('Location: index.php?tenant=' . urlencode($tenantSlug));
        exit;
    }

    // バナー一覧を取得
    $stmtBanners = $pdo->prepare("
        SELECT * FROM index_layout_banners 
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
        $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/index_layout_banners/';
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
                $image_path = '/uploads/index_layout_banners/' . $new_filename;

                // display_orderを取得（最大値+1）
                $stmt = $pdo->prepare("
                    SELECT COALESCE(MAX(display_order), 0) + 1 as next_order 
                    FROM index_layout_banners 
                    WHERE section_id = ? AND tenant_id = ?
                ");
                $stmt->execute([$section['id'], $tenantId]);
                $next_order = $stmt->fetchColumn();

                // データベースに保存
                $stmt = $pdo->prepare("
                    INSERT INTO index_layout_banners 
                    (tenant_id, section_id, image_path, link_url, target, nofollow, alt_text, display_order) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                if ($stmt->execute([$tenantId, $section['id'], $image_path, $link_url, $target, $nofollow, $alt_text, $next_order])) {
                    $success = 'バナーを追加しました！';
                    header('Location: banner_manage.php?section=' . $sectionKey . '&tenant=' . urlencode($tenantSlug) . '&success=1');
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
        background: var(--bg-card);
        border-radius: 20px;
        padding: 30px;
        margin-bottom: 40px;
        border: none;
        box-shadow: var(--shadow-card);
    }

    .form-container h2 {
        color: var(--text-primary);
        margin-bottom: 20px;
        font-size: 1.3rem;
    }

    .form-group {
        margin-bottom: 25px;
    }

    label {
        display: block;
        margin-bottom: 8px;
        color: var(--text-primary);
        font-weight: 500;
    }

    input[type="text"],
    input[type="url"],
    input[type="file"],
    select {
        width: 100%;
        padding: 12px;
        border: 1px solid var(--border-color);
        border-radius: 10px;
        background: var(--bg-body);
        color: var(--text-primary);
        box-sizing: border-box;
        transition: all 0.3s ease;
    }

    input:focus,
    select:focus {
        outline: none;
        border-color: var(--primary);
        background: var(--bg-body);
    }

    .buttons {
        display: flex;
        gap: 15px;
        margin-top: 30px;
    }

    .buttons .btn {
        flex: 1;
        padding: 14px 28px;
        border-radius: 12px;
    }

    .banner-list {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .banner-item {
        display: flex;
        align-items: center;
        border: none;
        border-radius: 15px;
        padding: 20px;
        background: var(--bg-card);
        box-shadow: var(--shadow-card);
        transition: all 0.3s ease;
        cursor: grab;
    }

    .banner-item:active {
        cursor: grabbing;
    }

    .banner-item:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-card);
        border-color: var(--primary-border);
    }

    .banner-item.hidden {
        opacity: 0.5;
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

    .btn-icon-muted {
        opacity: 0.5;
    }

    .error {
        color: var(--danger);
        background: var(--danger-bg);
        padding: 12px;
        border-radius: 10px;
        margin-bottom: 20px;
    }

    .success {
        color: var(--success);
        background: var(--success-bg);
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
        background: var(--bg-overlay);
        backdrop-filter: blur(5px);
    }

    .modal-content {
        background: var(--bg-card);
        margin: 5% auto;
        padding: 30px;
        border: none;
        border-radius: 20px;
        width: 90%;
        max-width: 600px;
        max-height: 80vh;
        overflow-y: auto;
        box-shadow: var(--shadow-xl);
    }

    .modal-content h2 {
        margin: 0 0 25px 0;
        color: var(--primary);
        font-size: 1.8rem;
    }

    .close-modal {
        color: var(--text-muted);
        float: right;
        font-size: 32px;
        font-weight: bold;
        cursor: pointer;
    }

    .modal-form-group {
        margin-bottom: 20px;
    }

    .modal-form-group input,
    .modal-form-group select {
        width: 100%;
        padding: 12px 15px;
        background: var(--bg-body);
        border: 2px solid var(--border-color);
        border-radius: 10px;
        color: var(--text-primary);
        font-size: 1rem;
        box-sizing: border-box;
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
    }

    .modal-btn.primary {
        background: var(--primary-gradient);
        color: var(--text-inverse);
    }

    .modal-btn.primary:hover {
        background: var(--primary-gradient-hover);
        transform: translateY(-2px);
    }

    .modal-btn.secondary {
        background: var(--bg-body);
        color: var(--text-primary);
        border: 2px solid var(--border-color);
    }
</style>

<div class="container">
    <div class="header">
        <h1>画像管理</h1>
        <p>※対応拡張子：jpg, jpeg, png, gif, webp / アップロード制限：最大2MB</p>
    </div>

    <!-- タイトル設定フォーム -->
    <div class="form-container">
        <h2>セクション設定</h2>
        <form id="titleForm">
            <div class="form-group">
                <label for="adminTitle">管理名（必須）:</label>
                <input type="text" id="adminTitle" value="<?php echo h($section['admin_title']); ?>"
                    placeholder="管理画面で表示される名前" required>
            </div>
            <div class="form-group">
                <label for="titleEn">メインタイトル（任意）:</label>
                <input type="text" id="titleEn" value="<?php echo h($section['title_en']); ?>"
                    placeholder="例: About Our Shop">
            </div>
            <div class="form-group">
                <label for="titleJa">サブタイトル（任意）:</label>
                <input type="text" id="titleJa" value="<?php echo h($section['title_ja']); ?>" placeholder="例: お店紹介">
            </div>
            <div class="buttons">
                <button type="button" class="btn btn-secondary"
                    onclick="window.location.href='index.php?tenant=<?php echo urlencode($tenantSlug); ?>'">
                    <span class="material-icons">arrow_back</span>
                    戻る
                </button>
                <button type="button" class="btn btn-primary" onclick="saveTitles()">
                    <span class="material-icons">save</span>
                    タイトルを保存
                </button>
            </div>
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
                <div class="banner-upload-area" onclick="document.getElementById('banner_image').click()">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <div class="banner-upload-text">クリックして画像を選択</div>
                    <div class="banner-upload-subtext">またはドラッグ＆ドロップ (推奨: 幅600px以上)</div>
                    <div id="banner_image_name" style="margin-top: 10px; color: var(--accent); font-weight: bold;">
                    </div>
                </div>
                <input type="file" id="banner_image" name="banner_image" accept="image/*" required
                    style="display: none;" onchange="previewImage(this); updateFileName(this, 'banner_image_name')">
                <small style="color: var(--text-muted); display: block; margin-top: 5px;">
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
                <input type="text" id="alt_text" name="alt_text"
                    placeholder="例: <?php echo h($section['admin_title']); ?>">
            </div>
            <div class="buttons">
                <button type="button" class="btn btn-secondary"
                    onclick="window.location.href='index.php?tenant=<?php echo urlencode($tenantSlug); ?>'">
                    <span class="material-icons">arrow_back</span>
                    戻る
                </button>
                <button type="submit" name="register" class="btn btn-primary">
                    <span class="material-icons">save</span>
                    追加する
                </button>
            </div>
        </form>
    </div>

    <!-- バナー一覧 -->
    <div class="form-container">
        <h2>登録済み画像</h2>
        <div class="banner-list" id="bannerList">
            <?php if (count($banners) > 0): ?>
                <?php foreach ($banners as $banner): ?>
                    <div class="banner-item <?php echo $banner['is_visible'] ? '' : 'hidden'; ?>"
                        data-id="<?php echo $banner['id']; ?>">
                        <img src="<?php echo h($banner['image_path']); ?>" alt="<?php echo h($banner['alt_text']); ?>"
                            class="banner-img">
                        <div class="banner-info">
                            <?php if (!empty($banner['link_url'])): ?>
                                <div style="color: var(--text-primary);">
                                    リンク先: <a href="<?php echo h($banner['link_url']); ?>" target="_blank"
                                        style="color: var(--primary);"><?php echo h($banner['link_url']); ?></a>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($banner['alt_text'])): ?>
                                <div style="color: var(--text-muted); font-size: 0.9rem;">
                                    alt: <?php echo h($banner['alt_text']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="banner-actions">
                            <button onclick="toggleBannerVisibility(<?php echo $banner['id']; ?>, this)"
                                class="btn-icon <?php echo $banner['is_visible'] ? '' : 'btn-icon-muted'; ?>"
                                data-tooltip="<?php echo $banner['is_visible'] ? '表示中' : '非表示'; ?>">
                                <span class="material-icons"><?php echo $banner['is_visible'] ? 'visibility' : 'visibility_off'; ?></span>
                            </button>
                            <button class="btn-icon" data-tooltip="編集" onclick="editBanner(<?php echo $banner['id']; ?>)">
                                <span class="material-icons">edit</span>
                            </button>
                            <button class="btn-icon btn-icon-danger" data-tooltip="削除" onclick="deleteBanner(<?php echo $banner['id']; ?>)">
                                <span class="material-icons">delete</span>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: var(--text-muted);">
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
                <label style="color: var(--text-primary); margin-bottom: 8px; display: block;">リンク先URL:</label>
                <input type="url" id="editLinkUrl" placeholder="https://example.com">
            </div>
            <div class="modal-form-group">
                <label style="color: var(--text-primary); margin-bottom: 8px; display: block;">リンクの開き方:</label>
                <select id="editTarget">
                    <option value="_self">同じタブで開く (_self)</option>
                    <option value="_blank">新しいタブで開く (_blank)</option>
                </select>
            </div>
            <div class="modal-form-group">
                <label
                    style="display: flex; align-items: center; gap: 10px; cursor: pointer; color: var(--text-primary);">
                    <input type="checkbox" id="editNofollow" style="width: auto; cursor: pointer;">
                    <span>nofollow属性を付与（SEO評価を渡さない）</span>
                </label>
            </div>
            <div class="modal-form-group">
                <label
                    style="color: var(--text-primary); margin-bottom: 8px; display: block;">alt属性（画像の説明）:</label>
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
    const TENANT_SLUG = '<?php echo addslashes($tenantSlug); ?>';

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

        fetch('edit_title.php?tenant=' + TENANT_SLUG, {
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
            const maxFileSize = 2 * 1024 * 1024;
            if (input.files[0].size > maxFileSize) {
                alert('ファイルサイズを2MB以下にして下さい');
                input.value = '';
                preview.style.display = 'none';
                return;
            }

            const reader = new FileReader();
            reader.onload = function (e) {
                previewImg.src = e.target.result;
                preview.style.display = 'block';
            }
            reader.readAsDataURL(input.files[0]);
        } else {
            preview.style.display = 'none';
        }
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

    // Sortable.js初期化
    const bannerList = new Sortable(document.getElementById('bannerList'), {
        animation: 150,
        onEnd: function () {
            updateBannerOrder();
        }
    });

    // 順序更新
    function updateBannerOrder() {
        const items = Array.from(document.querySelectorAll('.banner-item'));
        const order = items.map(item => item.dataset.id);

        fetch('update_banner_order.php?tenant=' + TENANT_SLUG, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ order: order })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('並び替えを保存しました。');
                } else {
                    alert('順序の更新に失敗しました');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('順序の更新に失敗しました');
            });
    }

    // 表示/非表示切り替え
    function toggleBannerVisibility(id, button) {
        fetch('toggle_banner_visibility.php?tenant=' + TENANT_SLUG, {
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
                    const icon = button.querySelector('.material-icons');
                    if (data.is_visible) {
                        icon.textContent = 'visibility';
                        button.setAttribute('data-tooltip', '表示中');
                        button.classList.remove('btn-icon-muted');
                        item.classList.remove('hidden');
                    } else {
                        icon.textContent = 'visibility_off';
                        button.setAttribute('data-tooltip', '非表示');
                        button.classList.add('btn-icon-muted');
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
        fetch('get_banner.php?id=' + id + '&tenant=' + TENANT_SLUG)
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

        fetch('edit_banner.php?tenant=' + TENANT_SLUG, {
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

    window.addEventListener('click', function (e) {
        if (e.target === modal) {
            closeModal();
        }
    });

    // 削除
    function deleteBanner(id) {
        if (!confirm('本当にこのバナーを削除しますか？')) {
            return;
        }

        fetch('delete_banner.php?tenant=' + TENANT_SLUG, {
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