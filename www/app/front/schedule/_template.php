<?php
/**
 * pullcass - スケジュールページテンプレート
 * 参考: https://club-houman.com/schedule/day1
 * 
 * このファイルは各day*.phpからincludeされます
 * 必要な変数: $dayNumber (1-7)
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

// 今日の日付と曜日名
$dayOfWeekNames = ['日', '月', '火', '水', '木', '金', '土'];

// 7日分の日付を生成
$scheduleLinks = [];
for ($i = 0; $i < 7; $i++) {
    $date = new DateTime();
    $date->modify("+{$i} days");
    $dayLabel = $dayOfWeekNames[$date->format('w')];
    $dateStr = $date->format('n/j') . '(' . $dayLabel . ')';
    
    $scheduleLinks[] = [
        'dayNum' => $i + 1,
        'url' => '/app/front/schedule/day' . ($i + 1) . '.php',
        'date' => $dateStr,
        'fullDate' => $date->format('Y-m-d')
    ];
}

// 現在表示中の日付情報
$currentDay = $scheduleLinks[$dayNumber - 1];
$currentDateLabel = $currentDay['date'];

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

// 該当日に出勤するキャストを取得
$casts = [];
try {
    $tableName = "tenant_cast_data_{$activeSource}";
    $dayColumn = "day{$dayNumber}";
    $stmt = $pdo->prepare("
        SELECT *
        FROM {$tableName}
        WHERE tenant_id = ? 
          AND checked = 1
          AND {$dayColumn} IS NOT NULL
          AND {$dayColumn} != ''
        ORDER BY sort_order ASC
    ");
    $stmt->execute([$tenantId]);
    $casts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Schedule error: " . $e->getMessage());
}

// ページタイトル
$pageTitle = $currentDateLabel . 'の出勤｜' . $shopName;
$pageDescription = $shopName . 'の' . $currentDateLabel . 'の出勤スケジュールです。';
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
        
        /* タイトルセクション */
        .title-section {
            text-align: left;
            padding: 14px 16px 30px;
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
        
        .title-section .dot-line {
            height: 10px;
            margin-top: 10px;
            background: repeating-radial-gradient(circle, var(--color-primary) 0px, var(--color-primary) 2px, transparent 2px, transparent 12px);
            background-size: 12px 10px;
            background-repeat: repeat-x;
        }
        
        /* 日付タブ */
        .date-links {
            overflow-x: auto;
            white-space: nowrap;
            padding: 5px 10px;
            margin: 2px 10px;
            max-width: 1100px;
            margin-left: auto;
            margin-right: auto;
            text-align: center;
        }
        
        .date-links-inner {
            display: inline-flex;
            gap: 10px;
            min-width: min-content;
            margin: 0 auto;
        }
        
        .date-link {
            display: inline-block;
            padding: 8px 15px;
            background: var(--color-primary);
            font-weight: 400;
            color: var(--color-btn-text);
            text-decoration: none;
            border-radius: 20px;
            font-weight: bold;
            min-width: 120px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            opacity: 0.7;
            font-size: 16px;
        }
        
        .date-link:hover {
            opacity: 0.9;
        }
        
        .date-link.active {
            opacity: 1;
        }
        
        /* キャストグリッド */
        .cast-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 20px;
            padding: 0 16px 40px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .cast-card {
            text-decoration: none;
            color: var(--color-text);
            display: block;
            background: rgba(255, 255, 255, 0.6);
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .cast-card .image-container {
            position: relative;
            overflow: hidden;
            aspect-ratio: 3 / 4;
        }
        
        .cast-card .image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        
        .cast-card .cast-info {
            text-align: center;
            padding: 10px 5px;
        }
        
        .cast-card .cast-name {
            font-size: 18px;
            font-weight: 500;
            margin: 0;
        }
        
        .cast-card .cast-stats {
            font-size: 14px;
            color: var(--color-text);
            margin: 4px 0;
        }
        
        .cast-card .cast-stats .cup {
            font-weight: 400;
        }
        
        .cast-card .cast-pr {
            font-size: 13px;
            color: var(--color-text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin: 4px 0;
        }
        
        .cast-card .cast-time {
            font-size: 14px;
            color: var(--color-primary);
            font-weight: 500;
            margin: 5px 0 0;
        }
        
        .cast-card .cast-status {
            display: inline-block;
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 10px;
            margin-top: 5px;
        }
        
        .cast-card .cast-status.now {
            color: var(--color-primary);
            border: 1px solid var(--color-primary);
            background: transparent;
        }
        
        .cast-card .cast-status.closed {
            color: var(--color-primary);
            border: 1px solid var(--color-primary);
            background: transparent;
        }
        
        /* 出勤なしメッセージ */
        .no-schedule {
            text-align: center;
            padding: 60px 20px;
            max-width: 1100px;
            margin: 0 auto;
            color: var(--color-text);
        }
        
        .no-schedule i {
            font-size: 48px;
            color: var(--color-primary);
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .no-schedule p {
            font-size: 16px;
        }
        
        /* レスポンシブ対応 */
        @media (max-width: 1024px) {
            .cast-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }
        
        @media (max-width: 900px) {
            .cast-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .title-section h1 {
                font-size: 28px;
            }
            
            .title-section h2 {
                font-size: 16px;
            }
            
            .date-links {
                padding: 5px 10px;
                text-align: left;
            }
            
            .date-links-inner {
                gap: 10px;
            }
            
            /* スマホでも参考サイトと同じサイズを維持 */
            .date-link {
                padding: 8px 15px;
                font-size: 16px;
                min-width: 120px;
            }
            
            .cast-grid {
                grid-template-columns: repeat(3, 1fr);
                gap: 10px;
            }
            
            .cast-card .cast-name {
                font-size: 16px;
            }
            
            .cast-card .cast-stats {
                font-size: 13px;
            }
            
            .cast-card .cast-pr {
                font-size: 12px;
            }
            
            .cast-card .cast-time {
                font-size: 13px;
            }
        }
        
        /* スマホでも参考サイトと同じ3列を維持 */
    </style>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <main>
        <!-- パンくず -->
        <nav class="breadcrumb">
            <a href="/app/front/index.php">ホーム</a><span>»</span>
            <a href="/app/front/top.php">トップ</a><span>»</span><?php echo h($currentDateLabel); ?>の出勤 |
        </nav>
        
        <!-- タイトルセクション -->
        <section class="title-section">
            <h1>SCHEDULE</h1>
            <h2><?php echo h($currentDateLabel); ?>の出勤</h2>
            <div class="dot-line"></div>
        </section>
        
        <!-- 日付タブ -->
        <div class="date-links">
            <div class="date-links-inner">
                <?php foreach ($scheduleLinks as $link): ?>
                    <a href="<?php echo h($link['url']); ?>" 
                       class="date-link <?php echo ($link['dayNum'] === $dayNumber) ? 'active' : ''; ?>"><?php echo h($link['date']); ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- キャストグリッド -->
        <?php if (!empty($casts)): ?>
        <div class="cast-grid">
            <?php foreach ($casts as $cast): ?>
                <?php 
                    $dayColumn = "day{$dayNumber}";
                    $scheduleTime = $cast[$dayColumn] ?? '';
                ?>
                <a href="/app/front/cast/detail.php?id=<?php echo h($cast['id']); ?>" class="cast-card">
                    <div class="image-container">
                        <?php if (!empty($cast['img1'])): ?>
                            <img src="<?php echo h($cast['img1']); ?>" 
                                 alt="<?php echo h($shopName . ' ' . $cast['name']); ?>"
                                 loading="lazy">
                        <?php else: ?>
                            <img src="/assets/img/no-image.png" 
                                 alt="<?php echo h($shopName . ' ' . $cast['name']); ?>">
                        <?php endif; ?>
                    </div>
                    <div class="cast-info">
                        <h2 class="cast-name"><?php echo h($cast['name']); ?></h2>
                        <p class="cast-stats">
                            <?php echo h($cast['age']); ?>歳 
                            <span class="cup"><?php echo h($cast['cup']); ?>カップ</span>
                        </p>
                        <?php if ($cast['pr_title']): ?>
                            <p class="cast-pr"><?php echo h($cast['pr_title']); ?></p>
                        <?php endif; ?>
                        <?php if ($scheduleTime): ?>
                            <p class="cast-time"><?php echo h($scheduleTime); ?></p>
                        <?php endif; ?>
                        <?php if ($cast['now']): ?>
                            <span class="cast-status now">案内中</span>
                        <?php elseif ($cast['closed']): ?>
                            <span class="cast-status closed">受付終了</span>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="no-schedule">
            <i class="fas fa-calendar-times"></i>
            <p><?php echo h($currentDateLabel); ?>の出勤予定はまだ登録されていません。</p>
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
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // スマホサイズでのみ実行（PC版の表示を壊さないため）
        if (window.innerWidth <= 768) {
            // 現在の日付ボタン（アクティブなボタン）を特定
            const dateLinks = document.querySelectorAll('.date-link');
            let activeButton = null;
            
            dateLinks.forEach((link, index) => {
                // 現在のページ番号と一致するボタンを探す
                if (index + 1 === <?php echo $dayNumber; ?>) {
                    activeButton = link;
                }
            });
            
            if (activeButton) {
                // 少し遅延を入れて確実にレンダリング完了後に実行
                setTimeout(function() {
                    activeButton.scrollIntoView({
                        behavior: 'smooth',      // スムーズスクロール
                        block: 'nearest',       // 縦スクロールに影響しない
                        inline: 'start'         // 横スクロールで左端に表示
                    });
                }, 100);
            }
        }
    });
    </script>
</body>
</html>
