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
    /* セクションカード - ダークテーマ */
    .scraping-section {
        background: var(--card-bg);
        border-radius: 20px;
        padding: 25px;
        margin-bottom: 25px;
        border: 1px solid var(--border-color);
    }
    
    .scraping-section h2 {
        font-size: 1.1rem;
        font-weight: 600;
        margin: 0 0 8px 0;
        color: var(--text-light);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .scraping-section h2 i {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    
    .scraping-section > p {
        color: var(--text-muted);
        font-size: 0.85rem;
        margin: 0 0 20px 0;
    }
    
    /* サイトカード */
    .site-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 15px;
    }
    
    .site-card {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid var(--border-color);
        border-radius: 15px;
        padding: 20px;
        transition: all 0.3s ease;
    }
    
    .site-card:hover {
        border-color: rgba(255, 107, 157, 0.3);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        transform: translateY(-2px);
    }
    
    .site-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }
    
    .site-name {
        font-weight: 600;
        font-size: 1rem;
        color: var(--text-light);
    }
    
    .site-status {
        font-size: 0.75rem;
        padding: 4px 12px;
        border-radius: 20px;
        background: rgba(76, 175, 80, 0.15);
        border: 1px solid rgba(76, 175, 80, 0.3);
        color: #81c784;
    }
    
    .site-status.not-set {
        background: rgba(255, 152, 0, 0.15);
        border-color: rgba(255, 152, 0, 0.3);
        color: #ffb74d;
    }
    
    .site-status.disabled {
        background: rgba(255, 255, 255, 0.05);
        border-color: var(--border-color);
        color: var(--text-muted);
    }
    
    .site-card-body {
        margin-bottom: 15px;
    }
    
    .url-input-group {
        display: flex;
        gap: 10px;
        margin-bottom: 10px;
    }
    
    .url-input {
        flex: 1;
        padding: 12px 15px;
        border: 1px solid var(--border-color);
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.05);
        color: var(--text-light);
        font-size: 0.9rem;
        transition: all 0.3s ease;
    }
    
    .url-input:focus {
        outline: none;
        border-color: var(--accent);
        background: rgba(255, 255, 255, 0.08);
    }
    
    .url-input::placeholder {
        color: rgba(255, 255, 255, 0.4);
    }
    
    .btn-small {
        padding: 10px 18px;
        border: none;
        border-radius: 25px;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.3s ease;
    }
    
    .btn-small.btn-primary {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: var(--text-light);
    }
    
    .btn-small.btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(255, 107, 157, 0.3);
    }
    
    .btn-small.btn-secondary {
        background: rgba(255, 255, 255, 0.1);
        color: var(--text-light);
        border: 1px solid var(--border-color);
    }
    
    .btn-small.btn-secondary:hover {
        background: rgba(255, 255, 255, 0.15);
    }
    
    .btn-small.btn-danger {
        background: rgba(244, 67, 54, 0.15);
        border: 1px solid rgba(244, 67, 54, 0.3);
        color: #e57373;
    }
    
    .btn-small.btn-danger:hover {
        background: rgba(244, 67, 54, 0.25);
    }
    
    .site-card-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
    }
    
    /* データソース選択 */
    .source-options {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin-bottom: 20px;
    }
    
    .source-option {
        display: flex;
        align-items: center;
        padding: 18px 20px;
        background: rgba(255, 255, 255, 0.03);
        border: 2px solid var(--border-color);
        border-radius: 15px;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .source-option:hover {
        border-color: rgba(255, 107, 157, 0.5);
        background: rgba(255, 255, 255, 0.05);
    }
    
    .source-option.active {
        border-color: var(--primary);
        background: rgba(255, 107, 157, 0.1);
    }
    
    .source-option input[type="radio"] {
        display: none;
    }
    
    .source-option-content {
        flex: 1;
        display: flex;
        align-items: center;
        gap: 15px;
        flex-wrap: wrap;
    }
    
    .source-option-badge {
        font-size: 0.75rem;
        padding: 4px 10px;
        border-radius: 20px;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: #fff;
        font-weight: 600;
    }
    
    .source-option-name {
        font-weight: 600;
        min-width: 100px;
        color: var(--text-light);
    }
    
    .source-option-info {
        font-size: 0.85rem;
        color: var(--text-muted);
    }
    
    .source-option-status {
        font-size: 0.8rem;
        color: #81c784;
    }
    
    .source-option-status.unavailable {
        color: var(--text-muted);
    }
    
    /* 現在の表示データ */
    .current-source-info {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 15px 20px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 12px;
        margin-bottom: 20px;
        border: 1px solid var(--border-color);
    }
    
    .current-source-label {
        font-size: 0.85rem;
        color: var(--text-muted);
    }
    
    .current-source-name {
        font-weight: 700;
        font-size: 1.1rem;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    
    .current-source-count {
        font-size: 0.85rem;
        color: var(--text-muted);
    }
    
    /* 注意事項 */
    .notice-box {
        background: rgba(255, 152, 0, 0.1);
        border-left: 4px solid var(--warning);
        padding: 15px 20px;
        margin-top: 20px;
        border-radius: 0 12px 12px 0;
    }
    
    .notice-box strong {
        display: block;
        margin-bottom: 10px;
        color: var(--warning);
        font-size: 0.9rem;
    }
    
    .notice-box ul {
        margin: 0;
        padding-left: 20px;
        color: var(--text-muted);
        font-size: 0.85rem;
    }
    
    .notice-box li {
        margin-bottom: 6px;
    }
    
    /* 即時更新 */
    .execute-section {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
    }
    
    .execute-card {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid var(--border-color);
        border-radius: 15px;
        padding: 20px;
        text-align: center;
        transition: all 0.3s ease;
    }
    
    .execute-card:hover {
        border-color: rgba(255, 107, 157, 0.3);
    }
    
    .execute-card .site-name {
        margin-bottom: 15px;
        color: var(--text-light);
    }
    
    .btn-execute {
        width: 100%;
        padding: 14px;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: var(--text-light);
        border: none;
        border-radius: 25px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .btn-execute:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(255, 107, 157, 0.3);
    }
    
    .btn-execute:disabled {
        background: rgba(255, 255, 255, 0.1);
        color: var(--text-muted);
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }
    
    .btn-execute.running {
        background: linear-gradient(135deg, #ff9800, #ffb74d);
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
        background: rgba(0, 0, 0, 0.7);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }
    
    .modal-overlay.active {
        display: flex;
    }
    
    .modal-content {
        background: var(--dark);
        border-radius: 20px;
        padding: 30px;
        max-width: 600px;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
        border: 1px solid var(--border-color);
    }
    
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 1px solid var(--border-color);
    }
    
    .modal-header h3 {
        margin: 0;
        font-size: 1.2rem;
        color: var(--text-light);
    }
    
    .modal-close {
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        color: var(--text-muted);
        transition: color 0.3s;
    }
    
    .modal-close:hover {
        color: var(--danger);
    }
    
    .modal-body {
        margin-bottom: 20px;
        color: var(--text-light);
    }
    
    .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 15px;
        margin-top: 25px;
        padding-top: 20px;
        border-top: 1px solid var(--border-color);
    }
</style>

<div class="page-header">
    <div>
        <h1><i class="fas fa-sync"></i> キャストスクレイピング管理</h1>
        <p>スクレイピング実行と表示データの切り替え</p>
    </div>
</div>

<!-- スクレイピング設定 -->
<div class="scraping-section">
    <h2><i class="fas fa-cog"></i> スクレイピング設定</h2>
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
                        <i class="fas fa-save"></i> 保存
                    </button>
                </div>
            </div>
            <div class="site-card-footer">
                <button type="button" class="btn-small btn-secondary" onclick="validateUrl('<?php echo $site; ?>')">
                    <i class="fas fa-check-circle"></i> 確認
                </button>
                <button type="button" class="btn-small <?php echo $enabled ? 'btn-danger' : 'btn-primary'; ?>" onclick="toggleEnabled('<?php echo $site; ?>')">
                    <?php if ($enabled): ?>
                        <i class="fas fa-stop"></i> 停止
                    <?php else: ?>
                        <i class="fas fa-play"></i> 有効化
                    <?php endif; ?>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- HP表示データ -->
<div class="scraping-section">
    <h2><i class="fas fa-exchange-alt"></i> HP表示データ</h2>
    
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
                    <?php if ($canSwitch): ?>
                        <i class="fas fa-check-circle"></i> 切り替え可能
                    <?php else: ?>
                        <i class="fas fa-times-circle"></i> データなし
                    <?php endif; ?>
                </span>
            </div>
        </label>
        <?php endforeach; ?>
    </div>
    
    <button type="button" class="btn btn-primary" onclick="switchSource()" id="switchSourceBtn">
        <i class="fas fa-exchange-alt"></i> データソースを切り替える
    </button>
    
    <div class="notice-box">
        <strong><i class="fas fa-exclamation-triangle"></i> 切り替え時の注意</strong>
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
    <h2><i class="fas fa-bolt"></i> 即時更新</h2>
    <p>定期更新を待たずに即時更新</p>
    
    <div class="execute-section">
        <?php foreach ($sites as $site): ?>
        <div class="execute-card">
            <div class="site-name"><?php echo h($siteNames[$site]); ?></div>
            <button type="button" class="btn-execute" id="execute_<?php echo $site; ?>" onclick="executeScrap('<?php echo $site; ?>')">
                <i class="fas fa-play"></i> 実行
            </button>
        </div>
        <?php endforeach; ?>
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
