<?php
/**
 * pullcass - ハンバーガーメニュー（ポップアップメニュー）
 * データベースから動的にメニュー項目を読み込んで表示
 * 
 * 必須変数:
 * - $tenantId: テナントID
 * - $shopName: 店舗名
 */

// メニュー項目を取得
$pdo = getTenantDb();
try {
    $stmt = $pdo->prepare("
        SELECT * FROM menu_items 
        WHERE tenant_id = ? AND is_active = 1 
        ORDER BY order_num ASC, id ASC
    ");
    $stmt->execute([$tenantId]);
    $menuItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("メニュー取得エラー: " . $e->getMessage());
    $menuItems = [];
}

// メニューが存在しない場合のデフォルトメニュー（フォールバック）
if (empty($menuItems)) {
    $menuItems = [
        ['code' => 'TOP', 'label' => 'トップ', 'url' => '/app/front/top.php', 'link_type' => 'internal', 'target' => '_self'],
        ['code' => 'CAST', 'label' => 'キャスト一覧', 'url' => '/app/front/cast/list.php', 'link_type' => 'internal', 'target' => '_self'],
        ['code' => 'SCHEDULE', 'label' => 'スケジュール', 'url' => '/app/front/schedule/day1.php', 'link_type' => 'internal', 'target' => '_self'],
    ];
}
?>

<!-- ハンバーガーメニュー ポップアップ -->
<div id="popup-menu-overlay" class="popup-menu-overlay" aria-hidden="true">
    <div id="popup-menu-panel" class="popup-menu-panel">
        <!-- 背景スライドショー（オプション） -->
        <div class="menu-background-slideshow">
            <?php
            // TODO: 背景画像はテナント設定または管理画面から設定できるようにする
            $popupImages = [];
            // 背景画像があれば表示
            if (!empty($popupImages)) {
                foreach ($popupImages as $index => $imageSrc) {
                    $style = 'background-image: url(\'' . htmlspecialchars($imageSrc, ENT_QUOTES, 'UTF-8') . '\');';
                    if ($index === 0) {
                        $style .= ' opacity: 1;';
                    }
                    echo '<div class="menu-background-slide" style="' . $style . '"></div>';
                }
            }
            ?>
        </div>
        
        <!-- オーバーレイ -->
        <div class="menu-panel-overlay"></div>
        
        <!-- メニューコンテンツ -->
        <div class="menu-panel-content">
            <button id="close-menu-button" class="close-menu-icon" aria-label="メニューを閉じる">&times;</button>
            
            <nav class="popup-main-nav">
                <?php foreach ($menuItems as $item): ?>
                    <?php
                    // URLの処理
                    $href = $item['url'];
                    
                    // 内部リンクの場合、URLがスラッシュで始まっていない場合は追加
                    if ($item['link_type'] === 'internal' && !str_starts_with($href, '/') && !str_starts_with($href, 'http')) {
                        $href = '/' . $href;
                    }
                    
                    // ターゲット属性
                    $targetAttr = $item['target'] === '_blank' ? ' target="_blank" rel="noopener noreferrer"' : '';
                    ?>
                    
                    <a href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>" 
                       class="popup-nav-item"<?php echo $targetAttr; ?>>
                        <?php if (!empty($item['code'])): ?>
                        <div class="nav-item-code"><?php echo htmlspecialchars($item['code'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>
                        <div class="nav-item-label"><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></div>
                    </a>
                <?php endforeach; ?>
            </nav>
            
            <!-- フッターリンク -->
            <a href="/app/front/index.php" class="popup-footer-link">
                <?php if (!empty($logoLargeUrl)): ?>
                <img src="<?php echo h($logoLargeUrl); ?>" alt="<?php echo h($shopName); ?>" class="popup-footer-logo">
                <?php endif; ?>
                <div class="popup-footer-text-official">OFFICIAL</div>
                <div class="popup-footer-text-sitename"><?php echo h($shopName); ?> オフィシャルサイト</div>
            </a>
        </div>
    </div>
</div>

<script>
// ハンバーガーメニューの開閉処理
(function() {
    const hamburgerButton = document.querySelector('.hamburger-button');
    const menuOverlay = document.getElementById('popup-menu-overlay');
    const menuPanel = document.getElementById('popup-menu-panel');
    const closeButton = document.getElementById('close-menu-button');
    
    if (!hamburgerButton || !menuOverlay || !menuPanel) {
        return;
    }
    
    // メニューを開く
    function openMenu() {
        menuOverlay.classList.add('active');
        menuPanel.classList.add('active');
        document.body.style.overflow = 'hidden'; // スクロールを無効化
        menuOverlay.setAttribute('aria-hidden', 'false');
        hamburgerButton.setAttribute('aria-expanded', 'true');
    }
    
    // メニューを閉じる
    function closeMenu() {
        menuOverlay.classList.remove('active');
        menuPanel.classList.remove('active');
        document.body.style.overflow = ''; // スクロールを有効化
        menuOverlay.setAttribute('aria-hidden', 'true');
        hamburgerButton.setAttribute('aria-expanded', 'false');
    }
    
    // ハンバーガーボタンクリック
    hamburgerButton.addEventListener('click', function() {
        const isOpen = menuOverlay.classList.contains('active');
        if (isOpen) {
            closeMenu();
        } else {
            openMenu();
        }
    });
    
    // 閉じるボタンクリック
    if (closeButton) {
        closeButton.addEventListener('click', closeMenu);
    }
    
    // オーバーレイクリックで閉じる
    menuOverlay.addEventListener('click', function(e) {
        if (e.target === menuOverlay) {
            closeMenu();
        }
    });
    
    // ESCキーで閉じる
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && menuOverlay.classList.contains('active')) {
            closeMenu();
        }
    });
})();
</script>
