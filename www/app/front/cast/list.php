<?php
/**
 * pullcass - キャスト一覧ページ
 * 参考: https://club-houman.com/cast/list
 */

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

// ページタイトル
$pageTitle = 'キャスト一覧｜' . $shopName;
$pageDescription = $shopName . 'の在籍キャスト一覧です。';

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

// データソースに基づいてキャストデータを取得
$casts = [];
try {
    $tableName = "tenant_cast_data_{$activeSource}";
    $stmt = $pdo->prepare("
        SELECT id, name, age, height, size, cup, pr_title, img1, today, `now`, closed, `new`
        FROM {$tableName}
        WHERE tenant_id = ? AND checked = 1
        ORDER BY sort_order ASC, id DESC
    ");
    $stmt->execute([$tenantId]);
    $casts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Cast list error: " . $e->getMessage());
}
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
            padding: 20px 15px;
        }
        
        .title-section h1 {
            font-family: var(--font-title-en);
            font-size: 2.5em;
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
            margin: 15px auto;
            height: 1px;
            background: repeating-linear-gradient(
                to right,
                var(--color-primary) 0,
                var(--color-primary) 4px,
                transparent 4px,
                transparent 8px
            );
        }
        
        /* キャストグリッド */
        .cast-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            padding: 0 10px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        @media (min-width: 768px) {
            .cast-grid {
                grid-template-columns: repeat(4, 1fr);
                gap: 15px;
                padding: 0 20px;
            }
        }
        
        @media (min-width: 1024px) {
            .cast-grid {
                grid-template-columns: repeat(5, 1fr);
            }
        }
        
        /* キャストカード */
        .cast-card {
            background: rgba(255, 255, 255, 0.6);
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: transform 0.3s ease;
        }
        
        .cast-card:hover {
            transform: translateY(-3px);
        }
        
        .cast-card a {
            text-decoration: none;
            color: inherit;
            display: block;
        }
        
        .cast-image {
            position: relative;
            width: 100%;
            padding-top: 133%;
            overflow: hidden;
        }
        
        .cast-image img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .cast-info {
            padding: 8px 5px;
            text-align: center;
        }
        
        .cast-info h3 {
            font-size: 1em;
            margin: 0 0 3px 0;
            line-height: 1.2;
            color: var(--color-text);
        }
        
        .cast-info .cast-stats {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            font-size: 0.85em;
            margin-bottom: 3px;
        }
        
        .cast-info .cast-cup {
            font-weight: bold;
            color: var(--color-primary);
        }
        
        .cast-info .cast-pr {
            font-size: 0.75em;
            color: var(--color-text);
            line-height: 1.3;
            min-height: 2.6em;
            max-height: 2.6em;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            margin: 0;
        }
        
        .cast-badges {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 3px;
            margin-top: 5px;
        }
        
        .badge {
            font-size: 9px;
            padding: 2px 6px;
            border-radius: 10px;
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
        
        /* スマホ対応 */
        @media (max-width: 480px) {
            .cast-card {
                border-radius: 5px;
            }
            
            .cast-info {
                padding: 5px 3px;
            }
            
            .cast-info h3 {
                font-size: 0.9em;
            }
            
            .cast-info .cast-stats {
                font-size: 0.8em;
            }
            
            .cast-info .cast-pr {
                font-size: 0.7em;
            }
            
            .badge {
                font-size: 8px;
                padding: 1px 4px;
            }
        }
        
        /* セクション下の影 */
        .section-shadow {
            width: 100%;
            height: 15px;
            background-color: transparent;
            box-shadow: 0 -8px 12px -4px rgba(0, 0, 0, 0.2);
            position: relative;
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
            キャスト一覧
        </nav>
        
        <!-- タイトルセクション -->
        <section class="title-section">
            <h1>ALL CAST</h1>
            <h2>キャスト一覧</h2>
            <div class="dot-line"></div>
        </section>
        
        <!-- キャストグリッド -->
        <div class="cast-grid">
            <?php foreach ($casts as $cast): ?>
            <div class="cast-card">
                <a href="/app/front/cast/detail.php?id=<?php echo h($cast['id']); ?>">
                    <div class="cast-image">
                        <?php if ($cast['img1']): ?>
                            <img src="<?php echo h($cast['img1']); ?>" 
                                 alt="<?php echo h($shopName . ' ' . $cast['name']); ?>"
                                 loading="lazy">
                        <?php else: ?>
                            <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-user" style="font-size: 48px; color: #ccc;"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="cast-info">
                        <h3><?php echo h($cast['name']); ?></h3>
                        <div class="cast-stats">
                            <span><?php echo h($cast['age']); ?>歳</span>
                            <span class="cast-cup"><?php echo h($cast['cup']); ?>カップ</span>
                        </div>
                        <p class="cast-pr"><?php echo h($cast['pr_title']); ?></p>
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
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (empty($casts)): ?>
        <div style="text-align: center; padding: 50px 20px; color: var(--color-text);">
            <i class="fas fa-users" style="font-size: 48px; color: #ccc; margin-bottom: 15px;"></i>
            <p>現在、キャストデータがありません。</p>
        </div>
        <?php endif; ?>
        
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
    
    <?php
    // プレビューバーを表示
    if ($currentTheme['is_preview']) {
        echo generatePreviewBar($tenant['code']);
    }
    ?>
</body>
</html>
