<?php
/**
 * セクション追加処理（インデックスページ用）
 * 1カラム構成
 */

// 認証チェック（$tenant, $tenantIdが自動設定される）
require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

header('Content-Type: application/json');

// POSTデータを取得
$input = json_decode(file_get_contents('php://input'), true);
$section_type = $input['section_type'] ?? '';
$admin_title = $input['admin_title'] ?? '';
$position = $input['position'] ?? 0;

// バリデーション
if (!$admin_title || trim($admin_title) === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '管理タイトルは必須です']);
    exit;
}

if (!in_array($section_type, ['banner', 'text_content', 'embed_widget'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => '無効なセクションタイプです']);
    exit;
}

try {
    // section_keyを生成（ユニーク）
    $section_key = $section_type . '_' . uniqid();
    
    // JavaScriptからのposition（0ベース）を変換
    // heroセクションがdisplay_order=1なので、追加セクションは2から
    $insertPosition = $position + 2;
    
    $pdo->beginTransaction();
    
    // 指定位置以降のセクションのorder値を+1
    $stmt = $pdo->prepare("
        UPDATE index_layout_sections 
        SET display_order = display_order + 1 
        WHERE display_order >= ? AND tenant_id = ? AND section_key != 'hero'
    ");
    $stmt->execute([$insertPosition, $tenantId]);
    
    $stmt = $pdo->prepare("
        INSERT INTO index_layout_sections 
        (tenant_id, section_key, section_type, admin_title, title_en, title_ja, is_visible, display_order, config) 
        VALUES (?, ?, ?, ?, '', '', 1, ?, '{}')
    ");
    
    $stmt->execute([
        $tenantId,
        $section_key,
        $section_type,
        $admin_title,
        $insertPosition
    ]);
    
    $section_id = $pdo->lastInsertId();
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'section_id' => $section_id,
        'section_key' => $section_key,
        'message' => 'セクションを作成しました'
    ]);
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Add section error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'データベースエラー: ' . $e->getMessage()
    ]);
}
