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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
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
        }
        
        .menu-button .line {
            display: block;
            width: 20px;
            height: 2px;
            background: var(--color-btn-text);
        }
        
        .menu-button span {
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
            padding: 10px 20px 20px;
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
        
        /* キャストグリッド */
        .cast-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            padding: 0 10px;
            max-width: 1400px;
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
                gap: 20px;
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
            transform: translateY(-5px);
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
            padding: 5px 3px;
            text-align: center;
        }
        
        .cast-info h2 {
            font-size: 1.2em;
            margin: 0 0 2px 0;
            line-height: 1.2;
            color: var(--color-text);
            font-weight: bold;
        }
        
        .cast-stats {
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 0 2px 0;
        }
        
        .cast-stats .age {
            font-size: 0.9em;
            color: var(--color-text);
        }
        
        .cast-stats .cup {
            font-size: 1em;
            font-weight: bold;
            color: var(--color-text);
            margin-left: 5px;
        }
        
        .cast-pr {
            font-size: 0.8em;
            color: var(--color-text);
            line-height: 1.1;
            min-height: 2.2em;
            max-height: 2.2em;
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
            gap: 2px;
            margin-top: 3px;
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
        
        /* スマホ対応 */
        @media (max-width: 480px) {
            .cast-card {
                border-radius: 5px;
            }
            
            .cast-info {
                padding: 4px 2px;
            }
            
            .cast-info h2 {
                font-size: 1.1em;
                margin: 0 0 1px 0;
                line-height: 1.1;
            }
            
            .cast-stats {
                margin: 0 0 1px 0;
            }
            
            .cast-pr {
                margin: 0;
                height: 2.2em;
                line-height: 1.1;
            }
            
            .cast-badges {
                margin: 2px 0 0 0;
            }
            
            .badge {
                font-size: 10px;
                padding: 0 3px;
            }
            
            .title-section h1 {
                font-size: 1.8em;
            }
            
            .phone-button {
                padding: 8px 15px;
                font-size: 14px;
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
            キャスト一覧 |
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
                        <h2><?php echo h($cast['name']); ?></h2>
                        <div class="cast-stats">
                            <span class="age"><?php echo h($cast['age']); ?>歳</span>
                            <span class="cup"><?php echo h($cast['cup']); ?>カップ</span>
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
        <div class="section-shadow" style="margin-top: 20px;"></div>
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
    
    <?php
    // プレビューバーを表示
    if ($currentTheme['is_preview']) {
        echo generatePreviewBar($tenant['code']);
    }
    ?>
</body>
</html>
