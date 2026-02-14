<?php
/**
 * フリーページ管理 - 一覧ページ
 */

// 認証チェック（$tenant, $tenantIdが自動設定される）
require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

require_once __DIR__ . '/../../../includes/free_page_helpers.php';

$pdo = getPlatformDb();

// ステータスフィルター
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';
$validStatuses = ['all', 'published', 'draft'];
if (!in_array($statusFilter, $validStatuses)) {
    $statusFilter = 'all';
}

// ページネーション
$limit = 20;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// データ取得
$pages = getAllFreePages($pdo, $tenantId, $statusFilter, $limit, $offset);
$totalCount = countFreePages($pdo, $tenantId, $statusFilter);
$totalPages = ceil($totalCount / $limit);

// 統計
$publishedCount = countFreePages($pdo, $tenantId, 'published');
$draftCount = countFreePages($pdo, $tenantId, 'draft');
$allCount = $publishedCount + $draftCount;

$shopName = $tenant['name'];
$pageTitle = 'フリーページ管理';

// 共通ヘッダーを読み込む
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .header-actions {
        display: flex;
        gap: 10px;
    }

    /* フィルタータブ */
    .filter-tabs {
        display: flex;
        gap: 10px;
        margin-bottom: 30px;
        flex-wrap: wrap;
    }

    .filter-tab {
        padding: 10px 20px;
        border-radius: 8px;
        background: var(--bg-tab);
        color: var(--text-secondary);
        text-decoration: none;
        font-size: 0.9rem;
        transition: all var(--transition-base);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .filter-tab:hover {
        background: var(--bg-tab-hover);
        color: var(--text-primary);
    }

    .filter-tab.active {
        background: var(--primary-gradient);
        color: var(--text-inverse);
    }

    .filter-tab.active:hover {
        background: var(--primary-gradient-hover);
        box-shadow: var(--shadow-primary);
    }

    .filter-tab .count {
        background: rgba(0, 0, 0, 0.15);
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 0.8rem;
    }

    /* ページ一覧 */
    .pages-list {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .page-card {
        background: var(--bg-card);
        border: none;
        border-radius: 12px;
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 20px;
        transition: all var(--transition-base);
        box-shadow: var(--shadow-card);
    }

    .page-card:hover {
        box-shadow: var(--shadow-card-hover);
    }

    .page-card {
        cursor: grab;
    }

    .page-card:active {
        cursor: grabbing;
    }

    .page-card .thumbnail {
        width: 80px;
        height: 60px;
        border-radius: 8px;
        object-fit: cover;
        background: var(--bg-hover);
        flex-shrink: 0;
    }

    .page-card .no-image {
        width: 80px;
        height: 60px;
        border-radius: 8px;
        background: var(--bg-hover);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-muted);
        flex-shrink: 0;
    }

    .page-card .info {
        flex: 1;
        min-width: 0;
    }

    .page-card .title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 5px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .page-card .slug {
        font-size: 0.85rem;
        color: var(--text-muted);
        margin-bottom: 8px;
    }

    .page-card .slug code {
        background: var(--bg-code);
        padding: 2px 8px;
        border-radius: 4px;
    }

    .page-card .meta {
        display: flex;
        gap: 15px;
        font-size: 0.85rem;
        color: var(--text-muted);
    }

    .page-card .status {
        padding: 3px 10px;
        border-radius: 15px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .page-card .status.published {
        background: var(--success-bg);
        color: var(--success);
    }

    .page-card .status.draft {
        background: var(--warning-bg);
        color: var(--warning);
    }

    .page-card .actions {
        display: flex;
        gap: 8px;
        flex-shrink: 0;
    }

    /* 空状態 */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--text-muted);
    }

    .empty-state i {
        font-size: 4rem;
        margin-bottom: 20px;
        opacity: 0.3;
    }

    .empty-state h3 {
        font-size: 1.2rem;
        margin-bottom: 10px;
        color: var(--text-secondary);
    }

    .empty-state p {
        margin-bottom: 20px;
    }

    /* ページネーション */
    .pagination {
        display: flex;
        justify-content: center;
        gap: 8px;
        margin-top: 30px;
    }

    .pagination a,
    .pagination span {
        padding: 10px 15px;
        border-radius: 8px;
        background: var(--bg-hover);
        color: var(--text-secondary);
        text-decoration: none;
        transition: all var(--transition-base);
    }

    .pagination a:hover {
        background: var(--bg-tab-hover);
        color: var(--text-primary);
    }

    .pagination .active {
        background: var(--primary-gradient);
        color: var(--text-inverse);
    }

    /* ソート中のスタイル */
    .sortable-ghost {
        opacity: 0.4;
    }

    .sortable-chosen {
        box-shadow: var(--shadow-lg);
    }

    /* 削除確認モーダル */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: var(--bg-overlay);
        backdrop-filter: blur(4px);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 1000;
    }

    .modal-overlay.active {
        display: flex;
    }

    .modal {
        background: var(--bg-card);
        border-radius: var(--card-radius);
        padding: 30px;
        max-width: 400px;
        width: 90%;
        text-align: center;
        border: none;
        box-shadow: var(--shadow-xl);
    }

    .modal h3 {
        color: var(--text-primary);
        margin-bottom: 15px;
    }

    .modal p {
        color: var(--text-secondary);
        margin-bottom: 25px;
    }

    .modal .modal-actions {
        display: flex;
        gap: 10px;
        justify-content: center;
    }

    @media (max-width: 768px) {
        .page-card {
            flex-wrap: wrap;
        }

        .page-card .thumbnail,
        .page-card .no-image {
            width: 60px;
            height: 45px;
        }

        .page-card .actions {
            width: 100%;
            justify-content: flex-end;
            margin-top: 10px;
        }
    }
</style>

<?php
require_once __DIR__ . '/../includes/breadcrumb.php';
$breadcrumbs = [
    ['label' => 'ダッシュボード', 'url' => '/app/manage/?tenant=' . $tenantSlug, 'icon' => 'fas fa-chart-pie'],
    ['label' => 'フリーページ', 'url' => '', 'icon' => 'fas fa-file-alt']
];
renderBreadcrumb($breadcrumbs);
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-file-alt"></i> フリーページ管理</h1>
        <p>自由にページを作成・編集できます</p>
    </div>
    <div class="header-actions">
        <a href="post?tenant=<?php echo h($tenantSlug); ?>" class="btn btn-primary">
            <i class="fas fa-plus"></i>
            新規作成
        </a>
    </div>
</div>

<div class="content-card">
    <!-- フィルタータブ -->
    <div class="filter-tabs">
        <a href="?tenant=<?php echo h($tenantSlug); ?>&status=all"
            class="filter-tab <?php echo $statusFilter === 'all' ? 'active' : ''; ?>">
            すべて
            <span class="count"><?php echo $allCount; ?></span>
        </a>
        <a href="?tenant=<?php echo h($tenantSlug); ?>&status=published"
            class="filter-tab <?php echo $statusFilter === 'published' ? 'active' : ''; ?>">
            <i class="fas fa-globe"></i>
            公開中
            <span class="count"><?php echo $publishedCount; ?></span>
        </a>
        <a href="?tenant=<?php echo h($tenantSlug); ?>&status=draft"
            class="filter-tab <?php echo $statusFilter === 'draft' ? 'active' : ''; ?>">
            <i class="fas fa-edit"></i>
            下書き
            <span class="count"><?php echo $draftCount; ?></span>
        </a>
    </div>

    <?php if (empty($pages)): ?>
        <div class="empty-state">
            <i class="fas fa-file-alt"></i>
            <h3>フリーページがありません</h3>
            <p>「新規作成」ボタンから最初のページを作成しましょう。</p>
            <a href="post?tenant=<?php echo h($tenantSlug); ?>" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                新規作成
            </a>
        </div>
    <?php else: ?>
        <div class="pages-list" id="sortable-list">
            <?php foreach ($pages as $p): ?>
                <div class="page-card" data-id="<?php echo $p['id']; ?>">
                    <?php if (!empty($p['featured_image'])): ?>
                        <img src="<?php echo h($p['featured_image']); ?>" alt="" class="thumbnail">
                    <?php else: ?>
                        <div class="no-image">
                            <i class="fas fa-image"></i>
                        </div>
                    <?php endif; ?>

                    <div class="info">
                        <div class="title">
                            <?php echo h($p['title']); ?>
                            <span class="status <?php echo $p['status']; ?>">
                                <?php echo $p['status'] === 'published' ? '公開中' : '下書き'; ?>
                            </span>
                        </div>
                        <div class="slug">
                            URL: <code>/<?php echo h($p['slug']); ?></code>
                        </div>
                        <div class="meta">
                            <span>
                                <i class="fas fa-calendar"></i>
                                <?php echo date('Y/m/d H:i', strtotime($p['created_at'])); ?>
                            </span>
                            <?php if ($p['published_at']): ?>
                                <span>
                                    <i class="fas fa-globe"></i>
                                    <?php echo date('Y/m/d H:i', strtotime($p['published_at'])); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="actions">
                        <a href="post?tenant=<?php echo h($tenantSlug); ?>&id=<?php echo $p['id']; ?>" class="btn-icon"
                            data-tooltip="編集">
                            <i class="fas fa-edit"></i>
                        </a>
                        <button type="button" class="btn-icon" data-tooltip="PC版プレビュー"
                            onclick="openPreview(<?php echo $p['id']; ?>, 'pc')">
                            <i class="fas fa-desktop"></i>
                        </button>
                        <button type="button" class="btn-icon" data-tooltip="スマホ版プレビュー"
                            onclick="openPreview(<?php echo $p['id']; ?>, 'mobile')">
                            <i class="fas fa-mobile-alt"></i>
                        </button>
                        <button type="button" class="btn-icon delete"
                            onclick="confirmDelete(<?php echo $p['id']; ?>, '<?php echo h(addslashes($p['title'])); ?>')"
                            data-tooltip="削除">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a
                        href="?tenant=<?php echo h($tenantSlug); ?>&status=<?php echo h($statusFilter); ?>&page=<?php echo $page - 1; ?>">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php if ($i === $page): ?>
                        <span class="active"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?tenant=<?php echo h($tenantSlug); ?>&status=<?php echo h($statusFilter); ?>&page=<?php echo $i; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a
                        href="?tenant=<?php echo h($tenantSlug); ?>&status=<?php echo h($statusFilter); ?>&page=<?php echo $page + 1; ?>">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- 削除確認モーダル -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal">
        <h3><i class="fas fa-exclamation-triangle" style="color: var(--danger);"></i> 削除の確認</h3>
        <p id="deleteMessage">このページを削除しますか？この操作は取り消せません。</p>
        <div class="modal-actions">
            <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">キャンセル</button>
            <button type="button" class="btn btn-danger" id="confirmDeleteBtn">削除する</button>
        </div>
    </div>
</div>

<script>
    let deletePageId = null;

    // 削除確認モーダル
    function confirmDelete(id, title) {
        deletePageId = id;
        document.getElementById('deleteMessage').innerHTML =
            `「<strong>${title}</strong>」を削除しますか？<br>この操作は取り消せません。`;
        document.getElementById('deleteModal').classList.add('active');
    }

    function closeDeleteModal() {
        document.getElementById('deleteModal').classList.remove('active');
        deletePageId = null;
    }

    document.getElementById('confirmDeleteBtn').addEventListener('click', function () {
        if (deletePageId) {
            fetch('api/delete_page?tenant=<?php echo h($tenantSlug); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ id: deletePageId })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('削除に失敗しました: ' + (data.error || '不明なエラー'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('削除に失敗しました');
                });
        }
        closeDeleteModal();
    });

    // モーダル外クリックで閉じる
    document.getElementById('deleteModal').addEventListener('click', function (e) {
        if (e.target === this) {
            closeDeleteModal();
        }
    });

    // ドラッグ&ドロップ並び替え
    const sortableList = document.getElementById('sortable-list');
    if (sortableList) {
        new Sortable(sortableList, {
            animation: 150,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            filter: '.actions, .actions *',
            onEnd: function () {
                const orders = [];
                sortableList.querySelectorAll('.page-card').forEach((card, index) => {
                    orders.push({
                        id: parseInt(card.dataset.id),
                        order: index
                    });
                });

                fetch('api/update_order?tenant=<?php echo h($tenantSlug); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ orders: orders })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('並び替えを保存しました。');
                        } else {
                            console.error('並び替え保存エラー:', data.error);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
            }
        });
    }

    // プレビューを別ウィンドウで開く
    const TENANT_SLUG = <?php echo json_encode($tenantSlug); ?>;

    function openPreview(pageId, mode) {
        let url, windowName, windowFeatures;
        if (mode === 'mobile') {
            url = '/app/front/free_preview_mobile?tenant=' + TENANT_SLUG + '&id=' + pageId;
            windowName = 'freePagePreviewMobile';
            windowFeatures = 'width=550,height=1100,scrollbars=yes,resizable=yes';
        } else {
            url = '/app/front/free_preview_pc?tenant=' + TENANT_SLUG + '&id=' + pageId;
            windowName = 'freePagePreviewPC';
            windowFeatures = 'width=1400,height=900,scrollbars=yes,resizable=yes';
        }
        window.open(url, windowName, windowFeatures);
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>