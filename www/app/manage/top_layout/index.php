<?php
require_once __DIR__ . '/../../../includes/bootstrap.php';

// テナント認証（HTML出力なし）
require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

$pdo = getPlatformDb();
// テナント情報は auth.php で $tenant, $tenantId, $tenantSlug が既にセット済み

// 現在のステータスを判定
// top_layout_sections と top_layout_sections_published を比較
try {
    // 編集中のセクション数
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM top_layout_sections WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $editCount = $stmt->fetchColumn();
    
    // 公開済みのセクション数
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM top_layout_sections_published WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $publishedCount = $stmt->fetchColumn();
    
    // 下書き保存のセクション数
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM top_layout_sections_saved WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $savedCount = $stmt->fetchColumn();
    
    // 公開済みテーブルが空、または内容が異なる場合は「編集中」
    if ($publishedCount == 0) {
        $currentStatus = 'new'; // 未公開
        $statusLabel = '未公開';
        $statusClass = 'status-draft';
    } else {
        // 簡易比較：セクション数とIDのリストで判定
        $stmt = $pdo->prepare("SELECT GROUP_CONCAT(id ORDER BY id) FROM top_layout_sections WHERE tenant_id = ?");
        $stmt->execute([$tenantId]);
        $editIds = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT GROUP_CONCAT(id ORDER BY id) FROM top_layout_sections_published WHERE tenant_id = ?");
        $stmt->execute([$tenantId]);
        $publishedIds = $stmt->fetchColumn();
        
        if ($editIds === $publishedIds) {
            // さらに詳細比較（順序やvisibility）
            $stmt = $pdo->prepare("SELECT MD5(GROUP_CONCAT(CONCAT(id, '-', COALESCE(pc_left_order,''), '-', COALESCE(pc_right_order,''), '-', COALESCE(mobile_order,''), '-', is_visible) ORDER BY id)) FROM top_layout_sections WHERE tenant_id = ?");
            $stmt->execute([$tenantId]);
            $editHash = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("SELECT MD5(GROUP_CONCAT(CONCAT(id, '-', COALESCE(pc_left_order,''), '-', COALESCE(pc_right_order,''), '-', COALESCE(mobile_order,''), '-', is_visible) ORDER BY id)) FROM top_layout_sections_published WHERE tenant_id = ?");
            $stmt->execute([$tenantId]);
            $publishedHash = $stmt->fetchColumn();
            
            if ($editHash === $publishedHash) {
                $currentStatus = 'published'; // 公開済み（変更なし）
                $statusLabel = '公開済み';
                $statusClass = 'status-published';
            } else {
                $currentStatus = 'draft'; // 編集中
                $statusLabel = '編集中（未保存の変更あり）';
                $statusClass = 'status-draft';
            }
        } else {
            $currentStatus = 'draft'; // 編集中
            $statusLabel = '編集中（未保存の変更あり）';
            $statusClass = 'status-draft';
        }
    }
} catch (PDOException $e) {
    $currentStatus = 'unknown';
    $statusLabel = '状態不明';
    $statusClass = 'status-draft';
}

// デフォルトセクションのsection_keyリスト（削除不可なセクション）
// 削除は不可だが、表示/非表示の切り替えとタイトル編集は可能
$defaultSectionKeys = [
    'hero_text',        // トップバナー下テキスト
    'new_cast',         // 新人キャスト
    'today_cast',       // 本日の出勤キャスト
    'history',          // 閲覧履歴
    'videos',           // 動画一覧
    'repeat_ranking',  // リピートランキング
    'attention_ranking' // 注目度ランキング
];

// セクションがデフォルトかどうかを判定する関数
function isDefaultSection($sectionKey, $defaultKeys) {
    return in_array($sectionKey, $defaultKeys);
}

// セクションが存在しない場合、または必要なセクションが不足している場合は追加
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM top_layout_sections WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $sectionCount = $stmt->fetchColumn();
    
    if ($sectionCount == 0) {
        // デフォルトセクションを作成
        require_once __DIR__ . '/../../../includes/top_layout_init.php';
        initTopLayoutSections($pdo, $tenantId);
    } else {
        // 必要なセクションが不足しているかチェック
        $requiredSections = ['hero_text', 'new_cast', 'today_cast', 'videos', 'repeat_ranking', 'attention_ranking', 'history'];
        $stmt = $pdo->prepare("SELECT section_key FROM top_layout_sections WHERE tenant_id = ?");
        $stmt->execute([$tenantId]);
        $existingSections = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $missingSections = array_diff($requiredSections, $existingSections);
        
        if (!empty($missingSections)) {
            // 不足しているセクションを追加
            require_once __DIR__ . '/../../../includes/top_layout_init.php';
            addMissingSections($pdo, $tenantId, $missingSections);
        }
    }
} catch (Exception $e) {
    error_log("デフォルトセクション作成エラー: " . $e->getMessage());
}

// セクション取得
try {
    // トップバナー下テキスト（hero_text）を取得
    $stmt = $pdo->prepare("
        SELECT * FROM top_layout_sections 
        WHERE tenant_id = ? AND section_key = 'hero_text'
        LIMIT 1
    ");
    $stmt->execute([$tenantId]);
    $heroTextSection = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // PC左カラム用セクション
    $stmt = $pdo->prepare("
        SELECT * FROM top_layout_sections 
        WHERE tenant_id = ? AND pc_left_order IS NOT NULL
        ORDER BY pc_left_order ASC
    ");
    $stmt->execute([$tenantId]);
    $draftLeftSections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // PC右カラム用セクション
    $stmt = $pdo->prepare("
        SELECT * FROM top_layout_sections 
        WHERE tenant_id = ? AND pc_right_order IS NOT NULL
        ORDER BY pc_right_order ASC
    ");
    $stmt->execute([$tenantId]);
    $draftRightSections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // スマホ用セクション
    $stmt = $pdo->prepare("
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
    $stmt->execute([$tenantId]);
    $draftMobileSections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "データの取得に失敗しました: " . $e->getMessage();
}

// テナントスラッグをJavaScriptで使用するため、JSON形式で出力
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
    <title><?php echo h($tenant['name']); ?> トップページレイアウト管理</title>
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
        
        .section-key-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            background: rgba(156, 39, 176, 0.2);
            color: #9C27B0;
            font-family: monospace;
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
            font-family: 'Roboto', 'Noto Sans JP', 'Hiragino Sans', 'Hiragino Kaku Gothic ProN', sans-serif;
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
            background: #27a3eb;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .edit-title-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(39, 163, 235, 0.3);
        }

        .add-banner-btn {
            background: rgba(39, 163, 235, 0.1);
            border: 2px dashed rgba(39, 163, 235, 0.4);
            color: #27a3eb;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .add-banner-btn:hover {
            background: rgba(39, 163, 235, 0.2);
            border-color: #27a3eb;
        }

        .delete-section-btn {
            background: rgba(244, 67, 54, 0.1);
            border: 2px solid rgba(244, 67, 54, 0.4);
            color: #f44336;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
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
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .btn-draft:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 255, 255, 0.1);
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

        .mobile-section {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 25px;
            border: 1px solid rgba(255, 255, 255, 0.1);
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

        @media (max-width: 1024px) {
            .columns-container {
                grid-template-columns: 1fr;
            }
            
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
            
            .tabs {
                flex-direction: column;
            }
            
            .tab {
                padding: 12px 20px;
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

        .add-section-btn .material-icons {
            font-size: 20px;
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

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 10px;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body class="admin-body">
<?php include __DIR__ . '/../includes/header.php'; ?>
    <div class="container">
        <div class="header">
            <h1>トップページレイアウト管理</h1>
            <p>トップページのセクション配置を管理<?php if ($currentStatus !== 'published'): ?><span class="status-indicator <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span><?php endif; ?></p>
        </div>

        <!-- アクションボタン（上部） -->
        <div class="action-buttons">
            <button class="btn btn-draft" onclick="saveDraft()">
                <span class="material-icons" style="vertical-align: middle; margin-right: 5px;">save</span>
                下書き保存
            </button>
            <button onclick="openPreview('pc')" class="btn btn-preview" id="top-preview-btn">
                <span class="material-icons" style="vertical-align: middle; margin-right: 5px;" id="top-preview-icon">preview</span>
                <span id="top-preview-text">プレビュー確認</span>
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

        <!-- タブ -->
        <div class="tabs">
            <button class="tab active" data-tab="pc">PC表示設定</button>
            <button class="tab" data-tab="mobile">スマホ表示設定</button>
        </div>

        <!-- PC表示設定タブ -->
        <div class="tab-content active" id="tab-pc">
            
            <!-- トップバナー下テキスト（hero_text） -->
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
                        <button class="edit-title-btn" onclick="window.location.href='hero_text_edit.php?tenant=<?php echo urlencode($tenantSlug); ?>&id=<?php echo $heroTextSection['id']; ?>'">
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
                        左カラム（横スクロール対応）
                    </div>
                    <div class="section-list" id="left-column" data-column="left">
                        <?php foreach ($draftLeftSections as $section): ?>
                        <div class="section-card <?php echo $section['is_visible'] ? '' : 'hidden'; ?>" data-id="<?php echo $section['id']; ?>" data-key="<?php echo $section['section_key']; ?>">
                            <?php $isDefault = isDefaultSection($section['section_key'], $defaultSectionKeys); ?>
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
                                    <button class="edit-title-btn" onclick="window.location.href='title_edit.php?tenant=<?php echo urlencode($tenantSlug); ?>&id=<?php echo $section['id']; ?>'">
                                        <span class="material-icons" style="font-size: 14px; vertical-align: middle;">edit</span>
                                        編集
                                    </button>
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

                <!-- 右カラム -->
                <div class="column-section">
                    <div class="column-title">
                        右カラム（縦スクロール対応）
                    </div>
                    <div class="section-list" id="right-column" data-column="right">
                        <?php foreach ($draftRightSections as $section): ?>
                        <?php $isDefault = isDefaultSection($section['section_key'], $defaultSectionKeys); ?>
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
                                    <button class="edit-title-btn" onclick="window.location.href='title_edit.php?tenant=<?php echo urlencode($tenantSlug); ?>&id=<?php echo $section['id']; ?>'">
                                        <span class="material-icons" style="font-size: 14px; vertical-align: middle;">edit</span>
                                        編集
                                    </button>
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
            </div>
        </div>

        <!-- スマホ表示設定タブ -->
        <div class="tab-content" id="tab-mobile">
            <div class="mobile-section">
                <div class="column-title">
                    スマホ表示順序
                </div>
                <p style="color: rgba(255, 255, 255, 0.7); margin-bottom: 20px; font-size: 0.9rem;">
                    ※ドラッグで並び替え可能です。セクションの編集はPC表示設定タブで行ってください。
                </p>
                
                <!-- H1テキストセクション（固定・並び替え不可） -->
                <?php if ($heroTextSection): ?>
                <?php $heroMobileVisible = isset($heroTextSection['mobile_visible']) ? $heroTextSection['mobile_visible'] : 1; ?>
                <div style="margin-bottom: 15px;">
                    <div style="color: rgba(255,255,255,0.6); font-size: 0.85rem; margin-bottom: 10px; padding-left: 5px;">
                        <span class="material-icons" style="font-size: 16px; vertical-align: middle;">push_pin</span>
                        H1テキスト（固定・最上部に表示）
                    </div>
                    <div class="section-list">
                        <div class="section-card mobile-card <?php echo $heroMobileVisible ? '' : 'hidden'; ?>" data-id="<?php echo $heroTextSection['id']; ?>" style="cursor: default; opacity: 0.7;">
                            <div class="section-info">
                                <span class="material-icons" style="color: rgba(255,255,255,0.2);">lock</span>
                                <div class="section-titles">
                                    <div class="admin-title-label">管理名：<?php echo h($heroTextSection['admin_title']); ?></div>
                                    <div class="title-en" style="color: rgba(255,255,255,0.6); font-size: 0.85rem;">トップページ最上部に表示</div>
                                    <div class="title-ja" style="color: rgba(255,255,255,0.6); font-size: 0.85rem;">H1タイトルと導入文</div>
                                </div>
                            </div>
                            <div class="section-actions">
                                <button class="visibility-toggle <?php echo $heroMobileVisible ? '' : 'hidden'; ?>"
                                        onclick="toggleMobileVisibility(<?php echo $heroTextSection['id']; ?>, this)"
                                        title="<?php echo $heroMobileVisible ? 'スマホで非表示にする' : 'スマホで表示する'; ?>">
                                    <span class="material-icons"><?php echo $heroMobileVisible ? 'visibility' : 'visibility_off'; ?></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- スマホ用セクション（並び替え可能） -->
                <div>
                    <div style="color: rgba(255,255,255,0.6); font-size: 0.85rem; margin-bottom: 10px; padding-left: 5px;">
                        セクション（ドラッグで並び替え）
                    </div>
                    <div class="section-list" id="mobile-list">
                        <?php foreach ($draftMobileSections as $section): ?>
                        <?php if ($section['section_key'] === 'hero_text') continue; // H1は上で固定表示 ?>
                        <?php $mobileVisible = isset($section['mobile_visible']) ? $section['mobile_visible'] : 1; ?>
                        <div class="section-card mobile-card <?php echo $mobileVisible ? '' : 'hidden'; ?>" data-id="<?php echo $section['id']; ?>">
                            <div class="section-info">
                                <div class="section-titles">
                                    <div class="admin-title-label">管理名：<?php echo h($section['admin_title']); ?></div>
                                    <div class="title-en"><?php echo !empty($section['title_en']) ? h($section['title_en']) : '<span style="color: rgba(255,255,255,0.4);">タイトルなし</span>'; ?></div>
                                    <div class="title-ja"><?php echo !empty($section['title_ja']) ? h($section['title_ja']) : '<span style="color: rgba(255,255,255,0.4);">タイトルなし</span>'; ?></div>
                                </div>
                            </div>
                            <div class="section-actions">
                                <button class="visibility-toggle <?php echo $mobileVisible ? '' : 'hidden'; ?>"
                                        onclick="toggleMobileVisibility(<?php echo $section['id']; ?>, this)"
                                        title="<?php echo $mobileVisible ? 'スマホで非表示にする' : 'スマホで表示する'; ?>">
                                    <span class="material-icons"><?php echo $mobileVisible ? 'visibility' : 'visibility_off'; ?></span>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- アクションボタン（下部・共通） -->
        <div class="action-buttons bottom">
            <button class="btn btn-draft" onclick="saveDraft()">
                <span class="material-icons" style="vertical-align: middle; margin-right: 5px;">save</span>
                下書き保存
            </button>
            <button onclick="openPreview('pc')" class="btn btn-preview" id="bottom-preview-btn">
                <span class="material-icons" style="vertical-align: middle; margin-right: 5px;" id="bottom-preview-icon">preview</span>
                <span id="bottom-preview-text">PC版プレビュー</span>
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
        // テナントスラッグをJavaScriptで使用
        const TENANT_SLUG = <?php echo $tenantSlugJson; ?>;
        
        // プレビューを別ウィンドウで開く
        function openPreview(mode) {
            let url, windowName, windowFeatures;
            if (mode === 'mobile') {
                url = '/app/front/top_preview_mobile.php?tenant=' + TENANT_SLUG;
                windowName = 'topLayoutPreviewMobile';
                windowFeatures = 'width=520,height=1100,scrollbars=yes,resizable=yes';
            } else {
                url = '/app/front/top_preview_pc.php?tenant=' + TENANT_SLUG;
                windowName = 'topLayoutPreviewPC';
                windowFeatures = 'width=1400,height=900,scrollbars=yes,resizable=yes';
            }
            window.open(url, windowName, windowFeatures);
        }
        
        // タブ切り替え
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', function() {
                const targetTab = this.getAttribute('data-tab');

                // すべてのタブとコンテンツを非アクティブに
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

                // 選択されたタブとコンテンツをアクティブに
                this.classList.add('active');
                document.getElementById('tab-' + targetTab).classList.add('active');
                
                // 上部・下部プレビューボタンのテキスト・アイコン・モードを切り替え
                const previewIcon = document.getElementById('top-preview-icon');
                const previewText = document.getElementById('top-preview-text');
                const bottomPreviewIcon = document.getElementById('bottom-preview-icon');
                const bottomPreviewText = document.getElementById('bottom-preview-text');
                const topBtn = document.getElementById('top-preview-btn');
                const bottomBtn = document.getElementById('bottom-preview-btn');
                if (targetTab === 'mobile') {
                    previewIcon.textContent = 'phone_iphone';
                    previewText.textContent = 'スマホプレビュー';
                    bottomPreviewIcon.textContent = 'phone_iphone';
                    bottomPreviewText.textContent = 'スマホプレビュー';
                    topBtn.onclick = function() { openPreview('mobile'); };
                    bottomBtn.onclick = function() { openPreview('mobile'); };
                } else {
                    previewIcon.textContent = 'preview';
                    previewText.textContent = 'プレビュー確認';
                    bottomPreviewIcon.textContent = 'preview';
                    bottomPreviewText.textContent = 'PC版プレビュー';
                    topBtn.onclick = function() { openPreview('pc'); };
                    bottomBtn.onclick = function() { openPreview('pc'); };
                }
            });
        });

        // Sortable.js 初期化（左カラム）
        const leftColumn = new Sortable(document.getElementById('left-column'), {
            animation: 150,
            draggable: '.section-card',
            filter: '.visibility-toggle, .edit-title-btn, .add-banner-btn, .delete-section-btn',
            preventOnFilter: true,
            ghostClass: 'sortable-ghost',
            dragClass: 'sortable-drag',
            onEnd: function() {
                initAddButtons(); // ➕ボタンを再配置
                autoSaveDraft();
            }
        });

        // Sortable.js 初期化（右カラム）
        const rightColumn = new Sortable(document.getElementById('right-column'), {
            animation: 150,
            draggable: '.section-card',
            filter: '.visibility-toggle, .edit-title-btn, .add-banner-btn, .delete-section-btn',
            preventOnFilter: true,
            ghostClass: 'sortable-ghost',
            dragClass: 'sortable-drag',
            onEnd: function() {
                initAddButtons(); // ➕ボタンを再配置
                autoSaveDraft();
            }
        });

        // Sortable.js 初期化（スマホ）
        const mobileList = document.getElementById('mobile-list');
        if (mobileList) {
            new Sortable(mobileList, {
                animation: 150,
                draggable: '.section-card',
                filter: '.visibility-toggle',
                preventOnFilter: true,
                ghostClass: 'sortable-ghost',
                dragClass: 'sortable-drag',
                onEnd: function() {
                    autoSaveMobileOrder();
                }
            });
        }

        // 表示/非表示切り替え（PC版）
        function toggleVisibility(id, button) {
            fetch('toggle_visibility.php?tenant=' + TENANT_SLUG, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ id: id, type: 'pc' })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP error! status: ' + response.status);
                }
                return response.json();
            })
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
                alert('表示状態の更新に失敗しました: ' + error.message);
            });
        }
        
        // 表示/非表示切り替え（スマホ版）
        function toggleMobileVisibility(id, button) {
            fetch('toggle_visibility.php?tenant=' + TENANT_SLUG, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ id: id, type: 'mobile' })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP error! status: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    const card = button.closest('.section-card');
                    const icon = button.querySelector('.material-icons');
                    
                    if (data.is_visible) {
                        button.classList.remove('hidden');
                        card.classList.remove('hidden');
                        icon.textContent = 'visibility';
                        button.title = 'スマホで非表示にする';
                    } else {
                        button.classList.add('hidden');
                        card.classList.add('hidden');
                        icon.textContent = 'visibility_off';
                        button.title = 'スマホで表示する';
                    }
                } else {
                    alert('エラー: ' + (data.message || '表示状態の更新に失敗しました。'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('表示状態の更新に失敗しました: ' + error.message);
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

        // 下書き保存（手動）- スナップショットも保存
        function saveDraft() {
            const leftOrder = Array.from(document.querySelectorAll('#left-column .section-card')).map(el => el.dataset.id);
            const rightOrder = Array.from(document.querySelectorAll('#right-column .section-card')).map(el => el.dataset.id);

            fetch('save_order.php?tenant=' + TENANT_SLUG, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    leftOrder: leftOrder,
                    rightOrder: rightOrder,
                    autoSave: false  // 手動保存：スナップショットも保存
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

        // 自動保存（ドラッグ&ドロップ後）- 順序のみ更新、スナップショットは作らない
        function autoSaveDraft() {
            const leftOrder = Array.from(document.querySelectorAll('#left-column .section-card')).map(el => el.dataset.id);
            const rightOrder = Array.from(document.querySelectorAll('#right-column .section-card')).map(el => el.dataset.id);

            fetch('save_order.php?tenant=' + TENANT_SLUG, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    leftOrder: leftOrder,
                    rightOrder: rightOrder,
                    autoSave: true  // 自動保存：順序のみ更新
                })
            })
            .then(response => response.json())
            .then(data => {
                // 順序更新完了
            })
            .catch(error => {
                console.error('Auto-save error:', error);
            });
        }

        // スマホ順序の自動保存
        function autoSaveMobileOrder() {
            const mobileOrder = Array.from(document.querySelectorAll('#mobile-list .section-card')).map(el => el.dataset.id);

            fetch('save_mobile_order.php?tenant=' + TENANT_SLUG, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    mobileOrder: mobileOrder
                })
            })
            .then(response => response.json())
            .then(data => {
                // スマホ順序更新完了
            })
            .catch(error => {
                console.error('Mobile order save error:', error);
            });
        }

        // 公開処理
        function publishLayout() {
            if (!confirm('現在の下書き内容を公開しますか？\ntop.php（本番）に反映されます。')) {
                return;
            }
            
            // 現在のタブを確認
            const activeTab = document.querySelector('.tab.active')?.getAttribute('data-tab') || 'pc';

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
                    alert('レイアウトを公開しました！\ntop.phpで確認できます。\n\n（セクション数: ' + data.section_count + '）');
                    // 公開後、タブに応じてページを開く
                    if (activeTab === 'mobile') {
                        window.open('/app/front/top_mobile.php?tenant=' + TENANT_SLUG, '_blank');
                    } else {
                        window.open('/app/front/top.php?tenant=' + TENANT_SLUG, '_blank');
                    }
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

        // リセット処理（下書き保存または公開時の状態に戻す）
        function resetLayout() {
            if (!confirm('編集内容を破棄して、前回保存した状態に戻しますか？\n\n※下書き保存があればその状態に、なければ公開済みの状態に戻ります。\n\nこの操作は取り消せません。')) {
                return;
            }
            
            // 現在のタブを記録
            const activeTab = document.querySelector('.tab.active')?.getAttribute('data-tab') || 'pc';

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
                    alert(data.message + '\n\n（セクション数: ' + data.section_count + '）');
                    // タブを保持してリロード
                    if (activeTab === 'mobile') {
                        window.location.href = window.location.pathname + '?tenant=' + TENANT_SLUG + '&tab=mobile';
                    } else {
                        location.reload();
                    }
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
            const lists = [
                { id: 'left-column', column: 'left' },
                { id: 'right-column', column: 'right' }
            ];

            lists.forEach(listInfo => {
                const list = document.getElementById(listInfo.id);
                if (!list) return;

                // 既存の➕ボタンを削除
                list.querySelectorAll('.add-section-btn').forEach(btn => btn.remove());

                // 各カードの前に➕ボタンを追加
                const cards = list.querySelectorAll('.section-card');
                cards.forEach((card, index) => {
                    const addBtn = createAddButton(listInfo.column, index);
                    card.parentNode.insertBefore(addBtn, card);
                });

                // 最後に➕ボタンを追加
                const lastAddBtn = createAddButton(listInfo.column, cards.length);
                list.appendChild(lastAddBtn);
            });
        }

        function createAddButton(column, position) {
            const btn = document.createElement('button');
            btn.className = 'add-section-btn';
            btn.innerHTML = '<span class="material-icons">add</span> コンテンツを追加';
            btn.onclick = () => openAddModal(column, position);
            return btn;
        }

        // モーダル関連
        let currentColumn = '';
        let currentPosition = 0;

        function openAddModal(column, position) {
            currentColumn = column;
            currentPosition = position;
            document.getElementById('addModal').classList.add('active');
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.remove('active');
        }

        function selectContentType(type) {
            closeAddModal();
            
            if (type === 'banner') {
                // バナーセクションを作成
                createNewSection('banner');
            } else if (type === 'text') {
                // テキストコンテンツ作成画面へ
                createNewSection('text_content');
            } else if (type === 'embed') {
                // 埋め込みウィジェット作成画面へ
                createNewSection('embed_widget');
            }
        }

        function createNewSection(sectionType) {
            // 仮の管理タイトルを自動生成（編集ページで正式なタイトルを入力）
            let adminTitle = '';
            if (sectionType === 'banner') {
                adminTitle = '新規画像セクション';
            } else if (sectionType === 'text_content') {
                adminTitle = '新規テキストセクション';
            } else if (sectionType === 'embed_widget') {
                adminTitle = '新規リンクパーツセクション';
            }

            const requestData = {
                section_type: sectionType,
                admin_title: adminTitle,
                column: currentColumn,
                position: currentPosition
            };

            fetch('add_section.php?tenant=' + TENANT_SLUG, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(requestData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 成功：編集画面へリダイレクト
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

        // ページ読み込み時に➕ボタンを初期化＆タブ復元
        document.addEventListener('DOMContentLoaded', function() {
            initAddButtons();
            
            // URLパラメータからタブを復元
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            if (tabParam === 'mobile') {
                // スマホタブをアクティブに
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                document.querySelector('.tab[data-tab="mobile"]').classList.add('active');
                document.getElementById('tab-mobile').classList.add('active');
                
                // 上部・下部プレビューボタンも更新
                const previewIcon = document.getElementById('top-preview-icon');
                const previewText = document.getElementById('top-preview-text');
                const bottomPreviewIcon = document.getElementById('bottom-preview-icon');
                const bottomPreviewText = document.getElementById('bottom-preview-text');
                const topBtn = document.getElementById('top-preview-btn');
                const bottomBtn = document.getElementById('bottom-preview-btn');
                if (topBtn) {
                    previewIcon.textContent = 'phone_iphone';
                    previewText.textContent = 'スマホプレビュー';
                    bottomPreviewIcon.textContent = 'phone_iphone';
                    bottomPreviewText.textContent = 'スマホプレビュー';
                    topBtn.onclick = function() { openPreview('mobile'); };
                    bottomBtn.onclick = function() { openPreview('mobile'); };
                }
            }
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
                
                <button class="content-type-btn" onclick="selectContentType('text')">
                    <span class="material-icons">article</span>
                    <div class="content-type-info">
                        <div class="content-type-title">テキスト</div>
                        <div class="content-type-desc">お店紹介などのテキストコンテンツ（HTML対応）</div>
                    </div>
                </button>
                
                <button class="content-type-btn" onclick="selectContentType('embed')">
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
