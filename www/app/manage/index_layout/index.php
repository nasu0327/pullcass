<?php
/**
 * インデックスページ（年齢確認ページ）レイアウト管理
 * 1カラム構成
 */
require_once __DIR__ . '/../../../includes/bootstrap.php';

// テナント認証
require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

$pdo = getPlatformDb();

// 現在のステータスを判定
try {
    // 編集中のセクション数
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM index_layout_sections WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $editCount = $stmt->fetchColumn();

    // 公開済みのセクション数
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM index_layout_sections_published WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $publishedCount = $stmt->fetchColumn();

    // 公開済みテーブルが空、または内容が異なる場合は「編集中」
    if ($publishedCount == 0) {
        $currentStatus = 'new';
        $statusLabel = '未公開';
        $statusClass = 'status-draft';
    } else {
        // 簡易比較
        $stmt = $pdo->prepare("SELECT MD5(GROUP_CONCAT(CONCAT(id, '-', display_order, '-', is_visible) ORDER BY id)) FROM index_layout_sections WHERE tenant_id = ?");
        $stmt->execute([$tenantId]);
        $editHash = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT MD5(GROUP_CONCAT(CONCAT(id, '-', display_order, '-', is_visible) ORDER BY id)) FROM index_layout_sections_published WHERE tenant_id = ?");
        $stmt->execute([$tenantId]);
        $publishedHash = $stmt->fetchColumn();

        if ($editHash === $publishedHash) {
            $currentStatus = 'published';
            $statusLabel = '公開済み';
            $statusClass = 'status-published';
        } else {
            $currentStatus = 'draft';
            $statusLabel = '編集中（未保存の変更あり）';
            $statusClass = 'status-draft';
        }
    }
} catch (PDOException $e) {
    $currentStatus = 'unknown';
    $statusLabel = '状態不明';
    $statusClass = 'status-draft';
}

// デフォルトセクションのキーリスト
require_once __DIR__ . '/../../../includes/index_layout_init.php';
$defaultSectionKeys = getIndexDefaultSectionKeys();

// セクションが存在しない場合は作成
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM index_layout_sections WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $sectionCount = $stmt->fetchColumn();

    if ($sectionCount == 0) {
        initIndexLayoutSections($pdo, $tenantId);
    } else {
        // 必要なセクションが不足しているかチェック
        $requiredSections = ['hero', 'reciprocal_links'];
        $stmt = $pdo->prepare("SELECT section_key FROM index_layout_sections WHERE tenant_id = ?");
        $stmt->execute([$tenantId]);
        $existingSections = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $missingSections = array_diff($requiredSections, $existingSections);

        if (!empty($missingSections)) {
            addMissingIndexSections($pdo, $tenantId, $missingSections);
        }
    }
} catch (Exception $e) {
    error_log("デフォルトセクション作成エラー: " . $e->getMessage());
}

// セクション取得（display_order順）
try {
    $stmt = $pdo->prepare("
        SELECT * FROM index_layout_sections 
        WHERE tenant_id = ?
        ORDER BY display_order ASC
    ");
    $stmt->execute([$tenantId]);
    $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 上部エリア（ヒーローセクション）を分離
    $heroSection = null;
    $otherSections = [];
    foreach ($sections as $section) {
        if ($section['section_key'] === 'hero') {
            $heroSection = $section;
        } else {
            $otherSections[] = $section;
        }
    }
} catch (PDOException $e) {
    $error = "データの取得に失敗しました: " . $e->getMessage();
    $sections = [];
    $heroSection = null;
    $otherSections = [];
}

$tenantSlugJson = json_encode($tenantSlug);
?>

<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title><?php echo h($tenant['name']); ?> 認証ページ編集</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/admin.css?v=<?php echo time(); ?>">
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .column-section {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 25px;
            border: none;
            box-shadow: var(--shadow-card);
            margin-bottom: 30px;
        }

        .column-title {
            font-size: 1.3rem;
            font-weight: bold;
            color: var(--text-primary);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            min-height: 50px;
        }

        .section-card {
            background: var(--bg-card);
            border: none;
            border-radius: 12px;
            padding: 15px 20px;
            cursor: grab;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--shadow-card);
        }

        .section-card:active {
            cursor: grabbing;
        }

        .section-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-card-hover);
        }

        .section-card.sortable-ghost {
            opacity: 0.4;
        }

        .section-card.sortable-drag {
            opacity: 0.8;
            box-shadow: var(--shadow-lg);
        }

        .section-card.hidden {
            opacity: 0.5;
            background: var(--bg-body);
        }

        .section-info {
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 1;
        }

        .admin-title-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-bottom: 5px;
            font-weight: 500;
        }

        .section-titles {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .title-en {
            font-size: 0.9rem;
            font-weight: bold;
            color: var(--primary);
        }

        .title-ja {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .section-type-badge {
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 600;
            background: var(--primary-bg);
            color: var(--primary);
            margin-left: 8px;
            vertical-align: middle;
        }

        .section-type-default {
            background: var(--bg-hover);
            color: var(--text-secondary);
        }

        .section-type-text {
            background: var(--success-bg);
            color: var(--success);
        }

        .section-type-embed {
            background: var(--primary-bg);
            color: var(--primary);
        }

        .section-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }


        .action-buttons {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 10px;
            padding: 15px;
            background: var(--bg-card);
            border-radius: 15px;
            box-shadow: var(--shadow-card);
            margin-bottom: 30px;
        }

        .action-buttons.bottom {
            margin-top: 40px;
            margin-bottom: 0;
        }

        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 400;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            white-space: nowrap;
        }

        .btn-draft {
            background: var(--bg-hover);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-draft:hover {
            background: var(--bg-active);
            transform: translateY(-2px);
        }

        .btn-preview {
            background: var(--primary-gradient);
            color: var(--text-inverse);
        }

        .btn-preview:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-card-hover);
        }

        .btn-publish {
            background: var(--success);
            color: var(--text-inverse);
        }

        .btn-publish:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-card-hover);
        }

        .btn-reset {
            background: var(--danger);
            color: var(--text-inverse);
        }

        .btn-reset:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-card-hover);
        }

        .status-indicator {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-left: 15px;
        }

        .status-draft {
            background: var(--warning-bg);
            color: var(--warning);
        }

        .status-published {
            background: var(--success-bg);
            color: var(--success);
        }

        /* ➕ボタンスタイル */
        .add-section-btn {
            width: 100%;
            padding: 12px;
            background: var(--primary-bg);
            border: 2px dashed var(--primary-border);
            border-radius: 10px;
            color: var(--primary);
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 8px 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .add-section-btn:hover {
            background: var(--primary-bg-hover);
            border-color: var(--primary);
            transform: translateY(-2px);
        }

        /* モーダルスタイル */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--bg-overlay);
            backdrop-filter: blur(5px);
            z-index: 10000;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-content {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            border: none;
            box-shadow: var(--shadow-xl);
        }

        .modal-header {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--text-primary);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-body {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .content-type-btn {
            padding: 20px;
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: 12px;
            color: var(--text-primary);
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .content-type-btn:hover {
            background: var(--primary-bg);
            border-color: var(--primary);
            transform: translateX(10px);
        }

        .content-type-btn .material-icons {
            font-size: 32px;
            color: var(--primary);
        }

        .content-type-info {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .content-type-title {
            font-weight: bold;
            font-size: 1.1rem;
        }

        .content-type-desc {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .modal-close {
            margin-top: 20px;
            padding: 12px;
            background: var(--bg-hover);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            cursor: pointer;
            width: 100%;
            transition: all 0.3s ease;
        }

        .modal-close:hover {
            background: var(--bg-active);
        }

        @media (max-width: 1024px) {
            .action-buttons {
                gap: 8px;
                padding: 12px;
            }

            .btn {
                padding: 8px 14px;
                font-size: 13px;
            }

            .btn .material-icons {
                font-size: 18px !important;
                margin-right: 4px !important;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }

            .action-buttons {
                flex-direction: column;
                gap: 8px;
            }

            .btn {
                width: 100%;
                justify-content: center;
                padding: 10px 16px;
            }

            .section-card {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .section-actions {
                width: 100%;
                justify-content: flex-end;
                flex-wrap: wrap;
            }
        }
    </style>
</head>

<body class="admin-body">
    <?php include __DIR__ . '/../includes/header.php'; ?>
    <div class="container">
        <?php
        require_once __DIR__ . '/../includes/breadcrumb.php';
        $breadcrumbs = [
            ['label' => 'ダッシュボード', 'url' => '/app/manage/?tenant=' . $tenantSlug, 'icon' => 'fas fa-chart-pie'],
            ['label' => '認証ページ編集']
        ];
        renderBreadcrumb($breadcrumbs);
        ?>
        <div class="header">
            <h1>認証ページ編集</h1>
            <p>認証ページ（年齢確認ページ）のセクション配置を管理<?php if ($currentStatus !== 'published'): ?><span
                        class="status-indicator <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span><?php endif; ?>
            </p>
        </div>

        <!-- アクションボタン（上部） -->
        <div class="action-buttons action-buttons-icons">
            <button type="button" class="btn-icon" data-tooltip="下書き保存" onclick="saveDraft()">
                <span class="material-icons">save</span>
            </button>
            <button type="button" class="btn-icon" data-tooltip="PC版プレビュー" onclick="openPreview()">
                <span class="material-icons">computer</span>
            </button>
            <button type="button" class="btn-icon" data-tooltip="スマホ版プレビュー" onclick="openMobilePreview()">
                <span class="material-icons">smartphone</span>
            </button>
            <button type="button" class="btn-icon btn-icon-success" data-tooltip="公開する" onclick="publishLayout()">
                <span class="material-icons">publish</span>
            </button>
            <button type="button" class="btn-icon" data-tooltip="リセット" onclick="resetLayout()">
                <span class="material-icons">restart_alt</span>
            </button>
        </div>

        <!-- 上部エリア（固定・並び替え不可） -->
        <?php if ($heroSection): ?>
            <div class="column-section">
                <div class="column-title">
                    上部エリア（固定）
                </div>
                <div class="section-card <?php echo $heroSection['is_visible'] ? '' : 'hidden'; ?>"
                    style="cursor: default;">
                    <div class="section-info">
                        <div class="section-titles">
                            <div class="admin-title-label">
                                管理名：最上段大エリア
                                <span class="section-type-badge section-type-default">デフォルト</span>
                            </div>
                            <div class="title-ja" style="color: var(--text-secondary); font-size: 0.9rem;">上部エリアデザイン</div>
                        </div>
                    </div>
                    <div class="section-actions">
                        <button class="btn-icon"
                            data-tooltip="編集"
                            onclick="window.location.href='hero_edit.php?tenant=<?php echo urlencode($tenantSlug); ?>&id=<?php echo $heroSection['id']; ?>'">
                            <span class="material-icons">edit</span>
                        </button>
                        <button class="visibility-toggle <?php echo $heroSection['is_visible'] ? '' : 'hidden'; ?>"
                            onclick="toggleVisibility(<?php echo $heroSection['id']; ?>, this)"
                            data-tooltip="<?php echo $heroSection['is_visible'] ? '非表示にする' : '表示する'; ?>">
                            <span
                                class="material-icons"><?php echo $heroSection['is_visible'] ? 'visibility' : 'visibility_off'; ?></span>
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- その他のセクション（並び替え可能） -->
        <div class="column-section">
            <div class="column-title">
                セクション（ドラッグで並び替え）
            </div>
            <div class="section-list" id="section-list">
                <?php foreach ($otherSections as $section): ?>
                    <?php $isDefault = isIndexDefaultSection($section['section_key']); ?>
                    <div class="section-card <?php echo $section['is_visible'] ? '' : 'hidden'; ?>"
                        data-id="<?php echo $section['id']; ?>" data-key="<?php echo $section['section_key']; ?>">
                        <div class="section-info">
                            <div class="section-titles">
                                <div class="admin-title-label">
                                    管理名：<?php echo h($section['admin_title']); ?>
                                    <?php if ($isDefault): ?>
                                        <span class="section-type-badge section-type-default">デフォルト</span>
                                    <?php elseif ($section['section_type'] === 'banner'): ?>
                                        <span class="section-type-badge">バナー</span>
                                    <?php elseif ($section['section_type'] === 'text_content'): ?>
                                        <span class="section-type-badge section-type-text">テキスト</span>
                                    <?php elseif ($section['section_type'] === 'embed_widget'): ?>
                                        <span class="section-type-badge section-type-embed">リンクパーツ</span>
                                    <?php endif; ?>
                                </div>
                                <div class="title-en">
                                    <?php echo !empty($section['title_en']) ? h($section['title_en']) : '<span style="color: var(--text-secondary);">タイトルなし</span>'; ?>
                                </div>
                                <div class="title-ja">
                                    <?php echo !empty($section['title_ja']) ? h($section['title_ja']) : '<span style="color: var(--text-secondary);">タイトルなし</span>'; ?>
                                </div>
                            </div>
                        </div>
                        <div class="section-actions">
                            <?php if ($isDefault): ?>
                                <!-- デフォルトセクション：編集ボタンのみ -->
                            <?php else: ?>
                                <button class="btn-icon btn-icon-danger"
                                    data-tooltip="削除"
                                    onclick="deleteSection(<?php echo $section['id']; ?>, '<?php echo addslashes(h($section['admin_title'])); ?>')">
                                    <span class="material-icons">delete</span>
                                </button>
                                <?php if ($section['section_type'] === 'banner'): ?>
                                    <button class="btn-icon"
                                        data-tooltip="編集"
                                        onclick="manageBanner('<?php echo h($section['section_key']); ?>')">
                                        <span class="material-icons">edit</span>
                                    </button>
                                <?php elseif ($section['section_type'] === 'text_content'): ?>
                                    <button class="btn-icon"
                                        data-tooltip="編集"
                                        onclick="window.location.href='text_content_edit.php?tenant=<?php echo urlencode($tenantSlug); ?>&id=<?php echo $section['id']; ?>'">
                                        <span class="material-icons">edit</span>
                                    </button>
                                <?php elseif ($section['section_type'] === 'embed_widget'): ?>
                                    <button class="btn-icon"
                                        data-tooltip="編集"
                                        onclick="window.location.href='embed_widget_edit.php?tenant=<?php echo urlencode($tenantSlug); ?>&id=<?php echo $section['id']; ?>'">
                                        <span class="material-icons">edit</span>
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                            <button class="visibility-toggle <?php echo $section['is_visible'] ? '' : 'hidden'; ?>"
                                onclick="toggleVisibility(<?php echo $section['id']; ?>, this)"
                                data-tooltip="<?php echo $section['is_visible'] ? '非表示にする' : '表示する'; ?>">
                                <span
                                    class="material-icons"><?php echo $section['is_visible'] ? 'visibility' : 'visibility_off'; ?></span>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <script>
        const TENANT_SLUG = <?php echo $tenantSlugJson; ?>;

        // PCプレビューを別ウィンドウで開く
        function openPreview() {
            const url = '/app/front/index_preview_pc.php?tenant=' + TENANT_SLUG;
            window.open(url, 'indexLayoutPreview', 'width=1200,height=900,scrollbars=yes,resizable=yes');
        }

        // スマホプレビューを別ウィンドウで開く
        function openMobilePreview() {
            const url = '/app/front/index_preview_mobile.php?tenant=' + TENANT_SLUG;
            window.open(url, 'indexLayoutMobilePreview', 'width=550,height=950,scrollbars=yes,resizable=yes');
        }

        // Sortable.js 初期化
        const sectionList = document.getElementById('section-list');
        if (sectionList) {
            new Sortable(sectionList, {
                animation: 150,
                draggable: '.section-card',
                filter: '.visibility-toggle, .btn-icon',
                preventOnFilter: true,
                ghostClass: 'sortable-ghost',
                dragClass: 'sortable-drag',
                onEnd: function () {
                    initAddButtons();
                    autoSaveDraft();
                }
            });
        }

        // 表示/非表示切り替え
        function toggleVisibility(id, button) {
            fetch('toggle_visibility.php?tenant=' + TENANT_SLUG, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ id: id })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const card = button.closest('.section-card');
                        const icon = button.querySelector('.material-icons');

                        if (data.is_visible) {
                            button.classList.remove('hidden');
                            card.classList.remove('hidden');
                            icon.textContent = 'visibility';
                            button.setAttribute('data-tooltip', '非表示にする');
                        } else {
                            button.classList.add('hidden');
                            card.classList.add('hidden');
                            icon.textContent = 'visibility_off';
                            button.setAttribute('data-tooltip', '表示する');
                        }
                    } else {
                        alert('エラー: ' + (data.message || '表示状態の更新に失敗しました。'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('表示状態の更新に失敗しました。');
                });
        }

        // バナー管理画面へ遷移
        function manageBanner(sectionKey) {
            window.location.href = 'banner_manage.php?section=' + sectionKey + '&tenant=' + TENANT_SLUG;
        }

        // セクション削除
        function deleteSection(id, adminTitle) {
            if (!confirm('「' + adminTitle + '」を削除してもよろしいですか？\n\nこの操作は取り消せません。')) {
                return;
            }

            fetch('delete_section.php?tenant=' + TENANT_SLUG, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ id: id })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('セクションを削除しました！');
                        location.reload();
                    } else {
                        alert('削除に失敗しました: ' + (data.message || '不明なエラー'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('削除に失敗しました。');
                });
        }

        // 下書き保存（手動）
        function saveDraft() {
            const order = Array.from(document.querySelectorAll('#section-list .section-card')).map(el => el.dataset.id);

            fetch('save_order.php?tenant=' + TENANT_SLUG, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    order: order,
                    autoSave: false
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('下書きを保存しました！');
                    } else {
                        alert('保存に失敗しました: ' + (data.message || '不明なエラー'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('保存に失敗しました。');
                });
        }

        // 自動保存（ドラッグ&ドロップ後）
        function autoSaveDraft() {
            const order = Array.from(document.querySelectorAll('#section-list .section-card')).map(el => el.dataset.id);

            fetch('save_order.php?tenant=' + TENANT_SLUG, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    order: order,
                    autoSave: true
                })
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
                    console.error('Auto-save error:', error);
                    alert('並び替えに失敗しました。');
                });
        }

        // 公開処理
        function publishLayout() {
            if (!confirm('現在の下書き内容を公開しますか？\nインデックスページ（年齢確認ページ）に反映されます。')) {
                return;
            }

            fetch('publish.php?tenant=' + TENANT_SLUG, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('レイアウトを公開しました！');
                        window.open('/app/front/index.php?tenant=' + TENANT_SLUG, '_blank');
                        location.reload();
                    } else {
                        alert('公開に失敗しました: ' + (data.message || '不明なエラー'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('公開に失敗しました。');
                });
        }

        // リセット処理
        function resetLayout() {
            if (!confirm('編集内容を破棄して、前回保存した状態に戻しますか？\n\nこの操作は取り消せません。')) {
                return;
            }

            fetch('reset.php?tenant=' + TENANT_SLUG, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert('リセットに失敗しました: ' + (data.message || '不明なエラー'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('リセットに失敗しました。');
                });
        }

        // ➕ボタンの初期化
        function initAddButtons() {
            const list = document.getElementById('section-list');
            if (!list) return;

            // 既存の➕ボタンを削除
            list.querySelectorAll('.add-section-btn').forEach(btn => btn.remove());

            // 各カードの前に➕ボタンを追加
            const cards = list.querySelectorAll('.section-card');
            cards.forEach((card, index) => {
                const addBtn = createAddButton(index);
                card.parentNode.insertBefore(addBtn, card);
            });

            // 最後に➕ボタンを追加
            const lastAddBtn = createAddButton(cards.length);
            list.appendChild(lastAddBtn);
        }

        function createAddButton(position) {
            const btn = document.createElement('button');
            btn.className = 'add-section-btn';
            btn.innerHTML = '<span class="material-icons">add</span> コンテンツを追加';
            btn.onclick = () => openAddModal(position);
            return btn;
        }

        // モーダル関連
        let currentPosition = 0;

        function openAddModal(position) {
            currentPosition = position;
            document.getElementById('addModal').classList.add('active');
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.remove('active');
        }

        function selectContentType(type) {
            closeAddModal();
            createNewSection(type);
        }

        function createNewSection(sectionType) {
            let adminTitle = '';
            if (sectionType === 'banner') {
                adminTitle = '新規画像セクション';
            } else if (sectionType === 'text_content') {
                adminTitle = '新規テキストセクション';
            } else if (sectionType === 'embed_widget') {
                adminTitle = '新規リンクパーツセクション';
            }

            fetch('add_section.php?tenant=' + TENANT_SLUG, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    section_type: sectionType,
                    admin_title: adminTitle,
                    position: currentPosition
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (sectionType === 'banner') {
                            window.location.href = 'banner_manage.php?section=' + data.section_key + '&tenant=' + TENANT_SLUG;
                        } else if (sectionType === 'text_content') {
                            window.location.href = 'text_content_edit.php?id=' + data.section_id + '&tenant=' + TENANT_SLUG;
                        } else if (sectionType === 'embed_widget') {
                            window.location.href = 'embed_widget_edit.php?id=' + data.section_id + '&tenant=' + TENANT_SLUG;
                        }
                    } else {
                        alert('作成に失敗しました: ' + (data.message || '不明なエラー'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('作成に失敗しました');
                });
        }

        // ページ読み込み時に➕ボタンを初期化
        document.addEventListener('DOMContentLoaded', function () {
            initAddButtons();
        });
    </script>

    <!-- コンテンツタイプ選択モーダル -->
    <div id="addModal" class="modal-overlay" onclick="if(event.target === this) closeAddModal()">
        <div class="modal-content">
            <div class="modal-header">
                <span class="material-icons">add_circle</span>
                コンテンツタイプを選択
            </div>
            <div class="modal-body">
                <button class="content-type-btn" onclick="selectContentType('banner')">
                    <span class="material-icons">image</span>
                    <div class="content-type-info">
                        <div class="content-type-title">画像</div>
                        <div class="content-type-desc">バナー画像を複数追加・管理できます</div>
                    </div>
                </button>

                <button class="content-type-btn" onclick="selectContentType('text_content')">
                    <span class="material-icons">article</span>
                    <div class="content-type-info">
                        <div class="content-type-title">テキスト</div>
                        <div class="content-type-desc">注意事項などのテキストコンテンツ（HTML対応）</div>
                    </div>
                </button>

                <button class="content-type-btn" onclick="selectContentType('embed_widget')">
                    <span class="material-icons">code</span>
                    <div class="content-type-info">
                        <div class="content-type-title">リンクパーツ</div>
                        <div class="content-type-desc">外部ウィジェットやiframeコードを埋め込み</div>
                    </div>
                </button>
            </div>
            <button class="modal-close" onclick="closeAddModal()">キャンセル</button>
        </div>
    </div>

</body>

</html>