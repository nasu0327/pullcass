<?php
/**
 * 口コミスクレイピング進捗確認API
 */

require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

header('Content-Type: application/json');

try {
    $platformPdo = getPlatformDb();
    $tenantId = $tenant['id'];

    $stmt = $platformPdo->prepare("
        SELECT * FROM review_scrape_logs
        WHERE tenant_id = ? AND status = 'running'
        ORDER BY started_at DESC LIMIT 1
    ");
    $stmt->execute([$tenantId]);
    $runningLog = $stmt->fetch();

    if ($runningLog) {
        echo json_encode([
            'status' => 'running',
            'log_id' => (int)$runningLog['id'],
            'reviews_saved' => (int)$runningLog['reviews_saved'],
            'reviews_found' => (int)$runningLog['reviews_found'],
            'reviews_skipped' => (int)$runningLog['reviews_skipped'],
            'pages_processed' => (int)$runningLog['pages_processed'],
            'errors_count' => (int)$runningLog['errors_count'],
        ]);
    } else {
        $stmt = $platformPdo->prepare("
            SELECT * FROM review_scrape_logs WHERE tenant_id = ? ORDER BY started_at DESC LIMIT 1
        ");
        $stmt->execute([$tenantId]);
        $lastLog = $stmt->fetch();
        if ($lastLog) {
            $status = in_array($lastLog['status'], ['success', 'error'], true) ? 'completed' : 'idle';
            echo json_encode([
                'status' => $status,
                'log_id' => (int)$lastLog['id'],
                'reviews_saved' => (int)$lastLog['reviews_saved'],
                'reviews_found' => (int)$lastLog['reviews_found'],
                'pages_processed' => (int)$lastLog['pages_processed'],
                'final_status' => $lastLog['status'],
                'error_message' => $lastLog['error_message'],
            ]);
        } else {
            echo json_encode(['status' => 'idle', 'log_id' => 0]);
        }
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
}
