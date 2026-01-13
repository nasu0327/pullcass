<?php
/**
 * pullcass - マルチテナントシステム
 * エントリーポイント
 */

// 設定ファイルの読み込み
require_once __DIR__ . '/includes/bootstrap.php';

// テナント判別（DBエラーの場合はスキップ）
$tenant = null;
try {
    $tenant = getTenantFromRequest();
} catch (Exception $e) {
    // DBなしの場合は無視
}

if ($tenant) {
    // テナントが特定できた場合 → フロントページへ
    require_once __DIR__ . '/app/front/index.php';
} else {
    // テナントが特定できない場合 → プラットフォームトップ
    ?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>pullcass - デリヘル向けマルチテナントシステム</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Hiragino Kaku Gothic ProN', 'Noto Sans JP', sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #fff;
        }
        
        .container {
            text-align: center;
            padding: 40px;
        }
        
        .logo {
            font-size: 4rem;
            font-weight: 900;
            background: linear-gradient(135deg, #e94560 0%, #ff6b6b 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 20px;
            letter-spacing: -2px;
        }
        
        .tagline {
            font-size: 1.2rem;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 40px;
        }
        
        .status {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px 50px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .status h2 {
            font-size: 1.5rem;
            margin-bottom: 15px;
            color: #4ade80;
        }
        
        .status p {
            color: rgba(255, 255, 255, 0.7);
            line-height: 1.8;
        }
        
        .links {
            margin-top: 40px;
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .links a {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 15px 30px;
            background: linear-gradient(135deg, #e94560 0%, #ff6b6b 100%);
            color: #fff;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(233, 69, 96, 0.4);
        }
        
        .links a:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(233, 69, 96, 0.5);
        }
        
        .links a.secondary {
            background: transparent;
            border: 2px solid rgba(255, 255, 255, 0.3);
            box-shadow: none;
        }
        
        .links a.secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.5);
        }
        
        .version {
            margin-top: 60px;
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.4);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="logo">pullcass</h1>
        <p class="tagline">デリヘル向けマルチテナントシステム</p>
        
        <div class="status">
            <h2>✅ システム稼働中</h2>
            <p>
                PHP: <?php echo PHP_VERSION; ?><br>
                Server: <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?>
            </p>
        </div>
        
        <div class="links">
            <a href="/admin/">
                🔐 スーパー管理画面
            </a>
            <a href="/app/manage/" class="secondary">
                🏪 店舗管理画面
            </a>
        </div>
        
        <p class="version">pullcass v1.0.0-dev (MVP)</p>
    </div>
</body>
</html>
    <?php
}
