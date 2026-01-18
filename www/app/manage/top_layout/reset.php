<?php
/**
 * レイアウトリセット機能
 * 優先順位：
 * 1. 下書き保存（top_layout_sections_saved）があれば → そこから復元
 * 2. なければ → 公開済み（top_layout_sections_published）から復元
 * ※バナー（top_layout_banners）は変更しない（section_idで紐づけ）
 */

// 認証チェック
require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

// 共通ファイル読み込み
require_once __DIR__ . '/../../../includes/bootstrap.php';

// JSON形式で返す
header('Content-Type: application/json');

// テナント情報取得
$tenantAdmin = getCurrentTenantAdmin();
$tenantId = $tenantAdmin['tenant_id'];

try {
    // 下書き保存テーブルにデータがあるか確認
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM top_layout_sections_saved WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $savedCount = $stmt->fetchColumn();
    
    // 公開用テーブルにデータがあるか確認
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM top_layout_sections_published WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $publishedCount = $stmt->fetchColumn();
    
    if ($savedCount == 0 && $publishedCount == 0) {
        throw new Exception('復元できるデータがありません。まず「下書き保存」または「公開する」を実行してください。');
    }
    
    $pdo->beginTransaction();
    
    // 編集中テーブルをクリア（当該テナントのみ）
    $stmt = $pdo->prepare("DELETE FROM top_layout_sections WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    
    if ($savedCount > 0) {
        // 下書き保存から復元
        $stmt = $pdo->prepare("
            INSERT INTO top_layout_sections 
            SELECT * FROM top_layout_sections_saved 
            WHERE tenant_id = ?
        ");
        $stmt->execute([$tenantId]);
        $restoreSource = 'saved';
        $message = '下書き保存の状態に戻しました';
    } else {
        // 公開済みから復元
        $stmt = $pdo->prepare("
            INSERT INTO top_layout_sections 
            SELECT * FROM top_layout_sections_published 
            WHERE tenant_id = ?
        ");
        $stmt->execute([$tenantId]);
        $restoreSource = 'published';
        $message = '公開済みの状態に戻しました';
    }
    
    // 復元された件数を取得
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM top_layout_sections WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $count = $stmt->fetchColumn();
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'section_count' => $count,
        'restore_source' => $restoreSource
    ]);
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Reset error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'データベースエラー: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Reset error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
