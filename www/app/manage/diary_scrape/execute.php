<?php
/**
 * 写メ日記スクレイピング実行スクリプト
 * バックグラウンドでworker.phpを起動して即座にレスポンスを返す
 */

require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

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
    
    // 実行中のチェック
    $stmt = $platformPdo->prepare("
        SELECT id FROM diary_scrape_logs 
        WHERE tenant_id = ? AND status = 'running'
        LIMIT 1
    ");
    $stmt->execute([$tenantId]);
    if ($stmt->fetch()) {
        throw new Exception('既に実行中です');
    }
    
    // 実行ログ作成
    $stmt = $platformPdo->prepare("
        INSERT INTO diary_scrape_logs (
            tenant_id, execution_type, started_at, status
        ) VALUES (?, ?, NOW(), 'running')
    ");
    $stmt->execute([$tenantId, $executionType]);
    $logId = $platformPdo->lastInsertId();
    
    // worker.phpのパス
    $workerPath = __DIR__ . '/worker.php';
    $bootstrapPath = realpath(__DIR__ . '/../../../includes/bootstrap.php');
    
    // バックグラウンドで実行（nohup + &で完全にバックグラウンド化）
    $command = sprintf(
        'nohup php %s %d %d > /dev/null 2>&1 &',
        escapeshellarg($workerPath),
        (int)$tenantId,
        (int)$logId
    );
    
    exec($command);
    
    // 即座にレスポンス
    echo json_encode([
        'success' => true,
        'log_id' => $logId,
        'message' => 'スクレイピングをバックグラウンドで開始しました'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
