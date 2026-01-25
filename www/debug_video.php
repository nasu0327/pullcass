<?php
require_once __DIR__ . '/includes/bootstrap.php';

// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Debug Info</h1>";

// 1. Check Document Root and File Existence
echo "<h2>1. File System Check</h2>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "<br>";
$castInfoPath = __DIR__ . '/app/front/cast/get_cast_info.php';
echo "Checking cast/get_cast_info.php: " . (file_exists($castInfoPath) ? "EXISTS" : "MISSING") . " (" . $castInfoPath . ")<br>";

$thumbDir = __DIR__ . '/img/tenants/2/movie';
echo "Checking img/tenants/2/movie: " . (is_dir($thumbDir) ? "EXISTS" : "MISSING") . "<br>";
$files = glob($thumbDir . '/*');
echo "Files in movie dir: " . count($files) . "<br>";
foreach ($files as $f) {
    echo basename($f) . "<br>";
}

// 2. Check Database - Layout Sections
echo "<h2>2. Database - Published Layout</h2>";
try {
    $pdo = getPlatformDb();

    // Get Tenant ID (Targeting ID 2 per user feedback)
    $stmt = $pdo->prepare("SELECT * FROM tenants WHERE id = 2");
    $stmt->execute();
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tenant) {
        echo "Tenant ID 2 not found! Loading first available...<br>";
        $stmt = $pdo->query("SELECT * FROM tenants LIMIT 1");
        $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    $tenantId = $tenant['id'];
    echo "Tenant ID: " . $tenantId . " (" . $tenant['name'] . ")<br>";

    // Also verify directory for THIS tenant
    $thumbDir = __DIR__ . '/img/tenants/' . $tenantId . '/movie';
    echo "Checking " . str_replace(__DIR__, '', $thumbDir) . ": " . (is_dir($thumbDir) ? "EXISTS" : "MISSING") . "<br>";

    $stmt = $pdo->prepare("SELECT * FROM top_layout_sections_published WHERE tenant_id = ? ORDER BY id");
    $stmt->execute([$tenantId]);
    $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table border='1'><tr><th>ID</th><th>Key</th><th>Type</th><th>Visible</th><th>MobileOrder</th><th>PC_Left</th></tr>";
    foreach ($sections as $s) {
        echo "<tr>";
        echo "<td>{$s['id']}</td>";
        echo "<td>{$s['section_key']}</td>";
        echo "<td>{$s['section_type']}</td>";
        echo "<td>{$s['is_visible']}</td>";
        echo "<td>{$s['mobile_order']}</td>";
        echo "<td>{$s['pc_left_order']}</td>";
        echo "</tr>";
    }
    echo "</table>";

    // 3. Check Cast Video Info
    echo "<h2>3. Cast Video Info</h2>";
    $stmt = $pdo->prepare("SELECT id, name, movie_1_thumbnail, movie_1_seo_thumbnail FROM tenant_casts WHERE tenant_id = ? AND movie_1 IS NOT NULL LIMIT 5");
    $stmt->execute([$tenantId]);
    $casts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($casts as $c) {
        echo "ID: {$c['id']} Name: {$c['name']}<br>";
        echo "Thumb: {$c['movie_1_thumbnail']}<br>";
        echo "SEO: {$c['movie_1_seo_thumbnail']}<br>";
        echo "<hr>";
    }

} catch (Exception $e) {
    echo "DB Error: " . $e->getMessage();
}
