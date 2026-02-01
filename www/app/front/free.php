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

// 店舗情報（header.phpで必要な変数を全て定義）
$shopName = $tenant['name'];
$shopCode = $tenant['code'];
$shopTitle = $tenant['title'] ?? '';  // header.phpで必要
$logoLargeUrl = $tenant['logo_large_url'] ?? '';
$logoSmallUrl = $tenant['logo_small_url'] ?? '';
$faviconUrl = $tenant['favicon_url'] ?? '';
$phoneNumber = $tenant['phone'] ?? '';
$businessHours = $tenant['business_hours'] ?? '';
$businessHoursNote = $tenant['business_hours_note'] ?? '';

// ページ情報
$pageTitle = (!empty($page['main_title']) ? $page['main_title'] : $page['title']) . '｜' . $shopName;
$pageDescription = !empty($page['meta_description']) ? $page['meta_description'] : ($page['excerpt'] ?? '');
$ogImage = !empty($page['featured_image']) ? $page['featured_image'] : ($tenant['logo_large_url'] ?? '');
$displayTitle = !empty($page['main_title']) ? $page['main_title'] : $page['title'];
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <?php include __DIR__ . '/includes/head.php'; ?>

    <!-- OGP -->
    <meta property="og:title" content="<?php echo h($pageTitle); ?>">
    <meta property="og:type" content="article">
    <?php if ($pageDescription): ?>
        <meta property="og:description" content="<?php echo h($pageDescription); ?>">
    <?php endif; ?>
    <?php if ($ogImage): ?>
        <meta property="og:image" content="<?php echo h($ogImage); ?>">
    <?php endif; ?>

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo h($pageTitle); ?>">
    <?php if ($pageDescription): ?>
        <meta name="twitter:description" content="<?php echo h($pageDescription); ?>">
    <?php endif; ?>
    <?php if ($ogImage): ?>
        <meta name="twitter:image" content="<?php echo h($ogImage); ?>">
    <?php endif; ?>

    <!-- 構造化データ -->
    <script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "WebPage",
    "name": "<?php echo h($displayTitle); ?>",
    "description": "<?php echo h($pageDescription); ?>",
    "publisher": {
        "@type": "Organization",
        "name": "<?php echo h($shopName); ?>"
    },
    "datePublished": "<?php echo $page['published_at'] ?? $page['created_at']; ?>",
    "dateModified": "<?php echo $page['updated_at']; ?>"
}
</script>

    <style>
        /* コンテンツエリア */
        .content-area {
            position: relative;
            margin-top: -10px;
        }

        /* コンテナのoverflow設定 */
        .page-content {
            font-size: 16px;
            color: var(--color-text);
            overflow: hidden;
        }

        .tinymce-content {
            text-align: left;
            line-height: 1.4;
            padding: 20px;
            overflow: hidden;
        }

        .tinymce-content p {
            text-align: left;
            margin: 0 0 0.8em 0;
            font-size: 16px;
        }

        .tinymce-content h1,
        .tinymce-content h2,
        .tinymce-content h3,
        .tinymce-content h4,
        .tinymce-content h5,
        .tinymce-content h6 {
            text-align: left;
            margin: 0.8em 0 0.4em 0;
            font-weight: bold;
            color: var(--color-text);
        }

        .tinymce-content h1 {
            font-size: 24px;
        }

        .tinymce-content h2 {
            font-size: 20px;
        }

        .tinymce-content h3 {
            font-size: 18px;
        }

        .tinymce-content h4 {
            font-size: 16px;
        }

        .tinymce-content h5 {
            font-size: 14px;
        }

        .tinymce-content h6 {
            font-size: 14px;
        }

        .tinymce-content ul,
        .tinymce-content ol {
            margin: 0 0 0.8em 0;
            padding-left: 2em;
        }

        .tinymce-content ul {
            list-style-type: disc;
        }

        .tinymce-content ol {
            list-style-type: decimal;
        }

        .tinymce-content li {
            margin: 0.2em 0;
        }

        /* 画像のデフォルトスタイル */
        .tinymce-content img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
        }

        /* クラスベースの画像配置 */
        .tinymce-content img.img-align-left {
            float: left !important;
            margin: 0 15px 10px 0 !important;
            display: inline !important;
        }

        .tinymce-content img.img-align-center {
            display: block !important;
            margin: 10px auto !important;
            float: none !important;
        }

        .tinymce-content img.img-align-right {
            float: right !important;
            margin: 0 0 10px 15px !important;
            display: inline !important;
        }

        /* floatクリア用 */
        .tinymce-content::after {
            content: "";
            display: table;
            clear: both;
        }

        .tinymce-content blockquote {
            text-align: left;
            margin: 1em 0;
            padding: 1em;
            border-left: 4px solid var(--color-primary);
            background: rgba(255, 255, 255, 0.1);
            font-style: italic;
        }

        .tinymce-content table {
            width: 100%;
            border-collapse: collapse;
            margin: 1em 0;
        }

        .tinymce-content th,
        .tinymce-content td {
            text-align: left;
            padding: 0.5em;
            border: 1px solid rgba(255, 255, 255, 0.2);
            vertical-align: top;
        }

        .tinymce-content th {
            background: rgba(255, 255, 255, 0.1);
            font-weight: bold;
        }

        .tinymce-content strong,
        .tinymce-content b {
            font-weight: bold;
        }

        .tinymce-content em,
        .tinymce-content i {
            font-style: italic;
        }

        .tinymce-content a {
            color: var(--color-primary);
        }

        .tinymce-content hr {
            border: none;
            height: 2px;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            margin: 30px 0;
        }

        /* アイキャッチ画像 */
        .featured-image {
            width: 100%;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        /* レスポンシブデザイン */
        @media screen and (max-width: 768px) {
            .content-area {
                margin-top: 10px;
            }

            .page-content {
                padding-left: 15px;
                padding-right: 15px;
            }

            .tinymce-content {
                padding: 15px;
            }

            /* モバイルでは画像のfloatを解除 */
            .tinymce-content img.img-align-left,
            .tinymce-content img.img-align-right,
            .tinymce-content img[style*="float: left"],
            .tinymce-content img[style*="float: right"],
            .tinymce-content img[style*="float:left"],
            .tinymce-content img[style*="float:right"] {
                float: none !important;
                display: block !important;
                margin: 10px auto !important;
                max-width: 100% !important;
            }
        }
    </style>
</head>

<body>
    <!-- ヘッダー -->
    <?php include __DIR__ . '/includes/header.php'; ?>

    <!-- メインコンテンツ -->
    <main class="main-content">
        <!-- パンくずナビ -->
        <nav class="breadcrumb">
            <a href="/"><?php echo h($shopName); ?></a><span>»</span><a
                href="/top">トップ</a><span>»</span><?php echo h($displayTitle); ?>
        </nav>

        <!-- タイトルセクション -->
        <section class="title-section">
            <h1><?php echo h($displayTitle); ?></h1>
            <?php if (!empty($page['sub_title'])): ?>
                <h2><?php echo h($page['sub_title']); ?></h2>
            <?php endif; ?>
            <div class="dot-line"></div>
        </section>

        <!-- メインコンテンツエリア -->
        <section class="content-area">
            <!-- アイキャッチ画像 -->
            <?php if (!empty($page['featured_image'])): ?>
                <img src="<?php echo h($page['featured_image']); ?>" alt="<?php echo h($displayTitle); ?>"
                    class="featured-image">
            <?php endif; ?>

            <!-- ページ本文 -->
            <div class="page-content">
                <div class="tinymce-content">
                    <?php echo $page['content']; ?>
                </div>
            </div>
        </section>

        <!-- セクション下の影 -->
        <div
            style="background-color:transparent; box-shadow:0 -8px 12px -4px rgba(0,0,0,0.2); position:relative; height:15px;">
        </div>
    </main>

    <!-- フッターナビゲーション -->
    <?php include __DIR__ . '/includes/footer_nav.php'; ?>

    <!-- 固定フッター（電話ボタン） -->
    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>

</html>