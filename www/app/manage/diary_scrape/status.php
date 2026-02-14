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
    
    // 最新の実行ログを取得
    $stmt = $platformPdo->prepare("
        SELECT * FROM diary_scrape_logs 
        WHERE tenant_id = ? AND status = 'running'
        ORDER BY started_at DESC 
        LIMIT 1
    ");
    $stmt->execute([$tenantId]);
    $runningLog = $stmt->fetch();
    
    if ($runningLog) {
        echo json_encode([
            'status' => 'running',
            'posts_count' => $runningLog['posts_saved'],
            'pages_processed' => $runningLog['pages_processed']
        ]);
    } else {
        // 最後に完了したログを取得
        $stmt = $platformPdo->prepare("
            SELECT * FROM diary_scrape_logs 
            WHERE tenant_id = ?
            ORDER BY started_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$tenantId]);
        $lastLog = $stmt->fetch();
        
        if ($lastLog && $lastLog['status'] === 'success') {
            echo json_encode([
                'status' => 'completed',
                'posts_count' => $lastLog['posts_saved']
            ]);
        } else {
            echo json_encode([
                'status' => 'idle'
            ]);
        }
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error' => $e->getMessage()
    ]);
}
