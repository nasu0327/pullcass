<?php
/**
 * pullcass - 店舗フロントページ
 * テナント別トップページ
 */

// index.phpから呼ばれた場合はbootstrapは既に読み込まれている
if (!function_exists('h')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
}

// テナント情報を取得
$tenant = getCurrentTenant();

if (!$tenant) {
    // テナントが設定されていない場合
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="ja">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>店舗が見つかりません | pullcass</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Zen+Kaku+Gothic+New:wght@400;500;700;900&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: 'Zen Kaku Gothic New', sans-serif;
                background: #0f0f1a;
                color: #fff;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .container {
                text-align: center;
                padding: 40px;
            }
            .icon { font-size: 4rem; margin-bottom: 20px; color: #ff6b9d; }
            h1 { font-size: 1.5rem; margin-bottom: 15px; }
            p { color: #a0a0b0; margin-bottom: 30px; }
            a {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 12px 24px;
                background: linear-gradient(135deg, #ff6b9d, #7c4dff);
                color: #fff;
                text-decoration: none;
                border-radius: 8px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="icon"><i class="fas fa-store-slash"></i></div>
            <h1>店舗が見つかりません</h1>
            <p>指定された店舗は存在しないか、現在非公開です。</p>
            <a href="https://pullcass.com"><i class="fas fa-home"></i> トップページへ</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 店舗情報
$shopName = $tenant['name'];
$shopCode = $tenant['code'];

// テーマカラー（将来的にはDBから取得）
$colors = [
    'primary' => '#ff6b9d',
    'primary_light' => '#ff8fb3',
    'secondary' => '#7c4dff',
    'text' => '#ffffff',
    'bg' => '#0f0f1a'
];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($shopName); ?> | pullcass</title>
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
            --primary: <?php echo h($colors['primary']); ?>;
            --primary-light: <?php echo h($colors['primary_light']); ?>;
            --secondary: <?php echo h($colors['secondary']); ?>;
            --text: <?php echo h($colors['text']); ?>;
            --bg: <?php echo h($colors['bg']); ?>;
            --bg-card: #16162a;
            --border: rgba(255, 255, 255, 0.1);
        }
        
        body {
            font-family: 'Zen Kaku Gothic New', sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.6;
            min-height: 100vh;
        }
        
        /* ヘッダー */
        .header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            padding: 30px 20px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2rem;
            font-weight: 900;
            margin-bottom: 10px;
        }
        
        .header .phone {
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .header .phone a {
            color: var(--text);
            text-decoration: none;
        }
        
        /* メインコンテンツ */
        .main {
            max-width: 1000px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        /* 準備中セクション */
        .coming-soon {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 60px 40px;
            text-align: center;
        }
        
        .coming-soon-icon {
            font-size: 5rem;
            margin-bottom: 25px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .coming-soon h2 {
            font-size: 1.8rem;
            margin-bottom: 15px;
        }
        
        .coming-soon p {
            color: #a0a0b0;
            font-size: 1.1rem;
            margin-bottom: 30px;
        }
        
        .coming-soon .features {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px;
            margin-top: 40px;
        }
        
        .feature-item {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px 25px;
            min-width: 150px;
        }
        
        .feature-item i {
            font-size: 1.5rem;
            color: var(--primary);
            margin-bottom: 10px;
            display: block;
        }
        
        .feature-item span {
            font-size: 0.9rem;
            color: #c8c8d8;
        }
        
        /* フッター */
        .footer {
            background: #0a0a12;
            padding: 30px 20px;
            text-align: center;
            margin-top: 60px;
        }
        
        .footer-shop-name {
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 15px;
        }
        
        .footer-powered {
            font-size: 0.85rem;
            color: #666;
        }
        
        .footer-powered a {
            color: var(--primary);
            text-decoration: none;
        }
    </style>
</head>
<body>
    <header class="header">
        <h1><?php echo h($shopName); ?></h1>
        <p class="phone">
            <i class="fas fa-phone"></i>
            <span>電話番号準備中</span>
        </p>
    </header>
    
    <main class="main">
        <div class="coming-soon">
            <div class="coming-soon-icon"><i class="fas fa-hard-hat"></i></div>
            <h2>ホームページ準備中</h2>
            <p>現在、ホームページを準備しております。<br>もうしばらくお待ちください。</p>
            
            <div class="features">
                <div class="feature-item">
                    <i class="fas fa-user"></i>
                    <span>キャスト情報</span>
                </div>
                <div class="feature-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span>出勤スケジュール</span>
                </div>
                <div class="feature-item">
                    <i class="fas fa-yen-sign"></i>
                    <span>料金案内</span>
                </div>
                <div class="feature-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <span>店舗情報</span>
                </div>
            </div>
        </div>
    </main>
    
    <footer class="footer">
        <div class="footer-shop-name"><?php echo h($shopName); ?></div>
        <p class="footer-powered">
            Powered by <a href="https://pullcass.com" target="_blank">pullcass</a>
        </p>
    </footer>
</body>
</html>
