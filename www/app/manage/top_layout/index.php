<?php
/**
 * トップページレイアウト管理（参考サイト準拠・テナント対応版）
 */

// 共通ファイル読み込み
require_once __DIR__ . '/../../../includes/bootstrap.php';

// 認証チェック
require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

// ページタイトル設定
$pageTitle = 'トップページレイアウト管理';

// デフォルトセクションのsection_keyリスト
$defaultSectionKeys = [
    'hero_text', // トップバナー下テキスト
    'today_cast', 'new_cast', 'reviews', 'videos',
    'repeat_ranking', 'attention_ranking', 
    'diary', 'history'
];

// セクションがデフォルトかどうかを判定する関数
function isDefaultSection($sectionKey, $defaultKeys) {
    return in_array($sectionKey, $defaultKeys);
}

// 現在のステータスを判定
try {
    // 編集中のセクション数
    $editCountStmt = $pdo->prepare("SELECT COUNT(*) FROM top_layout_sections WHERE tenant_id = ?");
    $editCountStmt->execute([$tenantId]);
    $editCount = $editCountStmt->fetchColumn();
    
    // 公開済みのセクション数
    $publishedCountStmt = $pdo->prepare("SELECT COUNT(*) FROM top_layout_sections_published WHERE tenant_id = ?");
    $publishedCountStmt->execute([$tenantId]);
    $publishedCount = $publishedCountStmt->fetchColumn();
    
    // 公開済みテーブルが空、または内容が異なる場合
    if ($publishedCount == 0) {
        $currentStatus = 'new';
        $statusLabel = '未公開';
        $statusClass = 'status-draft';
    } else {
        // 簡易比較：IDのリストで判定
        $editIdsStmt = $pdo->prepare("SELECT GROUP_CONCAT(id ORDER BY id) FROM top_layout_sections WHERE tenant_id = ?");
        $editIdsStmt->execute([$tenantId]);
        $editIds = $editIdsStmt->fetchColumn();
        
        $publishedIdsStmt = $pdo->prepare("SELECT GROUP_CONCAT(id ORDER BY id) FROM top_layout_sections_published WHERE tenant_id = ?");
        $publishedIdsStmt->execute([$tenantId]);
        $publishedIds = $publishedIdsStmt->fetchColumn();
        
        if ($editIds === $publishedIds) {
            // 詳細比較
            $editHashStmt = $pdo->prepare("
                SELECT MD5(GROUP_CONCAT(
                    CONCAT(
                        id, '-', 
                        COALESCE(pc_left_order,''), '-', 
                        COALESCE(pc_right_order,''), '-', 
                        COALESCE(mobile_order,''), '-', 
                        is_visible
                    ) ORDER BY id
                )) 
                FROM top_layout_sections 
                WHERE tenant_id = ?
            ");
            $editHashStmt->execute([$tenantId]);
            $editHash = $editHashStmt->fetchColumn();
            
            $publishedHashStmt = $pdo->prepare("
                SELECT MD5(GROUP_CONCAT(
                    CONCAT(
                        id, '-', 
                        COALESCE(pc_left_order,''), '-', 
                        COALESCE(pc_right_order,''), '-', 
                        COALESCE(mobile_order,''), '-', 
                        is_visible
                    ) ORDER BY id
                )) 
                FROM top_layout_sections_published 
                WHERE tenant_id = ?
            ");
            $publishedHashStmt->execute([$tenantId]);
            $publishedHash = $publishedHashStmt->fetchColumn();
            
            if ($editHash === $publishedHash) {
                $currentStatus = 'published';
                $statusLabel = '公開済み';
                $statusClass = 'status-published';
            } else {
                $currentStatus = 'draft';
                $statusLabel = '編集中（未保存の変更あり）';
                $statusClass = 'status-draft';
            }
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

// セクション取得
try {
    // hero_text取得
    $stmtHeroText = $pdo->prepare("
        SELECT * FROM top_layout_sections 
        WHERE tenant_id = ? AND section_key = 'hero_text'
        LIMIT 1
    ");
    $stmtHeroText->execute([$tenantId]);
    $heroTextSection = $stmtHeroText->fetch(PDO::FETCH_ASSOC);
    
    // PC左カラム
    $stmtLeft = $pdo->prepare("
        SELECT * FROM top_layout_sections 
        WHERE tenant_id = ? AND pc_left_order IS NOT NULL
        ORDER BY pc_left_order ASC
    ");
    $stmtLeft->execute([$tenantId]);
    $draftLeftSections = $stmtLeft->fetchAll(PDO::FETCH_ASSOC);
    
    // PC右カラム
    $stmtRight = $pdo->prepare("
        SELECT * FROM top_layout_sections 
        WHERE tenant_id = ? AND pc_right_order IS NOT NULL
        ORDER BY pc_right_order ASC
    ");
    $stmtRight->execute([$tenantId]);
    $draftRightSections = $stmtRight->fetchAll(PDO::FETCH_ASSOC);
    
    // スマホ用
    $stmtMobile = $pdo->prepare("
        SELECT * FROM top_layout_sections
        WHERE tenant_id = ?
        ORDER BY 
            CASE 
                WHEN mobile_order IS NOT NULL THEN mobile_order
                WHEN pc_left_order IS NOT NULL THEN pc_left_order
                WHEN pc_right_order IS NOT NULL THEN pc_right_order + 1000
                ELSE 9999
            END ASC
    ");
    $stmtMobile->execute([$tenantId]);
    $draftMobileSections = $stmtMobile->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "データの取得に失敗しました: " . $e->getMessage();
}

// セクションカード描画関数
function renderSectionCard($section, $defaultKeys, $tenantSlug, $isMobile = false) {
    $isDefault = isDefaultSection($section['section_key'], $defaultKeys);
    $visibleClass = $section['is_visible'] ? '' : 'hidden';
    
    // section_typeに応じたバッジ
    $badge = '';
    $badgeStyle = '';
    switch ($section['section_type']) {
        case 'banner':
            $badge = 'バナー';
            break;
        case 'text_content':
            $badge = 'テキスト';
            $badgeStyle = 'background: rgba(76, 175, 80, 0.2); color: #4CAF50;';
            break;
        case 'embed_widget':
            $badge = '埋め込み';
            $badgeStyle = 'background: rgba(156, 39, 176, 0.2); color: #9C27B0;';
            break;
    }
    
    echo '<div class="section-card ' . $visibleClass . '" data-id="' . $section['id'] . '" data-key="' . h($section['section_key']) . '">';
    echo '<div class="section-info">';
    echo '<span class="material-icons drag-handle">drag_indicator</span>';
    echo '<div class="section-titles">';
    echo '<div class="admin-title-label">管理名：' . h($section['admin_title']) . '</div>';
    echo '<div class="title-en">' . (!empty($section['title_en']) ? h($section['title_en']) : '<span style="color: rgba(255,255,255,0.4);">タイトルなし</span>') . '</div>';
    echo '<div class="title-ja">' . (!empty($section['title_ja']) ? h($section['title_ja']) : '<span style="color: rgba(255,255,255,0.4);">タイトルなし</span>') . '</div>';
    echo '</div>';
    if ($badge) {
        echo '<span class="section-type-badge" ' . ($badgeStyle ? 'style="' . $badgeStyle . '"' : '') . '>' . $badge . '</span>';
    }
    echo '</div>';
    echo '<div class="section-actions">';
    
    // 編集・削除ボタン
    if ($isDefault) {
        // デフォルトセクション
        echo '<button class="edit-title-btn" onclick="window.location.href=\'title_edit.php?id=' . $section['id'] . '&tenant=' . urlencode($tenantSlug) . '\'">';
        echo '<span class="material-icons" style="font-size: 14px; vertical-align: middle;">edit</span>';
        echo '編集</button>';
    } else {
        // カスタムセクション
        switch ($section['section_type']) {
            case 'banner':
                echo '<button class="edit-title-btn" onclick="manageBanner(\'' . h($section['section_key']) . '\')">';
                echo '<span class="material-icons" style="font-size: 14px; vertical-align: middle;">edit</span>';
                echo '編集</button>';
                break;
            case 'text_content':
                $editUrl = 'text_content_edit.php?id=' . $section['id'] . '&tenant=' . urlencode($tenantSlug);
                echo '<button class="edit-title-btn" onclick="window.location.href=\'' . $editUrl . '\'">';
                echo '<span class="material-icons" style="font-size: 14px; vertical-align: middle;">edit</span>';
                echo '編集</button>';
                break;
            case 'embed_widget':
                $editUrl = 'embed_widget_edit.php?id=' . $section['id'] . '&tenant=' . urlencode($tenantSlug);
                echo '<button class="edit-title-btn" onclick="window.location.href=\'' . $editUrl . '\'">';
                echo '<span class="material-icons" style="font-size: 14px; vertical-align: middle;">edit</span>';
                echo '編集</button>';
                break;
        }
        
        echo '<button class="delete-section-btn" onclick="deleteSection(' . $section['id'] . ', \'' . htmlspecialchars($section['admin_title'], ENT_QUOTES) . '\')">';
        echo '<span class="material-icons" style="font-size: 14px; vertical-align: middle;">delete</span>';
        echo '削除</button>';
    }
    
    // 表示/非表示ボタン
    $visibilityIcon = $section['is_visible'] ? 'visibility' : 'visibility_off';
    $visibilityTitle = $section['is_visible'] ? '非表示にする' : '表示する';
    echo '<button class="visibility-toggle ' . $visibleClass . '" onclick="toggleVisibility(' . $section['id'] . ', this)" title="' . $visibilityTitle . '">';
    echo '<span class="material-icons">' . $visibilityIcon . '</span>';
    echo '</button>';
    
    echo '</div>';
    echo '</div>';
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title><?php echo h($pageTitle); ?> | <?php echo h($shopName); ?></title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/admin_common.css?v=<?php echo time(); ?>">
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

        .header {
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .header p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 1rem;
        }

        .status-indicator {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: bold;
            margin-left: 15px;
        }

        .status-draft {
            background: rgba(255, 193, 7, 0.2);
            color: #FFC107;
        }

        .status-published {
            background: rgba(76, 175, 80, 0.2);
            color: #4CAF50;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            text-decoration: none;
        }

        .btn-draft {
            background: rgba(255, 193, 7, 0.2);
            color: #FFC107;
            border: 2px solid rgba(255, 193, 7, 0.3);
        }

        .btn-draft:hover {
            background: rgba(255, 193, 7, 0.3);
            transform: translateY(-2px);
        }

        .btn-preview {
            background: rgba(33, 150, 243, 0.2);
            color: #2196F3;
            border: 2px solid rgba(33, 150, 243, 0.3);
        }

        .btn-preview:hover {
            background: rgba(33, 150, 243, 0.3);
            transform: translateY(-2px);
        }

        .btn-publish {
            background: rgba(76, 175, 80, 0.2);
            color: #4CAF50;
            border: 2px solid rgba(76, 175, 80, 0.3);
        }

        .btn-publish:hover {
            background: rgba(76, 175, 80, 0.3);
            transform: translateY(-2px);
        }

        .btn-reset {
            background: rgba(244, 67, 54, 0.2);
            color: #F44336;
            border: 2px solid rgba(244, 67, 54, 0.3);
        }

        .btn-reset:hover {
            background: rgba(244, 67, 54, 0.3);
            transform: translateY(-2px);
        }

        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
        }

        .tab {
            padding: 15px 30px;
            background: rgba(255, 255, 255, 0.05);
            border: none;
            border-radius: 10px 10px 0 0;
            color: rgba(255, 255, 255, 0.6);
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .tab.active {
            background: rgba(39, 163, 235, 0.2);
            color: #27a3eb;
            border-bottom: 3px solid #27a3eb;
        }

        .tab:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .columns-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }

        .column-section {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 25px;
            border: 1px solid rgba(255, 255, 255, 0.1);
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
            min-height: 100px;
        }

        .section-card {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            padding: 15px 20px;
            cursor: move;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: space-between;
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

        .drag-handle {
            color: rgba(255, 255, 255, 0.5);
            cursor: grab;
        }

        .drag-handle:active {
            cursor: grabbing;
        }

        .section-titles {
            flex: 1;
        }

        .admin-title-label {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.5);
            margin-bottom: 4px;
        }

        .title-en {
            font-size: 1.1rem;
            font-weight: 600;
            color: #fff;
            margin-bottom: 2px;
        }

        .title-ja {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.7);
        }

        .section-type-badge {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.75rem;
            background: rgba(39, 163, 235, 0.2);
            color: #27a3eb;
            font-weight: 600;
        }

        .section-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .edit-title-btn,
        .delete-section-btn,
        .visibility-toggle {
            padding: 8px 12px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .edit-title-btn {
            background: rgba(33, 150, 243, 0.2);
            color: #2196F3;
        }

        .edit-title-btn:hover {
            background: rgba(33, 150, 243, 0.3);
        }

        .delete-section-btn {
            background: rgba(244, 67, 54, 0.2);
            color: #F44336;
        }

        .delete-section-btn:hover {
            background: rgba(244, 67, 54, 0.3);
        }

        .visibility-toggle {
            background: rgba(76, 175, 80, 0.2);
            color: #4CAF50;
            padding: 8px;
        }

        .visibility-toggle.hidden {
            background: rgba(158, 158, 158, 0.2);
            color: #9E9E9E;
        }

        .visibility-toggle:hover {
            background: rgba(76, 175, 80, 0.3);
        }

        .visibility-toggle.hidden:hover {
            background: rgba(158, 158, 158, 0.3);
        }

        .add-section-btn {
            width: 100%;
            padding: 15px;
            background: rgba(39, 163, 235, 0.1);
            border: 2px dashed rgba(39, 163, 235, 0.3);
            border-radius: 12px;
            color: #27a3eb;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .add-section-btn:hover {
            background: rgba(39, 163, 235, 0.2);
            border-color: rgba(39, 163, 235, 0.5);
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: rgba(30, 30, 30, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 20px;
            color: #27a3eb;
        }

        .modal-options {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 20px;
        }

        .modal-option {
            padding: 15px 20px;
            background: rgba(255, 255, 255, 0.08);
            border: 2px solid rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .modal-option:hover {
            background: rgba(255, 255, 255, 0.12);
            border-color: rgba(39, 163, 235, 0.4);
            transform: translateX(5px);
        }

        .modal-option-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #fff;
            margin-bottom: 5px;
        }

        .modal-option-desc {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.6);
        }

        .modal-close {
            padding: 12px;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            border-radius: 10px;
            color: #fff;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s ease;
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .columns-container {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 10px;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
<?php require_once __DIR__ . '/../includes/header.php'; ?>

<div class="container">
    <div class="header">
        <h1>トップページレイアウト管理</h1>
        <p>トップページのセクション配置を管理<?php if ($currentStatus !== 'published'): ?><span class="status-indicator <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span><?php endif; ?></p>
    </div>

    <!-- アクションボタン -->
    <div class="action-buttons">
        <button class="btn btn-draft" onclick="saveDraft()">
            <span class="material-icons" style="vertical-align: middle; margin-right: 5px;">save</span>
            下書き保存
        </button>
        <a href="/app/front/top.php?tenant=<?php echo urlencode($tenant['slug']); ?>" target="_blank" class="btn btn-preview">
            <span class="material-icons" style="vertical-align: middle; margin-right: 5px;">preview</span>
            プレビュー確認
        </a>
        <button class="btn btn-publish" onclick="publishLayout()">
            <span class="material-icons" style="vertical-align: middle; margin-right: 5px;">publish</span>
            公開する
        </button>
        <button class="btn btn-reset" onclick="resetLayout()">
            <span class="material-icons" style="vertical-align: middle; margin-right: 5px;">restart_alt</span>
            リセット
        </button>
    </div>

    <!-- タブ -->
    <div class="tabs">
        <button class="tab active" data-tab="pc">PC表示設定</button>
        <button class="tab" data-tab="mobile">スマホ表示設定</button>
    </div>

    <!-- PC表示設定 -->
    <div class="tab-content active" id="tab-pc">
        
        <!-- Hero Text -->
        <?php if ($heroTextSection): ?>
        <div style="margin-bottom: 30px;">
            <div class="section-card <?php echo $heroTextSection['is_visible'] ? '' : 'hidden'; ?>" style="max-width: 100%; margin: 0;">
                <div class="section-info">
                    <span class="material-icons" style="font-size: 28px;">description</span>
                    <div class="section-titles">
                        <div class="admin-title-label">管理名：<?php echo h($heroTextSection['admin_title']); ?></div>
                        <div class="title-en" style="color: rgba(255,255,255,0.6); font-size: 0.85rem;">トップページ最上部に表示</div>
                        <div class="title-ja" style="color: rgba(255,255,255,0.6); font-size: 0.85rem;">H1タイトルと導入文</div>
                    </div>
                    <span class="section-type-badge">H1テキスト</span>
                </div>
                <div class="section-actions">
                    <button class="edit-title-btn" onclick="window.location.href='hero_text_edit.php?id=<?php echo $heroTextSection['id']; ?>&tenant=<?php echo urlencode($tenant['slug']); ?>'">
                        <span class="material-icons" style="font-size: 14px; vertical-align: middle;">edit</span>
                        編集
                    </button>
                    <button class="visibility-toggle <?php echo $heroTextSection['is_visible'] ? '' : 'hidden'; ?>" 
                            onclick="toggleVisibility(<?php echo $heroTextSection['id']; ?>, this)"
                            title="<?php echo $heroTextSection['is_visible'] ? '非表示にする' : '表示する'; ?>">
                        <span class="material-icons"><?php echo $heroTextSection['is_visible'] ? 'visibility' : 'visibility_off'; ?></span>
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="columns-container">
            <!-- 左カラム -->
            <div class="column-section">
                <div class="column-title">
                    <span class="material-icons">view_week</span>
                    左カラム（横スクロール対応）
                </div>
                <div class="section-list" id="left-column" data-column="left">
                    <?php foreach ($draftLeftSections as $section): ?>
                    <?php renderSectionCard($section, $defaultSectionKeys, $tenant['slug']); ?>
                    <?php endforeach; ?>
                </div>
                <button class="add-section-btn" onclick="openAddModal('left')" style="margin-top: 15px;">
                    <span class="material-icons">add_circle</span>
                    セクション追加
                </button>
            </div>

            <!-- 右カラム -->
            <div class="column-section">
                <div class="column-title">
                    <span class="material-icons">view_agenda</span>
                    右カラム（縦スクロール対応）
                </div>
                <div class="section-list" id="right-column" data-column="right">
                    <?php foreach ($draftRightSections as $section): ?>
                    <?php renderSectionCard($section, $defaultSectionKeys, $tenant['slug']); ?>
                    <?php endforeach; ?>
                </div>
                <button class="add-section-btn" onclick="openAddModal('right')" style="margin-top: 15px;">
                    <span class="material-icons">add_circle</span>
                    セクション追加
                </button>
            </div>
        </div>
    </div>

    <!-- スマホ表示設定 -->
    <div class="tab-content" id="tab-mobile">
        <div class="column-section">
            <div class="column-title">
                <span class="material-icons">smartphone</span>
                スマホ表示順序
            </div>
            <div class="section-list" id="mobile-list">
                <?php foreach ($draftMobileSections as $section): ?>
                <?php if ($section['section_key'] !== 'hero_text'): ?>
                <?php renderSectionCard($section, $defaultSectionKeys, $tenant['slug'], true); ?>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- 追加モーダル -->
<div class="modal" id="add-modal">
    <div class="modal-content">
        <div class="modal-title">新規セクション追加</div>
        <div class="modal-options">
            <div class="modal-option" onclick="addSection('banner')">
                <div class="modal-option-title">📷 画像バナー</div>
                <div class="modal-option-desc">画像とリンクを設定できるバナーセクション</div>
            </div>
            <div class="modal-option" onclick="addSection('text_content')">
                <div class="modal-option-title">📝 テキストコンテンツ</div>
                <div class="modal-option-desc">リッチエディタで編集可能なテキストセクション</div>
            </div>
            <div class="modal-option" onclick="addSection('embed_widget')">
                <div class="modal-option-title">🔗 埋め込みパーツ</div>
                <div class="modal-option-desc">HTMLコードを埋め込めるセクション</div>
            </div>
        </div>
        <button class="modal-close" onclick="closeAddModal()">キャンセル</button>
    </div>
</div>

<script>
// グローバル変数
let currentAddColumn = 'left';

// タブ切り替え
document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', function() {
        const targetTab = this.dataset.tab;
        
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        
        this.classList.add('active');
        document.getElementById('tab-' + targetTab).classList.add('active');
    });
});

// Sortable初期化（PC左）
Sortable.create(document.getElementById('left-column'), {
    animation: 150,
    ghostClass: 'sortable-ghost',
    dragClass: 'sortable-drag',
    handle: '.drag-handle',
    onEnd: function() {
        autoSavePcOrder();
    }
});

// Sortable初期化（PC右）
Sortable.create(document.getElementById('right-column'), {
    animation: 150,
    ghostClass: 'sortable-ghost',
    dragClass: 'sortable-drag',
    handle: '.drag-handle',
    onEnd: function() {
        autoSavePcOrder();
    }
});

// Sortable初期化（モバイル）
Sortable.create(document.getElementById('mobile-list'), {
    animation: 150,
    ghostClass: 'sortable-ghost',
    dragClass: 'sortable-drag',
    handle: '.drag-handle',
    onEnd: function() {
        autoSaveMobileOrder();
    }
});

// 自動保存（PC順序）
function autoSavePcOrder() {
    const leftIds = Array.from(document.querySelectorAll('#left-column .section-card'))
        .map(card => parseInt(card.dataset.id));
    const rightIds = Array.from(document.querySelectorAll('#right-column .section-card'))
        .map(card => parseInt(card.dataset.id));
    
    fetch('save_order.php?tenant=<?php echo urlencode($tenant['slug']); ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            leftIds: leftIds,
            rightIds: rightIds,
            autoSave: true
        })
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            alert('保存に失敗しました');
        }
    });
}

// 自動保存（モバイル順序）
function autoSaveMobileOrder() {
    const mobileIds = Array.from(document.querySelectorAll('#mobile-list .section-card'))
        .map(card => parseInt(card.dataset.id));
    
    fetch('save_mobile_order.php?tenant=<?php echo urlencode($tenant['slug']); ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({mobileIds: mobileIds})
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            alert('保存に失敗しました');
        }
    });
}

// 下書き保存
function saveDraft() {
    if (!confirm('現在の状態を下書き保存しますか？')) return;
    
    const leftIds = Array.from(document.querySelectorAll('#left-column .section-card'))
        .map(card => parseInt(card.dataset.id));
    const rightIds = Array.from(document.querySelectorAll('#right-column .section-card'))
        .map(card => parseInt(card.dataset.id));
    
    fetch('save_order.php?tenant=<?php echo urlencode($tenant['slug']); ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            leftIds: leftIds,
            rightIds: rightIds,
            autoSave: false
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('下書きを保存しました');
            location.reload();
        } else {
            alert('保存に失敗しました');
        }
    });
}

// 公開
function publishLayout() {
    if (!confirm('現在の編集内容を公開しますか？\n公開後、フロント画面に反映されます。')) return;
    
    fetch('publish.php?tenant=<?php echo urlencode($tenant['slug']); ?>', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('レイアウトを公開しました');
            location.reload();
        } else {
            alert('公開に失敗しました');
        }
    });
}

// リセット
function resetLayout() {
    if (!confirm('編集内容を破棄して、最後に保存した状態に戻しますか？')) return;
    
    fetch('reset.php?tenant=<?php echo urlencode($tenant['slug']); ?>', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('リセットしました');
            location.reload();
        } else {
            alert('リセットに失敗しました');
        }
    });
}

// 表示/非表示切り替え
function toggleVisibility(sectionId, button) {
    fetch('toggle_visibility.php?tenant=<?php echo urlencode($tenant['slug']); ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({sectionId: sectionId})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const card = button.closest('.section-card');
            const icon = button.querySelector('.material-icons');
            
            if (data.isVisible) {
                card.classList.remove('hidden');
                button.classList.remove('hidden');
                icon.textContent = 'visibility';
                button.title = '非表示にする';
            } else {
                card.classList.add('hidden');
                button.classList.add('hidden');
                icon.textContent = 'visibility_off';
                button.title = '表示する';
            }
        } else {
            alert('切り替えに失敗しました');
        }
    });
}

// セクション削除
function deleteSection(sectionId, title) {
    if (!confirm(`「${title}」を削除しますか？\nこの操作は取り消せません。`)) return;
    
    fetch('delete_section.php?tenant=<?php echo urlencode($tenant['slug']); ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({sectionId: sectionId})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('削除しました');
            location.reload();
        } else {
            alert('削除に失敗しました');
        }
    });
}

// バナー管理画面へ
function manageBanner(sectionKey) {
    window.location.href = 'banner_manage.php?section_key=' + sectionKey + '&tenant=<?php echo urlencode($tenant['slug']); ?>';
}

// 追加モーダル
function openAddModal(column) {
    currentAddColumn = column;
    document.getElementById('add-modal').classList.add('active');
}

function closeAddModal() {
    document.getElementById('add-modal').classList.remove('active');
}

// セクション追加
function addSection(type) {
    fetch('add_section.php?tenant=<?php echo urlencode($tenant['slug']); ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            sectionType: type,
            defaultColumn: currentAddColumn
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeAddModal();
            location.reload();
        } else {
            alert('追加に失敗しました');
        }
    });
}
</script>

</body>
</html>
