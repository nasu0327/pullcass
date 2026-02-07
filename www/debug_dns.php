<?php
/**
 * DNS設定確認用（一時ファイル：確認後に削除すること）
 */
header('Content-Type: text/plain; charset=UTF-8');

echo "=== pullcass.com DNS 設定確認 ===\n\n";

echo "--- MX レコード ---\n";
$mx = dns_get_record('pullcass.com', DNS_MX);
if ($mx) {
    foreach ($mx as $r) {
        echo "  優先度 {$r['pri']}: {$r['target']}\n";
    }
} else {
    echo "  ★ MXレコードが見つかりません！\n";
}

echo "\n--- TXT レコード（SPF等） ---\n";
$txt = dns_get_record('pullcass.com', DNS_TXT);
if ($txt) {
    foreach ($txt as $r) {
        echo "  {$r['txt']}\n";
    }
} else {
    echo "  ★ TXTレコードが見つかりません！\n";
}

echo "\n--- DMARC レコード ---\n";
$dmarc = dns_get_record('_dmarc.pullcass.com', DNS_TXT);
if ($dmarc) {
    foreach ($dmarc as $r) {
        echo "  {$r['txt']}\n";
    }
} else {
    echo "  DMARCレコードなし\n";
}

echo "\n--- A レコード ---\n";
$a = dns_get_record('pullcass.com', DNS_A);
if ($a) {
    foreach ($a as $r) {
        echo "  {$r['ip']}\n";
    }
}

echo "\n--- NS レコード ---\n";
$ns = dns_get_record('pullcass.com', DNS_NS);
if ($ns) {
    foreach ($ns as $r) {
        echo "  {$r['target']}\n";
    }
}

echo "\n=== SMTP接続テスト（Xserver） ===\n";
$host = 'sv14162.xserver.jp';
$port = 587;
$errno = 0;
$errstr = '';
$socket = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 10);
if ($socket) {
    $greeting = fgets($socket, 8192);
    echo "  接続OK: " . trim($greeting) . "\n";
    fclose($socket);
} else {
    echo "  ★ 接続失敗: {$errstr}\n";
}
