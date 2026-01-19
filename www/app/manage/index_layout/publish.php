<?php
/**
 * レイアウト公開処理（インデックスページ用）
 */

// 認証チェック（$tenant, $tenantIdが自動設定される）
require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

header('Content-Type: application/json');

try {
    $pdo->beginTransaction();
    
    // 公開用テーブルをクリア（当該テナントのみ）
    $stmt = $pdo->prepare("DELETE FROM index_layout_sections_published WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    
    // 編集中テーブルから公開用テーブルにコピー
    $stmt = $pdo->prepare("
        INSERT INTO index_layout_sections_published 
        SELECT * FROM index_layout_sections 
        WHERE tenant_id = ?
    ");
    $stmt->execute([$tenantId]);
    
    // 下書き保存テーブルをクリア（公開したので不要）
    $stmt = $pdo->prepare("DELETE FROM index_layout_sections_saved WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    
    // コピーされた件数を取得
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM index_layout_sections_published WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $count = $stmt->fetchColumn();
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'レイアウトを公開しました！インデックスページで確認できます。',
        'section_count' => $count,
        'published_at' => date('Y-m-d H:i:s')
    ]);
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Publish error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'データベースエラー: ' . $e->getMessage()
    ]);
}
