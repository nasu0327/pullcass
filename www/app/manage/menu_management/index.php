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
        gap: 10px;
        margin-top: 20px;
    }

    .menu-item-card {
        background: var(--bg-card);
        border: none;
        border-radius: 12px;
        padding: 14px 18px;
        cursor: grab;
        transition: box-shadow var(--transition-base);
        display: flex;
        flex-direction: column;
        gap: 8px;
        box-shadow: var(--shadow-card);
    }

    .menu-item-card:hover {
        box-shadow: var(--shadow-card-hover);
    }

    .menu-item-card:active {
        cursor: grabbing;
    }

    .menu-item-card.sortable-ghost {
        opacity: 0.4;
    }

    .menu-item-card.sortable-drag {
        opacity: 0.8;
        box-shadow: var(--shadow-lg);
    }

    .menu-item-card.inactive {
        opacity: 0.5;
    }

    .card-top-row {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .menu-item-info {
        flex: 1;
        min-width: 0;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .menu-item-code {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 8px;
        font-size: 0.7rem;
        font-weight: 600;
        background: var(--primary-bg);
        color: var(--primary);
        font-family: monospace;
        flex-shrink: 0;
    }

    .menu-item-label {
        font-size: 0.95rem;
        font-weight: 600;
        color: var(--text-primary);
        flex-shrink: 0;
    }

    .menu-item-url {
        font-size: 0.8rem;
        color: var(--text-muted);
        flex-shrink: 1;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .menu-item-meta {
        display: flex;
        gap: 6px;
        flex-shrink: 0;
    }

    .meta-badge {
        padding: 2px 6px;
        border-radius: 6px;
        font-size: 0.65rem;
        font-weight: 600;
        white-space: nowrap;
    }

    .meta-badge.internal {
        background: var(--success-bg);
        color: var(--success);
    }

    .meta-badge.external {
        background: var(--warning-bg);
        color: var(--warning);
    }

    .meta-badge.target-blank {
        background: var(--info-bg);
        color: var(--info);
    }

    .menu-item-actions {
        display: flex;
        gap: 8px;
        align-items: center;
        justify-content: flex-end;
    }

    .add-menu-btn {
        width: 100%;
        padding: 15px;
        background: var(--primary-bg);
        border: 2px dashed var(--primary-border);
        border-radius: 15px;
        color: var(--primary);
        font-size: 1rem;
        cursor: pointer;
        transition: all var(--transition-base);
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        margin-top: 20px;
    }

    .add-menu-btn:hover {
        background: var(--primary-bg-hover);
        border-color: var(--primary);
        transform: translateY(-2px);
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
        color: var(--text-primary);
    }

    .radio-group input[type="radio"] {
        width: 18px;
        height: 18px;
        accent-color: var(--primary);
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

<?php
require_once __DIR__ . '/../includes/breadcrumb.php';
$breadcrumbs = [
    ['label' => 'ダッシュボード', 'url' => '/app/manage/?tenant=' . $tenantSlug, 'icon' => 'fas fa-chart-pie'],
    ['label' => 'メニュー管理']
];
renderBreadcrumb($breadcrumbs);
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-bars"></i> <?php echo h($pageTitle); ?></h1>
        <p>ハンバーガーメニューの項目を管理します。ドラッグ&ドロップで並び替えができます。</p>
    </div>
</div>

<div id="alert-container"></div>

<div class="content-card">
    <h2><i class="fas fa-palette"></i> メニュー背景設定</h2>
    
    <button class="add-menu-btn" onclick="window.location.href='background_settings.php?tenant=<?php echo urlencode($tenantSlug); ?>'" style="margin-top: 0;">
        <i class="fas fa-palette"></i> メニュー背景を設定
    </button>
</div>

<div class="content-card">
    <h2><i class="fas fa-list"></i> メニュー項目一覧</h2>
    
    <div class="menu-list" id="menu-list">
        <?php foreach ($menuItems as $item): ?>
        <div class="menu-item-card <?php echo $item['is_active'] ? '' : 'inactive'; ?>" data-id="<?php echo $item['id']; ?>">
            <div class="card-top-row">
                <div class="menu-item-info">
                    <?php if ($item['code']): ?>
                    <div class="menu-item-code"><?php echo h($item['code']); ?></div>
                    <?php endif; ?>
                    <div class="menu-item-label"><?php echo h($item['label']); ?></div>
                    <div class="menu-item-url"><?php echo h($item['url']); ?></div>
                    <div class="menu-item-meta">
                        <span class="meta-badge <?php echo $item['link_type']; ?>">
                            <?php echo $item['link_type'] === 'internal' ? '内部' : '外部'; ?>
                        </span>
                        <?php if ($item['target'] === '_blank'): ?>
                        <span class="meta-badge target-blank">新規</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="menu-item-actions">
                <button class="visibility-toggle <?php echo $item['is_active'] ? '' : 'hidden'; ?>" 
                        onclick="toggleStatus(<?php echo $item['id']; ?>, this)" 
                        data-tooltip="<?php echo $item['is_active'] ? '非表示にする' : '表示する'; ?>">
                    <span class="material-icons"><?php echo $item['is_active'] ? 'visibility' : 'visibility_off'; ?></span>
                </button>
                <button class="btn-icon" data-tooltip="編集" onclick="editMenu(<?php echo htmlspecialchars(json_encode($item), ENT_QUOTES); ?>)">
                    <i class="fas fa-edit"></i>
                </button>
                <button class="btn-icon btn-icon-danger" data-tooltip="削除" onclick="deleteMenu(<?php echo $item['id']; ?>, '<?php echo h($item['label']); ?>')">
                    <i class="fas fa-trash"></i>
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
<div class="modal-overlay" id="menu-modal" onclick="if(event.target===this)closeModal()">
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
                <label class="form-label">表示タイトル <span style="color: var(--danger);">*</span></label>
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
                <label class="form-label">URL <span style="color: var(--danger);">*</span></label>
                <input type="text" class="form-control" id="menu-url" name="url" placeholder="例: /app/front/cast/list" required>
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
document.addEventListener('DOMContentLoaded', function() {
    const menuList = document.getElementById('menu-list');
    if (menuList && menuList.children.length > 0) {
        Sortable.create(menuList, {
            animation: 150,
            draggable: '.menu-item-card',
            filter: '.edit-title-btn, .delete-section-btn, .visibility-toggle',
            preventOnFilter: true,
            ghostClass: 'sortable-ghost',
            dragClass: 'sortable-drag',
            onEnd: function(evt) {
                saveOrder();
            }
        });
    }
});

// リンクタイプの変更でURL入力欄のプレースホルダーを変更
document.querySelectorAll('input[name="link_type"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const urlInput = document.getElementById('menu-url');
        const urlHint = document.getElementById('url-hint');
        if (this.value === 'internal') {
            urlInput.placeholder = '例: /app/front/cast/list';
            urlHint.textContent = '内部リンクは相対パス（例: /app/front/top）';
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
        urlInput.placeholder = '例: /app/front/cast/list';
        urlHint.textContent = '内部リンクは相対パス（例: /app/front/top）';
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
            alert(result.message);
            closeModal();
            setTimeout(() => location.reload(), 300);
        } else {
            alert('エラー: ' + result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('エラーが発生しました');
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
            alert(result.message);
            setTimeout(() => location.reload(), 300);
        } else {
            alert('エラー: ' + result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('エラーが発生しました');
    }
}

// ステータスを切り替え
async function toggleStatus(id, button) {
    try {
        const response = await fetch('toggle_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(result.message);
            const card = button.closest('.menu-item-card');
            const icon = button.querySelector('.material-icons');
            
            if (result.is_active) {
                button.classList.remove('hidden');
                card.classList.remove('inactive');
                icon.textContent = 'visibility';
                button.setAttribute('data-tooltip', '非表示にする');
            } else {
                button.classList.add('hidden');
                card.classList.add('inactive');
                icon.textContent = 'visibility_off';
                button.setAttribute('data-tooltip', '表示する');
            }
        } else {
            alert('エラー: ' + result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('エラーが発生しました');
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
            alert('並び替えを保存しました。');
        } else {
            alert('エラー: ' + result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('並び順の保存中にエラーが発生しました');
    }
}
</script>

</main>
</body>
</html>
