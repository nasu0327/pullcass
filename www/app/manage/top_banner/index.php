<?php
/**
 * 店舗管理画面 - トップバナー管理
 */

require_once __DIR__ . '/../../../includes/bootstrap.php';

// テナント認証（HTML出力なし）
require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

$pdo = getPlatformDb();
$error = '';
$success = '';

// 新規登録処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $pc_url = trim($_POST['pc_url'] ?? '');
    $sp_url = trim($_POST['sp_url'] ?? '');
    $alt_text = trim($_POST['alt_text'] ?? '');

    if (empty($pc_url) || empty($sp_url)) {
        $error = 'PCリンクURLとSPリンクURLは必須です。';
    } else {
        $pc_image_path = '';
        $sp_image_path = '';

        // アップロードディレクトリ
        $upload_dir = __DIR__ . '/../../../uploads/tenants/' . $tenantId . '/banners/';
        $web_path = '/uploads/tenants/' . $tenantId . '/banners/';

        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // PC画像のアップロード
        if (isset($_FILES['pc_image']) && $_FILES['pc_image']['error'] === UPLOAD_ERR_OK) {
            $file_extension = strtolower(pathinfo($_FILES['pc_image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (in_array($file_extension, $allowed)) {
                $new_filename = 'pc_' . uniqid() . '.' . $file_extension;
                if (move_uploaded_file($_FILES['pc_image']['tmp_name'], $upload_dir . $new_filename)) {
                    $pc_image_path = $web_path . $new_filename;
                }
            }
        }

        // SP画像のアップロード
        if (isset($_FILES['sp_image']) && $_FILES['sp_image']['error'] === UPLOAD_ERR_OK) {
            $file_extension = strtolower(pathinfo($_FILES['sp_image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (in_array($file_extension, $allowed)) {
                $new_filename = 'sp_' . uniqid() . '.' . $file_extension;
                if (move_uploaded_file($_FILES['sp_image']['tmp_name'], $upload_dir . $new_filename)) {
                    $sp_image_path = $web_path . $new_filename;
                }
            }
        }

        if ($pc_image_path && $sp_image_path) {
            try {
                // 既存バナーの表示順を+1
                $pdo->prepare("UPDATE top_banners SET display_order = display_order + 1 WHERE tenant_id = ?")->execute([$tenantId]);

                // 新規バナーを登録
                $stmt = $pdo->prepare("
                    INSERT INTO top_banners (tenant_id, pc_image, sp_image, pc_url, sp_url, alt_text, display_order) 
                    VALUES (?, ?, ?, ?, ?, ?, 0)
                ");
                $stmt->execute([$tenantId, $pc_image_path, $sp_image_path, $pc_url, $sp_url, $alt_text]);

                header('Location: index?tenant=' . urlencode($tenantSlug) . '&success=1');
                exit;
            } catch (PDOException $e) {
                $error = 'データベースへの保存に失敗しました。';
                // アップロードした画像を削除
                if ($pc_image_path)
                    @unlink($upload_dir . basename($pc_image_path));
                if ($sp_image_path)
                    @unlink($upload_dir . basename($sp_image_path));
            }
        } else {
            $error = 'PC画像とSP画像の両方をアップロードしてください。';
        }
    }
}

// 更新処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $id = (int) ($_POST['id'] ?? 0);
    $pc_url = trim($_POST['pc_url'] ?? '');
    $sp_url = trim($_POST['sp_url'] ?? '');
    $alt_text = trim($_POST['alt_text'] ?? '');

    if ($id && $pc_url && $sp_url) {
        try {
            $stmt = $pdo->prepare("UPDATE top_banners SET pc_url = ?, sp_url = ?, alt_text = ? WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$pc_url, $sp_url, $alt_text, $id, $tenantId]);
            header('Location: index?tenant=' . urlencode($tenantSlug) . '&success=2');
            exit;
        } catch (PDOException $e) {
            $error = '更新に失敗しました。';
        }
    }
}

// 成功メッセージ
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case '1':
            $success = 'バナーを登録しました。';
            break;
        case '2':
            $success = 'バナーを更新しました。';
            break;
        case '3':
            $success = 'バナーを削除しました。';
            break;
    }
}

// バナー一覧の取得
$stmt = $pdo->prepare("SELECT * FROM top_banners WHERE tenant_id = ? ORDER BY display_order ASC");
$stmt->execute([$tenantId]);
$banners = $stmt->fetchAll();

// ヘッダー読み込み（HTML出力開始）
$pageTitle = 'トップバナー管理';
require_once __DIR__ . '/../includes/header.php';
?>

<?php
require_once __DIR__ . '/../includes/breadcrumb.php';
$breadcrumbs = [
    ['label' => 'ダッシュボード', 'url' => '/app/manage/?tenant=' . $tenantSlug, 'icon' => 'fas fa-chart-pie'],
    ['label' => 'トップバナー管理']
];
renderBreadcrumb($breadcrumbs);
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-images"></i> トップバナー管理</h1>
        <p>トップページのスライドバナーを管理します</p>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i> <?php echo h($error); ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?php echo h($success); ?>
    </div>
<?php endif; ?>

<div class="form-container">
    <h2><i class="fas fa-plus-circle"></i> 新規バナー登録</h2>

    <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label>PC画像 <span style="color: var(--danger);">*</span></label>
            <div class="banner-upload-area" onclick="document.getElementById('pc_image').click()">
                <i class="fas fa-cloud-upload-alt"></i>
                <div class="banner-upload-text">クリックして画像を選択</div>
                <div class="banner-upload-subtext">またはドラッグ＆ドロップ (推奨: 1200x400px)</div>
                <div id="pc_image_name" style="margin-top: 10px; color: var(--accent); font-weight: bold;"></div>
            </div>
            <input type="file" id="pc_image" name="pc_image" accept="image/*" required style="display: none;"
                onchange="previewImage(this, 'pcPreview'); updateFileName(this, 'pc_image_name')">
            <div id="pcPreview" class="image-preview" style="display: none;">
                <img src="" alt="PCプレビュー">
            </div>
        </div>

        <div class="form-group">
            <label for="pc_url">PCリンクURL <span style="color: var(--danger);">*</span></label>
            <input type="url" id="pc_url" name="pc_url" class="form-control" required placeholder="https://example.com">
        </div>

        <div class="form-group">
            <label>SP画像 <span style="color: var(--danger);">*</span></label>
            <div class="banner-upload-area" onclick="document.getElementById('sp_image').click()">
                <i class="fas fa-cloud-upload-alt"></i>
                <div class="banner-upload-text">クリックして画像を選択</div>
                <div class="banner-upload-subtext">またはドラッグ＆ドロップ (推奨: 750x400px)</div>
                <div id="sp_image_name" style="margin-top: 10px; color: var(--accent); font-weight: bold;"></div>
            </div>
            <input type="file" id="sp_image" name="sp_image" accept="image/*" required style="display: none;"
                onchange="previewImage(this, 'spPreview'); updateFileName(this, 'sp_image_name')">
            <div id="spPreview" class="image-preview" style="display: none;">
                <img src="" alt="SPプレビュー">
            </div>
        </div>

        <div class="form-group">
            <label for="sp_url">SPリンクURL <span style="color: var(--danger);">*</span></label>
            <input type="url" id="sp_url" name="sp_url" class="form-control" required placeholder="https://example.com">
        </div>

        <div class="form-group">
            <label for="alt_text">alt属性（画像の説明）</label>
            <input type="text" id="alt_text" name="alt_text" class="form-control" placeholder="例: 新人入店キャンペーン">
            <p class="form-help">SEO・アクセシビリティのための画像説明文です。</p>
        </div>

        <button type="submit" name="register" class="btn btn-primary">
            <i class="fas fa-plus"></i> 登録する
        </button>
    </form>
</div>

<div class="form-container">
    <h2><i class="fas fa-list"></i> 登録済みバナー一覧</h2>
    <p class="form-help" style="margin-bottom: 20px;">ドラッグ&ドロップで並び替えができます。</p>

    <?php if (count($banners) > 0): ?>
        <div class="item-list" id="bannerList">
            <?php foreach ($banners as $banner): ?>
                <div class="list-item <?php echo $banner['is_visible'] ? '' : 'opacity-50'; ?>"
                    data-id="<?php echo $banner['id']; ?>">
                    <div class="list-item-image" style="display: flex; gap: 10px;">
                        <img src="<?php echo h($banner['pc_image']); ?>" alt="PC" title="PC画像">
                        <img src="<?php echo h($banner['sp_image']); ?>" alt="SP" title="SP画像">
                    </div>

                    <div class="list-item-info">
                        <div style="margin-bottom: 5px;">
                            <strong>PC:</strong> <a href="<?php echo h($banner['pc_url']); ?>"
                                target="_blank"><?php echo h(mb_strimwidth($banner['pc_url'], 0, 50, '...')); ?></a>
                        </div>
                        <div style="margin-bottom: 5px;">
                            <strong>SP:</strong> <a href="<?php echo h($banner['sp_url']); ?>"
                                target="_blank"><?php echo h(mb_strimwidth($banner['sp_url'], 0, 50, '...')); ?></a>
                        </div>
                        <?php if ($banner['alt_text']): ?>
                            <div style="color: var(--text-muted); font-size: 0.85rem;">
                                alt: <?php echo h($banner['alt_text']); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="list-item-actions">
                        <button type="button" class="visibility-toggle <?php echo $banner['is_visible'] ? '' : 'hidden'; ?>"
                            onclick="toggleVisibility(<?php echo $banner['id']; ?>, this)"
                            data-tooltip="<?php echo $banner['is_visible'] ? '非表示にする' : '表示する'; ?>">
                            <span class="material-icons"><?php echo $banner['is_visible'] ? 'visibility' : 'visibility_off'; ?></span>
                        </button>
                        <button type="button" class="btn-icon" data-tooltip="編集" onclick="openEditModal(<?php echo $banner['id']; ?>)">
                            <i class="fas fa-edit"></i>
                        </button>
                        <a href="delete?id=<?php echo $banner['id']; ?>&tenant=<?php echo h($tenantSlug); ?>"
                            class="btn-icon btn-icon-danger" data-tooltip="削除" onclick="return confirm('本当に削除しますか？');">
                            <i class="fas fa-trash"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-images"></i>
            <p>登録されているバナーはありません。</p>
        </div>
    <?php endif; ?>
</div>

<!-- 編集モーダル -->
<div id="editModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h2>バナー編集</h2>
            <button type="button" class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form id="editForm" method="POST">
            <input type="hidden" name="id" id="editId">
            <input type="hidden" name="update" value="1">

            <div class="form-group">
                <label for="editPcUrl">PCリンクURL</label>
                <input type="url" id="editPcUrl" name="pc_url" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="editSpUrl">SPリンクURL</label>
                <input type="url" id="editSpUrl" name="sp_url" class="form-control" required>
            </div>

            <div class="form-group">
                <label for="editAltText">alt属性</label>
                <input type="text" id="editAltText" name="alt_text" class="form-control">
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">キャンセル</button>
                <button type="submit" class="btn btn-primary">更新</button>
            </div>
        </form>
    </div>
</div>

<script>
    // 画像プレビュー
    function previewImage(input, previewId) {
        const preview = document.getElementById(previewId);
        const previewImg = preview.querySelector('img');

        if (input.files && input.files[0]) {
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

    // 編集モーダル
    function openEditModal(id) {
        fetch('get_banner?id=' + id + '&tenant=<?php echo urlencode($tenantSlug); ?>')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('editId').value = data.banner.id;
                    document.getElementById('editPcUrl').value = data.banner.pc_url;
                    document.getElementById('editSpUrl').value = data.banner.sp_url;
                    document.getElementById('editAltText').value = data.banner.alt_text || '';
                    document.getElementById('editModal').classList.add('active');
                } else {
                    alert('バナー情報の取得に失敗しました。');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('バナー情報の取得に失敗しました。');
            });
    }

    function closeModal() {
        document.getElementById('editModal').classList.remove('active');
    }

    // モーダル外クリックで閉じる
    document.getElementById('editModal').addEventListener('click', function (e) {
        if (e.target === this) closeModal();
    });

    // 表示/非表示切り替え
    function toggleVisibility(id, button) {
        fetch('toggle_visibility', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, tenant: '<?php echo $tenantSlug; ?>' })
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const listItem = button.closest('.list-item');
                    const icon = button.querySelector('.material-icons');
                    if (data.is_visible) {
                        icon.textContent = 'visibility';
                        button.classList.remove('hidden');
                        button.setAttribute('data-tooltip', '非表示にする');
                        listItem.classList.remove('opacity-50');
                    } else {
                        icon.textContent = 'visibility_off';
                        button.classList.add('hidden');
                        button.setAttribute('data-tooltip', '表示する');
                        listItem.classList.add('opacity-50');
                    }
                } else {
                    alert('更新に失敗しました。');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('更新に失敗しました。');
            });
    }

    // ドラッグ&ドロップ並び替え
    document.addEventListener('DOMContentLoaded', function () {
        const bannerList = document.getElementById('bannerList');

        if (bannerList && bannerList.children.length > 0) {
            Sortable.create(bannerList, {
                animation: 150,
                draggable: '.list-item',
                filter: '.visibility-toggle, .edit-title-btn, .delete-section-btn',
                preventOnFilter: true,
                ghostClass: 'sortable-ghost',
                dragClass: 'sortable-drag',
                onEnd: function (evt) {
                    const items = [...bannerList.querySelectorAll('.list-item')];
                    const newOrder = items.map(item => item.dataset.id);

                    fetch('update_order', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ order: newOrder, tenant: '<?php echo $tenantSlug; ?>' })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                alert('並び替えを保存しました。');
                            } else {
                                alert('並び替えに失敗しました。');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('並び替えに失敗しました。');
                        });
                }
            });
        }
    });
</script>

<style>
    /* ドラッグ時のスタイル（manage.css共通を上書き不要、追加のみ） */
    .list-item.sortable-drag {
        box-shadow: var(--shadow-lg);
    }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>