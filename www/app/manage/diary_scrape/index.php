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

include __DIR__ . '/../includes/header.php';
?>

<div class="header-section">
    <h2><i class="fas fa-camera"></i> 写メ日記スクレイピング管理</h2>
    <p>CityHeavenから写メ日記を自動取得</p>
</div>

<?php if (empty($settings['cityheaven_login_id']) || empty($settings['shop_url'])): ?>
<div class="content-card" style="border-left: 4px solid var(--warning);">
    <p style="color: var(--warning-text); font-weight: 600;">
        <i class="fas fa-exclamation-triangle"></i> 設定が未完了です。
        下記の「設定」ボタンからCityHeavenのログイン情報と店舗URLを設定してください。
    </p>
</div>
<?php endif; ?>

<!-- アクションボタン -->
<div class="content-card">
    <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
        <a href="config.php?tenant=<?= h($tenantSlug) ?>" class="btn btn-primary">
            <i class="fas fa-cog"></i> 設定
        </a>
        <button class="btn btn-success" id="btn-manual" onclick="executeManual()" <?= empty($settings['cityheaven_login_id']) ? 'disabled' : '' ?>>
            <i class="fas fa-play"></i> 手動実行
        </button>
        <a href="test.php?tenant=<?= h($tenantSlug) ?>" class="btn btn-outline">
            <i class="fas fa-stethoscope"></i> 動作確認
        </a>
    </div>
</div>

<!-- 進捗表示エリア -->
<div class="content-card" id="progress-area" style="display: none;">
    <h3 style="color: var(--primary); margin-bottom: 15px;">
        <i class="fas fa-spinner fa-spin"></i> <span id="progress-title">スクレイピング実行中...</span>
    </h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 15px; margin-bottom: 15px;">
        <div style="text-align: center; padding: 15px; background: var(--success-bg); border-radius: 12px;">
            <div style="font-size: 1.8em; font-weight: bold; color: var(--success);" id="item-counter">0</div>
            <div style="font-size: 0.85em; color: var(--text-secondary);">保存件数</div>
        </div>
        <div style="text-align: center; padding: 15px; background: var(--accent-bg); border-radius: 12px;">
            <div style="font-size: 1.8em; font-weight: bold; color: var(--accent);" id="found-counter">0</div>
            <div style="font-size: 0.85em; color: var(--text-secondary);">検出件数</div>
        </div>
        <div style="text-align: center; padding: 15px; background: var(--primary-bg); border-radius: 12px;">
            <div style="font-size: 1.8em; font-weight: bold; color: var(--primary);" id="page-counter">0</div>
            <div style="font-size: 0.85em; color: var(--text-secondary);">処理ページ</div>
        </div>
        <div style="text-align: center; padding: 15px; background: var(--bg-hover); border-radius: 12px;">
            <div style="font-size: 1.2em; font-weight: bold; color: var(--text-primary);" id="elapsed-time">00:00</div>
            <div style="font-size: 0.85em; color: var(--text-secondary);">経過時間</div>
        </div>
    </div>
    <div style="text-align: center;">
        <button onclick="emergencyStop()" class="btn btn-danger">
            <i class="fas fa-stop"></i> 停止
        </button>
    </div>
</div>

<!-- 統計情報 -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 20px;">
    <!-- 投稿統計 -->
    <div class="content-card">
        <h3 style="color: var(--primary); margin-bottom: 15px; border-bottom: 2px solid var(--primary-border); padding-bottom: 10px;">
            <i class="fas fa-chart-bar"></i> 投稿統計
        </h3>
        <div style="font-size: 2.5em; font-weight: bold; color: var(--primary); margin: 10px 0;">
            <?= number_format($totalPosts) ?>
        </div>
        <div style="color: var(--text-secondary);">累計投稿数</div>
        <div style="margin-top: 10px; color: var(--text-secondary);">
            今日: <strong style="color: var(--primary);"><?= $todayPosts ?>件</strong>
        </div>
    </div>

    <!-- 実行状態 -->
    <div class="content-card">
        <h3 style="color: var(--primary); margin-bottom: 15px; border-bottom: 2px solid var(--primary-border); padding-bottom: 10px;">
            <i class="fas fa-clock"></i> 実行状態
        </h3>
        <?php if ($settings['last_executed_at']): ?>
            <div style="margin-bottom: 10px;">
                <div style="color: var(--text-secondary); font-size: 0.85em;">最終実行</div>
                <div style="color: var(--text-primary); font-size: 1.1em; font-weight: 600;">
                    <?= date('Y/m/d H:i', strtotime($settings['last_executed_at'])) ?>
                </div>
            </div>
            <div>
                <div style="color: var(--text-secondary); font-size: 0.85em;">結果</div>
                <div style="font-weight: 600;">
                    <?php if ($settings['last_execution_status'] === 'success'): ?>
                        <span style="color: var(--success);">成功（<?= $settings['last_posts_count'] ?>件）</span>
                    <?php elseif ($settings['last_execution_status'] === 'error'): ?>
                        <span style="color: var(--danger);">エラー</span>
                    <?php else: ?>
                        <span style="color: var(--warning);">実行中</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div style="color: var(--text-muted);">まだ実行されていません</div>
        <?php endif; ?>
    </div>

    <!-- 設定情報 -->
    <div class="content-card">
        <h3 style="color: var(--primary); margin-bottom: 15px; border-bottom: 2px solid var(--primary-border); padding-bottom: 10px;">
            <i class="fas fa-cog"></i> 設定情報
        </h3>
        <div style="margin-bottom: 10px;">
            <div style="color: var(--text-secondary); font-size: 0.85em;">ログインID</div>
            <div style="color: var(--text-primary); font-size: 0.9em; word-break: break-all;">
                <?= !empty($settings['cityheaven_login_id']) ? h($settings['cityheaven_login_id']) : '<span style="color:var(--danger)">未設定</span>' ?>
            </div>
        </div>
        <div style="margin-bottom: 10px;">
            <div style="color: var(--text-secondary); font-size: 0.85em;">店舗URL</div>
            <div style="color: var(--text-primary); font-size: 0.9em; word-break: break-all;">
                <?= !empty($settings['shop_url']) ? h($settings['shop_url']) : '<span style="color:var(--danger)">未設定</span>' ?>
            </div>
        </div>
    </div>
</div>

<!-- 最新投稿 -->
<?php if (!empty($latestPosts)): ?>
<div class="content-card">
    <h3 style="color: var(--primary); margin-bottom: 15px;">
        <i class="fas fa-list"></i> 最新投稿
    </h3>
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
    <h3 style="color: var(--primary); margin-bottom: 15px;">
        <i class="fas fa-history"></i> 実行履歴
    </h3>
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
                    <td><?= $log['execution_type'] === 'manual' ? '手動' : '自動' ?></td>
                    <td>
                        <?php if ($log['status'] === 'success'): ?>
                            <span style="color: var(--success); font-weight: 600;">成功</span>
                        <?php elseif ($log['status'] === 'running'): ?>
                            <span style="color: var(--warning); font-weight: 600;">実行中</span>
                        <?php else: ?>
                            <span style="color: var(--danger); font-weight: 600;">エラー</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $log['posts_found'] ?>件</td>
                    <td><?= $log['posts_saved'] ?>件</td>
                    <td><?= $log['posts_skipped'] ?>件</td>
                    <td><?= $log['execution_time'] ? round($log['execution_time'], 1) . '秒' : '-' ?></td>
                    <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
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
        
        // ポーリング開始
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
        console.error('ポーリングエラー:', error);
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

<?php include __DIR__ . '/../includes/footer.php'; ?>
