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

// ページ固有のCSS（最小限）
$additionalCss = <<<CSS
/* キャスト一覧ページ固有スタイル */
.cast-info h2 {
    font-size: 1.2em;
    margin: 0 0 2px 0;
    line-height: 1.2;
    color: var(--color-text);
    font-weight: 700;
}

.cast-stats .age {
    font-size: 0.9em;
    color: var(--color-text);
}

.cast-stats .cup {
    font-size: 1em;
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
                                <span class="badge">NEW</span>
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
    if (isset($currentTheme['is_preview']) && $currentTheme['is_preview']) {
        echo generatePreviewBar($currentTheme, $tenantId, $tenant['code']);
    }
    ?>
</body>
</html>
