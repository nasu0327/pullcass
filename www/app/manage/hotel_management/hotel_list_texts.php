<?php
/**
 * ホテルリストページ用テキストの取得・保存API（JSON）
 * GET: ?tenant=xxx&type=title|description → 現在の文言
 * POST: tenant, type, content → 保存
 */
require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

requireTenantAdminLogin();

header('Content-Type: application/json; charset=utf-8');

$pdo = getPlatformDb();
$tenantSlug = $_GET['tenant'] ?? $_POST['tenant'] ?? $_SESSION['manage_tenant_slug'] ?? null;
if (!$tenantSlug) {
    http_response_code(400);
    echo json_encode(['error' => 'tenant required']);
    exit;
}

$tenantId = $_SESSION['manage_tenant']['id'] ?? null;
if (!$tenantId) {
    http_response_code(403);
    echo json_encode(['error' => 'tenant not found']);
    exit;
}

$allowedTypes = ['title', 'description', 'page_title', 'page_subtitle'];
$type = $_GET['type'] ?? $_POST['type'] ?? null;
if (!$type || !in_array($type, $allowedTypes, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid type']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = $_POST['content'] ?? '';
    $content = trim($content);

    try {
        $stmt = $pdo->prepare("
            INSERT INTO tenant_hotel_list_texts (tenant_id, text_type, content)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE content = VALUES(content), updated_at = NOW()
        ");
        $stmt->execute([$tenantId, $type, $content]);
        echo json_encode(['success' => true]);
        exit;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'save failed', 'message' => $e->getMessage()]);
        exit;
    }
}

// GET: 現在の文言を返す
try {
    $stmt = $pdo->prepare("SELECT content FROM tenant_hotel_list_texts WHERE tenant_id = ? AND text_type = ?");
    $stmt->execute([$tenantId, $type]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $content = $row ? trim($row['content']) : '';
    echo json_encode(['content' => $content]);
} catch (PDOException $e) {
    // テーブル未作成の場合は空を返す
    echo json_encode(['content' => '']);
}
