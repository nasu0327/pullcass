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

// PC/スマホ判定（top.phpと同じ方法）
$isMobile = preg_match('/Mobile|Android|iPhone|iPad/', $_SERVER['HTTP_USER_AGENT']);
$isMobilePreview = isset($_GET['mobile']) && $_GET['mobile'] == '1';

// プレビューバッジ表示（iframe内でない場合のみ）
$isInIframe = isset($_GET['iframe_preview']) && $_GET['iframe_preview'] == '1';
$showPreviewBadge = !$isInIframe;
$isThemePreview = isset($_GET['preview_id']) || (isset($_SESSION['theme_preview_id']) && $_SESSION['theme_preview_id']);

// フレーム表示許可（管理画面からの表示用）
header('X-Frame-Options: ALLOWALL');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' \'unsafe-eval\' https://cdn.jsdelivr.net https://fonts.googleapis.com https://cdnjs.cloudflare.com; style-src \'self\' \'unsafe-inline\' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; font-src \'self\' data: https://fonts.gstatic.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; img-src \'self\' data: https:; connect-src \'self\' https://cdn.jsdelivr.net; frame-src \'self\' *;');

// キャッシュ制御
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// セクションレンダラーを読み込み
require_once __DIR__ . '/../../includes/top_section_renderer.php';

// セクションデータを取得（プレビュー用：編集中のtop_layout_sectionsから取得）
// これにより、管理画面での表示/非表示の変更が即座にプレビューに反映される
$heroTextSection = null;
$leftSections = [];
$rightSections = [];
$mobileSections = [];

try {
    // mobile_visibleカラムが存在するかチェック
    $checkColumn = $pdo->query("SHOW COLUMNS FROM top_layout_sections LIKE 'mobile_visible'");
    $hasMobileVisible = ($checkColumn->rowCount() > 0);
    
    if ($isMobile || $isMobilePreview) {
        // スマホ用セクション
        if ($hasMobileVisible) {
            $stmtHero = $pdo->prepare("
                SELECT * FROM top_layout_sections 
                WHERE tenant_id = ? AND section_key = 'hero_text' AND mobile_visible = 1
                LIMIT 1
            ");
        } else {
            $stmtHero = $pdo->prepare("
                SELECT * FROM top_layout_sections 
                WHERE tenant_id = ? AND section_key = 'hero_text' AND is_visible = 1
                LIMIT 1
            ");
        }
        $stmtHero->execute([$tenantId]);
        $heroTextSection = $stmtHero->fetch(PDO::FETCH_ASSOC);
        
        if ($hasMobileVisible) {
            $stmtMobile = $pdo->prepare("
                SELECT * FROM top_layout_sections
                WHERE tenant_id = ? AND mobile_visible = 1 AND section_key != 'hero_text'
                ORDER BY 
                    CASE 
                        WHEN mobile_order IS NOT NULL THEN mobile_order
                        WHEN pc_left_order IS NOT NULL THEN pc_left_order
                        WHEN pc_right_order IS NOT NULL THEN pc_right_order + 1000
                        ELSE 9999
                    END ASC
            ");
        } else {
            $stmtMobile = $pdo->prepare("
                SELECT * FROM top_layout_sections
                WHERE tenant_id = ? AND is_visible = 1 AND section_key != 'hero_text'
                ORDER BY 
                    CASE 
                        WHEN mobile_order IS NOT NULL THEN mobile_order
                        WHEN pc_left_order IS NOT NULL THEN pc_left_order
                        WHEN pc_right_order IS NOT NULL THEN pc_right_order + 1000
                        ELSE 9999
                    END ASC
            ");
        }
        $stmtMobile->execute([$tenantId]);
        $mobileSections = $stmtMobile->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // PC用セクション
        $stmtHero = $pdo->prepare("
            SELECT * FROM top_layout_sections 
            WHERE tenant_id = ? AND section_key = 'hero_text' AND is_visible = 1
            LIMIT 1
        ");
        $stmtHero->execute([$tenantId]);
        $heroTextSection = $stmtHero->fetch(PDO::FETCH_ASSOC);
        
        // 左カラム
        $stmtLeft = $pdo->prepare("
            SELECT * FROM top_layout_sections 
            WHERE tenant_id = ? AND is_visible = 1 AND pc_left_order IS NOT NULL
            ORDER BY pc_left_order ASC
        ");
        $stmtLeft->execute([$tenantId]);
        $leftSections = $stmtLeft->fetchAll(PDO::FETCH_ASSOC);
        
        // 右カラム
        $stmtRight = $pdo->prepare("
            SELECT * FROM top_layout_sections 
            WHERE tenant_id = ? AND is_visible = 1 AND pc_right_order IS NOT NULL
            ORDER BY pc_right_order ASC
        ");
        $stmtRight->execute([$tenantId]);
        $rightSections = $stmtRight->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Section load error: " . $e->getMessage());
    $heroTextSection = null;
    $leftSections = [];
    $rightSections = [];
    $mobileSections = [];
}

// トップバナーを取得（本番と同じ）
$topBanners = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM top_banners 
        WHERE tenant_id = ? AND is_visible = 1 
        ORDER BY display_order ASC
    ");
    $stmt->execute([$tenantId]);
    $topBanners = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Top banner load error: " . $e->getMessage());
}

// 日付情報（本番と同じ）
date_default_timezone_set('Asia/Tokyo');
$today = date('n/j');
$dayOfWeek = ['日', '月', '火', '水', '木', '金', '土'][date('w')];

// ページタイトル
$pageTitle = 'トップ｜' . $shopName;
$pageDescription = $shopName . 'のオフィシャルサイトです。';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<?php include __DIR__ . '/includes/head.php'; ?>
<?php renderSectionStyles(); ?>
    
    <!-- プレビューモード用スタイル -->
    <?php if ($showPreviewBadge && !$isThemePreview): ?>
    <style>
        .preview-mode-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: <?php echo $currentTheme['theme_data']['colors']['primary'] ?? '#f568df'; ?>;
            color: <?php echo $currentTheme['theme_data']['colors']['btn_text'] ?? '#ffffff'; ?>;
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
</head>

<body>
    <!-- ヘッダー -->
    <?php include __DIR__ . '/includes/header.php'; ?>
    
    <!-- メインコンテンツ -->
    <main class="main-content">
        <!-- パンくずナビ -->
        <nav class="breadcrumb">
            <?php if ($showPreviewBadge && !$isThemePreview): ?>
            <span class="preview-mode-badge" onclick="closePreview();" title="クリックで閉じる">プレビューモード <span class="exit-icon">✕</span></span>
            <?php endif; ?>
            <a href="/app/front/index.php"><?php echo h($shopName); ?></a><span> » </span>トップ
        </nav>
        
        <!-- メインスライダー (Swiper) -->
        <?php if (count($topBanners) > 0): ?>
        <section class="top-banner-section">
            <div class="top-banner-container">
                <div class="swiper topBannerSwiper">
                    <div class="swiper-wrapper">
                        <?php foreach ($topBanners as $banner): ?>
                        <div class="swiper-slide">
                            <a href="<?php echo h($banner['pc_url']); ?>">
                                <picture>
                                    <source media="(max-width: 767px)" srcset="<?php echo h($banner['sp_image']); ?>">
                                    <img src="<?php echo h($banner['pc_image']); ?>" alt="<?php echo h($banner['alt_text'] ?? ''); ?>">
                                </picture>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($topBanners) > 1): ?>
                    <div class="swiper-button-next"></div>
                    <div class="swiper-button-prev"></div>
                    <div class="swiper-pagination"></div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
        <?php else: ?>
        <section class="top-banner-section">
            <div class="top-banner-container">
                <div class="slider-placeholder">
                    <i class="fas fa-images"></i>
                    <p>メインビジュアル準備中</p>
                    <p style="font-size: 12px; margin-top: 5px;">店舗管理画面からバナー画像を登録できます</p>
                </div>
            </div>
        </section>
        <?php endif; ?>
        
        <!-- 店長オススメティッカー -->
        <section class="ticker-section">
            <span class="ticker-label"><?php echo h($today); ?>(<?php echo h($dayOfWeek); ?>)本日の店長オススメ</span>
            <span class="ticker-content">準備中...</span>
        </section>
        
        <!-- Hero Textセクション（H1タイトル） -->
        <?php if ($heroTextSection): ?>
            <?php renderHeroTextSection($heroTextSection); ?>
        <?php endif; ?>
        
        <!-- メインコンテンツレイアウト -->
        <div class="main-content-container">
            <?php if ($isMobile || $isMobilePreview): ?>
                <!-- スマホ版: 1カラムレイアウト -->
                <div class="mobile-sections">
                    <?php if (empty($mobileSections)): ?>
                <div class="section-card">
                            <div style="padding: 40px; text-align: center; color: var(--color-text); opacity: 0.6;">
                                <i class="fas fa-info-circle" style="font-size: 2rem; margin-bottom: 15px;"></i>
                                <p style="margin: 0; font-size: 1.1rem;">コンテンツは準備中です</p>
                                <p style="margin: 10px 0 0 0; font-size: 0.9rem;">店舗管理画面からトップページのレイアウトを設定できます</p>
                            </div>
                    </div>
                    <?php else: ?>
                        <?php foreach ($mobileSections as $section): ?>
                            <?php renderSection($section, $pdo, $tenantId); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <!-- PC版: 2カラムレイアウト -->
                <!-- 左カラム（メイン） -->
                <div class="left-section">
                    <?php if (empty($leftSections)): ?>
                <div class="section-card">
                            <div style="padding: 40px; text-align: center; color: var(--color-text); opacity: 0.6;">
                                <i class="fas fa-info-circle" style="font-size: 2rem; margin-bottom: 15px;"></i>
                                <p style="margin: 0; font-size: 1.1rem;">左カラムのコンテンツは準備中です</p>
                                <p style="margin: 10px 0 0 0; font-size: 0.9rem;">店舗管理画面からトップページのレイアウトを設定できます</p>
                            </div>
                    </div>
                    <?php else: ?>
                        <?php foreach ($leftSections as $section): ?>
                            <?php renderSection($section, $pdo, $tenantId); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- 右カラム（サイド） -->
            <div class="right-section">
                    <?php if (empty($rightSections)): ?>
                <div class="section-card">
                            <div style="padding: 40px; text-align: center; color: var(--color-text); opacity: 0.6;">
                                <i class="fas fa-info-circle" style="font-size: 2rem; margin-bottom: 15px;"></i>
                                <p style="margin: 0; font-size: 1.1rem;">右カラムのコンテンツは準備中です</p>
                                <p style="margin: 10px 0 0 0; font-size: 0.9rem;">店舗管理画面からトップページのレイアウトを設定できます</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($rightSections as $section): ?>
                            <?php renderSection($section, $pdo, $tenantId); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <!-- フッターナビゲーション -->
    <?php include __DIR__ . '/includes/footer_nav.php'; ?>
    
    <!-- 固定フッター（電話ボタン） -->
    <?php include __DIR__ . '/includes/footer.php'; ?>

    <!-- Swiper -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>
        // トップバナーSwiper初期化（本番と同じ）
        <?php if (count($topBanners) > 0): ?>
        new Swiper('.topBannerSwiper', {
            loop: true,
            spaceBetween: 0,
            autoplay: {
                delay: 5000,
                disableOnInteraction: false,
            },
            speed: 500,
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
            },
        });
        <?php endif; ?>
        
        // プレビューを閉じる関数
        function closePreview() {
            // window.close()を試す
            if (window.opener) {
                // ポップアップで開かれている場合
                window.close();
            } else {
                // 通常のタブで開かれている場合、管理画面に戻る
                const tenantSlug = '<?php echo $tenantSlug; ?>';
                window.location.href = '/app/manage/top_layout/?tenant=' + tenantSlug;
            }
        }
    </script>
    
    <!-- 横スクロール用のグラデーション制御 -->
    <script src="/assets/js/top.js"></script>
</body>
</html>
