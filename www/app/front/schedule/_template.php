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
        ORDER BY {$dayColumn} ASC
    ");
    $stmt->execute([$tenantId]);
    $casts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Schedule error: " . $e->getMessage());
}

// ページタイトル
$pageTitle = $currentDateLabel . 'の出勤｜' . $shopName;
$pageDescription = $shopName . 'の' . $currentDateLabel . 'の出勤スケジュールです。';

// ページ固有のCSS
$additionalCss = <<<CSS
/* スケジュールページ固有 */
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
    min-width: 120px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    opacity: 0.7;
    font-size: 16px;
}

.date-link:hover {
    opacity: 0.9;
    text-decoration: none;
}

.date-link.active {
    opacity: 1;
}

/* キャストグリッド */
.schedule-cast-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 20px;
    padding: 0 16px 40px;
    max-width: 1200px;
    margin: 0 auto;
}

.schedule-cast-grid .cast-card {
    display: block;
    background: rgba(255, 255, 255, 0.6);
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.07);
    overflow: hidden;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    text-decoration: none !important;
}

.schedule-cast-grid .cast-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.12);
    text-decoration: none !important;
}

.schedule-cast-grid .cast-card .cast-info {
    text-align: center;
    padding: 5px 3px;
}

.schedule-cast-grid .cast-card .cast-name {
    font-size: 1.2em;
    font-weight: 700;
    margin: 0 0 2px 0;
    line-height: 1.2;
    color: var(--color-text);
}

.schedule-cast-grid .cast-card .cast-stats {
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 0 2px 0;
    padding: 0;
    font-size: 0.9em;
    color: var(--color-text);
}

.schedule-cast-grid .cast-card .cast-stats > span {
    font-weight: 400 !important;
}

.schedule-cast-grid .cast-card .cast-stats .cup {
    font-size: 1.0em;
    font-weight: 400 !important;
    margin-left: 5px;
}

.schedule-cast-grid .cast-card .cast-pr {
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
    padding: 0;
}

.schedule-cast-grid .cast-card .cast-time {
    font-size: 14px;
    color: var(--color-primary);
    font-weight: 700;
    margin: 5px 0 0;
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

/* レスポンシブ対応 */
@media (max-width: 1024px) {
    .schedule-cast-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

@media (max-width: 900px) {
    .schedule-cast-grid {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 768px) {
    .date-links {
        padding: 5px 10px;
        text-align: left;
    }
    
    .schedule-cast-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
    }
    
    .schedule-cast-grid .cast-card .cast-name {
        font-size: 16px;
    }
    
    .schedule-cast-grid .cast-card .cast-stats {
        font-size: 13px;
    }
    
    .schedule-cast-grid .cast-card .cast-pr {
        font-size: 12px;
    }
    
    .schedule-cast-grid .cast-card .cast-time {
        font-size: 13px;
    }
}
CSS;
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <?php include __DIR__ . '/../includes/head.php'; ?>
</head>

<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <main class="main-content">
        <!-- パンくず -->
        <nav class="breadcrumb">
            <a href="/app/front/index.php"><?php echo h($shopName); ?></a><span>»</span>
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
            <div class="schedule-cast-grid cast-grid">
                <?php foreach ($casts as $cast): ?>
                    <?php
                    $dayColumn = "day{$dayNumber}";
                    $scheduleTime = $cast[$dayColumn] ?? '';
                    ?>
                    <a href="/app/front/cast/detail.php?id=<?php echo h($cast['id']); ?>&tenant=<?php echo h($shopCode); ?>"
                        class="cast-card">
                        <div class="cast-image">
                            <?php if (!empty($cast['img1'])): ?>
                                <img src="<?php echo h($cast['img1']); ?>" alt="<?php echo h($shopName . ' ' . $cast['name']); ?>"
                                    loading="lazy">
                            <?php else: ?>
                                <img src="/assets/img/no-image.png" alt="<?php echo h($shopName . ' ' . $cast['name']); ?>">
                            <?php endif; ?>
                        </div>
                        <div class="cast-info">
                            <h2 class="cast-name"><?php echo h($cast['name']); ?></h2>
                            <div class="cast-stats">
                                <span><?php echo h($cast['age']); ?>歳</span>
                                <span class="cup"><?php echo h($cast['cup']); ?>カップ</span>
                            </div>
                            <p class="cast-pr"><?php echo h($cast['pr_title']); ?></p>
                            <?php if ($scheduleTime): ?>
                                <p class="cast-time"><?php echo h($scheduleTime); ?></p>
                            <?php endif; ?>
                            <div
                                style="text-align: center; display: flex; flex-wrap: wrap; justify-content: center; gap: 2px; padding: 0; margin: 0;">
                                <?php if ($cast['now']): ?>
                                    <span class="badge now">案内中</span>
                                <?php elseif ($cast['closed']): ?>
                                    <span class="badge closed">受付終了</span>
                                <?php endif; ?>
                            </div>
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
    if (isset($currentTheme['is_preview']) && $currentTheme['is_preview']) {
        echo generatePreviewBar($currentTheme, $tenantId, $tenant['code']);
    }
    ?>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // スマホサイズでのみ実行
            if (window.innerWidth <= 768) {
                const dateLinks = document.querySelectorAll('.date-link');
                let activeButton = null;

                dateLinks.forEach((link, index) => {
                    if (index + 1 === <?php echo $dayNumber; ?>) {
                        activeButton = link;
                    }
                });

                if (activeButton) {
                    setTimeout(function () {
                        activeButton.scrollIntoView({
                            behavior: 'smooth',
                            block: 'nearest',
                            inline: 'start'
                        });
                    }, 100);
                }
            }
        });
    </script>
</body>

</html>