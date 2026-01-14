<?php
/**
 * pullcass - 店舗トップページ
 * ENTERボタンを押した後のメインページ
 */

// index.phpから呼ばれた場合はbootstrapは既に読み込まれている
if (!function_exists('h')) {
    require_once __DIR__ . '/../../includes/bootstrap.php';
}

// テナント情報を取得
$tenant = getCurrentTenant();

if (!$tenant) {
    // テナントが見つからない場合はインデックスへリダイレクト
    header('Location: /');
    exit;
}

// 店舗情報
$shopName = $tenant['name'];
$shopCode = $tenant['code'];
$tenantId = $tenant['id'];

// ロゴ画像
$logoLargeUrl = $tenant['logo_large_url'] ?? '';
$logoSmallUrl = $tenant['logo_small_url'] ?? '';
$faviconUrl = $tenant['favicon_url'] ?? '';

// 電話番号
$phoneNumber = $tenant['phone'] ?? '';

// 営業時間
$businessHours = $tenant['business_hours'] ?? '';
$businessHoursNote = $tenant['business_hours_note'] ?? '';

// テーマカラー（将来的にはDBから取得）
$colors = [
    'primary' => '#f568df',
    'primary_light' => '#ffa0f8',
    'text' => '#474747',
    'btn_text' => '#ffffff',
    'bg' => '#ffffff'
];

// ページタイトル
$pageTitle = $shopName;
$pageDescription = $shopName . 'のオフィシャルサイトです。';
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Zen+Kaku+Gothic+New:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --color-primary: <?php echo h($colors['primary']); ?>;
            --color-primary-light: <?php echo h($colors['primary_light']); ?>;
            --color-text: <?php echo h($colors['text']); ?>;
            --color-btn-text: <?php echo h($colors['btn_text']); ?>;
            --color-bg: <?php echo h($colors['bg']); ?>;
            --color-border: #eee;
            --color-gray: #888;
        }
        
        body {
            font-family: 'Zen Kaku Gothic New', sans-serif;
            background: var(--color-bg);
            color: var(--color-text);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            padding-top: 70px; /* ヘッダー分 */
            padding-bottom: 70px; /* 固定フッター分 */
        }
        
        /* ==================== ヘッダー ==================== */
        .site-header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            height: 70px;
            display: flex;
            align-items: center;
        }
        
        .header-container {
            max-width: 1100px;
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
            margin-right: 10px;
        }
        
        .logo-text {
            display: flex;
            flex-direction: column;
        }
        
        .logo-main-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--color-text);
            line-height: 1.2;
        }
        
        .logo-sub-title {
            font-size: 11px;
            color: var(--color-gray);
            line-height: 1.2;
        }
        
        /* ハンバーガーメニューボタン */
        .hamburger-button {
            width: 50px;
            height: 50px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--color-primary), var(--color-primary-light));
            color: var(--color-btn-text);
            border-radius: 50%;
            border: none;
            cursor: pointer;
            transition: transform 0.2s;
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
            width: 20px;
            height: 2px;
            background: var(--color-btn-text);
            border-radius: 1px;
        }
        
        .menu-text {
            font-size: 8px;
            font-weight: 700;
            letter-spacing: 1px;
        }
        
        /* ==================== メインコンテンツ ==================== */
        .main-content {
            flex: 1;
            max-width: 1100px;
            width: 100%;
            margin: 0 auto;
            padding: 20px 15px;
        }
        
        /* パンくずナビ */
        .breadcrumb {
            font-size: 12px;
            color: var(--color-gray);
            margin-bottom: 15px;
        }
        
        .breadcrumb a {
            color: var(--color-primary);
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        /* セクションタイトル */
        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--color-text);
            margin: 30px 0 10px;
            padding-bottom: 5px;
            border-bottom: 3px solid var(--color-primary);
            display: inline-block;
        }
        
        .section-title i {
            margin-right: 8px;
            color: var(--color-primary);
        }
        
        /* 準備中メッセージ */
        .coming-soon-section {
            background: #f9f9f9;
            border: 1px solid var(--color-border);
            border-radius: 10px;
            padding: 40px 20px;
            text-align: center;
            margin: 15px 0;
        }
        
        .coming-soon-section i {
            font-size: 3rem;
            color: var(--color-primary);
            margin-bottom: 15px;
        }
        
        .coming-soon-section h3 {
            font-size: 1.2rem;
            margin-bottom: 10px;
        }
        
        .coming-soon-section p {
            color: var(--color-gray);
            font-size: 0.9rem;
        }
        
        /* メニューグリッド */
        .menu-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .menu-item {
            background: #fff;
            border: 1px solid var(--color-border);
            border-radius: 10px;
            padding: 20px 15px;
            text-align: center;
            text-decoration: none;
            color: var(--color-text);
            transition: all 0.2s;
        }
        
        .menu-item:hover {
            border-color: var(--color-primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(245, 104, 223, 0.2);
        }
        
        .menu-item i {
            font-size: 2rem;
            color: var(--color-primary);
            margin-bottom: 10px;
            display: block;
        }
        
        .menu-item span {
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        /* 2カラムレイアウト（PC用） */
        .two-column {
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
        }
        
        @media (min-width: 768px) {
            .two-column {
                grid-template-columns: 2fr 1fr;
            }
        }
        
        /* ==================== 固定フッター ==================== */
        .fixed-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            background: linear-gradient(135deg, var(--color-primary), var(--color-primary-light));
            padding: 10px 15px;
            z-index: 1000;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .fixed-footer-container {
            max-width: 1100px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .fixed-footer-info {
            color: var(--color-btn-text);
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
            background: var(--color-btn-text);
            color: var(--color-primary);
            padding: 10px 20px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 700;
            transition: transform 0.2s;
        }
        
        .phone-button:hover {
            transform: scale(1.05);
        }
        
        .phone-button i {
            font-size: 1.2rem;
        }
        
        /* ==================== 通常フッター ==================== */
        .site-footer {
            background: #f5f5f5;
            padding: 30px 20px;
            margin-top: auto;
        }
        
        .footer-nav {
            max-width: 1100px;
            margin: 0 auto;
        }
        
        .footer-nav ul {
            list-style: none;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 5px 15px;
            margin-bottom: 20px;
        }
        
        .footer-nav ul li a {
            color: var(--color-text);
            text-decoration: none;
            font-size: 12px;
            transition: color 0.2s;
        }
        
        .footer-nav ul li a:hover {
            color: var(--color-primary);
        }
        
        .copyright {
            text-align: center;
            font-size: 11px;
            color: var(--color-gray);
        }
        
        /* ==================== レスポンシブ ==================== */
        @media (max-width: 600px) {
            .logo-main-title {
                font-size: 14px;
            }
            
            .logo-sub-title {
                font-size: 10px;
            }
            
            .fixed-footer-container {
                flex-direction: column;
                gap: 10px;
            }
            
            .fixed-footer-info {
                text-align: center;
            }
            
            .phone-button {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- ヘッダー -->
    <header class="site-header">
        <div class="header-container">
            <a href="/" class="logo-area">
                <?php if ($logoSmallUrl): ?>
                    <img src="<?php echo h($logoSmallUrl); ?>" alt="<?php echo h($shopName); ?>" class="logo-image">
                <?php elseif ($logoLargeUrl): ?>
                    <img src="<?php echo h($logoLargeUrl); ?>" alt="<?php echo h($shopName); ?>" class="logo-image">
                <?php endif; ?>
                <div class="logo-text">
                    <div class="logo-main-title"><?php echo h($shopName); ?></div>
                    <div class="logo-sub-title">オフィシャルサイト</div>
                </div>
            </a>
            <button class="hamburger-button" aria-label="メニューを開く">
                <div class="hamburger-lines">
                    <span class="hamburger-line"></span>
                    <span class="hamburger-line"></span>
                    <span class="hamburger-line"></span>
                </div>
                <span class="menu-text">MENU</span>
            </button>
        </div>
    </header>
    
    <!-- メインコンテンツ -->
    <main class="main-content">
        <!-- パンくずナビ -->
        <nav class="breadcrumb">
            <a href="/"><?php echo h($shopName); ?></a> <span>»</span> トップ
        </nav>
        
        <!-- メニューグリッド -->
        <section>
            <div class="menu-grid">
                <a href="/cast/list" class="menu-item">
                    <i class="fas fa-user"></i>
                    <span>キャスト一覧</span>
                </a>
                <a href="/schedule" class="menu-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span>出勤スケジュール</span>
                </a>
                <a href="/system" class="menu-item">
                    <i class="fas fa-yen-sign"></i>
                    <span>料金システム</span>
                </a>
                <a href="/hotel_list" class="menu-item">
                    <i class="fas fa-hotel"></i>
                    <span>ホテルリスト</span>
                </a>
                <a href="/reviews" class="menu-item">
                    <i class="fas fa-comment-dots"></i>
                    <span>口コミ</span>
                </a>
                <a href="/diary" class="menu-item">
                    <i class="fas fa-camera"></i>
                    <span>写メ日記</span>
                </a>
                <a href="/yoyaku" class="menu-item">
                    <i class="fas fa-calendar-check"></i>
                    <span>ネット予約</span>
                </a>
                <a href="/faq" class="menu-item">
                    <i class="fas fa-question-circle"></i>
                    <span>よくある質問</span>
                </a>
            </div>
        </section>
        
        <!-- 2カラムレイアウト -->
        <div class="two-column">
            <!-- 左カラム -->
            <div class="left-column">
                <!-- 本日の出勤 -->
                <h2 class="section-title"><i class="fas fa-calendar-day"></i>本日の出勤</h2>
                <div class="coming-soon-section">
                    <i class="fas fa-user-clock"></i>
                    <h3>出勤情報準備中</h3>
                    <p>キャストの出勤情報は店舗管理画面から登録できます。</p>
                </div>
                
                <!-- 新着情報 -->
                <h2 class="section-title"><i class="fas fa-bullhorn"></i>新着情報</h2>
                <div class="coming-soon-section">
                    <i class="fas fa-newspaper"></i>
                    <h3>新着情報準備中</h3>
                    <p>お知らせは店舗管理画面から登録できます。</p>
                </div>
                
                <!-- 写メ日記 -->
                <h2 class="section-title"><i class="fas fa-camera"></i>写メ日記</h2>
                <div class="coming-soon-section">
                    <i class="fas fa-images"></i>
                    <h3>写メ日記準備中</h3>
                    <p>キャストの写メ日記は店舗管理画面から登録できます。</p>
                </div>
            </div>
            
            <!-- 右カラム -->
            <div class="right-column">
                <!-- 新人キャスト -->
                <h2 class="section-title"><i class="fas fa-star"></i>新人キャスト</h2>
                <div class="coming-soon-section">
                    <i class="fas fa-user-plus"></i>
                    <h3>新人情報準備中</h3>
                    <p>新人キャストは店舗管理画面から登録できます。</p>
                </div>
                
                <!-- ランキング -->
                <h2 class="section-title"><i class="fas fa-crown"></i>人気ランキング</h2>
                <div class="coming-soon-section">
                    <i class="fas fa-trophy"></i>
                    <h3>ランキング準備中</h3>
                    <p>ランキングはデータが蓄積されると自動で表示されます。</p>
                </div>
            </div>
        </div>
    </main>
    
    <!-- 通常フッター -->
    <footer class="site-footer">
        <nav class="footer-nav">
            <ul>
                <li><a href="/"><?php echo h($shopName); ?></a></li>
                <li><a href="/top">トップページ</a></li>
                <li><a href="/cast/list">在籍一覧</a></li>
                <li><a href="/schedule">出勤スケジュール</a></li>
                <li><a href="/system">料金システム</a></li>
                <li><a href="/hotel_list">ホテルリスト</a></li>
                <li><a href="/reviews">口コミ</a></li>
                <li><a href="/diary">写メ日記</a></li>
                <li><a href="/yoyaku">ネット予約</a></li>
                <li><a href="/faq">よくある質問</a></li>
            </ul>
        </nav>
        <p class="copyright">
            &copy; <?php echo date('Y'); ?> <?php echo h($shopName); ?>. All Rights Reserved.
        </p>
    </footer>
    
    <!-- 固定フッター（電話ボタン） -->
    <footer class="fixed-footer">
        <div class="fixed-footer-container">
            <div class="fixed-footer-info">
                <p class="open-hours">OPEN <?php echo $businessHours ? h($businessHours) : '準備中'; ?></p>
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
</body>
</html>
