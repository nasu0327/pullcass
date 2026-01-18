<?php
/**
 * セクション追加処理
 * カスタムセクション（banner、text_content、embed_widget）を追加
 */

// 認証チェック（$tenant, $tenantIdが自動設定される）
require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();


// POSTデータを取得
$input = json_decode(file_get_contents('php://input'), true);
$section_type = $input['section_type'] ?? '';
$admin_title = $input['admin_title'] ?? '';
$column = $input['column'] ?? '';
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
    
    // default_columnを決定
    $default_column = 'left';
    if ($column === 'right') {
        $default_column = 'right';
    }
    
    // JavaScriptからのposition（0ベース）を1ベースに変換
    $insertPosition = $position + 1;
    
    $pdo->beginTransaction();
    
    // 左カラム用のorder値を計算
    if ($column === 'left') {
        // 指定位置以降のセクションのorder値を+1
        $stmt = $pdo->prepare("
            UPDATE top_layout_sections 
            SET pc_left_order = pc_left_order + 1 
            WHERE pc_left_order >= ? AND tenant_id = ?
        ");
        $stmt->execute([$insertPosition, $tenantId]);
        $pc_left_order = $insertPosition;
        $pc_right_order = null;
        
    } elseif ($column === 'right') {
        // 指定位置以降のセクションのorder値を+1
        $stmt = $pdo->prepare("
            UPDATE top_layout_sections 
            SET pc_right_order = pc_right_order + 1 
            WHERE pc_right_order >= ? AND tenant_id = ?
        ");
        $stmt->execute([$insertPosition, $tenantId]);
        $pc_left_order = null;
        $pc_right_order = $insertPosition;
        
    } else {
        // スマホタブから追加：左カラムの末尾に配置
        $stmt = $pdo->prepare("
            SELECT COALESCE(MAX(pc_left_order), 0) + 1 
            FROM top_layout_sections 
            WHERE tenant_id = ?
        ");
        $stmt->execute([$tenantId]);
        $pc_left_order = $stmt->fetchColumn();
        $pc_right_order = null;
    }
    
    // 一時的なmobile_order（後で再計算）
    $mobile_order = 9999;
    
    $stmt = $pdo->prepare("
        INSERT INTO top_layout_sections 
        (tenant_id, section_key, section_type, default_column, admin_title, title_en, title_ja, is_visible, 
         pc_left_order, pc_right_order, mobile_order, status, config) 
        VALUES (?, ?, ?, ?, ?, '', '', 1, ?, ?, ?, 'draft', '{}')
    ");
    
    $stmt->execute([
        $tenantId,
        $section_key,
        $section_type,
        $default_column,
        $admin_title,
        $pc_left_order,
        $pc_right_order,
        $mobile_order
    ]);
    
    $section_id = $pdo->lastInsertId();
    
    // mobile_orderを「左カラム順 → 右カラム順」で再計算
    // 左カラムのセクションを順番に取得
    $stmt = $pdo->prepare("
        SELECT id FROM top_layout_sections 
        WHERE pc_left_order IS NOT NULL AND tenant_id = ?
        ORDER BY pc_left_order ASC
    ");
    $stmt->execute([$tenantId]);
    $leftSections = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // 右カラムのセクションを順番に取得
    $stmt = $pdo->prepare("
        SELECT id FROM top_layout_sections 
        WHERE pc_right_order IS NOT NULL AND tenant_id = ?
        ORDER BY pc_right_order ASC
    ");
    $stmt->execute([$tenantId]);
    $rightSections = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // 左カラム → 右カラムの順でmobile_orderを設定
    $mobileOrder = 1;
    $updateStmt = $pdo->prepare("UPDATE top_layout_sections SET mobile_order = ? WHERE id = ? AND tenant_id = ?");
    
    foreach ($leftSections as $sectionId) {
        $updateStmt->execute([$mobileOrder, $sectionId, $tenantId]);
        $mobileOrder++;
    }
    
    foreach ($rightSections as $sectionId) {
        $updateStmt->execute([$mobileOrder, $sectionId, $tenantId]);
        $mobileOrder++;
    }
    
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
