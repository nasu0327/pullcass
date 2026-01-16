<?php
/**
 * pullcass - キャスト一覧ページ
 * 参考: https://club-houman.com/cast/list
 */

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
        
        /* ==================== ヘッダー・フッター共通スタイル ==================== */
        <?php include __DIR__ . '/../includes/header_styles.php'; ?>
        
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
            font-weight: 500;
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
            font-weight: 400;
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
        
        /* スマホ対応（ページ固有） */
        @media (max-width: 768px) {
            .title-section h1 {
                font-size: 28px;
            }
            
            .title-section h2 {
                font-size: 16px;
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
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
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
    </main>
    
    <?php include __DIR__ . '/../includes/footer_nav.php'; ?>
    
    <?php include __DIR__ . '/../includes/footer.php'; ?>
    
    <?php
    // プレビューバーを表示
    if ($currentTheme['is_preview']) {
        echo generatePreviewBar($tenant['code']);
    }
    ?>
</body>
</html>
