<?php
/**
 * レイアウト公開処理
 * 1. top_layout_sections → top_layout_sections_published にコピー
 * 2. top_layout_sections_saved をクリア（公開したので下書きは不要）
 */

// 認証チェック（$tenant, $tenantIdが自動設定される）
require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();


try {
    $pdo->beginTransaction();

    // 公開用テーブルをクリア（当該テナントのみ）
    $stmt = $pdo->prepare("DELETE FROM top_layout_sections_published WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    
    // 編集中テーブルから公開用テーブルにコピー
    $stmt = $pdo->prepare("
        INSERT INTO top_layout_sections_published 
        SELECT * FROM top_layout_sections 
        WHERE tenant_id = ?
    ");
    $stmt->execute([$tenantId]);

    // 下書き保存テーブルをクリア（公開したので不要）
    $stmt = $pdo->prepare("DELETE FROM top_layout_sections_saved WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    
    // コピーされた件数を取得
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM top_layout_sections_published WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $count = $stmt->fetchColumn();
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'レイアウトを公開しました！トップページで確認できます。',
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
