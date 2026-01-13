<?php
/**
 * pullcass - 店舗管理画面
 * ダッシュボード
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

// テナント判別（URLパラメータまたはセッション）
$tenantSlug = $_GET['tenant'] ?? $_SESSION['manage_tenant_slug'] ?? null;

if (!$tenantSlug) {
    // テナントが指定されていない場合
    ?>
    <!DOCTYPE html>
    <html lang="ja">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>店舗管理画面 | pullcass</title>
        <style>
            body {
                font-family: 'Hiragino Kaku Gothic ProN', sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0;
            }
            .container {
                background: #fff;
                padding: 50px;
                border-radius: 20px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                text-align: center;
                max-width: 500px;
            }
            h1 {
                color: #333;
                margin-bottom: 20px;
            }
            p {
                color: #666;
                margin-bottom: 30px;
            }
            a {
                display: inline-block;
                padding: 15px 30px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: #fff;
                text-decoration: none;
                border-radius: 10px;
                font-weight: 600;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>🏪 店舗管理画面</h1>
            <p>管理する店舗が指定されていません。<br>スーパー管理画面から店舗を選択してください。</p>
            <a href="/admin/">スーパー管理画面へ</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// テナント情報を取得
try {
    $pdo = getPlatformDb();
    $stmt = $pdo->prepare("SELECT * FROM tenants WHERE slug = ? AND status = 'active'");
    $stmt->execute([$tenantSlug]);
    $tenant = $stmt->fetch();
    
    if (!$tenant) {
        die("店舗が見つかりません。");
    }
    
    // セッションに保存
    $_SESSION['manage_tenant_slug'] = $tenantSlug;
    $_SESSION['manage_tenant'] = $tenant;
    
    // テナントDBに接続
    $tenantDb = getTenantDb($tenant['db_name']);
    
    // 統計情報を取得
    $castCount = $tenantDb->query("SELECT COUNT(*) FROM casts WHERE status = 'active'")->fetchColumn();
    $todaySchedule = $tenantDb->query("SELECT COUNT(*) FROM schedules WHERE work_date = CURDATE()")->fetchColumn();
    
} catch (PDOException $e) {
    die("データベースエラー: " . (APP_DEBUG ? $e->getMessage() : 'システムエラーが発生しました'));
}

$shopName = $tenant['name'];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($shopName); ?> - 管理画面 | pullcass</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary: #667eea;
            --primary-light: #764ba2;
            --bg-dark: #1e1e2f;
            --bg-medium: #27293d;
            --text-light: #f8f9fa;
            --sidebar-width: 260px;
        }
        
        body {
            font-family: 'Noto Sans JP', sans-serif;
            background: #f0f2f5;
            min-height: 100vh;
            display: flex;
        }
        
        /* サイドバー */
        .sidebar {
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--bg-dark) 0%, var(--bg-medium) 100%);
            min-height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
        }
        
        .sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-logo {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-light);
        }
        
        .sidebar-shop {
            font-size: 0.85rem;
            color: rgba(255,255,255,0.6);
            margin-top: 5px;
        }
        
        .sidebar-nav {
            padding: 20px 15px;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            color: var(--text-light);
            text-decoration: none;
            border-radius: 10px;
            margin-bottom: 5px;
            transition: all 0.2s ease;
        }
        
        .nav-item:hover, .nav-item.active {
            background: rgba(255,255,255,0.1);
        }
        
        .nav-item.active {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
        }
        
        /* メインコンテンツ */
        .main-content {
            margin-left: var(--sidebar-width);
            flex: 1;
            padding: 30px;
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-header h1 {
            font-size: 1.8rem;
            color: #333;
        }
        
        /* 統計カード */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: #fff;
            border-radius: 15px;
            padding: 25px;
            display: flex;
            align-items: center;
            gap: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .stat-icon {
            font-size: 2.5rem;
        }
        
        .stat-value {
            display: block;
            font-size: 2rem;
            font-weight: 700;
            color: #333;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #666;
        }
        
        /* クイックアクション */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .action-card {
            background: #fff;
            border-radius: 15px;
            padding: 25px;
            text-decoration: none;
            color: #333;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .action-icon {
            font-size: 2rem;
            margin-bottom: 15px;
        }
        
        .action-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .action-desc {
            font-size: 0.9rem;
            color: #666;
        }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">🏪 店舗管理</div>
            <div class="sidebar-shop"><?php echo h($shopName); ?></div>
        </div>
        
        <nav class="sidebar-nav">
            <a href="/app/manage/?tenant=<?php echo h($tenantSlug); ?>" class="nav-item active">
                <span>📊</span> ダッシュボード
            </a>
            <a href="/app/manage/casts/?tenant=<?php echo h($tenantSlug); ?>" class="nav-item">
                <span>👤</span> キャスト管理
            </a>
            <a href="/app/manage/schedules/?tenant=<?php echo h($tenantSlug); ?>" class="nav-item">
                <span>📅</span> スケジュール
            </a>
            <a href="/app/manage/prices/?tenant=<?php echo h($tenantSlug); ?>" class="nav-item">
                <span>💰</span> 料金管理
            </a>
            <a href="/app/manage/themes/?tenant=<?php echo h($tenantSlug); ?>" class="nav-item">
                <span>🎨</span> テーマ設定
            </a>
            <a href="/app/manage/settings/?tenant=<?php echo h($tenantSlug); ?>" class="nav-item">
                <span>⚙️</span> 店舗設定
            </a>
            <hr style="border-color: rgba(255,255,255,0.1); margin: 15px 0;">
            <a href="/admin/" class="nav-item">
                <span>🔙</span> スーパー管理へ
            </a>
        </nav>
    </aside>
    
    <main class="main-content">
        <div class="page-header">
            <h1>📊 ダッシュボード</h1>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👤</div>
                <div>
                    <span class="stat-value"><?php echo $castCount; ?></span>
                    <span class="stat-label">在籍キャスト</span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📅</div>
                <div>
                    <span class="stat-value"><?php echo $todaySchedule; ?></span>
                    <span class="stat-label">本日の出勤</span>
                </div>
            </div>
        </div>
        
        <h2 style="margin-bottom: 20px; color: #333;">🚀 クイックアクション</h2>
        
        <div class="quick-actions">
            <a href="/app/manage/casts/create.php?tenant=<?php echo h($tenantSlug); ?>" class="action-card">
                <div class="action-icon">➕</div>
                <div class="action-title">キャストを追加</div>
                <div class="action-desc">新しいキャストを登録する</div>
            </a>
            <a href="/app/manage/schedules/?tenant=<?php echo h($tenantSlug); ?>" class="action-card">
                <div class="action-icon">📅</div>
                <div class="action-title">スケジュール編集</div>
                <div class="action-desc">出勤スケジュールを管理</div>
            </a>
            <a href="/app/manage/themes/?tenant=<?php echo h($tenantSlug); ?>" class="action-card">
                <div class="action-icon">🎨</div>
                <div class="action-title">デザイン変更</div>
                <div class="action-desc">サイトのテーマを編集</div>
            </a>
            <a href="/?tenant=<?php echo h($tenantSlug); ?>" class="action-card" target="_blank">
                <div class="action-icon">🌐</div>
                <div class="action-title">サイトを確認</div>
                <div class="action-desc">公開中のサイトを表示</div>
            </a>
        </div>
    </main>
</body>
</html>
