<?php
/**
 * 口コミスクレイピング管理画面
 */

require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

$pageTitle = '口コミスクレイピング管理';
$currentPage = 'review_scrape';

$platformPdo = getPlatformDb();
$tenantId = $tenant['id'];
$tenantSlug = $tenant['code'];

// 設定保存（POST）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    header('Content-Type: application/json');
    try {
        $reviewsBaseUrl = trim($_POST['reviews_base_url'] ?? '');
        if (empty($reviewsBaseUrl)) {
            echo json_encode(['success' => false, 'error' => '口コミページURLを入力してください']);
            exit;
        }
        $stmt = $platformPdo->prepare("SELECT id FROM review_scrape_settings WHERE tenant_id = ?");
        $stmt->execute([$tenantId]);
        $existing = $stmt->fetch();
        $interval = 10;
        $delay = 1.0;
        $maxPages = 50;
        $timeout = 30;
        if ($existing) {
            $stmt = $platformPdo->prepare("
                UPDATE review_scrape_settings SET
                    reviews_base_url = ?,
                    scrape_interval = ?,
                    request_delay = ?,
                    max_pages = ?,
                    timeout = ?,
                    updated_at = NOW()
                WHERE tenant_id = ?
            ");
            $stmt->execute([$reviewsBaseUrl, $interval, $delay, $maxPages, $timeout, $tenantId]);
        } else {
            $stmt = $platformPdo->prepare("
                INSERT INTO review_scrape_settings (
                    tenant_id, reviews_base_url, scrape_interval, request_delay, max_pages, timeout
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$tenantId, $reviewsBaseUrl, $interval, $delay, $maxPages, $timeout]);
        }
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// 設定取得
$stmt = $platformPdo->prepare("SELECT * FROM review_scrape_settings WHERE tenant_id = ?");
$stmt->execute([$tenantId]);
$settings = $stmt->fetch();
if (!$settings) {
    $settings = [
        'reviews_base_url' => '',
        'is_enabled' => 0,
        'last_executed_at' => null,
        'last_execution_status' => null,
        'total_reviews_scraped' => 0,
        'last_reviews_count' => 0,
    ];
}

$stmt = $platformPdo->prepare("SELECT COUNT(*) AS total FROM reviews WHERE tenant_id = ?");
$stmt->execute([$tenantId]);
$totalReviews = (int)$stmt->fetch()['total'];

$stmt = $platformPdo->prepare("SELECT COUNT(*) AS today FROM reviews WHERE tenant_id = ? AND DATE(created_at) = CURDATE()");
$stmt->execute([$tenantId]);
$todayReviews = (int)$stmt->fetch()['today'];

$stmt = $platformPdo->prepare("
    SELECT id, title, cast_name, review_date, user_name, created_at
    FROM reviews WHERE tenant_id = ? ORDER BY review_date DESC LIMIT 10
");
$stmt->execute([$tenantId]);
$latestReviews = $stmt->fetchAll();

$stmt = $platformPdo->prepare("
    SELECT * FROM review_scrape_logs WHERE tenant_id = ? ORDER BY started_at DESC LIMIT 10
");
$stmt->execute([$tenantId]);
$executionHistory = $stmt->fetchAll();

$hasConfig = !empty($settings['reviews_base_url']);

include __DIR__ . '/../includes/header.php';
?>

<?php
require_once __DIR__ . '/../includes/breadcrumb.php';
$breadcrumbs = [
    ['label' => 'ダッシュボード', 'url' => '/app/manage/?tenant=' . $tenantSlug, 'icon' => 'fas fa-chart-pie'],
    ['label' => '口コミスクレイピング管理']
];
renderBreadcrumb($breadcrumbs);
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-comments"></i> 口コミスクレイピング管理</h1>
        <p>スクレイピング先で口コミが掲載されたタイミングで、手動実行で取得・管理します</p>
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

<?php if (!$hasConfig): ?>
<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle"></i> 設定が未完了です。「スクレイピング設定」から口コミページURLを設定してください。
</div>
<?php endif; ?>

<div id="scraping-overlay" class="scraping-overlay">
    <div class="scraping-overlay-content">
        <div class="scraping-spinner"><i class="fas fa-sync-alt fa-spin"></i></div>
        <div class="scraping-overlay-title" id="overlay-title">口コミスクレイピング実行中…</div>
        <div class="scraping-overlay-stats">
            <span>保存 <strong id="ol-saved">0</strong>件</span>
            <span class="ol-divider">|</span>
            <span id="ol-elapsed">00:00</span>
        </div>
    </div>
</div>

<div class="stat-grid-3">
    <div class="stat-card">
        <div class="stat-card-header"><i class="fas fa-chart-bar"></i> 口コミ統計</div>
        <div class="stat-card-value"><?= number_format($totalReviews) ?></div>
        <div class="stat-card-label">累計口コミ数</div>
        <div class="stat-card-sub">今日: <strong><?= $todayReviews ?>件</strong></div>
    </div>
    <div class="stat-card">
        <div class="stat-card-header"><i class="fas fa-clock"></i> 実行状態</div>
        <?php if ($settings['last_executed_at']): ?>
            <div class="stat-card-row">
                <div class="stat-card-row-label">最終実行</div>
                <div class="stat-card-row-value"><?= date('Y/m/d H:i', strtotime($settings['last_executed_at'])) ?></div>
            </div>
            <div class="stat-card-row">
                <div class="stat-card-row-label">結果</div>
                <div class="stat-card-row-value">
                    <?php if ($settings['last_execution_status'] === 'success'): ?>
                        <span class="badge badge-success">成功（<?= (int)$settings['last_reviews_count'] ?>件）</span>
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
        <div class="stat-card-header"><i class="fas fa-cog"></i> 設定</div>
        <div class="stat-card-row">
            <div class="stat-card-row-label">口コミページURL</div>
            <div class="stat-card-row-value"><?= $hasConfig ? h($settings['reviews_base_url']) : '<span class="badge badge-danger">未設定</span>' ?></div>
        </div>
    </div>
</div>

<?php if (!empty($latestReviews)): ?>
<div class="content-card">
    <div class="card-section-title"><i class="fas fa-list"></i> 最新口コミ</div>
    <div style="overflow-x: auto;">
        <table class="data-table">
            <thead>
                <tr><th>タイトル</th><th>キャスト</th><th>掲載日</th></tr>
            </thead>
            <tbody>
                <?php foreach ($latestReviews as $r): ?>
                <tr>
                    <td><?= h($r['title'] ?: '(タイトルなし)') ?></td>
                    <td><?= h($r['cast_name'] ?: '-') ?></td>
                    <td><?= $r['review_date'] ? date('Y/m/d', strtotime($r['review_date'])) : '-' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($executionHistory)): ?>
<div class="content-card">
    <div class="card-section-title"><i class="fas fa-history"></i> 実行履歴</div>
    <div style="overflow-x: auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>実行日時</th><th>タイプ</th><th>結果</th><th style="text-align:center;">取得数</th><th>時間</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($executionHistory as $log): ?>
                <tr>
                    <td><?= date('Y/m/d H:i', strtotime($log['started_at'])) ?></td>
                    <td>
                        <?php if ($log['execution_type'] === 'manual'): ?><span class="badge badge-primary">手動</span>
                        <?php else: ?><span class="badge badge-info">自動</span><?php endif; ?>
                    </td>
                    <td>
                        <?php if ($log['status'] === 'success'): ?><span class="badge badge-success">成功</span>
                        <?php elseif ($log['status'] === 'running'): ?><span class="badge badge-warning">実行中</span>
                        <?php else: ?><span class="badge badge-danger" title="<?= h($log['error_message'] ?? '') ?>">エラー</span><?php endif; ?>
                    </td>
                    <td style="text-align:center; font-weight:600;"><?= (int)$log['reviews_saved'] ?>件</td>
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
            <div class="modal-title"><i class="fas fa-cog" style="color: var(--primary);"></i> <span>口コミ取得設定</span></div>
            <button type="button" class="modal-close" onclick="closeConfigModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div class="modal-field">
                <label>口コミページURL（CityHeaven）</label>
                <input type="url" id="modal-reviews-url" placeholder="https://www.cityheaven.net/地域/エリア/店舗名/reviews/"
                       value="<?= h($settings['reviews_base_url']) ?>">
            </div>
            <div class="modal-validation" id="config-validation"></div>
            <div class="modal-actions">
                <button type="button" class="modal-btn save" onclick="saveConfig()"><i class="fas fa-save"></i> 保存</button>
            </div>
        </div>
    </div>
</div>

<style>
.switch-button { background: var(--primary-gradient); color: var(--text-inverse); border: none; padding: 15px 40px; border-radius: 30px; font-size: 1rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; }
.switch-button:hover:not(:disabled) { transform: translateY(-2px); box-shadow: var(--shadow-lg); }
.switch-button:disabled { background: var(--text-muted); cursor: not-allowed; }
.scraping-overlay { display: none; position: fixed; top: 0; left: var(--sidebar-width, 260px); right: 0; bottom: 0; background: rgba(255,255,255,0.4); backdrop-filter: blur(4px); z-index: 90; justify-content: center; align-items: center; }
@media (max-width: 768px) { .scraping-overlay { left: 0; } }
.scraping-overlay.show { display: flex; }
.scraping-overlay-content { text-align: center; color: var(--text-primary); }
.scraping-spinner { font-size: 3.5rem; margin-bottom: 20px; color: var(--primary); }
.scraping-overlay-title { font-size: 1.6rem; font-weight: 700; margin-bottom: 16px; }
.scraping-overlay-stats { font-size: 1.05rem; color: var(--text-secondary); display: flex; align-items: center; justify-content: center; gap: 8px; }
.setting-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; justify-content: center; align-items: center; }
.setting-modal.show { display: flex; }
.modal-content { background: var(--bg-card); border-radius: 16px; width: 90%; max-width: 520px; box-shadow: var(--shadow-lg); overflow: hidden; }
.modal-header { display: flex; align-items: center; justify-content: space-between; padding: 18px 20px; border-bottom: 1px solid var(--border-color); }
.modal-title { display: flex; align-items: center; gap: 10px; font-weight: 700; font-size: 1.05rem; }
.modal-close { background: transparent; border: none; color: var(--text-muted); cursor: pointer; width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
.modal-body { padding: 20px; }
.modal-field { margin-bottom: 15px; }
.modal-field label { display: block; font-size: 0.85rem; color: var(--text-muted); margin-bottom: 8px; font-weight: 500; }
.modal-field input { width: 100%; padding: 12px 15px; border: 1px solid var(--border-color); border-radius: 10px; font-size: 0.95rem; box-sizing: border-box; }
.modal-validation { min-height: 24px; margin-bottom: 15px; font-size: 0.85rem; padding: 8px 12px; border-radius: 8px; }
.modal-validation:empty { display: none; }
.modal-validation.valid { background: var(--success-bg); color: var(--success); }
.modal-validation.invalid { background: var(--danger-bg); color: var(--danger); }
.modal-actions { display: flex; gap: 10px; justify-content: flex-end; }
.modal-btn { display: flex; align-items: center; gap: 6px; padding: 10px 20px; border: none; border-radius: 8px; font-size: 0.9rem; font-weight: 600; cursor: pointer; }
.modal-btn.save { background: var(--primary-gradient); color: var(--text-inverse); }
</style>

<script>
function openConfigModal() {
    document.getElementById('configModal').classList.add('show');
    document.getElementById('config-validation').className = 'modal-validation';
    document.getElementById('config-validation').textContent = '';
}
function closeConfigModal() {
    document.getElementById('configModal').classList.remove('show');
}
document.getElementById('configModal').addEventListener('click', function(e) { if (e.target === this) closeConfigModal(); });
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeConfigModal(); });

async function saveConfig() {
    var url = document.getElementById('modal-reviews-url').value.trim();
    var validation = document.getElementById('config-validation');
    if (!url) {
        validation.className = 'modal-validation invalid';
        validation.textContent = '口コミページURLを入力してください';
        return;
    }
    validation.className = 'modal-validation';
    validation.textContent = '保存中...';
    validation.style.display = 'block';
    try {
        var formData = new FormData();
        formData.append('save_config', '1');
        formData.append('reviews_base_url', url);
        var response = await fetch('?tenant=<?= h($tenantSlug) ?>', { method: 'POST', body: formData });
        var result = await response.json();
        if (result.success) {
            validation.className = 'modal-validation valid';
            validation.textContent = '設定を保存しました';
            setTimeout(function() { location.reload(); }, 800);
        } else {
            validation.className = 'modal-validation invalid';
            validation.textContent = result.error || '保存に失敗しました';
        }
    } catch (err) {
        validation.className = 'modal-validation invalid';
        validation.textContent = '通信エラー: ' + err.message;
    }
}

var isManualExecution = false, overlayStartTime = null, elapsedTimer = null;
function showOverlay(title) {
    document.getElementById('overlay-title').textContent = title;
    document.getElementById('ol-saved').textContent = '0';
    document.getElementById('ol-elapsed').textContent = '00:00';
    document.getElementById('scraping-overlay').classList.add('show');
    overlayStartTime = Date.now();
    if (elapsedTimer) clearInterval(elapsedTimer);
    elapsedTimer = setInterval(function() {
        if (!overlayStartTime) return;
        var s = Math.floor((Date.now() - overlayStartTime) / 1000);
        var m = Math.floor(s / 60), sec = s % 60;
        document.getElementById('ol-elapsed').textContent = m.toString().padStart(2,'0') + ':' + sec.toString().padStart(2,'0');
    }, 1000);
}
function hideOverlay() {
    document.getElementById('scraping-overlay').classList.remove('show');
    if (elapsedTimer) clearInterval(elapsedTimer);
    elapsedTimer = null;
    overlayStartTime = null;
    isManualExecution = false;
}
async function executeManual() {
    try {
        var checkRes = await fetch('status.php?tenant=<?= h($tenantSlug) ?>&t=' + Date.now());
        var checkData = await checkRes.json();
        if (checkData.status === 'running') {
            alert('現在スクレイピングが実行中です。');
            return;
        }
    } catch (e) {}
    if (!confirm('口コミの取得を開始しますか？')) return;
    isManualExecution = true;
    showOverlay('口コミスクレイピング実行中…');
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
    } catch (err) {
        alert('通信エラー: ' + err.message);
        hideOverlay();
    }
}

var prevStatus = 'idle', lastSeenLogId = null;
async function pollStatus() {
    try {
        var response = await fetch('status.php?tenant=<?= h($tenantSlug) ?>&t=' + Date.now());
        var data = await response.json();
        var overlay = document.getElementById('scraping-overlay');
        var currentLogId = data.log_id || 0;
        if (data.status === 'running') {
            if (!overlay.classList.contains('show')) showOverlay('口コミスクレイピング実行中…');
            document.getElementById('ol-saved').textContent = data.reviews_saved || 0;
            prevStatus = 'running';
            lastSeenLogId = currentLogId;
            setTimeout(pollStatus, 2000);
        } else if (prevStatus === 'running' && (data.status === 'completed' || data.status === 'idle')) {
            document.getElementById('overlay-title').textContent = '完了！ ' + (data.reviews_saved || 0) + '件保存';
            document.querySelector('#scraping-overlay .scraping-spinner i').className = 'fas fa-check-circle';
            prevStatus = 'idle';
            lastSeenLogId = currentLogId;
            setTimeout(function() { hideOverlay(); location.reload(); }, 1500);
        } else if (prevStatus === 'running' && data.status === 'error') {
            document.getElementById('overlay-title').textContent = 'エラーが発生しました';
            document.querySelector('#scraping-overlay .scraping-spinner i').className = 'fas fa-exclamation-circle';
            prevStatus = 'idle';
            lastSeenLogId = currentLogId;
            setTimeout(function() { hideOverlay(); location.reload(); }, 2500);
        } else {
            if (lastSeenLogId === null && currentLogId > 0) lastSeenLogId = currentLogId;
            prevStatus = 'idle';
            setTimeout(pollStatus, 5000);
        }
    } catch (e) {
        setTimeout(pollStatus, 5000);
    }
}
document.addEventListener('DOMContentLoaded', pollStatus);
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
