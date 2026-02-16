<?php
/**
 * pullcass - 共通<head>セクション
 * 
 * 必要な変数:
 * - $pageTitle: ページタイトル
 * - $pageDescription: ページの説明
 * - $faviconUrl: ファビコンURL（オプション）
 * - $themeData: テーマデータ配列（colors, fonts）
 * - $additionalCss: 追加のCSS（オプション）
 */

// テーマヘルパーを読み込む（まだ読み込まれていない場合）
if (!function_exists('generateThemeCSSVariables')) {
    require_once __DIR__ . '/../../../includes/theme_helper.php';
}

// デフォルト値の設定
$pageTitle = $pageTitle ?? 'pullcass';
$pageDescription = $pageDescription ?? '';
$faviconUrl = $faviconUrl ?? '';
$themeData = $themeData ?? [];
$additionalCss = $additionalCss ?? '';
$bodyClass = $bodyClass ?? '';

// テーマカラー
$themeColors = $themeData['colors'] ?? [];
$themeFonts = $themeData['fonts'] ?? [];

// 後方互換性
if (!isset($themeFonts['body_ja'])) {
    $themeFonts['body_ja'] = 'Zen Kaku Gothic New';
}
if (!isset($themeFonts['title1_en'])) {
    $themeFonts['title1_en'] = 'Kranky';
}
if (!isset($themeFonts['title1_ja'])) {
    $themeFonts['title1_ja'] = 'Kaisei Decol';
}
if (!isset($themeFonts['title2_en'])) {
    $themeFonts['title2_en'] = $themeFonts['title1_en'];
}
if (!isset($themeFonts['title2_ja'])) {
    $themeFonts['title2_ja'] = $themeFonts['title1_ja'];
}

// 背景タイプ
$bgType = $themeColors['bg_type'] ?? 'solid';
$bgCss = '';
$bgGradientCss = 'none'; // グラデーション変数のデフォルト値
if ($bgType === 'gradient') {
    $gradientStart = $themeColors['bg_gradient_start'] ?? ($themeColors['bg'] ?? '#ffffff');
    $gradientEnd = $themeColors['bg_gradient_end'] ?? ($themeColors['bg'] ?? '#ffffff');
    $bgCss = "background: linear-gradient(90deg, {$gradientStart} 0%, {$gradientEnd} 100%);";
    $bgGradientCss = "linear-gradient(90deg, {$gradientStart} 0%, {$gradientEnd} 100%)";
} else {
    $bgCss = "background-color: " . ($themeColors['bg'] ?? '#ffffff') . ";";
}
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="<?php echo h($pageDescription); ?>">
<title><?php echo h($pageTitle); ?></title>
<?php if ($faviconUrl): ?>
<link rel="icon" type="image/png" href="<?php echo h($faviconUrl); ?>">
<?php endif; ?>
<!-- Google Fonts -->
<?php echo generateGoogleFontsLink(); ?>
<!-- Material Icons -->
<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<!-- Swiper CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
<!-- 共通スタイルシート -->
<link rel="stylesheet" href="/assets/css/style.css?v=<?php echo time(); ?>">
<!-- テーマCSS変数 -->
<style id="theme-variables">
    :root {
        /* テーマカラー */
        --color-primary: <?php echo h($themeColors['primary'] ?? '#f568df'); ?>;
        --color-primary-light: <?php echo h($themeColors['primary_light'] ?? '#ffa0f8'); ?>;
        --color-text: <?php echo h($themeColors['text'] ?? '#474747'); ?>;
        --color-btn-text: <?php echo h($themeColors['btn_text'] ?? '#ffffff'); ?>;
        --color-bg: <?php echo h($themeColors['bg'] ?? '#ffffff'); ?>;
        --color-overlay: <?php echo h($themeColors['overlay'] ?? 'rgba(244, 114, 182, 0.2)'); ?>;
        --color-bg-gradient: <?php echo $bgGradientCss; ?>;
        
        /* テーマフォント - タイトル1 */
        --font-title1: '<?php echo h($themeFonts['title1_en']); ?>', '<?php echo h($themeFonts['title1_ja']); ?>', sans-serif;
        --font-title1-en: '<?php echo h($themeFonts['title1_en']); ?>', sans-serif;
        --font-title1-ja: '<?php echo h($themeFonts['title1_ja']); ?>', sans-serif;
        
        /* テーマフォント - タイトル2 */
        --font-title2: '<?php echo h($themeFonts['title2_en']); ?>', '<?php echo h($themeFonts['title2_ja']); ?>', sans-serif;
        --font-title2-en: '<?php echo h($themeFonts['title2_en']); ?>', sans-serif;
        --font-title2-ja: '<?php echo h($themeFonts['title2_ja']); ?>', sans-serif;
        
        /* テーマフォント - 本文 */
        --font-body: '<?php echo h($themeFonts['body_ja']); ?>', sans-serif;
    }
    
    /* 背景設定 */
    body {
        <?php echo $bgCss; ?>
    }
</style>

<?php
// メニュー背景設定を取得
if (!function_exists('getMenuBackground')) {
    require_once __DIR__ . '/../../../app/manage/menu_management/includes/background_functions.php';
}
$menuBgSettings = getMenuBackground($pdo, $tenantId);
$menuBgCSS = generateMenuBackgroundCSS($menuBgSettings);
?>

<!-- メニュー背景の動的スタイル -->
<style>
.popup-menu-panel {
    <?php if ($menuBgSettings && $menuBgSettings['background_type'] === 'image'): ?>
    background: transparent;
    <?php else: ?>
    <?php echo $menuBgCSS; ?>
    <?php endif; ?>
}
</style>

<?php if ($additionalCss): ?>
<!-- ページ固有スタイル -->
<style>
<?php echo $additionalCss; ?>
</style>
<?php endif; ?>

<?php if (isset($shopCode) && $shopCode !== ''): ?>
<script>window.PULLCASS_TENANT_CODE=<?php echo json_encode((string)$shopCode); ?>;</script>
<?php endif; ?>
