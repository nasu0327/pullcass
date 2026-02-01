<?php
/**
 * フリーページ - フロントエンド表示
 */

if (!function_exists('h')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
}
require_once __DIR__ . '/../../includes/theme_helper.php';
require_once __DIR__ . '/../../includes/free_page_helpers.php';

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
    include __DIR__ . '/includes/404.php';
    exit;
}

// スラッグを取得
$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    http_response_code(404);
    include __DIR__ . '/includes/404.php';
    exit;
}

$pdo = getPlatformDb();
$tenantId = $tenant['id'];

// ページ取得
$page = getFreePageBySlug($pdo, $slug, $tenantId);
if (!$page) {
    http_response_code(404);
    include __DIR__ . '/includes/404.php';
    exit;
}

// テーマを取得
$currentTheme = getCurrentTheme($tenantId);
$themeData = $currentTheme['theme_data'];

// 店舗情報
$shopName = $tenant['name'];
$siteUrl = '/top';
$pageTitle = !empty($page['main_title']) ? $page['main_title'] : $page['title'];
$metaDescription = !empty($page['meta_description']) ? $page['meta_description'] : $page['excerpt'];
$ogImage = !empty($page['featured_image']) ? $page['featured_image'] : ($tenant['logo_large_url'] ?? '');
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($pageTitle); ?> | <?php echo h($shopName); ?></title>

    <?php if ($metaDescription): ?>
        <meta name="description" content="<?php echo h($metaDescription); ?>">
    <?php endif; ?>

    <!-- OGP -->
    <meta property="og:title" content="<?php echo h($pageTitle); ?> | <?php echo h($shopName); ?>">
    <meta property="og:type" content="article">
    <?php if ($metaDescription): ?>
        <meta property="og:description" content="<?php echo h($metaDescription); ?>">
    <?php endif; ?>
    <?php if ($ogImage): ?>
        <meta property="og:image" content="<?php echo h($ogImage); ?>">
    <?php endif; ?>

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo h($pageTitle); ?> | <?php echo h($shopName); ?>">
    <?php if ($metaDescription): ?>
        <meta name="twitter:description" content="<?php echo h($metaDescription); ?>">
    <?php endif; ?>
    <?php if ($ogImage): ?>
        <meta name="twitter:image" content="<?php echo h($ogImage); ?>">
    <?php endif; ?>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/style.css?v=<?php echo time(); ?>">

    <!-- テーマCSS変数（styleタグを含む） -->
    <?php echo generateThemeCSSVariables($themeData); ?>

    <style>
        body {
            background-image: var(--color-bg-gradient);
            background-color: var(--color-bg);
            background-repeat: no-repeat;
            background-attachment: fixed;
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }

        .free-page-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
            padding-bottom: 100px;
        }

        .page-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .page-header .sub-title {
            font-family: var(--font-title2-ja);
            font-size: 14px;
            color: var(--color-primary);
            margin-bottom: 10px;
            letter-spacing: 2px;
        }

        .page-header .main-title {
            font-family: var(--font-title1-ja);
            font-size: 28px;
            font-weight: 700;
            color: var(--color-text);
            margin: 0;
        }

        .page-header .title-divider {
            height: 10px;
            width: 100%;
            max-width: 300px;
            margin: 20px auto 0;
            background-image: repeating-radial-gradient(circle, var(--color-primary) 0 2px, transparent 2px 12px);
            background-repeat: repeat-x;
            background-size: 12px 10px;
        }

        .featured-image {
            width: 100%;
            border-radius: 12px;
            margin-bottom: 30px;
        }

        .page-content {
            font-family: var(--font-body);
            font-size: 16px;
            line-height: 1.8;
            color: var(--color-text);
        }

        .page-content img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
        }

        .page-content h1,
        .page-content h2,
        .page-content h3,
        .page-content h4,
        .page-content h5,
        .page-content h6 {
            font-family: var(--font-title1-ja);
            color: var(--color-text);
            margin: 1.5em 0 0.5em;
        }

        .page-content p {
            margin: 0 0 1em;
        }

        .page-content a {
            color: var(--color-primary);
        }

        .page-content ul,
        .page-content ol {
            margin: 0 0 1em;
            padding-left: 1.5em;
        }

        .page-content table {
            width: 100%;
            border-collapse: collapse;
            margin: 1em 0;
        }

        .page-content table th,
        .page-content table td {
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 10px;
            text-align: left;
        }

        .page-content table th {
            background: rgba(255, 255, 255, 0.1);
        }

        /* レスポンシブ */
        @media (max-width: 768px) {
            .page-header .main-title {
                font-size: 22px;
            }

            .free-page-container {
                padding: 30px 15px;
                padding-bottom: 100px;
            }
        }
    </style>

    <!-- 構造化データ -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebPage",
        "name": "<?php echo h($pageTitle); ?>",
        "description": "<?php echo h($metaDescription); ?>",
        "publisher": {
            "@type": "Organization",
            "name": "<?php echo h($shopName); ?>"
        },
        "datePublished": "<?php echo $page['published_at'] ?? $page['created_at']; ?>",
        "dateModified": "<?php echo $page['updated_at']; ?>"
    }
    </script>
</head>

<body>
    <div class="free-page-container">
        <!-- ページヘッダー -->
        <header class="page-header">
            <?php if (!empty($page['sub_title'])): ?>
                <div class="sub-title"><?php echo h($page['sub_title']); ?></div>
            <?php endif; ?>
            <h1 class="main-title"><?php echo h($pageTitle); ?></h1>
            <div class="title-divider"></div>
        </header>

        <!-- アイキャッチ画像 -->
        <?php if (!empty($page['featured_image'])): ?>
            <img src="<?php echo h($page['featured_image']); ?>" alt="<?php echo h($pageTitle); ?>" class="featured-image">
        <?php endif; ?>

        <!-- コンテンツ -->
        <article class="page-content">
            <?php echo $page['content']; ?>
        </article>
    </div>

    <?php include __DIR__ . '/includes/footer_nav.php'; ?>
</body>

</html>