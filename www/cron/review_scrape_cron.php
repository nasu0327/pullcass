<?php
/**
 * 口コミスクレイピング 定期実行スクリプト
 * is_enabled = 1 のテナントで、last_executed_at から10分以上経過したものを実行
 *
 * crontab例:
 * * * * * * /usr/bin/php /var/www/pullcass/www/cron/review_scrape_cron.php >> /var/log/pullcass_review_scrape.log 2>&1
 */

if (php_sapi_name() !== 'cli') {
    die("このスクリプトはCLIからのみ実行できます\n");
}

date_default_timezone_set('Asia/Tokyo');
set_time_limit(300);
ini_set('memory_limit', '256M');

$lockFile = '/tmp/pullcass_review_scrape.lock';
if (file_exists($lockFile)) {
    $elapsed = time() - filemtime($lockFile);
    if ($elapsed > 600) {
        @unlink($lockFile);
    } else {
        exit(0);
    }
}
touch($lockFile);

try {
    require_once __DIR__ . '/../includes/bootstrap.php';
    $pdo = getPlatformDb();
    if (!$pdo) throw new Exception("データベース接続に失敗しました");

    $stmt = $pdo->prepare("
        SELECT rss.tenant_id, rss.last_executed_at, t.name AS tenant_name
        FROM review_scrape_settings rss
        JOIN tenants t ON t.id = rss.tenant_id AND t.is_active = 1
        WHERE rss.is_enabled = 1
          AND rss.reviews_base_url != ''
          AND (rss.last_executed_at IS NULL OR rss.last_executed_at <= DATE_SUB(NOW(), INTERVAL 10 MINUTE))
        ORDER BY rss.last_executed_at ASC
    ");
    $stmt->execute();
    $targets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($targets)) {
        @unlink($lockFile);
        exit(0);
    }

    $workerPath = __DIR__ . '/../app/manage/review_scrape/worker.php';
    $executed = 0;
    foreach ($targets as $target) {
        $tenantId = $target['tenant_id'];
        $runningCheck = $pdo->prepare("
            SELECT id FROM review_scrape_logs
            WHERE tenant_id = ? AND status = 'running' AND started_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE) LIMIT 1
        ");
        $runningCheck->execute([$tenantId]);
        if ($runningCheck->fetch()) continue;

        $logStmt = $pdo->prepare("INSERT INTO review_scrape_logs (tenant_id, execution_type, started_at, status) VALUES (?, 'cron', NOW(), 'running')");
        $logStmt->execute([$tenantId]);
        $logId = $pdo->lastInsertId();

        $command = sprintf('nohup /usr/bin/php %s %d %d > /dev/null 2>&1 &', escapeshellarg($workerPath), (int)$tenantId, (int)$logId);
        exec($command);
        $executed++;
        sleep(1);
    }
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] エラー: " . $e->getMessage() . "\n";
} finally {
    @unlink($lockFile);
}
