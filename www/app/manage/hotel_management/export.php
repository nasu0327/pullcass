<?php
require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/lib/SimpleXLSXGen.php';

use Shuchkin\SimpleXLSXGen;

// ログイン認証
requireTenantAdminLogin();

$pdo = getPlatformDb();
$tenantSlug = $_GET['tenant'] ?? $_SESSION['manage_tenant_slug'] ?? null;
if (!$tenantSlug) {
    header('Location: /');
    exit;
}

// エリア一覧取得（タブ順はDBの取得順。エリア名はテナントごとの名称をそのまま使用）
$areas_stmt = $pdo->prepare("SELECT DISTINCT area FROM hotels WHERE tenant_id = ? AND area IS NOT NULL AND area != '' ORDER BY area");
$areas_stmt->execute([$tenantId]);
$areas = $areas_stmt->fetchAll(PDO::FETCH_COLUMN);

$xlsx = new SimpleXLSXGen();
$hasSheets = false;

// ヘッダー行
$header = ['案内種別', 'ホテル名', '交通費', '電話番号', '住所', '案内方法', 'ホテル詳細', 'URL'];

foreach ($areas as $area) {
    // データ取得（管理画面の並び順と同じ: sort_order昇順・id降順。先頭ホテルがシートの1行目に来る）
    $stmt = $pdo->prepare("SELECT * FROM hotels WHERE tenant_id = ? AND area = ? ORDER BY sort_order ASC, id DESC");
    $stmt->execute([$tenantId, $area]);
    $hotels = $stmt->fetchAll();
    // シート内の並びが逆になる環境では正順に揃える（先頭ホテル＝管理画面の1件目が上）
    $hotels = array_values(array_reverse($hotels));

    $data = [$header];
    foreach ($hotels as $hotel) {
        $data[] = [
            $hotel['symbol'],
            $hotel['name'],
            $hotel['cost'],
            $hotel['phone'],
            $hotel['address'],
            $hotel['method'],
            $hotel['hotel_description'],
            '' // URLカラム（空欄）
        ];
    }

    // シート名は最大31文字（Excel制限）
    $sheetName = mb_strimwidth($area, 0, 31, '...');
    $xlsx->addSheet($data, $sheetName);
    $hasSheets = true;
}

// データがない場合のフォールバック（空シート）
if (!$hasSheets) {
    $xlsx->addSheet([$header], 'Sheet1');
}

// ファイルダウンロード
$filename = 'hotel_list_' . date('Ymd_His') . '.xlsx';
$xlsx->downloadAs($filename);
exit;
