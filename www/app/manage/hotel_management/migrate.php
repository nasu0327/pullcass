<?php
/**
 * JSONデータ移行スクリプト
 * https://club-houman.com/scripts/get_hotels.php からデータを取得し、DBに保存する。
 */
require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php'; // 管理者認証用

// ログインチェック（テナント管理者以上）
requireTenantAdminLogin();

$pdo = getPlatformDb();

// JSONデータの取得
$jsonUrl = 'https://club-houman.com/scripts/get_hotels.php';
echo "Fetching JSON from {$jsonUrl}...\n";
$json = file_get_contents($jsonUrl);

if (!$json) {
    die("Error: Failed to fetch JSON data.");
}

$hotels = json_decode($json, true);

if (!$hotels) {
    die("Error: Failed to decode JSON data.");
}

echo "Found " . count($hotels) . " hotels.\n";

// 移行前にテーブルをクリアする（ユーザーの要望：一旦削除して再投入）
$pdo->exec("TRUNCATE TABLE hotels");

$count = 0;
$stmt = $pdo->prepare("
    INSERT INTO hotels (
        name, symbol, area, address, phone, cost, method, is_love_hotel, 
        lat, lng, hotel_description, sort_order
    ) VALUES (
        :name, :symbol, :area, :address, :phone, :cost, :method, :is_love_hotel, 
        :lat, :lng, :hotel_description, :sort_order
    ) ON DUPLICATE KEY UPDATE
        symbol = VALUES(symbol),
        area = VALUES(area),
        address = VALUES(address),
        phone = VALUES(phone),
        cost = VALUES(cost),
        method = VALUES(method),
        is_love_hotel = VALUES(is_love_hotel),
        hotel_description = VALUES(hotel_description)
");

foreach ($hotels as $hotel) {
    // データ整形
    $name = $hotel['name'] ?? '';
    // nameが空の場合はスキップ
    if (empty($name))
        continue;

    $symbol = $hotel['symbol'] ?? '';
    $area = $hotel['area'] ?? '';
    $address = $hotel['address'] ?? '';
    $phone = $hotel['phone'] ?? '';
    $cost = $hotel['cost'] ?? '';
    $method = $hotel['method'] ?? '';

    // ラブホテル判定 (is_love_hotelフラグまたはareaなどから判定)
    $isLoveHotel = 0;
    if (isset($hotel['is_love_hotel']) && $hotel['is_love_hotel']) {
        $isLoveHotel = 1;
    }

    // lat/lng (もしJSONにあれば)
    $lat = $hotel['lat'] ?? null;
    $lng = $hotel['lng'] ?? null;

    // description
    $description = $hotel['hotel_description'] ?? '';

    // 並び順
    $sortOrder = $count * 10;

    $stmt->execute([
        ':name' => $name,
        ':symbol' => $symbol,
        ':area' => $area,
        ':address' => $address,
        ':phone' => $phone,
        ':cost' => $cost,
        ':method' => $method,
        ':is_love_hotel' => $isLoveHotel,
        ':lat' => $lat,
        ':lng' => $lng,
        ':hotel_description' => $description,
        ':sort_order' => $sortOrder
    ]);
    $count++;
}

echo "Migration completed. {$count} record(s) processed.";
