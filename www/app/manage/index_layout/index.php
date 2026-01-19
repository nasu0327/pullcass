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
    
    // ヒーローセクションを分離
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
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 25px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 30px;
        }

        .column-title {
            font-size: 1.3rem;
            font-weight: bold;
            color: #27a3eb;
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
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            padding: 15px 20px;
            cursor: grab;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .section-card:active {
            cursor: grabbing;
        }

        .section-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(39, 163, 235, 0.2);
            border-color: rgba(39, 163, 235, 0.4);
        }

        .section-card.sortable-ghost {
            opacity: 0.4;
        }

        .section-card.sortable-drag {
            opacity: 0.8;
            box-shadow: 0 10px 30px rgba(39, 163, 235, 0.4);
        }

        .section-card.hidden {
            opacity: 0.5;
            background: rgba(255, 255, 255, 0.03);
        }

        .section-info {
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 1;
        }

        .admin-title-label {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.8);
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
            color: #27a3eb;
        }

        .title-ja {
            font-size: 1rem;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.9);
        }

        .section-type-badge {
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 600;
            background: rgba(39, 163, 235, 0.2);
            color: #27a3eb;
            margin-left: 8px;
            vertical-align: middle;
        }
        
        .section-type-default {
            background: rgba(158, 158, 158, 0.2);
            color: #9e9e9e;
        }
        
        .section-type-text {
            background: rgba(76, 175, 80, 0.2);
            color: #4CAF50;
        }
        
        .section-type-embed {
            background: rgba(156, 39, 176, 0.2);
            color: #9C27B0;
        }

        .section-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .visibility-toggle {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.8rem;
            padding: 5px;
            transition: all 0.3s ease;
            color: #4CAF50;
        }

        .visibility-toggle:hover {
            transform: scale(1.2);
        }

        .visibility-toggle.hidden {
            color: rgba(255, 255, 255, 0.3);
        }

        .edit-title-btn {
            background: #FF9800;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .edit-title-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(255, 152, 0, 0.3);
        }

        .delete-section-btn {
            background: rgba(244, 67, 54, 0.1);
            border: 2px solid rgba(244, 67, 54, 0.4);
            color: #f44336;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .delete-section-btn:hover {
            background: rgba(244, 67, 54, 0.2);
            border-color: #f44336;
            transform: translateY(-2px);
        }

        .action-buttons {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 10px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        .action-buttons.bottom {
            margin-top: 40px;
            margin-bottom: 0;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            white-space: nowrap;
        }

        .btn-draft {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .btn-draft:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        .btn-preview {
            background: linear-gradient(45deg, #9C27B0, #E91E63);
            color: white;
        }

        .btn-preview:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(156, 39, 176, 0.4);
        }

        .btn-publish {
            background: linear-gradient(45deg, #4CAF50, #45a049);
            color: white;
        }

        .btn-publish:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(76, 175, 80, 0.4);
        }
        
        .btn-reset {
            background: linear-gradient(45deg, #FF5722, #E64A19);
            color: white;
        }
        
        .btn-reset:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 87, 34, 0.4);
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
            background: rgba(255, 152, 0, 0.2);
            color: #FF9800;
        }

        .status-published {
            background: rgba(76, 175, 80, 0.2);
            color: #4CAF50;
        }

        /* ➕ボタンスタイル */
        .add-section-btn {
            width: 100%;
            padding: 12px;
            background: rgba(39, 163, 235, 0.1);
            border: 2px dashed rgba(39, 163, 235, 0.4);
            border-radius: 10px;
            color: #27a3eb;
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
            background: rgba(39, 163, 235, 0.2);
            border-color: #27a3eb;
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
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            z-index: 10000;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-content {
            background: linear-gradient(135deg, #1e1e2e 0%, #2d2d44 100%);
            border-radius: 20px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        }

        .modal-header {
            font-size: 1.5rem;
            font-weight: bold;
            color: #fff;
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
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: #fff;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .content-type-btn:hover {
            background: rgba(39, 163, 235, 0.2);
            border-color: #27a3eb;
            transform: translateX(10px);
        }

        .content-type-btn .material-icons {
            font-size: 32px;
            color: #27a3eb;
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
            color: rgba(255, 255, 255, 0.6);
        }

        .modal-close {
            margin-top: 20px;
            padding: 12px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            color: #fff;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s ease;
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        @media (max-width: 1024px) {
            .action-buttons {
                gap: 8px;
                padding: 12px;
            }
            
            .btn {
                padding: 8px 14px;
                font-size: 0.8rem;
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
        <div class="header">
            <h1>認証ページ編集</h1>
            <p>認証ページ（年齢確認ページ）のセクション配置を管理<?php if ($currentStatus !== 'published'): ?><span class="status-indicator <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span><?php endif; ?></p>
        </div>

        <!-- アクションボタン（上部） -->
        <div class="action-buttons">
            <button class="btn btn-draft" onclick="saveDraft()">
                <span class="material-icons" style="vertical-align: middle; margin-right: 5px;">save</span>
                下書き保存
            </button>
            <button onclick="openPreview()" class="btn btn-preview">
                <span class="material-icons" style="vertical-align: middle; margin-right: 5px;">computer</span>
                PCプレビュー
            </button>
            <button onclick="openMobilePreview()" class="btn btn-preview" style="background: linear-gradient(45deg, #00BCD4, #00ACC1);">
                <span class="material-icons" style="vertical-align: middle; margin-right: 5px;">smartphone</span>
                スマホプレビュー
            </button>
            <button class="btn btn-publish" onclick="publishLayout()">
                <span class="material-icons" style="vertical-align: middle; margin-right: 5px;">publish</span>
                公開する
            </button>
            <button class="btn btn-reset" onclick="resetLayout()">
                <span class="material-icons" style="vertical-align: middle; margin-right: 5px;">restart_alt</span>
                リセット
            </button>
        </div>

        <!-- ヒーローセクション（固定・並び替え不可） -->
        <?php if ($heroSection): ?>
        <div class="column-section">
            <div class="column-title">
                ヒーローセクション（固定）
            </div>
            <div class="section-card <?php echo $heroSection['is_visible'] ? '' : 'hidden'; ?>" style="cursor: default;">
                <div class="section-info">
                    <div class="section-titles">
                        <div class="admin-title-label">
                            管理名：<?php echo h($heroSection['admin_title']); ?>
                            <span class="section-type-badge section-type-default">デフォルト</span>
                        </div>
                        <div class="title-ja" style="color: rgba(255,255,255,0.6); font-size: 0.9rem;">背景画像・動画の設定</div>
                    </div>
                </div>
                <div class="section-actions">
                    <button class="edit-title-btn" onclick="window.location.href='hero_edit.php?tenant=<?php echo urlencode($tenantSlug); ?>&id=<?php echo $heroSection['id']; ?>'">
                        <span class="material-icons" style="font-size: 14px; vertical-align: middle;">edit</span>
                        編集
                    </button>
                    <button class="visibility-toggle <?php echo $heroSection['is_visible'] ? '' : 'hidden'; ?>" 
                            onclick="toggleVisibility(<?php echo $heroSection['id']; ?>, this)"
                            title="<?php echo $heroSection['is_visible'] ? '非表示にする' : '表示する'; ?>">
                        <span class="material-icons"><?php echo $heroSection['is_visible'] ? 'visibility' : 'visibility_off'; ?></span>
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
                <div class="section-card <?php echo $section['is_visible'] ? '' : 'hidden'; ?>" data-id="<?php echo $section['id']; ?>" data-key="<?php echo $section['section_key']; ?>">
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
                            <div class="title-en"><?php echo !empty($section['title_en']) ? h($section['title_en']) : '<span style="color: rgba(255,255,255,0.4);">タイトルなし</span>'; ?></div>
                            <div class="title-ja"><?php echo !empty($section['title_ja']) ? h($section['title_ja']) : '<span style="color: rgba(255,255,255,0.4);">タイトルなし</span>'; ?></div>
                        </div>
                    </div>
                    <div class="section-actions">
                        <?php if ($isDefault): ?>
                            <!-- デフォルトセクション：編集ボタンのみ -->
                        <?php else: ?>
                            <button class="delete-section-btn" onclick="deleteSection(<?php echo $section['id']; ?>, '<?php echo addslashes(h($section['admin_title'])); ?>')">
                                <span class="material-icons" style="font-size: 14px; vertical-align: middle;">delete</span>
                                削除
                            </button>
                            <?php if ($section['section_type'] === 'banner'): ?>
                            <button class="edit-title-btn" onclick="manageBanner('<?php echo h($section['section_key']); ?>')">
                                <span class="material-icons" style="font-size: 14px; vertical-align: middle;">edit</span>
                                編集
                            </button>
                            <?php elseif ($section['section_type'] === 'text_content'): ?>
                            <button class="edit-title-btn" onclick="window.location.href='text_content_edit.php?tenant=<?php echo urlencode($tenantSlug); ?>&id=<?php echo $section['id']; ?>'">
                                <span class="material-icons" style="font-size: 14px; vertical-align: middle;">edit</span>
                                編集
                            </button>
                            <?php elseif ($section['section_type'] === 'embed_widget'): ?>
                            <button class="edit-title-btn" onclick="window.location.href='embed_widget_edit.php?tenant=<?php echo urlencode($tenantSlug); ?>&id=<?php echo $section['id']; ?>'">
                                <span class="material-icons" style="font-size: 14px; vertical-align: middle;">edit</span>
                                編集
                            </button>
                            <?php endif; ?>
                        <?php endif; ?>
                        <button class="visibility-toggle <?php echo $section['is_visible'] ? '' : 'hidden'; ?>" 
                                onclick="toggleVisibility(<?php echo $section['id']; ?>, this)"
                                title="<?php echo $section['is_visible'] ? '非表示にする' : '表示する'; ?>">
                            <span class="material-icons"><?php echo $section['is_visible'] ? 'visibility' : 'visibility_off'; ?></span>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- アクションボタン（下部） -->
        <div class="action-buttons bottom">
            <button class="btn btn-draft" onclick="saveDraft()">
                <span class="material-icons" style="vertical-align: middle; margin-right: 5px;">save</span>
                下書き保存
            </button>
            <button onclick="openPreview()" class="btn btn-preview">
                <span class="material-icons" style="vertical-align: middle; margin-right: 5px;">computer</span>
                PCプレビュー
            </button>
            <button onclick="openMobilePreview()" class="btn btn-preview" style="background: linear-gradient(45deg, #00BCD4, #00ACC1);">
                <span class="material-icons" style="vertical-align: middle; margin-right: 5px;">smartphone</span>
                スマホプレビュー
            </button>
            <button class="btn btn-publish" onclick="publishLayout()">
                <span class="material-icons" style="vertical-align: middle; margin-right: 5px;">publish</span>
                公開する
            </button>
            <button class="btn btn-reset" onclick="resetLayout()">
                <span class="material-icons" style="vertical-align: middle; margin-right: 5px;">restart_alt</span>
                リセット
            </button>
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
            window.open(url, 'indexLayoutMobilePreview', 'width=500,height=950,scrollbars=yes,resizable=yes');
        }

        // Sortable.js 初期化
        const sectionList = document.getElementById('section-list');
        if (sectionList) {
            new Sortable(sectionList, {
                animation: 150,
                draggable: '.section-card',
                filter: '.visibility-toggle, .edit-title-btn, .delete-section-btn',
                preventOnFilter: true,
                ghostClass: 'sortable-ghost',
                dragClass: 'sortable-drag',
                onEnd: function() {
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
                        button.title = '非表示にする';
                    } else {
                        button.classList.add('hidden');
                        card.classList.add('hidden');
                        icon.textContent = 'visibility_off';
                        button.title = '表示する';
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
            .catch(error => {
                console.error('Auto-save error:', error);
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
        document.addEventListener('DOMContentLoaded', function() {
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
