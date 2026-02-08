<?php
/**
 * メニュー管理画面
 * ハンバーガーメニューの項目を管理
 */

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/menu_functions.php';

// ログイン認証
requireTenantAdminLogin();

$pdo = getPlatformDb();
$pageTitle = 'メニュー管理';

// メニュー項目を取得
$menuItems = getMenuItems($pdo, $tenantId);

// デフォルトメニューが存在しない場合は作成
if (empty($menuItems)) {
    createDefaultMenuItems($pdo, $tenantId, $tenant['name']);
    $menuItems = getMenuItems($pdo, $tenantId);
}

// 共通ヘッダーを読み込む
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .menu-list {
        display: flex;
        flex-direction: column;
        gap: 15px;
        margin-top: 20px;
    }

    .menu-item-card {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid var(--border-color);
        border-radius: 15px;
        padding: 20px;
        cursor: grab;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .menu-item-card:active {
        cursor: grabbing;
    }

    .menu-item-card:hover {
        transform: translateY(-2px);
        border-color: rgba(39, 163, 235, 0.4);
        box-shadow: 0 8px 20px rgba(39, 163, 235, 0.2);
    }

    .menu-item-card.sortable-ghost {
        opacity: 0.4;
    }

    .menu-item-card.sortable-drag {
        opacity: 0.8;
        box-shadow: 0 10px 30px rgba(39, 163, 235, 0.4);
    }

    .menu-item-card.inactive {
        opacity: 0.5;
        background: rgba(255, 255, 255, 0.02);
    }

    .drag-handle {
        font-size: 1.5rem;
        color: var(--text-muted);
        cursor: grab;
    }

    .drag-handle:active {
        cursor: grabbing;
    }

    .menu-item-info {
        flex: 1;
        min-width: 0;
    }

    .menu-item-code {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: 600;
        background: rgba(156, 39, 176, 0.2);
        color: #9C27B0;
        font-family: monospace;
        margin-bottom: 5px;
    }

    .menu-item-label {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text-light);
        margin-bottom: 5px;
    }

    .menu-item-url {
        font-size: 0.9rem;
        color: var(--text-muted);
        word-break: break-all;
    }

    .menu-item-meta {
        display: flex;
        gap: 10px;
        margin-top: 8px;
    }

    .meta-badge {
        padding: 3px 8px;
        border-radius: 10px;
        font-size: 0.7rem;
        font-weight: 600;
    }

    .meta-badge.internal {
        background: rgba(76, 175, 80, 0.2);
        color: #4CAF50;
    }

    .meta-badge.external {
        background: rgba(255, 152, 0, 0.2);
        color: #FF9800;
    }

    .meta-badge.target-blank {
        background: rgba(33, 150, 243, 0.2);
        color: #2196F3;
    }

    .menu-item-actions {
        display: flex;
        gap: 10px;
        align-items: center;
    }

    .visibility-toggle {
        background: none;
        border: none;
        cursor: pointer;
        font-size: 1.8rem;
        padding: 5px;
        transition: all 0.3s ease;
    }

    .visibility-toggle.active {
        color: #4CAF50;
    }

    .visibility-toggle.inactive {
        color: rgba(255, 255, 255, 0.3);
    }

    .visibility-toggle:hover {
        transform: scale(1.2);
    }

    .add-menu-btn {
        width: 100%;
        padding: 15px;
        background: rgba(39, 163, 235, 0.1);
        border: 2px dashed rgba(39, 163, 235, 0.4);
        border-radius: 15px;
        color: #27a3eb;
        font-size: 1rem;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        margin-top: 20px;
    }

    .add-menu-btn:hover {
        background: rgba(39, 163, 235, 0.2);
        border-color: #27a3eb;
        transform: translateY(-2px);
    }

    /* モーダル */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(5px);
        z-index: 10000;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .modal-overlay.active {
        display: flex;
    }

    .modal-content {
        background: linear-gradient(135deg, #1e1e2e 0%, #2d2d44 100%);
        border-radius: 20px;
        padding: 30px;
        max-width: 600px;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
        border: 1px solid rgba(255, 255, 255, 0.1);
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
    }

    .modal-header {
        font-size: 1.5rem;
        font-weight: bold;
        color: #fff;
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-close-btn {
        background: none;
        border: none;
        color: var(--text-muted);
        font-size: 24px;
        cursor: pointer;
        transition: color 0.3s;
    }

    .modal-close-btn:hover {
        color: var(--danger);
    }

    .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 25px;
        padding-top: 20px;
        border-top: 1px solid var(--border-color);
    }

    .radio-group {
        display: flex;
        gap: 20px;
        margin-top: 8px;
    }

    .radio-group label {
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        color: var(--text-light);
    }

    .radio-group input[type="radio"] {
        width: 18px;
        height: 18px;
        accent-color: var(--accent);
    }

    @media (max-width: 768px) {
        .menu-item-card {
            flex-direction: column;
            align-items: flex-start;
        }

        .menu-item-actions {
            width: 100%;
            justify-content: flex-end;
        }
    }
</style>

<div class="page-header">
    <h1><i class="fas fa-bars"></i> <?php echo h($pageTitle); ?></h1>
    <p>ハンバーガーメニューの項目を管理します。ドラッグ&ドロップで並び替えができます。</p>
</div>

<?php
require_once __DIR__ . '/../includes/breadcrumb.php';
$breadcrumbs = [
    ['label' => 'ダッシュボード', 'url' => '/app/manage/?tenant=' . $tenantSlug, 'icon' => 'fas fa-chart-pie'],
    ['label' => 'メニュー管理']
];
renderBreadcrumb($breadcrumbs);
?>

<div id="alert-container"></div>

<div class="content-card">
    <h2><i class="fas fa-list"></i> メニュー項目一覧</h2>
    
    <div class="menu-list" id="menu-list">
        <?php foreach ($menuItems as $item): ?>
        <div class="menu-item-card <?php echo $item['is_active'] ? '' : 'inactive'; ?>" data-id="<?php echo $item['id']; ?>">
            <i class="fas fa-grip-vertical drag-handle"></i>
            
            <div class="menu-item-info">
                <?php if ($item['code']): ?>
                <div class="menu-item-code"><?php echo h($item['code']); ?></div>
                <?php endif; ?>
                <div class="menu-item-label"><?php echo h($item['label']); ?></div>
                <div class="menu-item-url"><?php echo h($item['url']); ?></div>
                <div class="menu-item-meta">
                    <span class="meta-badge <?php echo $item['link_type']; ?>">
                        <?php echo $item['link_type'] === 'internal' ? '内部リンク' : '外部リンク'; ?>
                    </span>
                    <?php if ($item['target'] === '_blank'): ?>
                    <span class="meta-badge target-blank">新しいタブ</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="menu-item-actions">
                <button class="visibility-toggle <?php echo $item['is_active'] ? 'active' : 'inactive'; ?>" 
                        onclick="toggleStatus(<?php echo $item['id']; ?>)" 
                        title="<?php echo $item['is_active'] ? '表示中' : '非表示'; ?>">
                    <i class="fas <?php echo $item['is_active'] ? 'fa-eye' : 'fa-eye-slash'; ?>"></i>
                </button>
                <button class="btn btn-sm edit-title-btn" onclick="editMenu(<?php echo htmlspecialchars(json_encode($item), ENT_QUOTES); ?>)">
                    <i class="fas fa-edit"></i> 編集
                </button>
                <button class="btn btn-sm delete-section-btn" onclick="deleteMenu(<?php echo $item['id']; ?>, '<?php echo h($item['label']); ?>')">
                    <i class="fas fa-trash"></i> 削除
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <button class="add-menu-btn" onclick="openAddMenu()">
        <i class="fas fa-plus"></i> 新しいメニューを追加
    </button>
</div>

<!-- メニュー追加/編集モーダル -->
<div class="modal-overlay" id="menu-modal">
    <div class="modal-content">
        <div class="modal-header">
            <span id="modal-title">メニューを追加</span>
            <button class="modal-close-btn" onclick="closeModal()">&times;</button>
        </div>
        
        <form id="menu-form">
            <input type="hidden" id="menu-id" name="id">
            
            <div class="form-group">
                <label class="form-label">コード（任意）</label>
                <input type="text" class="form-control" id="menu-code" name="code" placeholder="例: HOME, CAST">
                <p class="help-text">メニュー項目の識別子（英数字推奨）</p>
            </div>
            
            <div class="form-group">
                <label class="form-label">表示タイトル <span style="color: #f44336;">*</span></label>
                <input type="text" class="form-control" id="menu-label" name="label" placeholder="例: キャスト一覧" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">リンクタイプ</label>
                <div class="radio-group">
                    <label>
                        <input type="radio" name="link_type" value="internal" checked>
                        内部リンク（相対パス）
                    </label>
                    <label>
                        <input type="radio" name="link_type" value="external">
                        外部リンク（完全URL）
                    </label>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">URL <span style="color: #f44336;">*</span></label>
                <input type="text" class="form-control" id="menu-url" name="url" placeholder="例: /app/front/cast/list.php" required>
                <p class="help-text" id="url-hint">内部リンクは相対パス、外部リンクは完全URLを入力</p>
            </div>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" id="menu-target" name="target" value="_blank">
                    新しいタブで開く
                </label>
            </div>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" id="menu-active" name="is_active" value="1" checked>
                    有効にする
                </label>
            </div>
        </form>
        
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal()">キャンセル</button>
            <button class="btn btn-primary" onclick="saveMenu()">
                <i class="fas fa-save"></i> 保存
            </button>
        </div>
    </div>
</div>

<script>
const tenantSlug = <?php echo json_encode($tenantSlug); ?>;

// SortableJSで並び替え機能を初期化
const menuList = document.getElementById('menu-list');
if (menuList) {
    new Sortable(menuList, {
        animation: 150,
        handle: '.drag-handle',
        ghostClass: 'sortable-ghost',
        dragClass: 'sortable-drag',
        onEnd: function(evt) {
            saveOrder();
        }
    });
}

// リンクタイプの変更でURL入力欄のプレースホルダーを変更
document.querySelectorAll('input[name="link_type"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const urlInput = document.getElementById('menu-url');
        const urlHint = document.getElementById('url-hint');
        if (this.value === 'internal') {
            urlInput.placeholder = '例: /app/front/cast/list.php';
            urlHint.textContent = '内部リンクは相対パス（例: /app/front/top.php）';
        } else {
            urlInput.placeholder = '例: https://example.com';
            urlHint.textContent = '外部リンクは完全URL（例: https://example.com）';
        }
    });
});

// メニュー追加モーダルを開く
function openAddMenu() {
    document.getElementById('modal-title').textContent = 'メニューを追加';
    document.getElementById('menu-form').reset();
    document.getElementById('menu-id').value = '';
    document.getElementById('menu-active').checked = true;
    document.getElementById('menu-modal').classList.add('active');
}

// メニュー編集モーダルを開く
function editMenu(item) {
    document.getElementById('modal-title').textContent = 'メニューを編集';
    document.getElementById('menu-id').value = item.id;
    document.getElementById('menu-code').value = item.code || '';
    document.getElementById('menu-label').value = item.label;
    document.getElementById('menu-url').value = item.url;
    document.querySelector(`input[name="link_type"][value="${item.link_type}"]`).checked = true;
    document.getElementById('menu-target').checked = item.target === '_blank';
    document.getElementById('menu-active').checked = item.is_active == 1;
    document.getElementById('menu-modal').classList.add('active');
    
    // URL入力欄のプレースホルダーを更新
    const urlInput = document.getElementById('menu-url');
    const urlHint = document.getElementById('url-hint');
    if (item.link_type === 'internal') {
        urlInput.placeholder = '例: /app/front/cast/list.php';
        urlHint.textContent = '内部リンクは相対パス（例: /app/front/top.php）';
    } else {
        urlInput.placeholder = '例: https://example.com';
        urlHint.textContent = '外部リンクは完全URL（例: https://example.com）';
    }
}

// モーダルを閉じる
function closeModal() {
    document.getElementById('menu-modal').classList.remove('active');
}

// メニューを保存
async function saveMenu() {
    const form = document.getElementById('menu-form');
    const formData = new FormData(form);
    
    const data = {
        id: formData.get('id') || null,
        code: formData.get('code') || null,
        label: formData.get('label'),
        link_type: formData.get('link_type'),
        url: formData.get('url'),
        target: formData.get('target') ? '_blank' : '_self',
        is_active: formData.get('is_active') ? 1 : 0
    };
    
    try {
        const response = await fetch('save_menu.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showAlert(result.message, 'success');
            closeModal();
            setTimeout(() => location.reload(), 800);
        } else {
            showAlert(result.message, 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('エラーが発生しました', 'error');
    }
}

// メニューを削除
async function deleteMenu(id, label) {
    if (!confirm(`「${label}」を削除してもよろしいですか？`)) {
        return;
    }
    
    try {
        const response = await fetch('delete_menu.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showAlert(result.message, 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            showAlert(result.message, 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('エラーが発生しました', 'error');
    }
}

// ステータスを切り替え
async function toggleStatus(id) {
    try {
        const response = await fetch('toggle_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showAlert(result.message, 'success');
            setTimeout(() => location.reload(), 800);
        } else {
            showAlert(result.message, 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('エラーが発生しました', 'error');
    }
}

// 並び順を保存
async function saveOrder() {
    const items = document.querySelectorAll('.menu-item-card');
    const orders = Array.from(items).map((item, index) => ({
        id: parseInt(item.dataset.id),
        order_num: index + 1
    }));
    
    try {
        const response = await fetch('save_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ orders: orders })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showAlert(result.message, 'success');
        } else {
            showAlert(result.message, 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showAlert('エラーが発生しました', 'error');
    }
}

// アラート表示
function showAlert(message, type) {
    const alertContainer = document.getElementById('alert-container');
    const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
    const iconClass = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
    
    const alert = document.createElement('div');
    alert.className = `alert ${alertClass}`;
    alert.innerHTML = `<i class="fas ${iconClass}"></i> ${message}`;
    
    alertContainer.appendChild(alert);
    
    setTimeout(() => {
        alert.remove();
    }, 5000);
}
</script>

</main>
</body>
</html>
