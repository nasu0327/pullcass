<?php
/**
 * pullcass - キャスト詳細ページ
 * 参考: https://club-houman.com/cast/detail.php
 */

session_start();

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/theme_helper.php';

// テナント情報を取得
$tenant = getCurrentTenant();

if (!$tenant) {
    header('Location: /');
    exit;
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

// キャストIDを取得
$castId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$castId) {
    header('Location: /app/front/cast/list.php');
    exit;
}

// アクティブなデータソースを取得
$pdo = getPlatformDb();
$activeSource = 'ekichika'; // デフォルト

try {
    $stmt = $pdo->prepare("SELECT config_value FROM tenant_scraping_config WHERE tenant_id = ? AND config_key = 'active_source'");
    $stmt->execute([$tenantId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $activeSource = $result['config_value'];
    }
} catch (Exception $e) {
    // デフォルト値を使用
}

// キャストデータを取得
$cast = null;
try {
    $tableName = "tenant_cast_data_{$activeSource}";
    $stmt = $pdo->prepare("
        SELECT *
        FROM {$tableName}
        WHERE id = ? AND tenant_id = ? AND checked = 1
    ");
    $stmt->execute([$castId, $tenantId]);
    $cast = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Cast detail error: " . $e->getMessage());
}

if (!$cast) {
    header('Location: /app/front/cast/list.php');
    exit;
}

// 出勤スケジュールを配列に整理（7日分）
$schedule = [];
for ($i = 1; $i <= 7; $i++) {
    $dayKey = "day{$i}";
    if (isset($cast[$dayKey]) && $cast[$dayKey]) {
        $schedule[] = [
            'day' => $cast[$dayKey],
            'time' => '---' // 時間データは別途取得が必要
        ];
    }
}

// ページタイトル
$pageTitle = $cast['name'] . '｜' . $shopName;
$pageDescription = $shopName . 'の' . $cast['name'] . 'のプロフィールページです。';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
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
            --color-btn-text: <?php echo h($themeColors['btn_text']); ?>;
            --color-bg: <?php echo h($themeColors['bg']); ?>;
            --color-overlay: <?php echo h($themeColors['overlay']); ?>;
            --font-title-en: '<?php echo h($themeFonts['title1_en'] ?? 'Kranky'); ?>', cursive;
            --font-title-ja: '<?php echo h($themeFonts['title1_ja'] ?? 'Kaisei Decol'); ?>', serif;
            --font-body: '<?php echo h($themeFonts['body_ja'] ?? 'M PLUS 1p'); ?>', sans-serif;
        }
        
        body {
            font-family: var(--font-body);
            line-height: 1.6;
            color: var(--color-text);
            <?php if (isset($themeColors['bg_type']) && $themeColors['bg_type'] === 'gradient'): ?>
            background: linear-gradient(135deg, <?php echo h($themeColors['bg_gradient_start'] ?? '#ffffff'); ?> 0%, <?php echo h($themeColors['bg_gradient_end'] ?? '#ffd2fe'); ?> 100%);
            <?php else: ?>
            background: <?php echo h($themeColors['bg']); ?>;
            <?php endif; ?>
            min-height: 100vh;
        }
        
        /* ヘッダー */
        .site-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 15px;
            background-color: rgba(255, 255, 255, 0.3);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(7px);
            -webkit-backdrop-filter: blur(7px);
        }
        
        .header-logo-area {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }
        
        .header-logo-area img {
            max-height: 45px;
            width: auto;
        }
        
        .header-shop-info {
            display: none;
        }
        
        @media (min-width: 768px) {
            .header-shop-info {
                display: flex;
                flex-direction: column;
                font-size: 12px;
                line-height: 1.3;
                color: var(--color-text);
            }
        }
        
        .menu-button {
            background: var(--color-primary);
            border: none;
            padding: 10px 15px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 3px;
            width: 55px;
            height: 55px;
            justify-content: center;
            text-decoration: none;
        }
        
        .menu-button .line {
            display: block;
            width: 20px;
            height: 2px;
            background: var(--color-btn-text);
        }
        
        .menu-button span:last-child {
            color: var(--color-btn-text);
            font-size: 9px;
            font-weight: bold;
            margin-top: 2px;
        }
        
        /* メインコンテンツ */
        .main-content {
            padding-top: 75px;
            padding-bottom: 80px;
        }
        
        /* パンくず */
        .breadcrumb {
            padding: 10px 15px;
            font-size: 12px;
            color: var(--color-primary);
        }
        
        .breadcrumb a {
            color: var(--color-primary);
            text-decoration: none;
        }
        
        .breadcrumb span {
            margin: 0 3px;
            color: var(--color-text);
        }
        
        /* タイトルセクション */
        .title-section {
            text-align: left;
            padding: 10px 20px;
        }
        
        .title-section h1 {
            font-family: var(--font-title-en);
            font-size: 2.2em;
            color: var(--color-primary);
            margin: 0;
            line-height: 1;
            font-style: italic;
        }
        
        .title-section h2 {
            font-family: var(--font-title-ja);
            font-size: 0.95em;
            color: var(--color-text);
            margin-top: 3px;
            font-weight: 400;
        }
        
        .dot-line {
            width: 100%;
            max-width: 100%;
            margin: 10px 0;
            height: 1px;
            background: repeating-linear-gradient(
                to right,
                var(--color-primary) 0,
                var(--color-primary) 4px,
                transparent 4px,
                transparent 8px
            );
        }
        
        /* キャストコンテンツ */
        .cast-content {
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
            padding: 0 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        /* スライダー */
        .swiper-container {
            flex: 1;
            min-width: 300px;
            max-width: 500px;
            position: relative;
        }
        
        .swiper {
            width: 100%;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        
        .swiper-slide {
            padding: 2px;
        }
        
        .swiper-slide img {
            width: 100%;
            height: auto;
            border-radius: 15px;
        }
        
        .swiper-button-prev,
        .swiper-button-next {
            color: var(--color-primary);
            opacity: 0.7;
            width: 40px;
            height: 40px;
        }
        
        .swiper-button-prev:hover,
        .swiper-button-next:hover {
            opacity: 1;
        }
        
        .swiper-button-prev::after,
        .swiper-button-next::after {
            font-size: 30px;
            font-weight: bold;
        }
        
        .swiper-pagination {
            position: relative;
            bottom: 0;
            margin-top: 20px;
        }
        
        .swiper-pagination-bullet {
            width: 10px;
            height: 10px;
            background: var(--color-primary);
            opacity: 0.5;
        }
        
        .swiper-pagination-bullet-active {
            opacity: 1;
        }
        
        /* マイキャストボタン */
        .favorite-button-container {
            position: absolute;
            bottom: 58px;
            right: 15px;
            z-index: 100;
        }
        
        .favorite-button {
            background: transparent;
            border: none;
            padding: 2px;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
            animation: doublePulse 0.9s ease-in-out infinite;
        }
        
        .favorite-text {
            font-size: 10px;
            color: #ff1493;
            font-weight: 600;
            line-height: 1;
        }
        
        .favorite-icon {
            font-size: 28px;
            line-height: 1;
            color: #ff1493;
        }
        
        @keyframes doublePulse {
            0%, 100% { transform: scale(1); }
            8% { transform: scale(1.2); }
            15% { transform: scale(1); }
            23% { transform: scale(1.2); }
            30%, 100% { transform: scale(1); }
        }
        
        /* キャスト情報 */
        .cast-info-sidebar {
            flex: 1;
            min-width: 300px;
        }
        
        .cast-name-age {
            text-align: center;
            margin-bottom: 10px;
        }
        
        .cast-name-age h3 {
            font-size: 1.8em;
            margin: 0;
            color: var(--color-text);
            font-family: var(--font-body);
        }
        
        .cast-name-age h3 span {
            font-size: 0.8em;
            color: var(--color-text);
        }
        
        .cast-pr-title {
            font-size: 1.6em;
            font-weight: bold;
            margin-bottom: 5px;
            color: var(--color-text);
            font-family: var(--font-body);
        }
        
        .cast-stats {
            margin-bottom: 5px;
            line-height: 1.2;
        }
        
        .cast-stats p {
            margin: 0;
            font-size: 1.3em;
            font-weight: bold;
            color: var(--color-text);
            font-family: var(--font-body);
        }
        
        .cast-stats .cup-size {
            font-size: 1.5em;
        }
        
        .cast-badges {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
            margin: 5px 0 20px;
        }
        
        .badge {
            font-size: 12px;
            padding: 0 8px;
            border-radius: 10px;
            font-weight: bold;
            line-height: 1.5;
            color: var(--color-primary);
            background-color: transparent;
            border: 1px solid var(--color-primary);
        }
        
        .badge.new {
            color: #ff4444;
            border-color: #ff4444;
        }
        
        .badge.today {
            color: var(--color-primary);
            border-color: var(--color-primary);
        }
        
        .badge.now {
            color: #4caf50;
            border-color: #4caf50;
        }
        
        .badge.closed {
            color: #9e9e9e;
            border-color: #9e9e9e;
        }
        
        .cast-pr-text {
            margin-top: 20px;
            line-height: 1.2;
            color: var(--color-text);
            font-family: var(--font-body);
            text-align: left;
        }
        
        /* 出勤スケジュール */
        .schedule-section {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .schedule-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px 0;
            table-layout: fixed;
            margin-top: 10px;
        }
        
        .schedule-table th {
            padding: 8px 4px;
            text-align: center;
            font-weight: bold;
            color: var(--color-btn-text);
            font-family: var(--font-body);
            background: var(--color-primary);
            border-radius: 10px 10px 0 0;
            white-space: nowrap;
            font-size: 0.9em;
        }
        
        .schedule-table td {
            padding: 8px 4px;
            text-align: center;
            border-radius: 0 0 10px 10px;
            font-family: var(--font-body);
            color: var(--color-text);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            background: rgba(255, 255, 255, 0.6);
            white-space: nowrap;
            font-size: 0.85em;
        }
        
        /* スマホ用スケジュール */
        .sp-schedule {
            display: none;
        }
        
        .sp-schedule-scroll {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding: 0;
            -webkit-overflow-scrolling: touch;
        }
        
        .sp-schedule-item {
            width: 115px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            flex-shrink: 0;
        }
        
        .sp-schedule-item .day {
            color: var(--color-btn-text);
            font-weight: bold;
            font-size: 0.9em;
            padding: 8px;
            text-align: center;
            background: var(--color-primary);
            border-radius: 10px 10px 0 0;
        }
        
        .sp-schedule-item .time {
            color: var(--color-text);
            text-align: center;
            padding: 8px 3px;
            font-size: 0.9em;
            background: rgba(255, 255, 255, 0.6);
            border-radius: 0 0 10px 10px;
        }
        
        /* 予約ボタン */
        .reserve-section {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .reserve-button {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 20px;
            background-color: rgba(255, 255, 255, 0.5);
            border-radius: 20px;
            font-weight: bold;
            font-family: var(--font-body);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border: none;
            cursor: pointer;
            color: var(--color-text);
            text-decoration: none;
            max-width: 400px;
            margin: 10px auto 0;
        }
        
        .reserve-button:hover {
            background-color: rgba(255, 255, 255, 0.7);
        }
        
        .reserve-button img {
            width: auto;
            height: auto;
            max-height: 24px;
        }
        
        /* フッター */
        .fixed-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            background-color: rgba(255, 255, 255, 0.3);
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(7px);
            -webkit-backdrop-filter: blur(7px);
        }
        
        .fixed-footer-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 15px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .fixed-footer-info {
            font-size: 12px;
            color: var(--color-text);
        }
        
        .fixed-footer-info p {
            margin: 0;
            line-height: 1.4;
        }
        
        .phone-button {
            background: var(--color-primary);
            color: var(--color-btn-text);
            border: none;
            border-radius: 25px;
            padding: 10px 20px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .phone-button i {
            font-size: 16px;
        }
        
        /* セクション下の影 */
        .section-shadow {
            width: 100%;
            height: 15px;
            background-color: transparent;
            box-shadow: 0 -8px 12px -4px rgba(0, 0, 0, 0.2);
            position: relative;
        }
        
        /* レスポンシブ対応 */
        @media (max-width: 768px) {
            .cast-content {
                flex-direction: column;
                padding: 0 20px;
                gap: 0;
            }
            
            .swiper-container {
                max-width: 100%;
            }
            
            .cast-info-sidebar {
                min-width: 100%;
                margin-top: -10px;
                padding-top: 0;
            }
            
            .cast-name-age {
                margin: 0 0 10px 0;
                padding: 0;
            }
            
            .cast-name-age h3 {
                font-size: 25px;
                margin: 0;
                padding: 0;
            }
            
            .cast-pr-title {
                font-size: 1.2em;
                margin: 0 0 15px 0;
            }
            
            .cast-stats {
                margin: 0 0 5px 0;
            }
            
            .cast-stats p {
                font-size: 1em;
                margin: 0;
                line-height: 1;
                font-weight: bold;
            }
            
            .cast-badges {
                margin: 0 0 15px 0;
            }
            
            .cast-pr-text {
                line-height: 1.2;
                margin: 0;
                font-size: 0.9em;
            }
            
            .swiper-button-prev,
            .swiper-button-next {
                width: 30px;
                height: 30px;
            }
            
            .swiper-button-prev::after,
            .swiper-button-next::after {
                font-size: 20px;
            }
            
            .title-section h1 {
                font-size: 1.6em;
            }
            
            .phone-button {
                padding: 8px 15px;
                font-size: 14px;
            }
            
            /* PC用スケジュール非表示 */
            .pc-schedule {
                display: none;
            }
            
            /* スマホ用スケジュール表示 */
            .sp-schedule {
                display: block;
                margin-top: 0;
            }
            
            .favorite-button-container {
                bottom: 55px;
                right: 12px;
            }
            
            .favorite-text {
                font-size: 8px;
            }
            
            .favorite-icon {
                font-size: 24px;
            }
            
            .reserve-button {
                width: 100%;
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <!-- ヘッダー -->
    <header class="site-header">
        <a href="/app/front/top.php" class="header-logo-area">
            <?php if ($logoSmallUrl): ?>
                <img src="<?php echo h($logoSmallUrl); ?>" alt="<?php echo h($shopName); ?>">
            <?php else: ?>
                <span style="font-family: var(--font-title-en); font-size: 24px; color: var(--color-primary);"><?php echo h($shopName); ?></span>
            <?php endif; ?>
            <div class="header-shop-info">
                <span><?php echo h($shopTitle ?: '当店のキャストをご紹介'); ?></span>
            </div>
        </a>
        
        <a href="/app/front/top.php" class="menu-button">
            <span class="line"></span>
            <span class="line"></span>
            <span class="line"></span>
            <span>MENU</span>
        </a>
    </header>
    
    <main class="main-content">
        <!-- パンくず -->
        <nav class="breadcrumb">
            <a href="/app/front/index.php">ホーム</a><span>»</span>
            <a href="/app/front/top.php">トップ</a><span>»</span>
            <a href="/app/front/cast/list.php">キャスト一覧</a><span>»</span>
            <?php echo h($cast['name']); ?> |
        </nav>
        
        <!-- タイトルセクション -->
        <section class="title-section">
            <h1>PROFILE</h1>
            <h2>「<?php echo h($cast['name']); ?>」さんのプロフィール</h2>
            <div class="dot-line"></div>
        </section>
        
        <!-- キャストコンテンツ -->
        <div class="cast-content">
            <!-- 画像スライダー -->
            <div class="swiper-container">
                <div class="swiper cast-swiper">
                    <div class="swiper-wrapper">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <?php $imgKey = "img{$i}"; ?>
                            <?php if (!empty($cast[$imgKey])): ?>
                                <div class="swiper-slide">
                                    <div style="overflow: hidden; border-radius: 15px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                                        <img src="<?php echo h($cast[$imgKey]); ?>" 
                                             alt="<?php echo h($shopName . ' ' . $cast['name'] . ' 写真' . $i); ?>"
                                             loading="lazy">
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                    
                    <!-- マイキャスト登録ボタン -->
                    <div class="favorite-button-container">
                        <button class="favorite-button">
                            <span class="favorite-text">マイキャスト登録</span>
                            <span class="favorite-icon">♡</span>
                        </button>
                    </div>
                    
                    <div class="swiper-button-prev"></div>
                    <div class="swiper-button-next"></div>
                    <div class="swiper-pagination"></div>
                </div>
            </div>
            
            <!-- キャスト情報 -->
            <div class="cast-info-sidebar">
                <div class="cast-name-age">
                    <h3>
                        <?php echo h($cast['name']); ?>
                        <span><?php echo h($cast['age']); ?>歳</span>
                    </h3>
                </div>
                
                <?php if ($cast['pr_title']): ?>
                <h2 class="cast-pr-title">
                    <?php echo h($cast['pr_title']); ?>
                </h2>
                <?php endif; ?>
                
                <div class="cast-stats">
                    <p>
                        <span class="cup-size"><?php echo h($cast['cup']); ?></span> カップ
                    </p>
                    <?php if ($cast['height'] || $cast['size']): ?>
                    <p>
                        身長<?php echo h($cast['height']); ?>cm <?php echo h($cast['size']); ?>
                    </p>
                    <?php endif; ?>
                </div>
                
                <div class="cast-badges">
                    <?php if ($cast['new']): ?>
                        <span class="badge new">NEW</span>
                    <?php endif; ?>
                    <?php if ($cast['today']): ?>
                        <span class="badge today">本日出勤</span>
                    <?php endif; ?>
                    <?php if ($cast['now']): ?>
                        <span class="badge now">案内中</span>
                    <?php endif; ?>
                    <?php if ($cast['closed']): ?>
                        <span class="badge closed">受付終了</span>
                    <?php endif; ?>
                </div>
                
                <?php if ($cast['pr_text']): ?>
                <div class="cast-pr-text">
                    <?php echo nl2br(h($cast['pr_text'])); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- 出勤スケジュール -->
        <?php if (!empty($schedule)): ?>
        <section class="schedule-section">
            <div class="title-section" style="padding-left: 0;">
                <h1>SCHEDULE</h1>
                <h2>出勤表</h2>
                <div class="dot-line"></div>
            </div>
            
            <!-- PC表示用 -->
            <div class="pc-schedule" style="overflow-x: auto; -webkit-overflow-scrolling: touch;">
                <table class="schedule-table">
                    <tr>
                        <?php foreach ($schedule as $item): ?>
                            <th><?php echo h($item['day']); ?></th>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <?php foreach ($schedule as $item): ?>
                            <td><?php echo h($item['time']); ?></td>
                        <?php endforeach; ?>
                    </tr>
                </table>
            </div>
            
            <!-- スマホ表示用 -->
            <div class="sp-schedule">
                <div class="sp-schedule-scroll">
                    <?php foreach ($schedule as $item): ?>
                    <div class="sp-schedule-item">
                        <div class="day"><?php echo h($item['day']); ?></div>
                        <div class="time"><?php echo h($item['time']); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>
        
        <!-- 予約セクション -->
        <section class="reserve-section">
            <div class="title-section" style="padding-left: 0;">
                <h1>RESERVE</h1>
                <h2>ネット予約</h2>
                <div class="dot-line"></div>
            </div>
            
            <a href="/app/front/yoyaku.php?cast=<?php echo h($cast['id']); ?>" class="reserve-button">
                <?php if ($logoSmallUrl): ?>
                    <img src="<?php echo h($logoSmallUrl); ?>" alt="<?php echo h($cast['name']); ?>さんを予約する">
                <?php endif; ?>
                <?php echo h($cast['name']); ?>さんを予約する
            </a>
        </section>
        
        <!-- セクション下の影 -->
        <div class="section-shadow"></div>
    </main>
    
    <!-- 固定フッター -->
    <footer class="fixed-footer">
        <div class="fixed-footer-container">
            <div class="fixed-footer-info">
                <?php if ($businessHours): ?>
                <p>OPEN <?php echo h($businessHours); ?></p>
                <?php endif; ?>
                <?php if ($businessHoursNote): ?>
                <p><?php echo h($businessHoursNote); ?></p>
                <?php endif; ?>
            </div>
            
            <?php if ($phoneNumber): ?>
            <a href="tel:<?php echo h($phoneNumber); ?>" class="phone-button">
                <i class="fas fa-phone"></i>
                <?php echo h($phoneNumber); ?>
            </a>
            <?php endif; ?>
        </div>
    </footer>
    
    <!-- Swiper.js -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>
        const castSwiper = new Swiper('.cast-swiper', {
            loop: true,
            slidesPerView: 1,
            spaceBetween: 10,
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
            },
        });
    </script>
    
    <?php
    // プレビューバーを表示
    if ($currentTheme['is_preview']) {
        echo generatePreviewBar($tenant['code']);
    }
    ?>
</body>
</html>
