<?php
/**
 * 現在アクティブなキャストデータテーブル名を取得する
 */
function getActiveCastTable($pdo, $tenantId)
{
    // デフォルトは ekichika
    $source = 'ekichika';
    try {
        $stmt = $pdo->prepare("SELECT config_value FROM tenant_scraping_config WHERE tenant_id = ? AND config_key = 'active_source'");
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['config_value']) {
            $source = $row['config_value'];
        }
    } catch (Exception $e) {
        // テーブルが存在しない場合などはデフォルトを使用
    }

    // 安全のため、許可されたソースのみを許可（SQLインジェクション対策）
    $validSources = ['ekichika', 'heaven', 'dto'];
    if (!in_array($source, $validSources)) {
        $source = 'ekichika';
    }

    return "tenant_cast_data_{$source}";
}
