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

// 今日の日付
$today = date('n/j');
$dayOfWeek = ['日', '月', '火', '水', '木', '金', '土'][date('w')];

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
            background: linear-gradient(90deg, <?php echo h($themeColors['bg_gradient_start'] ?? '#ffffff'); ?> 0%, <?php echo h($themeColors['bg_gradient_end'] ?? '#ffd2fe'); ?> 100%);
            <?php else: ?>
            background: <?php echo h($themeColors['bg']); ?>;
            <?php endif; ?>
            min-height: 100vh;
            padding-top: 70px;
            padding-bottom: 56px;
        }
        
        /* ==================== ヘッダー（トップページと同じ） ==================== */
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
            text-decoration: none;
        }
        
        .hamburger-button:hover {
            transform: scale(1.05);
        }
        
        .hamburger-lines {
            display: flex;
            flex-direction: column;
            gap: 4px;
            margin-bottom: 2px;
        }
        
        .hamburger-line {
            width: 18px;
            height: 2px;
            background: var(--color-btn-text);
            border-radius: 2px;
        }
        
        .hamburger-text {
            font-size: 10px;
            font-weight: 700;
            color: var(--color-btn-text);
        }
        
        .header-date {
            font-size: 14px;
            font-weight: 600;
            color: var(--color-text);
        }
        
        /* パンくず */
        .breadcrumb {
            padding: 10px 16px;
            font-size: 12px;
            color: var(--color-primary);
            max-width: 1100px;
            margin: 0 auto;
        }
        
        .breadcrumb a {
            color: var(--color-primary);
            text-decoration: none;
        }
        
        .breadcrumb span {
            margin: 0 3px;
            color: var(--color-text);
        }
        
        /* タイトルセクション - 参考サイトに合わせて調整 */
        .title-section {
            text-align: left;
            padding: 14px 16px 0;
            max-width: 1100px;
            margin: 0 auto;
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
        
        /* キャストグリッド - 参考サイトに合わせて調整 */
        .cast-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            padding: 20px;
            max-width: 1100px;
            margin: 0 auto 20px;
            text-align: center;
        }
        
        @media (min-width: 768px) {
            .cast-grid {
                grid-template-columns: repeat(4, 1fr);
                gap: 15px;
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
        
        /* ==================== フッター（トップページと同じ） ==================== */
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
        }
        
        .phone-button:hover {
            transform: scale(1.03);
        }
        
        .phone-button i {
            font-size: 1rem;
        }
        
        /* スマホ対応 */
        @media (max-width: 768px) {
            .header-date {
                display: none;
            }
            
            .hamburger-button {
                width: 48px;
                height: 48px;
            }
            
            .title-section h1 {
                font-size: 28px;
            }
            
            .title-section h2 {
                font-size: 16px;
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
        }
        
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
        }
    </style>
</head>
<body>
    <!-- ヘッダー（トップページと同じ） -->
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
                        foreach ($titleLines as $i => $line): 
                        ?>
                            <span class="<?php echo $i === 0 ? 'logo-main-title' : 'logo-sub-title'; ?>"><?php echo h(trim($line)); ?></span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="logo-main-title"><?php echo h($shopName); ?></span>
                    <?php endif; ?>
                </div>
            </a>
            
            <span class="header-date"><?php echo $today; ?>(<?php echo $dayOfWeek; ?>)</span>
            
            <a href="/app/front/top.php" class="hamburger-button">
                <div class="hamburger-lines">
                    <span class="hamburger-line"></span>
                    <span class="hamburger-line"></span>
                    <span class="hamburger-line"></span>
                </div>
                <span class="hamburger-text">MENU</span>
            </a>
        </div>
    </header>
    
    <main>
        <!-- パンくず -->
        <nav class="breadcrumb">
            <a href="/app/front/index.php">ホーム</a><span>»</span>
            <a href="/app/front/top.php">トップ</a><span>»</span>キャスト一覧 |
        </nav>
        
        <!-- タイトルセクション -->
        <section class="title-section">
            <h1>ALL CAST</h1>
            <h2>キャスト一覧</h2>
        </section>
        
        <!-- ドットライン -->
        <div style="max-width: 1100px; margin: 0 auto; padding: 0 16px;">
            <div class="dot-line"></div>
        </div>
        
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
    </main>
    
    <!-- フッター（トップページと同じ） -->
    <footer class="fixed-footer">
        <div class="fixed-footer-container">
            <div class="fixed-footer-info">
                <p class="open-hours"><?php echo $businessHours ? h($businessHours) : 'OPEN 準備中'; ?></p>
                <p><?php echo $businessHoursNote ? h($businessHoursNote) : '電話予約受付中！'; ?></p>
            </div>
            <?php if ($phoneNumber): ?>
            <a href="tel:<?php echo h(preg_replace('/[^0-9]/', '', $phoneNumber)); ?>" class="phone-button">
                <i class="fas fa-phone"></i>
                <span><?php echo h($phoneNumber); ?></span>
            </a>
            <?php else: ?>
            <span class="phone-button" style="opacity: 0.6; cursor: default;">
                <i class="fas fa-phone"></i>
                <span>電話番号準備中</span>
            </span>
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
