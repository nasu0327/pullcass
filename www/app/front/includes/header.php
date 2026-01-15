<?php
/**
 * pullcass - 共通ヘッダー
 * すべてのフロントページで使用する共通ヘッダー
 * 
 * 必要な変数:
 * - $shopName: 店舗名
 * - $shopTitle: 店舗タイトル（改行区切り）
 * - $logoLargeUrl: 大きいロゴURL
 * - $logoSmallUrl: 小さいロゴURL
 */
?>
<!-- ヘッダー -->
<header class="site-header">
    <div class="header-container">
        <a href="/app/front/top.php" class="logo-area">
            <?php if ($logoSmallUrl): ?>
                <img src="<?php echo h($logoSmallUrl); ?>" alt="<?php echo h($shopName); ?>" class="logo-image">
            <?php elseif ($logoLargeUrl): ?>
                <img src="<?php echo h($logoLargeUrl); ?>" alt="<?php echo h($shopName); ?>" class="logo-image">
            <?php endif; ?>
            <div class="logo-text">
                <?php if ($shopTitle): ?>
                    <?php 
                    $titleLines = explode("\n", $shopTitle);
                    foreach ($titleLines as $line): 
                        $line = trim($line);
                        if ($line):
                    ?>
                    <div class="logo-main-title"><?php echo h($line); ?></div>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                <?php else: ?>
                    <div class="logo-main-title"><?php echo h($shopName); ?></div>
                    <div class="logo-sub-title">オフィシャルサイト</div>
                <?php endif; ?>
            </div>
        </a>
        <button class="hamburger-button" aria-label="メニューを開く">
            <div class="hamburger-lines">
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
            </div>
            <span class="menu-text">MENU</span>
        </button>
    </div>
</header>
