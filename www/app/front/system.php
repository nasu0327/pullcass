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

$bodyClass = '';
$additionalCss = '';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<?php include __DIR__ . '/includes/head.php'; ?>
<style>
/* パンくずリストのスタイル */
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

/* PCサイズ（768px以上）でのパンくずリスト */
@media (min-width: 768px) {
    .breadcrumb {
        font-size: 12px;
        padding-top: 5px;
        padding-left: 20px;
    }
}

/* タイトルセクションのスタイル */
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

/* PCサイズ（768px以上）でのタイトルセクション */
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

/* ドットライン */
.dot-line {
    height: 10px;
    background-image: repeating-radial-gradient(circle, var(--color-primary) 0 2px, transparent 2px 12px);
    background-repeat: repeat-x;
    background-size: 12px 10px;
    margin: 0;
    margin-bottom: -20px;
}

/* メインコンテンツエリア */
.main-content {
    max-width: 1100px;
    margin-left: auto;
    margin-right: auto;
    padding: 0 15px;
    text-align: center;
    margin-top: 0;
}

/* PCサイズ（768px以上）でのメインコンテンツ */
@media (min-width: 768px) {
    .main-content {
        padding: 0 15px;
    }
}
</style>
<?php echo getPriceTableStyles(); ?>
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="main-content">
  <!-- パンくず -->
  <nav class="breadcrumb">
    <a href="/app/front/index.php">ホーム</a><span>»</span><a href="/app/front/top.php">トップ</a><span>»</span>料金システム |
  </nav>

  <!-- タイトルセクション -->
  <section class="title-section">
    <h1>SYSTEM</h1>
    <h2>料金システム</h2>
    <div class="dot-line"></div>
  </section>
  
  <!-- メインコンテンツエリア -->
  <section class="main-content" style="margin-top: 25px;">
    
    <!-- 動的料金表示エリア -->
    <section class="price-section">
      <?php renderPriceContents($pdo, $priceContents, '_published'); ?>
    </section>

  </section>
  <!-- セクション下の影 -->
  <div class="w-full h-[15px]" style="background-color:transparent; box-shadow:0 -8px 12px -4px rgba(0,0,0,0.2); position:relative;"></div>
</main>

<!-- フッターナビゲーション -->
<?php include __DIR__ . '/includes/footer_nav.php'; ?>

<!-- 固定フッター（電話ボタン） -->
<?php include __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
