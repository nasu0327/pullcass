<?php
/**
 * pullcass - マスター管理画面
 * 料金表管理 - 一括保存API
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if (!isSuperAdminLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'ログインが必要です']);
    exit;
}

$pdo = getPlatformDb();
if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'データベースに接続できません']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$setId = intval($input['set_id'] ?? 0);
$setName = trim($input['set_name'] ?? '');
$contents = $input['contents'] ?? [];

if (!$setId || !$setName) {
    echo json_encode(['success' => false, 'message' => 'パラメータが不足しています']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // 料金セット情報を更新
    $updateSetSql = "UPDATE price_sets SET set_name = ?";
    $params = [$setName];
    
    // 特別期間の場合は日時も更新
    if (isset($input['start_datetime']) && isset($input['end_datetime'])) {
        $startDatetime = date('Y-m-d H:i:s', strtotime($input['start_datetime']));
        $endDatetime = date('Y-m-d H:i:s', strtotime($input['end_datetime']));
        
        if (strtotime($startDatetime) >= strtotime($endDatetime)) {
            throw new Exception('終了日時は開始日時より後に設定してください');
        }
        
        // 期間重複チェック（自分自身を除く）
        $stmt = $pdo->prepare("
            SELECT id, set_name, start_datetime, end_datetime 
            FROM price_sets 
            WHERE set_type = 'special' 
              AND is_active = 1
              AND id != ?
              AND (
                  (start_datetime <= ? AND end_datetime >= ?)
                  OR (start_datetime <= ? AND end_datetime >= ?)
                  OR (start_datetime >= ? AND end_datetime <= ?)
              )
        ");
        $stmt->execute([
            $setId,
            $startDatetime, $startDatetime,
            $endDatetime, $endDatetime,
            $startDatetime, $endDatetime
        ]);
        
        $overlapping = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($overlapping) {
            $overlapStart = date('Y/m/d H:i', strtotime($overlapping['start_datetime']));
            $overlapEnd = date('Y/m/d H:i', strtotime($overlapping['end_datetime']));
            throw new Exception("期間が「{$overlapping['set_name']}」({$overlapStart} 〜 {$overlapEnd}) と重複しています");
        }
        
        $updateSetSql .= ", start_datetime = ?, end_datetime = ?";
        $params[] = $startDatetime;
        $params[] = $endDatetime;
    }
    
    $updateSetSql .= " WHERE id = ?";
    $params[] = $setId;
    
    $stmt = $pdo->prepare($updateSetSql);
    $stmt->execute($params);
    
    // 各コンテンツを更新
    foreach ($contents as $index => $content) {
        $contentId = intval($content['id']);
        
        // コンテンツ情報を更新
        $stmt = $pdo->prepare("
            UPDATE price_contents 
            SET admin_title = ?, display_order = ?
            WHERE id = ?
        ");
        $stmt->execute([$content['admin_title'] ?? '', $index, $contentId]);
        
        // タイプ別の詳細を更新
        if ($content['type'] === 'price_table') {
            $tableId = intval($content['table_id'] ?? 0);
            
            if ($tableId) {
                $stmt = $pdo->prepare("
                    UPDATE price_tables 
                    SET table_name = ?, column1_header = ?, column2_header = ?, note = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $content['table_name'] ?? '',
                    $content['column1_header'] ?? '',
                    $content['column2_header'] ?? '',
                    $content['note'] ?? '',
                    $tableId
                ]);
                
                // 料金行を更新
                if (!empty($content['rows'])) {
                    foreach ($content['rows'] as $rowIndex => $row) {
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
        } elseif ($content['type'] === 'banner') {
            $stmt = $pdo->prepare("
                UPDATE price_banners 
                SET image_path = ?, link_url = ?, alt_text = ?
                WHERE content_id = ?
            ");
            $stmt->execute([
                $content['image_path'] ?? '',
                $content['link_url'] ?? '',
                $content['alt_text'] ?? '',
                $contentId
            ]);
        } elseif ($content['type'] === 'text') {
            $stmt = $pdo->prepare("
                UPDATE price_texts 
                SET content = ?
                WHERE content_id = ?
            ");
            $stmt->execute([
                $content['content'] ?? '',
                $contentId
            ]);
        }
    }
    
    $pdo->commit();
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Save all error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
