<?php
/**
 * pullcass - メール送信ヘルパー
 * MAIL_HOST が設定されている場合は SMTP（AWS SES 等）で送信、未設定時は mail() に任せる
 */

/**
 * SMTP でメールを送信する（MAIL_HOST が設定されている場合に使用）
 *
 * @param string $to 宛先メールアドレス
 * @param string $subject 件名
 * @param string $body 本文（UTF-8）
 * @param array $headers ヘッダー配列（例: ['From' => '...', 'Reply-To' => '...']）
 * @return bool 送信成功時 true
 */
function send_mail_via_smtp($to, $subject, $body, array $headers = [])
{
    $host = getenv('MAIL_HOST');
    if (empty($host)) {
        return false;
    }

    $port = (int)(getenv('MAIL_PORT') ?: 587);
    $username = getenv('MAIL_USERNAME') ?: '';
    $password = getenv('MAIL_PASSWORD') ?: '';
    $encryption = strtolower(getenv('MAIL_ENCRYPTION') ?: 'tls');

    $errno = 0;
    $errstr = '';
    $timeout = 15;

    $context = stream_context_create();
    $socket = @stream_socket_client(
        'tcp://' . $host . ':' . $port,
        $errno,
        $errstr,
        $timeout,
        STREAM_CLIENT_CONNECT,
        $context
    );

    if (!$socket) {
        error_log("Reservation mail SMTP: connection failed to {$host}:{$port} - {$errstr}");
        return false;
    }

    stream_set_timeout($socket, $timeout);

    $readResponse = function () use ($socket) {
        $line = '';
        while ($buf = fgets($socket, 8192)) {
            $line .= $buf;
            if (isset($buf[3]) && $buf[3] === ' ') {
                break;
            }
        }
        return $line;
    };

    $send = function ($cmd) use ($socket, $readResponse) {
        if (fwrite($socket, $cmd . "\r\n") === false) {
            return false;
        }
        return $readResponse();
    };

    $response = $readResponse();
    if (strpos($response, '220') !== 0) {
        error_log("Reservation mail SMTP: unexpected greeting: " . trim($response));
        fclose($socket);
        return false;
    }

    $send('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
    if ($encryption === 'tls' && $port === 587) {
        $resp = $send('STARTTLS');
        if (strpos($resp, '220') !== 0) {
            fclose($socket);
            return false;
        }
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            error_log("Reservation mail SMTP: TLS failed");
            fclose($socket);
            return false;
        }
        $send('EHLO ' . ($_SERVER['SERVER_NAME'] ?? 'localhost'));
    }

    if ($username !== '' && $password !== '') {
        $send('AUTH LOGIN');
        $send(base64_encode($username));
        $resp = $send(base64_encode($password));
        if (strpos($resp, '235') !== 0) {
            error_log("Reservation mail SMTP: auth failed");
            fclose($socket);
            return false;
        }
    }

    $fromHeader = $headers['From'] ?? 'noreply@pullcass.com';
    if (preg_match('/<([^>]+)>/', $fromHeader, $m)) {
        $fromAddr = trim($m[1]);
    } else {
        $fromAddr = trim($fromHeader);
    }

    $send('MAIL FROM:<' . $fromAddr . '>');
    $rcptResp = $send('RCPT TO:<' . $to . '>');
    if (strpos($rcptResp, '250') !== 0) {
        error_log("Reservation mail SMTP: RCPT TO rejected (e.g. SES sandbox: verify recipient) - " . trim($rcptResp));
        fclose($socket);
        return false;
    }
    $resp = $send('DATA');
    if (strpos($resp, '354') !== 0) {
        error_log("Reservation mail SMTP: DATA command rejected - " . trim($resp));
        fclose($socket);
        return false;
    }

    $msg = 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n";
    foreach ($headers as $k => $v) {
        $msg .= $k . ': ' . $v . "\r\n";
    }
    $msg .= "MIME-Version: 1.0\r\n";
    $msg .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $msg .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $msg .= chunk_split(base64_encode($body));
    $msg .= "\r\n.\r\n";

    if (fwrite($socket, $msg) === false) {
        fclose($socket);
        return false;
    }
    $resp = $readResponse();
    fwrite($socket, "QUIT\r\n");
    fclose($socket);

    if (strpos($resp, '250') !== 0) {
        error_log("Reservation mail SMTP: DATA response not 250 - " . trim($resp));
        return false;
    }
    return true;
}

/**
 * メールを送信する（SMTP 設定があれば SMTP、なければ mb_send_mail）
 *
 * @param string $to 宛先
 * @param string $subject 件名
 * @param string $body 本文
 * @param string $headerStr 追加ヘッダー（改行区切り文字列）
 * @return bool 送信成功時 true
 */
function send_reservation_mail($to, $subject, $body, $headerStr)
{
    if (getenv('MAIL_HOST')) {
        $headers = [];
        foreach (preg_split('/\r?\n/', $headerStr) as $line) {
            $line = trim($line);
            if ($line !== '' && strpos($line, ':') !== false) {
                list($k, $v) = explode(':', $line, 2);
                $headers[trim($k)] = trim($v);
            }
        }
        return send_mail_via_smtp($to, $subject, $body, $headers);
    }
    return @mb_send_mail($to, $subject, $body, $headerStr);
}
