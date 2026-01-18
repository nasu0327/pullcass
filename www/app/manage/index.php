<?php
/**
 * pullcass - 店舗管理画面
 * ダッシュボード
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';

// ログイン認証チェック（テナント未指定時はスキップ）
$tenantSlug = $_GET['tenant'] ?? $_SESSION['manage_tenant_slug'] ?? null;

if (!$tenantSlug) {
    // テナントが指定されていない場合（ログイン不要）
    ?>
    <!DOCTYPE html>
    <html lang="ja">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>店舗管理画面 | pullcass</title>
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
                --secondary: #7c4dff;
                --darker: #0f0f1a;
                --card-bg: #16162a;
                --border-color: rgba(255, 255, 255, 0.1);
                --text-light: #ffffff;
                --text-muted: #c8c8d8;
            }
            
            body {
                font-family: 'Zen Kaku Gothic New', sans-serif;
                background: var(--darker);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                position: relative;
            }
            
            body::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: 
                    radial-gradient(ellipse at 20% 80%, rgba(255, 107, 157, 0.1) 0%, transparent 50%),
                    radial-gradient(ellipse at 80% 20%, rgba(124, 77, 255, 0.1) 0%, transparent 50%);
                pointer-events: none;
            }
            
            .container {
                background: var(--card-bg);
                padding: 50px;
                border-radius: 20px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                text-align: center;
                max-width: 500px;
                border: 1px solid var(--border-color);
                position: relative;
                z-index: 1;
            }
            
            h1 {
                color: var(--text-light);
                margin-bottom: 20px;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 12px;
            }
            
            h1 i {
                background: linear-gradient(135deg, var(--primary), var(--secondary));
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
            }
            
            p {
                color: var(--text-muted);
                margin-bottom: 30px;
            }
            
            a {
                display: inline-flex;
                align-items: center;
                gap: 10px;
                padding: 15px 30px;
                background: linear-gradient(135deg, var(--primary), var(--secondary));
                color: var(--text-light);
                text-decoration: none;
                border-radius: 10px;
                font-weight: 600;
                box-shadow: 0 4px 15px rgba(255, 107, 157, 0.3);
                transition: all 0.3s ease;
            }
            
            a:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(255, 107, 157, 0.4);
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1><i class="fas fa-store"></i> 店舗管理画面</h1>
            <p>管理する店舗が指定されていません。<br>マスター管理画面から店舗を選択してください。</p>
            <a href="/admin/"><i class="fas fa-arrow-left"></i> マスター管理画面へ</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ログイン認証チェック
requireTenantAdminLogin();

// テナント情報を取得
try {
    $pdo = getPlatformDb();
    $stmt = $pdo->prepare("SELECT * FROM tenants WHERE code = ? AND is_active = 1");
    $stmt->execute([$tenantSlug]);
    $tenant = $stmt->fetch();
    
    if (!$tenant) {
        die("店舗が見つかりません。");
    }
    
    // セッションに保存
    $_SESSION['manage_tenant_slug'] = $tenantSlug;
    $_SESSION['manage_tenant'] = $tenant;
    
    // 統計情報（現時点ではダミー値）
    $castCount = 0;
    $todaySchedule = 0;
    
} catch (PDOException $e) {
    die("データベースエラー: " . (APP_DEBUG ? $e->getMessage() : 'システムエラーが発生しました'));
}

$shopName = $tenant['name'];
$tenantId = $tenant['id'];
$pageTitle = 'ダッシュボード';

// 共通ヘッダーを読み込む
require_once __DIR__ . '/includes/header.php';
?>

<style>
    /* 統計カード */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: var(--card-bg);
        border-radius: 15px;
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
    
    /* セクションタイトル */
    .section-title {
        margin-bottom: 20px;
        color: var(--text-light);
        font-size: 1.2rem;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    /* クイックアクション */
    .quick-actions {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }
    
    .action-card {
        background: var(--card-bg);
        border-radius: 15px;
        padding: 25px;
        text-decoration: none;
        color: var(--text-light);
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
    }
    
    .action-card:hover {
        transform: translateY(-5px);
        border-color: var(--primary);
        box-shadow: 0 8px 25px rgba(255, 107, 157, 0.2);
    }
    
    .action-icon {
        font-size: 2rem;
        margin-bottom: 15px;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    
    .action-title {
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 5px;
    }
    
    .action-desc {
        font-size: 0.9rem;
        color: var(--text-muted);
    }
</style>

<div class="page-header">
    <h1><i class="fas fa-chart-pie"></i> <?php echo h($pageTitle); ?></h1>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-user"></i></div>
        <div>
            <span class="stat-value"><?php echo $castCount; ?></span>
            <span class="stat-label">在籍キャスト</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
        <div>
            <span class="stat-value"><?php echo $todaySchedule; ?></span>
            <span class="stat-label">本日の出勤</span>
        </div>
    </div>
</div>

<h2 class="section-title"><i class="fas fa-bolt"></i> クイックアクション</h2>

<div class="quick-actions">
    <a href="/app/manage/casts/create.php?tenant=<?php echo h($tenantSlug); ?>" class="action-card">
        <div class="action-icon"><i class="fas fa-user-plus"></i></div>
        <div class="action-title">キャストを追加</div>
        <div class="action-desc">新しいキャストを登録する</div>
    </a>
    <a href="/app/manage/schedules/?tenant=<?php echo h($tenantSlug); ?>" class="action-card">
        <div class="action-icon"><i class="fas fa-calendar-alt"></i></div>
        <div class="action-title">スケジュール編集</div>
        <div class="action-desc">出勤スケジュールを管理</div>
    </a>
    <a href="/app/manage/themes/?tenant=<?php echo h($tenantSlug); ?>" class="action-card">
        <div class="action-icon"><i class="fas fa-palette"></i></div>
        <div class="action-title">デザイン変更</div>
        <div class="action-desc">サイトのテーマを編集</div>
    </a>
    <a href="https://<?php echo h($tenant['code']); ?>.pullcass.com/app/front/top.php" class="action-card" target="_blank">
        <div class="action-icon"><i class="fas fa-globe"></i></div>
        <div class="action-title">サイトを確認</div>
        <div class="action-desc">公開中のサイトを表示</div>
    </a>
</div>

</main>
</body>
</html>
