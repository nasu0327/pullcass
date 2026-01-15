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

// 出勤スケジュールを配列に整理
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
            padding: 0 15px;
            height: 55px;
            background-color: rgba(255, 255, 255, 0.3);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(7px);
            -webkit-backdrop-filter: blur(7px);
        }
        
        .hamburger-button {
            background: linear-gradient(135deg, var(--color-primary), var(--color-primary-light));
            border: none;
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
            text-decoration: none;
        }
        
        .hamburger-button i {
            color: var(--color-btn-text);
            font-size: 18px;
        }
        
        .hamburger-button span {
            color: var(--color-btn-text);
            font-size: 10px;
            font-weight: bold;
        }
        
        .site-logo img {
            max-height: 50px;
            width: auto;
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .header-date {
            font-size: 12px;
            color: var(--color-text);
        }
        
        /* メインコンテンツ */
        .main-content {
            padding-top: 65px;
            padding-bottom: 80px;
        }
        
        /* パンくず */
        .breadcrumb {
            padding: 10px 15px;
            font-size: 12px;
            color: var(--color-text);
        }
        
        .breadcrumb a {
            color: var(--color-primary);
            text-decoration: none;
        }
        
        .breadcrumb span {
            margin: 0 5px;
        }
        
        /* タイトルセクション */
        .title-section {
            text-align: center;
            padding: 20px 15px 10px;
        }
        
        .title-section h1 {
            font-family: var(--font-title-en);
            font-size: 2em;
            color: var(--color-primary);
            margin: 0;
            line-height: 1.2;
        }
        
        .title-section h2 {
            font-family: var(--font-title-ja);
            font-size: 1em;
            color: var(--color-text);
            margin-top: 5px;
            font-weight: 400;
        }
        
        .dot-line {
            width: 100%;
            max-width: 400px;
            margin: 15px auto 0;
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
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        /* スライダー */
        .swiper-container {
            flex: 1;
            min-width: 300px;
            max-width: 500px;
        }
        
        .swiper {
            width: 100%;
            border-radius: 15px;
            overflow: hidden;
        }
        
        .swiper-slide {
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .swiper-slide img {
            width: 100%;
            height: auto;
            border-radius: 15px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        
        .swiper-button-prev,
        .swiper-button-next {
            color: var(--color-primary);
            opacity: 0.7;
        }
        
        .swiper-button-prev:hover,
        .swiper-button-next:hover {
            opacity: 1;
        }
        
        .swiper-button-prev::after,
        .swiper-button-next::after {
            font-size: 25px;
            font-weight: bold;
        }
        
        .swiper-pagination-bullet {
            background: var(--color-primary);
            opacity: 0.5;
        }
        
        .swiper-pagination-bullet-active {
            opacity: 1;
        }
        
        /* キャスト情報 */
        .cast-info-sidebar {
            flex: 1;
            min-width: 300px;
        }
        
        .cast-name-age {
            text-align: center;
            margin-bottom: 15px;
        }
        
        .cast-name-age h3 {
            font-size: 1.8em;
            margin: 0;
            color: var(--color-text);
        }
        
        .cast-name-age h3 span {
            font-size: 0.6em;
            color: var(--color-text);
        }
        
        .cast-pr-title {
            font-size: 1.4em;
            font-weight: bold;
            margin-bottom: 10px;
            color: var(--color-text);
            text-align: center;
        }
        
        .cast-stats {
            margin-bottom: 10px;
            text-align: center;
        }
        
        .cast-stats p {
            margin: 0;
            font-size: 1.2em;
            font-weight: bold;
            color: var(--color-text);
        }
        
        .cast-stats .cup-size {
            font-size: 1.5em;
            color: var(--color-primary);
        }
        
        .cast-badges {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
            margin: 15px 0;
        }
        
        .badge {
            font-size: 12px;
            padding: 5px 15px;
            border-radius: 15px;
            font-weight: bold;
        }
        
        .badge.new {
            background: linear-gradient(135deg, #ff6b6b, #ff8e8e);
            color: #fff;
        }
        
        .badge.today {
            background: linear-gradient(135deg, var(--color-primary), var(--color-primary-light));
            color: var(--color-btn-text);
        }
        
        .badge.now {
            background: linear-gradient(135deg, #4caf50, #81c784);
            color: #fff;
        }
        
        .badge.closed {
            background: #9e9e9e;
            color: #fff;
        }
        
        .cast-pr-text {
            margin-top: 20px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 10px;
            line-height: 1.8;
        }
        
        /* 出勤スケジュール */
        .schedule-section {
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .schedule-title {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .schedule-title h2 {
            font-family: var(--font-title-en);
            font-size: 1.8em;
            color: var(--color-primary);
            margin: 0;
        }
        
        .schedule-title h3 {
            font-family: var(--font-title-ja);
            font-size: 1em;
            color: var(--color-text);
            margin-top: 5px;
            font-weight: 400;
        }
        
        .schedule-grid {
            display: flex;
            overflow-x: auto;
            gap: 10px;
            padding: 10px 0;
            -webkit-overflow-scrolling: touch;
        }
        
        .schedule-item {
            flex-shrink: 0;
            width: 80px;
            text-align: center;
            background: rgba(255, 255, 255, 0.7);
            border-radius: 10px;
            padding: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .schedule-item .day {
            font-weight: bold;
            color: var(--color-text);
            font-size: 0.9em;
            margin-bottom: 5px;
        }
        
        .schedule-item .time {
            color: var(--color-primary);
            font-size: 0.85em;
        }
        
        /* フッター */
        .fixed-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            height: 62px;
            background-color: rgba(255, 255, 255, 0.3);
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(7px);
            -webkit-backdrop-filter: blur(7px);
        }
        
        .fixed-footer-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 100%;
            padding: 0 10px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .fixed-footer-info {
            font-size: 11px;
            color: var(--color-text);
        }
        
        .fixed-footer-info .hours {
            font-weight: bold;
        }
        
        .phone-button {
            background: linear-gradient(135deg, var(--color-primary), var(--color-primary-light));
            color: var(--color-btn-text);
            border: none;
            border-radius: 25px;
            padding: 10px 25px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .phone-button i {
            font-size: 18px;
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
                padding: 15px;
                gap: 15px;
            }
            
            .swiper-container {
                max-width: 100%;
            }
            
            .cast-info-sidebar {
                min-width: 100%;
            }
            
            .cast-name-age h3 {
                font-size: 1.5em;
            }
            
            .cast-pr-title {
                font-size: 1.2em;
            }
            
            .cast-stats p {
                font-size: 1em;
            }
            
            .title-section h1 {
                font-size: 1.6em;
            }
        }
    </style>
</head>
<body>
    <!-- ヘッダー -->
    <header class="site-header">
        <a href="/app/front/top.php" class="hamburger-button">
            <i class="fas fa-bars"></i>
            <span>MENU</span>
        </a>
        
        <a href="/app/front/top.php" class="site-logo">
            <?php if ($logoSmallUrl): ?>
                <img src="<?php echo h($logoSmallUrl); ?>" alt="<?php echo h($shopName); ?>">
            <?php else: ?>
                <span style="font-family: var(--font-title-en); font-size: 20px; color: var(--color-primary);"><?php echo h($shopName); ?></span>
            <?php endif; ?>
        </a>
        
        <div class="header-right">
            <div class="header-date">
                <?php echo date('n/j'); ?>(<?php echo ['日', '月', '火', '水', '木', '金', '土'][date('w')]; ?>)
            </div>
        </div>
    </header>
    
    <main class="main-content">
        <!-- パンくず -->
        <nav class="breadcrumb">
            <a href="/app/front/index.php">ホーム</a><span>»</span>
            <a href="/app/front/top.php">トップ</a><span>»</span>
            <a href="/app/front/cast/list.php">キャスト一覧</a><span>»</span>
            <?php echo h($cast['name']); ?>
        </nav>
        
        <!-- タイトルセクション -->
        <section class="title-section">
            <h1>CAST PROFILE</h1>
            <h2>キャストプロフィール</h2>
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
                                    <img src="<?php echo h($cast[$imgKey]); ?>" 
                                         alt="<?php echo h($shopName . ' ' . $cast['name'] . ' 写真' . $i); ?>"
                                         loading="lazy">
                                </div>
                            <?php endif; ?>
                        <?php endfor; ?>
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
                <div class="cast-pr-title">
                    <?php echo h($cast['pr_title']); ?>
                </div>
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
            <div class="schedule-title">
                <h2>SCHEDULE</h2>
                <h3>出勤スケジュール</h3>
            </div>
            <div class="schedule-grid">
                <?php foreach ($schedule as $item): ?>
                <div class="schedule-item">
                    <div class="day"><?php echo h($item['day']); ?></div>
                    <div class="time"><?php echo h($item['time']); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
        
        <!-- 一覧に戻るボタン -->
        <div style="text-align: center; padding: 20px;">
            <a href="/app/front/cast/list.php" style="
                display: inline-block;
                background: linear-gradient(135deg, var(--color-primary), var(--color-primary-light));
                color: var(--color-btn-text);
                text-decoration: none;
                padding: 12px 30px;
                border-radius: 25px;
                font-weight: bold;
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
                transition: transform 0.3s, box-shadow 0.3s;
            ">
                <i class="fas fa-arrow-left"></i> キャスト一覧に戻る
            </a>
        </div>
        
        <!-- セクション下の影 -->
        <div class="section-shadow"></div>
    </main>
    
    <!-- 固定フッター -->
    <footer class="fixed-footer">
        <div class="fixed-footer-container">
            <div class="fixed-footer-info">
                <?php if ($businessHours): ?>
                <div class="hours">受付 <?php echo h($businessHours); ?></div>
                <?php endif; ?>
                <?php if ($businessHoursNote): ?>
                <div><?php echo h($businessHoursNote); ?></div>
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
