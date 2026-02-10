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

<!-- ハンバーガーメニュースタイル -->
<style>
/* === ポップアップメニュースタイル（参考サイト準拠） === */
.popup-menu-overlay {
    position: fixed;
    inset: 0;
    z-index: 2000;
    background-color: rgba(255, 255, 255, 0.402);
    backdrop-filter: blur(4px);
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s ease, visibility 0s linear 0.3s;
    will-change: opacity, visibility;
}

.popup-menu-overlay.is-open {
    opacity: 1;
    visibility: visible;
    transition: opacity 0.3s ease, visibility 0s linear 0s;
}

.popup-menu-panel {
    position: fixed;
    top: 0;
    right: -100%;
    width: 100%;
    max-width: 400px;
    height: 100vh;
    <?php if ($menuBgSettings && $menuBgSettings['background_type'] === 'image'): ?>
    background: transparent;
    <?php else: ?>
    <?php echo $menuBgCSS; ?>
    <?php endif; ?>
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    box-shadow: -5px 0 15px rgba(0, 0, 0, 0.2);
    transition: right 0.3s ease;
    will-change: right;
}

.popup-menu-overlay.is-open .popup-menu-panel {
    right: 0;
}

@media screen and (max-width: 768px) {
    .popup-menu-panel {
        width: calc(100% - 60px);
        left: 60px;
        right: 0;
    }
}

.menu-panel-content {
    position: relative;
    z-index: 3;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    height: 100%;
    padding: 1rem;
    overflow-y: auto;
    background: transparent;
}

.close-menu-icon {
    position: absolute;
    top: 0px;
    right: 15px;
    background: none;
    border: none;
    font-size: 2rem;
    color: #888;
    cursor: pointer;
    line-height: 1;
    padding: 5px;
    z-index: 4;
}

.close-menu-icon:hover {
    color: var(--color-text);
}

.popup-main-nav {
    margin-top: 0px;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.popup-nav-item {
    display: block;
    text-align: left;
    margin-bottom: 0px;
    text-decoration: none !important;
    padding: 2px;
    border-radius: 8px;
    transition: background-color 0.3s ease;
}

.popup-nav-item:hover {
    background-color: rgba(255, 255, 255, 0.1);
    text-decoration: none !important;
}

.popup-nav-item:hover .nav-item-code,
.popup-nav-item:hover .nav-item-label {
    opacity: 0.8;
}

.nav-item-code {
    font-family: var(--font-title1-en);
    font-size: 20px;
    font-weight: 700;
    margin-bottom: 10px;
    text-shadow: 1px 1px 2px rgba(84, 84, 84, 0.3);
    color: var(--color-primary);
    line-height: 1;
    margin-right: 15px;
    transition: transform 0.3s ease;
    min-width: 60px;
    text-align: left;
}

.nav-item-label {
    margin-top: -12px;
    font-family: var(--font-title2-ja);
    font-size: 13px;
    font-weight: 800;
    text-shadow: 1px 1px 2px rgba(84, 84, 84, 0.3);
    line-height: 1.2;
    transition: transform 0.3s ease;
}

.popup-footer-link {
    display: block;
    text-align: center;
    text-decoration: none;
    margin-top: auto;
    padding-bottom: 0;
}

.popup-footer-link:hover {
    text-decoration: none !important;
    opacity: 0.8;
}

.popup-footer-logo {
    width: 45px;
    height: 45px;
    margin-left: auto;
    margin-right: auto;
    margin-bottom: 2px;
}

.popup-footer-text-official {
    font-family: var(--font-title1-en);
    font-size: 25px;
    font-weight: 400;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, .3);
    color: var(--color-primary);
    line-height: 1;
}

.popup-footer-text-sitename {
    font-family: var(--font-title2-ja);
    font-size: 13px;
    font-weight: 800;
    line-height: 0.8;
    margin-bottom: 3px;
}
</style>

<?php if ($additionalCss): ?>
<!-- ページ固有スタイル -->
<style>
<?php echo $additionalCss; ?>
</style>
<?php endif; ?>
