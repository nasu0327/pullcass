<?php
/**
 * テナント判別デバッグスクリプト
 * 現在どちらのテナントとして認識されているかを表示します。
 */
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/tenant.php';

// エラー詳細表示
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Tenant Resolution Debug</h1>";

echo "<h2>1. Request Info</h2>";
echo "HTTP_HOST: " . htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'N/A') . "<br>";
echo "REQUEST_URI: " . htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'N/A') . "<br>";

echo "<h2>2. Resolution Process</h2>";

// URLパラメータ
$paramTenant = isset($_GET['tenant']) ? getTenantByCode($_GET['tenant']) : null;
echo "getTenantByCode(\$_GET['tenant']): " . ($paramTenant ? "Found ({$paramTenant['code']})" : "NULL") . "<br>";

// サブドメイン
$subdomainTenant = getTenantBySubdomain($_SERVER['HTTP_HOST']);
echo "getTenantBySubdomain: " . ($subdomainTenant ? "Found ({$subdomainTenant['code']})" : "NULL") . "<br>";

// カスタムドメイン
$domainTenant = getTenantByDomain($_SERVER['HTTP_HOST']);
echo "getTenantByDomain: " . ($domainTenant ? "Found ({$domainTenant['code']})" : "NULL") . "<br>";

// 最終的な判定
$tenantFromRequest = getTenantFromRequest();
echo "<strong>getTenantFromRequest() Result:</strong> " . ($tenantFromRequest ? "Found (ID: {$tenantFromRequest['id']}, Code: {$tenantFromRequest['code']})" : "NULL") . "<br>";

echo "<h2>3. Session Info</h2>";
$sessionTenant = $_SESSION['current_tenant'] ?? null;
echo "Session 'current_tenant': " . ($sessionTenant ? "ID: {$sessionTenant['id']}, Code: {$sessionTenant['code']}" : "NULL") . "<br>";

echo "<h2>4. Database Check</h2>";
$pdo = getPlatformDb();
$stmt = $pdo->query("SELECT id, code, name, domain FROM tenants");
echo "<table border='1'><tr><th>ID</th><th>Code</th><th>Name</th><th>Domain</th></tr>";
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td>{$row['code']}</td>";
    echo "<td>{$row['name']}</td>";
    echo "<td>{$row['domain']}</td>";
    echo "</tr>";
}
echo "</table>";
