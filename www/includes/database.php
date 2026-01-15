<?php
/**
 * pullcass - データベース接続
 */

// 設定ファイルを読み込み
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

/**
 * プラットフォームDB接続を取得
 */
function getPlatformDb() {
    static $pdo = null;
    static $connectionFailed = false;
    
    // 既に接続失敗している場合はnullを返す
    if ($connectionFailed) {
        return null;
    }
    
    if ($pdo === null) {
        $host = defined('DB_HOST') ? DB_HOST : (getenv('DB_HOST') ?: 'localhost');
        $port = getenv('DB_PORT') ?: '3306';
        $dbname = defined('DB_NAME') ? DB_NAME : (getenv('DB_DATABASE') ?: 'pullcass');
        $username = defined('DB_USER') ? DB_USER : (getenv('DB_USERNAME') ?: 'root');
        $password = defined('DB_PASS') ? DB_PASS : (getenv('DB_PASSWORD') ?: '');
        
        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 2, // 2秒でタイムアウト
            ];
            $pdo = new PDO($dsn, $username, $password, $options);
            
            // MySQLのタイムゾーンを日本時間に設定
            $pdo->exec("SET time_zone = '+09:00'");
        } catch (PDOException $e) {
            $connectionFailed = true;
            return null;
        }
    }
    
    return $pdo;
}

/**
 * テナントDB接続を取得
 */
function getTenantDb($dbName) {
    static $connections = [];
    
    if (!isset($connections[$dbName])) {
        $host = defined('DB_HOST') ? DB_HOST : (getenv('DB_HOST') ?: 'localhost');
        $port = getenv('DB_PORT') ?: '3306';
        $username = defined('DB_USER') ? DB_USER : (getenv('DB_USERNAME') ?: 'root');
        $password = defined('DB_PASS') ? DB_PASS : (getenv('DB_PASSWORD') ?: '');
        
        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $connections[$dbName] = new PDO($dsn, $username, $password, $options);
            
            // MySQLのタイムゾーンを日本時間に設定
            $connections[$dbName]->exec("SET time_zone = '+09:00'");
        } catch (PDOException $e) {
            $debug = defined('APP_DEBUG') ? APP_DEBUG : false;
            if ($debug) {
                die("テナントDB接続エラー ({$dbName}): " . $e->getMessage());
            }
            die("システムエラーが発生しました。");
        }
    }
    
    return $connections[$dbName];
}
