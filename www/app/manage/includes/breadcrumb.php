<?php
/**
 * パンくずリスト共通コンポーネント
 * 
 * 使用方法:
 * $breadcrumbs = [
 *     ['label' => 'ダッシュボード', 'url' => '/app/manage/?tenant=' . $tenantSlug, 'icon' => 'fas fa-chart-pie'],
 *     ['label' => '料金表管理', 'url' => '/app/manage/price_manage/?tenant=' . $tenantSlug],
 *     ['label' => '平常期間料金 編集']  // URLなしで現在のページ
 * ];
 * renderBreadcrumb($breadcrumbs);
 */

function renderBreadcrumb($items)
{
    if (empty($items))
        return;

    echo '<nav class="breadcrumb-nav">';
    $lastIndex = count($items) - 1;

    foreach ($items as $index => $item) {
        if ($index === $lastIndex) {
            // 現在のページ（リンクなし）
            echo '<span class="breadcrumb-current">';
            if (isset($item['icon'])) {
                echo '<i class="' . htmlspecialchars($item['icon']) . '"></i> ';
            }
            echo htmlspecialchars($item['label']);
            echo '</span>';
        } else {
            // リンクあり
            echo '<a href="' . htmlspecialchars($item['url']) . '" class="breadcrumb-item">';
            if (isset($item['icon'])) {
                echo '<i class="' . htmlspecialchars($item['icon']) . '"></i> ';
            }
            echo htmlspecialchars($item['label']);
            echo '</a>';
            echo '<span class="breadcrumb-separator"><i class="fas fa-chevron-right"></i></span>';
        }
    }

    echo '</nav>';
}
