<?php
/**
 * メニュー管理 - ヘルパー関数
 * メニュー項目の取得、保存、削除などの共通処理を提供
 */

/**
 * テナントのメニュー項目を取得
 * 
 * @param PDO $pdo データベース接続
 * @param int $tenantId テナントID
 * @param bool $activeOnly 有効なメニューのみ取得するか（デフォルト: false）
 * @return array メニュー項目の配列
 */
function getMenuItems($pdo, $tenantId, $activeOnly = false)
{
    try {
        $sql = "SELECT * FROM menu_items WHERE tenant_id = ?";
        if ($activeOnly) {
            $sql .= " AND is_active = 1";
        }
        $sql .= " ORDER BY order_num ASC, id ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("メニュー取得エラー: " . $e->getMessage());
        return [];
    }
}

/**
 * デフォルトメニュー項目を作成
 * 
 * @param PDO $pdo データベース接続
 * @param int $tenantId テナントID
 * @param string $tenantName テナント名
 * @return bool 成功時true、失敗時false
 */
function createDefaultMenuItems($pdo, $tenantId, $tenantName)
{
    try {
        // すでにメニューが存在するかチェック
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM menu_items WHERE tenant_id = ?");
        $stmt->execute([$tenantId]);
        if ($stmt->fetchColumn() > 0) {
            return true; // すでに存在する場合はスキップ
        }
        
        $defaultMenus = [
            [
                'code' => 'HOME',
                'label' => $tenantName,
                'link_type' => 'internal',
                'url' => '/app/front/index',
                'target' => '_self',
                'order_num' => 1
            ],
            [
                'code' => 'TOP',
                'label' => 'トップ',
                'link_type' => 'internal',
                'url' => '/app/front/top',
                'target' => '_self',
                'order_num' => 2
            ],
            [
                'code' => 'CAST',
                'label' => 'キャスト一覧',
                'link_type' => 'internal',
                'url' => '/app/front/cast/list',
                'target' => '_self',
                'order_num' => 3
            ],
            [
                'code' => 'SCHEDULE',
                'label' => 'スケジュール',
                'link_type' => 'internal',
                'url' => '/app/front/schedule/day1',
                'target' => '_self',
                'order_num' => 4
            ],
            [
                'code' => 'SYSTEM',
                'label' => '料金システム',
                'link_type' => 'internal',
                'url' => '/app/front/system',
                'target' => '_self',
                'order_num' => 5
            ]
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO menu_items (tenant_id, code, label, link_type, url, target, order_num, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)
        ");
        
        foreach ($defaultMenus as $menu) {
            $stmt->execute([
                $tenantId,
                $menu['code'],
                $menu['label'],
                $menu['link_type'],
                $menu['url'],
                $menu['target'],
                $menu['order_num']
            ]);
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("デフォルトメニュー作成エラー: " . $e->getMessage());
        return false;
    }
}

/**
 * メニュー項目を保存（新規作成または更新）
 * 
 * @param PDO $pdo データベース接続
 * @param array $data メニューデータ
 * @return array 結果 ['success' => bool, 'message' => string, 'id' => int|null]
 */
function saveMenuItem($pdo, $data)
{
    try {
        // バリデーション
        if (empty($data['label'])) {
            return ['success' => false, 'message' => '表示タイトルは必須です'];
        }
        if (empty($data['url'])) {
            return ['success' => false, 'message' => 'URLは必須です'];
        }
        
        // デフォルト値の設定
        $data['code'] = $data['code'] ?? null;
        $data['link_type'] = $data['link_type'] ?? 'internal';
        $data['target'] = $data['target'] ?? '_self';
        $data['is_active'] = isset($data['is_active']) ? (int)$data['is_active'] : 1;
        
        if (isset($data['id']) && $data['id'] > 0) {
            // 更新
            $stmt = $pdo->prepare("
                UPDATE menu_items 
                SET code = ?, label = ?, link_type = ?, url = ?, target = ?, is_active = ?, updated_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([
                $data['code'],
                $data['label'],
                $data['link_type'],
                $data['url'],
                $data['target'],
                $data['is_active'],
                $data['id'],
                $data['tenant_id']
            ]);
            return ['success' => true, 'message' => 'メニューを更新しました', 'id' => $data['id']];
        } else {
            // 新規作成
            // 最大のorder_numを取得して+1
            $stmt = $pdo->prepare("SELECT COALESCE(MAX(order_num), 0) + 1 FROM menu_items WHERE tenant_id = ?");
            $stmt->execute([$data['tenant_id']]);
            $nextOrder = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("
                INSERT INTO menu_items (tenant_id, code, label, link_type, url, target, order_num, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['tenant_id'],
                $data['code'],
                $data['label'],
                $data['link_type'],
                $data['url'],
                $data['target'],
                $nextOrder,
                $data['is_active']
            ]);
            return ['success' => true, 'message' => 'メニューを追加しました', 'id' => $pdo->lastInsertId()];
        }
    } catch (PDOException $e) {
        error_log("メニュー保存エラー: " . $e->getMessage());
        return ['success' => false, 'message' => 'データベースエラー: ' . $e->getMessage()];
    }
}

/**
 * メニュー項目を削除
 * 
 * @param PDO $pdo データベース接続
 * @param int $id メニュー項目ID
 * @param int $tenantId テナントID（セキュリティチェック用）
 * @return array 結果 ['success' => bool, 'message' => string]
 */
function deleteMenuItem($pdo, $id, $tenantId)
{
    try {
        $stmt = $pdo->prepare("DELETE FROM menu_items WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenantId]);
        
        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'message' => 'メニューを削除しました'];
        } else {
            return ['success' => false, 'message' => 'メニューが見つかりません'];
        }
    } catch (PDOException $e) {
        error_log("メニュー削除エラー: " . $e->getMessage());
        return ['success' => false, 'message' => 'データベースエラー: ' . $e->getMessage()];
    }
}

/**
 * メニュー項目の並び順を更新
 * 
 * @param PDO $pdo データベース接続
 * @param array $orders [['id' => 1, 'order_num' => 1], ...]
 * @param int $tenantId テナントID（セキュリティチェック用）
 * @return array 結果 ['success' => bool, 'message' => string]
 */
function updateMenuOrder($pdo, $orders, $tenantId)
{
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("UPDATE menu_items SET order_num = ? WHERE id = ? AND tenant_id = ?");
        
        foreach ($orders as $item) {
            $stmt->execute([$item['order_num'], $item['id'], $tenantId]);
        }
        
        $pdo->commit();
        return ['success' => true, 'message' => '並び順を更新しました'];
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("並び順更新エラー: " . $e->getMessage());
        return ['success' => false, 'message' => 'データベースエラー: ' . $e->getMessage()];
    }
}

/**
 * メニュー項目の有効/無効を切り替え
 * 
 * @param PDO $pdo データベース接続
 * @param int $id メニュー項目ID
 * @param int $tenantId テナントID（セキュリティチェック用）
 * @return array 結果 ['success' => bool, 'message' => string, 'is_active' => int]
 */
function toggleMenuStatus($pdo, $id, $tenantId)
{
    try {
        // 現在の状態を取得
        $stmt = $pdo->prepare("SELECT is_active FROM menu_items WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenantId]);
        $currentStatus = $stmt->fetchColumn();
        
        if ($currentStatus === false) {
            return ['success' => false, 'message' => 'メニューが見つかりません'];
        }
        
        // 状態を反転
        $newStatus = $currentStatus ? 0 : 1;
        $stmt = $pdo->prepare("UPDATE menu_items SET is_active = ? WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$newStatus, $id, $tenantId]);
        
        return [
            'success' => true,
            'message' => $newStatus ? 'メニューを有効にしました' : 'メニューを無効にしました',
            'is_active' => $newStatus
        ];
    } catch (PDOException $e) {
        error_log("ステータス切り替えエラー: " . $e->getMessage());
        return ['success' => false, 'message' => 'データベースエラー: ' . $e->getMessage()];
    }
}
