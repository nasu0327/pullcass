<?php
// Verify table structure
$host = 'localhost';
$db = 'nasu0903_houman';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $stmt = $pdo->query("DESCRIBE hotels");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "Columns in 'hotels' table:\n";
    foreach ($columns as $col) {
        echo "- $col\n";
    }

} catch (\PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
