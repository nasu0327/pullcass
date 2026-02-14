<?php
/**
 * キャスト個人ページ短縮URLルーター
 * /cast/?id=129 → /app/front/cast/detail.php?id=129
 */

// ?id=数字 でキャスト詳細ページを表示
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $_GET['id'] = (int) $_GET['id'];
    require __DIR__ . '/../app/front/cast/detail.php';
    exit;
}

// IDなしの場合はキャスト一覧へ
header('Location: /cast/list');
exit;
