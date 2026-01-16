<?php
/**
 * pullcass - キャスト詳細ページ
 * 参考: https://club-houman.com/cast/detail.php
 */

session_start();

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/theme_helper.php';

// テナント情報を取得（リクエストのサブドメインを優先）
$tenantFromRequest = getTenantFromRequest();
$tenantFromSession = getCurrentTenant();

// リクエストからテナントが判別できた場合はそれを使用
if ($tenantFromRequest) {
    $tenant = $tenantFromRequest;
    // セッションと異なる場合は更新
    if (!$tenantFromSession || $tenantFromSession['id'] !== $tenant['id']) {
        setCurrentTenant($tenant);
    }
} elseif ($tenantFromSession) {
    // リクエストから判別できない場合はセッションを使用
    $tenant = $tenantFromSession;
} else {
    // どちらも無い場合はプラットフォームトップへ
    header('Location: https://pullcass.com/');
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

// 今日の日付
$today = date('n/j');
$dayOfWeek = ['日', '月', '火', '水', '木', '金', '土'][date('w')];

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
// 今日から7日分の日付を生成し、各日の出勤時間を取得
$schedule = [];
$dayOfWeekNames = ['日', '月', '火', '水', '木', '金', '土'];
for ($i = 0; $i < 7; $i++) {
    $date = new DateTime();
    $date->modify("+{$i} days");
    $dayNum = $i + 1;
    $dayKey = "day{$dayNum}";
    
    // 時間データを取得（なければ "---"）
    $time = (isset($cast[$dayKey]) && !empty($cast[$dayKey])) ? $cast[$dayKey] : '---';
    
    $schedule[] = [
        'date' => $date->format('n/j') . '(' . $dayOfWeekNames[$date->format('w')] . ')',
        'time' => $time
    ];
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
    <meta name="cast-id" content="<?php echo h($castId); ?>">
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
            --font-title1: '<?php echo h($themeFonts['title1_en'] ?? 'Kranky'); ?>', '<?php echo h($themeFonts['title1_ja'] ?? 'Kaisei Decol'); ?>', sans-serif;
            --font-body: '<?php echo h($themeFonts['body_ja'] ?? 'M PLUS 1p'); ?>', sans-serif;
        }
        
        body {
            font-family: var(--font-body);
            line-height: 1.6;
            color: var(--color-text);
            <?php if (isset($themeColors['bg_type']) && $themeColors['bg_type'] === 'gradient'): ?>
            background: linear-gradient(90deg, <?php echo h($themeColors['bg_gradient_start'] ?? '#ffffff'); ?> 0%, <?php echo h($themeColors['bg_gradient_end'] ?? '#ffd2fe'); ?> 100%);
            <?php else: ?>
            background: <?php echo h($themeColors['bg']); ?>;
            <?php endif; ?>
            min-height: 100vh;
            padding-top: 70px;
            padding-bottom: 56px;
        }
        
        /* ==================== ヘッダー・フッター共通スタイル ==================== */
        <?php include __DIR__ . '/../includes/header_styles.php'; ?>
        
        /* タイトルセクション - 参考サイトに合わせて調整 */
        .title-section {
            text-align: left;
            padding: 14px 16px 0;
            max-width: 1100px;
            margin: 0 auto;
        }
        
        .title-section.cast-detail-title {
            padding: 14px 0 0;
        }
        
        .title-section.cast-detail-title .dot-line {
            margin-top: 0;
        }
        
        .title-section h1 {
            font-family: var(--font-title-en), var(--font-title-ja), sans-serif;
            font-size: 40px;
            color: var(--color-primary);
            margin: 0;
            line-height: 1;
            font-style: normal;
            letter-spacing: -0.8px;
        }
        
        .title-section h2 {
            font-family: var(--font-title-en), var(--font-title-ja), sans-serif;
            font-size: 20px;
            color: var(--color-text);
            margin-top: 0;
            font-weight: 400;
            letter-spacing: -0.8px;
        }
        
        /* ドットライン - 参考サイトに合わせて調整 */
        .dot-line {
            width: 100%;
            height: 10px;
            margin: 0 0 0 0;
            background: repeating-radial-gradient(
                circle,
                var(--color-primary) 0px,
                var(--color-primary) 2px,
                transparent 2px,
                transparent 12px
            );
            background-size: 12px 10px;
            background-repeat: repeat-x;
        }
        
        .dot-line-container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 16px;
        }
        
        /* キャストコンテンツ */
        .cast-content {
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
            padding: 20px;
            max-width: 1100px;
            margin: 0 auto;
        }
        
        /* スライダー - 参考サイトに合わせて調整 */
        .swiper-container {
            flex: 1;
            min-width: 300px;
            max-width: 500px;
            position: relative;
        }
        
        .cast-swiper {
            max-width: 100%;
            margin: 0;
            padding: 0;
            position: relative;
            overflow: hidden;
        }
        
        .cast-swiper .swiper-slide {
            width: auto;
        }
        
        .slide-padding {
            padding: 2px;
            box-sizing: border-box;
        }
        
        .slide-inner {
            overflow: hidden;
            border-radius: 15px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        
        .slide-inner img {
            width: 100%;
            height: auto;
            object-fit: cover;
            display: block;
        }
        
        .swiper-button-prev,
        .swiper-button-next {
            color: var(--color-primary);
            width: 40px;
            height: 40px;
            background: transparent;
        }
        
        .swiper-button-prev::after,
        .swiper-button-next::after {
            font-size: 30px;
            font-weight: bold;
        }
        
        .swiper-pagination {
            position: relative;
            bottom: 8px;
            margin-top: 10px;
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
            font-weight: 500;
        }
        
        .cast-name-age h3 span {
            font-size: 0.8em;
            color: var(--color-text);
        }
        
        .cast-pr-title {
            font-size: 1.6em;
            font-weight: 500;
            margin-bottom: 5px;
            color: var(--color-text);
            font-family: var(--font-body);
            text-align: center;
        }
        
        .cast-stats-detail {
            margin-bottom: 5px;
            line-height: 1.2;
            text-align: center;
        }
        
        .cast-stats-detail p {
            margin: 0;
            font-size: 1.3em;
            font-weight: 500;
            color: var(--color-text);
            font-family: var(--font-body);
            text-align: center;
        }
        
        .cast-stats-detail .cup-size {
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
            color: var(--color-primary);
            border-color: var(--color-primary);
        }
        
        .badge.today {
            color: var(--color-primary);
            border-color: var(--color-primary);
        }
        
        .badge.now {
            color: var(--color-primary);
            border-color: var(--color-primary);
        }
        
        .badge.closed {
            color: var(--color-primary);
            border-color: var(--color-primary);
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
            padding: 20px 15px;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
            box-sizing: border-box;
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
            font-weight: 400;
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
            font-weight: 400;
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
            padding: 20px 15px;
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
            box-sizing: border-box;
        }
        
        .reserve-button {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 20px;
            background-color: rgba(255, 255, 255, 0.5);
            border-radius: 20px;
            font-weight: 500;
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
        
        /* レスポンシブ対応（ページ固有） */
        @media (max-width: 768px) {
            .title-section h1 {
                font-size: 28px;
            }
            
            .title-section h2 {
                font-size: 16px;
            }
            
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
                font-weight: 500;
            }
            
            .cast-pr-title {
                font-size: 1.2em;
                margin: 0 0 15px 0;
            }
            
            .cast-stats-detail {
                margin: 0 0 5px 0;
            }
            
            .cast-stats-detail p {
                font-size: 1em;
                margin: 0;
                line-height: 1;
                font-weight: 500;
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
        
        /* 3カラムレイアウト */
        .three-sections {
            display: flex;
            flex-direction: row;
            gap: 10px;
            margin: 0 auto;
            width: 100%;
            max-width: 1200px;
            padding: 0 15px;
            box-sizing: border-box;
        }
        
        .three-sections .review-section,
        .three-sections .history-section {
            width: 30%;
            min-width: 0;
            position: relative;
        }
        
        .three-sections .photo-section {
            width: 40%;
            min-width: 0;
            position: relative;
        }
        
        .three-sections .title-section.cast-detail-title h1 {
            font-family: var(--font-title1);
            font-size: 20px;
        }
        
        .three-sections .title-section.cast-detail-title h2 {
            font-family: var(--font-body);
            font-size: 14px;
        }
        
        .coming-soon-message {
            text-align: center;
            padding: 50px 20px;
            color: var(--color-text);
            font-size: 14px;
            font-family: var(--font-body);
        }
        
        /* 閲覧履歴スタイル */
        .history-wrapper {
            position: relative;
        }
        .history-content {
            height: 300px;
            overflow: hidden;
        }
        .history-cards {
            height: 100%;
            overflow-y: auto;
        }
        .history-cards .history-card {
            display: flex !important;
            align-items: stretch !important;
            width: 100% !important;
            background: rgba(255,255,255,0.6) !important;
            border-radius: 12px !important;
            margin-bottom: 5px !important;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07) !important;
            text-decoration: none !important;
            color: inherit !important;
            min-height: 70px !important;
            padding: 0 !important;
            box-sizing: border-box !important;
            overflow: hidden !important;
        }
        .history-cards .card-image {
            width: 60px !important;
            min-width: 60px !important;
            height: 100% !important;
            border-radius: 12px 0 0 12px !important;
            overflow: hidden !important;
            margin: 0 !important;
            flex-shrink: 0 !important;
        }
        .history-cards .card-image img {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
        }
        .history-cards .card-info {
            flex: 1 !important;
            display: flex !important;
            flex-direction: column !important;
            justify-content: center !important;
            height: 100% !important;
            padding: 8px 10px !important;
            font-family: var(--font-body);
        }
        .history-cards .card-name {
            display: flex;
            align-items: center;
            gap: 5px;
            font-weight: bold;
            font-size: 13px;
            margin-bottom: 2px;
            text-align: left;
        }
        .history-cards .card-pr {
            font-size: 12px;
            color: var(--color-text);
            text-align: left;
            margin: 0;
            padding: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .history-empty {
            text-align: center;
            color: var(--color-text);
            padding: 20px;
            font-size: 13px;
            font-family: var(--font-body);
        }
        .scroll-gradient-bottom {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 40px;
            background: linear-gradient(to top, rgba(255, 255, 255, 0.8), transparent);
            pointer-events: none;
            z-index: 1;
        }
        
        /* レスポンシブ：3カラム → 縦並び */
        @media (max-width: 768px) {
            .three-sections {
                flex-direction: column !important;
                gap: 0 !important;
                padding: 0 !important;
            }
            .three-sections .review-section,
            .three-sections .photo-section,
            .three-sections .history-section {
                width: 100% !important;
                margin: 0 !important;
                padding: 15px !important;
            }
            .history-content {
                max-height: 250px;
            }
        }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <main>
        <!-- パンくず -->
        <nav class="breadcrumb">
            <a href="/app/front/index.php">ホーム</a><span>»</span>
            <a href="/app/front/top.php">トップ</a><span>»</span>
            <a href="/app/front/cast/list.php">キャスト一覧</a><span>»</span><?php echo h($cast['name']); ?> |
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
                                    <div class="slide-padding">
                                        <div class="slide-inner">
                                            <img src="<?php echo h($cast[$imgKey]); ?>" 
                                                 alt="<?php echo h($shopName . ' ' . $cast['name'] . ' 写真' . $i); ?>"
                                                 loading="lazy">
                                        </div>
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
                
                <div class="cast-stats-detail">
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
        <section class="schedule-section">
            <div class="title-section cast-detail-title" style="padding-left: 0;">
                <h1>SCHEDULE</h1>
                <h2>出勤表</h2>
                <div class="dot-line"></div>
            </div>
            
            <!-- PC表示用 -->
            <div class="pc-schedule">
                <table class="schedule-table">
                    <tr>
                        <?php foreach ($schedule as $item): ?>
                            <th><?php echo h($item['date']); ?></th>
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
                        <div class="day"><?php echo h($item['date']); ?></div>
                        <div class="time"><?php echo h($item['time']); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        
        <!-- 予約セクション -->
        <section class="reserve-section">
            <div class="title-section cast-detail-title" style="padding-left: 0;">
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
        
        <!-- 3カラムセクション -->
        <div class="three-sections">
            <!-- 口コミセクション（準備中） -->
            <section class="review-section">
                <div class="title-section cast-detail-title">
                    <h1>REVIEW</h1>
                    <h2>口コミ</h2>
                    <div class="dot-line"></div>
                </div>
                <div class="review-wrapper">
                    <div class="review-content">
                        <div class="coming-soon-message">
                            現在口コミはありません。
                        </div>
                    </div>
                </div>
            </section>
            
            <!-- 写メ日記セクション（準備中） -->
            <section class="photo-section">
                <div class="title-section cast-detail-title">
                    <h1>DIARY</h1>
                    <h2>動画・写メ日記</h2>
                    <div class="dot-line"></div>
                </div>
                <div class="photo-wrapper">
                    <div class="photo-content">
                        <div class="coming-soon-message">
                            日記の投稿はありません。
                        </div>
                    </div>
                </div>
            </section>
            
            <!-- 閲覧履歴セクション -->
            <section class="history-section">
                <div class="title-section cast-detail-title">
                    <h1>HISTORY</h1>
                    <h2>閲覧履歴</h2>
                    <div class="dot-line"></div>
                </div>
                <div class="history-wrapper">
                    <div class="history-content">
                        <div class="history-cards">
                            <!-- 履歴カードはJavaScriptで動的に生成されます -->
                        </div>
                    </div>
                    <div class="scroll-gradient-bottom"></div>
                </div>
            </section>
        </div>
    </main>
    
    <?php include __DIR__ . '/../includes/footer_nav.php'; ?>
    
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    
    <!-- Swiper.js -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>
        const castSwiper = new Swiper('.cast-swiper', {
            loop: true,
            slidesPerView: 1,
            spaceBetween: 0,
            speed: 300,
            autoplay: {
                delay: 4000,
                disableOnInteraction: false,
            },
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
    
    <!-- 閲覧履歴スクリプト -->
    <script src="/assets/js/history.js"></script>
</body>
</html>
