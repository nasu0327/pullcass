<?php
/**
 * スクレイピング進捗確認API
 */

require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

header('Content-Type: application/json');

try {
    $platformPdo = getPlatformDb();
    $tenantId = $tenant['id'];
    
    // 実行中のログを取得
    $stmt = $platformPdo->prepare("
        SELECT * FROM diary_scrape_logs 
        WHERE tenant_id = ? AND status = 'running'
        ORDER BY started_at DESC LIMIT 1
    ");
    $stmt->execute([$tenantId]);
    $runningLog = $stmt->fetch();
    
    if ($runningLog) {
        echo json_encode([
            'status' => 'running',
            'posts_saved' => (int)$runningLog['posts_saved'],
            'posts_found' => (int)$runningLog['posts_found'],
            'posts_skipped' => (int)$runningLog['posts_skipped'],
            'pages_processed' => (int)$runningLog['pages_processed'],
            'errors_count' => (int)$runningLog['errors_count'],
        ]);
    } else {
        // 最後に完了したログを取得
        $stmt = $platformPdo->prepare("
            SELECT * FROM diary_scrape_logs 
            WHERE tenant_id = ?
            ORDER BY started_at DESC LIMIT 1
        ");
        $stmt->execute([$tenantId]);
        $lastLog = $stmt->fetch();
        
        if ($lastLog) {
            $status = ($lastLog['status'] === 'success' || $lastLog['status'] === 'error') ? 'completed' : 'idle';
            echo json_encode([
                'status' => $status,
                'posts_saved' => (int)$lastLog['posts_saved'],
                'posts_found' => (int)$lastLog['posts_found'],
                'pages_processed' => (int)$lastLog['pages_processed'],
                'final_status' => $lastLog['status'],
                'error_message' => $lastLog['error_message'],
            ]);
        } else {
            echo json_encode(['status' => 'idle']);
        }
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error' => $e->getMessage()
    ]);
}
