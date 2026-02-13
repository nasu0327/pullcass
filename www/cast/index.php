<?php
/**
 * キャスト個人ページ短縮URLルーター
 * /cast/129 → /app/front/cast/detail.php?id=129
 */

$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);

// /cast/数字 のパターンを抽出
if (preg_match('#^/cast/(\d+)$#', $path, $matches)) {
    $_GET['id'] = (int) $matches[1];
    require __DIR__ . '/../app/front/cast/detail.php';
    exit;
}

// パターンに一致しない場合はキャスト一覧へ
header('Location: /app/front/cast/list.php');
exit;
