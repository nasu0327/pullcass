<?php
/**
 * 店舗管理画面 - 相互リンク管理
 */

require_once __DIR__ . '/../../../includes/bootstrap.php';

// テナント認証（HTML出力なし）
require_once __DIR__ . '/../includes/auth.php';

$pdo = getPlatformDb();
$error = '';
$success = '';

// 画像バナー新規登録処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $alt_text = trim($_POST['alt_text'] ?? '');
    $link_url = trim($_POST['link_url'] ?? '');
    $nofollow = isset($_POST['nofollow']) ? 1 : 0;
    
    if (empty($alt_text) || empty($link_url)) {
        $error = 'ALTテキストとリンクURLは必須です。';
    } else {
        $banner_image_path = '';
        
        // アップロードディレクトリ
        $upload_dir = __DIR__ . '/../../../uploads/tenants/' . $tenantId . '/reciprocal/';
        $web_path = '/uploads/tenants/' . $tenantId . '/reciprocal/';
        
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // 画像のアップロード
        if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] === UPLOAD_ERR_OK) {
            $file_extension = strtolower(pathinfo($_FILES['banner_image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($file_extension, $allowed)) {
                $new_filename = uniqid() . '.' . $file_extension;
                if (move_uploaded_file($_FILES['banner_image']['tmp_name'], $upload_dir . $new_filename)) {
                    $banner_image_path = $web_path . $new_filename;
                }
            }
        }
        
        if ($banner_image_path) {
            try {
                // 既存リンクの表示順を+1
                $pdo->prepare("UPDATE reciprocal_links SET display_order = display_order + 1 WHERE tenant_id = ?")->execute([$tenantId]);
                
                // 新規リンクを登録
                $stmt = $pdo->prepare("
                    INSERT INTO reciprocal_links (tenant_id, banner_image, alt_text, link_url, nofollow, display_order) 
                    VALUES (?, ?, ?, ?, ?, 0)
                ");
                $stmt->execute([$tenantId, $banner_image_path, $alt_text, $link_url, $nofollow]);
                
                header('Location: index.php?tenant=' . urlencode($tenantSlug) . '&success=1');
                exit;
            } catch (PDOException $e) {
                $error = 'データベースへの保存に失敗しました。';
                if ($banner_image_path) @unlink($upload_dir . basename($banner_image_path));
            }
        } else {
            $error = '画像をアップロードしてください。';
        }
    }
}

// カスタムコード新規登録処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_code'])) {
    $code_name = trim($_POST['code_name'] ?? '');
    $custom_code = trim($_POST['custom_code'] ?? '');
    
    if (empty($custom_code)) {
        $error = 'コードを入力してください。';
    } else {
        try {
            // 既存リンクの表示順を+1
            $pdo->prepare("UPDATE reciprocal_links SET display_order = display_order + 1 WHERE tenant_id = ?")->execute([$tenantId]);
            
            // 新規コードを登録
            $stmt = $pdo->prepare("
                INSERT INTO reciprocal_links (tenant_id, alt_text, custom_code, display_order) 
                VALUES (?, ?, ?, 0)
            ");
            $stmt->execute([$tenantId, $code_name, $custom_code]);
            
            header('Location: index.php?tenant=' . urlencode($tenantSlug) . '&success=2');
            exit;
        } catch (PDOException $e) {
            $error = 'データベースへの保存に失敗しました。';
        }
    }
}

// 成功メッセージ
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case '1': $success = '画像バナーを登録しました。'; break;
        case '2': $success = 'カスタムコードを登録しました。'; break;
        case '3': $success = '相互リンクを削除しました。'; break;
        case '4': $success = '更新しました。'; break;
    }
}

// リンク一覧の取得
$stmt = $pdo->prepare("SELECT * FROM reciprocal_links WHERE tenant_id = ? ORDER BY display_order ASC");
$stmt->execute([$tenantId]);
$links = $stmt->fetchAll();

// ヘッダー読み込み（HTML出力開始）
$pageTitle = '相互リンク管理';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-link"></i> 相互リンク管理</h1>
        <p>相互リンクの登録・編集・削除を行います</p>
    </div>
    <a href="https://<?php echo h($tenant['code']); ?>.pullcass.com/" target="_blank" class="btn btn-secondary btn-sm">
        <i class="fas fa-external-link-alt"></i> サイトで確認
    </a>
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
    <h2><i class="fas fa-plus-circle"></i> 新規登録</h2>
    
    <!-- タブ切り替え -->
    <div class="tab-container">
        <button class="tab-btn active" onclick="switchTab('banner')">
            <i class="fas fa-image"></i> 画像バナー登録
        </button>
        <button class="tab-btn" onclick="switchTab('code')">
            <i class="fas fa-code"></i> コード貼り付け登録
        </button>
    </div>
    
    <!-- 画像バナー登録フォーム -->
    <div id="tab-banner" class="tab-content active">
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="banner_image">画像ファイル <span style="color: var(--danger);">*</span></label>
                <input type="file" id="banner_image" name="banner_image" class="form-control" accept="image/*" required onchange="previewImage(this, 'imagePreview')">
                <div id="imagePreview" class="image-preview" style="display: none;">
                    <img src="" alt="プレビュー">
                </div>
            </div>
            
            <div class="form-group">
                <label for="alt_text">ALTテキスト <span style="color: var(--danger);">*</span></label>
                <input type="text" id="alt_text" name="alt_text" class="form-control" required placeholder="バナーの説明文">
            </div>
            
            <div class="form-group">
                <label for="link_url">リンクURL <span style="color: var(--danger);">*</span></label>
                <input type="url" id="link_url" name="link_url" class="form-control" required placeholder="https://example.com">
            </div>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="nofollow" value="1" checked>
                    <span>nofollow属性を付与（SEO評価を渡さない）</span>
                </label>
                <p class="form-help">チェックを外すとリンク先にSEO評価が渡されます。</p>
            </div>
            
            <button type="submit" name="register" class="btn btn-primary">
                <i class="fas fa-plus"></i> 画像バナーを登録
            </button>
        </form>
    </div>
    
    <!-- カスタムコード登録フォーム -->
    <div id="tab-code" class="tab-content">
        <form method="POST">
            <div class="form-group">
                <label for="code_name">管理用名前（任意）</label>
                <input type="text" id="code_name" name="code_name" class="form-control" placeholder="例：○○サイト様バナー">
                <p class="form-help">管理画面での識別用です。空欄でも登録できます。</p>
            </div>
            
            <div class="form-group">
                <label for="custom_code">HTMLコード <span style="color: var(--danger);">*</span></label>
                <textarea id="custom_code" name="custom_code" class="form-control" rows="6" required 
                          placeholder="相互リンク先から指定されたHTMLコードを貼り付けてください。&#10;例: <a href=&quot;...&quot;><img src=&quot;...&quot; /></a>"
                          style="font-family: monospace;"></textarea>
                <p class="form-help">iframe、script、aタグなど、相互リンク先から指定されたコードをそのまま貼り付けてください。</p>
            </div>
            
            <button type="submit" name="register_code" class="btn btn-primary">
                <i class="fas fa-plus"></i> コードを登録
            </button>
        </form>
    </div>
</div>

<div class="form-container">
    <h2><i class="fas fa-list"></i> 登録済み相互リンク一覧</h2>
    <p class="form-help" style="margin-bottom: 20px;">ドラッグ&ドロップで並び替えができます。</p>
    
    <?php if (count($links) > 0): ?>
    <div class="item-list" id="linkList">
        <?php foreach ($links as $link): ?>
        <div class="list-item" data-id="<?php echo $link['id']; ?>">
            <div class="drag-handle">
                <i class="material-icons">drag_indicator</i>
            </div>
            
            <?php if (!empty($link['custom_code'])): ?>
            <!-- カスタムコード型 -->
            <div class="list-item-image" style="margin-left: 30px;">
                <div style="background: rgba(0,0,0,0.3); padding: 10px; border-radius: 8px; font-family: monospace; font-size: 11px; color: var(--text-muted); max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                    <i class="fas fa-code"></i> コード
                </div>
            </div>
            <div class="list-item-info">
                <div style="margin-bottom: 5px;">
                    <strong>名前:</strong> <?php echo h($link['alt_text'] ?: '（未設定）'); ?>
                </div>
                <div style="color: var(--text-muted); font-size: 0.85rem; font-family: monospace; background: rgba(0,0,0,0.2); padding: 8px; border-radius: 5px; max-height: 60px; overflow: hidden;">
                    <?php echo h(mb_strimwidth($link['custom_code'], 0, 100, '...')); ?>
                </div>
            </div>
            <?php else: ?>
            <!-- 画像バナー型 -->
            <div class="list-item-image" style="margin-left: 30px;">
                <img src="<?php echo h($link['banner_image']); ?>" alt="<?php echo h($link['alt_text']); ?>">
            </div>
            <div class="list-item-info">
                <div style="margin-bottom: 5px;">
                    <strong>ALT:</strong> <?php echo h($link['alt_text']); ?>
                </div>
                <div style="margin-bottom: 5px;">
                    <strong>URL:</strong> <a href="<?php echo h($link['link_url']); ?>" target="_blank"><?php echo h(mb_strimwidth($link['link_url'], 0, 50, '...')); ?></a>
                </div>
                <div style="color: var(--text-muted); font-size: 0.85rem;">
                    <?php echo $link['nofollow'] ? 'nofollow: ON' : 'nofollow: OFF'; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="list-item-actions">
                <button type="button" class="btn btn-accent btn-sm" 
                        onclick="openEditModal(<?php echo $link['id']; ?>, '<?php echo !empty($link['custom_code']) ? 'code' : 'banner'; ?>')">
                    <i class="fas fa-edit"></i> 編集
                </button>
                <a href="delete.php?id=<?php echo $link['id']; ?>&tenant=<?php echo h($tenantSlug); ?>" 
                   class="btn btn-danger btn-sm" 
                   onclick="return confirm('本当に削除しますか？');">
                    <i class="fas fa-trash"></i> 削除
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <i class="fas fa-link"></i>
        <p>登録されている相互リンクはありません。</p>
    </div>
    <?php endif; ?>
</div>

<!-- 編集モーダル -->
<div id="editModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">編集</h2>
            <button type="button" class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        
        <input type="hidden" id="editId">
        <input type="hidden" id="editType">
        
        <!-- 画像バナー用フォーム -->
        <div id="edit-banner-form">
            <div id="currentImageContainer" class="form-group">
                <label>現在の画像</label>
                <div class="image-preview" style="display: block;">
                    <img id="currentImage" src="" alt="現在の画像">
                </div>
                <p class="form-help">※画像の変更はできません。変更する場合は削除して再登録してください。</p>
            </div>
            
            <div class="form-group">
                <label for="edit_alt_text">ALTテキスト</label>
                <input type="text" id="edit_alt_text" class="form-control" placeholder="バナーの説明文">
            </div>
            
            <div class="form-group">
                <label for="edit_link_url">リンクURL</label>
                <input type="url" id="edit_link_url" class="form-control" placeholder="https://example.com">
            </div>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" id="edit_nofollow">
                    <span>nofollow属性を付与</span>
                </label>
            </div>
        </div>
        
        <!-- カスタムコード用フォーム -->
        <div id="edit-code-form" style="display: none;">
            <div class="form-group">
                <label for="edit_code_name">管理用名前（任意）</label>
                <input type="text" id="edit_code_name" class="form-control" placeholder="例：○○サイト様バナー">
            </div>
            
            <div class="form-group">
                <label for="edit_custom_code">HTMLコード</label>
                <textarea id="edit_custom_code" class="form-control" rows="6" style="font-family: monospace;"></textarea>
            </div>
        </div>
        
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal()">キャンセル</button>
            <button type="button" class="btn btn-primary" onclick="saveEdit()">更新</button>
        </div>
    </div>
</div>

<script>
// タブ切り替え
function switchTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    
    if (tab === 'banner') {
        document.querySelector('.tab-btn:first-child').classList.add('active');
        document.getElementById('tab-banner').classList.add('active');
    } else {
        document.querySelector('.tab-btn:last-child').classList.add('active');
        document.getElementById('tab-code').classList.add('active');
    }
}

// 画像プレビュー
function previewImage(input, previewId) {
    const preview = document.getElementById(previewId);
    const previewImg = preview.querySelector('img');
    
    if (input.files && input.files[0]) {
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

// 編集モーダル
function openEditModal(id, type) {
    const modal = document.getElementById('editModal');
    const bannerForm = document.getElementById('edit-banner-form');
    const codeForm = document.getElementById('edit-code-form');
    const modalTitle = document.getElementById('modalTitle');
    
    // フォームの表示切り替え
    if (type === 'code') {
        bannerForm.style.display = 'none';
        codeForm.style.display = 'block';
        modalTitle.textContent = 'カスタムコードを編集';
    } else {
        bannerForm.style.display = 'block';
        codeForm.style.display = 'none';
        modalTitle.textContent = '画像バナーを編集';
    }
    
    document.getElementById('editId').value = id;
    document.getElementById('editType').value = type;
    
    // データを取得
    fetch('get_link.php?id=' + id + '&tenant=<?php echo urlencode($tenantSlug); ?>')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const link = data.data;
                
                if (type === 'code') {
                    document.getElementById('edit_code_name').value = link.alt_text || '';
                    document.getElementById('edit_custom_code').value = link.custom_code || '';
                } else {
                    document.getElementById('edit_alt_text').value = link.alt_text || '';
                    document.getElementById('edit_link_url').value = link.link_url || '';
                    document.getElementById('edit_nofollow').checked = link.nofollow == 1;
                    
                    // 現在の画像を表示
                    const currentImage = document.getElementById('currentImage');
                    const currentImageContainer = document.getElementById('currentImageContainer');
                    if (link.banner_image) {
                        currentImage.src = link.banner_image;
                        currentImageContainer.style.display = 'block';
                    } else {
                        currentImageContainer.style.display = 'none';
                    }
                }
                
                modal.classList.add('active');
            } else {
                alert('データの取得に失敗しました: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('データの取得に失敗しました');
        });
}

function closeModal() {
    document.getElementById('editModal').classList.remove('active');
}

// 編集を保存
function saveEdit() {
    const id = document.getElementById('editId').value;
    const type = document.getElementById('editType').value;
    
    let data = { id: id, type: type, tenant: '<?php echo $tenantSlug; ?>' };
    
    if (type === 'code') {
        data.alt_text = document.getElementById('edit_code_name').value;
        data.custom_code = document.getElementById('edit_custom_code').value;
        
        if (!data.custom_code) {
            alert('コードを入力してください');
            return;
        }
    } else {
        data.alt_text = document.getElementById('edit_alt_text').value;
        data.link_url = document.getElementById('edit_link_url').value;
        data.nofollow = document.getElementById('edit_nofollow').checked ? 1 : 0;
        
        if (!data.alt_text || !data.link_url) {
            alert('すべての項目を入力してください');
            return;
        }
    }
    
    fetch('update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            closeModal();
            location.href = 'index.php?tenant=<?php echo urlencode($tenantSlug); ?>&success=4';
        } else {
            alert('更新に失敗しました: ' + result.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('更新に失敗しました');
    });
}

// モーダル外クリックで閉じる
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

// ドラッグ&ドロップ並び替え
document.addEventListener('DOMContentLoaded', function() {
    const linkList = document.getElementById('linkList');
    
    if (linkList && linkList.children.length > 0) {
        Sortable.create(linkList, {
            animation: 150,
            handle: '.drag-handle',
            ghostClass: 'dragging',
            onEnd: function(evt) {
                const items = [...linkList.querySelectorAll('.list-item')];
                const newOrder = items.map(item => item.dataset.id);
                
                fetch('update_order.php', {
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
