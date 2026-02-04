<?php
/**
 * pullcass - ブートストラップ
 * アプリケーション初期化
 */

// .env を読み込む（Docker の場合は環境変数で渡すため未使用でも可）
$envFile = dirname(dirname(__DIR__)) . '/.env';
if (is_readable($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            if ($name !== '') {
                if (preg_match('/^["\'](.+)["\']\s*$/', $value, $m)) {
                    $value = $m[1];
                }
                putenv($name . '=' . $value);
                $_ENV[$name] = $value;
            }
        }
    }
}

// エラー表示設定（開発環境）
$appEnv = getenv('APP_ENV') ?: 'development';
$appDebug = getenv('APP_DEBUG') ?: true;

if ($appDebug) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');

// セッション開始（サブドメイン間で共有するためにcookieドメインを設定）
if (session_status() === PHP_SESSION_NONE) {
    // 本番環境ではサブドメイン間でセッションを共有
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (strpos($host, 'pullcass.com') !== false) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '.pullcass.com',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
    session_start();
}

// 定数定義
define('APP_ROOT', dirname(__DIR__));
define('APP_ENV', $appEnv);
define('APP_DEBUG', $appDebug);

// 共通関数の読み込み
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/tenant.php';
