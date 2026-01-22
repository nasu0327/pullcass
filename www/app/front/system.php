<?php
/**
 * pullcass - 料金システムページ
 * 公開用テーブル（_published）からデータを読み込む
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/theme_helper.php';
require_once __DIR__ . '/../../includes/price_helpers.php';

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
    header('Location: https://pullcass.com/');
    exit;
}

// 店舗情報
$shopName = $tenant['name'];
$shopCode = $tenant['code'];
$tenantId = $tenant['id'];
$shopTitle = $tenant['title'] ?? '';
$shopDescription = $tenant['description'] ?? '';
$logoLargeUrl = $tenant['logo_large_url'] ?? '';
$logoSmallUrl = $tenant['logo_small_url'] ?? '';
$faviconUrl = $tenant['favicon_url'] ?? '';
$phoneNumber = $tenant['phone'] ?? '';
$businessHours = $tenant['business_hours'] ?? '';
$businessHoursNote = $tenant['business_hours_note'] ?? '';

// テーマを取得
$currentTheme = getCurrentTheme($tenantId);
$themeData = $currentTheme['theme_data'];

// ページタイトル
$pageTitle = '料金システム｜' . $shopName;
$pageDescription = $shopName . 'の料金システムです。各種コース料金をご確認いただけます。';

// PC/スマホ判定
$isMobile = preg_match('/Mobile|Android|iPhone|iPad/', $_SERVER['HTTP_USER_AGENT']);

// データベース接続取得
try {
    $pdo = getPlatformDb();
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("データベース接続エラー");
}

// 表示する料金セットを取得
$priceSet = null;
$priceContents = [];

try {
    $priceSet = getActivePriceSet($pdo, '_published');
    if ($priceSet) {
        $priceContents = getPriceContents($pdo, $priceSet['id'], '_published');
    }
} catch (PDOException $e) {
    error_log('Price system error: ' . $e->getMessage());
}

$bodyClass = 'system-page';
$additionalCss = '';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<?php include __DIR__ . '/includes/head.php'; ?>
<style>
/* 料金システムページ固有スタイル */
.system-page {
    background: var(--color-bg);
    min-height: 100vh;
}

.main-content {
    max-width: 900px;
    margin: 0 auto;
    padding: 40px 20px;
}

.title-section {
    text-align: center;
    margin-bottom: 40px;
    padding-bottom: 20px;
    border-bottom: 2px solid var(--color-primary);
}

.title-section h1 {
    font-family: var(--font-title1);
    font-size: 2.5rem;
    font-weight: bold;
    color: var(--color-primary);
    margin-bottom: 10px;
    letter-spacing: 0.1em;
}

.title-section h2 {
    font-size: 1.5rem;
    font-weight: 600;
    color: var(--color-text);
    margin-bottom: 15px;
}

.dot-line {
    width: 60px;
    height: 3px;
    background: var(--color-primary);
    margin: 0 auto;
    border-radius: 2px;
}

/* 料金表スタイル */
.price-section {
    margin-top: 30px;
}

.price-table-group {
    margin-bottom: 40px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 15px;
    padding: 25px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.price-table-title {
    font-size: 1.4rem;
    font-weight: 600;
    color: var(--color-primary);
    text-align: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid var(--color-primary);
}

.price-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 15px;
    background: rgba(255, 255, 255, 0.95);
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.price-table th,
.price-table td {
    padding: 15px 20px;
    text-align: center;
    border-bottom: 1px solid rgba(0, 0, 0, 0.1);
}

.price-table th {
    background: linear-gradient(135deg, var(--color-primary), var(--color-primary-light));
    color: var(--color-btn-text);
    font-weight: 600;
    font-size: 1rem;
}

.price-table td {
    font-size: 1rem;
    color: var(--color-text);
    background: rgba(255, 255, 255, 0.95);
}

.price-table tr:last-child td {
    border-bottom: none;
}

.price-table tr:hover td {
    background: rgba(245, 104, 223, 0.1);
}

.price-table-note {
    margin-top: 15px;
    padding: 15px 20px;
    background: rgba(255, 255, 255, 0.95);
    border-radius: 10px;
    font-size: 0.9rem;
    color: var(--color-text);
    line-height: 1.7;
    text-align: left;
}

.price-banner {
    display: block;
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    overflow: hidden;
    text-decoration: none;
    margin-bottom: 30px;
    transition: transform 0.3s ease;
}

.price-banner:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
}

.price-banner img {
    width: 100%;
    display: block;
}

.price-text-content {
    margin-bottom: 30px;
    padding: 25px;
    background: rgba(255, 255, 255, 0.95);
    border-radius: 15px;
    line-height: 1.8;
    color: var(--color-text);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.price-text-content h1,
.price-text-content h2,
.price-text-content h3,
.price-text-content h4,
.price-text-content h5,
.price-text-content h6 {
    color: var(--color-primary);
    margin-top: 1.5em;
    margin-bottom: 0.5em;
}

.price-text-content p {
    margin-bottom: 1em;
}

.price-text-content ul,
.price-text-content ol {
    margin-left: 1.5em;
    margin-bottom: 1em;
}

.price-text-content img {
    max-width: 100%;
    height: auto;
    border-radius: 10px;
    margin: 15px 0;
}

/* レスポンシブ対応 */
@media (max-width: 768px) {
    .main-content {
        padding: 20px 15px;
    }
    
    .title-section h1 {
        font-size: 2rem;
    }
    
    .title-section h2 {
        font-size: 1.2rem;
    }
    
    .price-table-group {
        padding: 20px 15px;
    }
    
    .price-table-title {
        font-size: 1.2rem;
    }
    
    .price-table th,
    .price-table td {
        padding: 12px 10px;
        font-size: 0.9rem;
    }
    
    .price-text-content {
        padding: 20px 15px;
    }
}
</style>
</head>
<body class="<?php echo $bodyClass; ?>">
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="main-content">
    <!-- タイトルセクション -->
    <section class="title-section">
        <h1>SYSTEM</h1>
        <h2>料金システム</h2>
        <div class="dot-line"></div>
    </section>
    
    <!-- 動的料金表示エリア -->
    <section class="price-section">
        <?php renderPriceContents($pdo, $priceContents, '_published'); ?>
    </section>
</main>

<!-- フッターナビゲーション -->
<?php include __DIR__ . '/includes/footer_nav.php'; ?>

<!-- 固定フッター（電話ボタン） -->
<?php include __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
