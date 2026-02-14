<?php
/**
 * pullcass - 店舗トップページ（セクション動的生成対応）
 * ENTERボタンを押した後のメインページ
 */

// 共通ファイル読み込み
if (!function_exists('h')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
}
require_once __DIR__ . '/../../includes/theme_helper.php';
require_once __DIR__ . '/../../includes/top_section_renderer.php';

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
$pageTitle = 'トップ｜' . $shopName;
$pageDescription = $shopName . 'のオフィシャルサイトです。';

// PC/スマホ判定
$isMobile = preg_match('/Mobile|Android|iPhone|iPad/', $_SERVER['HTTP_USER_AGENT']);

// データベース接続取得
try {
    $pdo = getPlatformDb();
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("データベース接続エラー");
}

// セクションデータを取得（公開済みテーブルから）
$heroTextSection = null;
$leftSections = [];
$rightSections = [];
$mobileSections = [];

try {
    if ($isMobile) {
        // スマホ用セクション
        $stmtHero = $pdo->prepare("
            SELECT * FROM top_layout_sections_published 
            WHERE tenant_id = ? AND section_key = 'hero_text' AND mobile_visible = 1
            LIMIT 1
        ");
        $stmtHero->execute([$tenantId]);
        $heroTextSection = $stmtHero->fetch(PDO::FETCH_ASSOC);
        
        $stmtMobile = $pdo->prepare("
            SELECT * FROM top_layout_sections_published
            WHERE tenant_id = ? AND mobile_visible = 1 AND section_key != 'hero_text'
            ORDER BY mobile_order ASC
        ");
        $stmtMobile->execute([$tenantId]);
        $mobileSections = $stmtMobile->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // PC用セクション
        $stmtHero = $pdo->prepare("
            SELECT * FROM top_layout_sections_published 
            WHERE tenant_id = ? AND section_key = 'hero_text' AND is_visible = 1
            LIMIT 1
        ");
        $stmtHero->execute([$tenantId]);
        $heroTextSection = $stmtHero->fetch(PDO::FETCH_ASSOC);
        
        // 左カラム
        $stmtLeft = $pdo->prepare("
            SELECT * FROM top_layout_sections_published 
            WHERE tenant_id = ? AND is_visible = 1 AND pc_left_order IS NOT NULL
            ORDER BY pc_left_order ASC
        ");
        $stmtLeft->execute([$tenantId]);
        $leftSections = $stmtLeft->fetchAll(PDO::FETCH_ASSOC);
        
        // 右カラム
        $stmtRight = $pdo->prepare("
            SELECT * FROM top_layout_sections_published 
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

// トップバナーを取得
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

// 日付情報
date_default_timezone_set('Asia/Tokyo');
$today = date('n/j');
$dayOfWeek = ['日', '月', '火', '水', '木', '金', '土'][date('w')];

// ニュースティッカーを取得
$newsTickers = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM news_tickers 
        WHERE tenant_id = ? AND is_visible = 1 
        ORDER BY display_order ASC
    ");
    $stmt->execute([$tenantId]);
    $newsTickers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("News ticker load error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<?php include __DIR__ . '/includes/head.php'; ?>
<?php renderSectionStyles(); ?>
</head>

<body>
    <!-- ヘッダー -->
    <?php include __DIR__ . '/includes/header.php'; ?>
    
    <!-- メインコンテンツ -->
    <main class="main-content">
        <!-- パンくずナビ -->
        <nav class="breadcrumb">
            <a href="/"><?php echo h($shopName); ?></a><span> » </span>トップ
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
        
        <!-- ニュースティッカー -->
        <?php if (count($newsTickers) > 0): ?>
        <section class="news-ticker-section">
            <div class="news-ticker-container">
                <div class="news-ticker">
                    <div class="news-ticker-wrapper">
                        <?php foreach ($newsTickers as $item): ?>
                            <div class="news-item">
                                <?php if (!empty($item['url'])): ?>
                                    <a href="<?php echo h($item['url']); ?>">
                                        <?php echo h($item['text']); ?>
                                    </a>
                                <?php else: ?>
                                    <span><?php echo h($item['text']); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>
        <?php endif; ?>
        
        <!-- Hero Textセクション（H1タイトル） -->
        <?php if ($heroTextSection): ?>
            <?php renderHeroTextSection($heroTextSection); ?>
        <?php endif; ?>
        
        <!-- メインコンテンツレイアウト -->
        <div class="main-content-container">
            <?php if ($isMobile): ?>
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
        // トップバナーSwiper初期化
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
    </script>
    
    <!-- 横スクロール用のグラデーション制御 -->
    <script src="/assets/js/top.js"></script>
    
    <!-- 閲覧履歴スクリプト -->
    <script src="/assets/js/history.js"></script>
</body>
</html>
