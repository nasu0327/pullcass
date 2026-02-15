<?php
/**
 * 写メ日記スクレイピング実行ワーカー（バックグラウンド実行）
 * CLIから呼び出される
 */

// タイムアウト無効化
set_time_limit(0);
ini_set('max_execution_time', 0);

// 引数チェック
if (php_sapi_name() === 'cli') {
    if ($argc < 3) {
        die("Usage: php worker.php <tenant_id> <log_id>\n");
    }
    $tenantId = (int)$argv[1];
    $logId = (int)$argv[2];
} else {
    die("CLIから実行してください\n");
}

// ブートストラップ読み込み
require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/includes/scraper.php';

try {
    $platformPdo = getPlatformDb();
    
    if (!$platformPdo) {
        throw new Exception('データベース接続に失敗しました');
    }
    
    // 設定取得
    $stmt = $platformPdo->prepare("SELECT * FROM diary_scrape_settings WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $settings = $stmt->fetch();
    
    if (!$settings) {
        throw new Exception('設定が見つかりません');
    }
    
    // スクレイパー実行
    $scraper = new DiaryScraper($tenantId, $settings, $platformPdo, $logId);
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
    echo "エラー: " . $e->getMessage() . "\n";
    
    // エラーログ更新
    if (isset($platformPdo) && isset($logId)) {
        try {
            $stmt = $platformPdo->prepare("
                UPDATE diary_scrape_logs SET
                    finished_at = NOW(),
                    execution_time = TIMESTAMPDIFF(SECOND, started_at, NOW()),
                    status = 'error',
                    error_message = ?
                WHERE id = ?
            ");
            $stmt->execute([$e->getMessage(), $logId]);
        } catch (Exception $e2) {
            // 無視
        }
    }
    
    exit(1);
}
