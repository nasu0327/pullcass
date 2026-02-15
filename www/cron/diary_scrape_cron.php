<?php
/**
 * 写メ日記スクレイピング 定期実行スクリプト
 * 
 * 仕組み:
 * - cronで毎分実行される
 * - 各テナントの last_executed_at を確認し、10分以上経過したテナントのみ実行
 * - テナントごとに手動実行のタイミングが異なるため、自然に分散される
 * - is_enabled = 1 のテナントのみ対象
 * 
 * crontab設定:
 * * * * * * /usr/bin/php /var/www/pullcass/www/cron/diary_scrape_cron.php >> /var/log/pullcass_diary_scrape.log 2>&1
 */

// CLI実行チェック
if (php_sapi_name() !== 'cli') {
    die("このスクリプトはCLIからのみ実行できます\n");
}

// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');

// タイムアウト設定
set_time_limit(300); // 5分（毎分起動なので短めに）
ini_set('memory_limit', '256M');

// ロックファイル（同時実行防止）
$lockFile = '/tmp/pullcass_diary_scrape.lock';

// 既に実行中かチェック
if (file_exists($lockFile)) {
    $lockTime = filemtime($lockFile);
    $elapsed = time() - $lockTime;
    
    // 10分以上経過していたらロック解除（異常終了対策）
    if ($elapsed > 600) {
        @unlink($lockFile);
        echo "[" . date('Y-m-d H:i:s') . "] 古いロックファイルを削除しました（{$elapsed}秒経過）\n";
    } else {
        // 前回のcron実行がまだ処理中 → 正常なのでサイレントスキップ
        exit(0);
    }
}

// ロックファイル作成
touch($lockFile);

try {
    // DB接続
    require_once __DIR__ . '/../includes/bootstrap.php';
    $pdo = getPlatformDb();
    
    if (!$pdo) {
        throw new Exception("データベース接続に失敗しました");
    }
    
    // 実行対象テナントを取得
    // 条件: is_enabled = 1 かつ last_executed_at から10分以上経過（またはNULL）
    $stmt = $pdo->prepare("
        SELECT 
            dss.tenant_id,
            dss.last_executed_at,
            t.name AS tenant_name,
            t.code AS tenant_code
        FROM diary_scrape_settings dss
        JOIN tenants t ON t.id = dss.tenant_id AND t.is_active = 1
        WHERE dss.is_enabled = 1
          AND dss.cityheaven_login_id != ''
          AND dss.shop_url != ''
          AND (
              dss.last_executed_at IS NULL
              OR dss.last_executed_at <= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
          )
        ORDER BY dss.last_executed_at ASC
    ");
    $stmt->execute();
    $targets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($targets)) {
        // 対象なし → サイレント終了
        @unlink($lockFile);
        exit(0);
    }
    
    $count = count($targets);
    echo "[" . date('Y-m-d H:i:s') . "] 写メ日記定期スクレイピング: {$count}件のテナントが対象\n";
    
    $executed = 0;
    
    foreach ($targets as $target) {
        $tenantId = $target['tenant_id'];
        $tenantName = $target['tenant_name'];
        
        // 既に実行中のタスクがないかチェック
        $runningCheck = $pdo->prepare("
            SELECT id FROM diary_scrape_logs 
            WHERE tenant_id = ? AND status = 'running'
            AND started_at > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
            LIMIT 1
        ");
        $runningCheck->execute([$tenantId]);
        if ($runningCheck->fetch()) {
            echo "  [{$tenantName}] 実行中のタスクあり → スキップ\n";
            continue;
        }
        
        // 実行ログ作成
        $logStmt = $pdo->prepare("
            INSERT INTO diary_scrape_logs (
                tenant_id, execution_type, started_at, status
            ) VALUES (?, 'cron', NOW(), 'running')
        ");
        $logStmt->execute([$tenantId]);
        $logId = $pdo->lastInsertId();
        
        // worker.phpをバックグラウンドで実行
        $workerPath = __DIR__ . '/../app/manage/diary_scrape/worker.php';
        $command = sprintf(
            'nohup /usr/bin/php %s %d %d > /dev/null 2>&1 &',
            escapeshellarg($workerPath),
            (int)$tenantId,
            (int)$logId
        );
        
        exec($command);
        $executed++;
        
        echo "  [{$tenantName}] (ID:{$tenantId}) スクレイピング開始 (log_id: {$logId})\n";
        
        // テナント間で1秒待機（CityHeavenへのアクセス分散）
        sleep(1);
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] 完了: {$executed}件実行\n";
    
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] エラー: " . $e->getMessage() . "\n";
} finally {
    // ロックファイル削除
    @unlink($lockFile);
}
