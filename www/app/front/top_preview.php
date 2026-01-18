<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

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

$tenantId = $tenant['id'];
$tenantSlug = $tenant['code'];

// 店舗情報（ヘッダー・フッター用）
$shopName = $tenant['name'];
$shopCode = $tenant['code'];
$shopTitle = $tenant['title'] ?? '';
$shopDescription = $tenant['description'] ?? '';
$logoLargeUrl = $tenant['logo_large_url'] ?? '';
$logoSmallUrl = $tenant['logo_small_url'] ?? '';
$faviconUrl = $tenant['favicon_url'] ?? '';
$phoneNumber = $tenant['phone'] ?? '';
$businessHours = $tenant['business_hours'] ?? '';
$businessHoursNote = $tenant['business_hours_note'] ?? '';

// テーマ設定を取得
require_once __DIR__ . '/../../includes/theme_helper.php';
$currentTheme = getCurrentTheme($tenantId);
$themeData = $currentTheme['theme_data'];

// スマホプレビューモードの判定
$isMobilePreview = isset($_GET['mobile']) && $_GET['mobile'] == '1';

// フレーム表示許可（管理画面からの表示用）
header('X-Frame-Options: ALLOWALL');
// CSPヘッダー：Font Awesome (cdnjs.cloudflare.com) とデータURIフォントを許可
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' \'unsafe-eval\' https://cdn.jsdelivr.net https://fonts.googleapis.com https://cdnjs.cloudflare.com; style-src \'self\' \'unsafe-inline\' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; font-src \'self\' data: https://fonts.gstatic.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; img-src \'self\' data: https:; connect-src \'self\' https://cdn.jsdelivr.net; frame-src \'self\' *;');

// キャッシュ制御（本日の出勤情報は常に最新を表示）
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// セクションレンダラーを読み込み
require_once __DIR__ . '/../../includes/top_section_renderer.php';

// top_preview.php - トップページ（プレビュー用・編集中の表示）

// セクションデータを取得
try {
    // mobile_visibleカラムが存在するかチェック
    $checkColumn = $pdo->query("SHOW COLUMNS FROM top_layout_sections LIKE 'mobile_visible'");
    $hasMobileVisible = ($checkColumn->rowCount() > 0);
    
    if ($isMobilePreview) {
        // スマホプレビューモード
        // トップバナー下テキスト（hero_text）を取得（スマホ用）
        if ($hasMobileVisible) {
            $stmt = $pdo->prepare("
                SELECT * FROM top_layout_sections 
                WHERE tenant_id = ? AND section_key = 'hero_text' AND mobile_visible = 1
                LIMIT 1
            ");
        } else {
            $stmt = $pdo->prepare("
                SELECT * FROM top_layout_sections 
                WHERE tenant_id = ? AND section_key = 'hero_text' AND is_visible = 1
                LIMIT 1
            ");
        }
        $stmt->execute([$tenantId]);
        $heroTextSection = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // スマホ用セクション（mobile_visibleを使用）
        if ($hasMobileVisible) {
            $stmt = $pdo->prepare("
                SELECT * FROM top_layout_sections
                WHERE tenant_id = ? AND mobile_visible = 1
                ORDER BY 
                    CASE 
                        WHEN mobile_order IS NOT NULL THEN mobile_order
                        WHEN pc_left_order IS NOT NULL THEN pc_left_order
                        WHEN pc_right_order IS NOT NULL THEN pc_right_order + 1000
                        ELSE 9999
                    END ASC
            ");
        } else {
            $stmt = $pdo->prepare("
                SELECT * FROM top_layout_sections
                WHERE tenant_id = ? AND is_visible = 1
                ORDER BY 
                    CASE 
                        WHEN mobile_order IS NOT NULL THEN mobile_order
                        WHEN pc_left_order IS NOT NULL THEN pc_left_order
                        WHEN pc_right_order IS NOT NULL THEN pc_right_order + 1000
                        ELSE 9999
                    END ASC
            ");
        }
        $stmt->execute([$tenantId]);
        $mobileSections = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // PC用は不要だが変数は用意
        $leftSections = [];
        $rightSections = [];
    } else {
        // PCプレビューモード
        // トップバナー下テキスト（hero_text）を取得（PC用）
        $stmt = $pdo->prepare("
            SELECT * FROM top_layout_sections 
            WHERE tenant_id = ? AND section_key = 'hero_text' AND is_visible = 1
            LIMIT 1
        ");
        $stmt->execute([$tenantId]);
        $heroTextSection = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // PC左カラム用セクション
        $stmt = $pdo->prepare("
            SELECT * FROM top_layout_sections 
            WHERE tenant_id = ? AND is_visible = 1 AND pc_left_order IS NOT NULL
            ORDER BY pc_left_order ASC
        ");
        $stmt->execute([$tenantId]);
        $leftSections = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // PC右カラム用セクション
        $stmt = $pdo->prepare("
            SELECT * FROM top_layout_sections 
            WHERE tenant_id = ? AND is_visible = 1 AND pc_right_order IS NOT NULL
            ORDER BY pc_right_order ASC
        ");
        $stmt->execute([$tenantId]);
        $rightSections = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // スマホ用は不要だが変数は用意
        $mobileSections = [];
    }
    
} catch (PDOException $e) {
    // エラー時は空配列
    $heroTextSection = null;
    $leftSections = [];
    $rightSections = [];
    $mobileSections = [];
}

// テナント情報を使用したページタイトル等
$pageTitle = h($tenant['name']) . " - トップページ（プレビュー）";
$pageDescription = "プレビューモード";
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo $pageTitle; ?></title>
    
    <!-- 共通head -->
    <?php include __DIR__ . '/includes/head.php'; ?>
    
    <!-- プレビューモード用スタイル -->
    <style>
        .preview-mode-badge {
            position: fixed;
            top: 10px;
            right: 10px;
            z-index: 10000;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #FF9800;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: bold;
            box-shadow: 0 4px 12px rgba(255, 152, 0, 0.4);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .preview-mode-badge:hover {
            opacity: 0.9;
            transform: scale(1.02);
        }
        .preview-mode-badge .exit-icon {
            font-size: 16px;
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <!-- プレビューバッジ -->
    <div class="preview-mode-badge" onclick="window.close();" title="クリックで閉じる">
        <i class="fas fa-eye"></i> プレビューモード
        <span class="exit-icon">×</span>
    </div>

    <!-- ヘッダー -->
    <?php include __DIR__ . '/includes/header.php'; ?>

    <main class="main-content">
        <!-- パンくずナビゲーション -->
        <nav class="breadcrumb">
            <a href="<?php echo '/' . $tenantSlug; ?>"><?php echo h($tenant['name']); ?></a><span>»</span>トップ（プレビュー）
        </nav>

        <section class="main-content">
            <!-- トップスライドバナー -->
            <section class="top-banner-section">
                <div class="top-banner-container">
                    <?php
                    // バナーの取得（表示中のもののみ）
                    $stmt = $pdo->prepare("SELECT * FROM top_banners WHERE tenant_id = ? AND is_visible = TRUE ORDER BY display_order ASC");
                    $stmt->execute([$tenantId]);
                    $banners = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (count($banners) > 0):
                    ?>
                    <!-- Swiper -->
                    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
                    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

                    <div class="swiper topBannerSwiper">
                        <div class="swiper-wrapper">
                            <?php foreach ($banners as $banner): ?>
                            <div class="swiper-slide">
                                <a href="<?php echo h($banner['pc_url']); ?>">
                                    <picture>
                                        <source media="(max-width: 767px)" srcset="<?php echo h($banner['sp_image']); ?>">
                                        <img src="<?php echo h($banner['pc_image']); ?>" alt="<?php echo h($banner['alt_text'] ?: 'トップバナー'); ?>">
                                    </picture>
                                </a>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="swiper-button-next"></div>
                        <div class="swiper-button-prev"></div>
                        <div class="swiper-pagination"></div>
                    </div>

                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            new Swiper('.topBannerSwiper', {
                                loop: true,
                                autoplay: {
                                    delay: 5000,
                                    disableOnInteraction: false
                                },
                                pagination: {
                                    el: '.swiper-pagination',
                                    clickable: true
                                },
                                navigation: {
                                    nextEl: '.swiper-button-next',
                                    prevEl: '.swiper-button-prev'
                                },
                                spaceBetween: 0
                            });
                        });
                    </script>
                    <?php else: ?>
                    <div style="height: 100px; background: rgba(245, 104, 223, 0.1); border-radius: 15px; display: flex; align-items: center; justify-content: center; color: var(--color-text);">
                        バナーが設定されていません
                    </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- メインタイトル（H1）とリード文 -->
            <?php 
            if ($heroTextSection && $heroTextSection['is_visible']): 
                $heroConfig = json_decode($heroTextSection['config'], true) ?? [];
                $h1Title = $heroConfig['h1_title'] ?? '';
                $introText = $heroConfig['intro_text'] ?? '';
                
                if (!empty(trim($h1Title)) || !empty(trim($introText))):
            ?>
            <section style="max-width: 1200px; margin: 20px auto 10px; padding: 0 15px;">
                <?php if (!empty(trim($h1Title))): ?>
                <h1 style="font-size: 20px; font-weight: bold; color: var(--color-text); text-align: center; line-height: 1.2; margin: 0; padding: 15px 0 10px;">
                    <?php echo h($h1Title); ?>
                </h1>
                <?php endif; ?>
                <?php if (!empty(trim($introText))): ?>
                <p style="font-size: 14px; color: var(--color-text); text-align: center; line-height: 1.2; margin: 0; padding: 0 10px 15px;">
                    <?php echo h($introText); ?>
                </p>
                <?php endif; ?>
            </section>
            <?php 
                endif;
            endif; 
            ?>

            <!-- メインコンテンツ -->
            <?php if ($isMobilePreview): ?>
            <!-- スマホプレビューモード -->
            <section class="main-content-sections">
                <div class="main-content-container" style="display: block;">
                    <div class="mobile-section-list">
                        <?php
                        foreach ($mobileSections as $section) {
                            if ($section['section_key'] === 'hero_text') continue;
                            renderSection($section, $pdo, $tenantId);
                        }
                        ?>
                    </div>
                </div>
            </section>
            <?php else: ?>
            <!-- PC用2カラムレイアウト -->
            <section class="main-content-sections">
                <div class="main-content-container">
                    <!-- 左カラム -->
                    <div class="left-section">
                        <?php
                        foreach ($leftSections as $section) {
                            renderSection($section, $pdo, $tenantId);
                        }
                        ?>
                    </div>

                    <!-- 右カラム -->
                    <div class="right-section">
                        <?php
                        foreach ($rightSections as $section) {
                            renderSection($section, $pdo, $tenantId);
                        }
                        ?>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <!-- セクション下の影 -->
            <div class="w-full h-[15px]" style="background-color:transparent; box-shadow:0 -8px 12px -4px rgba(0,0,0,0.2); position:relative;"></div>
        </section>
    </main>

    <!-- フッター -->
    <?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
