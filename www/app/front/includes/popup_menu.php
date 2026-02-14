<?php
/**
 * pullcass - ハンバーガーメニュー（ポップアップメニュー）
 * データベースから動的にメニュー項目を読み込んで表示
 * 参考サイトのデザインを忠実に再現
 */

// メニュー背景設定を取得
if (!function_exists('getMenuBackground')) {
    require_once __DIR__ . '/../../../app/manage/menu_management/includes/background_functions.php';
}
$menuBgSettings = getMenuBackground($pdo, $tenantId);

// メニュー項目を取得
$pdo = getPlatformDb();
$menuItems = [];

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
        ['code' => 'TOP', 'label' => 'トップ', 'url' => '/top', 'link_type' => 'internal', 'target' => '_self'],
        ['code' => 'CAST', 'label' => 'キャスト一覧', 'url' => '/cast/list', 'link_type' => 'internal', 'target' => '_self'],
        ['code' => 'SCHEDULE', 'label' => 'スケジュール', 'url' => '/schedule/day1', 'link_type' => 'internal', 'target' => '_self'],
        ['code' => 'SYSTEM', 'label' => '料金システム', 'url' => '/system', 'link_type' => 'internal', 'target' => '_self'],
    ];
}
?>

<!-- ハンバーガーメニュー ポップアップ（参考サイト準拠） -->
<div id="popup-menu-overlay" class="popup-menu-overlay" aria-hidden="true">
    <div id="popup-menu-panel" class="popup-menu-panel">
        <?php if ($menuBgSettings && $menuBgSettings['background_type'] === 'image' && !empty($menuBgSettings['background_image'])): ?>
        <!-- 背景画像モード -->
        <div class="menu-background-image" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-image: url('<?php echo h($menuBgSettings['background_image']); ?>'); background-size: cover; background-position: center; z-index: 1;"></div>
        <div class="menu-background-overlay" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-color: <?php echo h($menuBgSettings['overlay_color'] ?? '#000000'); ?>; opacity: <?php echo h($menuBgSettings['overlay_opacity'] ?? 0.5); ?>; z-index: 2;"></div>
        <?php endif; ?>
        
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
            <a href="/" class="popup-footer-link">
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
// ハンバーガーメニューの開閉処理（参考サイト準拠）
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
        menuOverlay.classList.add('is-open');
        document.body.style.overflow = 'hidden'; // スクロールを無効化
        menuOverlay.setAttribute('aria-hidden', 'false');
        hamburgerButton.setAttribute('aria-expanded', 'true');
    }
    
    // メニューを閉じる
    function closeMenu() {
        menuOverlay.classList.remove('is-open');
        document.body.style.overflow = ''; // スクロールを有効化
        menuOverlay.setAttribute('aria-hidden', 'true');
        hamburgerButton.setAttribute('aria-expanded', 'false');
    }
    
    // ハンバーガーボタンクリック
    hamburgerButton.addEventListener('click', function() {
        const isOpen = menuOverlay.classList.contains('is-open');
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
        if (e.key === 'Escape' && menuOverlay.classList.contains('is-open')) {
            closeMenu();
        }
    });
})();
</script>
