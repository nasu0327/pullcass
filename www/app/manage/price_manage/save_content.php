<?php
/**
 * pullcass - マスター管理画面
 * 料金表管理 - 個別コンテンツ保存API
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
$contentId = intval($input['id'] ?? 0);
$contentType = $input['type'] ?? '';
$adminTitle = trim($input['admin_title'] ?? '');

if (!$contentId || !$contentType) {
    echo json_encode(['success' => false, 'message' => 'パラメータが不足しています']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // コンテンツ情報を更新
    $stmt = $pdo->prepare("
        UPDATE price_contents 
        SET admin_title = ?
        WHERE id = ?
    ");
    $stmt->execute([$adminTitle, $contentId]);
    
    // タイプ別の詳細を更新
    if ($contentType === 'price_table') {
        $tableId = intval($input['table_id'] ?? 0);
        
        if ($tableId) {
            $stmt = $pdo->prepare("
                UPDATE price_tables 
                SET table_name = ?, column1_header = ?, column2_header = ?, note = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $input['table_name'] ?? '',
                $input['column1_header'] ?? '',
                $input['column2_header'] ?? '',
                $input['note'] ?? '',
                $tableId
            ]);
            
            // 料金行を更新
            if (!empty($input['rows'])) {
                foreach ($input['rows'] as $rowIndex => $row) {
                    $rowId = $row['id'];
                    
                    if ($rowId && $rowId !== 'new') {
                        $stmt = $pdo->prepare("
                            UPDATE price_rows 
                            SET time_label = ?, price_label = ?, display_order = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            $row['time_label'] ?? '',
                            $row['price_label'] ?? '',
                            $rowIndex,
                            intval($rowId)
                        ]);
                    }
                }
            }
        }
    } elseif ($contentType === 'banner') {
        // 画像ファイルが選択されている場合は先にアップロード
        $imagePath = $input['image_path'] ?? '';
        
        // 画像アップロード処理（必要に応じて）
        // ここでは既にアップロード済みのパスを使用
        
        $stmt = $pdo->prepare("
            UPDATE price_banners 
            SET image_path = ?, link_url = ?, alt_text = ?
            WHERE content_id = ?
        ");
        $stmt->execute([
            $imagePath,
            $input['link_url'] ?? '',
            $input['alt_text'] ?? '',
            $contentId
        ]);
    } elseif ($contentType === 'text') {
        $stmt = $pdo->prepare("
            UPDATE price_texts 
            SET content = ?
            WHERE content_id = ?
        ");
        $stmt->execute([
            $input['content'] ?? '',
            $contentId
        ]);
    }
    
    $pdo->commit();
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Save content error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
