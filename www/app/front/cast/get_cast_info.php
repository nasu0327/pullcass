<?php
/**
 * キャスト情報取得API（閲覧履歴用）
 */

require_once __DIR__ . '/../../../includes/bootstrap.php';

// セッション開始
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// キャッシュ制御
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid ID']);
    exit;
}

// テナント情報を取得（リクエストから、またはセッションから）
$tenant = getTenantFromRequest();
if (!$tenant) {
    // セッションからテナント情報を取得
    $tenant = getCurrentTenant();
}
if (!$tenant) {
    http_response_code(400);
    echo json_encode(['error' => 'Tenant not found']);
    exit;
}

$tenantId = $tenant['id'];

try {
    $pdo = getPlatformDb();

    // キャスト情報を取得
    $stmt = $pdo->prepare("
        SELECT id, name, age, img1 as image, cup, pr_title
        FROM tenant_casts
        WHERE id = :id AND tenant_id = :tenant_id AND checked = 1
    ");
    $stmt->execute(['id' => $id, 'tenant_id' => $tenantId]);
    $cast = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cast) {
        // 200 + error にするとブラウザが 404 をログに出さない（閲覧履歴のコンソールをきれいに保つ）
        echo json_encode(['error' => 'Cast not found']);
        exit;
    }

    echo json_encode($cast);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
