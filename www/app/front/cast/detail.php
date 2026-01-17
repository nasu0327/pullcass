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
$schedule = [];
$dayOfWeekNames = ['日', '月', '火', '水', '木', '金', '土'];
for ($i = 0; $i < 7; $i++) {
    $date = new DateTime();
    $date->modify("+{$i} days");
    $dayNum = $i + 1;
    $dayKey = "day{$dayNum}";
    
    $time = (isset($cast[$dayKey]) && !empty($cast[$dayKey])) ? $cast[$dayKey] : '---';
    
    $schedule[] = [
        'date' => $date->format('n/j') . '(' . $dayOfWeekNames[$date->format('w')] . ')',
        'time' => $time
    ];
}

// ページタイトル
$pageTitle = $cast['name'] . '｜' . $shopName;
$pageDescription = $shopName . 'の' . $cast['name'] . 'のプロフィールページです。';

// インラインCSS不要 - style.cssで管理
$additionalCss = '';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta name="cast-id" content="<?php echo h($castId); ?>">
<?php include __DIR__ . '/../includes/head.php'; ?>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <main>
        <!-- パンくず -->
        <nav class="breadcrumb">
            <a href="/app/front/index.php"><?php echo h($shopName); ?></a><span>»</span>
            <a href="/app/front/top.php">トップ</a><span>»</span>
            <a href="/app/front/cast/list.php">キャスト一覧</a><span>»</span><?php echo h($cast['name']); ?> |
        </nav>
        
        <!-- タイトルセクション -->
        <section class="title-section cast-detail-title" style="margin-bottom: 10px; padding-bottom: 0;">
            <h1>PROFILE</h1>
            <h2>「<?php echo h($cast['name']); ?>」さんのプロフィール</h2>
            <div class="dot-line"></div>
        </section>
        
        <!-- メインコンテンツエリア -->
        <section class="main-content" style="padding: 0; margin-top: 10px;">
            <!-- キャストコンテンツ（画像と情報の横並び） -->
            <div class="cast-content">
                <!-- スワイパー部分 -->
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
                        
                        <div class="swiper-button-next"></div>
                        <div class="swiper-button-prev"></div>
                        <div class="swiper-pagination"></div>
                    </div>
                </div>

                <!-- キャスト情報部分 -->
                <div class="cast-info-sidebar">
                    <div class="cast-name-age">
                        <h3>
                            <?php echo h($cast['name']); ?>
                            <span><?php echo h($cast['age']); ?>歳</span>
                        </h3>
                    </div>

                    <?php if ($cast['pr_title']): ?>
                    <h2 class="cast-pr-title"><?php echo h($cast['pr_title']); ?></h2>
                    <?php endif; ?>
                    
                    <div class="cast-stats-detail">
                        <p>
                            <span class="cup-size"><?php echo h($cast['cup']); ?></span> カップ
                        </p>
                        <?php if ($cast['height'] || $cast['size']): ?>
                        <p>身長<?php echo h($cast['height']); ?>cm <?php echo h($cast['size']); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="cast-badges">
                        <?php if ($cast['new']): ?>
                            <span class="badge new">NEW</span>
                        <?php endif; ?>
                        <?php if ($cast['today']): ?>
                            <span class="badge">本日出勤</span>
                        <?php endif; ?>
                        <?php if ($cast['now']): ?>
                            <span class="badge">案内中</span>
                        <?php endif; ?>
                        <?php if ($cast['closed']): ?>
                            <span class="badge" style="color: #888; border-color: #888;">受付終了</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($cast['pr_text']): ?>
                    <div class="cast-pr-text">
                        <p><?php echo nl2br(h($cast['pr_text'])); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- キャスト情報エリア（出勤表・予約・3カラム） -->
            <div class="cast-info-area">
                <!-- 出勤表セクション -->
                <section class="cast-schedule">
                    <div class="title-section cast-detail-title">
                        <h1>SCHEDULE</h1>
                        <h2>出勤表</h2>
                        <div class="dot-line"></div>
                    </div>

                    <!-- PC表示用 -->
                    <div class="pc-schedule">
                        <table>
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
                        <div class="sp-schedule-scroll-wrapper">
                            <div class="sp-schedule-scroll">
                                <?php foreach ($schedule as $item): ?>
                                <div class="sp-schedule-item">
                                    <div class="day"><?php echo h($item['date']); ?></div>
                                    <div class="time"><?php echo h($item['time']); ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 予約セクション -->
                <section class="cast-reserve">
                    <div class="title-section cast-detail-title">
                        <h1>RESERVE</h1>
                        <h2>ネット予約</h2>
                        <div class="dot-line"></div>
                    </div>

                    <div class="reserve-buttons">
                        <a href="/app/front/yoyaku.php?cast=<?php echo h($cast['id']); ?>" class="reserve-button">
                            <?php if ($logoSmallUrl): ?>
                                <img src="<?php echo h($logoSmallUrl); ?>" alt="<?php echo h($cast['name']); ?>さんを予約する">
                            <?php endif; ?>
                            <?php echo h($cast['name']); ?>さんを予約する
                        </a>
                    </div>
                </section>

                <!-- 3つのセクションエリア -->
                <div class="three-sections">
                    <!-- REVIEWセクション -->
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
                            <div class="scroll-gradient-bottom"></div>
                        </div>
                    </section>

                    <!-- DIARYセクション -->
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
                            <div class="scroll-gradient-bottom"></div>
                        </div>
                    </section>

                    <!-- HISTORYセクション -->
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
            </div>
        </section>
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
    if (isset($currentTheme['is_preview']) && $currentTheme['is_preview']) {
        echo generatePreviewBar($currentTheme, $tenantId, $tenant['code']);
    }
    ?>
    
    <!-- 閲覧履歴スクリプト -->
    <script src="/assets/js/history.js"></script>
</body>
</html>
