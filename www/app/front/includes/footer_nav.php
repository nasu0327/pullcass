<?php
/**
 * pullcass - 共通フッターナビゲーション
 * 参考サイト: https://club-houman.com/cast/list
 * 
 * 必要な変数:
 * - $shopName: 店舗名
 * - $tenantId: テナントID（写メ日記リンクの表示判定に使用、未設定の場合は非表示）
 */

// 写メ日記・口コミリンクの表示可否（マスターON かつ トップページ編集で公開済みの該当セクションON のときのみ）
$showDiaryLink = false;
$showReviewsLink = false;
if (!empty($tenantId)) {
    try {
        $pdo = getPlatformDb();
        $stmt = $pdo->prepare("SELECT is_enabled FROM tenant_features WHERE tenant_id = ? AND feature_code = 'diary_scrape'");
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && (int)$row['is_enabled'] === 1) {
            $stmt = $pdo->prepare("SELECT 1 FROM top_layout_sections_published WHERE tenant_id = ? AND section_key = 'diary' AND (is_visible = 1 OR mobile_visible = 1) LIMIT 1");
            $stmt->execute([$tenantId]);
            $showDiaryLink = (bool) $stmt->fetchColumn();
        }
        $stmt = $pdo->prepare("SELECT is_enabled FROM tenant_features WHERE tenant_id = ? AND feature_code = 'review_scrape'");
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && (int)$row['is_enabled'] === 1) {
            $stmt = $pdo->prepare("SELECT 1 FROM top_layout_sections_published WHERE tenant_id = ? AND section_key = 'reviews' AND (is_visible = 1 OR mobile_visible = 1) LIMIT 1");
            $stmt->execute([$tenantId]);
            $showReviewsLink = (bool) $stmt->fetchColumn();
        }
    } catch (Exception $e) {
        // 無効のまま
    }
}

// 今日から7日分の日付を生成
$scheduleLinks = [];
for ($i = 1; $i <= 7; $i++) {
    $date = new DateTime();
    $date->modify('+' . ($i - 1) . ' days');
    $dayLabel = ['日', '月', '火', '水', '木', '金', '土'][$date->format('w')];
    
    if ($i === 1) {
        $scheduleLinks[] = ['url' => '/schedule/day1', 'text' => '本日の出勤'];
    } elseif ($i === 2) {
        $scheduleLinks[] = ['url' => '/schedule/day2', 'text' => '明日の出勤'];
    } else {
        $scheduleLinks[] = ['url' => "/schedule/day{$i}", 'text' => $date->format('n/j') . '(' . $dayLabel . ')の出勤'];
    }
}

// トップページ以外の場合のみ、main-content-wrapperの閉じタグを出力
$currentPage = basename($_SERVER['PHP_SELF']);
$isTopPage = (in_array($currentPage, ['index.php', 'top.php']) || (isset($bodyClass) && $bodyClass === 'top-page')) && empty($isFreePage);
if (!$isTopPage):
?>
</div> <!-- .main-content-wrapper の閉じタグ -->
<?php endif; ?>

<!-- 通常フッター（ナビゲーション） -->
<footer class="site-footer-standard">
    <div class="page-footer-content">
        <nav class="footer-nav-standard">
            <ul>
                <li><a href="/"><?php echo h($shopName); ?></a></li>
                <li><a href="/top">トップ</a></li>
                <li><a href="/cast/list">在籍一覧</a></li>
                <?php foreach ($scheduleLinks as $link): ?>
                <li><a href="<?php echo h($link['url']); ?>"><?php echo h($link['text']); ?></a></li>
                <?php endforeach; ?>
                <li><a href="/system">料金システム</a></li>
                <li><a href="/hotel_list">ホテルリスト</a></li>
                <?php if ($showReviewsLink): ?>
                <li><a href="/reviews">口コミ</a></li>
                <?php endif; ?>
                <?php if ($showDiaryLink): ?>
                <li><a href="/diary">動画・写メ日記</a></li>
                <?php endif; ?>
                <li><a href="/yoyaku">ネット予約</a></li>
            </ul>
        </nav>
        <p class="copyright-standard">
            © <?php echo date('Y'); ?> <?php echo h($shopName); ?>. All Rights Reserved.
        </p>
    </div>
</footer>
