<?php
/**
 * pullcass - 設定ファイル
 * 
 * サーバー環境に合わせて設定してください
 */

// データベース設定
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_DATABASE') ?: 'pullcass');
define('DB_USER', getenv('DB_USERNAME') ?: 'root');
define('DB_PASS', getenv('DB_PASSWORD') ?: 'nasu0327');
define('DB_CHARSET', 'utf8mb4');

// URL設定
define('APP_URL', getenv('APP_URL') ?: 'https://pullcass.com');
