<?php
/**
 * デバッグログ確認用（一時ファイル：確認後に削除すること）
 */

$logFile = __DIR__ . '/../reservation_debug.log';

// ログファイルがなければ作成
if (!file_exists($logFile)) {
    file_put_contents($logFile, "[" . date("Y-m-d H:i:s") . "] log file created\n");
    chmod($logFile, 0666);
    echo "<p>ログファイルを作成しました。予約フォームから送信後、再度このページを開いてください。</p>";
    exit;
}

// ログを表示
header('Content-Type: text/plain; charset=UTF-8');
echo file_get_contents($logFile);
