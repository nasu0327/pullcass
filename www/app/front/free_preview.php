<?php
/**
 * フリーページ - iframe用プレビュー（PC/スマホ共通）
 */

if (!function_exists('h')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
}
require_once __DIR__ . '/../../includes/theme_helper.php';
require_once __DIR__ . '/../../includes/free_page_helpers.php';

// URLパラメータからテナント情報を取得
$tenantCode = $_GET['tenant'] ?? null;
$pageId = $_GET['id'] ?? null;

if (!$tenantCode || !$pageId) {
    http_response_code(404);
    echo '必要なパラメータがありません。';
    exit;
}

$pdo = getPlatformDb();
$stmt = $pdo->prepare("SELECT * FROM tenants WHERE code = ? AND is_active = 1");
$stmt->execute([$tenantCode]);
$tenant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tenant) {
    http_response_code(404);
    echo '指定されたテナントが見つかりません。';
    exit;
}

$tenantId = $tenant['id'];

// ページ取得
$page = getFreePage($pdo, (int) $pageId, $tenantId);
if (!$page) {
    http_response_code(404);
    echo '指定されたページが見つかりません。';
    exit;
}

// テーマを取得
$currentTheme = getCurrentTheme($tenantId);
$themeData = $currentTheme['theme_data'];

// 店舗情報（header.phpで必要な変数を全て定義）
$shopName = $tenant['name'];
$shopCode = $tenant['code'];
$shopTitle = $tenant['title'] ?? '';
$logoLargeUrl = $tenant['logo_large_url'] ?? '';
$logoSmallUrl = $tenant['logo_small_url'] ?? '';
$faviconUrl = $tenant['favicon_url'] ?? '';
$phoneNumber = $tenant['phone'] ?? '';
$businessHours = $tenant['business_hours'] ?? '';
$businessHoursNote = $tenant['business_hours_note'] ?? '';

// ページ情報
$pageTitle = (!empty($page['main_title']) ? $page['main_title'] : $page['title']) . '｜' . $shopName;
$pageDescription = !empty($page['meta_description']) ? $page['meta_description'] : ($page['excerpt'] ?? '');
$displayTitle = !empty($page['main_title']) ? $page['main_title'] : $page['title'];
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <?php include __DIR__ . '/includes/head.php'; ?>

    <style>
        /* system.phpに合わせたスタイル */
        .breadcrumb {
            font-size: 12px;
            padding: 1px 10px;
            opacity: 0.7;
            text-align: left;
        }

        .breadcrumb a {
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .breadcrumb span {
            margin: 0 4px;
        }

        @media (min-width: 768px) {
            .breadcrumb {
                font-size: 12px;
                padding-top: 5px;
                padding-left: 20px;
            }
        }

        .title-section {
            max-width: 1100px;
            margin-left: auto;
            margin-right: auto;
            text-align: left;
            padding: 14px 16px 0;
        }

        .title-section h1 {
            font-family: var(--font-title1);
            font-size: 32px;
            font-weight: 400;
            line-height: 31px;
            letter-spacing: -0.8px;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
            color: var(--color-primary);
            margin: 0;
        }

        .title-section h2 {
            font-family: var(--font-title2);
            font-size: 16px;
            font-weight: 400;
            line-height: 31px;
            letter-spacing: -0.8px;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
            margin: 0;
        }

        @media (min-width: 768px) {
            .title-section {
                text-align: left;
                padding-bottom: 30px;
            }

            .title-section h1 {
                font-size: 40px;
            }

            .title-section h2 {
                font-size: 20px;
            }
        }

        .dot-line {
            height: 10px;
            background-image: repeating-radial-gradient(circle, var(--color-primary) 0 2px, transparent 2px 12px);
            background-repeat: repeat-x;
            background-size: 12px 10px;
            margin: 0;
            margin-bottom: -20px;
        }

        /* 他ページ同様: ラッパーでフッターを画面下部に */
        .main-content-wrapper {
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }

        .main-content {
            width: 100%;
            max-width: 1100px;
            margin-left: auto;
            margin-right: auto;
            padding: 0 15px;
            text-align: center;
            margin-top: 0;
            flex: 1;
        }

        @media (min-width: 768px) {
            .main-content {
                padding: 0 15px;
            }
        }

        /* TinyMCEコンテンツ用スタイル（内容に依存せず幅100%） */
        .page-content {
            font-size: 16px;
            color: var(--color-text);
            overflow: hidden;
            width: 100%;
            min-width: 100%;
        }

        .tinymce-content {
            text-align: left;
            line-height: 1.4;
            padding: 20px;
            overflow: hidden;
            width: 100%;
            min-width: 100%;
            box-sizing: border-box;
        }

        /* page-backgroundがある場合 */
        .tinymce-content .page-background {
            padding: 20px;
            margin: 0 -20px -20px -20px;
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

        .tinymce-content img {
            max-width: 100%;
            height: auto;
        }

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

        .tinymce-content img[style*="float: left"]:not(.img-align-center):not(.img-align-right),
        .tinymce-content img[style*="float:left"]:not(.img-align-center):not(.img-align-right) {
            float: left;
            margin: 0 15px 10px 0;
            display: inline;
        }

        .tinymce-content img[style*="margin-left: auto"][style*="margin-right: auto"]:not(.img-align-left):not(.img-align-right),
        .tinymce-content img[style*="display: block"][style*="margin-left: auto"]:not(.img-align-left):not(.img-align-right) {
            display: block;
            margin: 10px auto;
            float: none;
        }

        .tinymce-content img[style*="float: right"]:not(.img-align-center):not(.img-align-left),
        .tinymce-content img[style*="float:right"]:not(.img-align-center):not(.img-align-left) {
            float: right;
            margin: 0 0 10px 15px;
            display: inline;
        }

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

        .tinymce-content u {
            text-decoration: underline;
        }

        .tinymce-content s,
        .tinymce-content strike {
            text-decoration: line-through;
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

        .featured-image {
            width: 100%;
            max-width: 100%;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        @media screen and (max-width: 768px) {
            .main-content {
                padding: 0 15px;
            }

            .tinymce-content {
                padding: 15px;
            }

            .tinymce-content .page-background {
                padding: 15px;
                margin: 0 -15px -15px -15px;
            }

            .tinymce-content img.img-align-left,
            .tinymce-content img.img-align-right,
            .tinymce-content img[style*="float: left"],
            .tinymce-content img[style*="float: right"] {
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

    <main class="main-content">
        <!-- パンくずナビ -->
        <nav class="breadcrumb">
            <a href="/">ホーム</a><span>»</span><a href="/top">トップ</a><span>»</span><?php echo h($displayTitle); ?>
        </nav>

        <!-- タイトルセクション -->
        <section class="title-section">
            <h1><?php echo h($displayTitle); ?></h1>
            <?php if (!empty($page['sub_title'])): ?>
                <h2><?php echo h($page['sub_title']); ?></h2>
            <?php endif; ?>
            <div class="dot-line"></div>
        </section>

        <!-- メインコンテンツエリア（system.phpと同じネスト構造） -->
        <section class="main-content" style="margin-top: 25px;">
            <?php if (!empty($page['featured_image'])): ?>
                <img src="<?php echo h($page['featured_image']); ?>" alt="<?php echo h($displayTitle); ?>"
                    class="featured-image">
            <?php endif; ?>

            <div class="page-content">
                <div class="tinymce-content">
                    <?php echo $page['content']; ?>
                </div>
            </div>
        </section>
    </main>

    <!-- フッターナビゲーション -->
    <?php include __DIR__ . '/includes/footer_nav.php'; ?>

    <!-- 固定フッター -->
    <?php include __DIR__ . '/includes/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // アンカーリンクのスムーズスクロール実装
            const anchorLinks = document.querySelectorAll('a[href^="#"]');
            anchorLinks.forEach(function (link) {
                link.addEventListener('click', function (e) {
                    const href = this.getAttribute('href');
                    if (href && href.length > 1) {
                        const target = document.querySelector(href);
                        if (target) {
                            e.preventDefault();
                            const headerHeight = 60;
                            const targetPosition = target.getBoundingClientRect().top + window.pageYOffset - headerHeight;
                            window.scrollTo({
                                top: targetPosition,
                                behavior: 'smooth'
                            });
                        }
                    }
                });
            });
        });
    </script>
</body>

</html>