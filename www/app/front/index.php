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

// ページタイトル
$pageTitle = $shopName;
$pageDescription = '';
$bodyClass = 'top-page';

// ページ固有のCSS (共通スタイルはstyle.cssに移動)
$additionalCss = '';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta name="robots" content="noindex, nofollow">
<?php include __DIR__ . '/includes/head.php'; ?>
</head>
<body class="top-page">
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
        
        <!-- 年齢確認警告 -->
        <div class="age-warning">
            <div class="age-warning-icon">
                <?php 
                $svgPath = __DIR__ . '/../../assets/img/common/18kin.svg';
                if (file_exists($svgPath)) {
                    $svg = file_get_contents($svgPath);
                    $svg = preg_replace('/<\?xml[^>]*\?>/', '', $svg);
                    echo $svg;
                }
                ?>
            </div>
            <p class="age-warning-text">
                当サイトは風俗店のオフィシャルサイトです。<br>
                18歳未満または高校生のご利用をお断りします。
            </p>
        </div>
        
        <?php if ($shopDescription): ?>
            <div class="shop-description">
                <?php echo nl2br(h($shopDescription)); ?>
            </div>
        <?php endif; ?>
    </main>
    
    <!-- 相互リンクセクション -->
    <section class="reciprocal-links">
        <h2 class="section-title-simple">相互リンク</h2>
        <div class="section-divider"></div>
        <div class="reciprocal-links-content">
            <?php if (count($reciprocalLinks) > 0): ?>
                <div class="reciprocal-links-grid">
                    <?php foreach ($reciprocalLinks as $link): ?>
                        <?php if (!empty($link['custom_code'])): ?>
                            <div class="reciprocal-link-item">
                                <?php echo $link['custom_code']; ?>
                            </div>
                        <?php elseif (!empty($link['banner_image'])): ?>
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
