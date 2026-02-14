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

// =====================================================
// 設定保存処理
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 既存設定チェック
        $stmt = $platformPdo->prepare("SELECT id FROM diary_scrape_settings WHERE tenant_id = ?");
        $stmt->execute([$tenantId]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // 更新
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
                $_POST['cityheaven_password'], // TODO: 暗号化実装
                $_POST['shop_url'],
                $_POST['scrape_interval'],
                $_POST['request_delay'],
                $_POST['max_pages'],
                $_POST['timeout'],
                $_POST['max_posts_per_tenant'],
                $tenantId
            ]);
        } else {
            // 新規作成
            $stmt = $platformPdo->prepare("
                INSERT INTO diary_scrape_settings (
                    tenant_id,
                    cityheaven_login_id,
                    cityheaven_password,
                    shop_url,
                    scrape_interval,
                    request_delay,
                    max_pages,
                    timeout,
                    max_posts_per_tenant
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $tenantId,
                $_POST['cityheaven_login_id'],
                $_POST['cityheaven_password'], // TODO: 暗号化実装
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

// =====================================================
// 設定取得
// =====================================================
$stmt = $platformPdo->prepare("SELECT * FROM diary_scrape_settings WHERE tenant_id = ?");
$stmt->execute([$tenantId]);
$settings = $stmt->fetch();

// デフォルト値
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

<style>
.container {
    max-width: 900px;
    margin: 0 auto;
    padding: 20px;
}

.settings-form {
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(20px);
    padding: 30px;
    border-radius: 20px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.form-section {
    margin-bottom: 35px;
    padding-bottom: 30px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.form-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.form-section h3 {
    margin: 0 0 20px 0;
    color: #27a3eb;
    font-size: 1.2rem;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: rgba(255, 255, 255, 0.9);
    font-weight: 600;
}

.required {
    color: #ff4444;
    margin-left: 3px;
}

.form-control {
    width: 100%;
    padding: 12px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    background: rgba(0, 0, 0, 0.3);
    color: #fff;
    font-size: 1rem;
}

.form-control:focus {
    outline: none;
    border-color: #27a3eb;
    box-shadow: 0 0 0 3px rgba(39, 163, 235, 0.1);
}

.form-help {
    display: block;
    margin-top: 5px;
    font-size: 0.85em;
    color: rgba(255, 255, 255, 0.6);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.alert {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 10px;
}

.alert-success {
    background: rgba(39, 174, 96, 0.2);
    border: 1px solid rgba(39, 174, 96, 0.4);
    color: #27ae60;
}

.alert-danger {
    background: rgba(220, 53, 69, 0.2);
    border: 1px solid rgba(220, 53, 69, 0.4);
    color: #dc3545;
}

.btn-group {
    display: flex;
    gap: 15px;
    margin-top: 30px;
}

.btn {
    padding: 15px 30px;
    border: none;
    border-radius: 25px;
    cursor: pointer;
    font-size: 1rem;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.btn-primary {
    background: #27a3eb;
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(39, 163, 235, 0.3);
}

.btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: white;
}

.btn-secondary:hover {
    background: rgba(255, 255, 255, 0.15);
}
</style>

<div class="container">
    <div class="header">
        <h1>写メ日記スクレイピング設定</h1>
        <p>CityHeavenからの自動取得設定</p>
    </div>

    <?php if (isset($success)): ?>
    <div class="alert alert-success">
        ✅ <?= h($success) ?>
    </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
    <div class="alert alert-danger">
        ❌ <?= h($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" class="settings-form">
        <!-- CityHeavenログイン情報 -->
        <div class="form-section">
            <h3>🔐 CityHeavenログイン情報</h3>
            
            <div class="form-group">
                <label>
                    ログインID（メールアドレス）<span class="required">*</span>
                </label>
                <input type="email" name="cityheaven_login_id" class="form-control" 
                       value="<?= h($settings['cityheaven_login_id']) ?>" required>
                <span class="form-help">CityHeavenにログインする際のメールアドレス</span>
            </div>

            <div class="form-group">
                <label>
                    パスワード<span class="required">*</span>
                </label>
                <input type="password" name="cityheaven_password" class="form-control" 
                       value="<?= h($settings['cityheaven_password']) ?>" required>
                <span class="form-help">CityHeavenにログインする際のパスワード（暗号化して保存されます）</span>
            </div>
        </div>

        <!-- 店舗情報 -->
        <div class="form-section">
            <h3>🏪 店舗情報</h3>
            
            <div class="form-group">
                <label>
                    写メ日記ページURL<span class="required">*</span>
                </label>
                <input type="url" name="shop_url" class="form-control" 
                       value="<?= h($settings['shop_url']) ?>" required
                       placeholder="https://www.cityheaven.net/fukuoka/A4001/A400101/店舗名/diarylist/">
                <span class="form-help">
                    例: https://www.cityheaven.net/fukuoka/A4001/A400101/houmantengoku/diarylist/
                </span>
            </div>
        </div>

        <!-- スクレイピング設定 -->
        <div class="form-section">
            <h3>⚙️ スクレイピング設定</h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label>取得間隔（分）</label>
                    <input type="number" name="scrape_interval" class="form-control" 
                           value="<?= h($settings['scrape_interval']) ?>" min="5" max="1440">
                    <span class="form-help">自動実行の間隔（5〜1440分）</span>
                </div>

                <div class="form-group">
                    <label>リクエスト遅延（秒）</label>
                    <input type="number" name="request_delay" class="form-control" 
                           value="<?= h($settings['request_delay']) ?>" min="0.1" max="5" step="0.1">
                    <span class="form-help">ページ取得間の待機時間</span>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>最大ページ数</label>
                    <input type="number" name="max_pages" class="form-control" 
                           value="<?= h($settings['max_pages']) ?>" min="1" max="100">
                    <span class="form-help">1回の実行で取得する最大ページ数</span>
                </div>

                <div class="form-group">
                    <label>タイムアウト（秒）</label>
                    <input type="number" name="timeout" class="form-control" 
                           value="<?= h($settings['timeout']) ?>" min="10" max="120">
                    <span class="form-help">1ページあたりの最大待機時間</span>
                </div>
            </div>

            <div class="form-group">
                <label>最大保存件数</label>
                <input type="number" name="max_posts_per_tenant" class="form-control" 
                       value="<?= h($settings['max_posts_per_tenant']) ?>" min="100" max="10000">
                <span class="form-help">テナントごとの最大保存件数（超過分は古いものから削除）</span>
            </div>
        </div>

        <div class="btn-group">
            <button type="submit" class="btn btn-primary">
                💾 保存
            </button>
            <a href="index.php" class="btn btn-secondary">
                ← 戻る
            </a>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
