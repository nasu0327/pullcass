<?php
/**
 * pullcass - ブートストラップ
 * アプリケーション初期化
 */

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

// セッション開始
if (session_status() === PHP_SESSION_NONE) {
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
