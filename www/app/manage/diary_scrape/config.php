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
        
        $success = '設定を保存しました';
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

<div class="header-section">
    <h2><i class="fas fa-cog"></i> 写メ日記スクレイピング設定</h2>
    <p>CityHeavenからの自動取得設定</p>
</div>

<?php if (isset($success)): ?>
<div class="content-card" style="border-left: 4px solid var(--success);">
    <p style="color: var(--success-text); font-weight: 600;">
        <i class="fas fa-check-circle"></i> <?= h($success) ?>
    </p>
</div>
<?php endif; ?>

<?php if (isset($error)): ?>
<div class="content-card" style="border-left: 4px solid var(--danger);">
    <p style="color: var(--danger-text); font-weight: 600;">
        <i class="fas fa-times-circle"></i> <?= h($error) ?>
    </p>
</div>
<?php endif; ?>

<form method="POST">
    <!-- CityHeavenログイン情報 -->
    <div class="content-card">
        <h3 style="color: var(--primary); margin-bottom: 20px;">
            <i class="fas fa-key"></i> CityHeavenログイン情報
        </h3>
        
        <div class="form-group">
            <label class="form-label">
                ログインID（メールアドレス）<span style="color: var(--danger);">*</span>
            </label>
            <input type="email" name="cityheaven_login_id" class="form-control" 
                   value="<?= h($settings['cityheaven_login_id']) ?>" required>
            <small style="color: var(--text-muted); display: block; margin-top: 5px;">
                CityHeavenにログインする際のメールアドレス
            </small>
        </div>

        <div class="form-group">
            <label class="form-label">
                パスワード<span style="color: var(--danger);">*</span>
            </label>
            <input type="password" name="cityheaven_password" class="form-control" 
                   value="<?= h($settings['cityheaven_password']) ?>" required>
            <small style="color: var(--text-muted); display: block; margin-top: 5px;">
                CityHeavenにログインする際のパスワード
            </small>
        </div>
    </div>

    <!-- 店舗情報 -->
    <div class="content-card">
        <h3 style="color: var(--primary); margin-bottom: 20px;">
            <i class="fas fa-store"></i> 店舗情報
        </h3>
        
        <div class="form-group">
            <label class="form-label">
                写メ日記ページURL<span style="color: var(--danger);">*</span>
            </label>
            <input type="url" name="shop_url" class="form-control" 
                   value="<?= h($settings['shop_url']) ?>" required
                   placeholder="https://www.cityheaven.net/fukuoka/A4001/A400101/店舗名/diarylist/">
            <small style="color: var(--text-muted); display: block; margin-top: 5px;">
                例: https://www.cityheaven.net/fukuoka/A4001/A400101/houmantengoku/diarylist/
            </small>
        </div>
    </div>

    <!-- スクレイピング設定 -->
    <div class="content-card">
        <h3 style="color: var(--primary); margin-bottom: 20px;">
            <i class="fas fa-sliders-h"></i> スクレイピング設定
        </h3>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div class="form-group">
                <label class="form-label">取得間隔（分）</label>
                <input type="number" name="scrape_interval" class="form-control" 
                       value="<?= h($settings['scrape_interval']) ?>" min="5" max="1440">
                <small style="color: var(--text-muted); display: block; margin-top: 5px;">
                    自動実行の間隔（5〜1440分）
                </small>
            </div>

            <div class="form-group">
                <label class="form-label">リクエスト遅延（秒）</label>
                <input type="number" name="request_delay" class="form-control" 
                       value="<?= h($settings['request_delay']) ?>" min="0.1" max="5" step="0.1">
                <small style="color: var(--text-muted); display: block; margin-top: 5px;">
                    ページ取得間の待機時間
                </small>
            </div>

            <div class="form-group">
                <label class="form-label">最大ページ数</label>
                <input type="number" name="max_pages" class="form-control" 
                       value="<?= h($settings['max_pages']) ?>" min="1" max="100">
            </div>

            <div class="form-group">
                <label class="form-label">タイムアウト（秒）</label>
                <input type="number" name="timeout" class="form-control" 
                       value="<?= h($settings['timeout']) ?>" min="10" max="120">
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">最大保存件数</label>
            <input type="number" name="max_posts_per_tenant" class="form-control" 
                   value="<?= h($settings['max_posts_per_tenant']) ?>" min="100" max="10000">
            <small style="color: var(--text-muted); display: block; margin-top: 5px;">
                テナントごとの最大保存件数（超過分は古いものから削除）
            </small>
        </div>
    </div>

    <div style="display: flex; gap: 15px; margin-top: 20px;">
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save"></i> 保存
        </button>
        <a href="index.php?tenant=<?= h($tenantSlug) ?>" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> 戻る
        </a>
    </div>
</form>

<?php include __DIR__ . '/../includes/footer.php'; ?>
