<?php
/**
 * 口コミスクレイピング実行API
 */

require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $executionType = $input['type'] ?? 'manual';

    $tenantId = $tenant['id'];
    $platformPdo = getPlatformDb();

    $stmt = $platformPdo->prepare("SELECT * FROM review_scrape_settings WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $settings = $stmt->fetch();
    if (!$settings) {
        throw new Exception('設定が見つかりません');
    }
    if (empty($settings['reviews_base_url'])) {
        throw new Exception('口コミページURLが設定されていません');
    }

    $stmt = $platformPdo->prepare("
        SELECT id FROM review_scrape_logs
        WHERE tenant_id = ? AND status = 'running'
        LIMIT 1
    ");
    $stmt->execute([$tenantId]);
    if ($stmt->fetch()) {
        throw new Exception('既に実行中です');
    }

    $stmt = $platformPdo->prepare("
        INSERT INTO review_scrape_logs (tenant_id, execution_type, started_at, status)
        VALUES (?, ?, NOW(), 'running')
    ");
    $stmt->execute([$tenantId, $executionType]);
    $logId = $platformPdo->lastInsertId();

    $workerPath = __DIR__ . '/worker.php';
    $command = sprintf(
        'nohup php %s %d %d > /dev/null 2>&1 &',
        escapeshellarg($workerPath),
        (int)$tenantId,
        (int)$logId
    );
    exec($command);

    echo json_encode([
        'success' => true,
        'log_id' => (int)$logId,
        'message' => '口コミスクレイピングをバックグラウンドで開始しました'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
