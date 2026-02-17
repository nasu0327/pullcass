<?php
/**
 * 口コミスクレイピング実行ワーカー（バックグラウンド実行）
 */

set_time_limit(0);
ini_set('max_execution_time', 0);

if (php_sapi_name() === 'cli') {
    if ($argc < 3) {
        die("Usage: php worker.php <tenant_id> <log_id>\n");
    }
    $tenantId = (int)$argv[1];
    $logId = (int)$argv[2];
} else {
    die("CLIから実行してください\n");
}

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/includes/scraper.php';

try {
    $platformPdo = getPlatformDb();
    if (!$platformPdo) {
        throw new Exception('データベース接続に失敗しました');
    }

    $stmt = $platformPdo->prepare("SELECT * FROM review_scrape_settings WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $settings = $stmt->fetch();
    if (!$settings) {
        throw new Exception('設定が見つかりません');
    }

    $scraper = new ReviewScraper($tenantId, $settings, $platformPdo, $logId);
    $result = $scraper->execute();

    $stmt = $platformPdo->prepare("
        UPDATE review_scrape_logs SET
            finished_at = NOW(),
            execution_time = TIMESTAMPDIFF(SECOND, started_at, NOW()),
            status = ?,
            pages_processed = ?,
            reviews_found = ?,
            reviews_saved = ?,
            reviews_skipped = ?,
            errors_count = ?,
            error_message = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $result['status'],
        $result['pages_processed'],
        $result['reviews_found'],
        $result['reviews_saved'],
        $result['reviews_skipped'],
        $result['errors_count'],
        $result['error_message'] ?? null,
        $logId
    ]);

    $stmt = $platformPdo->prepare("
        UPDATE review_scrape_settings SET
            last_executed_at = NOW(),
            last_execution_status = ?,
            last_reviews_count = ?,
            total_reviews_scraped = total_reviews_scraped + ?
        WHERE tenant_id = ?
    ");
    $stmt->execute([
        $result['status'],
        $result['reviews_saved'],
        $result['reviews_saved'],
        $tenantId
    ]);

} catch (Exception $e) {
    if (isset($platformPdo, $logId)) {
        try {
            $stmt = $platformPdo->prepare("
                UPDATE review_scrape_logs SET
                    finished_at = NOW(),
                    execution_time = TIMESTAMPDIFF(SECOND, started_at, NOW()),
                    status = 'error',
                    error_message = ?
                WHERE id = ?
            ");
            $stmt->execute([$e->getMessage(), $logId]);
        } catch (Exception $e2) {}
    }
    exit(1);
}
