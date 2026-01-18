<?php
/**
 * pullcass - キャスト一覧ページ
 * 参考: https://club-houman.com/cast/list
 * ※参考サイトのインラインスタイルを忠実に再現
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
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<?php include __DIR__ . '/../includes/head.php'; ?>
<style>
/* キャスト一覧ページ固有スタイル（参考サイト準拠） */
@media (max-width: 768px) {
    .cast-card {
        font-size: 0.9em;
    }
}
@media (max-width: 480px) {
    .cast-card {
        border-radius: 5px !important;
    }
    .cast-info {
        padding: 4px 2px !important;
    }
    .cast-info h2 {
        font-size: 1.1em !important;
        margin: 0 0 1px 0 !important;
        line-height: 1.1 !important;
    }
    .cast-info > div:first-of-type {
        margin: 0 0 1px 0 !important;
    }
    .cast-pr {
        margin: 0px !important;
        height: 2.2em !important;
        line-height: 1.1 !important;
    }
    .cast-info > div:last-of-type {
        margin: 2px 0 0 0 !important;
    }
    .badge {
        font-size: 10px !important;
        padding: 0px 3px !important;
    }
}
</style>
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <main class="main-content">
        <!-- パンくず -->
        <nav class="breadcrumb">
            <a href="/app/front/index.php">ホーム</a><span>»</span>
            <a href="/app/front/top.php">トップ</a><span>»</span>キャスト一覧 |
        </nav>
        
        <!-- タイトルセクション -->
        <section class="title-section" style="padding-bottom: 0;">
            <h1>ALL CAST</h1>
            <h2>キャスト一覧</h2>
            <div class="dot-line" style="margin-bottom: 0px;"></div>
        </section>
        
        <!-- メインコンテンツエリア -->
        <section class="main-content" style="padding: 0;">
            <div class="cast-grid" style="margin-bottom: 20px; padding: 0 16px;">
                <?php foreach ($casts as $cast): ?>
                <div class="cast-card" style="background: rgba(255,255,255,0.6); border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); overflow: hidden; transition: transform 0.3s ease;">
                    <a href="/app/front/cast/detail.php?id=<?php echo h($cast['id']); ?>" style="text-decoration: none; color: inherit; display: block;">
                        <div class="cast-image" style="position: relative; width: 100%; padding-top: 133%; overflow: hidden;">
                            <?php if ($cast['img1']): ?>
                                <img src="<?php echo h($cast['img1']); ?>" 
                                     alt="<?php echo h($shopName . ' ' . $cast['name']); ?>"
                                     loading="lazy"
                                     style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-user" style="font-size: 48px; color: #ccc;"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="cast-info" style="padding: 5px 3px;">
                            <h2 style="margin: 0 0 2px 0; font-size: 1.2em; color: var(--color-text); text-align: center; line-height: 1.2; padding: 0;"><?php echo h($cast['name']); ?></h2>
                            <div style="display: flex; align-items: center; justify-content: center; margin: 0 0 2px 0; padding: 0;">
                                <span style="color: var(--color-text); font-size: 0.9em; padding: 0;"><?php echo h($cast['age']); ?>歳</span>
                                <span style="color: var(--color-text); font-size: 1.0em; margin-left: 5px; font-weight: bold; padding: 0;"><?php echo h($cast['cup']); ?>カップ</span>
                            </div>
                            <p class="cast-pr" style="margin: 0; color: var(--color-text); font-size: 0.8em; line-height: 1.1; min-height: 2.2em; max-height: 2.2em; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; padding: 0;"><?php echo h($cast['pr_title']); ?></p>
                            <div style="text-align: center; display: flex; flex-wrap: wrap; justify-content: center; gap: 2px; padding: 0; margin: 0;">
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
        </section>
        
        <?php if (empty($casts)): ?>
        <div style="text-align: center; padding: 50px 20px; color: var(--color-text);">
            <i class="fas fa-users" style="font-size: 48px; color: #ccc; margin-bottom: 15px;"></i>
            <p>現在、キャストデータがありません。</p>
        </div>
        <?php endif; ?>
        
        <!-- セクション下の影 -->
        <div class="w-full h-[15px]" style="background-color:transparent; box-shadow:0 -8px 12px -4px rgba(0,0,0,0.2); position:relative;"></div>
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
