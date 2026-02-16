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

<!-- スクレイピング実行中オーバーレイ -->
<div id="scraping-overlay" class="scraping-overlay">
    <div class="scraping-overlay-content">
        <div class="scraping-spinner">
            <i class="fas fa-sync-alt fa-spin"></i>
        </div>
        <div class="scraping-overlay-title" id="overlay-title">スクレイピング実行中…</div>
        <div class="scraping-overlay-stats">
            <span>通常 <strong id="ol-normal">0</strong></span>
            <span class="ol-divider">/</span>
            <span>🎬 <strong id="ol-video">0</strong><span id="ol-video-mg" style="color: var(--text-secondary, #888);"></span></span>
            <span class="ol-divider">/</span>
            <span>🔓 <strong id="ol-mygirl">0</strong></span>
            <span class="ol-divider">|</span>
            <span>合計 <strong id="ol-saved">0</strong>件</span>
            <span class="ol-divider">/</span>
            <span id="ol-elapsed">00:00</span>
        </div>
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
                    <th style="text-align: center;">通常</th>
                    <th style="text-align: center;">🎬 動画（🔓限定）</th>
                    <th style="text-align: center;">🔓 限定</th>
                    <th style="text-align: center;">取得合計</th>
                    <th>時間</th>
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
                            <span class="badge badge-danger" title="<?= h($log['error_message'] ?? '') ?>">エラー</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: center;"><?= (int)$log['saved_normal'] ?></td>
                    <td style="text-align: center;"><?php
                        $videoTotal = (int)$log['saved_video'];
                        $videoMg = (int)$log['saved_video_mygirl'];
                        echo $videoTotal;
                        if ($videoMg > 0) echo '<span style="color: var(--text-secondary, #888);">(' . $videoMg . ')</span>';
                    ?></td>
                    <td style="text-align: center;"><?= (int)$log['saved_mygirl'] ?></td>
                    <td style="text-align: center; font-weight: 600;"><?= (int)$log['posts_saved'] ?>件</td>
                    <td><?= $log['execution_time'] ? round($log['execution_time'], 0) . '秒' : '-' ?></td>
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

/* スクレイピング実行中オーバーレイ（コンテンツエリアのみ） */
.scraping-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: var(--sidebar-width, 260px);
    right: 0;
    bottom: 0;
    background: rgba(255, 255, 255, 0.4);
    backdrop-filter: blur(4px);
    -webkit-backdrop-filter: blur(4px);
    z-index: 90;
    justify-content: center;
    align-items: center;
}
@media (max-width: 768px) {
    .scraping-overlay {
        left: 0;
    }
}
.scraping-overlay.show {
    display: flex;
}
.scraping-overlay-content {
    text-align: center;
    color: var(--text-primary, #333);
    user-select: none;
}
.scraping-spinner {
    font-size: 3.5rem;
    margin-bottom: 20px;
    color: var(--primary);
    animation: spin-pulse 1.5s ease-in-out infinite;
}
@keyframes spin-pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.6; transform: scale(1.05); }
}
.scraping-overlay-title {
    font-size: 1.6rem;
    font-weight: 700;
    margin-bottom: 16px;
    color: var(--text-primary, #333);
}
.scraping-overlay-stats {
    font-size: 1.05rem;
    color: var(--text-secondary, #666);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    flex-wrap: wrap;
}
.scraping-overlay-stats strong {
    font-size: 1.2rem;
}
.ol-divider {
    opacity: 0.4;
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

// === スクレイピング実行 & オーバーレイ制御 ===
let isManualExecution = false;
let overlayStartTime = null;
let elapsedTimer = null;

function showOverlay(title) {
    document.getElementById('overlay-title').textContent = title;
    document.getElementById('ol-normal').textContent = '0';
    document.getElementById('ol-video').textContent = '0';
    document.getElementById('ol-video-mg').textContent = '';
    document.getElementById('ol-mygirl').textContent = '0';
    document.getElementById('ol-saved').textContent = '0';
    document.getElementById('ol-elapsed').textContent = '00:00';
    document.getElementById('scraping-overlay').classList.add('show');
    
    overlayStartTime = Date.now();
    if (elapsedTimer) clearInterval(elapsedTimer);
    elapsedTimer = setInterval(updateOverlayElapsed, 1000);
}

function hideOverlay() {
    document.getElementById('scraping-overlay').classList.remove('show');
    if (elapsedTimer) clearInterval(elapsedTimer);
    elapsedTimer = null;
    overlayStartTime = null;
    isManualExecution = false;
}

function updateOverlayElapsed() {
    if (!overlayStartTime) return;
    var elapsed = Math.floor((Date.now() - overlayStartTime) / 1000);
    var m = Math.floor(elapsed / 60);
    var s = elapsed % 60;
    document.getElementById('ol-elapsed').textContent =
        m.toString().padStart(2, '0') + ':' + s.toString().padStart(2, '0');
}

function updateOverlayStats(data) {
    document.getElementById('ol-normal').textContent = data.saved_normal || 0;
    document.getElementById('ol-video').textContent = data.saved_video || 0;
    var vmg = data.saved_video_mygirl || 0;
    document.getElementById('ol-video-mg').textContent = vmg > 0 ? '(' + vmg + ')' : '';
    document.getElementById('ol-mygirl').textContent = data.saved_mygirl || 0;
    document.getElementById('ol-saved').textContent = data.posts_saved || 0;
}

async function executeManual() {
    // 実行中チェック
    try {
        var checkRes = await fetch('status.php?tenant=<?= h($tenantSlug) ?>&t=' + Date.now());
        var checkData = await checkRes.json();
        if (checkData.status === 'running') {
            alert('現在スクレイピングが実行中です。完了後に再度お試しください。');
            return;
        }
    } catch (e) {}
    
    if (!confirm('写メ日記の取得を開始しますか？')) return;
    
    isManualExecution = true;
    showOverlay('手動スクレイピング実行中…');
    
    try {
        var response = await fetch('execute.php?tenant=<?= h($tenantSlug) ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type: 'manual' })
        });
        var result = await response.json();
        
        if (!result.success) {
            alert('エラー: ' + (result.error || '実行開始に失敗しました'));
            hideOverlay();
        }
        // ポーリングが自動で検知するのでここでは何もしない
        
    } catch (error) {
        alert('通信エラー: ' + error.message);
        hideOverlay();
    }
}

// === 統合ポーリング（手動 + 定期を一元管理） ===
const POLL_IDLE = 5000;
const POLL_RUNNING = 2000;
let prevStatus = 'idle';
let lastSeenLogId = null;

async function pollStatus() {
    try {
        var response = await fetch('status.php?tenant=<?= h($tenantSlug) ?>&t=' + Date.now());
        var data = await response.json();
        var overlay = document.getElementById('scraping-overlay');
        var currentLogId = data.log_id || 0;
        
        if (data.status === 'running') {
            // 実行中 → オーバーレイ表示
            if (!overlay.classList.contains('show')) {
                showOverlay(isManualExecution ? '手動スクレイピング実行中…' : '定期スクレイピング実行中…');
            }
            updateOverlayStats(data);
            prevStatus = 'running';
            lastSeenLogId = currentLogId;
            setTimeout(pollStatus, POLL_RUNNING);
            
        } else if (prevStatus === 'running' && (data.status === 'completed' || data.status === 'idle')) {
            // running → 完了（実行中を検知していた場合）
            document.getElementById('overlay-title').textContent = '完了！ ' + (data.posts_saved || 0) + '件保存';
            document.getElementById('scraping-overlay').querySelector('.scraping-spinner i').className = 'fas fa-check-circle';
            
            prevStatus = 'idle';
            lastSeenLogId = currentLogId;
            setTimeout(function() {
                hideOverlay();
                location.reload();
            }, 1500);
            
        } else if (prevStatus === 'running' && data.status === 'error') {
            // running → エラー
            document.getElementById('overlay-title').textContent = 'エラーが発生しました';
            document.getElementById('scraping-overlay').querySelector('.scraping-spinner i').className = 'fas fa-exclamation-circle';
            
            prevStatus = 'idle';
            lastSeenLogId = currentLogId;
            setTimeout(function() {
                hideOverlay();
                location.reload();
            }, 2500);
            
        } else if (lastSeenLogId !== null && currentLogId !== lastSeenLogId && data.status === 'completed') {
            // ポーリング間隔内に完了した定期実行を検知（running状態を見逃した場合）
            lastSeenLogId = currentLogId;
            showOverlay('定期スクレイピング完了！');
            document.getElementById('overlay-title').textContent = '完了！ ' + (data.posts_saved || 0) + '件保存';
            document.getElementById('scraping-overlay').querySelector('.scraping-spinner i').className = 'fas fa-check-circle';
            
            prevStatus = 'idle';
            setTimeout(function() {
                hideOverlay();
                location.reload();
            }, 1500);
            
        } else {
            // アイドル状態
            if (overlay.classList.contains('show') && !isManualExecution) {
                hideOverlay();
            }
            // 初期化: 現在のlog_idを記録
            if (lastSeenLogId === null && currentLogId > 0) {
                lastSeenLogId = currentLogId;
            }
            prevStatus = 'idle';
            setTimeout(pollStatus, POLL_IDLE);
        }
        
    } catch (error) {
        setTimeout(pollStatus, POLL_IDLE);
    }
}

// ページ読み込み時にポーリング開始
document.addEventListener('DOMContentLoaded', function() {
    pollStatus();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
