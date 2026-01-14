<?php
/**
 * pullcass - 店舗フロントページ（インデックス）
 * 年齢確認ページ（ENTER/LEAVE）
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
$tenantId = $tenant['id'];
$shopTitle = $tenant['title'] ?? '';
$shopDescription = $tenant['description'] ?? '';

// ロゴ画像（登録されていれば表示）
$logoLargeUrl = $tenant['logo_large_url'] ?? '';
$logoSmallUrl = $tenant['logo_small_url'] ?? '';
$faviconUrl = $tenant['favicon_url'] ?? '/assets/img/common/favicon-default.png';

// サイトURL
$siteUrl = '/top'; // トップページへのリンク

// 相互リンクを取得
$reciprocalLinks = [];
try {
    $pdo = getPlatformDb();
    $stmt = $pdo->prepare("SELECT * FROM reciprocal_links WHERE tenant_id = ? ORDER BY display_order ASC");
    $stmt->execute([$tenantId]);
    $reciprocalLinks = $stmt->fetchAll();
} catch (PDOException $e) {
    // エラー時は空配列のまま
}

// テーマカラー（将来的にはDBから取得）
$colors = [
    'primary' => '#f568df',
    'primary_light' => '#ffa0f8',
    'text' => '#474747',
    'btn_text' => '#ffffff',
    'bg' => '#ffffff',
    'overlay' => 'rgba(255, 255, 255, 0.3)'
];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo h($shopName); ?></title>
    <?php if ($faviconUrl): ?>
    <link rel="icon" type="image/png" href="<?php echo h($faviconUrl); ?>">
    <?php endif; ?>
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
            --color-primary: <?php echo h($colors['primary']); ?>;
            --color-primary-light: <?php echo h($colors['primary_light']); ?>;
            --color-text: <?php echo h($colors['text']); ?>;
            --color-btn-text: <?php echo h($colors['btn_text']); ?>;
            --color-bg: <?php echo h($colors['bg']); ?>;
            --color-overlay: <?php echo h($colors['overlay']); ?>;
        }
        
        body {
            font-family: 'Zen Kaku Gothic New', sans-serif;
            background: var(--color-bg);
            color: var(--color-text);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        /* ヒーローセクション */
        .hero-section {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            text-align: center;
            min-height: 100vh;
            position: relative;
        }
        
        /* 店舗タイトル（ロゴ上） */
        .shop-title {
            font-size: 40px;
            font-weight: 700;
            color: var(--color-text);
            margin-bottom: 20px;
            letter-spacing: 0;
            line-height: 1.2;
        }
        
        /* ロゴ */
        .hero-logo {
            max-width: 300px;
            width: 80%;
            height: auto;
            margin-bottom: 20px;
        }
        
        /* 店舗名（ロゴがない場合） */
        .hero-title {
            font-size: 2rem;
            font-weight: 900;
            margin-bottom: 30px;
            color: var(--color-text);
        }
        
        /* 店舗紹介文 */
        .shop-description {
            max-width: 600px;
            font-size: 14px;
            line-height: 1.6;
            color: var(--color-text);
            margin: 25px auto 0;
            padding: 0 20px;
            text-align: center;
            font-weight: 400;
        }
        
        /* ENTER/LEAVEボタン */
        .button-container {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin: 30px 0;
            flex-wrap: nowrap;
        }
        
        .hero-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--color-primary), var(--color-primary-light));
            color: var(--color-btn-text);
            font-size: 18px;
            font-weight: bold;
            padding: 12px 30px;
            border-radius: 30px;
            box-shadow: 0 4px 15px rgba(245, 104, 223, 0.3);
            text-decoration: none;
            transition: all 0.3s ease;
            letter-spacing: 4px;
            min-width: 120px;
        }
        
        .hero-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(245, 104, 223, 0.4);
        }
        
        /* 年齢確認警告 */
        .age-warning {
            text-align: center;
            margin-top: 30px;
        }
        
        .age-warning-icon {
            width: 40px;
            height: 40px;
            display: block;
            margin: 0 auto 10px;
        }
        
        .age-warning-text {
            font-size: 12px;
            color: var(--color-text);
            line-height: 1.8;
        }
        
        /* 相互リンクセクション */
        .reciprocal-links {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 400;
            color: var(--color-text);
            margin: 0 0 5px 0;
            text-align: left;
        }
        
        .section-divider {
            height: 10px;
            width: 100%;
            background-image: repeating-radial-gradient(circle, var(--color-primary) 0 2px, transparent 2px 12px);
            background-repeat: repeat-x;
            background-size: 12px 10px;
            margin-bottom: 20px;
        }
        
        .reciprocal-links-content {
            text-align: center;
            font-size: 14px;
            color: #888;
            padding: 20px;
        }
        
        .reciprocal-links-grid {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 15px;
        }
        
        .reciprocal-link-item {
            display: inline-block;
        }
        
        .reciprocal-link-item img {
            max-width: 200px;
            max-height: 80px;
            height: auto;
            border-radius: 5px;
            transition: opacity 0.2s;
        }
        
        .reciprocal-link-item a:hover img {
            opacity: 0.8;
        }
        
        .reciprocal-link-item iframe {
            max-width: 100%;
        }
        
        /* フッター */
        .site-footer {
            background: #f5f5f5;
            padding: 30px 20px;
        }
        
        .footer-nav {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .footer-nav ul {
            list-style: none;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 5px 15px;
            margin-bottom: 20px;
        }
        
        .footer-nav ul li a {
            color: var(--color-text);
            text-decoration: none;
            font-size: 12px;
            transition: color 0.2s;
        }
        
        .footer-nav ul li a:hover {
            color: var(--color-primary);
        }
        
        .copyright {
            text-align: center;
            font-size: 11px;
            color: #888;
        }
        
        /* レスポンシブ */
        @media (max-width: 600px) {
            .shop-title {
                font-size: 24px;
                line-height: 1.3;
            }
            
            .hero-title {
                font-size: 1.5rem;
            }
            
            .hero-button {
                font-size: 16px;
                padding: 10px 25px;
                letter-spacing: 3px;
            }
            
            .button-container {
                gap: 0.8rem;
            }
            
            .shop-description {
                font-size: 13px;
            }
        }
    </style>
</head>
<body>
    <main class="hero-section">
        <?php if ($shopTitle): ?>
            <p class="shop-title"><?php echo nl2br(h($shopTitle)); ?></p>
        <?php endif; ?>
        
        <?php if ($logoLargeUrl): ?>
            <img src="<?php echo h($logoLargeUrl); ?>" alt="<?php echo h($shopName); ?>" class="hero-logo">
        <?php else: ?>
            <h1 class="hero-title"><?php echo h($shopName); ?></h1>
        <?php endif; ?>
        
        <div class="button-container">
            <a href="<?php echo h($siteUrl); ?>" class="hero-button">ENTER</a>
            <a href="https://www.google.co.jp/" target="_blank" class="hero-button">LEAVE</a>
        </div>
        
        <?php if ($shopDescription): ?>
            <div class="shop-description">
                <?php echo nl2br(h($shopDescription)); ?>
            </div>
        <?php endif; ?>
        
        <!-- 年齢確認警告 -->
        <div class="age-warning">
            <svg class="age-warning-icon" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                <circle cx="50" cy="50" r="45" fill="none" stroke="#e74c3c" stroke-width="5"/>
                <line x1="20" y1="80" x2="80" y2="20" stroke="#e74c3c" stroke-width="5"/>
                <text x="50" y="55" text-anchor="middle" font-size="24" font-weight="bold" fill="#e74c3c">18</text>
            </svg>
            <p class="age-warning-text">
                当サイトは風俗店のオフィシャルサイトです。<br>
                18歳未満または高校生のご利用をお断りします。
            </p>
        </div>
    </main>
    
    <!-- 相互リンクセクション -->
    <section class="reciprocal-links">
        <h2 class="section-title">相互リンク</h2>
        <div class="section-divider"></div>
        <div class="reciprocal-links-content">
            <?php if (count($reciprocalLinks) > 0): ?>
                <div class="reciprocal-links-grid">
                    <?php foreach ($reciprocalLinks as $link): ?>
                        <?php if (!empty($link['custom_code'])): ?>
                            <!-- カスタムコード -->
                            <div class="reciprocal-link-item">
                                <?php echo $link['custom_code']; ?>
                            </div>
                        <?php elseif (!empty($link['banner_image'])): ?>
                            <!-- 画像バナー -->
                            <div class="reciprocal-link-item">
                                <a href="<?php echo h($link['link_url']); ?>" target="_blank" rel="<?php echo $link['nofollow'] ? 'nofollow noopener' : 'noopener'; ?>">
                                    <img src="<?php echo h($link['banner_image']); ?>" alt="<?php echo h($link['alt_text']); ?>">
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>現在、相互リンクはありません。</p>
            <?php endif; ?>
        </div>
    </section>
    
    <!-- フッター -->
    <footer class="site-footer">
        <nav class="footer-nav">
            <ul>
                <li><a href="/"><?php echo h($shopName); ?></a></li>
                <li><a href="/top">トップページ</a></li>
                <li><a href="/cast/list">在籍一覧</a></li>
                <li><a href="/schedule">出勤スケジュール</a></li>
                <li><a href="/system">料金システム</a></li>
                <li><a href="/hotel_list">ホテルリスト</a></li>
                <li><a href="/reviews">口コミ</a></li>
                <li><a href="/diary">写メ日記</a></li>
                <li><a href="/yoyaku">ネット予約</a></li>
                <li><a href="/faq">よくある質問</a></li>
            </ul>
        </nav>
        <p class="copyright">
            &copy; <?php echo date('Y'); ?> <?php echo h($shopName); ?>. All Rights Reserved.
        </p>
    </footer>
</body>
</html>
