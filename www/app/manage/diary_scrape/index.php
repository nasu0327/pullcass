<?php
/**
 * 写メ日記スクレイピング管理画面
 */

require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

$pageTitle = '写メ日記スクレイピング管理';
$currentPage = 'diary_scrape';

$platformPdo = getPlatformDb();
$tenantId = $tenant['id'];
$tenantCode = $tenant['code'];

$configSuccess = '';
$configError = '';

// 設定保存処理（モーダルからのAJAX）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    header('Content-Type: application/json');
    try {
        $loginId = trim($_POST['cityheaven_login_id'] ?? '');
        $password = trim($_POST['cityheaven_password'] ?? '');
        $shopUrl = trim($_POST['shop_url'] ?? '');
        
        if (empty($loginId) || empty($password) || empty($shopUrl)) {
            echo json_encode(['success' => false, 'error' => '全ての項目を入力してください']);
            exit;
        }
        
        // 固定値
        $fixedInterval = 10;
        $fixedDelay = 0.5;
        $fixedTimeout = 30;
        $fixedMaxPages = 50;
        $fixedMaxPosts = 500; // キャスト単位で管理するため、テナント全体値は参考値

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
            $stmt->execute([$loginId, $password, $shopUrl, $fixedInterval, $fixedDelay, $fixedMaxPages, $fixedTimeout, $fixedMaxPosts, $tenantId]);
        } else {
            $stmt = $platformPdo->prepare("
                INSERT INTO diary_scrape_settings (
                    tenant_id, cityheaven_login_id, cityheaven_password,
                    shop_url, scrape_interval, request_delay,
                    max_pages, timeout, max_posts_per_tenant
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$tenantId, $loginId, $password, $shopUrl, $fixedInterval, $fixedDelay, $fixedMaxPages, $fixedTimeout, $fixedMaxPosts]);
        }
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
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
        'is_enabled' => 0,
        'scrape_interval' => 10,
        'last_executed_at' => null,
        'last_execution_status' => null,
        'total_posts_scraped' => 0,
        'last_posts_count' => 0,
    ];
}

// 統計情報取得
$stmt = $platformPdo->prepare("SELECT COUNT(*) as total FROM diary_posts WHERE tenant_id = ?");
$stmt->execute([$tenantId]);
$totalPosts = $stmt->fetch()['total'];

$stmt = $platformPdo->prepare("SELECT COUNT(*) as today FROM diary_posts WHERE tenant_id = ? AND DATE(created_at) = CURDATE()");
$stmt->execute([$tenantId]);
$todayPosts = $stmt->fetch()['today'];

// 最新投稿
$stmt = $platformPdo->prepare("
    SELECT title, cast_name, posted_at, created_at
    FROM diary_posts WHERE tenant_id = ?
    ORDER BY posted_at DESC LIMIT 10
");
$stmt->execute([$tenantId]);
$latestPosts = $stmt->fetchAll();

// 実行履歴
$stmt = $platformPdo->prepare("
    SELECT * FROM diary_scrape_logs 
    WHERE tenant_id = ? ORDER BY started_at DESC LIMIT 10
");
$stmt->execute([$tenantId]);
$executionHistory = $stmt->fetchAll();

$hasConfig = !empty($settings['cityheaven_login_id']) && !empty($settings['shop_url']);

include __DIR__ . '/../includes/header.php';
?>

<?php
require_once __DIR__ . '/../includes/breadcrumb.php';
$breadcrumbs = [
    ['label' => 'ダッシュボード', 'url' => '/app/manage/?tenant=' . $tenantSlug, 'icon' => 'fas fa-chart-pie'],
    ['label' => '写メ日記スクレイピング管理']
];
renderBreadcrumb($breadcrumbs);
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-camera"></i> 写メ日記スクレイピング管理</h1>
        <p>CityHeavenから写メ日記を自動取得・管理します</p>
    </div>
    <div class="scrape-status-badge" id="scrape-status-badge" style="display: none;">
        <i class="fas fa-sync-alt fa-spin"></i>
        <span id="scrape-status-text">取得中...</span>
    </div>
</div>

<div style="display: flex; gap: 15px; flex-wrap: wrap; justify-content: center; align-items: center; margin-bottom: 20px;">
    <button type="button" class="switch-button" id="btn-manual" onclick="executeManual()" <?= !$hasConfig ? 'disabled' : '' ?> style="background: var(--primary-gradient); min-width: 220px; justify-content: center;">
        <i class="fas fa-play"></i> 手動実行
    </button>
    <button type="button" class="switch-button" onclick="openConfigModal()" style="background: var(--primary-gradient); min-width: 220px; justify-content: center;">
        <i class="fas fa-cog"></i> スクレイピング設定
    </button>
</div>

<div class="auto-toggle-area">
    <span class="auto-toggle-label">定期実行（10分間隔）</span>
    <label class="toggle-switch" <?= !$hasConfig ? 'style="opacity:0.5;pointer-events:none;"' : '' ?>>
        <input type="checkbox" id="auto-toggle-checkbox" <?= $settings['is_enabled'] ? 'checked' : '' ?> onchange="toggleAutoScrape(this.checked)">
        <span class="slider round"></span>
    </label>
    <span class="auto-toggle-status" id="auto-toggle-status"><?= $settings['is_enabled'] ? 'ON' : 'OFF' ?></span>
</div>

<?php if (!$hasConfig): ?>
<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle"></i> 設定が未完了です。「スクレイピング設定」からCityHeavenのログイン情報と店舗URLを設定してください。
</div>
<?php endif; ?>

<!-- 進捗表示エリア -->
<div class="content-card" id="progress-area" style="display: none;">
    <div class="card-section-title">
        <i class="fas fa-spinner fa-spin"></i>
        <span id="progress-title">スクレイピング実行中...</span>
    </div>
    <div class="progress-counters">
        <div class="progress-counter-item progress-counter-item--success">
            <div class="progress-counter-value progress-counter-value--success" id="item-counter">0</div>
            <div class="progress-counter-label">保存件数</div>
        </div>
        <div class="progress-counter-item progress-counter-item--accent">
            <div class="progress-counter-value progress-counter-value--accent" id="found-counter">0</div>
            <div class="progress-counter-label">検出件数</div>
        </div>
        <div class="progress-counter-item progress-counter-item--primary">
            <div class="progress-counter-value progress-counter-value--primary" id="page-counter">0</div>
            <div class="progress-counter-label">処理ページ</div>
        </div>
        <div class="progress-counter-item progress-counter-item--muted">
            <div class="progress-counter-value progress-counter-value--muted" id="elapsed-time">00:00</div>
            <div class="progress-counter-label">経過時間</div>
        </div>
    </div>
    <div style="text-align: center;">
        <button onclick="emergencyStop()" class="btn btn-danger btn-sm">
            <i class="fas fa-stop"></i> 停止
        </button>
    </div>
</div>

<!-- 統計カード -->
<div class="stat-grid-3">
    <div class="stat-card">
        <div class="stat-card-header">
            <i class="fas fa-chart-bar"></i> 投稿統計
        </div>
        <div class="stat-card-value"><?= number_format($totalPosts) ?></div>
        <div class="stat-card-label">累計投稿数</div>
        <div class="stat-card-sub">
            今日: <strong><?= $todayPosts ?>件</strong>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <i class="fas fa-clock"></i> 実行状態
        </div>
        <?php if ($settings['last_executed_at']): ?>
            <div class="stat-card-row">
                <div class="stat-card-row-label">最終実行</div>
                <div class="stat-card-row-value">
                    <?= date('Y/m/d H:i', strtotime($settings['last_executed_at'])) ?>
                </div>
            </div>
            <div class="stat-card-row">
                <div class="stat-card-row-label">結果</div>
                <div class="stat-card-row-value">
                    <?php if ($settings['last_execution_status'] === 'success'): ?>
                        <span class="badge badge-success">成功（<?= $settings['last_posts_count'] ?>件）</span>
                    <?php elseif ($settings['last_execution_status'] === 'error'): ?>
                        <span class="badge badge-danger">エラー</span>
                    <?php else: ?>
                        <span class="badge badge-warning">実行中</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="stat-card-label">まだ実行されていません</div>
        <?php endif; ?>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <i class="fas fa-cog"></i> 設定情報
        </div>
        <div class="stat-card-row">
            <div class="stat-card-row-label">ログインID</div>
            <div class="stat-card-row-value">
                <?= $hasConfig ? h($settings['cityheaven_login_id']) : '<span class="badge badge-danger">未設定</span>' ?>
            </div>
        </div>
        <div class="stat-card-row">
            <div class="stat-card-row-label">店舗URL</div>
            <div class="stat-card-row-value">
                <?= !empty($settings['shop_url']) ? h($settings['shop_url']) : '<span class="badge badge-danger">未設定</span>' ?>
            </div>
        </div>
    </div>
</div>

<!-- 最新投稿 -->
<?php if (!empty($latestPosts)): ?>
<div class="content-card">
    <div class="card-section-title">
        <i class="fas fa-list"></i> 最新投稿
    </div>
    <div style="overflow-x: auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>タイトル</th>
                    <th>キャスト</th>
                    <th>投稿日時</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($latestPosts as $post): ?>
                <tr>
                    <td><?= h($post['title'] ?: '(タイトルなし)') ?></td>
                    <td><?= h($post['cast_name']) ?></td>
                    <td><?= date('Y/m/d H:i', strtotime($post['posted_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- 実行履歴 -->
<?php if (!empty($executionHistory)): ?>
<div class="content-card">
    <div class="card-section-title">
        <i class="fas fa-history"></i> 実行履歴
    </div>
    <div style="overflow-x: auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>実行日時</th>
                    <th>タイプ</th>
                    <th>結果</th>
                    <th>検出</th>
                    <th>保存</th>
                    <th>スキップ</th>
                    <th>実行時間</th>
                    <th>エラー</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($executionHistory as $log): ?>
                <tr>
                    <td><?= date('Y/m/d H:i', strtotime($log['started_at'])) ?></td>
                    <td>
                        <?php if ($log['execution_type'] === 'manual'): ?>
                            <span class="badge badge-primary">手動</span>
                        <?php else: ?>
                            <span class="badge badge-info">自動</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($log['status'] === 'success'): ?>
                            <span class="badge badge-success">成功</span>
                        <?php elseif ($log['status'] === 'running'): ?>
                            <span class="badge badge-warning">実行中</span>
                        <?php else: ?>
                            <span class="badge badge-danger">エラー</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $log['posts_found'] ?>件</td>
                    <td><?= $log['posts_saved'] ?>件</td>
                    <td><?= $log['posts_skipped'] ?>件</td>
                    <td><?= $log['execution_time'] ? round($log['execution_time'], 1) . '秒' : '-' ?></td>
                    <td class="cell-truncate">
                        <?= $log['error_message'] ? h($log['error_message']) : '-' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- 設定モーダル -->
<div id="configModal" class="setting-modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">
                <i class="fas fa-cog" style="color: var(--primary);"></i>
                <span>CityHeaven接続設定</span>
            </div>
            <button type="button" class="modal-close" onclick="closeConfigModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="modal-field">
                <label>ログインID（メールアドレス）</label>
                <input type="email" id="modal-login-id" placeholder="example@email.com"
                       value="<?= h($settings['cityheaven_login_id']) ?>">
            </div>
            <div class="modal-field">
                <label>パスワード</label>
                <div style="position: relative;">
                    <input type="password" id="modal-password" placeholder="パスワード"
                           value="<?= h($settings['cityheaven_password']) ?>"
                           style="padding-right: 50px;">
                    <button type="button" class="password-toggle" onclick="toggleModalPassword()">
                        <i class="fas fa-eye" id="modal-pw-icon"></i>
                    </button>
                </div>
            </div>
            <div class="modal-field">
                <label>写メ日記ページURL</label>
                <input type="url" id="modal-shop-url"
                       placeholder="https://www.cityheaven.net/地域/エリア/店舗名/diarylist/"
                       value="<?= h($settings['shop_url']) ?>">
            </div>
            <div class="modal-alert">
                <i class="fas fa-exclamation-triangle"></i>
                マイガール限定の投稿も解除した状態で反映させるために、必ず上記で登録するアカウントでキャスト全員をマイガール登録願いします。
            </div>
            <div class="modal-validation" id="config-validation"></div>
            <div class="modal-actions">
                <button type="button" class="modal-btn save" onclick="saveConfig()">
                    <i class="fas fa-save"></i> 保存
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.switch-button {
    background: var(--primary-gradient);
    color: var(--text-inverse);
    border: none;
    padding: 15px 40px;
    border-radius: 30px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all var(--transition-base);
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.switch-button:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}
.switch-button:disabled {
    background: var(--text-muted);
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

/* ステータスバッジ */
.scrape-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: 30px;
    font-size: 0.9rem;
    font-weight: 600;
    white-space: nowrap;
    animation: pulse-glow 2s ease-in-out infinite;
}
.scrape-status-badge.running {
    background: var(--primary-bg, rgba(59,130,246,0.1));
    color: var(--primary);
    border: 1px solid var(--primary-border, rgba(59,130,246,0.3));
}
.scrape-status-badge.completed {
    background: var(--success-bg, rgba(34,197,94,0.1));
    color: var(--success);
    border: 1px solid var(--success-border, rgba(34,197,94,0.3));
}
.scrape-status-badge.error {
    background: var(--danger-bg, rgba(239,68,68,0.1));
    color: var(--danger);
    border: 1px solid var(--danger-border, rgba(239,68,68,0.3));
}
@keyframes pulse-glow {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}

/* 定期実行トグルエリア */
.auto-toggle-area {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 14px;
    margin-bottom: 20px;
    padding: 14px 24px;
    background: var(--bg-card);
    border-radius: 12px;
    box-shadow: var(--shadow-card);
}
.auto-toggle-label {
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text-primary);
}
.auto-toggle-status {
    font-size: 0.9rem;
    font-weight: 700;
    min-width: 30px;
}
#auto-toggle-status {
    color: var(--text-muted);
}
/* ON状態のstatusの色はJSで制御 */

/* トグルスイッチ */
.toggle-switch {
    position: relative;
    display: inline-block;
    width: 52px;
    height: 28px;
    flex-shrink: 0;
}
.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}
.toggle-switch .slider {
    position: absolute;
    cursor: pointer;
    top: 0; left: 0; right: 0; bottom: 0;
    background: var(--text-muted, #ccc);
    transition: 0.3s;
}
.toggle-switch .slider.round {
    border-radius: 28px;
}
.toggle-switch .slider.round::before {
    border-radius: 50%;
}
.toggle-switch .slider::before {
    position: absolute;
    content: "";
    height: 22px;
    width: 22px;
    left: 3px;
    bottom: 3px;
    background: white;
    transition: 0.3s;
    border-radius: 50%;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}
.toggle-switch input:checked + .slider {
    background: var(--success, #28a745);
}
.toggle-switch input:checked + .slider::before {
    transform: translateX(24px);
}

/* 設定モーダル（cast_dataと同パターン） */
.setting-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 9999;
    justify-content: center;
    align-items: center;
}
.setting-modal.show {
    display: flex;
}
.modal-content {
    background: var(--bg-card);
    border-radius: 16px;
    width: 90%;
    max-width: 520px;
    box-shadow: var(--shadow-lg);
    overflow: hidden;
}
.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 18px 20px;
    border-bottom: 1px solid var(--border-color);
}
.modal-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 700;
    font-size: 1.05rem;
    color: var(--text-primary);
}
.modal-close {
    background: transparent;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    width: 36px;
    height: 36px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    transition: all var(--transition-fast);
}
.modal-close:hover {
    background: var(--bg-body);
    color: var(--text-primary);
}
.modal-body {
    padding: 20px;
}
.modal-field {
    margin-bottom: 15px;
}
.modal-field label {
    display: block;
    font-size: 0.85rem;
    color: var(--text-muted);
    margin-bottom: 8px;
    font-weight: 500;
}
.modal-field input {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid var(--border-color);
    border-radius: 10px;
    font-size: 0.95rem;
    color: var(--text-primary);
    background: var(--bg-input);
    box-sizing: border-box;
    transition: border-color var(--transition-fast);
}
.modal-field input:focus {
    outline: none;
    border-color: var(--primary);
}
.modal-alert {
    background: var(--warning-bg, rgba(234,88,12,0.08));
    color: var(--warning);
    padding: 12px 16px;
    border-radius: 10px;
    font-size: 0.82rem;
    line-height: 1.5;
    margin-bottom: 15px;
}
.modal-alert i {
    margin-right: 6px;
}
.modal-validation {
    min-height: 24px;
    margin-bottom: 15px;
    font-size: 0.85rem;
    padding: 8px 12px;
    border-radius: 8px;
}
.modal-validation:empty { display: none; }
.modal-validation.valid {
    display: block;
    background: var(--success-bg);
    color: var(--success);
}
.modal-validation.invalid {
    display: block;
    background: var(--danger-bg);
    color: var(--danger);
}
.modal-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}
.modal-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}
.modal-btn.save {
    background: var(--primary-gradient);
    color: var(--text-inverse);
}
.modal-btn.save:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}
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
// === 設定モーダル ===
function openConfigModal() {
    document.getElementById('configModal').classList.add('show');
    document.getElementById('config-validation').className = 'modal-validation';
    document.getElementById('config-validation').textContent = '';
}

function closeConfigModal() {
    document.getElementById('configModal').classList.remove('show');
}

// モーダル外クリックで閉じる
document.getElementById('configModal').addEventListener('click', function(e) {
    if (e.target === this) closeConfigModal();
});

// ESCキーで閉じる
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeConfigModal();
});

function toggleModalPassword() {
    var input = document.getElementById('modal-password');
    var icon = document.getElementById('modal-pw-icon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

async function saveConfig() {
    var loginId = document.getElementById('modal-login-id').value.trim();
    var password = document.getElementById('modal-password').value.trim();
    var shopUrl = document.getElementById('modal-shop-url').value.trim();
    var validation = document.getElementById('config-validation');
    
    if (!loginId || !password || !shopUrl) {
        validation.className = 'modal-validation invalid';
        validation.textContent = '全ての項目を入力してください';
        return;
    }
    
    validation.className = 'modal-validation';
    validation.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 保存中...';
    validation.style.display = 'block';
    validation.style.background = 'var(--primary-bg)';
    validation.style.color = 'var(--primary)';
    
    try {
        var formData = new FormData();
        formData.append('save_config', '1');
        formData.append('cityheaven_login_id', loginId);
        formData.append('cityheaven_password', password);
        formData.append('shop_url', shopUrl);
        
        var response = await fetch('?tenant=<?= h($tenantSlug) ?>', {
            method: 'POST',
            body: formData
        });
        var result = await response.json();
        
        if (result.success) {
            validation.className = 'modal-validation valid';
            validation.textContent = '設定を保存しました';
            setTimeout(function() {
                location.reload();
            }, 800);
        } else {
            validation.className = 'modal-validation invalid';
            validation.textContent = result.error || '保存に失敗しました';
        }
    } catch (error) {
        validation.className = 'modal-validation invalid';
        validation.textContent = '通信エラー: ' + error.message;
    }
}

// === 定期実行 ON/OFF ===
let autoEnabled = <?= $settings['is_enabled'] ? 'true' : 'false' ?>;

async function toggleAutoScrape(checked) {
    var action = checked ? '定期実行を開始' : '定期実行を停止';
    if (!confirm(action + 'しますか？' + (checked ? '\n\n10分おきに自動取得されます' : ''))) {
        // キャンセル時はチェックボックスを元に戻す
        document.getElementById('auto-toggle-checkbox').checked = autoEnabled;
        return;
    }
    
    try {
        var response = await fetch('toggle.php?tenant=<?= h($tenantSlug) ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ enabled: checked })
        });
        var result = await response.json();
        
        if (result.success) {
            autoEnabled = checked;
            var statusEl = document.getElementById('auto-toggle-status');
            statusEl.textContent = checked ? 'ON' : 'OFF';
            statusEl.style.color = checked ? 'var(--success, #28a745)' : 'var(--text-muted)';
        } else {
            document.getElementById('auto-toggle-checkbox').checked = autoEnabled;
            alert('エラー: ' + (result.error || '更新に失敗しました'));
        }
    } catch (error) {
        document.getElementById('auto-toggle-checkbox').checked = autoEnabled;
        alert('通信エラー: ' + error.message);
    }
}

// 初期状態のステータス色
(function() {
    var statusEl = document.getElementById('auto-toggle-status');
    if (autoEnabled) statusEl.style.color = 'var(--success, #28a745)';
})();

// === スクレイピング実行 ===
let currentExecution = null;
let startTime = null;
let pollingInterval = null;
let elapsedInterval = null;

async function executeManual() {
    if (currentExecution) {
        alert('既に実行中です');
        return;
    }
    
    if (!confirm('写メ日記の取得を開始しますか？')) return;
    
    currentExecution = true;
    startTime = Date.now();
    
    document.getElementById('progress-area').style.display = 'block';
    document.getElementById('btn-manual').disabled = true;
    document.getElementById('item-counter').textContent = '0';
    document.getElementById('found-counter').textContent = '0';
    document.getElementById('page-counter').textContent = '0';
    document.getElementById('progress-title').textContent = 'スクレイピング実行中...';
    
    try {
        const response = await fetch('execute.php?tenant=<?= h($tenantSlug) ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type: 'manual' })
        });
        
        const result = await response.json();
        
        if (!result.success) {
            alert('エラー: ' + (result.error || '実行開始に失敗しました'));
            hideProgress();
            return;
        }
        
        pollingInterval = setInterval(pollProgress, 2000);
        elapsedInterval = setInterval(updateElapsedTime, 1000);
        
    } catch (error) {
        alert('通信エラー: ' + error.message);
        hideProgress();
    }
}

async function pollProgress() {
    try {
        const response = await fetch('status.php?tenant=<?= h($tenantSlug) ?>');
        const data = await response.json();
        
        if (data.posts_saved !== undefined) {
            document.getElementById('item-counter').textContent = data.posts_saved;
        }
        if (data.posts_found !== undefined) {
            document.getElementById('found-counter').textContent = data.posts_found;
        }
        if (data.pages_processed !== undefined) {
            document.getElementById('page-counter').textContent = data.pages_processed;
        }
        
        if (data.status === 'completed' || data.status === 'idle') {
            clearInterval(pollingInterval);
            clearInterval(elapsedInterval);
            document.getElementById('progress-title').textContent = '完了！';
            
            setTimeout(() => {
                alert('取得完了: ' + (data.posts_saved || 0) + '件保存');
                location.reload();
            }, 500);
        }
        
        if (data.status === 'error') {
            clearInterval(pollingInterval);
            clearInterval(elapsedInterval);
            document.getElementById('progress-title').textContent = 'エラーが発生しました';
            
            setTimeout(() => {
                location.reload();
            }, 2000);
        }
        
    } catch (error) {
        // ポーリングエラーは無視
    }
}

function updateElapsedTime() {
    if (!startTime) return;
    const elapsed = Math.floor((Date.now() - startTime) / 1000);
    const minutes = Math.floor(elapsed / 60);
    const seconds = elapsed % 60;
    document.getElementById('elapsed-time').textContent = 
        `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
}

async function emergencyStop() {
    if (!confirm('実行を停止しますか？')) return;
    
    try {
        await fetch('stop.php?tenant=<?= h($tenantSlug) ?>', { method: 'POST' });
        clearInterval(pollingInterval);
        clearInterval(elapsedInterval);
        alert('停止しました');
        location.reload();
    } catch (error) {
        alert('停止エラー: ' + error.message);
    }
}

function hideProgress() {
    document.getElementById('progress-area').style.display = 'none';
    document.getElementById('btn-manual').disabled = false;
    currentExecution = null;
    startTime = null;
}

// === バックグラウンド ステータスポーリング ===
const POLL_IDLE = 10000;    // アイドル時: 10秒
const POLL_RUNNING = 3000;  // 実行中: 3秒
let bgPreviousStatus = 'idle';

async function bgPollStatus() {
    try {
        var response = await fetch('status.php?tenant=<?= h($tenantSlug) ?>&t=' + Date.now());
        var data = await response.json();
        var badge = document.getElementById('scrape-status-badge');
        var text = document.getElementById('scrape-status-text');
        var btnManual = document.getElementById('btn-manual');
        
        if (data.status === 'running') {
            // 実行中
            badge.style.display = 'inline-flex';
            badge.className = 'scrape-status-badge running';
            var saved = data.posts_saved || 0;
            var found = data.posts_found || 0;
            var pages = data.pages_processed || 0;
            text.textContent = '取得中… ' + saved + '件保存 / ' + found + '件検出 / ' + pages + 'ページ';
            badge.querySelector('i').className = 'fas fa-sync-alt fa-spin';
            
            // 手動実行ボタンを無効化（手動でもcronでも）
            btnManual.disabled = true;
            
            // 手動実行中でなくても進捗エリアを表示（cron実行検知）
            if (!currentExecution) {
                document.getElementById('progress-area').style.display = 'block';
                document.getElementById('progress-title').textContent = '定期スクレイピング実行中...';
                document.getElementById('item-counter').textContent = saved;
                document.getElementById('found-counter').textContent = found;
                document.getElementById('page-counter').textContent = pages;
            }
            
            setTimeout(bgPollStatus, POLL_RUNNING);
            
        } else if (data.status === 'completed' && bgPreviousStatus === 'running') {
            // 実行完了（running → completed に変わった瞬間）
            badge.style.display = 'inline-flex';
            badge.className = 'scrape-status-badge completed';
            badge.querySelector('i').className = 'fas fa-check-circle';
            text.textContent = '完了: ' + (data.posts_saved || 0) + '件保存';
            
            btnManual.disabled = false;
            
            // cron実行の完了 → 進捗エリアを非表示にしてリロード
            if (!currentExecution) {
                document.getElementById('progress-area').style.display = 'none';
                setTimeout(function() { location.reload(); }, 2000);
            }
            
            // 5秒後にバッジを非表示
            setTimeout(function() {
                badge.style.display = 'none';
            }, 5000);
            
            bgPreviousStatus = 'completed';
            setTimeout(bgPollStatus, POLL_IDLE);
            
        } else if (data.status === 'error' && bgPreviousStatus === 'running') {
            // エラー
            badge.style.display = 'inline-flex';
            badge.className = 'scrape-status-badge error';
            badge.querySelector('i').className = 'fas fa-exclamation-circle';
            text.textContent = 'エラー: ' + (data.error_message || '不明なエラー');
            
            btnManual.disabled = false;
            
            if (!currentExecution) {
                document.getElementById('progress-area').style.display = 'none';
                setTimeout(function() { location.reload(); }, 3000);
            }
            
            bgPreviousStatus = 'error';
            setTimeout(bgPollStatus, POLL_IDLE);
            
        } else {
            // アイドル
            badge.style.display = 'none';
            if (!currentExecution) {
                btnManual.disabled = <?= !$hasConfig ? 'true' : 'false' ?>;
            }
            bgPreviousStatus = data.status || 'idle';
            setTimeout(bgPollStatus, POLL_IDLE);
        }
        
    } catch (error) {
        setTimeout(bgPollStatus, POLL_IDLE);
    }
}

// ページ読み込み時にポーリング開始
document.addEventListener('DOMContentLoaded', function() {
    bgPollStatus();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
