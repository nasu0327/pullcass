<?php
/**
 * 派遣状況テキストの取得・保存API（JSON）
 * GET: ?tenant=xxx&type=full|conditional|limited|none → 現在の文言（保存値またはデフォルト）
 * POST: tenant, type, content → 保存
 */
require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../../includes/dispatch_default_content.php';

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

$allowedTypes = ['full', 'conditional', 'limited', 'none'];
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
            INSERT INTO tenant_dispatch_texts (tenant_id, dispatch_type, content)
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
    $content = get_default_dispatch_content($type);
    echo json_encode(['content' => $content, 'is_default' => true]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT content FROM tenant_dispatch_texts WHERE tenant_id = ? AND dispatch_type = ?");
    $stmt->execute([$tenantId, $type]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $content = $row ? trim($row['content']) : '';
    $isDefault = ($content === '');
    if ($isDefault) {
        $content = get_default_dispatch_content($type);
    }
    // 派遣不可：モーダル表示を「基本テキストに戻す」と一致させる
    if ($type === 'none') {
        // 編集対象外の「代替案のご提案」ブロックを削除（フロントで固定表示のためモーダルには出さない）
        $content = preg_replace(
            '/\s*<div[^>]*background:\s*white[^>]*>.*?代替案のご提案.*?派遣可能なホテル一覧を見る.*?<\/a>\s*<\/p>\s*<\/div>\s*/s',
            "\n\n            ",
            $content
        );
        // 古いハードコードをプレースホルダーに置換
        $content = str_replace(
            ['080-6316-3545', '092-441-3651', 'tel:08063163545', 'tel:0924413651', '10:30～翌2:00', '10:30~2:00', '10:30-02:00'],
            ['{{phone}}', '{{phone}}', 'tel:{{phone_raw}}', 'tel:{{phone_raw}}', '{{business_hours}}', '{{business_hours}}', '{{business_hours}}'],
            $content
        );
        // href は tel:{{phone_raw}} に統一（{{phone}}だとハイフン入りで tel: が効かない場合がある）
        $content = str_replace('href="tel:{{phone}}"', 'href="tel:{{phone_raw}}"', $content);
    }
    echo json_encode(['content' => $content, 'is_default' => $isDefault]);
} catch (PDOException $e) {
    // テーブル未作成の場合はデフォルトを返す
    $content = get_default_dispatch_content($type);
    echo json_encode(['content' => $content, 'is_default' => true]);
}
