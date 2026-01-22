<?php
/**
 * 店舗管理画面 - 共通ヘッダー（HTML出力部分）
 * ※POST処理後に読み込むこと
 */

// auth.phpがまだ読み込まれていない場合は読み込む
if (!isset($tenant) || !isset($tenantSlug) || !isset($tenantId)) {
    require_once __DIR__ . '/auth.php';
}

// 現在のページを判定
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$currentDir = basename(dirname($_SERVER['PHP_SELF']));
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? h($pageTitle) . ' - ' : ''; ?><?php echo h($shopName); ?> 様 管理画面 | pullcass</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary: #ff6b9d;
            --primary-dark: #e91e63;
            --secondary: #7c4dff;
            --accent: #27a3eb;
            --success: #4CAF50;
            --danger: #f44336;
            --warning: #ff9800;
            --dark: #1a1a2e;
            --darker: #0f0f1a;
            --card-bg: #16162a;
            --border-color: rgba(255, 255, 255, 0.1);
            --text-light: #ffffff;
            --text-muted: #c8c8d8;
            --sidebar-width: 260px;
        }
        
        body {
            font-family: "Hiragino Kaku Gothic ProN", "Hiragino Sans", "Meiryo", sans-serif;
            background: var(--darker);
            min-height: 100vh;
            display: flex;
            color: var(--text-light);
        }
        
        /* サイドバー */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--dark);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            border-right: 1px solid var(--border-color);
            z-index: 100;
            display: flex;
            flex-direction: column;
        }
        
        .sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid var(--border-color);
            flex-shrink: 0;
        }
        
        .sidebar-logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-light);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .sidebar-logo i {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .sidebar-shop {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 5px;
        }
        
        .sidebar-nav {
            padding: 20px 15px;
            flex: 1;
            overflow-y: auto;
        }
        
        .nav-section {
            margin-bottom: 20px;
        }
        
        .nav-section-title {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 10px 15px;
            margin-bottom: 5px;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            color: var(--text-muted);
            text-decoration: none;
            border-radius: 10px;
            margin-bottom: 5px;
            transition: all 0.2s ease;
        }
        
        .nav-item:hover {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-light);
        }
        
        .nav-item.active {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: var(--text-light);
            font-weight: 600;
        }
        
        .nav-item i {
            width: 20px;
            text-align: center;
        }
        
        .nav-divider {
            border: none;
            border-top: 1px solid var(--border-color);
            margin: 15px 0;
        }
        
        /* メインコンテンツ */
        .main-content {
            margin-left: var(--sidebar-width);
            flex: 1;
            padding: 30px;
            min-height: 100vh;
        }
        
        .page-header {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .page-header h1 {
            font-size: 1.8rem;
            color: var(--text-light);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .page-header h1 i {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .page-header p {
            color: var(--text-muted);
            margin-top: 5px;
        }
        
        /* フォームコンテナ */
        .form-container {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid var(--border-color);
        }
        
        .form-container h2 {
            font-size: 1.2rem;
            margin-bottom: 20px;
            color: var(--text-light);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-light);
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-light);
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--accent);
            background: rgba(255, 255, 255, 0.08);
        }
        
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.4);
        }
        
        .form-help {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 5px;
        }
        
        /* ボタン */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 8px 16px;
            border: none;
            border-radius: 25px;
            font-size: 13px;
            font-weight: 400;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: var(--text-light);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 107, 157, 0.3);
        }
        
        .btn-accent {
            background: var(--accent);
            color: var(--text-light);
        }
        
        .btn-accent:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(39, 163, 235, 0.3);
        }
        
        .btn-success {
            background: var(--success);
            color: var(--text-light);
        }
        
        .btn-danger {
            background: var(--danger);
            color: var(--text-light);
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(244, 67, 54, 0.3);
        }
        
        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-light);
            border: 1px solid var(--border-color);
        }
        
        .btn-sm {
            padding: 8px 16px;
            font-size: 13px;
        }
        
        /* 編集ボタン（青） */
        .edit-title-btn {
            background: #27a3eb;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
        }
        
        .edit-title-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(39, 163, 235, 0.3);
        }
        
        /* 削除ボタン（赤枠線） */
        .delete-section-btn {
            background: rgba(244, 67, 54, 0.1);
            border: 2px solid rgba(244, 67, 54, 0.4);
            color: #f44336;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
        }
        
        .delete-section-btn:hover {
            background: rgba(244, 67, 54, 0.2);
            border-color: #f44336;
            transform: translateY(-2px);
        }
        
        /* アラート */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background: rgba(76, 175, 80, 0.15);
            border: 1px solid rgba(76, 175, 80, 0.3);
            color: #81c784;
        }
        
        .alert-error {
            background: rgba(244, 67, 54, 0.15);
            border: 1px solid rgba(244, 67, 54, 0.3);
            color: #e57373;
        }
        
        /* リスト */
        .item-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .list-item {
            display: flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border-color);
            border-radius: 15px;
            padding: 20px;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .list-item:hover {
            transform: translateY(-2px);
            border-color: rgba(255, 107, 157, 0.3);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }
        
        .list-item.dragging {
            opacity: 0.5;
        }
        
        .drag-handle {
            position: absolute;
            top: 10px;
            left: 10px;
            color: var(--text-muted);
            cursor: grab;
        }
        
        .drag-handle:active {
            cursor: grabbing;
        }
        
        .list-item-image {
            flex: 0 0 auto;
            margin-right: 20px;
        }
        
        .list-item-image img {
            height: 60px;
            max-width: 150px;
            object-fit: contain;
            border-radius: 8px;
        }
        
        .list-item-info {
            flex: 1;
            min-width: 0;
        }
        
        .list-item-info a {
            color: var(--accent);
            text-decoration: none;
        }
        
        .list-item-info a:hover {
            text-decoration: underline;
        }
        
        .list-item-actions {
            display: flex;
            gap: 10px;
            margin-left: 20px;
        }
        
        /* 画像プレビュー */
        .image-preview {
            margin-top: 10px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 10px;
            border: 1px solid var(--border-color);
        }
        
        .image-preview img {
            max-width: 200px;
            border-radius: 8px;
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
            z-index: 1000;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .modal-overlay.active {
            display: flex;
        }
        
        .modal-content {
            background: var(--dark);
            border-radius: 20px;
            padding: 30px;
            max-width: 600px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            border: 1px solid var(--border-color);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .modal-header h2 {
            color: var(--text-light);
            font-size: 1.3rem;
        }
        
        .modal-close {
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 24px;
            cursor: pointer;
            transition: color 0.3s;
        }
        
        .modal-close:hover {
            color: var(--danger);
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }
        
        /* タブ */
        .tab-container {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .tab-btn {
            flex: 1;
            padding: 15px 20px;
            border: 1px solid var(--border-color);
            border-radius: 15px 15px 0 0;
            background: rgba(255, 255, 255, 0.03);
            color: var(--text-muted);
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .tab-btn:hover {
            background: rgba(255, 255, 255, 0.08);
        }
        
        .tab-btn.active {
            background: rgba(39, 163, 235, 0.2);
            color: var(--text-light);
            border-color: var(--accent);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* チェックボックス */
        .checkbox-label {
            display: flex;
            align-items: center;
            cursor: pointer;
        }
        
        .checkbox-label input[type="checkbox"] {
            width: 20px;
            height: 20px;
            margin-right: 10px;
            accent-color: var(--accent);
        }
        
        /* 表示/非表示ボタン */
        .visibility-btn {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .visibility-btn.visible {
            background: var(--success);
            color: white;
        }
        
        .visibility-btn.hidden {
            background: #6c757d;
            color: white;
        }
        
        /* 空の状態 */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: var(--text-muted);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        /* コンテンツカード */
        .content-card {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid var(--border-color);
        }
        
        .content-card h2 {
            font-size: 1.1rem;
            margin-bottom: 20px;
            color: var(--text-light);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .content-card h2 i {
            color: var(--primary);
        }
        
        /* フォーム関連（追加） */
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-light);
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--accent);
            background: rgba(255, 255, 255, 0.08);
        }
        
        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-light);
            font-weight: 500;
        }
        
        .help-text {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 5px;
        }
        
        /* レスポンシブ */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .list-item {
                flex-direction: column;
                text-align: center;
            }
            
            .list-item-image {
                margin-right: 0;
                margin-bottom: 15px;
            }
            
            .list-item-actions {
                margin-left: 0;
                margin-top: 15px;
            }
        }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo"><i class="fas fa-store"></i> 店舗管理</div>
            <div class="sidebar-shop"><?php echo h($shopName); ?> 様</div>
        </div>
        
        <nav class="sidebar-nav">
            <a href="https://<?php echo h($tenant['code']); ?>.pullcass.com/app/front/index.php" class="nav-item" target="_blank">
                <i class="fas fa-globe"></i> サイトを確認
            </a>
            
            <hr class="nav-divider">
            
            <div class="nav-section">
                <a href="/app/manage/?tenant=<?php echo h($tenantSlug); ?>" class="nav-item <?php echo $currentDir === 'manage' && $currentPage === 'index' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-pie"></i> ダッシュボード
                </a>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">情報更新</div>
                <a href="/app/manage/top_banner/?tenant=<?php echo h($tenantSlug); ?>" class="nav-item <?php echo $currentDir === 'top_banner' ? 'active' : ''; ?>">
                    <i class="fas fa-images"></i> トップバナー
                </a>
                <a href="/app/manage/news_ticker/?tenant=<?php echo h($tenantSlug); ?>" class="nav-item <?php echo $currentDir === 'news_ticker' ? 'active' : ''; ?>">
                    <i class="fas fa-bullhorn"></i> ニュースティッカー
                </a>
                <a href="/app/manage/index_layout/?tenant=<?php echo h($tenantSlug); ?>" class="nav-item <?php echo $currentDir === 'index_layout' ? 'active' : ''; ?>">
                    <i class="fas fa-door-open"></i> 認証ページ編集
                </a>
                <a href="/app/manage/top_layout/?tenant=<?php echo h($tenantSlug); ?>" class="nav-item <?php echo $currentDir === 'top_layout' ? 'active' : ''; ?>">
                    <i class="fas fa-th-large"></i> トップページ編集
                </a>
                <a href="/app/manage/reciprocal_links/?tenant=<?php echo h($tenantSlug); ?>" class="nav-item <?php echo $currentDir === 'reciprocal_links' ? 'active' : ''; ?>">
                    <i class="fas fa-link"></i> 相互リンク
                </a>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">キャスト管理</div>
                <a href="/app/manage/cast_data/?tenant=<?php echo h($tenantSlug); ?>" class="nav-item <?php echo $currentDir === 'cast_data' ? 'active' : ''; ?>">
                    <i class="fas fa-sync"></i> スクレイピング
                </a>
                <a href="/app/manage/casts/?tenant=<?php echo h($tenantSlug); ?>" class="nav-item <?php echo $currentDir === 'casts' ? 'active' : ''; ?>">
                    <i class="fas fa-user"></i> キャスト一覧
                </a>
                <a href="/app/manage/schedules/?tenant=<?php echo h($tenantSlug); ?>" class="nav-item <?php echo $currentDir === 'schedules' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-alt"></i> スケジュール
                </a>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">料金・設定</div>
                <a href="/app/manage/price_manage/?tenant=<?php echo h($tenantSlug); ?>" class="nav-item <?php echo $currentDir === 'price_manage' ? 'active' : ''; ?>">
                    <i class="fas fa-yen-sign"></i> 料金表管理
                </a>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">設定</div>
                <a href="/app/manage/themes/?tenant=<?php echo h($tenantSlug); ?>" class="nav-item <?php echo $currentDir === 'themes' ? 'active' : ''; ?>">
                    <i class="fas fa-palette"></i> テーマ設定
                </a>
                <a href="/app/manage/settings/?tenant=<?php echo h($tenantSlug); ?>" class="nav-item <?php echo $currentDir === 'settings' ? 'active' : ''; ?>">
                    <i class="fas fa-cog"></i> 店舗設定
                </a>
            </div>
            
            <hr class="nav-divider">
            
            <a href="/app/manage/logout.php" class="nav-item" style="color: #f87171;">
                <i class="fas fa-sign-out-alt"></i> ログアウト
            </a>
        </nav>
    </aside>
    
    <main class="main-content">
