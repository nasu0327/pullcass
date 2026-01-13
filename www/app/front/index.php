<?php
/**
 * pullcass - フロントページ
 * テナント別トップページ
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

// テナント情報を取得
$tenant = getCurrentTenant();

if (!$tenant) {
    // テナントが設定されていない場合
    http_response_code(404);
    echo "店舗が見つかりません。";
    exit;
}

// テナントDBに接続
try {
    $tenantDb = getTenantDb($tenant['db_name']);
    
    // 店舗設定を取得
    $stmt = $tenantDb->query("SELECT setting_key, setting_value FROM settings");
    $settingsRaw = $stmt->fetchAll();
    $settings = [];
    foreach ($settingsRaw as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    // テーマを取得
    $stmt = $tenantDb->query("SELECT * FROM themes WHERE status = 'published' LIMIT 1");
    $theme = $stmt->fetch();
    $themeData = $theme ? json_decode($theme['theme_data'], true) : null;
    
    // 本日出勤のキャストを取得
    $today = date('Y-m-d');
    $stmt = $tenantDb->prepare("
        SELECT c.*, s.start_time, s.end_time
        FROM casts c
        JOIN schedules s ON c.id = s.cast_id
        WHERE s.work_date = ? AND c.status = 'active'
        ORDER BY s.start_time ASC
    ");
    $stmt->execute([$today]);
    $todayCasts = $stmt->fetchAll();
    
    // 全キャストを取得
    $stmt = $tenantDb->query("SELECT * FROM casts WHERE status = 'active' ORDER BY display_order ASC, id ASC");
    $allCasts = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $settings = [];
    $themeData = null;
    $todayCasts = [];
    $allCasts = [];
}

// デフォルトテーマ
$defaultTheme = [
    'colors' => [
        'primary' => '#e94560',
        'primary_light' => '#ff6b6b',
        'text' => '#333333',
        'btn_text' => '#ffffff',
        'bg' => '#ffffff'
    ]
];

$colors = $themeData['colors'] ?? $defaultTheme['colors'];
$shopName = $settings['shop_name'] ?? $tenant['name'];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($shopName); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --color-primary: <?php echo h($colors['primary']); ?>;
            --color-primary-light: <?php echo h($colors['primary_light']); ?>;
            --color-text: <?php echo h($colors['text']); ?>;
            --color-btn-text: <?php echo h($colors['btn_text']); ?>;
            --color-bg: <?php echo h($colors['bg']); ?>;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Noto Sans JP', sans-serif;
            background: var(--color-bg);
            color: var(--color-text);
            line-height: 1.6;
        }
        
        /* ヘッダー */
        .header {
            background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-light) 100%);
            color: var(--color-btn-text);
            padding: 20px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2rem;
            font-weight: 700;
        }
        
        .header .phone {
            font-size: 1.5rem;
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .header .phone a {
            color: var(--color-btn-text);
            text-decoration: none;
        }
        
        /* ナビゲーション */
        .nav {
            background: #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .nav ul {
            display: flex;
            justify-content: center;
            list-style: none;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .nav li a {
            display: block;
            padding: 15px 25px;
            color: var(--color-text);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .nav li a:hover {
            color: var(--color-primary);
            background: rgba(233, 69, 96, 0.05);
        }
        
        /* メインコンテンツ */
        .main {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        /* セクション */
        .section {
            margin-bottom: 40px;
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--color-primary);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid var(--color-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* キャストグリッド */
        .cast-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .cast-card {
            background: #fff;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .cast-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .cast-image {
            width: 100%;
            aspect-ratio: 3/4;
            object-fit: cover;
            background: #f0f0f0;
        }
        
        .cast-image-placeholder {
            width: 100%;
            aspect-ratio: 3/4;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f0f0f0;
            font-size: 4rem;
            color: #ccc;
        }
        
        .cast-info {
            padding: 15px;
        }
        
        .cast-name {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .cast-stats {
            font-size: 0.9rem;
            color: #666;
        }
        
        .cast-time {
            display: inline-block;
            background: var(--color-primary);
            color: var(--color-btn-text);
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            margin-top: 8px;
        }
        
        /* 空状態 */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
        
        .empty-state .icon {
            font-size: 4rem;
            margin-bottom: 15px;
        }
        
        /* フッター */
        .footer {
            background: #333;
            color: #fff;
            padding: 40px 20px;
            text-align: center;
        }
        
        .footer-info {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .footer-shop-name {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 15px;
        }
        
        .footer-phone {
            font-size: 1.8rem;
            margin-bottom: 10px;
        }
        
        .footer-phone a {
            color: var(--color-primary-light);
            text-decoration: none;
        }
        
        .footer-hours {
            color: #aaa;
            margin-bottom: 20px;
        }
        
        .footer-copyright {
            font-size: 0.85rem;
            color: #666;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #444;
        }
        
        /* レスポンシブ */
        @media (max-width: 768px) {
            .header h1 {
                font-size: 1.5rem;
            }
            
            .nav ul {
                flex-wrap: wrap;
            }
            
            .nav li a {
                padding: 12px 15px;
                font-size: 0.9rem;
            }
            
            .cast-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <h1><?php echo h($shopName); ?></h1>
        <?php if (!empty($settings['phone'])): ?>
        <p class="phone">
            <i class="fas fa-phone"></i>
            <a href="tel:<?php echo h(preg_replace('/[^0-9]/', '', $settings['phone'])); ?>">
                <?php echo h($settings['phone']); ?>
            </a>
        </p>
        <?php endif; ?>
    </header>
    
    <nav class="nav">
        <ul>
            <li><a href="/">トップ</a></li>
            <li><a href="/cast/list.php">キャスト一覧</a></li>
            <li><a href="/schedule/">スケジュール</a></li>
            <li><a href="/price/">料金</a></li>
            <li><a href="/info/">店舗情報</a></li>
        </ul>
    </nav>
    
    <main class="main">
        <!-- 本日の出勤 -->
        <section class="section">
            <h2 class="section-title"><i class="fas fa-calendar-day"></i> 本日の出勤</h2>
            
            <?php if (empty($todayCasts)): ?>
            <div class="empty-state">
                <div class="icon"><i class="fas fa-calendar-alt"></i></div>
                <p>本日の出勤情報はまだ登録されていません</p>
            </div>
            <?php else: ?>
            <div class="cast-grid">
                <?php foreach ($todayCasts as $cast): ?>
                <div class="cast-card">
                    <?php if ($cast['profile_image']): ?>
                    <img src="<?php echo h($cast['profile_image']); ?>" alt="<?php echo h($cast['name']); ?>" class="cast-image">
                    <?php else: ?>
                    <div class="cast-image-placeholder"><i class="fas fa-user"></i></div>
                    <?php endif; ?>
                    <div class="cast-info">
                        <div class="cast-name"><?php echo h($cast['name']); ?></div>
                        <div class="cast-stats">
                            <?php echo h($cast['age']); ?>歳 / T<?php echo h($cast['height']); ?>
                            <?php if ($cast['cup']): ?> / <?php echo h($cast['cup']); ?>カップ<?php endif; ?>
                        </div>
                        <span class="cast-time">
                            <?php echo h(substr($cast['start_time'], 0, 5)); ?> - <?php echo h(substr($cast['end_time'], 0, 5)); ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>
        
        <!-- 在籍キャスト -->
        <section class="section">
            <h2 class="section-title"><i class="fas fa-crown"></i> 在籍キャスト</h2>
            
            <?php if (empty($allCasts)): ?>
            <div class="empty-state">
                <div class="icon"><i class="fas fa-user"></i></div>
                <p>キャストはまだ登録されていません</p>
            </div>
            <?php else: ?>
            <div class="cast-grid">
                <?php foreach ($allCasts as $cast): ?>
                <div class="cast-card">
                    <?php if ($cast['profile_image']): ?>
                    <img src="<?php echo h($cast['profile_image']); ?>" alt="<?php echo h($cast['name']); ?>" class="cast-image">
                    <?php else: ?>
                    <div class="cast-image-placeholder"><i class="fas fa-user"></i></div>
                    <?php endif; ?>
                    <div class="cast-info">
                        <div class="cast-name"><?php echo h($cast['name']); ?></div>
                        <div class="cast-stats">
                            <?php echo h($cast['age']); ?>歳 / T<?php echo h($cast['height']); ?>
                            <?php if ($cast['cup']): ?> / <?php echo h($cast['cup']); ?>カップ<?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>
    </main>
    
    <footer class="footer">
        <div class="footer-info">
            <div class="footer-shop-name"><?php echo h($shopName); ?></div>
            <?php if (!empty($settings['phone'])): ?>
            <div class="footer-phone">
                <a href="tel:<?php echo h(preg_replace('/[^0-9]/', '', $settings['phone'])); ?>">
                    <?php echo h($settings['phone']); ?>
                </a>
            </div>
            <?php endif; ?>
            <?php if (!empty($settings['open_time']) && !empty($settings['close_time'])): ?>
            <div class="footer-hours">
                営業時間: <?php echo h($settings['open_time']); ?> - <?php echo h($settings['close_time']); ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="footer-copyright">
            &copy; <?php echo date('Y'); ?> <?php echo h($shopName); ?> All Rights Reserved.<br>
            Powered by pullcass
        </div>
    </footer>
</body>
</html>
