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

// エリア一覧取得
$areas = $pdo->query("SELECT DISTINCT area FROM hotels WHERE area IS NOT NULL AND area != '' ORDER BY area")->fetchAll(PDO::FETCH_COLUMN);

$xlsx = new SimpleXLSXGen();
$hasSheets = false;

// ヘッダー行
$header = ['案内種別', 'ホテル名', '交通費', '電話番号', '住所', '案内方法', 'ホテル詳細', 'URL'];

foreach ($areas as $area) {
    // データ取得
    $stmt = $pdo->prepare("SELECT * FROM hotels WHERE area = ? ORDER BY sort_order ASC, id DESC");
    $stmt->execute([$area]);
    $hotels = $stmt->fetchAll();

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
