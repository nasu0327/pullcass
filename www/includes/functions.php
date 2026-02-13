<?php
/**
 * pullcass - 共通関数
 */

/**
 * HTMLエスケープ
 */
function h($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * CSRFトークン生成
 */
function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * CSRFトークン検証
 */
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * JSONレスポンス
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * リダイレクト
 */
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

/**
 * 現在のURLを取得
 */
function getCurrentUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    return $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

/**
 * フラッシュメッセージを設定
 */
function setFlash($type, $message) {
    $_SESSION['flash'][$type] = $message;
}

/**
 * フラッシュメッセージを取得（取得後削除）
 */
function getFlash($type) {
    $message = $_SESSION['flash'][$type] ?? null;
    unset($_SESSION['flash'][$type]);
    return $message;
}

/**
 * キャスト個人ページのURL生成
 * @param int $castId キャストID
 * @param string|null $from 遷移元セクション（'new','today','ranking','video','schedule','list'）
 */
function castDetailUrl($castId, $from = null) {
    $url = '/cast/?id=' . (int) $castId;
    if ($from) {
        $url .= '&from=' . urlencode($from);
    }
    return $url;
}

/**
 * デバッグ出力
 */
function dd($data) {
    if (APP_DEBUG) {
        echo '<pre>';
        var_dump($data);
        echo '</pre>';
        exit;
    }
}
