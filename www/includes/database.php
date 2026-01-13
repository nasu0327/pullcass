<?php
/**
 * pullcass - データベース接続
 */

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
        $host = getenv('DB_HOST') ?: 'localhost';
        $port = getenv('DB_PORT') ?: '3306';
        $dbname = getenv('DB_DATABASE') ?: 'pullcass';
        $username = getenv('DB_USERNAME') ?: 'pullcass';
        $password = getenv('DB_PASSWORD') ?: 'pullcass_secret';
        
        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_TIMEOUT => 2, // 2秒でタイムアウト
            ];
            $pdo = new PDO($dsn, $username, $password, $options);
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
        $host = getenv('DB_HOST') ?: 'mysql';
        $port = getenv('DB_PORT') ?: '3306';
        $username = getenv('DB_USERNAME') ?: 'pullcass';
        $password = getenv('DB_PASSWORD') ?: 'pullcass_secret';
        
        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $connections[$dbName] = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            if (APP_DEBUG) {
                die("テナントDB接続エラー ({$dbName}): " . $e->getMessage());
            }
            die("システムエラーが発生しました。");
        }
    }
    
    return $connections[$dbName];
}
