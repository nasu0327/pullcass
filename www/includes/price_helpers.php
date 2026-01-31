<?php
/**
 * pullcass - 料金表管理システム共通関数
 * 公開用テーブル（_published）からデータを取得
 */

/**
 * 現在表示すべき料金セットを取得する関数
 */
function getActivePriceSet($pdo, $tablePrefix = '_published')
{
    $now = date('Y-m-d H:i:s');
    $tableName = 'price_sets' . $tablePrefix;

    // 特別期間で現在有効なものを探す
    $stmt = $pdo->prepare("
        SELECT * FROM {$tableName}
        WHERE set_type = 'special' 
          AND is_active = 1 
          AND start_datetime <= ? 
          AND end_datetime >= ?
        ORDER BY start_datetime ASC 
        LIMIT 1
    ");
    $stmt->execute([$now, $now]);
    $specialSet = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($specialSet) {
        return $specialSet;
    }

    // 平常期間を返す
    $stmt = $pdo->query("
        SELECT * FROM {$tableName}
        WHERE set_type = 'regular' 
          AND is_active = 1 
        LIMIT 1
    ");
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * 料金セットのコンテンツを取得
 */
function getPriceContents($pdo, $setId, $tablePrefix = '_published')
{
    $tableName = 'price_contents' . $tablePrefix;
    $stmt = $pdo->prepare("
        SELECT * FROM {$tableName}
        WHERE set_id = ? AND is_active = 1
        ORDER BY display_order ASC
    ");
    $stmt->execute([$setId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 料金表の詳細を取得
 */
function getPriceTableDetail($pdo, $contentId, $tablePrefix = '_published')
{
    $tableName = 'price_tables' . $tablePrefix;
    $stmt = $pdo->prepare("
        SELECT * FROM {$tableName} WHERE content_id = ?
    ");
    $stmt->execute([$contentId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * 料金行を取得
 */
function getPriceRows($pdo, $tableId, $tablePrefix = '_published')
{
    $tableName = 'price_rows' . $tablePrefix;
    $stmt = $pdo->prepare("
        SELECT * FROM {$tableName} WHERE table_id = ? ORDER BY display_order ASC
    ");
    $stmt->execute([$tableId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * バナーの詳細を取得
 */
function getPriceBanner($pdo, $contentId, $tablePrefix = '_published')
{
    $tableName = 'price_banners' . $tablePrefix;
    $stmt = $pdo->prepare("
        SELECT * FROM {$tableName} WHERE content_id = ?
    ");
    $stmt->execute([$contentId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * テキストの詳細を取得
 */
function getPriceText($pdo, $contentId, $tablePrefix = '_published')
{
    $tableName = 'price_texts' . $tablePrefix;
    $stmt = $pdo->prepare("
        SELECT * FROM {$tableName} WHERE content_id = ?
    ");
    $stmt->execute([$contentId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * 料金表スタイルCSS
 */
function getPriceTableStyles()
{
    return <<<CSS
<style>
/* 料金表テキスト表示用スタイル */
.price-section {
    max-width: 800px;
    margin: 0 auto;
    padding: 0 10px;
}

.price-table-group {
    margin-bottom: 30px;
}

.price-table-title {
    font-size: 1.3rem;
    font-weight: bold;
    color: var(--color-primary, #27a3eb);
    text-align: center;
    margin-bottom: 15px;
    padding: 10px;
    border-bottom: 2px solid var(--color-primary, #27a3eb);
}

.price-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 5px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 10px;
    overflow: hidden;
}

.price-table th,
.price-table td {
    padding: 4px 15px;
    text-align: center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.price-table th {
    background: color-mix(in srgb, var(--color-primary, #f568df) 80%, transparent);
    color: var(--color-btn-text, #fff);
    font-weight: bold;
    font-size: 0.95rem;
}

.price-table td {
    font-size: 1rem;
    color: var(--color-text, #fff);
    background: rgba(255, 255, 255, 0.6);
}

.price-table tr:last-child td {
    border-bottom: none;
}

.price-table tr:hover td {
    background: rgba(255, 255, 255, 0.08);
}

.price-table-note {
    margin-top: 0;
    padding: 5px 10px;
    background: rgba(255, 255, 255, 0.6);
    border-radius: 10px;
    font-size: 0.75rem;
    color: var(--color-text, #333);
    line-height: 1.4;
    text-align: left;
}

.price-banner {
    display: block;
    border-radius: 10px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    overflow: hidden;
    text-decoration: none;
    margin-bottom: 15px;
}

.price-banner img {
    width: 100%;
    border-radius: 10px;
    display: block;
}

.price-text-content {
    margin-bottom: 20px;
    padding: 15px;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 10px;
    line-height: 1.7;
    color: var(--color-text, #fff);
}

/* レスポンシブ対応 */
@media (max-width: 600px) {
    .price-table th,
    .price-table td {
        padding: 3px 8px;
        font-size: 0.9rem;
    }
    
    .price-table-title {
        font-size: 1.1rem;
    }
}
</style>
CSS;
}

/**
 * 料金コンテンツをHTML出力
 */
function renderPriceContents($pdo, $priceContents, $tablePrefix = '_published')
{
    if (empty($priceContents)) {
        echo '<div style="text-align: center; padding: 60px 20px; color: rgba(255,255,255,0.5);">';
        echo '<p>料金表を準備中です</p>';
        echo '</div>';
        return;
    }

    foreach ($priceContents as $content) {
        if ($content['content_type'] === 'price_table') {
            $table = getPriceTableDetail($pdo, $content['id'], $tablePrefix);
            if ($table) {
                $rows = getPriceRows($pdo, $table['id'], $tablePrefix);
                $col1Header = htmlspecialchars($table['column1_header'] ?? '時間');
                $col2Header = htmlspecialchars($table['column2_header'] ?? '料金');
                echo '<div class="price-table-group">';
                echo '<h3 class="price-table-title">' . $table['table_name'] . '</h3>';
                if (!empty($rows)) {
                    $columnCount = $table['column_count'] ?? 2;

                    if ($columnCount == 1) {
                        // 新しい1カラム版（タイトルと内容のペア、ヘッダーなし）
                        echo '<table class="price-table">';
                        echo '<tbody>';
                        foreach ($rows as $row) {
                            echo '<tr>';
                            echo '<td style="background: color-mix(in srgb, var(--color-primary, #f568df) 80%, transparent); color: var(--color-btn-text, #fff); font-weight: bold; width: 40%;">' . htmlspecialchars($row['time_label']) . '</td>';
                            echo '<td style="width: 60%;">' . htmlspecialchars($row['price_label']) . '</td>';
                            echo '</tr>';
                        }
                        echo '</tbody></table>';
                    } else {
                        // 既存の2カラム版ロジック（絶対に触らない）
                        // 右列（price_label）が全て空かどうかをチェック
                        $hasRightColumn = false;
                        foreach ($rows as $row) {
                            if (!empty(trim($row['price_label']))) {
                                $hasRightColumn = true;
                                break;
                            }
                        }

                        echo '<table class="price-table">';
                        if ($hasRightColumn) {
                            // 2列表示
                            echo '<thead><tr><th>' . $col1Header . '</th><th>' . $col2Header . '</th></tr></thead>';
                            echo '<tbody>';
                            foreach ($rows as $row) {
                                echo '<tr>';
                                echo '<td>' . htmlspecialchars($row['time_label']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['price_label']) . '</td>';
                                echo '</tr>';
                            }
                        } else {
                            // 1列表示（中央寄せ）
                            echo '<thead><tr><th colspan="2">' . $col1Header . '</th></tr></thead>';
                            echo '<tbody>';
                            foreach ($rows as $row) {
                                echo '<tr>';
                                echo '<td colspan="2" style="text-align: center;">' . htmlspecialchars($row['time_label']) . '</td>';
                                echo '</tr>';
                            }
                        }
                        echo '</tbody></table>';
                    }
                }
                if (!empty($table['note'])) {
                    echo '<div class="price-table-note">' . $table['note'] . '</div>';
                }
                echo '</div>';
            }
        } elseif ($content['content_type'] === 'banner') {
            $banner = getPriceBanner($pdo, $content['id'], $tablePrefix);
            if ($banner && !empty($banner['image_path'])) {
                if (!empty($banner['link_url'])) {
                    echo '<a href="' . htmlspecialchars($banner['link_url']) . '" class="price-banner" target="_blank">';
                    echo '<img src="' . htmlspecialchars($banner['image_path']) . '" alt="' . htmlspecialchars($banner['alt_text'] ?? '') . '" />';
                    echo '</a>';
                } else {
                    echo '<div class="price-banner">';
                    echo '<img src="' . htmlspecialchars($banner['image_path']) . '" alt="' . htmlspecialchars($banner['alt_text'] ?? '') . '" />';
                    echo '</div>';
                }
            }
        } elseif ($content['content_type'] === 'text') {
            $text = getPriceText($pdo, $content['id'], $tablePrefix);
            if ($text && !empty($text['content'])) {
                echo '<div class="price-text-content">' . $text['content'] . '</div>';
            }
        }
    }
}
