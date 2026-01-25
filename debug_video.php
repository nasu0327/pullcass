<?php
// エラー表示
ini_set('display_errors', 1);
error_reporting(E_ALL);

// DB設定 (config.phpより)
$host = 'localhost';
$dbname = 'pullcass';
$username = 'root';
$password = 'nasu0327';

try {
    echo "Connecting to DB...\n";
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo "Connected successfully.\n";

    // テナントIDの取得（とりあえず最初の1つ）
    $stmt = $pdo->query("SELECT id, name FROM tenants LIMIT 1");
    $tenant = $stmt->fetch();
    if (!$tenant) {
        die("No tenants found.\n");
    }
    $tenantId = $tenant['id'];
    echo "Tenant: {$tenant['name']} (ID: $tenantId)\n";

    echo "\n=== Tenant Casts Video Data ===\n";
    $stmt = $pdo->prepare("SELECT id, name, movie_1, movie_2 FROM tenant_casts WHERE tenant_id = ? AND (movie_1 IS NOT NULL AND movie_1 != '' OR movie_2 IS NOT NULL AND movie_2 != '') LIMIT 5");
    $stmt->execute([$tenantId]);
    $casts = $stmt->fetchAll();

    if (empty($casts)) {
        echo "No casts with video data found for this tenant.\n";
    } else {
        echo count($casts) . " casts found with videos.\n";
        foreach ($casts as $cast) {
            echo "ID: {$cast['id']}, Name: {$cast['name']}\n";
            echo "  Movie 1: " . ($cast['movie_1'] ? $cast['movie_1'] : 'NULL') . "\n";
            echo "  Movie 2: " . ($cast['movie_2'] ? $cast['movie_2'] : 'NULL') . "\n";
        }
    }

    echo "\n=== Top Layout Sections for 'videos' ===\n";
    $stmt = $pdo->prepare("SELECT * FROM top_layout_sections WHERE tenant_id = ? AND section_key = 'videos'");
    $stmt->execute([$tenantId]);
    $sections = $stmt->fetchAll();

    if (empty($sections)) {
        echo "No 'videos' section found in top_layout_sections.\n";
    } else {
        foreach ($sections as $sec) {
            echo "ID: {$sec['id']}, Key: {$sec['section_key']}, Visible: {$sec['is_visible']}\n";
        }
    }

    echo "\n=== Top Layout Sections Published for 'videos' ===\n";
    $stmt = $pdo->prepare("SELECT * FROM top_layout_sections_published WHERE tenant_id = ? AND section_key = 'videos'");
    $stmt->execute([$tenantId]);
    $pubSections = $stmt->fetchAll();

    if (empty($pubSections)) {
        echo "No 'videos' section found in top_layout_sections_published.\n";
    } else {
        foreach ($pubSections as $sec) {
            echo "ID: {$sec['id']}, Key: {$sec['section_key']}, Visible: {$sec['is_visible']}\n";
        }
    }

} catch (PDOException $e) {
    echo "DB Connection Error: " . $e->getMessage() . "\n";
}
