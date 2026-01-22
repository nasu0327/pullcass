<?php
/**
 * pullcass - マスター管理画面
 * 料金表管理 - 特別期間料金セット追加API
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// 認証チェック
if (!isSuperAdminLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'ログインが必要です']);
    exit;
}

// データベース接続
$pdo = getPlatformDb();
if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'データベースに接続できません']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$setName = trim($input['set_name'] ?? '');
$startDatetime = $input['start_datetime'] ?? '';
$endDatetime = $input['end_datetime'] ?? '';
$copyFromRegular = $input['copy_from_regular'] ?? false;

// バリデーション
if (empty($setName) || empty($startDatetime) || empty($endDatetime)) {
    echo json_encode(['success' => false, 'message' => '全ての項目を入力してください']);
    exit;
}

// 日時フォーマット変換
$startDatetime = date('Y-m-d H:i:s', strtotime($startDatetime));
$endDatetime = date('Y-m-d H:i:s', strtotime($endDatetime));

if (strtotime($startDatetime) >= strtotime($endDatetime)) {
    echo json_encode(['success' => false, 'message' => '終了日時は開始日時より後に設定してください']);
    exit;
}

// 期間重複チェック
try {
    $stmt = $pdo->prepare("
        SELECT id, set_name, start_datetime, end_datetime 
        FROM price_sets 
        WHERE set_type = 'special' 
          AND is_active = 1
          AND (
              (start_datetime <= ? AND end_datetime >= ?)
              OR (start_datetime <= ? AND end_datetime >= ?)
              OR (start_datetime >= ? AND end_datetime <= ?)
          )
    ");
    $stmt->execute([
        $startDatetime, $startDatetime,  // 新しい開始日が既存期間内
        $endDatetime, $endDatetime,      // 新しい終了日が既存期間内
        $startDatetime, $endDatetime     // 新しい期間が既存期間を包含
    ]);
    
    $overlapping = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($overlapping) {
        $overlapStart = date('Y/m/d H:i', strtotime($overlapping['start_datetime']));
        $overlapEnd = date('Y/m/d H:i', strtotime($overlapping['end_datetime']));
        echo json_encode([
            'success' => false, 
            'message' => "期間が「{$overlapping['set_name']}」({$overlapStart} 〜 {$overlapEnd}) と重複しています。重複する期間は登録できません。"
        ]);
        exit;
    }

    // 新規作成
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("
        INSERT INTO price_sets (set_name, set_type, start_datetime, end_datetime, display_order, is_active)
        VALUES (?, 'special', ?, ?, 0, 1)
    ");
    $stmt->execute([$setName, $startDatetime, $endDatetime]);
    
    $newId = $pdo->lastInsertId();
    
    // 平常期間料金からコピーする場合
    if ($copyFromRegular) {
        // 平常期間のIDを取得
        $stmt = $pdo->query("SELECT id FROM price_sets WHERE set_type = 'regular' LIMIT 1");
        $regularSet = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($regularSet) {
            $regularSetId = $regularSet['id'];
            
            // price_contentsをコピー
            $stmt = $pdo->prepare("
                SELECT * FROM price_contents WHERE set_id = ? ORDER BY display_order
            ");
            $stmt->execute([$regularSetId]);
            $contents = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($contents as $content) {
                $oldContentId = $content['id'];
                
                // 新しいコンテンツを作成
                $stmt = $pdo->prepare("
                    INSERT INTO price_contents (set_id, content_type, admin_title, display_order, is_active)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $newId,
                    $content['content_type'],
                    $content['admin_title'],
                    $content['display_order'],
                    $content['is_active']
                ]);
                $newContentId = $pdo->lastInsertId();
                
                // タイプ別の詳細をコピー
                if ($content['content_type'] === 'price_table') {
                    // price_tablesをコピー
                    $stmt = $pdo->prepare("SELECT * FROM price_tables WHERE content_id = ?");
                    $stmt->execute([$oldContentId]);
                    $table = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($table) {
                        $oldTableId = $table['id'];
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO price_tables (content_id, table_name, column1_header, column2_header, note)
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $newContentId,
                            $table['table_name'],
                            $table['column1_header'],
                            $table['column2_header'],
                            $table['note']
                        ]);
                        $newTableId = $pdo->lastInsertId();
                        
                        // price_rowsをコピー
                        $stmt = $pdo->prepare("SELECT * FROM price_rows WHERE table_id = ? ORDER BY display_order");
                        $stmt->execute([$oldTableId]);
                        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($rows as $row) {
                            $stmt = $pdo->prepare("
                                INSERT INTO price_rows (table_id, time_label, price_label, display_order)
                                VALUES (?, ?, ?, ?)
                            ");
                            $stmt->execute([
                                $newTableId,
                                $row['time_label'],
                                $row['price_label'],
                                $row['display_order']
                            ]);
                        }
                    }
                    
                } elseif ($content['content_type'] === 'banner') {
                    // price_bannersをコピー
                    $stmt = $pdo->prepare("SELECT * FROM price_banners WHERE content_id = ?");
                    $stmt->execute([$oldContentId]);
                    $banner = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($banner) {
                        $stmt = $pdo->prepare("
                            INSERT INTO price_banners (content_id, image_path, link_url, alt_text)
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $newContentId,
                            $banner['image_path'],
                            $banner['link_url'],
                            $banner['alt_text']
                        ]);
                    }
                    
                } elseif ($content['content_type'] === 'text') {
                    // price_textsをコピー
                    $stmt = $pdo->prepare("SELECT * FROM price_texts WHERE content_id = ?");
                    $stmt->execute([$oldContentId]);
                    $text = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($text) {
                        $stmt = $pdo->prepare("
                            INSERT INTO price_texts (content_id, content)
                            VALUES (?, ?)
                        ");
                        $stmt->execute([
                            $newContentId,
                            $text['content']
                        ]);
                    }
                }
            }
        }
    }
    
    $pdo->commit();
    
    echo json_encode(['success' => true, 'id' => $newId]);
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Price set add error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'データベースエラーが発生しました']);
}
