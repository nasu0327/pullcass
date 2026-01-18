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
requireTenantAdminLogin();

$pageTitle = 'キャストスクレイピング管理';
$currentPage = 'cast_data';

$pdo = getPlatformDb();
$tenantId = $tenant['id'];
$tenantCode = $tenant['code'];

// =====================================================
// API処理
// =====================================================

// ログ取得API
if (isset($_GET['get_log'])) {
    header('Content-Type: text/plain; charset=UTF-8');
    
    $logType = $_GET['get_log'];
    $validTypes = ['ekichika', 'heaven', 'dto'];
    
    if (!in_array($logType, $validTypes)) {
        echo 'ログファイルが指定されていません';
        exit;
    }
    
    $logFile = __DIR__ . "/scraping_{$logType}_{$tenantId}.log";
    
    if (file_exists($logFile)) {
        echo file_get_contents($logFile);
    } else {
        echo 'ログファイルが存在しません';
    }
    exit;
}

// スクレイピングステータス確認API
if (isset($_GET['check_scraping_status'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    $sites = ['ekichika', 'heaven', 'dto'];
    $statuses = [];
    $castCounts = [];
    $anyRunning = false;
    
    foreach ($sites as $site) {
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
        
        try {
            $tableName = "tenant_cast_data_{$site}";
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$tableName} WHERE tenant_id = ? AND checked = 1");
            $stmt->execute([$tenantId]);
            $castCounts[$site] = (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            $castCounts[$site] = 0;
        }
    }
    
    $activeSource = 'ekichika';
    try {
        $stmt = $pdo->prepare("SELECT config_value FROM tenant_scraping_config WHERE tenant_id = ? AND config_key = 'active_source'");
        $stmt->execute([$tenantId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $activeSource = $result['config_value'];
        }
    } catch (Exception $e) {}
    
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
    
    if (empty($url)) {
        echo json_encode(['status' => 'empty', 'message' => '未設定']);
        exit;
    }
    
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        echo json_encode(['status' => 'invalid', 'message' => '無効なURLフォーマットです']);
        exit;
    }
    
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
    
    $forbiddenPaths = ['schedule', 'price', 'news', 'access', 'info', 'coupon', 'diary', 'review'];
    foreach ($forbiddenPaths as $forbidden) {
        if (stripos($path, $forbidden) !== false) {
            echo json_encode(['status' => 'invalid', 'message' => 'キャスト一覧ページのURLを入力してください（' . $forbidden . 'ページは使用できません）']);
            exit;
        }
    }
    
    $validPathPatterns = [
        'ekichika' => '#/girlslist/?$#',
        'heaven' => '#/girllist/?$#',
        'dto' => '#/gals/?$#'
    ];
    
    $examples = [
        'ekichika' => '例: https://ranking-deli.jp/40/shop/XXXXX/girlslist/',
        'heaven' => '例: https://www.cityheaven.net/fukuoka/エリア/店舗名/girllist/',
        'dto' => '例: https://www.dto.jp/shop/XXXXX/gals'
    ];
    
    if (isset($validPathPatterns[$site])) {
        if (!preg_match($validPathPatterns[$site], $path)) {
            $subPathErrors = ['attend' => '出勤一覧', 'newface' => '新人一覧', 'ranking' => 'ランキング'];
            $foundSubPath = '';
            foreach ($subPathErrors as $subPath => $name) {
                if (stripos($path, $subPath) !== false) {
                    $foundSubPath = $name;
                    break;
                }
            }
            
            if ($foundSubPath) {
                echo json_encode(['status' => 'invalid', 'message' => $foundSubPath . 'ページではなく、キャスト一覧ページのURLを入力してください。' . ($examples[$site] ?? '')]);
            } else {
                echo json_encode(['status' => 'invalid', 'message' => 'キャスト一覧ページのURLを入力してください。' . ($examples[$site] ?? '')]);
            }
            exit;
        }
    }
    
    // 実際にアクセスして確認
    $ctx = stream_context_create([
        'http' => [
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'timeout' => 10,
            'ignore_errors' => true
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);
    
    $html = @file_get_contents($url, false, $ctx);
    
    if ($html === false) {
        echo json_encode(['status' => 'invalid', 'message' => 'URLにアクセスできません']);
        exit;
    }
    
    $statusLine = isset($http_response_header[0]) ? $http_response_header[0] : '';
    if (strpos($statusLine, '200') === false && strpos($statusLine, '301') === false && strpos($statusLine, '302') === false) {
        echo json_encode(['status' => 'invalid', 'message' => 'ページが見つかりません（' . $statusLine . '）']);
        exit;
    }
    
    $patterns = [
        'ekichika' => ['girl-block-box', 'girlslist', 'girl-box'],
        'heaven' => ['girlid-', 'girl_list', 'shop_girl'],
        'dto' => ['href="/gal/', 'gal_list', 'shop_gal']
    ];
    
    $found = false;
    if (isset($patterns[$site])) {
        foreach ($patterns[$site] as $pattern) {
            if (stripos($html, $pattern) !== false) {
                $found = true;
                break;
            }
        }
    }
    
    if (!$found) {
        echo json_encode(['status' => 'warning', 'message' => 'キャスト一覧ページではない可能性があります']);
        exit;
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
        $tableName = "tenant_cast_data_{$newSource}";
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$tableName} WHERE tenant_id = ? AND checked = 1");
        $stmt->execute([$tenantId]);
        $count = (int)$stmt->fetchColumn();
        
        if ($count === 0) {
            echo json_encode(['status' => 'error', 'message' => '切り替え先にデータがありません']);
            exit;
        }
        
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
    
    $phpPath = '/usr/bin/php';
    $bgScript = __DIR__ . "/scraper_{$execType}.php";
    $logFile = __DIR__ . "/scraping_{$execType}_{$tenantId}.log";
    
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

$urlConfigs = [];
$enabledConfigs = [];
try {
    $stmt = $pdo->prepare("SELECT config_key, config_value FROM tenant_scraping_config WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (strpos($row['config_key'], '_list_url') !== false) {
            $urlConfigs[$row['config_key']] = $row['config_value'];
        } elseif (strpos($row['config_key'], '_enabled') !== false) {
            $enabledConfigs[$row['config_key']] = $row['config_value'];
        } elseif ($row['config_key'] === 'active_source') {
            $activeSource = $row['config_value'];
        }
    }
} catch (Exception $e) {}

$activeSource = $activeSource ?? 'ekichika';

// 各サイトのステータス情報
$sites = ['ekichika', 'heaven', 'dto'];
$sourceStatus = [];
$siteInfo = [
    'ekichika' => ['name' => '駅ちか', 'favicon' => 'https://ranking-deli.jp/favicon.ico'],
    'heaven' => ['name' => 'ヘブンネット', 'favicon' => 'https://www.cityheaven.net/favicon.ico'],
    'dto' => ['name' => 'デリヘルタウン', 'favicon' => 'https://www.dto.jp/favicon.ico']
];

foreach ($sites as $site) {
    $urlKey = $site . '_list_url';
    $enabledKey = $site . '_enabled';
    $url = $urlConfigs[$urlKey] ?? '';
    $enabled = !isset($enabledConfigs[$enabledKey]) || $enabledConfigs[$enabledKey] === '1';
    $urlConfigured = !empty($url);
    
    // キャスト数と最終更新を取得
    $count = 0;
    $lastUpdate = null;
    try {
        $tableName = "tenant_cast_data_{$site}";
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$tableName} WHERE tenant_id = ? AND checked = 1");
        $stmt->execute([$tenantId]);
        $count = (int)$stmt->fetchColumn();
        
        $stmt = $pdo->prepare("SELECT end_time FROM tenant_scraping_status WHERE tenant_id = ? AND scraping_type = ?");
        $stmt->execute([$tenantId, $site]);
        $statusRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $lastUpdate = $statusRow && $statusRow['end_time'] ? date('Y/m/d H:i', strtotime($statusRow['end_time'])) : '---';
    } catch (Exception $e) {}
    
    $sourceStatus[$site] = [
        'name' => $siteInfo[$site]['name'],
        'favicon' => $siteInfo[$site]['favicon'],
        'url' => $url,
        'urlConfigured' => $urlConfigured,
        'enabled' => $enabled,
        'count' => $count,
        'lastUpdate' => $lastUpdate,
        'available' => $count > 0
    ];
}

// 現在のデータソースのキャスト数
$mainTableCount = $sourceStatus[$activeSource]['count'];

include __DIR__ . '/../includes/header.php';
?>

<style>
    /* データソースセクション */
    .datasource-section {
        background: rgba(255, 255, 255, 0.08);
        backdrop-filter: blur(20px);
        border-radius: 20px;
        padding: 30px;
        margin-bottom: 25px;
        border: 1px solid rgba(255, 255, 255, 0.15);
    }
    
    .datasource-section h2 {
        color: #fff;
        margin-bottom: 20px;
        font-size: 1.3rem;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .datasource-section.compact {
        padding: 20px 25px;
    }
    
    .datasource-section.compact h2 {
        font-size: 1.1rem;
        margin-bottom: 15px;
    }
    
    /* 設定カード横並び */
    .setting-cards-row {
        display: flex;
        justify-content: center;
        gap: 15px;
    }
    
    .setting-card {
        flex: 1;
        max-width: 180px;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
        padding: 18px 15px;
        background: rgba(255, 255, 255, 0.06);
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 12px;
        transition: all 0.2s ease;
    }
    
    .setting-card:hover {
        background: rgba(255, 255, 255, 0.1);
    }
    
    .setting-card.configured {
        border-color: rgba(46, 204, 113, 0.4);
    }
    
    .setting-card.not-configured {
        border-color: rgba(241, 196, 15, 0.4);
    }
    
    .setting-card.paused {
        opacity: 0.7;
        border-color: rgba(231, 76, 60, 0.4);
    }
    
    .setting-card-site {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.95rem;
        font-weight: bold;
        color: #fff;
    }
    
    .setting-card-site .favicon {
        width: 20px;
        height: 20px;
        border-radius: 3px;
    }
    
    .setting-card-btn {
        padding: 8px 24px;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        border: none;
        border-radius: 20px;
        color: #fff;
        font-size: 0.85rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .setting-card-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 20px rgba(255, 107, 157, 0.3);
    }
    
    .setting-card-status {
        font-size: 0.8rem;
        min-height: 20px;
    }
    
    .setting-card-status .status-ok {
        color: #2ecc71;
    }
    
    .setting-card-status .status-warn {
        color: #f1c40f;
    }
    
    .setting-card-status .status-paused {
        color: #e74c3c;
    }
    
    /* 設定モーダル */
    .setting-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(5px);
        z-index: 9999;
        justify-content: center;
        align-items: center;
    }
    
    .setting-modal.show {
        display: flex;
    }
    
    .modal-content {
        background: linear-gradient(145deg, #2a2a3d, #1a1a2e);
        border-radius: 16px;
        width: 90%;
        max-width: 450px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        border: 1px solid rgba(255, 255, 255, 0.1);
        animation: modalSlideIn 0.3s ease;
    }
    
    @keyframes modalSlideIn {
        from {
            opacity: 0;
            transform: translateY(-20px) scale(0.95);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }
    
    .modal-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .modal-title {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 1.1rem;
        font-weight: bold;
        color: #fff;
    }
    
    .modal-title .favicon {
        width: 24px;
        height: 24px;
        border-radius: 4px;
    }
    
    .modal-close {
        background: transparent;
        border: none;
        color: rgba(255, 255, 255, 0.6);
        cursor: pointer;
        padding: 8px;
        border-radius: 50%;
        transition: all 0.2s;
        font-size: 20px;
    }
    
    .modal-close:hover {
        background: rgba(255, 255, 255, 0.1);
        color: #fff;
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
        color: rgba(255, 255, 255, 0.7);
        margin-bottom: 8px;
    }
    
    .modal-field input {
        width: 100%;
        padding: 12px 15px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.08);
        color: #fff;
        font-size: 0.9rem;
        box-sizing: border-box;
    }
    
    .modal-field input:focus {
        outline: none;
        border-color: var(--primary);
    }
    
    .modal-validation {
        min-height: 24px;
        margin-bottom: 15px;
        font-size: 0.85rem;
        padding: 8px 12px;
        border-radius: 8px;
    }
    
    .modal-validation:empty {
        display: none;
    }
    
    .modal-validation.valid {
        display: block;
        background: rgba(46, 204, 113, 0.15);
        color: #2ecc71;
    }
    
    .modal-validation.invalid {
        display: block;
        background: rgba(231, 76, 60, 0.15);
        color: #e74c3c;
    }
    
    .modal-validation.warning {
        display: block;
        background: rgba(241, 196, 15, 0.15);
        color: #f1c40f;
    }
    
    .modal-validation.loading {
        display: block;
        background: rgba(39, 163, 235, 0.15);
        color: #27a3eb;
    }
    
    .modal-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        flex-wrap: wrap;
    }
    
    .modal-btn {
        display: flex;
        align-items: center;
        gap: 6px;
        padding: 10px 16px;
        border: none;
        border-radius: 8px;
        font-size: 0.85rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .modal-btn.validate {
        background: rgba(39, 163, 235, 0.2);
        border: 1px solid rgba(39, 163, 235, 0.4);
        color: #27a3eb;
    }
    
    .modal-btn.validate:hover {
        background: rgba(39, 163, 235, 0.35);
    }
    
    .modal-btn.save {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: #fff;
    }
    
    .modal-btn.save:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(255, 107, 157, 0.3);
    }
    
    .modal-btn.toggle {
        background: rgba(231, 76, 60, 0.2);
        border: 1px solid rgba(231, 76, 60, 0.4);
        color: #e74c3c;
    }
    
    .modal-btn.toggle:hover {
        background: rgba(231, 76, 60, 0.35);
    }
    
    .modal-btn.toggle.resume {
        background: rgba(46, 204, 113, 0.2);
        border-color: rgba(46, 204, 113, 0.4);
        color: #2ecc71;
    }
    
    .modal-btn.toggle.resume:hover {
        background: rgba(46, 204, 113, 0.35);
    }
    
    .modal-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    /* 現在のソース */
    .current-source {
        background: linear-gradient(135deg, rgba(255, 107, 157, 0.2), rgba(255, 107, 157, 0.1));
        border: 2px solid var(--primary);
        border-radius: 15px;
        padding: 20px;
        margin-bottom: 25px;
        text-align: center;
    }
    
    .current-source .label {
        color: rgba(255, 255, 255, 0.7);
        font-size: 0.9rem;
        margin-bottom: 5px;
    }
    
    .current-source .value {
        color: var(--primary);
        font-size: 1.4rem;
        font-weight: bold;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    
    .current-source .value .favicon {
        width: 24px;
        height: 24px;
        border-radius: 4px;
    }
    
    .current-source .count {
        color: rgba(255, 255, 255, 0.8);
        font-size: 1rem;
        margin-top: 5px;
    }
    
    /* ソースリスト */
    .source-list {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .source-item {
        background: rgba(255, 255, 255, 0.05);
        border: 2px solid transparent;
        border-radius: 12px;
        padding: 15px;
        cursor: pointer;
        transition: all 0.3s ease;
        position: relative;
    }
    
    .source-item:hover {
        background: rgba(255, 255, 255, 0.1);
    }
    
    .source-item.active {
        border-color: var(--primary);
        background: rgba(255, 107, 157, 0.1);
    }
    
    .source-item.disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    .source-item.stopped {
        opacity: 0.6;
        cursor: not-allowed;
        border-color: rgba(231, 76, 60, 0.3);
    }
    
    .source-item input[type="radio"] {
        position: absolute;
        opacity: 0;
    }
    
    .source-item .source-name {
        font-size: 1.1rem;
        font-weight: bold;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
        color: #fff;
    }
    
    .source-item .source-name .favicon {
        width: 18px;
        height: 18px;
        border-radius: 3px;
    }
    
    .source-item .source-info {
        color: rgba(255, 255, 255, 0.7);
        font-size: 0.85rem;
    }
    
    .source-item .source-status {
        margin-top: 8px;
        font-size: 0.8rem;
    }
    
    .source-item .source-status.available {
        color: #2ecc71;
    }
    
    .source-item .source-status.unavailable {
        color: #e74c3c;
    }
    
    .source-item .current-badge {
        position: absolute;
        top: -8px;
        right: 10px;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: #fff;
        font-size: 0.7rem;
        padding: 3px 10px;
        border-radius: 10px;
    }
    
    .stopped-badge {
        background: rgba(231, 76, 60, 0.3);
        color: #e74c3c;
        font-size: 0.7rem;
        padding: 2px 8px;
        border-radius: 8px;
    }
    
    .switch-button {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: #fff;
        border: none;
        padding: 15px 40px;
        border-radius: 30px;
        font-size: 1rem;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .switch-button:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 10px 30px rgba(255, 107, 157, 0.4);
    }
    
    .switch-button:disabled {
        background: #555;
        cursor: not-allowed;
    }
    
    .switch-warning {
        background: rgba(241, 196, 15, 0.1);
        border: 1px solid rgba(241, 196, 15, 0.3);
        border-radius: 10px;
        padding: 15px;
        margin-top: 20px;
        color: rgba(255, 255, 255, 0.8);
        font-size: 0.85rem;
    }
    
    .switch-warning strong {
        display: block;
        margin-bottom: 10px;
        color: #f1c40f;
    }
    
    .switch-warning ul {
        margin: 0;
        padding-left: 20px;
    }
    
    .switch-warning li {
        margin-bottom: 5px;
    }
    
    /* 即時更新カード */
    .scraping-cards-row {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 15px;
    }
    
    @media (max-width: 768px) {
        .scraping-cards-row {
            grid-template-columns: 1fr;
        }
    }
    
    .scraping-card {
        background: rgba(255, 255, 255, 0.06);
        border: 1px solid rgba(255, 255, 255, 0.12);
        border-radius: 15px;
        padding: 25px 20px;
        text-align: center;
        transition: all 0.3s ease;
    }
    
    .scraping-card:hover {
        background: rgba(255, 255, 255, 0.1);
        transform: translateY(-2px);
    }
    
    .scraping-card.disabled {
        opacity: 0.5;
    }
    
    .scraping-card.stopped {
        opacity: 0.6;
        border-color: rgba(231, 76, 60, 0.3);
    }
    
    .scraping-card .card-header {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        margin-bottom: 15px;
        font-weight: bold;
        font-size: 1rem;
        color: #fff;
    }
    
    .scraping-card .card-header .favicon {
        width: 20px;
        height: 20px;
        border-radius: 3px;
    }
    
    .scraping-card .not-configured-badge {
        background: rgba(241, 196, 15, 0.3);
        color: #f1c40f;
        font-size: 0.7rem;
        padding: 2px 8px;
        border-radius: 8px;
        margin-left: 5px;
    }
    
    .execute-btn {
        width: 100%;
        padding: 14px 20px;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: #fff;
        border: none;
        border-radius: 25px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .execute-btn:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(255, 107, 157, 0.3);
    }
    
    .execute-btn:disabled {
        background: rgba(255, 255, 255, 0.1);
        color: var(--text-muted);
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }
    
    .execute-btn.running {
        background: linear-gradient(135deg, #ff9800, #ffb74d);
        animation: pulse 1.5s infinite;
    }
    
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.7; }
    }
    
    .notice-text {
        text-align: center;
        color: #f1c40f;
        font-size: 0.85rem;
        margin-bottom: 15px;
    }
    
    .header-section {
        margin-bottom: 20px;
    }
    
    .header-section h2 {
        font-size: 1.3rem;
        color: #fff;
        margin-bottom: 5px;
    }
    
    .header-section p {
        color: var(--text-muted);
        font-size: 0.9rem;
    }
</style>

<div class="page-header">
    <div>
        <h1><i class="fas fa-sync"></i> キャストスクレイピング管理</h1>
        <p>スクレイピング実行と表示データの切り替え</p>
    </div>
</div>

<!-- スクレイピング設定セクション -->
<div class="datasource-section compact">
    <h2><i class="fas fa-cog"></i> スクレイピング設定</h2>
    <p class="notice-text">※必ずお店ページ内のキャスト一覧ページを設定して下さい！</p>
    
    <div class="setting-cards-row">
        <?php foreach ($sourceStatus as $key => $status): ?>
        <div class="setting-card <?php echo !$status['enabled'] ? 'paused' : ($status['urlConfigured'] ? 'configured' : 'not-configured'); ?>" 
             data-site="<?php echo $key; ?>">
            <div class="setting-card-site">
                <img src="<?php echo $status['favicon']; ?>" alt="" class="favicon">
                <span><?php echo $status['name']; ?></span>
            </div>
            <button type="button" class="setting-card-btn" onclick="openSettingModal('<?php echo $key; ?>')">
                <i class="fas fa-cog"></i> 設定
            </button>
            <div class="setting-card-status" id="chip-status-<?php echo $key; ?>">
                <?php if (!$status['enabled']): ?>
                    <span class="status-paused">停止中</span>
                <?php elseif ($status['urlConfigured']): ?>
                    <span class="status-ok">設定済み</span>
                <?php else: ?>
                    <span class="status-warn">未設定</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- 設定モーダル -->
<div id="settingModal" class="setting-modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="modal-title">
                <img src="" alt="" class="favicon" id="modal-favicon">
                <span id="modal-site-name"></span>
            </div>
            <button type="button" class="modal-close" onclick="closeSettingModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="modal-field">
                <label>スクレイピングURL</label>
                <input type="url" id="modal-url" placeholder="https://..." />
            </div>
            <div class="modal-validation" id="modal-validation"></div>
            <div class="modal-actions">
                <button type="button" class="modal-btn validate" onclick="validateUrlModal()">
                    <i class="fas fa-check-circle"></i> 確認
                </button>
                <button type="button" class="modal-btn save" onclick="saveUrlModal()">
                    <i class="fas fa-save"></i> 保存
                </button>
                <button type="button" class="modal-btn toggle" id="modal-toggle-btn" onclick="toggleEnabledModal()">
                    <i class="fas fa-pause" id="modal-toggle-icon"></i>
                    <span id="modal-toggle-text">停止</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- 非表示の入力フィールド（データ保持用） -->
<?php foreach ($sourceStatus as $key => $status): ?>
<input type="hidden" id="url-<?php echo $key; ?>" value="<?php echo h($status['url']); ?>" data-enabled="<?php echo $status['enabled'] ? '1' : '0'; ?>">
<?php endforeach; ?>

<!-- HP表示データセクション -->
<div class="datasource-section">
    <h2><i class="fas fa-exchange-alt"></i> HP表示データ</h2>
    
    <div class="current-source">
        <div class="label">現在の表示データ</div>
        <div class="value">
            <img src="<?php echo $sourceStatus[$activeSource]['favicon']; ?>" alt="" class="favicon">
            <?php echo $sourceStatus[$activeSource]['name']; ?>
        </div>
        <div class="count" id="currentSourceCount"><?php echo $mainTableCount; ?>人表示中</div>
    </div>
    
    <form id="switchSourceForm">
        <div class="source-list">
            <?php foreach ($sourceStatus as $key => $status): 
                $canSwitch = $status['available'] && $status['enabled'];
                $itemClass = $key === $activeSource ? 'active' : '';
                if (!$status['available']) $itemClass .= ' disabled';
                if (!$status['enabled']) $itemClass .= ' stopped';
            ?>
            <label class="source-item <?php echo $itemClass; ?>">
                <input type="radio" name="switch_source" value="<?php echo $key; ?>" 
                    <?php echo $key === $activeSource ? 'checked' : ''; ?>
                    <?php echo !$canSwitch ? 'disabled' : ''; ?>>
                <?php if ($key === $activeSource): ?>
                <span class="current-badge">使用中</span>
                <?php endif; ?>
                <div class="source-name">
                    <img src="<?php echo $status['favicon']; ?>" alt="" class="favicon">
                    <?php echo $status['name']; ?>
                    <?php if (!$status['enabled']): ?>
                    <span class="stopped-badge">停止中</span>
                    <?php endif; ?>
                </div>
                <div class="source-info">
                    <?php if ($status['available']): ?>
                        <?php echo $status['count']; ?>人 ・ 最終更新: <?php echo $status['lastUpdate']; ?>
                    <?php else: ?>
                        データなし（0人）
                    <?php endif; ?>
                </div>
                <div class="source-status <?php echo $canSwitch ? 'available' : 'unavailable'; ?>">
                    <?php if (!$status['enabled']): ?>
                        ⏸️ 停止中（切り替え不可）
                    <?php elseif ($status['available']): ?>
                        ✅ 切り替え可能
                    <?php else: ?>
                        ⚠️ 切り替え不可
                    <?php endif; ?>
                </div>
            </label>
            <?php endforeach; ?>
        </div>
        
        <p id="switchingNotice" style="text-align: center; color: #f1c40f; font-size: 0.85rem; margin: 15px 0; display: none;"><i class="fas fa-exclamation-circle"></i> スクレイピング実行中は切り替えできません。</p>
        
        <div style="text-align: center;">
            <button type="submit" class="switch-button" id="switchButton">
                <i class="fas fa-exchange-alt"></i>
                データソースを切り替える
            </button>
        </div>
    </form>
    
    <div class="switch-warning">
        <strong><i class="fas fa-exclamation-triangle"></i> 切り替え時の注意</strong>
        <ul>
            <li>切り替え先に存在しないキャストは非表示になります（データは削除されません）</li>
            <li>切り替え先にのみ存在するキャストは新規追加されます</li>
            <li>契約のないサイトへの切り替えはできません</li>
            <li>停止中のサイトには切り替えできません</li>
        </ul>
    </div>
</div>

<!-- 即時更新セクション -->
<div class="header-section">
    <h2><i class="fas fa-bolt"></i> 即時更新</h2>
    <p>定期更新を待たずに即時更新</p>
</div>

<div class="scraping-cards-row">
    <?php foreach ($sourceStatus as $key => $status): 
        $disabled = !$status['urlConfigured'] || !$status['enabled'];
        $cardClass = !$status['urlConfigured'] ? 'disabled' : (!$status['enabled'] ? 'stopped' : '');
    ?>
    <div class="scraping-card <?php echo $cardClass; ?>">
        <div class="card-header">
            <img src="<?php echo $status['favicon']; ?>" alt="" class="favicon">
            <span><?php echo $status['name']; ?></span>
            <?php if (!$status['urlConfigured']): ?>
            <span class="not-configured-badge">未設定</span>
            <?php elseif (!$status['enabled']): ?>
            <span class="stopped-badge">停止中</span>
            <?php endif; ?>
        </div>
        <button type="button" class="execute-btn" id="execute_<?php echo $key; ?>" 
                onclick="executeScrap('<?php echo $key; ?>')" <?php echo $disabled ? 'disabled' : ''; ?>>
            実行
        </button>
    </div>
    <?php endforeach; ?>
</div>

<script>
const siteInfo = {
    'ekichika': { name: '駅ちか', favicon: 'https://ranking-deli.jp/favicon.ico' },
    'heaven': { name: 'ヘブンネット', favicon: 'https://www.cityheaven.net/favicon.ico' },
    'dto': { name: 'デリヘルタウン', favicon: 'https://www.dto.jp/favicon.ico' }
};

// サーバーから取得した現在のアクティブソース
const currentActiveSource = '<?php echo $activeSource; ?>';

let currentModalSite = null;

// 設定モーダル関連
function openSettingModal(site) {
    currentModalSite = site;
    const modal = document.getElementById('settingModal');
    const input = document.getElementById('url-' + site);
    const enabled = input.dataset.enabled === '1';
    
    document.getElementById('modal-favicon').src = siteInfo[site].favicon;
    document.getElementById('modal-site-name').textContent = siteInfo[site].name;
    document.getElementById('modal-url').value = input.value;
    document.getElementById('modal-validation').textContent = '';
    document.getElementById('modal-validation').className = 'modal-validation';
    
    const toggleBtn = document.getElementById('modal-toggle-btn');
    const toggleIcon = document.getElementById('modal-toggle-icon');
    const toggleText = document.getElementById('modal-toggle-text');
    
    if (enabled) {
        toggleBtn.classList.remove('resume');
        toggleIcon.className = 'fas fa-pause';
        toggleText.textContent = '停止';
    } else {
        toggleBtn.classList.add('resume');
        toggleIcon.className = 'fas fa-play';
        toggleText.textContent = '再開';
    }
    
    toggleBtn.disabled = !input.value;
    modal.classList.add('show');
}

function closeSettingModal() {
    document.getElementById('settingModal').classList.remove('show');
    currentModalSite = null;
}

// モーダル外クリックで閉じる
document.getElementById('settingModal').addEventListener('click', function(e) {
    if (e.target === this) closeSettingModal();
});

// ESCキーで閉じる
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && currentModalSite) closeSettingModal();
});

// URL検証
async function validateUrlModal() {
    const url = document.getElementById('modal-url').value.trim();
    const resultDiv = document.getElementById('modal-validation');
    
    resultDiv.className = 'modal-validation loading';
    resultDiv.textContent = '確認中...';
    
    try {
        const formData = new FormData();
        formData.append('validate_url', '1');
        formData.append('site', currentModalSite);
        formData.append('url', url);
        
        const response = await fetch('', { method: 'POST', body: formData });
        const result = await response.json();
        
        resultDiv.className = 'modal-validation ' + result.status;
        const icons = { 'valid': '✅ ', 'invalid': '❌ ', 'warning': '⚠️ ', 'empty': 'ℹ️ ' };
        resultDiv.textContent = (icons[result.status] || '') + result.message;
    } catch (error) {
        resultDiv.className = 'modal-validation invalid';
        resultDiv.textContent = '❌ エラーが発生しました';
    }
}

// URL保存
async function saveUrlModal() {
    const url = document.getElementById('modal-url').value.trim();
    const resultDiv = document.getElementById('modal-validation');
    
    if (!url) {
        await doSaveUrl(url, resultDiv);
        return;
    }
    
    resultDiv.className = 'modal-validation loading';
    resultDiv.textContent = 'URLを確認中...';
    
    try {
        const validateFormData = new FormData();
        validateFormData.append('validate_url', '1');
        validateFormData.append('site', currentModalSite);
        validateFormData.append('url', url);
        
        const validateResponse = await fetch('', { method: 'POST', body: validateFormData });
        const validateResult = await validateResponse.json();
        
        if (validateResult.status === 'invalid') {
            resultDiv.className = 'modal-validation invalid';
            resultDiv.textContent = '❌ ' + validateResult.message + '\n保存を中止しました';
            return;
        }
        
        if (validateResult.status === 'warning') {
            resultDiv.className = 'modal-validation warning';
            resultDiv.textContent = '⚠️ ' + validateResult.message;
            
            if (!confirm('⚠️ ' + validateResult.message + '\n\nこのまま保存しますか？')) {
                resultDiv.textContent = '保存をキャンセルしました';
                return;
            }
        }
        
        await doSaveUrl(url, resultDiv);
    } catch (error) {
        resultDiv.className = 'modal-validation invalid';
        resultDiv.textContent = '❌ エラーが発生しました';
    }
}

async function doSaveUrl(url, resultDiv) {
    resultDiv.className = 'modal-validation loading';
    resultDiv.textContent = '保存中...';
    
    try {
        const formData = new FormData();
        formData.append('save_url', '1');
        formData.append('site', currentModalSite);
        formData.append('url', url);
        
        const response = await fetch('', { method: 'POST', body: formData });
        const result = await response.json();
        
        if (result.status === 'success') {
            resultDiv.className = 'modal-validation valid';
            resultDiv.textContent = '✅ ' + result.message;
            document.getElementById('url-' + currentModalSite).value = url;
            
            // トグルボタンの状態を更新
            document.getElementById('modal-toggle-btn').disabled = !url;
            
            setTimeout(() => location.reload(), 1000);
        } else {
            resultDiv.className = 'modal-validation invalid';
            resultDiv.textContent = '❌ ' + result.message;
        }
    } catch (error) {
        resultDiv.className = 'modal-validation invalid';
        resultDiv.textContent = '❌ 通信エラーが発生しました';
    }
}

// 有効/無効切り替え
async function toggleEnabledModal() {
    const resultDiv = document.getElementById('modal-validation');
    
    try {
        const formData = new FormData();
        formData.append('toggle_enabled', '1');
        formData.append('site', currentModalSite);
        
        const response = await fetch('', { method: 'POST', body: formData });
        const result = await response.json();
        
        if (result.status === 'success') {
            resultDiv.className = 'modal-validation valid';
            resultDiv.textContent = '✅ ' + result.message;
            
            const input = document.getElementById('url-' + currentModalSite);
            input.dataset.enabled = result.enabled ? '1' : '0';
            
            setTimeout(() => location.reload(), 1000);
        } else {
            resultDiv.className = 'modal-validation invalid';
            resultDiv.textContent = '❌ ' + result.message;
        }
    } catch (error) {
        resultDiv.className = 'modal-validation invalid';
        resultDiv.textContent = '❌ 通信エラーが発生しました';
    }
}

// データソース切り替え
document.getElementById('switchSourceForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    // スクレイピング中は切り替え不可
    const anyRunning = Object.values(previousRunningStatus).some(s => s === 'running');
    if (anyRunning) {
        alert('スクレイピング実行中は切り替えできません。\n完了までお待ちください。');
        return;
    }
    
    const selected = document.querySelector('input[name="switch_source"]:checked');
    if (!selected) {
        alert('切り替え先を選択してください');
        return;
    }
    
    const newSource = selected.value;
    
    // サーバー側のアクティブソースと比較
    if (newSource === currentActiveSource) {
        alert('現在使用中のデータソースです。');
        return;
    }
    
    if (!confirm(siteInfo[newSource].name + 'に切り替えますか？\n\n・切り替え先に存在しないキャストは非表示になります\n・切り替え先にのみ存在するキャストは新規追加されます')) return;
    
    const switchBtn = document.getElementById('switchButton');
    switchBtn.disabled = true;
    switchBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 切り替え中...';
    
    try {
        const formData = new FormData();
        formData.append('switch_source', '1');
        formData.append('source', newSource);
        
        const response = await fetch('', { method: 'POST', body: formData });
        const result = await response.json();
        
        if (result.status === 'success') {
            alert(result.message);
            location.reload();
        } else {
            alert('エラー: ' + result.message);
            switchBtn.disabled = false;
            switchBtn.innerHTML = '<i class="fas fa-exchange-alt"></i> データソースを切り替える';
        }
    } catch (error) {
        alert('通信エラーが発生しました');
        switchBtn.disabled = false;
        switchBtn.innerHTML = '<i class="fas fa-exchange-alt"></i> データソースを切り替える';
    }
});

// 切り替え不可状態を更新
function updateSwitchingState(anyRunning) {
    const switchBtn = document.getElementById('switchButton');
    const switchingNotice = document.getElementById('switchingNotice');
    
    if (anyRunning) {
        switchBtn.disabled = true;
        switchingNotice.style.display = 'block';
    } else {
        switchBtn.disabled = false;
        switchingNotice.style.display = 'none';
    }
}

// ソースカード内のステータス表示を更新
function updateSourceCardStatus(site, isRunning) {
    const sourceItem = document.querySelector(`.source-item input[value="${site}"]`);
    if (!sourceItem) return;
    
    const sourceStatus = sourceItem.closest('.source-item').querySelector('.source-status');
    if (!sourceStatus) return;
    
    const input = document.getElementById('url-' + site);
    const enabled = input && input.dataset.enabled === '1';
    const urlConfigured = input && input.value;
    
    if (isRunning) {
        sourceStatus.textContent = '⏳ 更新中...';
        sourceStatus.className = 'source-status';
        sourceStatus.style.color = '#f1c40f';
    } else if (!enabled) {
        sourceStatus.textContent = '⏸️ 停止中（切り替え不可）';
        sourceStatus.className = 'source-status unavailable';
        sourceStatus.style.color = '';
    } else if (urlConfigured) {
        sourceStatus.textContent = '✅ 切り替え可能';
        sourceStatus.className = 'source-status available';
        sourceStatus.style.color = '';
    } else {
        sourceStatus.textContent = '⚠️ 切り替え不可';
        sourceStatus.className = 'source-status unavailable';
        sourceStatus.style.color = '';
    }
}

// 即時スクレイピング実行
async function executeScrap(site) {
    if (!confirm(siteInfo[site].name + 'のスクレイピングを実行しますか？\n※完了まで数分かかる場合があります。')) return;
    
    const btn = document.getElementById('execute_' + site);
    btn.disabled = true;
    btn.classList.add('running');
    btn.textContent = '実行中...';
    
    // 実行中ステータスを設定
    previousRunningStatus[site] = 'running';
    
    // 切り替えボタンを即座に無効化し、注意文を表示
    updateSwitchingState(true);
    
    // ソースカード内のステータスを「更新中」に変更
    updateSourceCardStatus(site, true);
    
    try {
        const formData = new FormData();
        formData.append('execute', '1');
        formData.append('exec_type', site);
        
        const response = await fetch('', { method: 'POST', body: formData });
        const result = await response.json();
        
        if (result.status === 'started') {
            // ステータスポーリング開始
            setTimeout(pollStatus, 3000);
        } else {
            alert('エラー: ' + result.message);
            btn.disabled = false;
            btn.classList.remove('running');
            btn.textContent = '実行';
            previousRunningStatus[site] = 'idle';
            // ソースカードのステータスを元に戻す
            updateSourceCardStatus(site, false);
            // 切り替え状態を再評価
            const anyStillRunning = Object.values(previousRunningStatus).some(s => s === 'running');
            updateSwitchingState(anyStillRunning);
        }
    } catch (error) {
        alert('通信エラーが発生しました');
        btn.disabled = false;
        btn.classList.remove('running');
        btn.textContent = '実行';
        previousRunningStatus[site] = 'idle';
        // ソースカードのステータスを元に戻す
        updateSourceCardStatus(site, false);
        const anyStillRunning = Object.values(previousRunningStatus).some(s => s === 'running');
        updateSwitchingState(anyStillRunning);
    }
}

// ステータスポーリング
let previousRunningStatus = {};
let wasAnyRunning = false;
const POLL_INTERVAL_IDLE = 10000;    // アイドル時: 10秒間隔
const POLL_INTERVAL_RUNNING = 3000;  // 実行中: 3秒間隔

async function pollStatus() {
    try {
        const response = await fetch('?check_scraping_status=1&t=' + Date.now());
        const data = await response.json();
        
        if (data.status === 'ok') {
            let anyRunning = data.anyRunning;
            
            for (const site of ['ekichika', 'heaven', 'dto']) {
                const btn = document.getElementById('execute_' + site);
                const input = document.getElementById('url-' + site);
                const urlConfigured = input && input.value;
                const enabled = input && input.dataset.enabled === '1';
                const isRunning = data.sites[site] === 'running';
                
                // 実行中 → 完了 に変わった場合アラート表示（手動実行の場合のみ）
                if (previousRunningStatus[site] === 'running' && !isRunning) {
                    // 最終更新時間を更新
                    const sourceItem = document.querySelector(`.source-item input[value="${site}"]`);
                    if (sourceItem) {
                        const infoDiv = sourceItem.closest('.source-item').querySelector('.source-info');
                        if (infoDiv && data.lastUpdated[site]) {
                            infoDiv.textContent = data.castCounts[site] + '人 ・ 最終更新: ' + data.lastUpdated[site];
                        }
                    }
                }
                
                // idle → running に変わった場合（cron等で開始された場合）も検知
                if (previousRunningStatus[site] === 'idle' && isRunning) {
                    console.log(siteInfo[site].name + ' のスクレイピングが開始されました');
                }
                
                // 現在のステータスを保存
                previousRunningStatus[site] = isRunning ? 'running' : 'idle';
                
                // 各サイトのボタンは独立して制御（そのサイトが実行中の時だけ無効化）
                if (isRunning) {
                    btn.disabled = true;
                    btn.classList.add('running');
                    btn.textContent = '実行中...';
                } else {
                    btn.classList.remove('running');
                    btn.textContent = '実行';
                    // URLが設定されていないか、無効化されている場合のみボタンを無効化
                    btn.disabled = !urlConfigured || !enabled;
                }
                
                // ソースカード内のステータス表示を更新
                updateSourceCardStatus(site, isRunning);
            }
            
            // データソース切り替えの状態を更新
            updateSwitchingState(anyRunning);
            
            // キャスト数を更新
            document.getElementById('currentSourceCount').textContent = data.castCounts[data.activeSource] + '人表示中';
            
            // 常にポーリングを継続（実行中は短い間隔、アイドル時は長い間隔）
            const nextInterval = anyRunning ? POLL_INTERVAL_RUNNING : POLL_INTERVAL_IDLE;
            setTimeout(pollStatus, nextInterval);
            
            wasAnyRunning = anyRunning;
        }
    } catch (error) {
        console.error('Status poll error:', error);
        // エラー時も継続
        setTimeout(pollStatus, POLL_INTERVAL_IDLE);
    }
}

// 初回ステータスチェック
document.addEventListener('DOMContentLoaded', function() {
    // 初期状態を設定
    previousRunningStatus = { ekichika: 'idle', heaven: 'idle', dto: 'idle' };
    pollStatus();
});

// ソース選択のハイライト
document.querySelectorAll('.source-item').forEach(item => {
    item.addEventListener('click', function() {
        if (this.classList.contains('disabled') || this.classList.contains('stopped')) return;
        document.querySelectorAll('.source-item').forEach(i => i.classList.remove('active'));
        this.classList.add('active');
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
