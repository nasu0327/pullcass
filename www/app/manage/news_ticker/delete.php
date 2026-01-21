<?php
/**
 * pullcass - ニュースティッカー 削除
 */

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

// ログイン認証チェック
requireTenantAdminLogin();

$id = $_GET['id'] ?? null;

if (!$id) {
    header('Location: index.php?tenant=' . urlencode($tenantSlug) . '&error=1');
    exit;
}

try {
    // 削除対象のdisplay_orderを取得
    $stmt = $pdo->prepare("SELECT display_order FROM news_tickers WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$id, $tenantId]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        header('Location: index.php?tenant=' . urlencode($tenantSlug) . '&error=2');
        exit;
    }
    
    // 削除
    $deleteStmt = $pdo->prepare("DELETE FROM news_tickers WHERE id = ? AND tenant_id = ?");
    $deleteStmt->execute([$id, $tenantId]);
    
    // display_orderを詰める
    $updateStmt = $pdo->prepare("UPDATE news_tickers SET display_order = display_order - 1 WHERE tenant_id = ? AND display_order > ?");
    $updateStmt->execute([$tenantId, $item['display_order']]);
    
    header('Location: index.php?tenant=' . urlencode($tenantSlug) . '&success=3');
    exit;
    
} catch (PDOException $e) {
    error_log("News ticker delete error: " . $e->getMessage());
    header('Location: index.php?tenant=' . urlencode($tenantSlug) . '&error=3');
    exit;
}
