<?php
/**
 * pullcass - 店舗管理画面
 * 料金表管理 - 編集ページ
 */

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

// ログイン認証チェック
requireTenantAdminLogin();

// データベース接続
$pdo = getPlatformDb();
if (!$pdo) {
    die('データベースに接続できません。');
}

$setId = intval($_GET['id'] ?? 0);
$tenantSlug = $_GET['tenant'] ?? $_SESSION['manage_tenant_slug'] ?? null;

if (!$setId) {
    header('Location: index.php?tenant=' . urlencode($tenantSlug));
    exit;
}

// 料金セット情報を取得
try {
    $stmt = $pdo->prepare("SELECT * FROM price_sets WHERE id = ?");
    $stmt->execute([$setId]);
    $priceSet = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$priceSet) {
        header('Location: index.php?tenant=' . urlencode($tenantSlug));
        exit;
    }
    
    // コンテンツ一覧を取得
    $stmt = $pdo->prepare("
        SELECT * FROM price_contents 
        WHERE set_id = ? 
        ORDER BY display_order ASC
    ");
    $stmt->execute([$setId]);
    $contents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 各コンテンツの詳細を取得
    foreach ($contents as &$content) {
        if ($content['content_type'] === 'price_table') {
            $stmt = $pdo->prepare("SELECT * FROM price_tables WHERE content_id = ?");
            $stmt->execute([$content['id']]);
            $content['detail'] = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($content['detail']) {
                $stmt = $pdo->prepare("SELECT * FROM price_rows WHERE table_id = ? ORDER BY display_order ASC");
                $stmt->execute([$content['detail']['id']]);
                $content['detail']['rows'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } elseif ($content['content_type'] === 'banner') {
            $stmt = $pdo->prepare("SELECT * FROM price_banners WHERE content_id = ?");
            $stmt->execute([$content['id']]);
            $content['detail'] = $stmt->fetch(PDO::FETCH_ASSOC);
        } elseif ($content['content_type'] === 'text') {
            $stmt = $pdo->prepare("SELECT * FROM price_texts WHERE content_id = ?");
            $stmt->execute([$content['id']]);
            $content['detail'] = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    unset($content);
    
} catch (PDOException $e) {
    $error = "データの取得に失敗しました: " . $e->getMessage();
}

$pageTitle = h($priceSet['set_name']) . ' 編集';
require_once __DIR__ . '/../includes/header.php';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script src="/assets/tinymce/tinymce.min.js"></script>
<script src="/assets/js/tinymce-config.js?v=<?php echo time(); ?>"></script>
<style>
    .set-info {
        background: var(--card-bg);
        border-radius: 12px;
        padding: 20px 25px;
        margin-bottom: 30px;
        border: 1px solid var(--border-color);
    }

    .set-info-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }

    .set-name-input {
        font-size: 1.4rem;
        font-weight: 600;
        background: transparent;
        border: none;
        color: var(--text-light);
        width: 100%;
        max-width: 400px;
        padding: 8px 0;
        border-bottom: 2px solid transparent;
    }

    .set-name-input:focus {
        outline: none;
        border-bottom-color: var(--primary);
    }

    .date-inputs {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
    }

    .date-group {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .date-group label {
        color: var(--text-muted);
        font-size: 0.9rem;
    }

    .date-group input {
        padding: 8px 12px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        background: var(--darker);
        color: var(--text-light);
        font-size: 0.9rem;
    }

    .date-group input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(255, 107, 157, 0.2);
    }

    .action-bar {
        display: flex;
        gap: 15px;
        margin-bottom: 30px;
        justify-content: flex-end;
        flex-wrap: wrap;
    }

    .action-buttons {
        display: flex;
        gap: 10px;
    }

    /* コンテンツリスト */
    .content-list {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .content-card {
        background: var(--card-bg);
        border-radius: 12px;
        padding: 20px;
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
        cursor: grab;
    }

    .content-card:active {
        cursor: grabbing;
    }

    .content-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(255, 107, 157, 0.2);
        border-color: var(--primary);
    }

    .content-card.sortable-ghost {
        opacity: 0.4;
    }

    .content-card.sortable-drag {
        opacity: 0.8;
        box-shadow: 0 10px 30px rgba(255, 107, 157, 0.4);
    }

    /* アコーディオン機能 */
    .content-card.collapsed .content-body {
        display: none;
    }

    .content-card.collapsed .content-header {
        margin-bottom: 0;
    }

    .content-body {
        animation: slideDown 0.3s ease;
    }

    .content-body * {
        pointer-events: auto;
    }

    @keyframes slideDown {
        from {
            opacity: 0;
            max-height: 0;
        }
        to {
            opacity: 1;
            max-height: 2000px;
        }
    }

    .toggle-btn {
        padding: 6px;
        border-radius: 6px;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
        background: rgba(255, 107, 157, 0.2);
        color: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .toggle-btn:hover {
        background: rgba(255, 107, 157, 0.3);
    }

    .toggle-btn i {
        transition: transform 0.3s ease;
    }

    .content-card.collapsed .toggle-btn i {
        transform: rotate(-90deg);
    }

    .content-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 15px;
        gap: 15px;
        cursor: pointer;
    }

    .content-header-left {
        display: flex;
        align-items: center;
        gap: 15px;
        flex: 1;
    }


    .content-type-badge {
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .badge-table {
        background: rgba(39, 163, 235, 0.2);
        color: #27a3eb;
    }

    .badge-banner {
        background: rgba(255, 152, 0, 0.2);
        color: #FF9800;
    }

    .badge-text {
        background: rgba(16, 185, 129, 0.2);
        color: var(--success);
    }

    .input-label {
        font-size: 0.8rem;
        color: var(--text-muted);
        margin-right: 5px;
    }

    .admin-title-input {
        background: transparent;
        border: none;
        color: var(--text-light);
        font-size: 1.1rem;
        font-weight: 600;
        width: 100%;
        max-width: 300px;
        padding: 5px 0;
        border-bottom: 1px dashed transparent;
    }

    .admin-title-input:focus {
        outline: none;
        border-bottom-color: var(--primary);
    }

    .content-actions {
        display: flex;
        gap: 8px;
    }

    .btn-icon {
        padding: 8px;
        border-radius: 8px;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
        background: rgba(255, 255, 255, 0.05);
        color: var(--text-muted);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .btn-icon:hover {
        background: rgba(255, 255, 255, 0.1);
        color: var(--text-light);
    }

    .btn-icon.delete:hover {
        background: rgba(239, 68, 68, 0.2);
        color: var(--danger);
    }

    /* 料金表スタイル */
    .price-table-editor {
        margin-top: 15px;
    }

    .table-name-wrapper {
        display: flex;
        flex-direction: column;
        align-items: center;
        margin-bottom: 15px;
    }

    .table-name-label {
        font-size: 0.8rem;
        color: var(--text-muted);
        margin-bottom: 5px;
    }

    .table-name-input {
        width: 100%;
        padding: 12px 15px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        background: var(--darker);
        color: var(--text-light);
        font-size: 1rem;
        font-weight: 600;
        text-align: center;
    }

    .table-name-input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(255, 107, 157, 0.2);
    }

    .table-headers-row {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 15px;
        margin-top: 15px;
    }
    
    .table-headers-label {
        font-size: 0.85rem;
        color: var(--text-muted);
        white-space: nowrap;
    }
    
    .table-headers-inputs {
        display: flex;
        gap: 10px;
        flex: 1;
    }
    
    .header-input {
        flex: 1;
        padding: 10px 12px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        background: var(--darker);
        color: var(--text-light);
        font-size: 0.95rem;
    }
    
    .header-input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(255, 107, 157, 0.2);
    }
    
    .header-input::placeholder {
        color: var(--text-muted);
    }

    .price-rows {
        margin-bottom: 15px;
    }

    .price-row {
        display: flex;
        gap: 10px;
        align-items: center;
        margin-bottom: 10px;
    }

    .price-row input {
        flex: 1;
        padding: 10px 12px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        background: var(--darker);
        color: var(--text-light);
        font-size: 0.95rem;
    }

    .price-row input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(255, 107, 157, 0.2);
    }

    .price-row .row-drag {
        cursor: grab;
        color: var(--text-muted);
    }

    .price-row .btn-icon {
        flex-shrink: 0;
    }

    .add-row-btn {
        width: 100%;
        padding: 10px;
        border: 2px dashed var(--border-color);
        border-radius: 8px;
        background: transparent;
        color: var(--primary);
        font-size: 0.9rem;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
    }

    .add-row-btn:hover {
        border-color: var(--primary);
        background: rgba(255, 107, 157, 0.1);
    }

    .row-buttons {
        display: flex;
        gap: 10px;
        margin-top: 10px;
    }

    .row-buttons .add-row-btn {
        flex: 1;
    }

    .table-note {
        width: 100%;
        padding: 12px 15px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        background: var(--darker);
        color: var(--text-light);
        font-size: 0.9rem;
        min-height: 80px;
        resize: vertical;
        margin-top: 15px;
    }

    .table-note:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(255, 107, 157, 0.2);
    }

    /* バナー編集 */
    .banner-editor {
        margin-top: 15px;
    }

    .banner-preview {
        max-width: 400px;
        margin-bottom: 15px;
        border-radius: 10px;
        overflow: hidden;
    }

    .banner-preview img {
        width: 100%;
        display: block;
    }

    .banner-upload-area {
        border: 2px dashed var(--border-color);
        border-radius: 10px;
        padding: 30px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        margin-bottom: 15px;
    }

    .banner-upload-area:hover {
        border-color: var(--primary);
        background: rgba(255, 107, 157, 0.1);
    }

    .banner-upload-area i {
        font-size: 48px;
        color: var(--text-muted);
        margin-bottom: 10px;
    }

    .banner-inputs {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .banner-inputs input {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        background: var(--darker);
        color: var(--text-light);
        font-size: 0.9rem;
    }

    .banner-inputs input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(255, 107, 157, 0.2);
    }

    /* テキスト編集 */
    .text-editor {
        margin-top: 15px;
    }

    .text-editor textarea {
        width: 100%;
        padding: 15px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        background: var(--darker);
        color: var(--text-light);
        font-size: 0.95rem;
        min-height: 150px;
        resize: vertical;
        line-height: 1.6;
    }

    .text-editor textarea:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(255, 107, 157, 0.2);
    }

    /* TinyMCE用スタイル */
    .text-editor .tox-tinymce {
        border-radius: 8px;
        border: 1px solid var(--border-color) !important;
    }

    .editor-wrapper {
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        border: 2px solid var(--border-color);
    }

    /* コンテンツ追加ボタン */
    .add-content-btn {
        width: 100%;
        padding: 20px;
        border: 2px dashed var(--border-color);
        border-radius: 12px;
        background: transparent;
        color: var(--primary);
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        margin-top: 20px;
    }

    .add-content-btn:hover {
        border-color: var(--primary);
        background: rgba(255, 107, 157, 0.1);
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
    }

    .modal-overlay.active {
        display: flex;
    }

    .modal-content {
        background: var(--card-bg);
        border-radius: 20px;
        padding: 30px;
        max-width: 500px;
        width: 90%;
        border: 1px solid var(--border-color);
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
    }

    .modal-header {
        font-size: 1.3rem;
        font-weight: 600;
        color: var(--text-light);
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
        border: 2px solid var(--border-color);
        border-radius: 12px;
        color: var(--text-light);
        font-size: 1.1rem;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .content-type-btn:hover {
        background: rgba(255, 107, 157, 0.2);
        border-color: var(--primary);
        transform: translateX(10px);
    }

    .content-type-btn i {
        font-size: 32px;
        color: var(--primary);
    }

    .content-type-info {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .content-type-title {
        font-weight: 600;
    }

    .content-type-desc {
        font-size: 0.85rem;
        color: var(--text-muted);
    }

    .modal-close {
        margin-top: 20px;
        padding: 12px;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        color: var(--text-light);
        cursor: pointer;
        width: 100%;
        transition: all 0.3s ease;
    }

    .modal-close:hover {
        background: rgba(255, 255, 255, 0.15);
    }

    .saving-indicator {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: rgba(16, 185, 129, 0.9);
        color: white;
        padding: 12px 20px;
        border-radius: 25px;
        display: none;
        align-items: center;
        gap: 8px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        z-index: 9999;
    }

    .saving-indicator.show {
        display: flex;
    }

    @media (max-width: 768px) {
        .action-bar {
            flex-direction: column;
        }
        
        .action-buttons {
            flex-wrap: wrap;
        }
        
        .date-inputs {
            flex-direction: column;
        }
        
        .price-row {
            flex-wrap: wrap;
        }
    }
</style>

<div class="content-wrapper">
    <div class="page-header">
        <h1><i class="fas fa-yen-sign"></i> 料金表編集</h1>
        <p class="subtitle"><?php echo $priceSet['set_type'] === 'regular' ? '平常期間料金' : '特別期間料金'; ?>の編集</p>
    </div>

    <!-- 料金セット情報 -->
    <div class="set-info">
        <div class="set-info-header">
            <input type="text" class="set-name-input" id="setName" value="<?php echo h($priceSet['set_name']); ?>" placeholder="料金セット名">
        </div>
        <?php if ($priceSet['set_type'] === 'special'): ?>
        <div class="date-inputs">
            <div class="date-group">
                <label>開始日時：</label>
                <input type="datetime-local" id="startDatetime" value="<?php echo date('Y-m-d\TH:i', strtotime($priceSet['start_datetime'])); ?>">
            </div>
            <div class="date-group">
                <label>終了日時：</label>
                <input type="datetime-local" id="endDatetime" value="<?php echo date('Y-m-d\TH:i', strtotime($priceSet['end_datetime'])); ?>">
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- アクションバー -->
    <div class="action-bar">
        <div class="action-buttons">
            <button onclick="openPreview('pc')" class="btn btn-secondary">
                <i class="fas fa-desktop"></i> PC版プレビュー
            </button>
            <button onclick="openPreview('mobile')" class="btn btn-secondary">
                <i class="fas fa-mobile-alt"></i> スマホ版プレビュー
            </button>
            <button class="btn btn-primary" onclick="saveAll()">
                <i class="fas fa-save"></i> 保存
            </button>
            <button class="btn btn-primary" onclick="publishPrices()">
                <i class="fas fa-paper-plane"></i> 公開
            </button>
        </div>
    </div>

    <!-- コンテンツ一覧 -->
    <div class="content-list" id="contentList">
        <?php foreach ($contents as $content): ?>
        <div class="content-card collapsed" data-id="<?php echo $content['id']; ?>" data-type="<?php echo $content['content_type']; ?>">
            <div class="content-header">
                <div class="content-header-left" onclick="toggleCard(this.closest('.content-card'))">
                    <button class="toggle-btn" onclick="event.stopPropagation(); toggleCard(this.closest('.content-card'))">
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <span class="content-type-badge badge-<?php echo $content['content_type'] === 'price_table' ? 'table' : ($content['content_type'] === 'banner' ? 'banner' : 'text'); ?>">
                        <?php 
                        echo $content['content_type'] === 'price_table' ? '料金表' : 
                             ($content['content_type'] === 'banner' ? 'バナー' : 'テキスト');
                        ?>
                    </span>
                    <span class="input-label">管理名:</span>
                    <input type="text" class="admin-title-input" value="<?php echo h($content['admin_title'] ?? ''); ?>" placeholder="管理名を入力" data-field="admin_title" onclick="event.stopPropagation();">
                </div>
                <div class="content-actions" onclick="event.stopPropagation()">
                    <button class="btn-icon delete" onclick="deleteContent(<?php echo $content['id']; ?>)" title="削除">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>

            <div class="content-body">
            <?php if ($content['content_type'] === 'price_table' && $content['detail']): ?>
            <div class="price-table-editor" data-table-id="<?php echo $content['detail']['id']; ?>">
                <div class="table-name-wrapper">
                    <span class="table-name-label">表示名:</span>
                    <input type="text" class="table-name-input" value="<?php echo h($content['detail']['table_name']); ?>" placeholder="表示名を入力" data-field="table_name">
                </div>
                
                <div class="table-headers-row">
                    <span class="table-headers-label">列名:</span>
                    <div class="table-headers-inputs">
                        <input type="text" class="header-input" value="<?php echo h($content['detail']['column1_header'] ?? ''); ?>" placeholder="左列タイトル" data-field="column1_header">
                        <input type="text" class="header-input" value="<?php echo h($content['detail']['column2_header'] ?? ''); ?>" placeholder="右列タイトル" data-field="column2_header">
                    </div>
                </div>
                
                <div class="price-rows" data-table-id="<?php echo $content['detail']['id']; ?>">
                    <?php if (!empty($content['detail']['rows'])): ?>
                    <?php foreach ($content['detail']['rows'] as $row): ?>
                    <div class="price-row" data-row-id="<?php echo $row['id']; ?>">
                        <i class="fas fa-grip-vertical row-drag"></i>
                        <input type="text" value="<?php echo h($row['time_label']); ?>" placeholder="時間（例：60分）" data-field="time_label">
                        <input type="text" value="<?php echo h($row['price_label']); ?>" placeholder="料金（例：12,000円）" data-field="price_label">
                        <button class="btn-icon delete" onclick="deleteRow(this, <?php echo $row['id']; ?>)" title="削除">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="row-buttons">
                    <button class="add-row-btn" onclick="addRow(this, <?php echo $content['detail']['id']; ?>)">
                        <i class="fas fa-plus"></i>
                        1行追加
                    </button>
                    <button class="add-row-btn duplicate" onclick="duplicateLastRow(this, <?php echo $content['detail']['id']; ?>)">
                        <i class="fas fa-copy"></i>
                        1行複製
                    </button>
                </div>
                
                <textarea class="table-note" placeholder="追記事項（HTML可）" data-field="note"><?php echo h($content['detail']['note'] ?? ''); ?></textarea>
            </div>
            <?php elseif ($content['content_type'] === 'banner'): ?>
            <div class="banner-editor" data-banner-id="<?php echo $content['detail']['id'] ?? ''; ?>">
                <?php if (!empty($content['detail']['image_path'])): ?>
                <div class="banner-preview">
                    <img src="<?php echo h($content['detail']['image_path']); ?>" alt="">
                </div>
                <?php endif; ?>
                
                <div class="banner-upload-area" onclick="document.getElementById('bannerFile<?php echo $content['id']; ?>').click()">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <p>クリックして画像を選択</p>
                    <input type="file" id="bannerFile<?php echo $content['id']; ?>" accept="image/*" style="display:none;" onchange="previewBannerImage(this, <?php echo $content['id']; ?>)">
                </div>
                
                <div class="banner-inputs">
                    <input type="text" value="<?php echo h($content['detail']['image_path'] ?? ''); ?>" placeholder="画像URL（アップロードまたはURL入力）" data-field="image_path">
                    <input type="text" value="<?php echo h($content['detail']['link_url'] ?? ''); ?>" placeholder="リンクURL（任意）" data-field="link_url">
                    <input type="text" value="<?php echo h($content['detail']['alt_text'] ?? ''); ?>" placeholder="alt属性（任意）" data-field="alt_text">
                </div>
            </div>
            <?php elseif ($content['content_type'] === 'text'): ?>
            <div class="text-editor" data-text-id="<?php echo $content['detail']['id'] ?? ''; ?>">
                <div class="editor-wrapper">
                    <textarea id="textEditor_<?php echo $content['id']; ?>" class="tinymce-text" data-field="content"><?php echo h($content['detail']['content'] ?? ''); ?></textarea>
                </div>
            </div>
            <?php endif; ?>
            </div><!-- /.content-body -->
        </div>
        <?php endforeach; ?>
    </div>

    <!-- コンテンツ追加ボタン -->
    <button class="add-content-btn" onclick="openAddModal()">
        <i class="fas fa-plus-circle"></i>
        コンテンツを追加
    </button>
</div>

<!-- コンテンツタイプ選択モーダル -->
<div id="addModal" class="modal-overlay" onclick="if(event.target === this) closeModal()">
    <div class="modal-content">
        <div class="modal-header">
            <i class="fas fa-plus-circle"></i>
            コンテンツタイプを選択
        </div>
        <div class="modal-body">
            <button class="content-type-btn" onclick="addContent('price_table')">
                <i class="fas fa-table"></i>
                <div class="content-type-info">
                    <div class="content-type-title">料金表</div>
                    <div class="content-type-desc">時間と料金の2列テーブル</div>
                </div>
            </button>
            
            <button class="content-type-btn" onclick="addContent('banner')">
                <i class="fas fa-image"></i>
                <div class="content-type-info">
                    <div class="content-type-title">バナー画像</div>
                    <div class="content-type-desc">画像とリンクを設定</div>
                </div>
            </button>
            
            <button class="content-type-btn" onclick="addContent('text')">
                <i class="fas fa-file-alt"></i>
                <div class="content-type-info">
                    <div class="content-type-title">テキスト</div>
                    <div class="content-type-desc">HTML対応テキストコンテンツ</div>
                </div>
            </button>
        </div>
        <button class="modal-close" onclick="closeModal()">キャンセル</button>
    </div>
</div>

<!-- 保存中インジケーター -->
<div class="saving-indicator" id="savingIndicator">
    <i class="fas fa-check-circle"></i>
    保存しました
</div>

<script>
    const setId = <?php echo $setId; ?>;
    const setType = '<?php echo $priceSet['set_type']; ?>';
    const TENANT_SLUG = <?php echo json_encode($tenantSlug, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    
    // プレビューを別ウィンドウで開く
    function openPreview(mode) {
        let url, windowName, windowFeatures;
        if (mode === 'mobile') {
            url = '/app/front/system_preview_mobile.php?tenant=' + encodeURIComponent(TENANT_SLUG) + '&set_id=' + setId;
            windowName = 'priceSystemPreviewMobile';
            windowFeatures = 'width=550,height=950,scrollbars=yes,resizable=yes';
        } else {
            url = '/app/front/system_preview_pc.php?tenant=' + encodeURIComponent(TENANT_SLUG) + '&set_id=' + setId;
            windowName = 'priceSystemPreviewPC';
            windowFeatures = 'width=1200,height=900,scrollbars=yes,resizable=yes';
        }
        window.open(url, windowName, windowFeatures);
    }

    // TinyMCE初期化関数
    function initTinyMCEForText(selector) {
        const priceManageConfig = addImageUploadConfig(TinyMCEConfig.full, '/app/manage/price_manage/api/upload_image.php?tenant=<?php echo h($tenantSlug); ?>', '/');
        
        tinymce.init({
            selector: selector,
            height: 500,
            ...priceManageConfig,
            content_style: 'body { font-family: "Hiragino Kaku Gothic ProN", "Hiragino Sans", "Meiryo", sans-serif; font-size: 16px; line-height: 1.4; margin: 0; padding: 20px; box-sizing: border-box; } .page-background { padding: 20px; margin: -20px; box-sizing: border-box; } p { margin: 0 0 0.5em 0; padding: 0; font-size: 16px; } h1, h2, h3, h4, h5, h6 { margin: 0 0 0.5em 0; padding: 0; font-weight: bold; display: block; } h1 { font-size: 24px; } h2 { font-size: 20px; } h3 { font-size: 18px; } h4 { font-size: 16px; } h5 { font-size: 14px; } h6 { font-size: 14px; } ul, ol { margin: 0 0 0.5em 0; padding: 0 0 0 1.5em; } li { margin: 0; padding: 0; font-size: 16px; } img { max-width: 100%; height: auto; } img.img-align-left { float: left !important; margin: 0 15px 10px 0 !important; } img.img-align-center { display: block !important; margin: 10px auto !important; float: none !important; } img.img-align-right { float: right !important; margin: 0 0 10px 15px !important; }',
        });
    }

    // 既存のテキストエディタにTinyMCEを初期化
    document.addEventListener('DOMContentLoaded', function() {
        const textEditors = document.querySelectorAll('.tinymce-text');
        if (textEditors.length > 0) {
            textEditors.forEach(textarea => {
                initTinyMCEForText('#' + textarea.id);
            });
        }
    });

    // アコーディオン開閉機能
    function toggleCard(card) {
        const isCollapsed = card.classList.contains('collapsed');
        
        // 他のカードを閉じる
        document.querySelectorAll('.content-card:not(.collapsed)').forEach(otherCard => {
            if (otherCard !== card) {
                otherCard.classList.add('collapsed');
            }
        });
        
        // クリックしたカードの開閉を切り替え
        if (isCollapsed) {
            card.classList.remove('collapsed');
            
            // テキストエディタの場合、TinyMCEを再描画（表示崩れ防止）
            const textarea = card.querySelector('.tinymce-text');
            if (textarea && tinymce.get(textarea.id)) {
                setTimeout(() => {
                    tinymce.get(textarea.id).execCommand('mceAutoResize');
                }, 100);
            }
        } else {
            card.classList.add('collapsed');
        }
    }

    // 新規追加時に開いた状態にする（URLパラメータをチェック）
    function openNewlyCreatedCard() {
        const urlParams = new URLSearchParams(window.location.search);
        const newContentId = urlParams.get('new');
        if (newContentId) {
            const newCard = document.querySelector(`.content-card[data-id="${newContentId}"]`);
            if (newCard) {
                toggleCard(newCard);
                // 新しく作成したカードの位置にスクロール
                setTimeout(() => {
                    newCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 100);
            }
            // URLからパラメータを削除
            window.history.replaceState({}, document.title, window.location.pathname + '?tenant=<?php echo h($tenantSlug); ?>&id=' + setId);
        }
    }

    document.addEventListener('DOMContentLoaded', openNewlyCreatedCard);

    // Sortable.js初期化（コンテンツ並び替え）
    const contentList = new Sortable(document.getElementById('contentList'), {
        animation: 150,
        draggable: '.content-card',
        filter: '.toggle-btn, .btn-icon, .admin-title-input, input, textarea, button, .add-row-btn, .banner-upload-area, .tox-tinymce, .tox-tinymce-aux',
        preventOnFilter: true,
        ghostClass: 'sortable-ghost',
        dragClass: 'sortable-drag',
        onEnd: function() {
            saveOrder();
        }
    });

    // 各料金表の行にもSortableを初期化
    document.querySelectorAll('.price-rows').forEach(el => {
        new Sortable(el, {
            animation: 150,
            handle: '.row-drag',
            ghostClass: 'sortable-ghost',
            onEnd: function() {
                saveRowOrder(el.dataset.tableId);
            }
        });
    });

    function openAddModal() {
        document.getElementById('addModal').classList.add('active');
    }

    function closeModal() {
        document.getElementById('addModal').classList.remove('active');
    }

    function addContent(type) {
        closeModal();
        
            fetch('add_content.php?tenant=<?php echo h($tenantSlug); ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    set_id: setId,
                    content_type: type
                })
            })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // 新規作成したコンテンツIDをURLに追加してリロード
                window.location.href = `edit.php?tenant=<?php echo h($tenantSlug); ?>&id=${setId}&new=${data.content_id}`;
            } else {
                alert('追加に失敗しました: ' + (data.message || '不明なエラー'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('追加に失敗しました');
        });
    }

    function deleteContent(contentId) {
        if (!confirm('このコンテンツを削除してもよろしいですか？')) {
            return;
        }

        fetch('delete_content.php?tenant=<?php echo h($tenantSlug); ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: contentId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.querySelector(`[data-id="${contentId}"]`).remove();
            } else {
                alert('削除に失敗しました');
            }
        });
    }

    function addRow(btn, tableId) {
        const rowButtons = btn.closest('.row-buttons');
        const rowsContainer = rowButtons.previousElementSibling;
        const newRowHtml = `
            <div class="price-row" data-row-id="new">
                <i class="fas fa-grip-vertical row-drag"></i>
                <input type="text" value="" placeholder="時間（例：60分）" data-field="time_label">
                <input type="text" value="" placeholder="料金（例：12,000円）" data-field="price_label">
                <button class="btn-icon delete" onclick="deleteRow(this, null)" title="削除">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        rowsContainer.insertAdjacentHTML('beforeend', newRowHtml);
        
        // 新しい行を保存
        const newRow = rowsContainer.lastElementChild;
        saveNewRow(tableId, newRow);
    }

    function duplicateLastRow(btn, tableId) {
        const rowButtons = btn.closest('.row-buttons');
        const rowsContainer = rowButtons.previousElementSibling;
        const rows = rowsContainer.querySelectorAll('.price-row');
        
        let timeValue = '';
        let priceValue = '';
        
        // 最後の行のデータを取得
        if (rows.length > 0) {
            const lastRow = rows[rows.length - 1];
            const timeInput = lastRow.querySelector('input[data-field="time_label"]');
            const priceInput = lastRow.querySelector('input[data-field="price_label"]');
            timeValue = timeInput ? timeInput.value : '';
            priceValue = priceInput ? priceInput.value : '';
        }
        
        const newRowHtml = `
            <div class="price-row" data-row-id="new">
                <i class="fas fa-grip-vertical row-drag"></i>
                <input type="text" value="${escapeHtml(timeValue)}" placeholder="時間（例：60分）" data-field="time_label">
                <input type="text" value="${escapeHtml(priceValue)}" placeholder="料金（例：12,000円）" data-field="price_label">
                <button class="btn-icon delete" onclick="deleteRow(this, null)" title="削除">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        rowsContainer.insertAdjacentHTML('beforeend', newRowHtml);
        
        // 新しい行を保存
        const newRow = rowsContainer.lastElementChild;
        saveNewRow(tableId, newRow);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function saveNewRow(tableId, rowElement) {
            fetch('add_row.php?tenant=<?php echo h($tenantSlug); ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ table_id: tableId })
            })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                rowElement.dataset.rowId = data.id;
                rowElement.querySelector('.btn-icon.delete').onclick = function() {
                    deleteRow(this, data.id);
                };
            }
        });
    }

    function deleteRow(btn, rowId) {
        const row = btn.closest('.price-row');
        
        if (rowId) {
            fetch('delete_row.php?tenant=<?php echo h($tenantSlug); ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: rowId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    row.remove();
                }
            });
        } else {
            row.remove();
        }
    }

    function saveOrder() {
        const order = Array.from(document.querySelectorAll('.content-card')).map(el => el.dataset.id);
        
        fetch('save_order.php?tenant=<?php echo h($tenantSlug); ?>', {
            method: 'POST',
            headers: { 
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ order: order })
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

    function saveRowOrder(tableId) {
        const container = document.querySelector(`.price-rows[data-table-id="${tableId}"]`);
        const order = Array.from(container.querySelectorAll('.price-row')).map(el => el.dataset.rowId);
        
        fetch('save_row_order.php?tenant=<?php echo h($tenantSlug); ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ table_id: tableId, order: order })
        })
        .then(response => response.json())
        .then(data => {
            // 順序保存は静かに完了
        });
    }

    async function saveAll() {
        const data = {
            set_id: setId,
            set_name: document.getElementById('setName').value,
            contents: []
        };

        if (setType === 'special') {
            data.start_datetime = document.getElementById('startDatetime').value;
            data.end_datetime = document.getElementById('endDatetime').value;
        }

        // まず、選択された画像ファイルをアップロード
        const uploadPromises = [];
        document.querySelectorAll('.content-card[data-type="banner"]').forEach(card => {
            const editor = card.querySelector('.banner-editor');
            const fileInput = editor.querySelector('input[type="file"]');
            if (fileInput && fileInput.files && fileInput.files[0]) {
                const contentId = card.dataset.id;
                const formData = new FormData();
                formData.append('file', fileInput.files[0]);
                formData.append('content_id', contentId);
                
                uploadPromises.push(
                    fetch('upload_banner.php?tenant=<?php echo h($tenantSlug); ?>', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(result => {
                        if (result.success) {
                            // アップロード成功後、画像パスを更新
                            editor.querySelector('[data-field="image_path"]').value = result.path;
                            // プレビューも更新（存在しない場合は作成）
                            let preview = editor.querySelector('.banner-preview');
                            if (!preview) {
                                preview = document.createElement('div');
                                preview.className = 'banner-preview';
                                editor.insertBefore(preview, editor.firstChild);
                            }
                            preview.innerHTML = `<img src="${result.path}" alt="">`;
                        } else {
                            throw new Error(result.message || '画像のアップロードに失敗しました');
                        }
                    })
                );
            }
        });

        // すべての画像アップロードが完了するまで待つ
        try {
            await Promise.all(uploadPromises);
        } catch (error) {
            alert('画像のアップロードに失敗しました: ' + error.message);
            return;
        }

        // データを収集
        document.querySelectorAll('.content-card').forEach(card => {
            const contentData = {
                id: card.dataset.id,
                type: card.dataset.type,
                admin_title: card.querySelector('[data-field="admin_title"]').value
            };

            if (card.dataset.type === 'price_table') {
                const editor = card.querySelector('.price-table-editor');
                contentData.table_name = editor.querySelector('[data-field="table_name"]').value;
                contentData.column1_header = editor.querySelector('[data-field="column1_header"]').value;
                contentData.column2_header = editor.querySelector('[data-field="column2_header"]').value;
                contentData.note = editor.querySelector('[data-field="note"]').value;
                contentData.table_id = editor.dataset.tableId;
                contentData.rows = [];

                editor.querySelectorAll('.price-row').forEach(row => {
                    contentData.rows.push({
                        id: row.dataset.rowId,
                        time_label: row.querySelector('[data-field="time_label"]').value,
                        price_label: row.querySelector('[data-field="price_label"]').value
                    });
                });
            } else if (card.dataset.type === 'banner') {
                const editor = card.querySelector('.banner-editor');
                contentData.banner_id = editor.dataset.bannerId;
                contentData.image_path = editor.querySelector('[data-field="image_path"]').value;
                contentData.link_url = editor.querySelector('[data-field="link_url"]').value;
                contentData.alt_text = editor.querySelector('[data-field="alt_text"]').value;
            } else if (card.dataset.type === 'text') {
                const editor = card.querySelector('.text-editor');
                contentData.text_id = editor.dataset.textId;
                // TinyMCEからコンテンツを取得
                const textarea = editor.querySelector('[data-field="content"]');
                const tinyEditor = tinymce.get(textarea.id);
                if (tinyEditor) {
                    contentData.content = tinyEditor.getContent();
                } else {
                    contentData.content = textarea.value;
                }
            }

            data.contents.push(contentData);
        });

        // データを保存
        fetch('save_all.php?tenant=<?php echo h($tenantSlug); ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                // 保存成功後、すべてのバナーのプレビューをサーバーのパスに更新
                document.querySelectorAll('.content-card[data-type="banner"]').forEach(card => {
                    const editor = card.querySelector('.banner-editor');
                    const imagePathInput = editor.querySelector('[data-field="image_path"]');
                    const imagePath = imagePathInput.value;
                    
                    if (imagePath) {
                        let preview = editor.querySelector('.banner-preview');
                        if (!preview) {
                            preview = document.createElement('div');
                            preview.className = 'banner-preview';
                            editor.insertBefore(preview, editor.firstChild);
                        }
                        preview.innerHTML = `<img src="${imagePath}" alt="">`;
                    }
                });
                
                alert('保存しました！');
            } else {
                alert('保存に失敗しました: ' + (result.message || '不明なエラー'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('保存に失敗しました');
        });
    }

    // 画像プレビュー表示（参考サイトの実装を参考）
    function previewBannerImage(input, contentId) {
        const file = input.files[0];
        if (!file) return;

        const card = input.closest('.content-card');
        const editor = card.querySelector('.banner-editor');
        
        // FileReaderでローカルプレビューを表示
        const reader = new FileReader();
        reader.onload = function(e) {
            // プレビュー更新
            let preview = editor.querySelector('.banner-preview');
            if (!preview) {
                preview = document.createElement('div');
                preview.className = 'banner-preview';
                editor.insertBefore(preview, editor.firstChild);
            }
            preview.innerHTML = `<img src="${e.target.result}" alt="">`;
            
            // ファイルをdata属性に保存（保存時にアップロードするため）
            input.dataset.fileSelected = 'true';
        };
        reader.readAsDataURL(file);
    }

    function publishPrices() {
        if (!confirm('現在の編集内容を公開しますか？\n\n※ 保存していない変更がある場合は、先に「保存」ボタンを押してください。')) {
            return;
        }

        fetch('publish.php?tenant=<?php echo h($tenantSlug); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('公開しました！\n料金ページで確認できます。');
                window.open('/system', '_blank');
            } else {
                alert('公開に失敗しました: ' + (data.message || '不明なエラー'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('公開に失敗しました。');
        });
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
