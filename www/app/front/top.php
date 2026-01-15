<?php
/**
 * pullcass - 店舗トップページ
 * ENTERボタンを押した後のメインページ
 * 参考: https://club-1914.jp/top/
 */

// index.phpから呼ばれた場合はbootstrapは既に読み込まれている
if (!function_exists('h')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
}

// テーマヘルパーを読み込む
require_once __DIR__ . '/../../includes/theme_helper.php';

// テナント情報を取得（セッションになければリクエストから取得）
$tenant = getCurrentTenant();

if (!$tenant) {
    // セッションにない場合はリクエストからテナントを判別
    $tenant = getTenantFromRequest();
    if ($tenant) {
        setCurrentTenant($tenant);
    } else {
        // テナントが見つからない場合はプラットフォームトップへ
        header('Location: https://pullcass.com/');
        exit;
    }
}

// 店舗情報
$shopName = $tenant['name'];
$shopCode = $tenant['code'];
$tenantId = $tenant['id'];
$shopTitle = $tenant['title'] ?? '';
$shopDescription = $tenant['description'] ?? '';

// ロゴ画像
$logoLargeUrl = $tenant['logo_large_url'] ?? '';
$logoSmallUrl = $tenant['logo_small_url'] ?? '';
$faviconUrl = $tenant['favicon_url'] ?? '';

// 電話番号
$phoneNumber = $tenant['phone'] ?? '';

// 営業時間
$businessHours = $tenant['business_hours'] ?? '';
$businessHoursNote = $tenant['business_hours_note'] ?? '';

// テーマを取得（プレビュー対応）
$currentTheme = getCurrentTheme($tenantId);
$themeData = $currentTheme['theme_data'];
$themeColors = $themeData['colors'];
$themeFonts = $themeData['fonts'] ?? [];

// 後方互換性
if (!isset($themeFonts['body_ja'])) {
    $themeFonts['body_ja'] = 'Zen Kaku Gothic New';
}

// ページタイトル
$pageTitle = 'トップ｜' . $shopName;
$pageDescription = $shopName . 'のオフィシャルサイトです。';

// トップバナーを取得
$topBanners = [];
try {
    $pdo = getPlatformDb();
    if ($pdo) {
        $stmt = $pdo->prepare("SELECT * FROM top_banners WHERE tenant_id = ? AND is_visible = 1 ORDER BY display_order ASC");
        $stmt->execute([$tenantId]);
        $topBanners = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    // エラー時は空配列のまま
}

// 今日の日付
$today = date('n/j');
$dayOfWeek = ['日', '月', '火', '水', '木', '金', '土'][date('w')];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo h($pageDescription); ?>">
    <title><?php echo h($pageTitle); ?></title>
    <?php if ($faviconUrl): ?>
    <link rel="icon" type="image/png" href="<?php echo h($faviconUrl); ?>">
    <?php endif; ?>
    <?php echo generateGoogleFontsLink(); ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --color-primary: <?php echo h($themeColors['primary']); ?>;
            --color-primary-light: <?php echo h($themeColors['primary_light']); ?>;
            --color-accent: <?php echo h($themeColors['primary_light']); ?>;
            --color-text: <?php echo h($themeColors['text']); ?>;
            --color-text-light: #888;
            --color-btn-text: <?php echo h($themeColors['btn_text']); ?>;
            --color-bg: <?php echo h($themeColors['bg']); ?>;
            --color-card-bg: #ffffff;
            --color-border: #f0e0dc;
            --font-body: '<?php echo h($themeFonts['body_ja']); ?>', sans-serif;
            --font-title1: '<?php echo h($themeFonts['title1_en'] ?? 'Kranky'); ?>', '<?php echo h($themeFonts['title1_ja'] ?? 'Kaisei Decol'); ?>', sans-serif;
            --font-title2: '<?php echo h($themeFonts['title2_en'] ?? 'Kranky'); ?>', '<?php echo h($themeFonts['title2_ja'] ?? 'Kaisei Decol'); ?>', sans-serif;
        }
        
        body {
            font-family: var(--font-body);
            <?php if (($themeColors['bg_type'] ?? 'solid') === 'gradient'): ?>
            background: linear-gradient(90deg, <?php echo h($themeColors['bg_gradient_start'] ?? $themeColors['bg']); ?> 0%, <?php echo h($themeColors['bg_gradient_end'] ?? $themeColors['bg']); ?> 100%);
            <?php else: ?>
            background: var(--color-bg);
            <?php endif; ?>
            color: var(--color-text);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            padding-top: 70px;
            padding-bottom: 56px;
        }
        
        /* ==================== ヘッダー ==================== */
        .site-header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(7px);
            -webkit-backdrop-filter: blur(7px);
            box-shadow: rgba(0, 0, 0, 0.1) 0px 4px 6px -1px, rgba(0, 0, 0, 0.1) 0px 2px 4px -2px;
            height: 70px;
            display: flex;
            align-items: center;
        }
        
        .header-container {
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 15px;
        }
        
        .logo-area {
            display: flex;
            align-items: center;
            text-decoration: none;
            color: inherit;
        }
        
        .logo-image {
            width: 50px;
            height: 50px;
            object-fit: contain;
            margin-right: 12px;
        }
        
        .logo-text {
            display: flex;
            flex-direction: column;
        }
        
        .logo-main-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--color-text);
            line-height: 1.3;
        }
        
        .logo-sub-title {
            font-size: 13px;
            font-weight: 700;
            color: var(--color-text);
            line-height: 1.3;
        }
        
        /* ハンバーガーメニューボタン */
        .hamburger-button {
            width: 56px;
            height: 56px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: linear-gradient(to right bottom, var(--color-primary), var(--color-primary-light));
            color: var(--color-btn-text);
            border-radius: 9999px;
            border: none;
            cursor: pointer;
            transition: transform 0.2s;
            box-shadow: none;
        }
        
        .hamburger-button:hover {
            transform: scale(1.05);
        }
        
        .hamburger-lines {
            display: flex;
            flex-direction: column;
            gap: 4px;
            margin-bottom: 3px;
        }
        
        .hamburger-line {
            width: 22px;
            height: 2px;
            background: var(--color-btn-text);
            border-radius: 1px;
        }
        
        .menu-text {
            font-size: 12px;
            font-weight: 500;
            line-height: 1;
        }
        
        /* ==================== メインコンテンツ ==================== */
        .main-content {
            flex: 1;
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
            padding: 15px;
        }
        
        /* メインスライダーエリア */
        .slider-section {
            background: var(--color-card-bg);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
            min-height: 200px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--color-border);
        }
        
        .slider-section.has-banners {
            padding: 0;
            min-height: auto;
            overflow: hidden;
        }
        
        /* Swiper スタイル */
        .main-swiper {
            width: 100%;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .main-swiper .swiper-slide {
            width: 100%;
        }
        
        .slide-link {
            display: block;
            width: 100%;
        }
        
        .slide-image {
            width: 100%;
            height: auto;
            display: block;
        }
        
        /* PC/SP画像の表示切り替え */
        .pc-link { display: block; }
        .sp-link { display: none; }
        
        @media (max-width: 768px) {
            .pc-link { display: none; }
            .sp-link { display: block; }
        }
        
        /* Swiper ナビゲーションボタン */
        .main-swiper .swiper-button-next,
        .main-swiper .swiper-button-prev {
            background: rgba(255, 255, 255, 0.9);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: all 0.2s;
        }
        
        .main-swiper .swiper-button-next::after,
        .main-swiper .swiper-button-prev::after {
            font-size: 16px;
            font-weight: bold;
            color: var(--color-text);
        }
        
        .main-swiper .swiper-button-next:hover,
        .main-swiper .swiper-button-prev:hover {
            background: var(--color-primary);
        }
        
        .main-swiper .swiper-button-next:hover::after,
        .main-swiper .swiper-button-prev:hover::after {
            color: white;
        }
        
        /* Swiper ページネーション（ドット） */
        .main-swiper .swiper-pagination {
            bottom: 15px;
        }
        
        .main-swiper .swiper-pagination-bullet {
            width: 10px;
            height: 10px;
            background: rgba(255, 255, 255, 0.5);
            opacity: 1;
        }
        
        .main-swiper .swiper-pagination-bullet-active {
            background: var(--color-primary);
        }
        
        .slider-placeholder {
            color: var(--color-text-light);
        }
        
        .slider-placeholder i {
            font-size: 3rem;
            color: var(--color-primary);
            opacity: 0.5;
            margin-bottom: 15px;
        }
        
        /* 店長オススメティッカー */
        .ticker-section {
            background: var(--color-card-bg);
            border-radius: 8px;
            padding: 12px 15px;
            margin-bottom: 20px;
            border: 1px solid var(--color-border);
            display: flex;
            align-items: center;
            gap: 15px;
            overflow: hidden;
        }
        
        .ticker-label {
            background: var(--color-accent);
            color: white;
            padding: 5px 12px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }
        
        .ticker-content {
            color: var(--color-text-light);
            font-size: 13px;
        }
        
        /* セクションタイトル */
        .section-header {
            margin: 25px 0 15px;
        }
        
        .section-title-en {
            font-size: 24px;
            font-weight: 700;
            color: var(--color-accent);
            margin: 0;
            line-height: 1.2;
        }
        
        .section-title-jp {
            font-size: 13px;
            color: var(--color-text);
            margin: 2px 0 8px;
        }
        
        .section-divider {
            height: 10px;
            width: 100%;
            background-image: repeating-radial-gradient(circle, var(--color-primary) 0 2px, transparent 2px 12px);
            background-repeat: repeat-x;
            background-size: 12px 10px;
        }
        
        /* 準備中カード */
        .coming-soon-card {
            background: var(--color-card-bg);
            border: 1px solid var(--color-border);
            border-radius: 10px;
            padding: 30px 20px;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .coming-soon-card i {
            font-size: 2.5rem;
            color: var(--color-primary);
            opacity: 0.4;
            margin-bottom: 12px;
        }
        
        .coming-soon-card h3 {
            font-size: 1rem;
            color: var(--color-text);
            margin-bottom: 8px;
        }
        
        .coming-soon-card p {
            color: var(--color-text-light);
            font-size: 0.85rem;
        }
        
        /* 2カラムレイアウト */
        .two-column {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0 30px;
        }
        
        @media (min-width: 900px) {
            .two-column {
                grid-template-columns: 2fr 1fr;
            }
        }
        
        /* サイドバー */
        .sidebar-section {
            margin-bottom: 20px;
        }
        
        .sidebar-banner {
            display: block;
            background: var(--color-card-bg);
            border: 1px solid var(--color-border);
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            text-decoration: none;
            color: var(--color-text);
            transition: all 0.2s;
        }
        
        .sidebar-banner:hover {
            border-color: var(--color-primary);
            box-shadow: 0 4px 15px rgba(245, 104, 223, 0.15);
        }
        
        .sidebar-banner i {
            font-size: 2rem;
            color: var(--color-primary);
            opacity: 0.5;
            display: block;
            margin-bottom: 10px;
        }
        
        .sidebar-banner span {
            font-size: 13px;
            color: var(--color-text-light);
        }
        
        /* ==================== 固定フッター ==================== */
        .fixed-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background: rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(7px);
            -webkit-backdrop-filter: blur(7px);
            padding: 8px 0;
            z-index: 1000;
            box-shadow: rgba(0, 0, 0, 0.15) 0px -4px 20px 0px;
            height: 56px;
            display: flex;
            align-items: center;
        }
        
        .fixed-footer-container {
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
            padding: 0 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .fixed-footer-info {
            color: var(--color-text);
            font-size: 12px;
            line-height: 1.4;
        }
        
        .fixed-footer-info .open-hours {
            font-weight: 700;
            font-size: 14px;
        }
        
        .phone-button {
            display: flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(to right bottom, var(--color-primary), var(--color-primary-light));
            color: var(--color-btn-text);
            padding: 10px 20px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 700;
            font-size: 14px;
            transition: transform 0.2s;
            box-shadow: none;
        }
        
        .phone-button:hover {
            transform: scale(1.03);
        }
        
        .phone-button i {
            font-size: 1rem;
        }
        
        /* ==================== 共通フッタースタイル ==================== */
        <?php include __DIR__ . '/includes/header_styles.php'; ?>
        
        /* ==================== レスポンシブ ==================== */
        @media (max-width: 600px) {
            body {
                padding-top: 60px;
                padding-bottom: 50px;
            }
            
            .site-header {
                height: 60px;
            }
            
            .logo-image {
                width: 40px;
                height: 40px;
            }
            
            .logo-main-title {
                font-size: 13px;
            }
            
            .logo-sub-title {
                font-size: 11px;
            }
            
            .hamburger-button {
                width: 48px;
                height: 48px;
            }
            
            .hamburger-line {
                width: 18px;
            }
            
            .fixed-footer {
                height: auto;
                min-height: 50px;
                padding: 10px 0;
            }
            
            .fixed-footer-container {
                flex-direction: row;
                gap: 10px;
                padding: 0 12px;
            }
            
            .fixed-footer-info {
                text-align: left;
                font-size: 11px;
                flex: 1;
                min-width: 0;
            }
            
            .fixed-footer-info .open-hours {
                font-size: 13px;
            }
            
            .phone-button {
                padding: 4px 12px;
                font-size: 12px;
                white-space: nowrap;
                flex-shrink: 0;
            }
            
            .section-title-en {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- ヘッダー -->
    <header class="site-header">
        <div class="header-container">
            <a href="/app/front/top.php" class="logo-area">
                <?php if ($logoSmallUrl): ?>
                    <img src="<?php echo h($logoSmallUrl); ?>" alt="<?php echo h($shopName); ?>" class="logo-image">
                <?php elseif ($logoLargeUrl): ?>
                    <img src="<?php echo h($logoLargeUrl); ?>" alt="<?php echo h($shopName); ?>" class="logo-image">
                <?php endif; ?>
                <div class="logo-text">
                    <?php if ($shopTitle): ?>
                        <?php 
                        $titleLines = explode("\n", $shopTitle);
                        foreach ($titleLines as $line): 
                            $line = trim($line);
                            if ($line):
                        ?>
                        <div class="logo-main-title"><?php echo h($line); ?></div>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    <?php else: ?>
                        <div class="logo-main-title"><?php echo h($shopName); ?></div>
                        <div class="logo-sub-title">オフィシャルサイト</div>
                    <?php endif; ?>
                </div>
            </a>
            <button class="hamburger-button" aria-label="メニューを開く">
                <div class="hamburger-lines">
                    <span class="hamburger-line"></span>
                    <span class="hamburger-line"></span>
                    <span class="hamburger-line"></span>
                </div>
                <span class="menu-text">MENU</span>
            </button>
        </div>
    </header>
    
    <!-- メインコンテンツ -->
    <main class="main-content">
        <!-- パンくずナビ -->
        <nav class="breadcrumb">
            <a href="/app/front/index.php">ホーム</a><span>»</span>トップページ |
        </nav>
        
        <!-- メインスライダー (Swiper) -->
        <?php if (count($topBanners) > 0): ?>
        <section class="slider-section has-banners">
            <div class="swiper main-swiper">
                <div class="swiper-wrapper">
                    <?php foreach ($topBanners as $banner): ?>
                    <div class="swiper-slide">
                        <a href="<?php echo h($banner['pc_url']); ?>" class="slide-link pc-link" target="_blank" rel="noopener noreferrer">
                            <img src="<?php echo h($banner['pc_image']); ?>" alt="<?php echo h($banner['alt_text'] ?? ''); ?>" class="slide-image">
                        </a>
                        <a href="<?php echo h($banner['sp_url']); ?>" class="slide-link sp-link" target="_blank" rel="noopener noreferrer">
                            <img src="<?php echo h($banner['sp_image']); ?>" alt="<?php echo h($banner['alt_text'] ?? ''); ?>" class="slide-image">
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if (count($topBanners) > 1): ?>
                <!-- ナビゲーションボタン -->
                <div class="swiper-button-next"></div>
                <div class="swiper-button-prev"></div>
                <!-- ページネーション（ドット） -->
                <div class="swiper-pagination"></div>
                <?php endif; ?>
            </div>
        </section>
        <?php else: ?>
        <section class="slider-section">
            <div class="slider-placeholder">
                <i class="fas fa-images"></i>
                <p>メインビジュアル準備中</p>
                <p style="font-size: 12px; margin-top: 5px;">店舗管理画面からバナー画像を登録できます</p>
            </div>
        </section>
        <?php endif; ?>
        
        <!-- 店長オススメティッカー -->
        <section class="ticker-section">
            <span class="ticker-label"><?php echo h($today); ?>(<?php echo h($dayOfWeek); ?>)本日の店長オススメ✨</span>
            <span class="ticker-content">準備中...</span>
        </section>
        
        <!-- 2カラムレイアウト -->
        <div class="two-column">
            <!-- 左カラム（メイン） -->
            <div class="main-column">
                <!-- NEW CAST 新人 -->
                <div class="section-header">
                    <h2 class="section-title-en">NEW CAST</h2>
                    <p class="section-title-jp">新人</p>
                    <div class="section-divider"></div>
                </div>
                <div class="coming-soon-card">
                    <i class="fas fa-user-plus"></i>
                    <h3>新人情報準備中</h3>
                    <p>新人キャストは店舗管理画面から登録できます。</p>
                </div>
                
                <!-- TODAY 本日の出勤 -->
                <div class="section-header">
                    <h2 class="section-title-en">TODAY</h2>
                    <p class="section-title-jp">本日の出勤 <?php echo h($today); ?>(<?php echo h($dayOfWeek); ?>)</p>
                    <div class="section-divider"></div>
                </div>
                <div class="coming-soon-card">
                    <i class="fas fa-calendar-day"></i>
                    <h3>出勤情報準備中</h3>
                    <p>キャストの出勤情報は店舗管理画面から登録できます。</p>
                </div>
                
                <!-- REVIEW 口コミ -->
                <div class="section-header">
                    <h2 class="section-title-en">REVIEW</h2>
                    <p class="section-title-jp">口コミ</p>
                    <div class="section-divider"></div>
                </div>
                <div class="coming-soon-card">
                    <i class="fas fa-comment-dots"></i>
                    <h3>口コミ準備中</h3>
                    <p>口コミは連携設定後に表示されます。</p>
                </div>
                
                <!-- VIDEO 動画 -->
                <div class="section-header">
                    <h2 class="section-title-en">VIDEO</h2>
                    <p class="section-title-jp">動画</p>
                    <div class="section-divider"></div>
                </div>
                <div class="coming-soon-card">
                    <i class="fas fa-video"></i>
                    <h3>動画準備中</h3>
                    <p>動画は店舗管理画面から登録できます。</p>
                </div>
            </div>
            
            <!-- 右カラム（サイドバー） -->
            <div class="sidebar-column">
                <!-- COMIC 体験漫画 -->
                <section class="sidebar-section">
                    <div class="section-header">
                        <h2 class="section-title-en">COMIC</h2>
                        <p class="section-title-jp">体験漫画</p>
                        <div class="section-divider"></div>
                    </div>
                    <a href="/comic" class="sidebar-banner">
                        <i class="fas fa-book-open"></i>
                        <span>体験漫画準備中</span>
                    </a>
                </section>
                
                <!-- HOTEL LIST -->
                <section class="sidebar-section">
                    <div class="section-header">
                        <h2 class="section-title-en">HOTEL LIST</h2>
                        <p class="section-title-jp">ホテルリスト</p>
                        <div class="section-divider"></div>
                    </div>
                    <a href="/hotel_list" class="sidebar-banner">
                        <i class="fas fa-hotel"></i>
                        <span>ホテルリストを見る</span>
                    </a>
                </section>
                
                <!-- PHOTO BLOG 写メ日記 -->
                <section class="sidebar-section">
                    <div class="section-header">
                        <h2 class="section-title-en">PHOTO BLOG</h2>
                        <p class="section-title-jp">写メ日記</p>
                        <div class="section-divider"></div>
                    </div>
                    <a href="/diary" class="sidebar-banner">
                        <i class="fas fa-camera"></i>
                        <span>写メ日記準備中</span>
                    </a>
                </section>
                
                <!-- HISTORY 閲覧履歴 -->
                <section class="sidebar-section">
                    <div class="section-header">
                        <h2 class="section-title-en">HISTORY</h2>
                        <p class="section-title-jp">閲覧履歴</p>
                        <div class="section-divider"></div>
                    </div>
                    <div class="coming-soon-card" style="padding: 20px;">
                        <p style="color: var(--color-text-light); font-size: 13px;">閲覧履歴はありません</p>
                    </div>
                </section>
            </div>
        </div>
    </main>
    
    <?php include __DIR__ . '/includes/footer_nav.php'; ?>
    
    <?php include __DIR__ . '/includes/footer.php'; ?>

    <?php if (count($topBanners) > 0): ?>
    <!-- Swiper JS -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const swiper = new Swiper('.main-swiper', {
            // スライドがループする
            loop: true,
            // スライド間のスペース
            spaceBetween: 0,
            // 自動再生
            autoplay: {
                delay: 5000,
                disableOnInteraction: false,
            },
            // スピード（ミリ秒）- スムーズな横スライド
            speed: 500,
            // ナビゲーション
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
            // ページネーション（ドット）
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
            },
        });
    });
    </script>
    <?php endif; ?>
    
    <?php
    // プレビューモードの場合はプレビューバーを表示
    if (isset($currentTheme['is_preview']) && $currentTheme['is_preview']) {
        echo generatePreviewBar($currentTheme, $tenantId, $tenant['code']);
    }
    ?>
</body>
</html>
