<?php
/**
 * 写メ日記スクレイピング管理画面
 * Version: 1.0
 */

require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

$pageTitle = '写メ日記スクレイピング管理';
$currentPage = 'diary_scrape';

$platformPdo = getPlatformDb();
$tenantId = $tenant['id'];
$tenantCode = $tenant['code'];

// =====================================================
// 設定取得
// =====================================================
$stmt = $platformPdo->prepare("SELECT * FROM diary_scrape_settings WHERE tenant_id = ?");
$stmt->execute([$tenantId]);
$settings = $stmt->fetch();

// 設定が存在しない場合はデフォルト値
if (!$settings) {
    $settings = [
        'cityheaven_login_id' => '',
        'cityheaven_password' => '',
        'shop_url' => '',
        'is_enabled' => 0,
        'scrape_interval' => 10,
        'request_delay' => 0.5,
        'max_pages' => 50,
        'timeout' => 30,
        'max_posts_per_tenant' => 1000,
        'last_executed_at' => null,
        'last_execution_status' => null,
        'total_posts_scraped' => 0,
        'last_posts_count' => 0,
    ];
}

// =====================================================
// 統計情報取得
// =====================================================
function getPostStats($platformPdo, $tenantId) {
    try {
        // 総投稿数
        $stmt = $platformPdo->prepare("SELECT COUNT(*) as total FROM diary_posts WHERE tenant_id = ?");
        $stmt->execute([$tenantId]);
        $total = $stmt->fetch()['total'];
        
        // 今日の投稿数
        $stmt = $platformPdo->prepare("SELECT COUNT(*) as today FROM diary_posts WHERE tenant_id = ? AND DATE(created_at) = CURDATE()");
        $stmt->execute([$tenantId]);
        $today = $stmt->fetch()['today'];
        
        // 最新投稿
        $stmt = $platformPdo->prepare("
            SELECT title, cast_name, posted_at, created_at
            FROM diary_posts
            WHERE tenant_id = ?
            ORDER BY posted_at DESC, created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$tenantId]);
        $latestPosts = $stmt->fetchAll();
        
        return [
            'total' => $total,
            'today' => $today,
            'latest_posts' => $latestPosts
        ];
        
    } catch (Exception $e) {
        return [
            'total' => 0,
            'today' => 0,
            'latest_posts' => [],
            'error' => $e->getMessage()
        ];
    }
}

$postStats = getPostStats($platformPdo, $tenantId);

// =====================================================
// 実行履歴取得
// =====================================================
$stmt = $platformPdo->prepare("
    SELECT * FROM diary_scrape_logs 
    WHERE tenant_id = ? 
    ORDER BY started_at DESC 
    LIMIT 10
");
$stmt->execute([$tenantId]);
$executionHistory = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<style>
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(20px);
    padding: 25px;
    border-radius: 20px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.stat-card h3 {
    margin: 0 0 15px 0;
    color: #27a3eb;
    border-bottom: 2px solid #27a3eb;
    padding-bottom: 10px;
    font-size: 1.1rem;
}

.stat-number {
    font-size: 2.5em;
    font-weight: bold;
    color: #27a3eb;
    margin: 10px 0;
}

.stat-label {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.9em;
}

.action-buttons {
    display: flex;
    gap: 20px;
    margin-bottom: 30px;
    flex-wrap: wrap;
    align-items: center;
}

.toggle-wrapper {
    display: flex;
    align-items: center;
    gap: 12px;
    background: rgba(255, 255, 255, 0.05);
    padding: 12px 20px;
    border-radius: 25px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.toggle-label {
    font-weight: 600;
    color: #fff;
}

.toggle-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 26px;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #555;
    transition: 0.3s;
    border-radius: 26px;
}

.toggle-slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: 0.3s;
    border-radius: 50%;
}

.toggle-switch input:checked + .toggle-slider {
    background-color: #28a745;
}

.toggle-switch input:checked + .toggle-slider:before {
    transform: translateX(24px);
}

.toggle-status {
    font-weight: bold;
    min-width: 30px;
}

.toggle-status.on {
    color: #28a745;
}

.toggle-status.off {
    color: #dc3545;
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

.btn-success {
    background: #27ae60;
    color: white;
}

.btn-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(39, 174, 96, 0.3);
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none !important;
}

.settings-section {
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(20px);
    padding: 25px;
    border-radius: 20px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    margin-bottom: 30px;
}

.settings-section h3 {
    margin: 0 0 20px 0;
    color: #27a3eb;
    border-bottom: 2px solid #27a3eb;
    padding-bottom: 10px;
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

.alert {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 10px;
}

.alert-warning {
    background: rgba(255, 193, 7, 0.2);
    border: 1px solid rgba(255, 193, 7, 0.4);
    color: #ffc107;
}

.alert-info {
    background: rgba(39, 163, 235, 0.2);
    border: 1px solid rgba(39, 163, 235, 0.4);
    color: #27a3eb;
}

.alert-success {
    background: rgba(39, 174, 96, 0.2);
    border: 1px solid rgba(39, 174, 96, 0.4);
    color: #27ae60;
}

#progress-area {
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    padding: 25px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    margin-bottom: 30px;
    display: none;
}

.progress-header {
    background: linear-gradient(135deg, rgba(39, 163, 235, 0.3), rgba(156, 39, 176, 0.3));
    border-radius: 15px;
    color: white;
    padding: 20px;
    margin-bottom: 20px;
}

.progress-header h3 {
    margin: 0;
    border: none;
}

.counter-display {
    background: rgba(255, 255, 255, 0.1);
    padding: 30px;
    border-radius: 10px;
    text-align: center;
    margin-bottom: 20px;
}

.counter-number {
    font-size: 2em;
    font-weight: bold;
    color: #28a745;
    margin-bottom: 10px;
}

.sub-info {
    font-size: 1em;
    color: rgba(255, 255, 255, 0.7);
}

.table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 15px;
}

.table th, .table td {
    padding: 8px 12px;
    text-align: left;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.9);
}

.table th {
    background: rgba(39, 163, 235, 0.1);
    font-weight: bold;
    color: #27a3eb;
}
</style>

<div class="container">
    <!-- ヘッダー -->
    <div class="header">
        <h1>写メ日記スクレイピング管理</h1>
        <p>CityHeavenから写メ日記を自動取得</p>
    </div>

    <!-- 設定未完了の警告 -->
    <?php if (empty($settings['cityheaven_login_id']) || empty($settings['shop_url'])): ?>
    <div class="alert alert-warning">
        <strong>⚠️ 設定が未完了です</strong><br>
        下記の「スクレイピング設定」でCityHeavenのログイン情報と店舗URLを設定してください。
    </div>
    <?php endif; ?>

    <!-- アクションボタン -->
    <div class="action-buttons">
        <!-- データ取得 ON/OFF トグル -->
        <div class="toggle-wrapper">
            <span class="toggle-label">自動取得</span>
            <label class="toggle-switch">
                <input type="checkbox" id="auto-scrape-toggle" <?= $settings['is_enabled'] ? 'checked' : '' ?> onchange="toggleAutoScrape(this.checked)">
                <span class="toggle-slider"></span>
            </label>
            <span class="toggle-status <?= $settings['is_enabled'] ? 'on' : 'off' ?>" id="toggle-status">
                <?= $settings['is_enabled'] ? 'ON' : 'OFF' ?>
            </span>
        </div>
        
        <button class="btn btn-success" id="btn-manual" onclick="executeManual()" <?= empty($settings['cityheaven_login_id']) ? 'disabled' : '' ?>>
            ▶️ 手動実行
        </button>
        
        <a href="config.php" class="btn btn-primary">
            ⚙️ 設定
        </a>
    </div>

    <!-- 進捗表示エリア -->
    <div id="progress-area">
        <div class="progress-header">
            <h3 id="progress-title">スクレイピング実行中</h3>
        </div>
        
        <div class="counter-display">
            <div class="counter-number">
                現在 <span id="item-counter">0</span> 件取得中...
            </div>
            <div class="sub-info">
                経過時間: <span id="elapsed-time">00:00</span>
            </div>
        </div>
        
        <div style="text-align: center;">
            <button onclick="emergencyStop()" class="btn" style="background: #dc3545; color: white;">
                ❌ 実行を停止
            </button>
        </div>
    </div>

    <!-- 統計情報 -->
    <div class="stats-grid">
        <!-- 投稿統計 -->
        <div class="stat-card">
            <h3>投稿統計</h3>
            <div class="stat-number"><?= number_format($postStats['total']) ?></div>
            <div class="stat-label">累計投稿数</div>
            <div style="margin-top: 15px;">
                <div style="color: rgba(255, 255, 255, 0.7);">
                    今日: <strong style="color: #27a3eb;"><?= $postStats['today'] ?>件</strong>
                </div>
            </div>
        </div>

        <!-- 実行状態 -->
        <div class="stat-card">
            <h3>実行状態</h3>
            <?php if ($settings['last_executed_at']): ?>
                <div style="margin-bottom: 10px;">
                    <div class="stat-label">最終実行</div>
                    <div style="color: #fff; font-size: 1.1em;">
                        <?= date('Y/m/d H:i', strtotime($settings['last_executed_at'])) ?>
                    </div>
                </div>
                <div style="margin-bottom: 10px;">
                    <div class="stat-label">実行結果</div>
                    <div style="color: #fff; font-size: 1.1em;">
                        <?php if ($settings['last_execution_status'] === 'success'): ?>
                            ✅ 成功（<?= $settings['last_posts_count'] ?>件取得）
                        <?php elseif ($settings['last_execution_status'] === 'error'): ?>
                            ❌ エラー
                        <?php else: ?>
                            ⏳ 実行中
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="stat-label">まだ実行されていません</div>
            <?php endif; ?>
        </div>

        <!-- 設定情報 -->
        <div class="stat-card">
            <h3>設定情報</h3>
            <div style="margin-bottom: 10px;">
                <div class="stat-label">ログインID</div>
                <div style="color: #fff; font-size: 0.9em; word-break: break-all;">
                    <?= !empty($settings['cityheaven_login_id']) ? h($settings['cityheaven_login_id']) : '未設定' ?>
                </div>
            </div>
            <div style="margin-bottom: 10px;">
                <div class="stat-label">店舗URL</div>
                <div style="color: #fff; font-size: 0.9em; word-break: break-all;">
                    <?= !empty($settings['shop_url']) ? h($settings['shop_url']) : '未設定' ?>
                </div>
            </div>
            <div>
                <div class="stat-label">取得間隔</div>
                <div style="color: #fff; font-size: 0.9em;">
                    <?= $settings['scrape_interval'] ?>分
                </div>
            </div>
        </div>
    </div>

    <!-- 最新投稿 -->
    <?php if (!empty($postStats['latest_posts'])): ?>
    <div class="stat-card">
        <h3>最新投稿</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>タイトル</th>
                    <th>キャスト</th>
                    <th>投稿日時</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($postStats['latest_posts'] as $post): ?>
                <tr>
                    <td><?= h($post['title'] ?: '(タイトルなし)') ?></td>
                    <td><?= h($post['cast_name']) ?></td>
                    <td><?= date('Y/m/d H:i', strtotime($post['posted_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- 実行履歴 -->
    <?php if (!empty($executionHistory)): ?>
    <div class="stat-card">
        <h3>実行履歴</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>実行日時</th>
                    <th>タイプ</th>
                    <th>結果</th>
                    <th>取得数</th>
                    <th>実行時間</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($executionHistory as $log): ?>
                <tr>
                    <td><?= date('Y/m/d H:i', strtotime($log['started_at'])) ?></td>
                    <td><?= $log['execution_type'] === 'manual' ? '手動' : '自動' ?></td>
                    <td>
                        <?php if ($log['status'] === 'success'): ?>
                            ✅ 成功
                        <?php elseif ($log['status'] === 'error'): ?>
                            ❌ エラー
                        <?php else: ?>
                            ⏳ 実行中
                        <?php endif; ?>
                    </td>
                    <td><?= $log['posts_saved'] ?>件</td>
                    <td><?= $log['execution_time'] ? round($log['execution_time'], 1) . '秒' : '-' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
let currentExecution = null;
let startTime = null;
let pollingInterval = null;

// 自動取得ON/OFF切替
async function toggleAutoScrape(enabled) {
    const statusEl = document.getElementById('toggle-status');
    
    try {
        const response = await fetch('toggle.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ enabled: enabled })
        });
        
        const result = await response.json();
        
        if (result.success) {
            statusEl.textContent = enabled ? 'ON' : 'OFF';
            statusEl.className = 'toggle-status ' + (enabled ? 'on' : 'off');
        } else {
            alert('エラー: ' + (result.error || '設定の保存に失敗しました'));
            document.getElementById('auto-scrape-toggle').checked = !enabled;
        }
    } catch (error) {
        alert('通信エラーが発生しました');
        document.getElementById('auto-scrape-toggle').checked = !enabled;
    }
}

// 手動実行
async function executeManual() {
    if (currentExecution) {
        alert('既に実行中です');
        return;
    }
    
    if (!confirm('写メ日記の取得を開始しますか？')) {
        return;
    }
    
    currentExecution = true;
    startTime = Date.now();
    
    // 進捗エリア表示
    document.getElementById('progress-area').style.display = 'block';
    document.getElementById('btn-manual').disabled = true;
    
    // 実行開始
    try {
        fetch('execute.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type: 'manual' })
        }).catch(error => {
            console.warn('Execution request error:', error);
        });
        
        // ポーリング開始
        setTimeout(() => {
            startPolling();
        }, 1000);
        
    } catch (error) {
        alert('実行開始エラー: ' + error.message);
        hideProgress();
    }
}

// ポーリング開始
function startPolling() {
    pollingInterval = setInterval(pollProgress, 2000);
    updateElapsedTime();
    setInterval(updateElapsedTime, 1000);
}

// 進捗確認
async function pollProgress() {
    try {
        const response = await fetch('status.php');
        const data = await response.json();
        
        // カウンター更新
        if (data.posts_count !== undefined) {
            document.getElementById('item-counter').textContent = data.posts_count;
        }
        
        // 実行完了チェック
        if (data.status === 'completed') {
            clearInterval(pollingInterval);
            setTimeout(() => {
                alert('取得完了: ' + data.posts_count + '件');
                location.reload();
            }, 1000);
        }
        
    } catch (error) {
        console.error('ポーリングエラー:', error);
    }
}

// 経過時間更新
function updateElapsedTime() {
    if (!startTime) return;
    
    const elapsed = Math.floor((Date.now() - startTime) / 1000);
    const minutes = Math.floor(elapsed / 60);
    const seconds = elapsed % 60;
    
    document.getElementById('elapsed-time').textContent = 
        `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
}

// 緊急停止
async function emergencyStop() {
    if (!confirm('実行を停止しますか？')) {
        return;
    }
    
    try {
        await fetch('stop.php', { method: 'POST' });
        clearInterval(pollingInterval);
        alert('停止しました');
        location.reload();
    } catch (error) {
        alert('停止エラー: ' + error.message);
    }
}

// 進捗非表示
function hideProgress() {
    document.getElementById('progress-area').style.display = 'none';
    document.getElementById('btn-manual').disabled = false;
    currentExecution = null;
    startTime = null;
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
