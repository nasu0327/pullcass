<?php
/**
 * ホテルリストページ用テキストの取得・保存API（JSON）
 * GET: ?tenant=xxx&type=title|description → 現在の文言（保存値またはデフォルト）
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

$allowedTypes = ['title', 'description'];
$type = $_GET['type'] ?? $_POST['type'] ?? null;
if (!$type || !in_array($type, $allowedTypes, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid type']);
    exit;
}

// デフォルトテキスト
function get_default_hotel_list_text($type) {
    switch ($type) {
        case 'title':
            return '福岡市・博多でデリヘルが呼べるビジネスホテル';
        case 'description':
            return "福岡市内の<strong>博多区</strong>や<strong>中央区</strong>など、各エリアのビジネスホテルでデリヘル「豊満倶楽部」をご利用いただけます。<br>\n<strong>デリヘルが呼べるビジネスホテル</strong>を博多駅周辺、中洲、天神エリア別にご案内。交通費や入室方法も詳しく掲載しています。";
        default:
            return '';
    }
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

// GET: 現在の文言を返す（default=1 の場合は常に基本テキスト）
$forceDefault = isset($_GET['default']) && $_GET['default'] !== '' && $_GET['default'] !== '0';

if ($forceDefault) {
    $content = get_default_hotel_list_text($type);
    echo json_encode(['content' => $content, 'is_default' => true]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT content FROM tenant_hotel_list_texts WHERE tenant_id = ? AND text_type = ?");
    $stmt->execute([$tenantId, $type]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $content = $row ? trim($row['content']) : '';
    $isDefault = ($content === '');
    if ($isDefault) {
        $content = get_default_hotel_list_text($type);
    }
    echo json_encode(['content' => $content, 'is_default' => $isDefault]);
} catch (PDOException $e) {
    // テーブル未作成の場合はデフォルトを返す
    $content = get_default_hotel_list_text($type);
    echo json_encode(['content' => $content, 'is_default' => true]);
}
