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

<div style="display: flex; gap: 15px; flex-wrap: wrap; justify-content: center; margin-bottom: 20px;">
    <button type="button" class="switch-button" id="btn-manual" onclick="executeManual()" <?= !$hasConfig ? 'disabled' : '' ?> style="background: var(--primary-gradient); min-width: 220px; justify-content: center;">
        <i class="fas fa-play"></i> 手動実行
    </button>
    <a href="config.php?tenant=<?= h($tenantSlug) ?>" class="switch-button" style="background: var(--primary-gradient); text-decoration: none; min-width: 220px; justify-content: center;">
        <i class="fas fa-cog"></i> スクレイピング設定
    </a>
</div>

<?php if (!$hasConfig): ?>
<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle"></i> 設定が未完了です。「設定」ボタンからCityHeavenのログイン情報と店舗URLを設定してください。
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

<script>
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
</script>

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
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
