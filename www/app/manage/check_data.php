<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
$pdo = getPlatformDb();

if (!$pdo) {
    die("Database connection failed.");
}

// 1. JSON取得
$jsonUrl = 'https://club-houman.com/scripts/get_hotels.php';
$json = file_get_contents($jsonUrl);
$jsonHotels = json_decode($json, true);
$jsonCount = count($jsonHotels);

// 2. DB取得
$stmt = $pdo->query("SELECT * FROM hotels");
$dbHotels = $stmt->fetchAll();
$dbCount = count($dbHotels);

echo "JSON Count: $jsonCount\n";
echo "DB Count: $dbCount\n";

if ($jsonCount !== $dbCount) {
    echo "Warning: Record count mismatch!\n";
}

// 3. エリアごとの比較
$jsonAreas = [];
foreach ($jsonHotels as $h) {
    $area = $h['area'] ?? 'Unknown';
    $jsonAreas[$area] = ($jsonAreas[$area] ?? 0) + 1;
}

$dbAreas = [];
foreach ($dbHotels as $h) {
    $area = $h['area'] ?? 'Unknown';
    $dbAreas[$area] = ($dbAreas[$area] ?? 0) + 1;
}

echo "\nArea Comparison (JSON vs DB):\n";
$allAreas = array_unique(array_merge(array_keys($jsonAreas), array_keys($dbAreas)));
sort($allAreas);

foreach ($allAreas as $area) {
    $jc = $jsonAreas[$area] ?? 0;
    $dc = $dbAreas[$area] ?? 0;
    $diff = ($jc !== $dc) ? " [MISMATCH]" : "";
    echo sprintf("%-20s: JSON=%3d, DB=%3d %s\n", $area, $jc, $dc, $diff);
}

// 4. 重複名のチェック
echo "\nDuplicate Names in JSON:\n";
$names = [];
foreach ($jsonHotels as $h) {
    $name = trim($h['name']);
    $names[$name] = ($names[$name] ?? 0) + 1;
}
foreach ($names as $name => $c) {
    if ($c > 1) {
        echo "- $name: $c times\n";
    }
}
