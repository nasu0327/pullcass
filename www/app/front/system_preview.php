<?php
/**
 * pullcass - 料金システムページ（プレビュー用）
 * 編集用テーブル（通常テーブル）からデータを読み込む
 */

// スマホブラウザ特別対応（強力なキャッシュ無効化）
if (preg_match('/Mobile|Android|iPhone|iPad/', $_SERVER['HTTP_USER_AGENT'])) {
    header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
}

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/theme_helper.php';
require_once __DIR__ . '/../../includes/price_helpers.php';

// URLパラメータからテナント情報を取得
$tenantCode = $_GET['tenant'] ?? null;
if (!$tenantCode) {
    die('テナント情報が見つかりません。URLパラメータ tenant が必要です。');
}

$pdo = getPlatformDb();
$stmt = $pdo->prepare("SELECT * FROM tenants WHERE code = ? AND is_active = 1");
$stmt->execute([$tenantCode]);
$tenant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tenant) {
    die('指定されたテナントが見つかりません。');
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

// テーブルプレフィックス（編集用 = プレフィックスなし）
$tablePrefix = '';

// ページタイトル
$pageTitle = '【プレビュー】料金システム｜' . $shopName;
$pageDescription = $shopName . 'の料金システムです。各種コース料金をご確認いただけます。';

// PC/スマホ判定（top_preview.phpと同じ方法）
$isMobile = preg_match('/Mobile|Android|iPhone|iPad/', $_SERVER['HTTP_USER_AGENT']);
$isMobilePreview = isset($_GET['mobile']) && $_GET['mobile'] == '1';

// 表示する料金セットを取得
$priceSet = null;
$priceContents = [];

// URLパラメータでset_idが指定されている場合はそのセットを表示（編集画面からのプレビュー用）
$previewSetId = isset($_GET['set_id']) ? intval($_GET['set_id']) : null;

try {
    if ($previewSetId) {
        // 指定されたセットを直接取得
        $stmt = $pdo->prepare("SELECT * FROM price_sets WHERE id = ?");
        $stmt->execute([$previewSetId]);
        $priceSet = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        // 通常のアクティブセットを取得
        $priceSet = getActivePriceSet($pdo, $tablePrefix);
    }
    
    if ($priceSet) {
        $priceContents = getPriceContents($pdo, $priceSet['id'], $tablePrefix);
    }
} catch (PDOException $e) {
    error_log('Price system preview error: ' . $e->getMessage());
}

$bodyClass = '';
$additionalCss = '';

// プレビューバッジ表示（iframe内でない場合のみ）
$isInIframe = isset($_GET['iframe_preview']) && $_GET['iframe_preview'] == '1';
$showPreviewBadge = !$isInIframe;
$isThemePreview = isset($_GET['preview_id']) || (isset($_SESSION['theme_preview_id']) && $_SESSION['theme_preview_id']);
$primaryColor = $themeData['colors']['primary'] ?? '#f568df';
$btnTextColor = $themeData['colors']['btn_text'] ?? '#ffffff';
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


<?php if ($showPreviewBadge && !$isThemePreview): ?>
<style>
    .preview-mode-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: <?php echo $primaryColor; ?>;
        color: <?php echo $btnTextColor; ?>;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: bold;
        margin-right: 8px;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .preview-mode-badge:hover {
        opacity: 0.9;
        transform: scale(1.02);
    }
    .preview-mode-badge .exit-icon {
        font-size: 12px;
        opacity: 0.8;
    }
</style>
<?php endif; ?>
</style>
<?php echo getPriceTableStyles(); ?>
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="main-content">
  <!-- パンくず -->
  <nav class="breadcrumb">
    <?php if ($showPreviewBadge && !$isThemePreview): ?>
    <span class="preview-mode-badge" onclick="window.close(); window.location.href='/app/manage/price_manage/index.php?tenant=<?php echo urlencode($tenantCode); ?>';" title="クリックで閉じる">プレビューモード <span class="exit-icon">✕</span></span>
    <?php endif; ?>
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
      <?php renderPriceContents($pdo, $priceContents, $tablePrefix); ?>
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
