<?php
/**
 * キャストスクレイピング管理画面
 * 
 * 機能:
 * - 3つのサイト（駅ちか、ヘブンネット、デリヘルタウン）のスクレイピング設定
 * - データソースの切り替え
 * - 即時更新実行
 */

require_once __DIR__ . '/../includes/auth.php';

$pageTitle = 'キャストスクレイピング管理';
$currentPage = 'cast_data';

$pdo = getPlatformDb();
$tenantId = $tenant['id'];
$tenantCode = $tenant['code'];

// =====================================================
// API処理
// =====================================================

// スクレイピングステータス確認API
if (isset($_GET['check_scraping_status'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    $sites = ['ekichika', 'heaven', 'dto'];
    $statuses = [];
    $castCounts = [];
    $anyRunning = false;
    
    foreach ($sites as $site) {
        // ステータス取得
        try {
            $stmt = $pdo->prepare("SELECT status FROM tenant_scraping_status WHERE tenant_id = ? AND scraping_type = ?");
            $stmt->execute([$tenantId, $site]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $statuses[$site] = $result ? $result['status'] : 'idle';
            if ($statuses[$site] === 'running') {
                $anyRunning = true;
            }
        } catch (Exception $e) {
            $statuses[$site] = 'idle';
        }
        
        // キャスト数取得
        try {
            $tableName = "tenant_cast_data_{$site}";
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$tableName} WHERE tenant_id = ? AND checked = 1");
            $stmt->execute([$tenantId]);
            $castCounts[$site] = (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            $castCounts[$site] = 0;
        }
    }
    
    // アクティブソース取得
    $activeSource = 'ekichika';
    try {
        $stmt = $pdo->prepare("SELECT config_value FROM tenant_scraping_config WHERE tenant_id = ? AND config_key = 'active_source'");
        $stmt->execute([$tenantId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $activeSource = $result['config_value'];
        }
    } catch (Exception $e) {
        // デフォルト値を使用
    }
    
    // 最終更新時刻取得
    $lastUpdated = [];
    foreach ($sites as $site) {
        try {
            $stmt = $pdo->prepare("SELECT end_time FROM tenant_scraping_status WHERE tenant_id = ? AND scraping_type = ?");
            $stmt->execute([$tenantId, $site]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $lastUpdated[$site] = $result && $result['end_time'] ? $result['end_time'] : null;
        } catch (Exception $e) {
            $lastUpdated[$site] = null;
        }
    }
    
    echo json_encode([
        'status' => 'ok',
        'sites' => $statuses,
        'anyRunning' => $anyRunning,
        'castCounts' => $castCounts,
        'activeSource' => $activeSource,
        'lastUpdated' => $lastUpdated
    ]);
    exit;
}

// URL設定保存API
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_url'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    $site = $_POST['site'] ?? '';
    $url = trim($_POST['url'] ?? '');
    
    $validSites = ['ekichika', 'heaven', 'dto'];
    if (!in_array($site, $validSites)) {
        echo json_encode(['status' => 'error', 'message' => '無効なサイト指定']);
        exit;
    }
    
    $configKey = $site . '_list_url';
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO tenant_scraping_config (tenant_id, config_key, config_value) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)
        ");
        $stmt->execute([$tenantId, $configKey, $url]);
        
        echo json_encode(['status' => 'success', 'message' => 'URLを保存しました']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'データベースエラー: ' . $e->getMessage()]);
    }
    exit;
}

// スクレイピング有効/無効切り替えAPI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_enabled'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    $site = $_POST['site'] ?? '';
    
    $validSites = ['ekichika', 'heaven', 'dto'];
    if (!in_array($site, $validSites)) {
        echo json_encode(['status' => 'error', 'message' => '無効なサイト指定']);
        exit;
    }
    
    $configKey = $site . '_enabled';
    
    try {
        // 現在の状態を取得
        $stmt = $pdo->prepare("SELECT config_value FROM tenant_scraping_config WHERE tenant_id = ? AND config_key = ?");
        $stmt->execute([$tenantId, $configKey]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $currentEnabled = $result ? ($result['config_value'] === '1') : true;
        $newEnabled = !$currentEnabled;
        $newValue = $newEnabled ? '1' : '0';
        
        $stmt = $pdo->prepare("
            INSERT INTO tenant_scraping_config (tenant_id, config_key, config_value) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)
        ");
        $stmt->execute([$tenantId, $configKey, $newValue]);
        
        echo json_encode([
            'status' => 'success',
            'enabled' => $newEnabled,
            'message' => $newEnabled ? 'スクレイピングを有効にしました' : 'スクレイピングを停止しました'
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'データベースエラー: ' . $e->getMessage()]);
    }
    exit;
}

// URLバリデーションAPI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['validate_url'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    $site = $_POST['site'] ?? '';
    $url = trim($_POST['url'] ?? '');
    
    // 空URLは未設定として有効
    if (empty($url)) {
        echo json_encode(['status' => 'empty', 'message' => '未設定']);
        exit;
    }
    
    // URLフォーマットチェック
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        echo json_encode(['status' => 'invalid', 'message' => '無効なURLフォーマットです']);
        exit;
    }
    
    // サイトごとのドメインチェック
    $expectedDomains = [
        'ekichika' => 'ranking-deli.jp',
        'heaven' => 'cityheaven.net',
        'dto' => 'dto.jp'
    ];
    
    $parsedUrl = parse_url($url);
    $host = $parsedUrl['host'] ?? '';
    $path = $parsedUrl['path'] ?? '';
    
    if (isset($expectedDomains[$site])) {
        if (strpos($host, $expectedDomains[$site]) === false) {
            echo json_encode(['status' => 'invalid', 'message' => $expectedDomains[$site] . ' のURLを入力してください']);
            exit;
        }
    }
    
    // 必須パスのチェック
    $validPathPatterns = [
        'ekichika' => '#/girlslist/?$#',
        'heaven' => '#/girllist/?$#',
        'dto' => '#/gals/?$#'
    ];
    
    if (isset($validPathPatterns[$site])) {
        if (!preg_match($validPathPatterns[$site], $path)) {
            $examples = [
                'ekichika' => '例: https://ranking-deli.jp/40/shop/XXXXX/girlslist/',
                'heaven' => '例: https://www.cityheaven.net/fukuoka/エリア/店舗名/girllist/',
                'dto' => '例: https://www.dto.jp/shop/XXXXX/gals'
            ];
            echo json_encode(['status' => 'invalid', 'message' => 'キャスト一覧ページのURLを入力してください。' . ($examples[$site] ?? '')]);
            exit;
        }
    }
    
    echo json_encode(['status' => 'valid', 'message' => '有効なURLです']);
    exit;
}

// データソース切り替えAPI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['switch_source'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    $newSource = $_POST['source'] ?? '';
    
    $validSources = ['ekichika', 'heaven', 'dto'];
    if (!in_array($newSource, $validSources)) {
        echo json_encode(['status' => 'error', 'message' => '無効なソース指定']);
        exit;
    }
    
    try {
        // 切り替え先にデータがあるか確認
        $tableName = "tenant_cast_data_{$newSource}";
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$tableName} WHERE tenant_id = ? AND checked = 1");
        $stmt->execute([$tenantId]);
        $count = (int)$stmt->fetchColumn();
        
        if ($count === 0) {
            echo json_encode(['status' => 'error', 'message' => '切り替え先にデータがありません']);
            exit;
        }
        
        // アクティブソースを更新
        $stmt = $pdo->prepare("
            INSERT INTO tenant_scraping_config (tenant_id, config_key, config_value) 
            VALUES (?, 'active_source', ?) 
            ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)
        ");
        $stmt->execute([$tenantId, $newSource]);
        
        echo json_encode([
            'status' => 'success',
            'message' => 'データソースを切り替えました',
            'newSource' => $newSource,
            'castCount' => $count
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'データベースエラー: ' . $e->getMessage()]);
    }
    exit;
}

// 即時スクレイピング実行API
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['execute'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    $execType = $_POST['exec_type'] ?? 'ekichika';
    
    $validTypes = ['ekichika', 'heaven', 'dto'];
    if (!in_array($execType, $validTypes)) {
        echo json_encode(['status' => 'error', 'message' => '無効な実行タイプ']);
        exit;
    }
    
    // スクレイピングスクリプトをバックグラウンドで実行
    $phpPath = '/usr/bin/php';
    $bgScript = __DIR__ . "/scraper_{$execType}.php";
    $logFile = __DIR__ . "/scraping_{$execType}_{$tenantId}.log";
    
    // テナントIDを引数として渡す
    $cmd = sprintf(
        "nohup %s %s %d > %s 2>&1 & echo $!",
        escapeshellarg($phpPath),
        escapeshellarg($bgScript),
        $tenantId,
        escapeshellarg($logFile)
    );
    
    exec($cmd, $output, $returnCode);
    $pid = isset($output[0]) ? (int)$output[0] : 0;
    
    $siteNames = [
        'ekichika' => '駅ちか',
        'heaven' => 'ヘブンネット',
        'dto' => 'デリヘルタウン'
    ];
    
    echo json_encode([
        'status' => 'started',
        'pid' => $pid,
        'type' => $execType,
        'message' => $siteNames[$execType] . 'スクレイピングをバックグラウンドで開始しました'
    ]);
    exit;
}

// =====================================================
// 設定値の取得
// =====================================================

// URL設定取得
$urlConfigs = [];
try {
    $stmt = $pdo->prepare("
        SELECT config_key, config_value 
        FROM tenant_scraping_config 
        WHERE tenant_id = ? AND config_key IN ('ekichika_list_url', 'heaven_list_url', 'dto_list_url')
    ");
    $stmt->execute([$tenantId]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $urlConfigs[$row['config_key']] = $row['config_value'];
    }
} catch (Exception $e) {
    // テーブルが存在しない場合は空
}

// 有効/無効設定取得
$enabledConfigs = [];
try {
    $stmt = $pdo->prepare("
        SELECT config_key, config_value 
        FROM tenant_scraping_config 
        WHERE tenant_id = ? AND config_key IN ('ekichika_enabled', 'heaven_enabled', 'dto_enabled')
    ");
    $stmt->execute([$tenantId]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $enabledConfigs[$row['config_key']] = $row['config_value'];
    }
} catch (Exception $e) {
    // テーブルが存在しない場合は空
}

// アクティブソース取得
$activeSource = 'ekichika';
try {
    $stmt = $pdo->prepare("SELECT config_value FROM tenant_scraping_config WHERE tenant_id = ? AND config_key = 'active_source'");
    $stmt->execute([$tenantId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        $activeSource = $result['config_value'];
    }
} catch (Exception $e) {
    // デフォルト値を使用
}

// 各サイトのキャスト数と最終更新時刻
$siteStats = [];
$sites = ['ekichika', 'heaven', 'dto'];
foreach ($sites as $site) {
    try {
        $tableName = "tenant_cast_data_{$site}";
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$tableName} WHERE tenant_id = ? AND checked = 1");
        $stmt->execute([$tenantId]);
        $count = (int)$stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT end_time FROM tenant_scraping_status WHERE tenant_id = ? AND scraping_type = ?");
        $stmt->execute([$tenantId, $site]);
        $statusRow = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $siteStats[$site] = [
            'count' => $count,
            'lastUpdated' => $statusRow && $statusRow['end_time'] ? date('Y/m/d H:i', strtotime($statusRow['end_time'])) : null
        ];
    } catch (Exception $e) {
        $siteStats[$site] = ['count' => 0, 'lastUpdated' => null];
    }
}

$siteNames = [
    'ekichika' => '駅ちか',
    'heaven' => 'ヘブンネット',
    'dto' => 'デリヘルタウン'
];

include __DIR__ . '/../includes/header.php';
?>

<style>
    .scraping-section {
        background: #fff;
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 25px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    
    .scraping-section h2 {
        font-size: 18px;
        font-weight: 600;
        margin: 0 0 8px 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .scraping-section h2 .material-icons {
        color: #f568df;
        font-size: 22px;
    }
    
    .scraping-section > p {
        color: #888;
        font-size: 13px;
        margin: 0 0 20px 0;
    }
    
    .site-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 15px;
    }
    
    .site-card {
        border: 1px solid #e0e0e0;
        border-radius: 10px;
        padding: 18px;
        transition: all 0.2s;
    }
    
    .site-card:hover {
        border-color: #f568df;
        box-shadow: 0 4px 12px rgba(245, 104, 223, 0.15);
    }
    
    .site-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
    }
    
    .site-name {
        font-weight: 600;
        font-size: 15px;
        color: #333;
    }
    
    .site-status {
        font-size: 12px;
        padding: 3px 10px;
        border-radius: 20px;
        background: #e8f5e9;
        color: #2e7d32;
    }
    
    .site-status.not-set {
        background: #fff3e0;
        color: #e65100;
    }
    
    .site-status.disabled {
        background: #fafafa;
        color: #999;
    }
    
    .site-card-body {
        margin-bottom: 15px;
    }
    
    .url-input-group {
        display: flex;
        gap: 8px;
        margin-bottom: 10px;
    }
    
    .url-input {
        flex: 1;
        padding: 10px 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 13px;
    }
    
    .url-input:focus {
        outline: none;
        border-color: #f568df;
    }
    
    .btn-small {
        padding: 8px 16px;
        border: none;
        border-radius: 6px;
        font-size: 13px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        transition: all 0.2s;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #f568df, #ffa0f8);
        color: #fff;
    }
    
    .btn-primary:hover {
        opacity: 0.9;
        transform: translateY(-1px);
    }
    
    .btn-secondary {
        background: #f5f5f5;
        color: #666;
    }
    
    .btn-secondary:hover {
        background: #eee;
    }
    
    .btn-danger {
        background: #ffebee;
        color: #c62828;
    }
    
    .btn-danger:hover {
        background: #ffcdd2;
    }
    
    .site-card-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    /* データソース選択 */
    .source-selector {
        margin-top: 20px;
    }
    
    .source-options {
        display: flex;
        flex-direction: column;
        gap: 10px;
        margin-bottom: 15px;
    }
    
    .source-option {
        display: flex;
        align-items: center;
        padding: 15px;
        border: 2px solid #e0e0e0;
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .source-option:hover {
        border-color: #f568df;
    }
    
    .source-option.active {
        border-color: #f568df;
        background: #fef7fd;
    }
    
    .source-option input[type="radio"] {
        display: none;
    }
    
    .source-option-content {
        flex: 1;
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .source-option-badge {
        font-size: 12px;
        padding: 2px 8px;
        border-radius: 4px;
        background: #f568df;
        color: #fff;
    }
    
    .source-option-name {
        font-weight: 600;
        min-width: 100px;
    }
    
    .source-option-info {
        font-size: 13px;
        color: #666;
    }
    
    .source-option-status {
        font-size: 12px;
        color: #2e7d32;
    }
    
    .source-option-status.unavailable {
        color: #999;
    }
    
    /* 現在の表示データ */
    .current-source-info {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 15px 20px;
        background: #f8f9fa;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    
    .current-source-label {
        font-size: 13px;
        color: #666;
    }
    
    .current-source-name {
        font-weight: 600;
        font-size: 16px;
        color: #f568df;
    }
    
    .current-source-count {
        font-size: 13px;
        color: #666;
    }
    
    /* 注意事項 */
    .notice-box {
        background: #fff8e1;
        border-left: 4px solid #ffc107;
        padding: 15px 20px;
        margin-top: 20px;
        border-radius: 0 8px 8px 0;
    }
    
    .notice-box strong {
        display: block;
        margin-bottom: 8px;
        color: #f57c00;
    }
    
    .notice-box ul {
        margin: 0;
        padding-left: 20px;
        color: #666;
        font-size: 13px;
    }
    
    .notice-box li {
        margin-bottom: 5px;
    }
    
    /* 即時更新 */
    .execute-section {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
    }
    
    .execute-card {
        border: 1px solid #e0e0e0;
        border-radius: 10px;
        padding: 15px;
        text-align: center;
    }
    
    .execute-card .site-name {
        margin-bottom: 12px;
    }
    
    .btn-execute {
        width: 100%;
        padding: 12px;
        background: linear-gradient(135deg, #f568df, #ffa0f8);
        color: #fff;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .btn-execute:hover {
        opacity: 0.9;
        transform: translateY(-1px);
    }
    
    .btn-execute:disabled {
        background: #ccc;
        cursor: not-allowed;
        transform: none;
    }
    
    .btn-execute.running {
        background: #ff9800;
        animation: pulse 1.5s infinite;
    }
    
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }
    
    /* モーダル */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }
    
    .modal-overlay.active {
        display: flex;
    }
    
    .modal-content {
        background: #fff;
        border-radius: 12px;
        padding: 25px;
        max-width: 500px;
        width: 90%;
    }
    
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
    
    .modal-header h3 {
        margin: 0;
        font-size: 18px;
    }
    
    .modal-close {
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        color: #999;
    }
    
    .modal-body {
        margin-bottom: 20px;
    }
    
    .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }
</style>

<div class="content-wrapper">
    <div class="page-header">
        <h1><span class="material-icons">sync</span> キャストスクレイピング管理</h1>
        <p>スクレイピング実行と表示データの切り替え</p>
    </div>
    
    <!-- スクレイピング設定 -->
    <div class="scraping-section">
        <h2><span class="material-icons">settings</span> スクレイピング設定</h2>
        <p>※必ずお店ページ内のキャスト一覧ページを設定してください！</p>
        
        <div class="site-cards">
            <?php foreach ($sites as $site): ?>
            <?php
                $urlKey = $site . '_list_url';
                $enabledKey = $site . '_enabled';
                $url = $urlConfigs[$urlKey] ?? '';
                $enabled = !isset($enabledConfigs[$enabledKey]) || $enabledConfigs[$enabledKey] === '1';
                $hasUrl = !empty($url);
            ?>
            <div class="site-card" data-site="<?php echo $site; ?>">
                <div class="site-card-header">
                    <span class="site-name"><?php echo h($siteNames[$site]); ?></span>
                    <?php if (!$hasUrl): ?>
                        <span class="site-status not-set">未設定</span>
                    <?php elseif (!$enabled): ?>
                        <span class="site-status disabled">停止中</span>
                    <?php else: ?>
                        <span class="site-status">設定済み</span>
                    <?php endif; ?>
                </div>
                <div class="site-card-body">
                    <div class="url-input-group">
                        <input type="text" class="url-input" id="url_<?php echo $site; ?>" 
                               value="<?php echo h($url); ?>" 
                               placeholder="キャスト一覧ページのURLを入力">
                        <button type="button" class="btn-small btn-primary" onclick="saveUrl('<?php echo $site; ?>')">
                            <span class="material-icons" style="font-size:16px;">save</span> 保存
                        </button>
                    </div>
                </div>
                <div class="site-card-footer">
                    <button type="button" class="btn-small btn-secondary" onclick="validateUrl('<?php echo $site; ?>')">
                        <span class="material-icons" style="font-size:16px;">check_circle</span> 確認
                    </button>
                    <button type="button" class="btn-small <?php echo $enabled ? 'btn-danger' : 'btn-primary'; ?>" onclick="toggleEnabled('<?php echo $site; ?>')">
                        <?php echo $enabled ? '停止' : '有効化'; ?>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- HP表示データ -->
    <div class="scraping-section">
        <h2><span class="material-icons">sync_alt</span> HP表示データ</h2>
        
        <div class="current-source-info">
            <span class="current-source-label">現在の表示データ</span>
            <span class="current-source-name" id="currentSourceName"><?php echo h($siteNames[$activeSource]); ?></span>
            <span class="current-source-count" id="currentSourceCount"><?php echo $siteStats[$activeSource]['count']; ?>人表示中</span>
        </div>
        
        <div class="source-options">
            <?php foreach ($sites as $site): ?>
            <?php
                $isActive = ($site === $activeSource);
                $count = $siteStats[$site]['count'];
                $lastUpdated = $siteStats[$site]['lastUpdated'];
                $canSwitch = $count > 0;
            ?>
            <label class="source-option <?php echo $isActive ? 'active' : ''; ?>" data-site="<?php echo $site; ?>">
                <input type="radio" name="source" value="<?php echo $site; ?>" <?php echo $isActive ? 'checked' : ''; ?>>
                <div class="source-option-content">
                    <?php if ($isActive): ?>
                        <span class="source-option-badge">使用中</span>
                    <?php endif; ?>
                    <span class="source-option-name"><?php echo h($siteNames[$site]); ?></span>
                    <span class="source-option-info"><?php echo $count; ?>人 ・ 最終更新: <?php echo $lastUpdated ?? '---'; ?></span>
                    <span class="source-option-status <?php echo $canSwitch ? '' : 'unavailable'; ?>">
                        <?php echo $canSwitch ? '✅ 切り替え可能' : '❌ データなし'; ?>
                    </span>
                </div>
            </label>
            <?php endforeach; ?>
        </div>
        
        <button type="button" class="btn-small btn-primary" onclick="switchSource()" id="switchSourceBtn">
            <span class="material-icons" style="font-size:16px;">swap_horiz</span> データソースを切り替える
        </button>
        
        <div class="notice-box">
            <strong>⚠️ 切り替え時の注意</strong>
            <ul>
                <li>切り替え先に存在しないキャストは非表示になります（データは削除されません）</li>
                <li>切り替え先にのみ存在するキャストは新規追加されます</li>
                <li>契約のないサイトへの切り替えはできません</li>
                <li>停止中のサイトには切り替えできません</li>
            </ul>
        </div>
    </div>
    
    <!-- 即時更新 -->
    <div class="scraping-section">
        <h2>即時更新</h2>
        <p>定期更新を待たずに即時更新</p>
        
        <div class="execute-section">
            <?php foreach ($sites as $site): ?>
            <div class="execute-card">
                <div class="site-name"><?php echo h($siteNames[$site]); ?></div>
                <button type="button" class="btn-execute" id="execute_<?php echo $site; ?>" onclick="executeScrap('<?php echo $site; ?>')">
                    実行
                </button>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
const siteNames = {
    ekichika: '駅ちか',
    heaven: 'ヘブンネット',
    dto: 'デリヘルタウン'
};

// URL保存
function saveUrl(site) {
    const url = document.getElementById('url_' + site).value.trim();
    
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'save_url=1&site=' + site + '&url=' + encodeURIComponent(url)
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            alert(data.message);
            location.reload();
        } else {
            alert('エラー: ' + data.message);
        }
    })
    .catch(err => {
        alert('通信エラーが発生しました');
    });
}

// URL確認
function validateUrl(site) {
    const url = document.getElementById('url_' + site).value.trim();
    
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'validate_url=1&site=' + site + '&url=' + encodeURIComponent(url)
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'valid') {
            alert('✅ ' + data.message);
        } else if (data.status === 'empty') {
            alert('⚠️ URLが未設定です');
        } else {
            alert('❌ ' + data.message);
        }
    })
    .catch(err => {
        alert('通信エラーが発生しました');
    });
}

// 有効/無効切り替え
function toggleEnabled(site) {
    if (!confirm(siteNames[site] + 'のスクレイピングを切り替えますか？')) {
        return;
    }
    
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'toggle_enabled=1&site=' + site
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            alert(data.message);
            location.reload();
        } else {
            alert('エラー: ' + data.message);
        }
    })
    .catch(err => {
        alert('通信エラーが発生しました');
    });
}

// データソース切り替え
function switchSource() {
    const selected = document.querySelector('input[name="source"]:checked');
    if (!selected) {
        alert('切り替え先を選択してください');
        return;
    }
    
    const newSource = selected.value;
    const currentSource = document.querySelector('.source-option.active input').value;
    
    if (newSource === currentSource) {
        alert('現在と同じソースが選択されています');
        return;
    }
    
    if (!confirm(siteNames[newSource] + 'に切り替えますか？')) {
        return;
    }
    
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'switch_source=1&source=' + newSource
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            alert(data.message);
            location.reload();
        } else {
            alert('エラー: ' + data.message);
        }
    })
    .catch(err => {
        alert('通信エラーが発生しました');
    });
}

// 即時スクレイピング実行
function executeScrap(site) {
    if (!confirm(siteNames[site] + 'のスクレイピングを実行しますか？\n※完了まで数分かかる場合があります。')) {
        return;
    }
    
    const btn = document.getElementById('execute_' + site);
    btn.disabled = true;
    btn.classList.add('running');
    btn.textContent = '実行中...';
    
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'execute=1&exec_type=' + site
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'started') {
            alert(data.message);
            // ステータスのポーリング開始
            pollStatus();
        } else {
            alert('エラー: ' + data.message);
            btn.disabled = false;
            btn.classList.remove('running');
            btn.textContent = '実行';
        }
    })
    .catch(err => {
        alert('通信エラーが発生しました');
        btn.disabled = false;
        btn.classList.remove('running');
        btn.textContent = '実行';
    });
}

// ステータスポーリング
function pollStatus() {
    fetch('?check_scraping_status=1')
    .then(res => res.json())
    .then(data => {
        if (data.status === 'ok') {
            let anyRunning = false;
            
            for (const site of ['ekichika', 'heaven', 'dto']) {
                const btn = document.getElementById('execute_' + site);
                if (data.sites[site] === 'running') {
                    anyRunning = true;
                    btn.disabled = true;
                    btn.classList.add('running');
                    btn.textContent = '実行中...';
                } else {
                    btn.disabled = false;
                    btn.classList.remove('running');
                    btn.textContent = '実行';
                }
            }
            
            // 現在の表示データを更新
            document.getElementById('currentSourceName').textContent = siteNames[data.activeSource];
            document.getElementById('currentSourceCount').textContent = data.castCounts[data.activeSource] + '人表示中';
            
            if (anyRunning) {
                setTimeout(pollStatus, 3000);
            }
        }
    })
    .catch(err => {
        console.error('Status poll error:', err);
        setTimeout(pollStatus, 5000);
    });
}

// 初回ステータスチェック
document.addEventListener('DOMContentLoaded', function() {
    pollStatus();
});

// ソース選択のハイライト
document.querySelectorAll('.source-option').forEach(option => {
    option.addEventListener('click', function() {
        document.querySelectorAll('.source-option').forEach(o => o.classList.remove('active'));
        this.classList.add('active');
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
