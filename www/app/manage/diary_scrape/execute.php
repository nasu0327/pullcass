<?php
/**
 * 写メ日記スクレイピング実行スクリプト
 */

// タイムアウト無効化
set_time_limit(0);
ini_set('max_execution_time', 0);

require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

require_once __DIR__ . '/includes/scraper.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $executionType = $input['type'] ?? 'manual';
    
    $tenantId = $tenant['id'];
    $platformPdo = getPlatformDb();
    
    // 設定取得
    $stmt = $platformPdo->prepare("SELECT * FROM diary_scrape_settings WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $settings = $stmt->fetch();
    
    if (!$settings) {
        throw new Exception('設定が見つかりません');
    }
    
    if (empty($settings['cityheaven_login_id']) || empty($settings['shop_url'])) {
        throw new Exception('CityHeavenのログイン情報または店舗URLが設定されていません');
    }
    
    // 実行ログ作成
    $stmt = $platformPdo->prepare("
        INSERT INTO diary_scrape_logs (
            tenant_id,
            execution_type,
            started_at,
            status
        ) VALUES (?, ?, NOW(), 'running')
    ");
    $stmt->execute([$tenantId, $executionType]);
    $logId = $platformPdo->lastInsertId();
    
    // バックグラウンドで実行
    $command = sprintf(
        'php %s %d %d > /dev/null 2>&1 &',
        escapeshellarg(__DIR__ . '/worker.php'),
        $tenantId,
        $logId
    );
    
    exec($command);
    
    echo json_encode([
        'success' => true,
        'log_id' => $logId
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
