<?php
/**
 * pullcass - マスター管理画面
 * 料金表管理 - コンテンツ追加API
 */

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// ログイン認証チェック
requireTenantAdminLogin();

$pdo = getPlatformDb();
if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'データベースに接続できません']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$setId = intval($input['set_id'] ?? 0);
$contentType = $input['content_type'] ?? '';

if (!$setId || !in_array($contentType, ['price_table', 'banner', 'text'])) {
    echo json_encode(['success' => false, 'message' => '無効なパラメータです']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // 最大の display_order を取得
    $stmt = $pdo->prepare("SELECT MAX(display_order) FROM price_contents WHERE set_id = ?");
    $stmt->execute([$setId]);
    $maxOrder = intval($stmt->fetchColumn()) + 1;
    
    // コンテンツを追加（管理名は空でプレースホルダー表示）
    $adminTitle = '';
    
    $stmt = $pdo->prepare("
        INSERT INTO price_contents (set_id, content_type, admin_title, display_order, is_active)
        VALUES (?, ?, ?, ?, 1)
    ");
    $stmt->execute([$setId, $contentType, $adminTitle, $maxOrder]);
    $contentId = $pdo->lastInsertId();
    
    // 詳細テーブルにも初期データを追加（表示名・列名も空でプレースホルダー表示）
    if ($contentType === 'price_table') {
        $stmt = $pdo->prepare("
            INSERT INTO price_tables (content_id, table_name, column1_header, column2_header, note)
            VALUES (?, '', '', '', '')
        ");
        $stmt->execute([$contentId]);
    } elseif ($contentType === 'banner') {
        $stmt = $pdo->prepare("
            INSERT INTO price_banners (content_id, image_path, link_url, alt_text)
            VALUES (?, '', '', '')
        ");
        $stmt->execute([$contentId]);
    } elseif ($contentType === 'text') {
        $stmt = $pdo->prepare("
            INSERT INTO price_texts (content_id, content)
            VALUES (?, '')
        ");
        $stmt->execute([$contentId]);
    }
    
    $pdo->commit();
    echo json_encode(['success' => true, 'id' => $contentId, 'content_id' => $contentId]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('Add content error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'データベースエラーが発生しました']);
}
