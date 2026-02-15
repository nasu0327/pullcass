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

// 固定値（管理画面では非表示）
$fixedScrapeInterval = 10;
$fixedRequestDelay = 0.5;
$fixedTimeout = 30;

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
                $fixedScrapeInterval,
                $fixedRequestDelay,
                $_POST['max_pages'],
                $fixedTimeout,
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
                $fixedScrapeInterval,
                $fixedRequestDelay,
                $_POST['max_pages'],
                $fixedTimeout,
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
        'max_pages' => 50,
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
    <!-- CityHeaven接続情報 -->
    <div class="content-card">
        <div class="card-section-title">
            <i class="fas fa-key"></i> CityHeaven接続情報
        </div>
        
        <div class="form-grid-2">
            <div class="form-group">
                <label class="form-label">ログインID <span style="color: var(--danger);">*</span></label>
                <input type="email" name="cityheaven_login_id" class="form-control" 
                       value="<?= h($settings['cityheaven_login_id']) ?>" required
                       placeholder="example@email.com">
            </div>
            <div class="form-group">
                <label class="form-label">パスワード <span style="color: var(--danger);">*</span></label>
                <div style="position: relative;">
                    <input type="password" name="cityheaven_password" id="ch-password" class="form-control" 
                           value="<?= h($settings['cityheaven_password']) ?>" required
                           style="padding-right: 50px;">
                    <button type="button" class="password-toggle" onclick="togglePassword()">
                        <i class="fas fa-eye" id="password-toggle-icon"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">写メ日記ページURL <span style="color: var(--danger);">*</span></label>
            <input type="url" name="shop_url" class="form-control" 
                   value="<?= h($settings['shop_url']) ?>" required
                   placeholder="https://www.cityheaven.net/地域/エリア/店舗名/diarylist/">
            <div class="help-text">CityHeavenの写メ日記一覧ページのURLを入力</div>
        </div>
    </div>

    <!-- 取得設定 -->
    <div class="content-card">
        <div class="card-section-title">
            <i class="fas fa-sliders-h"></i> 取得設定
        </div>
        
        <div class="form-grid-2">
            <div class="form-group">
                <label class="form-label">最大ページ数</label>
                <input type="number" name="max_pages" class="form-control" 
                       value="<?= h($settings['max_pages']) ?>" min="1" max="100">
                <div class="help-text">1回の実行で取得するページ数上限</div>
            </div>
            <div class="form-group">
                <label class="form-label">最大保存件数</label>
                <input type="number" name="max_posts_per_tenant" class="form-control" 
                       value="<?= h($settings['max_posts_per_tenant']) ?>" min="100" max="10000">
                <div class="help-text">超過分は古い投稿から自動削除</div>
            </div>
        </div>
    </div>

    <div style="display: flex; align-items: center; margin-top: 24px;">
        <a href="index.php?tenant=<?= h($tenantSlug) ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> 戻る
        </a>
        <div style="flex: 1; text-align: center;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> 保存
            </button>
        </div>
    </div>
</form>

<style>
.password-toggle {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    padding: 0;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color var(--transition-fast);
}
.password-toggle:hover {
    color: var(--primary);
}
</style>

<script>
function togglePassword() {
    var passwordInput = document.getElementById('ch-password');
    var toggleIcon = document.getElementById('password-toggle-icon');
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
