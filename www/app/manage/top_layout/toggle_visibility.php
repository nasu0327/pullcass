<?php
/**
 * 表示/非表示切り替え
 * PC版・スマホ版別々に管理
 */

// 認証チェック（$tenant, $tenantIdが自動設定される）
require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();


// POSTデータを取得
$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? null;
$type = $input['type'] ?? 'pc'; // 'pc' or 'mobile'

if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'IDが指定されていません']);
    exit;
}

// カラム名を決定
$column = ($type === 'mobile') ? 'mobile_visible' : 'is_visible';

try {
    // セクション情報を取得（section_key も取得）
    $stmt = $pdo->prepare("SELECT section_key, {$column} FROM top_layout_sections WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$id, $tenantId]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$current) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'セクションが見つかりません']);
        exit;
    }

    // 写メ日記セクションはオプション有効時のみトグル可能
    if ($current['section_key'] === 'diary') {
        $stmt = $pdo->prepare("SELECT is_enabled FROM tenant_features WHERE tenant_id = ? AND feature_code = 'diary_scrape'");
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || (int)$row['is_enabled'] !== 1) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'この機能は追加オプションです。詳しくは担当者までお問い合わせください。']);
            exit;
        }
    }

    // 表示状態をトグル
    $newVisibility = $current[$column] ? 0 : 1;
    
    // 更新
    $stmt = $pdo->prepare("UPDATE top_layout_sections SET {$column} = ? WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$newVisibility, $id, $tenantId]);

    // ランキングセクションの場合、tenant_ranking_configのvisibleとも連動
    if ($type === 'pc') {
        $stmt = $pdo->prepare("SELECT section_key FROM top_layout_sections WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$id, $tenantId]);
        $sectionKey = $stmt->fetchColumn();

        if ($sectionKey === 'repeat_ranking' || $sectionKey === 'attention_ranking') {
            $configColumn = ($sectionKey === 'repeat_ranking') ? 'repeat_visible' : 'attention_visible';
            try {
                $stmt = $pdo->prepare("UPDATE tenant_ranking_config SET {$configColumn} = ? WHERE tenant_id = ?");
                $stmt->execute([$newVisibility, $tenantId]);
            } catch (PDOException $e) {
                // tenant_ranking_configが無い場合は無視
                error_log('top_layout -> ranking config sync error: ' . $e->getMessage());
            }
        }
    }
    
    $message = $type === 'mobile' 
        ? ($newVisibility ? 'スマホで表示します' : 'スマホで非表示にしました')
        : ($newVisibility ? 'PCで表示します' : 'PCで非表示にしました');
    
    echo json_encode([
        'success' => true,
        'is_visible' => (bool)$newVisibility,
        'type' => $type,
        'message' => $message
    ]);
    
} catch (PDOException $e) {
    error_log("Toggle visibility error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'データベースエラー: ' . $e->getMessage()
    ]);
}
