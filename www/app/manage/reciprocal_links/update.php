<?php
/**
 * 相互リンク更新API
 */

require_once __DIR__ . '/../../../includes/bootstrap.php';

header('Content-Type: application/json');

session_start();

$input = json_decode(file_get_contents('php://input'), true);
$tenantSlug = $input['tenant'] ?? $_SESSION['manage_tenant_slug'] ?? null;
$id = (int)($input['id'] ?? 0);
$type = $input['type'] ?? 'banner';

if (!$tenantSlug || !$id) {
    echo json_encode(['success' => false, 'message' => 'パラメータが不正です']);
    exit;
}

try {
    $pdo = getPlatformDb();
    
    // テナントIDを取得
    $stmt = $pdo->prepare("SELECT id FROM tenants WHERE code = ?");
    $stmt->execute([$tenantSlug]);
    $tenant = $stmt->fetch();
    
    if (!$tenant) {
        echo json_encode(['success' => false, 'message' => 'テナントが見つかりません']);
        exit;
    }
    
    if ($type === 'code') {
        // カスタムコード型の更新
        $alt_text = $input['alt_text'] ?? '';
        $custom_code = $input['custom_code'] ?? '';
        
        if (empty($custom_code)) {
            echo json_encode(['success' => false, 'message' => 'コードを入力してください']);
            exit;
        }
        
        $stmt = $pdo->prepare("UPDATE reciprocal_links SET alt_text = ?, custom_code = ? WHERE id = ? AND tenant_id = ?");
        $result = $stmt->execute([$alt_text, $custom_code, $id, $tenant['id']]);
    } else {
        // 画像バナー型の更新
        $alt_text = $input['alt_text'] ?? '';
        $link_url = $input['link_url'] ?? '';
        $nofollow = isset($input['nofollow']) ? (int)$input['nofollow'] : 1;
        
        if (empty($alt_text) || empty($link_url)) {
            echo json_encode(['success' => false, 'message' => 'すべての項目を入力してください']);
            exit;
        }
        
        $stmt = $pdo->prepare("UPDATE reciprocal_links SET alt_text = ?, link_url = ?, nofollow = ? WHERE id = ? AND tenant_id = ?");
        $result = $stmt->execute([$alt_text, $link_url, $nofollow, $id, $tenant['id']]);
    }
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => '更新しました']);
    } else {
        echo json_encode(['success' => false, 'message' => '更新に失敗しました']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'データベースエラー: ' . $e->getMessage()]);
}
