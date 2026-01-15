<?php
/**
 * 全テナント スクレイピング定期実行スクリプト
 * 
 * 機能:
 * - 全テナントをループして有効なサイトをスクレイピング
 * - 同時実行防止（ロック機能）
 * - ログ出力
 * 
 * 使用法:
 * php scraping_all.php
 * 
 * crontab設定例（30分おき）:
 * * /30 * * * * /usr/bin/php /var/www/pullcass/www/cron/scraping_all.php >> /var/log/pullcass_scraping.log 2>&1
 */

// CLI実行チェック
if (php_sapi_name() !== 'cli') {
    die("このスクリプトはCLIからのみ実行できます\n");
}

// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');

// タイムアウト設定
set_time_limit(3600); // 1時間
ini_set('memory_limit', '512M');

// ロックファイル（同時実行防止）
$lockFile = '/tmp/pullcass_scraping.lock';

// 既に実行中かチェック
if (file_exists($lockFile)) {
    $lockTime = filemtime($lockFile);
    $elapsed = time() - $lockTime;
    
    // 1時間以上経過していたらロック解除（異常終了対策）
    if ($elapsed > 3600) {
        unlink($lockFile);
        echo "[" . date('Y-m-d H:i:s') . "] 古いロックファイルを削除しました\n";
    } else {
        echo "[" . date('Y-m-d H:i:s') . "] 既に実行中です（{$elapsed}秒前に開始）。スキップします。\n";
        exit(0);
    }
}

// ロックファイル作成
touch($lockFile);

echo "========================================\n";
echo "[" . date('Y-m-d H:i:s') . "] 定期スクレイピング開始\n";
echo "========================================\n";

try {
    // DB接続
    require_once __DIR__ . '/../includes/bootstrap.php';
    $pdo = getPlatformDb();
    
    if (!$pdo) {
        throw new Exception("データベース接続に失敗しました");
    }
    
    // 全テナントを取得
    $stmt = $pdo->query("SELECT id, code, name FROM tenants WHERE is_active = 1 ORDER BY id");
    $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "対象テナント数: " . count($tenants) . "件\n\n";
    
    $sites = ['ekichika', 'heaven', 'dto'];
    $siteNames = [
        'ekichika' => '駅ちか',
        'heaven' => 'ヘブンネット',
        'dto' => 'デリヘルタウン'
    ];
    
    $totalSuccess = 0;
    $totalError = 0;
    
    foreach ($tenants as $tenant) {
        $tenantId = $tenant['id'];
        $tenantCode = $tenant['code'];
        $tenantName = $tenant['name'];
        
        echo "----------------------------------------\n";
        echo "[テナント] {$tenantName} (ID: {$tenantId})\n";
        echo "----------------------------------------\n";
        
        foreach ($sites as $site) {
            // 有効かどうかチェック
            $stmtEnabled = $pdo->prepare("SELECT config_value FROM tenant_scraping_config WHERE tenant_id = ? AND config_key = ?");
            $stmtEnabled->execute([$tenantId, $site . '_enabled']);
            $enabledResult = $stmtEnabled->fetch(PDO::FETCH_ASSOC);
            $enabled = !$enabledResult || $enabledResult['config_value'] !== '0';
            
            if (!$enabled) {
                echo "  [{$siteNames[$site]}] 停止中 - スキップ\n";
                continue;
            }
            
            // URLが設定されているかチェック
            $stmtUrl = $pdo->prepare("SELECT config_value FROM tenant_scraping_config WHERE tenant_id = ? AND config_key = ?");
            $stmtUrl->execute([$tenantId, $site . '_list_url']);
            $urlResult = $stmtUrl->fetch(PDO::FETCH_ASSOC);
            $url = $urlResult ? $urlResult['config_value'] : '';
            
            if (empty($url)) {
                echo "  [{$siteNames[$site]}] URL未設定 - スキップ\n";
                continue;
            }
            
            echo "  [{$siteNames[$site]}] スクレイピング開始...\n";
            
            // スクレイパーを実行
            $scraperFile = __DIR__ . "/../app/manage/cast_data/scraper_{$site}.php";
            
            if (!file_exists($scraperFile)) {
                echo "  [{$siteNames[$site]}] スクレイパーファイルが見つかりません: {$scraperFile}\n";
                $totalError++;
                continue;
            }
            
            // サブプロセスで実行
            $phpPath = '/usr/bin/php';
            $cmd = sprintf(
                "%s %s %d 2>&1",
                escapeshellarg($phpPath),
                escapeshellarg($scraperFile),
                $tenantId
            );
            
            $output = [];
            $returnCode = 0;
            exec($cmd, $output, $returnCode);
            
            if ($returnCode === 0) {
                echo "  [{$siteNames[$site]}] 完了\n";
                $totalSuccess++;
            } else {
                echo "  [{$siteNames[$site]}] エラー (code: {$returnCode})\n";
                $totalError++;
            }
            
            // サイト間で少し待機（サーバー負荷軽減）
            sleep(2);
        }
        
        echo "\n";
        
        // テナント間で少し待機
        sleep(1);
    }
    
    echo "========================================\n";
    echo "[" . date('Y-m-d H:i:s') . "] 定期スクレイピング完了\n";
    echo "成功: {$totalSuccess}件 / エラー: {$totalError}件\n";
    echo "========================================\n";
    
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] エラー: " . $e->getMessage() . "\n";
} finally {
    // ロックファイル削除
    if (file_exists($lockFile)) {
        unlink($lockFile);
    }
}
