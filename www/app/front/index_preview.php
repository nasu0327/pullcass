<?php
/**
 * インデックスページ（年齢確認ページ）プレビュー
 * 編集中のindex_layout_sectionsからデータを取得
 */

if (!function_exists('h')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
}
require_once __DIR__ . '/../../includes/theme_helper.php';

// プレビュー画面：URLパラメータからテナント情報を取得
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
$faviconUrl = $tenant['favicon_url'] ?? '';
$siteUrl = '/app/front/top.php';

// スマホプレビューモード
$isMobilePreview = isset($_GET['mobile']) && $_GET['mobile'] == '1';

// フレーム表示許可
header('X-Frame-Options: ALLOWALL');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// インデックスページレイアウトを取得（編集中テーブルから）
$indexSections = [];
$heroConfig = ['background_type' => 'theme', 'background_image' => '', 'background_video' => '', 'video_poster' => ''];
try {
    // テーブルが存在するか確認
    $stmt = $pdo->query("SHOW TABLES LIKE 'index_layout_sections'");
    if ($stmt->rowCount() > 0) {
        $stmt = $pdo->prepare("
            SELECT * FROM index_layout_sections 
            WHERE tenant_id = ? AND is_visible = 1
            ORDER BY display_order ASC
        ");
        $stmt->execute([$tenantId]);
        $indexSections = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // ヒーローセクションのconfigを取得
        foreach ($indexSections as $section) {
            if ($section['section_key'] === 'hero') {
                $heroConfig = json_decode($section['config'], true) ?: $heroConfig;
                break;
            }
        }
    }
} catch (PDOException $e) {
    // エラー時はデフォルト設定のまま
}

// 相互リンクを取得
$reciprocalLinks = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM reciprocal_links WHERE tenant_id = ? ORDER BY display_order ASC");
    $stmt->execute([$tenantId]);
    $reciprocalLinks = $stmt->fetchAll();
} catch (PDOException $e) {
    // エラー時は空配列のまま
}

// バナーを取得する関数
function getIndexBanners($pdo, $sectionId, $tenantId) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM index_layout_banners 
            WHERE section_id = ? AND tenant_id = ? AND is_visible = 1
            ORDER BY display_order ASC
        ");
        $stmt->execute([$sectionId, $tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// テーマを取得
$currentTheme = getCurrentTheme($tenantId);
$themeData = $currentTheme['theme_data'];

$pageTitle = $shopName . ' - プレビュー';
$pageDescription = '';
$bodyClass = 'top-page';
$additionalCss = '';
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
        <?php if ($heroConfig['background_type'] === 'image' && !empty($heroConfig['background_image'])): ?>
        background-image: url('<?php echo h($heroConfig['background_image']); ?>');
        background-size: cover;
        background-position: center;
        background-attachment: fixed;
        <?php elseif ($heroConfig['background_type'] === 'video'): ?>
        background-color: var(--color-bg);
        <?php else: ?>
        background-image: var(--color-bg-gradient);
        background-color: var(--color-bg);
        background-repeat: no-repeat;
        background-attachment: fixed;
        <?php endif; ?>
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
    
    /* 背景動画用 */
    .video-background {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        z-index: -1;
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
    
    /* 追加セクション用 */
    .index-section {
        max-width: 800px;
        margin: 40px auto 0;
        padding: 0 15px;
    }
    .section-title-container {
        width: 100%;
        margin: 0 auto;
    }
    .section-title-en {
        font-family: var(--font-title2-ja);
        font-size: 18px;
        font-weight: 400;
        line-height: 31px;
        color: var(--color-text);
        margin: 0 0 2px 0;
        text-align: left;
    }
    .section-title-divider {
        height: 10px;
        width: 800px;
        max-width: 100%;
        background-image: repeating-radial-gradient(circle, var(--color-primary) 0 2px, transparent 2px 12px);
        background-repeat: repeat-x;
        background-size: 12px 10px;
    }
    .section-content {
        margin-top: 20px;
    }
    .section-banner-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        justify-content: center;
    }
    .section-banner-item img {
        max-width: 100%;
        height: auto;
        border-radius: 4px;
    }
    
    /* プレビューバッジ */
    .preview-badge {
        position: fixed;
        top: 10px;
        right: 10px;
        background: rgba(255, 152, 0, 0.9);
        color: white;
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: bold;
        z-index: 10000;
        box-shadow: 0 2px 10px rgba(0,0,0,0.3);
    }
</style>
</head>

<body class="top-page">

<?php 
// iframe内表示時はプレビューバッジを非表示
$isInIframe = isset($_GET['iframe_preview']) && $_GET['iframe_preview'] == '1';
if (!$isInIframe): 
?>
<!-- プレビューバッジ -->
<div class="preview-badge">プレビュー（編集中）</div>
<?php endif; ?>

<?php if ($heroConfig['background_type'] === 'video' && !empty($heroConfig['background_video'])): ?>
<video class="video-background" autoplay muted loop playsinline <?php echo !empty($heroConfig['video_poster']) ? 'poster="' . h($heroConfig['video_poster']) . '"' : ''; ?>>
    <source src="<?php echo h($heroConfig['background_video']); ?>" type="video/mp4">
</video>
<?php endif; ?>

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
                    <a href="<?php echo h($siteUrl); ?>" class="hero-button" style="display: inline-flex; align-items: center; font-family: var(--font-body); background: linear-gradient(135deg, var(--color-primary), var(--color-primary-light)); color: var(--color-btn-text); font-size: 18px; font-weight: bold; padding: 10px 20px; border-radius: 30px; box-shadow: 0 4px 15px rgba(245, 104, 223, 0.3); text-decoration: none; transition: all 0.3s ease; letter-spacing: 4.9px; opacity: 0.75;">
                        ENTER
                    </a>
                    <a href="https://www.google.co.jp/" target="_blank" class="hero-button" style="display: inline-flex; align-items: center; font-family: var(--font-body); background: linear-gradient(135deg, var(--color-primary), var(--color-primary-light)); color: var(--color-btn-text); font-size: 18px; font-weight: bold; padding: 10px 20px; border-radius: 30px; box-shadow: 0 4px 15px rgba(245, 104, 223, 0.3); text-decoration: none; transition: all 0.3s ease; letter-spacing: 4.9px; opacity: 0.75;">
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
                        echo '<div style="width: 60px; height: 60px; display: block; margin: 0 auto; color: var(--color-primary);">' . $svg . '</div>';
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

        <!-- 追加セクション（hero以外） -->
        <?php foreach ($indexSections as $section): ?>
            <?php if ($section['section_key'] === 'hero') continue; ?>
            
            <?php if ($section['section_type'] === 'reciprocal_links'): ?>
                <!-- 相互リンク -->
                <?php if (count($reciprocalLinks) > 0): ?>
                <section class="index-section">
                    <div class="section-title-container">
                        <h2 class="section-title-en"><?php echo !empty($section['title_ja']) ? h($section['title_ja']) : '相互リンク'; ?></h2>
                        <div class="section-title-divider"></div>
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
                
            <?php elseif ($section['section_type'] === 'banner'): ?>
                <!-- バナーセクション -->
                <?php $banners = getIndexBanners($pdo, $section['id'], $tenantId); ?>
                <?php if (count($banners) > 0): ?>
                <section class="index-section">
                    <?php if (!empty($section['title_en']) || !empty($section['title_ja'])): ?>
                    <div class="section-title-container">
                        <h2 class="section-title-en"><?php echo !empty($section['title_en']) ? h($section['title_en']) : h($section['title_ja']); ?></h2>
                        <div class="section-title-divider"></div>
                    </div>
                    <?php endif; ?>
                    <div class="section-content">
                        <div class="section-banner-grid">
                            <?php foreach ($banners as $banner): ?>
                                <?php if (!empty($banner['link_url'])): ?>
                                <a href="<?php echo h($banner['link_url']); ?>" 
                                   target="<?php echo h($banner['target']); ?>"
                                   <?php if ($banner['nofollow']): ?>rel="nofollow"<?php endif; ?>
                                   class="section-banner-item">
                                    <img src="<?php echo h($banner['image_path']); ?>" alt="<?php echo h($banner['alt_text']); ?>">
                                </a>
                                <?php else: ?>
                                <div class="section-banner-item">
                                    <img src="<?php echo h($banner['image_path']); ?>" alt="<?php echo h($banner['alt_text']); ?>">
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>
                <?php endif; ?>
                
            <?php elseif ($section['section_type'] === 'text_content'): ?>
                <!-- テキストコンテンツ -->
                <?php $config = json_decode($section['config'], true) ?: []; ?>
                <?php if (!empty($config['html_content'])): ?>
                <section class="index-section">
                    <?php if (!empty($section['title_en']) || !empty($section['title_ja'])): ?>
                    <div class="section-title-container">
                        <h2 class="section-title-en"><?php echo !empty($section['title_en']) ? h($section['title_en']) : h($section['title_ja']); ?></h2>
                        <div class="section-title-divider"></div>
                    </div>
                    <?php endif; ?>
                    <div class="section-content">
                        <?php echo $config['html_content']; ?>
                    </div>
                </section>
                <?php endif; ?>
                
            <?php elseif ($section['section_type'] === 'embed_widget'): ?>
                <!-- 埋め込みウィジェット -->
                <?php $config = json_decode($section['config'], true) ?: []; ?>
                <?php if (!empty($config['embed_code'])): ?>
                <section class="index-section">
                    <?php if (!empty($section['title_en']) || !empty($section['title_ja'])): ?>
                    <div class="section-title-container">
                        <h2 class="section-title-en"><?php echo !empty($section['title_en']) ? h($section['title_en']) : h($section['title_ja']); ?></h2>
                        <div class="section-title-divider"></div>
                    </div>
                    <?php endif; ?>
                    <div class="section-content" style="min-height: <?php echo h($config['embed_height'] ?? '400'); ?>px;">
                        <?php echo $config['embed_code']; ?>
                    </div>
                </section>
                <?php endif; ?>
            <?php endif; ?>
        <?php endforeach; ?>
        
        <!-- レイアウト管理が未設定の場合のフォールバック（相互リンク） -->
        <?php if (empty($indexSections) && count($reciprocalLinks) > 0): ?>
        <section style="max-width: 800px; margin: 40px auto 0; padding: 0 15px;">
            <div style="width: 100%; margin: 0 auto;">
                <h2 style="font-family: var(--font-title2-ja); font-size: 18px; font-weight: 400; line-height: 31px; color: var(--color-text); margin: 0 0 2px 0; text-align: left;">相互リンク</h2>
                <div style="height: 10px; width: 800px; max-width: 100%; background-image: repeating-radial-gradient(circle, var(--color-primary) 0 2px, transparent 2px 12px); background-repeat: repeat-x; background-size: 12px 10px;"></div>
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

</body>
</html>
