<?php
/**
 * pullcass - スーパー管理画面 ヘッダー
 * 白基調のクリーンなデザイン
 */
$currentAdmin = getCurrentSuperAdmin();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($pageTitle ?? 'スーパー管理画面'); ?> | pullcass</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary: #e94560;
            --primary-light: #ff6b6b;
            --primary-dark: #d63a54;
            --text-dark: #1a1a2e;
            --text-medium: #4a5568;
            --text-light: #718096;
            --border-color: #e2e8f0;
            --bg-light: #f7fafc;
            --bg-white: #ffffff;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --sidebar-width: 260px;
        }
        
        body {
            font-family: 'Noto Sans JP', sans-serif;
            background: var(--bg-light);
            min-height: 100vh;
            display: flex;
            color: var(--text-dark);
        }
        
        /* サイドバー - 白基調 */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--bg-white);
            min-height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 100;
            border-right: 1px solid var(--border-color);
        }
        
        .sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .sidebar-logo {
            font-size: 1.8rem;
            font-weight: 900;
            color: var(--primary);
            letter-spacing: -1px;
        }
        
        .sidebar-subtitle {
            font-size: 0.75rem;
            color: var(--text-light);
            margin-top: 5px;
        }
        
        .sidebar-nav {
            padding: 20px 15px;
        }
        
        .nav-section {
            margin-bottom: 25px;
        }
        
        .nav-section-title {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-light);
            padding: 0 10px;
            margin-bottom: 10px;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            color: var(--text-medium);
            text-decoration: none;
            border-radius: 10px;
            margin-bottom: 5px;
            transition: all 0.2s ease;
        }
        
        .nav-item:hover {
            background: var(--bg-light);
            color: var(--primary);
        }
        
        .nav-item.active {
            background: rgba(233, 69, 96, 0.1);
            color: var(--primary);
            font-weight: 600;
        }
        
        .nav-item-icon {
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }
        
        /* メインコンテンツ */
        .main-content {
            margin-left: var(--sidebar-width);
            flex: 1;
            min-height: 100vh;
        }
        
        .top-bar {
            background: var(--bg-white);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 50;
        }
        
        .breadcrumb {
            font-size: 0.9rem;
            color: var(--text-light);
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-info {
            font-size: 0.9rem;
            color: var(--text-medium);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-logout {
            padding: 8px 16px;
            font-size: 0.85rem;
            color: var(--text-medium);
            background: var(--bg-light);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .btn-logout:hover {
            background: var(--border-color);
        }
        
        .content-wrapper {
            padding: 30px;
        }
        
        /* ダッシュボード */
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .page-header .subtitle {
            color: var(--text-light);
            margin-top: 5px;
        }
        
        /* 統計カード */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--bg-white);
            border-radius: 12px;
            padding: 25px;
            display: flex;
            align-items: center;
            gap: 20px;
            border: 1px solid var(--border-color);
        }
        
        .stat-icon {
            font-size: 2rem;
            color: var(--primary);
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(233, 69, 96, 0.1);
            border-radius: 12px;
        }
        
        .stat-value {
            display: block;
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-dark);
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: var(--text-light);
        }
        
        /* セクション */
        .content-section {
            background: var(--bg-white);
            border-radius: 12px;
            padding: 25px;
            border: 1px solid var(--border-color);
            margin-bottom: 25px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .section-header h2 {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* テーブル */
        .table-container {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th,
        .data-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .data-table th {
            font-weight: 600;
            color: var(--text-light);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: var(--bg-light);
        }
        
        .data-table td {
            color: var(--text-dark);
        }
        
        .data-table tbody tr:hover {
            background: var(--bg-light);
        }
        
        /* ステータスバッジ */
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-active {
            background: #d1fae5;
            color: #059669;
        }
        
        .status-inactive {
            background: #fee2e2;
            color: #dc2626;
        }
        
        .status-maintenance {
            background: #fef3c7;
            color: #d97706;
        }
        
        /* ボタン */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            font-size: 0.9rem;
            font-weight: 500;
            border-radius: 8px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
        }
        
        .btn-primary {
            background: var(--primary);
            color: #fff;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            color: #fff;
        }
        
        .btn-secondary {
            background: var(--bg-light);
            color: var(--text-medium);
            border: 1px solid var(--border-color);
        }
        
        .btn-secondary:hover {
            background: var(--border-color);
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-medium);
        }
        
        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .btn-sm {
            padding: 8px 14px;
            font-size: 0.8rem;
        }
        
        /* 空状態 */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            color: var(--text-light);
        }
        
        .empty-state h3 {
            font-size: 1.2rem;
            color: var(--text-dark);
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: var(--text-light);
            margin-bottom: 25px;
        }
        
        /* アラート */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-warning {
            background: #fffbeb;
            border-color: #fcd34d;
            color: #92400e;
        }
        
        .alert-success {
            background: #ecfdf5;
            border-color: #6ee7b7;
            color: #065f46;
        }
        
        .alert-danger {
            background: #fef2f2;
            border-color: #fca5a5;
            color: #991b1b;
        }
        
        /* テキスト */
        .text-muted {
            color: var(--text-light);
        }
        
        code {
            background: var(--bg-light);
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
            color: var(--primary);
        }
        
        /* アクション */
        .actions {
            display: flex;
            gap: 8px;
        }
        
        /* リンク */
        a {
            color: var(--primary);
        }
        
        a:hover {
            color: var(--primary-dark);
        }
        
        /* フォーム */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-weight: 500;
            color: var(--text-dark);
            margin-bottom: 8px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            font-size: 1rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(233, 69, 96, 0.1);
        }
    </style>
</head>
<body>
    <!-- サイドバー -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">pullcass</div>
            <div class="sidebar-subtitle">スーパー管理画面</div>
        </div>
        
        <nav class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-section-title">メイン</div>
                <a href="/admin/" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'index.php' && dirname($_SERVER['PHP_SELF']) === '/admin' ? 'active' : ''; ?>">
                    <span class="nav-item-icon"><i class="fas fa-chart-pie"></i></span>
                    ダッシュボード
                </a>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">店舗管理</div>
                <a href="/admin/tenants/" class="nav-item">
                    <span class="nav-item-icon"><i class="fas fa-store"></i></span>
                    店舗一覧
                </a>
                <a href="/admin/tenants/create.php" class="nav-item">
                    <span class="nav-item-icon"><i class="fas fa-plus"></i></span>
                    新規店舗登録
                </a>
            </div>
            
            <div class="nav-section">
                <div class="nav-section-title">システム</div>
                <a href="/admin/settings.php" class="nav-item">
                    <span class="nav-item-icon"><i class="fas fa-cog"></i></span>
                    システム設定
                </a>
            </div>
        </nav>
    </aside>
    
    <!-- メインコンテンツ -->
    <main class="main-content">
        <div class="top-bar">
            <div class="breadcrumb">
                スーパー管理画面 / <?php echo h($pageTitle ?? 'ダッシュボード'); ?>
            </div>
            <div class="user-menu">
                <span class="user-info">
                    <i class="fas fa-user-circle"></i>
                    <?php echo h($currentAdmin['name'] ?? $currentAdmin['username'] ?? 'Unknown'); ?>
                </span>
                <a href="/admin/logout.php" class="btn-logout">
                    <i class="fas fa-sign-out-alt"></i> ログアウト
                </a>
            </div>
        </div>
        
        <div class="content-wrapper">
