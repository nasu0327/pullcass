<?php
/**
 * 写メ日記スクレイピング設定画面
 */

require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

$pageTitle = '写メ日記スクレイピング設定';
$currentPage = 'diary_scrape';

$platformPdo = getPlatformDb();
$tenantId = $tenant['id'];

$success = '';
$error = '';

// 設定保存処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $platformPdo->prepare("SELECT id FROM diary_scrape_settings WHERE tenant_id = ?");
        $stmt->execute([$tenantId]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $stmt = $platformPdo->prepare("
                UPDATE diary_scrape_settings SET
                    cityheaven_login_id = ?,
                    cityheaven_password = ?,
                    shop_url = ?,
                    scrape_interval = ?,
                    request_delay = ?,
                    max_pages = ?,
                    timeout = ?,
                    max_posts_per_tenant = ?,
                    updated_at = NOW()
                WHERE tenant_id = ?
            ");
            $stmt->execute([
                $_POST['cityheaven_login_id'],
                $_POST['cityheaven_password'],
                $_POST['shop_url'],
                $_POST['scrape_interval'],
                $_POST['request_delay'],
                $_POST['max_pages'],
                $_POST['timeout'],
                $_POST['max_posts_per_tenant'],
                $tenantId
            ]);
        } else {
            $stmt = $platformPdo->prepare("
                INSERT INTO diary_scrape_settings (
                    tenant_id, cityheaven_login_id, cityheaven_password,
                    shop_url, scrape_interval, request_delay,
                    max_pages, timeout, max_posts_per_tenant
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $tenantId,
                $_POST['cityheaven_login_id'],
                $_POST['cityheaven_password'],
                $_POST['shop_url'],
                $_POST['scrape_interval'],
                $_POST['request_delay'],
                $_POST['max_pages'],
                $_POST['timeout'],
                $_POST['max_posts_per_tenant']
            ]);
        }
        
        $success = '設定を保存しました。';
    } catch (Exception $e) {
        $error = '保存エラー: ' . $e->getMessage();
    }
}

// 設定取得
$stmt = $platformPdo->prepare("SELECT * FROM diary_scrape_settings WHERE tenant_id = ?");
$stmt->execute([$tenantId]);
$settings = $stmt->fetch();

if (!$settings) {
    $settings = [
        'cityheaven_login_id' => '',
        'cityheaven_password' => '',
        'shop_url' => '',
        'scrape_interval' => 10,
        'request_delay' => 0.5,
        'max_pages' => 50,
        'timeout' => 30,
        'max_posts_per_tenant' => 1000,
    ];
}

include __DIR__ . '/../includes/header.php';
?>

<?php
require_once __DIR__ . '/../includes/breadcrumb.php';
$breadcrumbs = [
    ['label' => 'ダッシュボード', 'url' => '/app/manage/?tenant=' . $tenantSlug, 'icon' => 'fas fa-chart-pie'],
    ['label' => '写メ日記スクレイピング', 'url' => '/app/manage/diary_scrape/?tenant=' . $tenantSlug],
    ['label' => '設定']
];
renderBreadcrumb($breadcrumbs);
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-cog"></i> 写メ日記スクレイピング設定</h1>
        <p>CityHeavenからの自動取得に必要な設定を行います</p>
    </div>
</div>

<?php if ($success): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i> <?= h($success) ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-error">
    <i class="fas fa-exclamation-circle"></i> <?= h($error) ?>
</div>
<?php endif; ?>

<form method="POST">
    <!-- CityHeavenログイン情報 -->
    <div class="content-card">
        <div class="card-section-title">
            <i class="fas fa-key"></i> CityHeavenログイン情報
        </div>
        
        <div class="form-group">
            <label class="form-label">
                ログインID（メールアドレス） <span style="color: var(--danger);">*</span>
            </label>
            <input type="email" name="cityheaven_login_id" class="form-control" 
                   value="<?= h($settings['cityheaven_login_id']) ?>" required
                   placeholder="example@email.com">
            <div class="help-text">CityHeavenにログインする際のメールアドレスを入力してください</div>
        </div>

        <div class="form-group">
            <label class="form-label">
                パスワード <span style="color: var(--danger);">*</span>
            </label>
            <input type="password" name="cityheaven_password" class="form-control" 
                   value="<?= h($settings['cityheaven_password']) ?>" required>
            <div class="help-text">CityHeavenにログインする際のパスワードを入力してください</div>
        </div>
    </div>

    <!-- 店舗情報 -->
    <div class="content-card">
        <div class="card-section-title">
            <i class="fas fa-store"></i> 店舗情報
        </div>
        
        <div class="form-group">
            <label class="form-label">
                写メ日記ページURL <span style="color: var(--danger);">*</span>
            </label>
            <input type="url" name="shop_url" class="form-control" 
                   value="<?= h($settings['shop_url']) ?>" required
                   placeholder="https://www.cityheaven.net/fukuoka/A4001/A400101/店舗名/diarylist/">
            <div class="help-text">例: https://www.cityheaven.net/fukuoka/A4001/A400101/houmantengoku/diarylist/</div>
        </div>
    </div>

    <!-- スクレイピング設定 -->
    <div class="content-card">
        <div class="card-section-title">
            <i class="fas fa-sliders-h"></i> スクレイピング詳細設定
        </div>
        
        <div class="form-grid-2">
            <div class="form-group">
                <label class="form-label">取得間隔（分）</label>
                <input type="number" name="scrape_interval" class="form-control" 
                       value="<?= h($settings['scrape_interval']) ?>" min="5" max="1440">
                <div class="help-text">自動実行の間隔（5〜1440分）</div>
            </div>

            <div class="form-group">
                <label class="form-label">リクエスト遅延（秒）</label>
                <input type="number" name="request_delay" class="form-control" 
                       value="<?= h($settings['request_delay']) ?>" min="0.1" max="5" step="0.1">
                <div class="help-text">ページ取得間の待機時間</div>
            </div>

            <div class="form-group">
                <label class="form-label">最大ページ数</label>
                <input type="number" name="max_pages" class="form-control" 
                       value="<?= h($settings['max_pages']) ?>" min="1" max="100">
                <div class="help-text">1回の実行で取得するページ数上限</div>
            </div>

            <div class="form-group">
                <label class="form-label">タイムアウト（秒）</label>
                <input type="number" name="timeout" class="form-control" 
                       value="<?= h($settings['timeout']) ?>" min="10" max="120">
                <div class="help-text">HTTPリクエストのタイムアウト秒数</div>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">最大保存件数</label>
            <input type="number" name="max_posts_per_tenant" class="form-control" 
                   value="<?= h($settings['max_posts_per_tenant']) ?>" min="100" max="10000">
            <div class="help-text">テナントごとの最大保存件数（超過分は古いものから自動削除されます）</div>
        </div>
    </div>

    <div class="action-bar" style="margin-top: 20px;">
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> 保存
        </button>
        <a href="index.php?tenant=<?= h($tenantSlug) ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> 戻る
        </a>
    </div>
</form>

<?php include __DIR__ . '/../includes/footer.php'; ?>
