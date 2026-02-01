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
$page = getFreePage($pdo, (int)$pageId, $tenantId);
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

.tinymce-content h1 { font-size: 24px; }
.tinymce-content h2 { font-size: 20px; }
.tinymce-content h3 { font-size: 18px; }
.tinymce-content h4 { font-size: 16px; }
.tinymce-content h5 { font-size: 14px; }
.tinymce-content h6 { font-size: 14px; }

.tinymce-content ul,
.tinymce-content ol {
    margin: 0 0 0.8em 0;
    padding-left: 2em;
}

.tinymce-content ul { list-style-type: disc; }
.tinymce-content ol { list-style-type: decimal; }
.tinymce-content li { margin: 0.2em 0; }

.tinymce-content img {
    max-width: 100%;
    height: auto;
    border-radius: 8px;
}

.tinymce-content img.img-align-left {
    float: left !important;
    margin: 0 15px 10px 0 !important;
}

.tinymce-content img.img-align-center {
    display: block !important;
    margin: 10px auto !important;
    float: none !important;
}

.tinymce-content img.img-align-right {
    float: right !important;
    margin: 0 0 10px 15px !important;
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

.tinymce-content a {
    color: var(--color-primary);
}

.featured-image {
    width: 100%;
    border-radius: 12px;
    margin-bottom: 20px;
}

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

    .tinymce-content img.img-align-left,
    .tinymce-content img.img-align-right {
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
            <a href="/"><?php echo h($shopName); ?></a><span>»</span><a href="/top">トップ</a><span>»</span><?php echo h($displayTitle); ?>
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
            <?php if (!empty($page['featured_image'])): ?>
                <img src="<?php echo h($page['featured_image']); ?>" alt="<?php echo h($displayTitle); ?>" class="featured-image">
            <?php endif; ?>

            <div class="page-content">
                <div class="tinymce-content">
                    <?php echo $page['content']; ?>
                </div>
            </div>
        </section>

        <div style="background-color:transparent; box-shadow:0 -8px 12px -4px rgba(0,0,0,0.2); position:relative; height:15px;"></div>
    </main>

    <!-- フッターナビゲーション -->
    <?php include __DIR__ . '/includes/footer_nav.php'; ?>

    <!-- 固定フッター -->
    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
