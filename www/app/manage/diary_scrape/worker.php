<?php
/**
 * 写メ日記スクレイピング実行ワーカー（バックグラウンド実行）
 */

// CLI専用
if (php_sapi_name() !== 'cli') {
    // Webからも実行可能にする（テスト用）
    set_time_limit(0);
    ini_set('max_execution_time', 0);
}

// 引数チェック
if ($argc < 3) {
    die("Usage: php worker.php <tenant_id> <log_id>\n");
}

$tenantId = (int)$argv[1];
$logId = (int)$argv[2];

require_once __DIR__ . '/../../../includes/database.php';
require_once __DIR__ . '/includes/scraper.php';

try {
    $platformPdo = getPlatformDb();
    
    // 設定取得
    $stmt = $platformPdo->prepare("SELECT * FROM diary_scrape_settings WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $settings = $stmt->fetch();
    
    if (!$settings) {
        throw new Exception('設定が見つかりません');
    }
    
    // スクレイパー実行
    $scraper = new DiaryScraper($tenantId, $settings, $platformPdo);
    $result = $scraper->execute();
    
    // ログ更新
    $stmt = $platformPdo->prepare("
        UPDATE diary_scrape_logs SET
            finished_at = NOW(),
            execution_time = TIMESTAMPDIFF(SECOND, started_at, NOW()),
            status = ?,
            pages_processed = ?,
            posts_found = ?,
            posts_saved = ?,
            posts_skipped = ?,
            errors_count = ?,
            error_message = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $result['status'],
        $result['pages_processed'],
        $result['posts_found'],
        $result['posts_saved'],
        $result['posts_skipped'],
        $result['errors_count'],
        $result['error_message'] ?? null,
        $logId
    ]);
    
    // 設定の最終実行情報を更新
    $stmt = $platformPdo->prepare("
        UPDATE diary_scrape_settings SET
            last_executed_at = NOW(),
            last_execution_status = ?,
            last_posts_count = ?,
            total_posts_scraped = total_posts_scraped + ?
        WHERE tenant_id = ?
    ");
    $stmt->execute([
        $result['status'],
        $result['posts_saved'],
        $result['posts_saved'],
        $tenantId
    ]);
    
    echo "完了: {$result['posts_saved']}件取得\n";
    
} catch (Exception $e) {
    // エラーログ更新
    if (isset($platformPdo) && isset($logId)) {
        $stmt = $platformPdo->prepare("
            UPDATE diary_scrape_logs SET
                finished_at = NOW(),
                execution_time = TIMESTAMPDIFF(SECOND, started_at, NOW()),
                status = 'error',
                error_message = ?
            WHERE id = ?
        ");
        $stmt->execute([$e->getMessage(), $logId]);
    }
    
    echo "エラー: " . $e->getMessage() . "\n";
    exit(1);
}
