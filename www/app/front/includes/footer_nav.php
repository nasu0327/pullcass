<?php
/**
 * pullcass - 共通フッターナビゲーション
 * 参考サイト: https://club-houman.com/cast/list
 * 
 * 必要な変数:
 * - $shopName: 店舗名
 */

// 今日から7日分の日付を生成
$scheduleLinks = [];
for ($i = 1; $i <= 7; $i++) {
    $date = new DateTime();
    $date->modify('+' . ($i - 1) . ' days');
    $dayLabel = ['日', '月', '火', '水', '木', '金', '土'][$date->format('w')];
    
    if ($i === 1) {
        $scheduleLinks[] = ['url' => '/app/front/schedule/day1.php', 'text' => '本日の出勤'];
    } elseif ($i === 2) {
        $scheduleLinks[] = ['url' => '/app/front/schedule/day2.php', 'text' => '明日の出勤'];
    } else {
        $scheduleLinks[] = ['url' => "/app/front/schedule/day{$i}.php", 'text' => $date->format('n/j') . '(' . $dayLabel . ')の出勤'];
    }
}
?>
</div> <!-- .main-content-wrapper の閉じタグ -->

<!-- 通常フッター（ナビゲーション） -->
<footer class="site-footer-standard">
    <div class="page-footer-content">
        <nav class="footer-nav-standard">
            <ul>
                <li><a href="/app/front/index.php"><?php echo h($shopName); ?></a></li>
                <li><a href="/app/front/top.php">トップ</a></li>
                <li><a href="/app/front/cast/list.php">在籍一覧</a></li>
                <?php foreach ($scheduleLinks as $link): ?>
                <li><a href="<?php echo h($link['url']); ?>"><?php echo h($link['text']); ?></a></li>
                <?php endforeach; ?>
                <li><a href="/app/front/system.php">料金システム</a></li>
                <li><a href="/app/front/hotel_list.php">ホテルリスト</a></li>
                <li><a href="/app/front/reviews.php">口コミ</a></li>
                <li><a href="/app/front/diary.php">動画・写メ日記</a></li>
                <li><a href="/app/front/yoyaku.php">ネット予約</a></li>
            </ul>
        </nav>
        <p class="copyright-standard">
            © <?php echo date('Y'); ?> <?php echo h($shopName); ?>. All Rights Reserved.
        </p>
    </div>
</footer>
