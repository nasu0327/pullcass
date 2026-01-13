<?php
/**
 * pullcass - スーパー管理画面 ヘッダー
 * 黒基調のモダンなデザイン
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
    <link href="https://fonts.googleapis.com/css2?family=Zen+Kaku+Gothic+New:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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
            --dark: #1a1a2e;
            --darker: #0f0f1a;
            --card-bg: #16162a;
            --border-color: rgba(255, 255, 255, 0.1);
            --text-light: #ffffff;
            --text-muted: #c8c8d8;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --sidebar-width: 260px;
        }
        
        body {
            font-family: 'Zen Kaku Gothic New', sans-serif;
            background: var(--darker);
            min-height: 100vh;
            display: flex;
            color: var(--text-light);
        }
        
        /* サイドバー */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--dark);
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
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -1px;
        }
        
        .sidebar-subtitle {
            font-size: 0.75rem;
            color: var(--text-muted);
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
            color: var(--text-muted);
            padding: 0 10px;
            margin-bottom: 10px;
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
            background: var(--dark);
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
            color: var(--text-muted);
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-info {
            font-size: 0.9rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-logout {
            padding: 8px 16px;
            font-size: 0.85rem;
            color: var(--text-muted);
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-logout:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-light);
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
        
        .page-header .subtitle {
            color: var(--text-muted);
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
            background: var(--card-bg);
            border-radius: 12px;
            padding: 25px;
            display: flex;
            align-items: center;
            gap: 20px;
            border: 1px solid var(--border-color);
        }
        
        .stat-icon {
            font-size: 1.5rem;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 12px;
            color: var(--text-light);
        }
        
        .stat-value {
            display: block;
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-light);
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: var(--text-muted);
        }
        
        /* セクション */
        .content-section {
            background: var(--card-bg);
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
            color: var(--text-light);
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
            color: var(--text-muted);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: rgba(255, 255, 255, 0.02);
        }
        
        .data-table td {
            color: var(--text-light);
        }
        
        .data-table tbody tr:hover {
            background: rgba(255, 255, 255, 0.03);
        }
        
        /* ステータスバッジ */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-active {
            background: rgba(16, 185, 129, 0.2);
            color: #10b981;
        }
        
        .status-inactive {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }
        
        .status-maintenance {
            background: rgba(245, 158, 11, 0.2);
            color: #f59e0b;
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
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: var(--text-light);
            box-shadow: 0 4px 15px rgba(255, 107, 157, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 107, 157, 0.4);
            color: var(--text-light);
        }
        
        .btn-secondary {
            background: rgba(255, 255, 255, 0.05);
            color: var(--text-light);
            border: 1px solid var(--border-color);
        }
        
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-light);
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-muted);
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
            color: var(--text-muted);
        }
        
        .empty-state h3 {
            font-size: 1.2rem;
            color: var(--text-light);
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: var(--text-muted);
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
            background: rgba(245, 158, 11, 0.1);
            border-color: rgba(245, 158, 11, 0.3);
            color: #fbbf24;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border-color: rgba(16, 185, 129, 0.3);
            color: #34d399;
        }
        
        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            border-color: rgba(239, 68, 68, 0.3);
            color: #f87171;
        }
        
        /* テキスト */
        .text-muted {
            color: var(--text-muted);
        }
        
        code {
            background: rgba(255, 255, 255, 0.1);
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
            text-decoration: none;
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
            color: var(--text-light);
            margin-bottom: 8px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            font-size: 1rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            transition: all 0.2s ease;
            background: var(--darker);
            color: var(--text-light);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(255, 107, 157, 0.2);
        }
        
        .form-control::placeholder {
            color: var(--text-muted);
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
