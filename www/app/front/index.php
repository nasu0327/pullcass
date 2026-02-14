<?php
/**
 * pullcass - 店舗フロントページ（インデックス）
 * 年齢確認ページ（ENTER/LEAVE）
 * ※参考サイト(club-houman.com)の構造を忠実に再現
 * ※index_layout_sections_publishedからレイアウト情報を取得
 */

if (!function_exists('h')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
}
require_once __DIR__ . '/../../includes/theme_helper.php';

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
    ?>
    <!DOCTYPE html>
    <html lang="ja">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>店舗が見つかりません | pullcass</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: sans-serif;
                background: #0f0f1a;
                color: #fff;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .container {
                text-align: center;
                padding: 40px;
            }

            .icon {
                font-size: 4rem;
                margin-bottom: 20px;
                color: #ff6b9d;
            }

            h1 {
                font-size: 1.5rem;
                margin-bottom: 15px;
            }

            p {
                color: #a0a0b0;
                margin-bottom: 30px;
            }

            a {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 12px 24px;
                background: linear-gradient(135deg, #ff6b9d, #7c4dff);
                color: #fff;
                text-decoration: none;
                border-radius: 8px;
            }
        </style>
    </head>

    <body>
        <div class="container">
            <div class="icon"><i class="fas fa-store-slash"></i></div>
            <h1>店舗が見つかりません</h1>
            <p>指定された店舗は存在しないか、現在非公開です。</p>
            <a href="https://pullcass.com"><i class="fas fa-home"></i> トップページへ</a>
        </div>
    </body>

    </html>
    <?php
    exit;
}

// 店舗情報
$shopName = $tenant['name'];
$shopCode = $tenant['code'];
$tenantId = $tenant['id'];
$shopTitle = $tenant['title'] ?? '';
$shopDescription = $tenant['description'] ?? '';
$logoLargeUrl = $tenant['logo_large_url'] ?? '';
$faviconUrl = $tenant['favicon_url'] ?? '';
$siteUrl = '/app/front/top';

$pdo = getPlatformDb();

// コンテンツ配置設定を取得
$contentPosition = 'below'; // デフォルト

// インデックスページレイアウトを取得
$indexSections = [];
$heroConfig = ['background_type' => 'theme', 'background_image' => '', 'background_video' => '', 'video_poster' => ''];
try {
    if ($pdo) {
        // テーブルが存在するか確認
        $stmt = $pdo->query("SHOW TABLES LIKE 'index_layout_sections_published'");
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->prepare("
                SELECT * FROM index_layout_sections_published 
                WHERE tenant_id = ? AND is_visible = 1
                ORDER BY display_order ASC
            ");
            $stmt->execute([$tenantId]);
            $indexSections = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // ヒーローセクションのconfigを取得
            foreach ($indexSections as $section) {
                if ($section['section_key'] === 'hero') {
                    $heroConfig = json_decode($section['config'], true) ?: $heroConfig;
                    // コンテンツ配置設定を取得
                    $contentPosition = $heroConfig['content_position'] ?? 'below';
                    break;
                }
            }
        }
    }
} catch (PDOException $e) {
    // エラー時はデフォルト設定のまま
}

// 相互リンクを取得
$reciprocalLinks = [];
try {
    if ($pdo) {
        $stmt = $pdo->prepare("SELECT * FROM reciprocal_links WHERE tenant_id = ? ORDER BY display_order ASC");
        $stmt->execute([$tenantId]);
        $reciprocalLinks = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    // エラー時は空配列のまま
}

// バナーを取得する関数
function getIndexBanners($pdo, $sectionId, $tenantId)
{
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

$pageTitle = $shopName;
$pageDescription = '';
$bodyClass = 'top-page';
$additionalCss = '';

// スマホブラウザ特別対応
if (preg_match('/Mobile|Android|iPhone|iPad/', $_SERVER['HTTP_USER_AGENT'])) {
    header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
}
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
            <?php if ($heroConfig['background_type'] === 'theme'): ?>
                background-image: var(--color-bg-gradient);
                background-color: var(--color-bg);
                background-repeat: no-repeat;
                background-attachment: fixed;
            <?php else: ?>
                background-color: var(--color-bg);
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
            padding-bottom: 20px;
            width: 100%;
            overflow-x: hidden;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        /* ヒーローセクション */
        .hero-section {
            position: relative;
            overflow: hidden;
            height: 100vh;
            min-height: 600px;
        }

        /* 背景動画用 */
        .hero-video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: 0;
            margin: 0;
            padding: 0;
        }

        /* 背景画像用 */
        .hero-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: 0;
            margin: 0;
            padding: 0;
        }

        /* ヒーローオーバーレイ */
        .hero-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 10;
            /* background-color と opacity はインラインスタイルで設定 */
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

        /* レスポンシブ対応: スマホでロゴを小さく */
        @media (max-width: 768px) {
            .hero-logo {
                height: 100px !important;
            }
        }
    </style>
</head>

<body class="top-page">

    <main class="top-page">
        <div class="top-page-content-wrapper">
            <section class="hero-section">
                <?php if ($heroConfig['background_type'] === 'video' && !empty($heroConfig['background_video'])):
                    $overlayColor = $heroConfig['video_overlay_color'] ?? '#000000';
                    $overlayOpacity = $heroConfig['video_overlay_opacity'] ?? 0.4;
                    ?>
                    <video class="hero-video" autoplay muted loop playsinline preload="none" aria-hidden="true"
                        role="presentation" <?php echo !empty($heroConfig['video_poster']) ? 'poster="' . h($heroConfig['video_poster']) . '"' : ''; ?>>
                        <source src="<?php echo h($heroConfig['background_video']); ?>" type="video/mp4">
                    </video>
                    <div class="hero-overlay"
                        style="background-color: <?php echo h($overlayColor); ?>; opacity: <?php echo h($overlayOpacity); ?>;">
                    </div>
                <?php elseif ($heroConfig['background_type'] === 'image' && !empty($heroConfig['background_image'])):
                    $imageOverlayColor = $heroConfig['image_overlay_color'] ?? '#000000';
                    $imageOverlayOpacity = $heroConfig['image_overlay_opacity'] ?? 0.5;
                    ?>
                    <img src="<?php echo h($heroConfig['background_image']); ?>" alt="<?php echo h($shopName); ?>背景画像"
                        class="hero-image" />
                    <div class="hero-overlay"
                        style="background-color: <?php echo h($imageOverlayColor); ?>; opacity: <?php echo h($imageOverlayOpacity); ?>;">
                    </div>
                <?php endif; ?>

                <?php if ($contentPosition === 'inside'): ?>
                    <!-- コンテンツをヒーロー内に配置 -->
                    <div class="hero-content"
                        style="position: relative; z-index: 20; padding: 3rem 1rem; text-align: center; max-width: 1100px; margin: 0 auto;">
                        <?php if ($shopTitle): ?>
                            <h2 class="hero-title"
                                style="font-family: var(--font-title1-ja); color: var(--color-btn-text); margin-bottom: 2rem;">
                                <?php echo nl2br(h($shopTitle)); ?>
                            </h2>
                        <?php endif; ?>

                        <?php if ($logoLargeUrl): ?>
                            <img src="<?php echo h($logoLargeUrl); ?>" alt="<?php echo h($shopName); ?>ロゴ" class="hero-logo"
                                style="height: 200px; width: auto; margin: 0 auto 0.25rem; display: block;" />
                        <?php endif; ?>

                        <h1 class="hero-subtitle"
                            style="font-family: var(--font-title2-ja); font-size: 20px; font-weight: 400; color: var(--color-btn-text); margin: 0 0 1rem 0;">
                            <?php echo h($shopName); ?>
                        </h1>

                        <div class="button-container"
                            style="display: flex; justify-content: center; gap: 1rem; margin: 2.5rem 0; flex-wrap: nowrap;">
                            <a href="<?php echo h($siteUrl); ?>" class="hero-button"
                                style="display: inline-flex; align-items: center; font-family: var(--font-body); background: linear-gradient(135deg, var(--color-primary), var(--color-primary-light)); color: var(--color-btn-text); font-size: 18px; font-weight: bold; padding: 12px 30px; border-radius: 30px; box-shadow: 0 4px 15px rgba(245, 104, 223, 0.3); text-decoration: none; transition: all 0.3s ease; letter-spacing: 4.9px;">
                                ENTER
                            </a>
                            <a href="https://www.google.co.jp/" target="_blank" class="hero-button"
                                style="display: inline-flex; align-items: center; font-family: var(--font-body); background: linear-gradient(135deg, var(--color-primary), var(--color-primary-light)); color: var(--color-btn-text); font-size: 18px; font-weight: bold; padding: 12px 30px; border-radius: 30px; box-shadow: 0 4px 15px rgba(245, 104, 223, 0.3); text-decoration: none; transition: all 0.3s ease; letter-spacing: 4.9px;">
                                LEAVE
                            </a>
                        </div>

                        <!-- 年齢確認警告 -->
                        <div style="text-align: center; margin-top: 2rem;">
                            <?php
                            $svgPath = __DIR__ . '/../../assets/img/common/18kin.svg';
                            if (file_exists($svgPath)) {
                                $svg = file_get_contents($svgPath);
                                $svg = preg_replace('/<\?xml[^>]*\?>/', '', $svg);
                                echo '<div style="width: 60px; height: 60px; display: block; margin: 0 auto; color: var(--color-btn-text);">' . $svg . '</div>';
                            }
                            ?>
                            <p
                                style="font-family: var(--font-body); font-size: 12px; color: var(--color-btn-text); text-shadow: 0 0 10px rgba(0, 0, 0, 0.6); margin: 10px 0; text-align: center;">
                                当サイトは風俗店のオフィシャルサイトです。<br>
                                18歳未満または高校生のご利用をお断りします。
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
            </section>

            <?php if ($contentPosition === 'below'): ?>
                <!-- ヒーローコンテンツ（ヒーローの下に配置） -->
                <section style="padding: 3rem 1rem; text-align: center; max-width: 1100px; margin: 0 auto;">
                    <?php if ($shopTitle): ?>
                        <h2 class="hero-title"
                            style="font-family: var(--font-title1-ja); color: var(--color-text); margin-bottom: 2rem;">
                            <?php echo nl2br(h($shopTitle)); ?>
                        </h2>
                    <?php endif; ?>

                    <?php if ($logoLargeUrl): ?>
                        <img src="<?php echo h($logoLargeUrl); ?>" alt="<?php echo h($shopName); ?>ロゴ" class="hero-logo"
                            style="height: 200px; width: auto; margin: 0 auto 0.25rem; display: block;" />
                    <?php endif; ?>

                    <h1 class="hero-subtitle"
                        style="font-family: var(--font-title2-ja); font-size: 20px; font-weight: 400; color: var(--color-text); margin: 0 0 1rem 0;">
                        <?php echo h($shopName); ?>
                    </h1>

                    <div class="button-container"
                        style="display: flex; justify-content: center; gap: 1rem; margin: 2.5rem 0; flex-wrap: nowrap;">
                        <a href="<?php echo h($siteUrl); ?>" class="hero-button"
                            style="display: inline-flex; align-items: center; font-family: var(--font-body); background: linear-gradient(135deg, var(--color-primary), var(--color-primary-light)); color: var(--color-btn-text); font-size: 18px; font-weight: bold; padding: 12px 30px; border-radius: 30px; box-shadow: 0 4px 15px rgba(245, 104, 223, 0.3); text-decoration: none; transition: all 0.3s ease; letter-spacing: 4.9px;">
                            ENTER
                        </a>
                        <a href="https://www.google.co.jp/" target="_blank" class="hero-button"
                            style="display: inline-flex; align-items: center; font-family: var(--font-body); background: linear-gradient(135deg, var(--color-primary), var(--color-primary-light)); color: var(--color-btn-text); font-size: 18px; font-weight: bold; padding: 12px 30px; border-radius: 30px; box-shadow: 0 4px 15px rgba(245, 104, 223, 0.3); text-decoration: none; transition: all 0.3s ease; letter-spacing: 4.9px;">
                            LEAVE
                        </a>
                    </div>

                    <!-- 年齢確認警告 -->
                    <div style="text-align: center; margin-top: 2rem;">
                        <?php
                        $svgPath = __DIR__ . '/../../assets/img/common/18kin.svg';
                        if (file_exists($svgPath)) {
                            $svg = file_get_contents($svgPath);
                            $svg = preg_replace('/<\?xml[^>]*\?>/', '', $svg);
                            echo '<div style="width: 60px; height: 60px; display: block; margin: 0 auto; color: var(--color-primary);">' . $svg . '</div>';
                        }
                        ?>
                        <p
                            style="font-family: var(--font-body); font-size: 12px; color: var(--color-text); margin: 10px 0; text-align: center;">
                            当サイトは風俗店のオフィシャルサイトです。<br>
                            18歳未満または高校生のご利用をお断りします。
                        </p>
                    </div>
                </section>
            <?php endif; ?>

            <!-- 店舗説明文（常に最後に表示） -->
            <?php if ($shopDescription): ?>
                <section style="padding: 2rem 1rem; text-align: center; max-width: 1100px; margin: 0 auto;">
                    <div class="hero-description"
                        style="font-family: var(--font-body); color: var(--color-text); margin: 0; line-height: 1.8;">
                        <?php echo nl2br(h($shopDescription)); ?>
                    </div>
                </section>
            <?php endif; ?>

            <!-- 追加セクション（hero以外） -->
            <?php foreach ($indexSections as $section): ?>
                <?php if ($section['section_key'] === 'hero')
                    continue; ?>

                <?php if ($section['section_type'] === 'reciprocal_links'): ?>
                    <!-- 相互リンク -->
                    <?php if (count($reciprocalLinks) > 0): ?>
                        <section class="index-section">
                            <div class="section-title-container">
                                <h2 class="section-title-en">
                                    <?php echo !empty($section['title_ja']) ? h($section['title_ja']) : '相互リンク'; ?>
                                </h2>
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
                                        <a href="<?php echo h($link['link_url']); ?>" target="_blank" <?php if (!empty($link['nofollow']) && $link['nofollow']): ?>rel="nofollow" <?php endif; ?>
                                            style="display: inline-block; margin: 1.5px;">
                                            <img src="<?php echo h($link['banner_image']); ?>" alt="<?php echo h($link['alt_text']); ?>"
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
                                    <h2 class="section-title-en">
                                        <?php echo !empty($section['title_en']) ? h($section['title_en']) : h($section['title_ja']); ?>
                                    </h2>
                                    <div class="section-title-divider"></div>
                                </div>
                            <?php endif; ?>
                            <div class="section-content">
                                <div class="section-banner-grid">
                                    <?php foreach ($banners as $banner): ?>
                                        <?php if (!empty($banner['link_url'])): ?>
                                            <a href="<?php echo h($banner['link_url']); ?>" target="<?php echo h($banner['target']); ?>"
                                                <?php if ($banner['nofollow']): ?>rel="nofollow" <?php endif; ?>
                                                class="section-banner-item">
                                                <img src="<?php echo h($banner['image_path']); ?>"
                                                    alt="<?php echo h($banner['alt_text']); ?>">
                                            </a>
                                        <?php else: ?>
                                            <div class="section-banner-item">
                                                <img src="<?php echo h($banner['image_path']); ?>"
                                                    alt="<?php echo h($banner['alt_text']); ?>">
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
                                    <h2 class="section-title-en">
                                        <?php echo !empty($section['title_en']) ? h($section['title_en']) : h($section['title_ja']); ?>
                                    </h2>
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
                                    <h2 class="section-title-en">
                                        <?php echo !empty($section['title_en']) ? h($section['title_en']) : h($section['title_ja']); ?>
                                    </h2>
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
                        <h2
                            style="font-family: var(--font-title2-ja); font-size: 18px; font-weight: 400; line-height: 31px; color: var(--color-text); margin: 0 0 2px 0; text-align: left;">
                            相互リンク</h2>
                        <div
                            style="height: 10px; width: 800px; max-width: 100%; background-image: repeating-radial-gradient(circle, var(--color-primary) 0 2px, transparent 2px 12px); background-repeat: repeat-x; background-size: 12px 10px;">
                        </div>
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
                                <a href="<?php echo h($link['link_url']); ?>" target="_blank" <?php if (!empty($link['nofollow']) && $link['nofollow']): ?>rel="nofollow" <?php endif; ?>
                                    style="display: inline-block; margin: 1.5px;">
                                    <img src="<?php echo h($link['banner_image']); ?>" alt="<?php echo h($link['alt_text']); ?>"
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

    <?php
    if (isset($currentTheme['is_preview']) && $currentTheme['is_preview']) {
        echo generatePreviewBar($currentTheme, $tenantId, $tenant['code']);
    }
    ?>

</body>

</html>