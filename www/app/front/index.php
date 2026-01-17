<?php
/**
 * pullcass - 店舗フロントページ（インデックス）
 * 年齢確認ページ（ENTER/LEAVE）
 * ※参考サイト(club-houman.com)の構造を忠実に再現
 */

if (!function_exists('h')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
}
require_once __DIR__ . '/../../includes/theme_helper.php';

// テナント情報を取得
$tenantFromRequest = getTenantFromRequest();
$tenantFromSession = getCurrentTenant();

if ($tenantFromRequest) {
    $tenant = $tenantFromRequest;
    if (!$tenantFromSession || $tenantFromSession['id'] !== $tenant['id']) {
        setCurrentTenant($tenant);
    }
} elseif ($tenantFromSession) {
    $tenant = $tenantFromSession;
} else {
    $tenant = null;
}

if (!$tenant) {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="ja">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>店舗が見つかりません | pullcass</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body { font-family: sans-serif; background: #0f0f1a; color: #fff; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
            .container { text-align: center; padding: 40px; }
            .icon { font-size: 4rem; margin-bottom: 20px; color: #ff6b9d; }
            h1 { font-size: 1.5rem; margin-bottom: 15px; }
            p { color: #a0a0b0; margin-bottom: 30px; }
            a { display: inline-flex; align-items: center; gap: 8px; padding: 12px 24px; background: linear-gradient(135deg, #ff6b9d, #7c4dff); color: #fff; text-decoration: none; border-radius: 8px; }
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
$logoLargeUrl = $tenant['logo_large_url'] ?? '';
$faviconUrl = $tenant['favicon_url'] ?? '';
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

// テーマを取得
$currentTheme = getCurrentTheme($tenantId);
$themeData = $currentTheme['theme_data'];

$pageTitle = $shopName;
$pageDescription = '';
$bodyClass = 'top-page';
$additionalCss = '';

// スマホブラウザ特別対応
if (preg_match('/Mobile|Android|iPhone|iPad/', $_SERVER['HTTP_USER_AGENT'])) {
    header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta name="robots" content="noindex, nofollow">
<?php include __DIR__ . '/includes/head.php'; ?>
<style>
    body.top-page {
        margin: 0;
        padding: 0;
        overflow-x: hidden;
        background-image: var(--color-bg-gradient);
        background-color: var(--color-bg);
        background-repeat: no-repeat;
        background-attachment: fixed;
    }
    
    .hero-button:hover {
        transform: translateY(-2px) !important;
        box-shadow: 0 6px 20px rgba(245, 104, 223, 0.4) !important;
    }
    
    .top-page {
        margin: 0;
        padding: 0;
        width: 100%;
        overflow-x: hidden;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }
    
    .top-page-content-wrapper {
        margin: 0;
        padding: 0;
        width: 100%;
        overflow-x: hidden;
        flex: 1;
        display: flex;
        flex-direction: column;
    }
    
    /* 相互リンク用 */
    .reciprocal-links {
        padding: 40px 0;
        margin-top: 40px;
    }
    .reciprocal-links-container {
        max-width: 800px;
        margin: 0 auto;
        padding: 0 15px;
    }
    .banner-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 5px;
        justify-items: center;
    }
    .banner-item {
        width: 100%;
        max-width: 200px;
        transition: transform 0.2s;
    }
    .banner-item:hover {
        transform: translateY(-5px);
    }
    .banner-item img {
        width: 100%;
        height: auto;
        border-radius: 4px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
</style>
</head>

<body class="top-page">

<main class="top-page">
    <div class="top-page-content-wrapper">
        <section class="hero-section">
            <div class="hero-content" style="position: relative; z-index: 20; padding: 3rem 1rem; text-align: center; max-width: 1100px; margin: 0 auto;">
                <?php if ($shopTitle): ?>
                <h2 class="hero-title" style="font-family: var(--font-title1-ja); color: var(--color-btn-text);">
                    <?php echo nl2br(h($shopTitle)); ?>
                </h2>
                <?php endif; ?>
                
                <?php if ($logoLargeUrl): ?>
                <img
                    src="<?php echo h($logoLargeUrl); ?>"
                    alt="<?php echo h($shopName); ?>ロゴ"
                    class="hero-logo"
                />
                <?php endif; ?>
                
                <h1 class="hero-subtitle" style="font-family: var(--font-title2-ja); font-size: 15px; font-weight: 400; color: var(--color-text); text-shadow: 0 0 10px rgba(255, 255, 255, 0.44); margin-top: 5px;">
                    <?php echo h($shopName); ?>
                </h1>

                <div class="button-container" style="display: flex; justify-content: center; gap: 1rem; margin-top: 2.5rem; flex-wrap: nowrap;">
                    <a href="<?php echo h($siteUrl); ?>" class="hero-button" style="display: inline-flex; align-items: center; font-family: var(--font-body); background: linear-gradient(135deg, var(--color-primary), var(--color-primary-light)); color: var(--color-btn-text); font-size: 18px; padding: 10px 20px; border-radius: 30px; box-shadow: 0 4px 15px rgba(245, 104, 223, 0.3); text-decoration: none; transition: all 0.3s ease; letter-spacing: 4.9px; opacity: 0.75;">
                        ENTER
                    </a>
                    <a href="https://www.google.co.jp/" target="_blank" class="hero-button" style="display: inline-flex; align-items: center; font-family: var(--font-body); background: linear-gradient(135deg, var(--color-primary), var(--color-primary-light)); color: var(--color-btn-text); font-size: 18px; padding: 10px 20px; border-radius: 30px; box-shadow: 0 4px 15px rgba(245, 104, 223, 0.3); text-decoration: none; transition: all 0.3s ease; letter-spacing: 4.9px; opacity: 0.75;">
                        LEAVE
                    </a>
                </div>

                <!-- 年齢確認警告 -->
                <div style="text-align: center; margin-top: 1.5rem;">
                    <?php 
                    $svgPath = __DIR__ . '/../../assets/img/common/18kin.svg';
                    if (file_exists($svgPath)) {
                        $svg = file_get_contents($svgPath);
                        $svg = preg_replace('/<\?xml[^>]*\?>/', '', $svg);
                        echo '<div style="width: 30px; height: 30px; display: block; margin: 0 auto; color: var(--color-primary);">' . $svg . '</div>';
                    }
                    ?>
                    <p style="font-family: var(--font-body); font-size: 12px; color: var(--color-btn-text); text-shadow: 0 0 10px rgba(0, 0, 0, 0.6); margin: 5px 0 0 0; text-align: center;">
                        当サイトは風俗店のオフィシャルサイトです。<br>
                        18歳未満または高校生のご利用をお断りします。
                    </p>
                </div>

                <?php if ($shopDescription): ?>
                <div class="hero-description" style="font-family: var(--font-body); color: var(--color-btn-text);">
                    <?php echo nl2br(h($shopDescription)); ?>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- 相互リンク -->
        <?php if (count($reciprocalLinks) > 0): ?>
        <section style="max-width: 800px; margin: 40px auto 0; padding: 0 15px;">
            <div style="width: 100%; margin: 0 auto;">
                <h2 style="font-family: var(--font-title2-ja); font-size: 18px; font-weight: 400; line-height: 31px; color: var(--color-text); margin: 0 0 2px 0; text-align: left;">相互リンク</h2>
                <div style="height: 10px; width: 100%; max-width: 800px; background-image: repeating-radial-gradient(circle, var(--color-primary) 0 2px, transparent 2px 12px); background-repeat: repeat-x; background-size: 12px 10px;"></div>
            </div>
        </section>
        
        <section style="max-width: 800px; margin: 20px auto 0; padding: 0 15px;">
            <div style="text-align: center; font-size: 0;">
                <?php foreach ($reciprocalLinks as $link): ?>
                    <?php if (!empty($link['custom_code'])): ?>
                        <div style="display: inline-block; margin: 1.5px; vertical-align: top;">
                            <?php echo $link['custom_code']; ?>
                        </div>
                    <?php elseif (!empty($link['banner_image'])): ?>
                        <a href="<?php echo h($link['link_url']); ?>" 
                           target="_blank" 
                           <?php if (!empty($link['nofollow']) && $link['nofollow']): ?>rel="nofollow"<?php endif; ?>
                           style="display: inline-block; margin: 1.5px;">
                            <img src="<?php echo h($link['banner_image']); ?>" 
                                 alt="<?php echo h($link['alt_text']); ?>"
                                 style="max-width: 100%; height: auto;">
                        </a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . '/includes/footer_nav.php'; ?>

<?php
if (isset($currentTheme['is_preview']) && $currentTheme['is_preview']) {
    echo generatePreviewBar($currentTheme, $tenantId, $tenant['code']);
}
?>

</body>
</html>
