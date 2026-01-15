<?php
/**
 * pullcass - 店舗フロントページ（インデックス）
 * 年齢確認ページ（ENTER/LEAVE）
 */

// index.phpから呼ばれた場合はbootstrapは既に読み込まれている
if (!function_exists('h')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
}

// テーマヘルパーを読み込む
require_once __DIR__ . '/../../includes/theme_helper.php';

// テナント情報を取得（リクエストのサブドメインを優先）
$tenantFromRequest = getTenantFromRequest();
$tenantFromSession = getCurrentTenant();

// リクエストからテナントが判別できた場合はそれを使用
if ($tenantFromRequest) {
    $tenant = $tenantFromRequest;
    // セッションと異なる場合は更新
    if (!$tenantFromSession || $tenantFromSession['id'] !== $tenant['id']) {
        setCurrentTenant($tenant);
    }
} elseif ($tenantFromSession) {
    // リクエストから判別できない場合はセッションを使用
    $tenant = $tenantFromSession;
} else {
    $tenant = null;
}

if (!$tenant) {
    // テナントが見つからない場合
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

// サイトURL（同じサブドメイン内のトップページ）
$siteUrl = '/app/front/top.php';

// 相互リンクを取得
$reciprocalLinks = [];
try {
    $pdo = getPlatformDb();
    if ($pdo) {
        $stmt = $pdo->prepare("SELECT * FROM reciprocal_links WHERE tenant_id = ? ORDER BY display_order ASC");
        $stmt->execute([$tenantId]);
        $reciprocalLinks = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    // エラー時は空配列のまま
}

// テーマを取得（プレビュー対応）
$currentTheme = getCurrentTheme($tenantId);
$themeData = $currentTheme['theme_data'];
$colors = $themeData['colors'];
$fonts = $themeData['fonts'] ?? [];

// 後方互換性
if (!isset($fonts['body_ja'])) {
    $fonts['body_ja'] = 'Zen Kaku Gothic New';
}
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
    <?php echo generateGoogleFontsLink(); ?>
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
            --color-overlay: <?php echo h($colors['overlay'] ?? 'rgba(255, 255, 255, 0.3)'); ?>;
            --font-body: '<?php echo h($fonts['body_ja']); ?>', sans-serif;
            --font-title1: '<?php echo h($fonts['title1_en'] ?? 'Kranky'); ?>', '<?php echo h($fonts['title1_ja'] ?? 'Kaisei Decol'); ?>', sans-serif;
        }
        
        body {
            font-family: var(--font-body);
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
        
        /* 共通フッタースタイル */
        <?php include __DIR__ . '/includes/header_styles.php'; ?>
        
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
    
    <?php include __DIR__ . '/includes/footer_nav.php'; ?>
    
    <?php
    // プレビューモードの場合はプレビューバーを表示
    if (isset($currentTheme['is_preview']) && $currentTheme['is_preview']) {
        echo generatePreviewBar($currentTheme, $tenantId, $tenant['code']);
    }
    ?>
</body>
</html>
