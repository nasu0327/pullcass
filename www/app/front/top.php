<?php
/**
 * pullcass - 店舗トップページ
 * ENTERボタンを押した後のメインページ
 * 参考: https://club-houman.com/top
 */

// index.phpから呼ばれた場合はbootstrapは既に読み込まれている
if (!function_exists('h')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
}

// テーマヘルパーを読み込む
require_once __DIR__ . '/../../includes/theme_helper.php';

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

// ページタイトル
$pageTitle = 'トップ｜' . $shopName;
$pageDescription = $shopName . 'のオフィシャルサイトです。';

// トップバナーを取得
$topBanners = [];
try {
    $pdo = getPlatformDb();
    if ($pdo) {
        $stmt = $pdo->prepare("SELECT * FROM top_banners WHERE tenant_id = ? AND is_visible = 1 ORDER BY display_order ASC");
        $stmt->execute([$tenantId]);
        $topBanners = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    // エラー時は空配列のまま
}

// 今日の日付
$today = date('n/j');
$dayOfWeek = ['日', '月', '火', '水', '木', '金', '土'][date('w')];

// テナントのスクレイピング設定を取得
$activeSource = 'ekichika'; // デフォルト
try {
    $pdo = getPlatformDb();
    if ($pdo) {
        $stmt = $pdo->prepare("SELECT config_value FROM tenant_scraping_config WHERE tenant_id = ? AND config_key = 'active_source'");
        $stmt->execute([$tenantId]);
        $settings = $stmt->fetch();
        $activeSource = $settings['config_value'] ?? 'ekichika';
    }
} catch (PDOException $e) {
    // デフォルト値を使用
}

// データソースに応じたテーブル名
$tableMap = [
    'ekichika' => 'tenant_cast_data_ekichika',
    'heaven' => 'tenant_cast_data_heaven',
    'dto' => 'tenant_cast_data_dto'
];
$tableName = $tableMap[$activeSource] ?? 'tenant_cast_data_ekichika';

// 新人キャストを取得
$newCasts = [];
try {
    $pdo = getPlatformDb();
    if ($pdo) {
        $stmt = $pdo->prepare("
            SELECT id, name, age, height, size, cup, pr_title, img1
            FROM {$tableName}
            WHERE tenant_id = ? AND checked = 1 AND `new` = '新人'
            ORDER BY id DESC
            LIMIT 10
        ");
        $stmt->execute([$tenantId]);
        $newCasts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    // エラー時は空配列のまま
}

// 本日の出勤キャストを取得
$todayCasts = [];
try {
    $pdo = getPlatformDb();
    if ($pdo) {
        // 今日はday1
        $stmt = $pdo->prepare("
            SELECT id, name, age, cup, pr_title, img1, day1, `now`, closed
            FROM {$tableName}
            WHERE tenant_id = ? 
              AND checked = 1
              AND day1 IS NOT NULL
              AND day1 != ''
            ORDER BY day1 ASC
        ");
        $stmt->execute([$tenantId]);
        $todayCasts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    // エラー時は空配列のまま
}

// ページ固有のCSS（最小限）
$additionalCss = <<<CSS
/* トップページ固有：Swiper スライダー */
.main-swiper {
    width: 100%;
    border-radius: 0;
    overflow: visible;
}

.main-swiper .swiper-slide {
    width: 100%;
}

.slide-link {
    display: block;
    width: 100%;
}

.slide-image {
    width: 100%;
    height: auto;
    display: block;
    border-radius: 15px;
}

/* PC/SP画像の表示切り替え */
.pc-link { display: block; }
.sp-link { display: none; }

@media (max-width: 768px) {
    .pc-link { display: none; }
    .sp-link { display: block; }
}

/* Swiper ナビゲーションボタン */
.main-swiper .swiper-button-next,
.main-swiper .swiper-button-prev {
    background: transparent;
    width: 40px;
    height: 40px;
    border-radius: 0;
    box-shadow: none;
    color: var(--color-primary);
    opacity: 0.7;
    transition: opacity 0.2s;
}

.main-swiper .swiper-button-next::after,
.main-swiper .swiper-button-prev::after {
    font-size: 30px;
    font-weight: 400;
    color: var(--color-primary);
}

.main-swiper .swiper-button-next:hover,
.main-swiper .swiper-button-prev:hover {
    opacity: 1;
}

/* Swiper ページネーション（ドット） */
.main-swiper .swiper-pagination {
    position: relative;
    bottom: auto;
    margin-top: 10px;
}

.main-swiper .swiper-pagination-bullet {
    width: 10px;
    height: 10px;
    background: var(--color-primary);
    opacity: 0.5;
    margin: 0 4px;
}

.main-swiper .swiper-pagination-bullet-active {
    background: var(--color-primary);
    opacity: 1;
}

/* スライダーセクション */
.slider-section {
    background: transparent;
    border-radius: 0;
    padding: 0;
    margin-bottom: 10px;
    text-align: center;
    min-height: 200px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.slider-section.has-banners {
    padding: 0;
    min-height: auto;
    overflow: visible;
}

.slider-placeholder {
    color: #888;
}

.slider-placeholder i {
    font-size: 3rem;
    color: var(--color-primary);
    opacity: 0.5;
    margin-bottom: 15px;
}

/* ティッカーセクション */
.ticker-section {
    background: rgba(255, 255, 255, 0.6);
    border-radius: 8px;
    padding: 12px 15px;
    margin-bottom: 20px;
    border: 1px solid #f0e0dc;
    display: flex;
    align-items: center;
    gap: 15px;
    overflow: hidden;
}

.ticker-label {
    background: var(--color-primary-light);
    color: white;
    padding: 5px 12px;
    border-radius: 5px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}

.ticker-content {
    color: #888;
    font-size: 13px;
}

/* 準備中カード */
.coming-soon-card {
    background: rgba(255, 255, 255, 0.6);
    border: 1px solid #f0e0dc;
    border-radius: 10px;
    padding: 30px 20px;
    text-align: center;
    margin-bottom: 20px;
}

.coming-soon-card i {
    font-size: 2.5rem;
    color: var(--color-primary);
    opacity: 0.4;
    margin-bottom: 12px;
}

.coming-soon-card h3 {
    font-size: 1rem;
    color: var(--color-text);
    margin-bottom: 8px;
}

.coming-soon-card p {
    color: #888;
    font-size: 0.85rem;
}

/* サイドバーバナー */
.sidebar-banner {
    display: block;
    background: rgba(255, 255, 255, 0.6);
    border-radius: 10px;
    padding: 20px;
    text-align: center;
    text-decoration: none;
    color: var(--color-text);
    transition: all 0.2s;
}

.sidebar-banner:hover {
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    text-decoration: none;
}

.sidebar-banner i {
    font-size: 2rem;
    color: var(--color-primary);
    opacity: 0.5;
    display: block;
    margin-bottom: 10px;
}

.sidebar-banner span {
    font-size: 13px;
    color: #888;
}

/* 閲覧履歴空表示 */
.history-empty {
    text-align: center;
    color: var(--color-text);
    padding: 20px;
    font-size: 13px;
    font-family: var(--font-body);
}

/* レスポンシブ */
@media (max-width: 600px) {
    body {
        padding-top: 60px;
    }
    
    .site-header {
        height: 60px;
    }
    
    .logo-image {
        width: 40px;
        height: 40px;
    }
    
    .hamburger-button {
        width: 48px;
        height: 48px;
    }
    
    .hamburger-line {
        width: 24px;
    }
}
CSS;
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<?php include __DIR__ . '/includes/head.php'; ?>
</head>
<body>
    <!-- ヘッダー -->
    <header class="site-header">
        <div class="header-container">
            <a href="/app/front/index.php" class="logo-area">
                <?php if ($logoSmallUrl): ?>
                    <img src="<?php echo h($logoSmallUrl); ?>" alt="<?php echo h($shopName); ?> ロゴ" class="logo-image">
                <?php elseif ($logoLargeUrl): ?>
                    <img src="<?php echo h($logoLargeUrl); ?>" alt="<?php echo h($shopName); ?> ロゴ" class="logo-image">
                <?php endif; ?>
                <div class="logo-text">
                    <?php if ($shopTitle): ?>
                        <?php 
                        $titleLines = explode("\n", $shopTitle);
                        foreach ($titleLines as $line): 
                            $line = trim($line);
                            if ($line):
                        ?>
                        <div class="logo-main-title"><?php echo h($line); ?></div>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    <?php else: ?>
                        <div class="logo-main-title"><?php echo h($shopName); ?></div>
                        <div class="logo-sub-title">オフィシャルサイト</div>
                    <?php endif; ?>
                </div>
            </a>
            <div class="menu-button-area">
                <button class="hamburger-button" aria-label="メニューを開く">
                    <div class="hamburger-lines">
                        <span class="hamburger-line"></span>
                        <span class="hamburger-line"></span>
                        <span class="hamburger-line"></span>
                    </div>
                    <span class="menu-text">MENU</span>
                </button>
            </div>
        </div>
    </header>
    
    <!-- メインコンテンツ -->
    <main class="main-content">
        <!-- パンくずナビ -->
        <nav class="breadcrumb">
            <a href="/app/front/index.php"><?php echo h($shopName); ?></a><span>»</span>トップ |
        </nav>
        
        <!-- メインスライダー (Swiper) -->
        <?php if (count($topBanners) > 0): ?>
        <section class="slider-section has-banners">
            <div class="swiper main-swiper">
                <div class="swiper-wrapper">
                    <?php foreach ($topBanners as $banner): ?>
                    <div class="swiper-slide">
                        <a href="<?php echo h($banner['pc_url']); ?>" class="slide-link pc-link">
                            <img src="<?php echo h($banner['pc_image']); ?>" alt="<?php echo h($banner['alt_text'] ?? ''); ?>" class="slide-image">
                        </a>
                        <a href="<?php echo h($banner['sp_url']); ?>" class="slide-link sp-link">
                            <img src="<?php echo h($banner['sp_image']); ?>" alt="<?php echo h($banner['alt_text'] ?? ''); ?>" class="slide-image">
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if (count($topBanners) > 1): ?>
                <!-- ナビゲーションボタン -->
                <div class="swiper-button-next"></div>
                <div class="swiper-button-prev"></div>
                <!-- ページネーション（ドット） -->
                <div class="swiper-pagination"></div>
                <?php endif; ?>
            </div>
        </section>
        <?php else: ?>
        <section class="slider-section">
            <div class="slider-placeholder">
                <i class="fas fa-images"></i>
                <p>メインビジュアル準備中</p>
                <p style="font-size: 12px; margin-top: 5px;">店舗管理画面からバナー画像を登録できます</p>
            </div>
        </section>
        <?php endif; ?>
        
        <!-- 店長オススメティッカー -->
        <section class="ticker-section">
            <span class="ticker-label"><?php echo h($today); ?>(<?php echo h($dayOfWeek); ?>)本日の店長オススメ</span>
            <span class="ticker-content">準備中...</span>
        </section>
        
        <!-- 2カラムレイアウト -->
        <div class="main-content-container">
            <!-- 左カラム（メイン） -->
            <div class="left-section">
                <!-- NEW CAST 新人 -->
                <div class="section-card">
                    <div class="section-title">
                        <div class="title-en">NEW CAST</div>
                        <div class="title-ja">新人</div>
                        <div class="dot-line"></div>
                    </div>
                    <?php if (!empty($newCasts)): ?>
                    <div class="scroll-wrapper">
                        <div class="cast-scroll-container scroll-container-x">
                            <div class="cast-cards cards-inline-flex">
                                <?php foreach ($newCasts as $cast): ?>
                                <div class="cast-card">
                                    <a href="/app/front/cast/detail.php?id=<?php echo h($cast['id']); ?>">
                                        <div class="cast-image">
                                            <?php if ($cast['img1']): ?>
                                                <img src="<?php echo h($cast['img1']); ?>" 
                                                     alt="<?php echo h($cast['name']); ?>"
                                                     loading="eager">
                                            <?php endif; ?>
                                        </div>
                                        <div class="cast-info">
                                            <div class="cast-name"><?php echo h($cast['name']); ?></div>
                                            <div class="cast-stats">
                                                <span><?php echo h($cast['age']); ?>歳</span>
                                                <span><?php echo h($cast['cup']); ?>カップ</span>
                                            </div>
                                            <?php if ($cast['pr_title']): ?>
                                            <div class="cast-pr-title"><?php echo h($cast['pr_title']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </a>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="scroll-gradient-right"></div>
                    </div>
                    <?php else: ?>
                    <div class="coming-soon-card">
                        <i class="fas fa-user-plus"></i>
                        <h3>新人情報準備中</h3>
                        <p>新人キャストがいません。</p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- TODAY 本日の出勤 -->
                <div class="section-card">
                    <div class="section-title">
                        <div class="title-en">TODAY</div>
                        <div class="title-ja">本日の出勤<span style="display: inline-block; margin-left: 10px; font-size: 0.8em;"><?php echo h($today); ?>(<?php echo h($dayOfWeek); ?>)</span></div>
                        <div class="dot-line"></div>
                    </div>
                    <?php if (!empty($todayCasts)): ?>
                    <div class="scroll-wrapper">
                        <div class="cast-scroll-container scroll-container-x">
                            <div class="cast-cards cards-inline-flex">
                                <?php foreach ($todayCasts as $cast): ?>
                                <div class="cast-card">
                                    <a href="/app/front/cast/detail.php?id=<?php echo h($cast['id']); ?>">
                                        <div class="cast-image">
                                            <?php if ($cast['img1']): ?>
                                                <img src="<?php echo h($cast['img1']); ?>" 
                                                     alt="<?php echo h($cast['name']); ?>"
                                                     loading="eager">
                                            <?php endif; ?>
                                        </div>
                                        <div class="cast-info">
                                            <div class="cast-name"><?php echo h($cast['name']); ?></div>
                                            <div class="cast-stats">
                                                <span><?php echo h($cast['age']); ?>歳</span>
                                                <span><?php echo h($cast['cup']); ?>カップ</span>
                                            </div>
                                            <?php if ($cast['pr_title']): ?>
                                            <div class="cast-pr-title"><?php echo h($cast['pr_title']); ?></div>
                                            <?php endif; ?>
                                            <?php if ($cast['day1']): ?>
                                            <div style="text-align: center; font-size: 12px; font-weight: bold; margin: 2px 0;"><?php echo h($cast['day1']); ?></div>
                                            <?php endif; ?>
                                            <div style="text-align: center;">
                                                <?php if ($cast['now']): ?>
                                                <span class="badge">案内中</span>
                                                <?php elseif ($cast['closed']): ?>
                                                <span class="badge" style="color: #888; border-color: #888;">受付終了</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="scroll-gradient-right"></div>
                    </div>
                    <?php else: ?>
                    <div class="coming-soon-card">
                        <i class="fas fa-calendar-day"></i>
                        <h3>本日の出勤情報なし</h3>
                        <p>本日の出勤キャストがいません。</p>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- REVIEW 口コミ -->
                <div class="section-card">
                    <div class="section-title">
                        <div class="title-en">REVIEW</div>
                        <div class="title-ja">口コミ</div>
                        <div class="dot-line"></div>
                    </div>
                    <div class="coming-soon-card">
                        <i class="fas fa-comment-dots"></i>
                        <h3>口コミ準備中</h3>
                        <p>口コミは連携設定後に表示されます。</p>
                    </div>
                </div>
                
                <!-- VIDEO 動画 -->
                <div class="section-card">
                    <div class="section-title">
                        <div class="title-en">VIDEO</div>
                        <div class="title-ja">動画</div>
                        <div class="dot-line"></div>
                    </div>
                    <div class="coming-soon-card">
                        <i class="fas fa-video"></i>
                        <h3>動画準備中</h3>
                        <p>動画は店舗管理画面から登録できます。</p>
                    </div>
                </div>
            </div>
            
            <!-- 右カラム（サイドバー） -->
            <div class="right-section">
                <!-- COMIC 体験漫画 -->
                <div class="section-card">
                    <div class="section-title">
                        <div class="title-en">COMIC</div>
                        <div class="title-ja">体験漫画</div>
                        <div class="dot-line"></div>
                    </div>
                    <a href="/comic" class="sidebar-banner">
                        <i class="fas fa-book-open"></i>
                        <span>体験漫画準備中</span>
                    </a>
                </div>
                
                <!-- HOTEL LIST -->
                <div class="section-card">
                    <div class="section-title">
                        <div class="title-en">HOTEL LIST</div>
                        <div class="title-ja">ホテルリスト</div>
                        <div class="dot-line"></div>
                    </div>
                    <a href="/hotel_list" class="sidebar-banner">
                        <i class="fas fa-hotel"></i>
                        <span>ホテルリストを見る</span>
                    </a>
                </div>
                
                <!-- PHOTO BLOG 写メ日記 -->
                <div class="section-card">
                    <div class="section-title">
                        <div class="title-en">PHOTO BLOG</div>
                        <div class="title-ja">写メ日記</div>
                        <div class="dot-line"></div>
                    </div>
                    <a href="/diary" class="sidebar-banner">
                        <i class="fas fa-camera"></i>
                        <span>写メ日記準備中</span>
                    </a>
                </div>
                
                <!-- HISTORY 閲覧履歴 -->
                <div class="section-card">
                    <div class="section-title">
                        <div class="title-en">HISTORY</div>
                        <div class="title-ja">閲覧履歴</div>
                        <div class="dot-line"></div>
                    </div>
                    <div class="history-wrapper">
                        <div class="history-content">
                            <div class="history-cards">
                                <!-- 履歴カードはJavaScriptで動的に生成されます -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <?php include __DIR__ . '/includes/footer_nav.php'; ?>
    
    <?php include __DIR__ . '/includes/footer.php'; ?>

    <?php if (count($topBanners) > 0): ?>
    <!-- Swiper JS -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const swiper = new Swiper('.main-swiper', {
            loop: true,
            spaceBetween: 0,
            autoplay: {
                delay: 5000,
                disableOnInteraction: false,
            },
            speed: 500,
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
            },
        });
    });
    </script>
    <?php endif; ?>
    
    <?php
    // プレビューモードの場合はプレビューバーを表示
    if (isset($currentTheme['is_preview']) && $currentTheme['is_preview']) {
        echo generatePreviewBar($currentTheme, $tenantId, $tenant['code']);
    }
    ?>
    
    <!-- 閲覧履歴スクリプト -->
    <script src="/assets/js/history.js"></script>
</body>
</html>
