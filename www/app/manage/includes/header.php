<?php
/**
 * 店舗管理画面 - 共通ヘッダー（HTML出力部分）
 * ※POST処理後に読み込むこと
 */

// auth.phpがまだ読み込まれていない場合は読み込む
if (!isset($tenant) || !isset($tenantSlug) || !isset($tenantId)) {
    require_once __DIR__ . '/auth.php';
}

// 現在のページを判定
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$currentDir = basename(dirname($_SERVER['PHP_SELF']));
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? h($pageTitle) . ' - ' : ''; ?><?php echo h($shopName); ?> 様 管理画面 | pullcass
    </title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/manage.css?v=<?php echo time(); ?>">
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script>
    (function(){
        var t = localStorage.getItem('manage-theme');
        if (t === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
    })();
    </script>
</head>

<body>
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo"><i class="fas fa-store"></i> 店舗管理</div>
            <div class="sidebar-shop"><?php echo h($shopName); ?> 様</div>
        </div>

        <nav class="sidebar-nav">
            <a href="https://<?php echo h($tenant['code']); ?>.pullcass.com/app/front/index.php" class="nav-item"
                target="_blank">
                <i class="fas fa-globe"></i> サイトを確認
            </a>

            <hr class="nav-divider">

            <div class="nav-section">
                <a href="/app/manage/?tenant=<?php echo h($tenantSlug); ?>"
                    class="nav-item <?php echo $currentDir === 'manage' && $currentPage === 'index' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-pie"></i> ダッシュボード
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">情報更新</div>
                <a href="/app/manage/top_banner/?tenant=<?php echo h($tenantSlug); ?>"
                    class="nav-item <?php echo $currentDir === 'top_banner' ? 'active' : ''; ?>">
                    <i class="fas fa-images"></i> トップバナー
                </a>
                <a href="/app/manage/news_ticker/?tenant=<?php echo h($tenantSlug); ?>"
                    class="nav-item <?php echo $currentDir === 'news_ticker' ? 'active' : ''; ?>">
                    <i class="fas fa-bullhorn"></i> ニュースティッカー
                </a>
                <a href="/app/manage/index_layout/?tenant=<?php echo h($tenantSlug); ?>"
                    class="nav-item <?php echo $currentDir === 'index_layout' ? 'active' : ''; ?>">
                    <i class="fas fa-door-open"></i> 認証ページ編集
                </a>
                <a href="/app/manage/top_layout/?tenant=<?php echo h($tenantSlug); ?>"
                    class="nav-item <?php echo $currentDir === 'top_layout' ? 'active' : ''; ?>">
                    <i class="fas fa-th-large"></i> トップページ編集
                </a>
                <a href="/app/manage/reciprocal_links/?tenant=<?php echo h($tenantSlug); ?>"
                    class="nav-item <?php echo $currentDir === 'reciprocal_links' ? 'active' : ''; ?>">
                    <i class="fas fa-link"></i> 相互リンク
                </a>
                <a href="/app/manage/free_page/?tenant=<?php echo h($tenantSlug); ?>"
                    class="nav-item <?php echo $currentDir === 'free_page' ? 'active' : ''; ?>">
                    <i class="fas fa-file-alt"></i> フリーページ
                </a>
                <a href="/app/manage/hotel_management/?tenant=<?php echo h($tenantSlug); ?>"
                    class="nav-item <?php echo $currentDir === 'hotel_management' ? 'active' : ''; ?>">
                    <i class="fas fa-hotel"></i> ホテル管理
                </a>
                <a href="/app/manage/menu_management/?tenant=<?php echo h($tenantSlug); ?>"
                    class="nav-item <?php echo $currentDir === 'menu_management' ? 'active' : ''; ?>">
                    <i class="fas fa-bars"></i> メニュー管理
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">キャスト管理</div>
                <a href="/app/manage/cast_data/?tenant=<?php echo h($tenantSlug); ?>"
                    class="nav-item <?php echo $currentDir === 'cast_data' ? 'active' : ''; ?>">
                    <i class="fas fa-sync"></i> スクレイピング
                </a>
                <a href="/app/manage/cast_info_management/?tenant=<?php echo h($tenantSlug); ?>"
                    class="nav-item <?php echo $currentDir === 'cast_info_management' ? 'active' : ''; ?>">
                    <i class="fas fa-users-cog"></i> キャスト情報管理
                </a>
                <a href="/app/manage/cast_widgets/?tenant=<?php echo h($tenantSlug); ?>"
                    class="nav-item <?php echo $currentDir === 'cast_widgets' ? 'active' : ''; ?>">
                    <i class="fas fa-code"></i> ウィジェット登録
                </a>

                <a href="/app/manage/ranking/?tenant=<?php echo h($tenantSlug); ?>"
                    class="nav-item <?php echo $currentDir === 'ranking' ? 'active' : ''; ?>">
                    <i class="fas fa-trophy"></i> ランキング
                </a>
                <a href="/app/manage/schedules/?tenant=<?php echo h($tenantSlug); ?>"
                    class="nav-item <?php echo $currentDir === 'schedules' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-alt"></i> スケジュール
                </a>
                <a href="/app/manage/movie_management/?tenant=<?php echo h($tenantSlug); ?>"
                    class="nav-item <?php echo $currentDir === 'movie_management' ? 'active' : ''; ?>">
                    <i class="fas fa-video"></i> 動画管理
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">料金・設定</div>
                <a href="/app/manage/price_manage/?tenant=<?php echo h($tenantSlug); ?>"
                    class="nav-item <?php echo $currentDir === 'price_manage' ? 'active' : ''; ?>">
                    <i class="fas fa-yen-sign"></i> 料金表管理
                </a>
                <a href="/app/manage/reservation_management/?tenant=<?php echo h($tenantSlug); ?>"
                    class="nav-item <?php echo $currentDir === 'reservation_management' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-check"></i> 予約機能管理
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">設定</div>
                <a href="/app/manage/themes/?tenant=<?php echo h($tenantSlug); ?>"
                    class="nav-item <?php echo $currentDir === 'themes' ? 'active' : ''; ?>">
                    <i class="fas fa-palette"></i> テーマ設定
                </a>
                <a href="/app/manage/settings/?tenant=<?php echo h($tenantSlug); ?>"
                    class="nav-item <?php echo $currentDir === 'settings' ? 'active' : ''; ?>">
                    <i class="fas fa-cog"></i> 店舗設定
                </a>
            </div>

            <hr class="nav-divider">

            <div class="theme-toggle-wrap">
                <span class="nav-section-title">表示</span>
                <button type="button" class="theme-toggle-btn" data-theme="light" title="ライトモード" aria-label="ライトモード">
                    <i class="fas fa-sun"></i>
                </button>
                <button type="button" class="theme-toggle-btn" data-theme="dark" title="ダークモード" aria-label="ダークモード">
                    <i class="fas fa-moon"></i>
                </button>
            </div>

            <a href="/app/manage/logout.php" class="nav-item logout-link">
                <i class="fas fa-sign-out-alt"></i> ログアウト
            </a>
        </nav>
    </aside>

    <script>
    (function(){
        var key = 'manage-theme';
        function applyTheme(theme) {
            if (theme === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
            else document.documentElement.removeAttribute('data-theme');
            localStorage.setItem(key, theme);
            document.querySelectorAll('.theme-toggle-btn').forEach(function(btn) {
                btn.classList.toggle('active', btn.getAttribute('data-theme') === theme);
            });
        }
        document.querySelectorAll('.theme-toggle-btn').forEach(function(btn) {
            btn.addEventListener('click', function() { applyTheme(this.getAttribute('data-theme')); });
        });
        var saved = localStorage.getItem(key) || 'light';
        applyTheme(saved);
    })();
    </script>
    <script>
    // サイドバーのスクロール位置を維持
    document.addEventListener('DOMContentLoaded', function() {
        const sidebarNav = document.querySelector('.sidebar-nav');
        if (!sidebarNav) return;

        // 保存された位置を復元
        const savedPos = sessionStorage.getItem('sidebarScrollPos');
        if (savedPos) {
            sidebarNav.scrollTop = parseInt(savedPos, 10);
        }

        // スクロール位置を保存
        sidebarNav.addEventListener('scroll', function() {
            sessionStorage.setItem('sidebarScrollPos', this.scrollTop);
        });
    });
    </script>

    <main class="main-content">