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
            <div class="sidebar-logo"><?php if (!empty($tenant['favicon_url'])): ?><img src="<?php echo h($tenant['favicon_url']); ?>" alt="<?php echo h($shopName); ?>" class="sidebar-favicon"><?php else: ?><i class="fas fa-store"></i><?php endif; ?> 店舗管理</div>
            <div class="sidebar-shop"><?php echo h($shopName); ?> 様</div>
        </div>

        <nav class="sidebar-nav">
            <div class="theme-toggle-wrap">
                <span class="nav-section-title">表示モード</span>
                <button type="button" class="theme-toggle-btn" data-theme="light" data-tooltip="ライトモード" aria-label="ライトモード">
                    <i class="fas fa-sun"></i>
                </button>
                <button type="button" class="theme-toggle-btn" data-theme="dark" data-tooltip="ダークモード" aria-label="ダークモード">
                    <i class="fas fa-moon"></i>
                </button>
            </div>

            <a href="https://<?php echo h($tenant['code']); ?>.pullcass.com/" class="nav-item"
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
                <a href="/app/manage/cast_data/?tenant=<?php echo h($tenantSlug); ?>"
                    class="nav-item <?php echo $currentDir === 'cast_data' ? 'active' : ''; ?>">
                    <i class="fas fa-sync"></i> スクレイピング
                </a>
                <a href="/app/manage/ranking/?tenant=<?php echo h($tenantSlug); ?>"
                    class="nav-item <?php echo $currentDir === 'ranking' ? 'active' : ''; ?>">
                    <i class="fas fa-trophy"></i> ランキング
                </a>
                <a href="/app/manage/movie_management/?tenant=<?php echo h($tenantSlug); ?>"
                    class="nav-item <?php echo $currentDir === 'movie_management' ? 'active' : ''; ?>">
                    <i class="fas fa-video"></i> 動画管理
                </a>
                <a href="/app/manage/cast_info_management/?tenant=<?php echo h($tenantSlug); ?>"
                    class="nav-item <?php echo $currentDir === 'cast_info_management' ? 'active' : ''; ?>">
                    <i class="fas fa-users-cog"></i> キャスト情報管理
                </a>
                <span class="nav-item is-disabled" title="準備中">
                    <i class="fas fa-calendar-alt"></i> スケジュール
                </span>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">コンテンツ設定</div>
                <a href="/app/manage/price_manage/?tenant=<?php echo h($tenantSlug); ?>"
                    class="nav-item <?php echo $currentDir === 'price_manage' ? 'active' : ''; ?>">
                    <i class="fas fa-yen-sign"></i> 料金表管理
                </a>
                <a href="/app/manage/cast_widgets/?tenant=<?php echo h($tenantSlug); ?>"
                    class="nav-item <?php echo $currentDir === 'cast_widgets' ? 'active' : ''; ?>">
                    <i class="fas fa-code"></i> ウィジェット
                </a>
                <a href="/app/manage/free_page/?tenant=<?php echo h($tenantSlug); ?>"
                    class="nav-item <?php echo $currentDir === 'free_page' ? 'active' : ''; ?>">
                    <i class="fas fa-file-alt"></i> フリーページ編集
                </a>
                <a href="/app/manage/menu_management/?tenant=<?php echo h($tenantSlug); ?>"
                    class="nav-item <?php echo $currentDir === 'menu_management' ? 'active' : ''; ?>">
                    <i class="fas fa-bars"></i> メニュー管理
                </a>
                <a href="/app/manage/hotel_management/?tenant=<?php echo h($tenantSlug); ?>"
                    class="nav-item <?php echo $currentDir === 'hotel_management' ? 'active' : ''; ?>">
                    <i class="fas fa-hotel"></i> ホテルリスト
                </a>
                <a href="/app/manage/reciprocal_links/?tenant=<?php echo h($tenantSlug); ?>"
                    class="nav-item <?php echo $currentDir === 'reciprocal_links' ? 'active' : ''; ?>">
                    <i class="fas fa-link"></i> 相互リンク
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">HPデザイン</div>
                <a href="/app/manage/themes/?tenant=<?php echo h($tenantSlug); ?>"
                    class="nav-item <?php echo $currentDir === 'themes' ? 'active' : ''; ?>">
                    <i class="fas fa-palette"></i> テーマ設定
                </a>
                <a href="/app/manage/index_layout/?tenant=<?php echo h($tenantSlug); ?>"
                    class="nav-item <?php echo $currentDir === 'index_layout' ? 'active' : ''; ?>">
                    <svg class="nav-icon-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 66.146 66.146"><g style="display:inline"><path style="fill:currentColor" d="m30.083 43.722v-7.662h-2.541-2.541v1.74 1.739h.817.817v5.922 5.923h1.724 1.724zm5.745-2.086c-.919-.54-1.02-1.969-.184-2.586.377-.278 1.115-.336 1.552-.121.937.46 1.059 1.978.21 2.605-.382.282-1.184.334-1.578.102zm-.073 6.495c-1.028-.58-1.354-1.85-.716-2.786.365-.535.85-.778 1.55-.775.475 0 .621.044.946.282 1.038.757 1.064 2.259.053 3.092-.294.243-.435.289-.953.312-.432.02-.686-.016-.88-.125zm2.53 3.307c2.096-.729 3.455-2.52 3.572-4.707.073-1.347-.24-2.421-1.009-3.458l-.333-.45.23-.406c.345-.61.527-1.34.53-2.125.006-2.035-1.402-3.8-3.49-4.376-.63-.174-1.893-.172-2.528.003-2.04.562-3.463 2.336-3.471 4.326-.003.775.122 1.304.482 2.045l.264.541-.33.446c-.665.896-1.026 1.997-1.028 3.129-.006 3.662 3.637 6.24 7.111 5.032zm2.144 3.283v-1.269h-6.897-6.897v1.27 1.269h6.897 6.897zm-16.588 1.242-9.192-9.523 1.106-.114c2.185-.226 3.894-1.063 5.38-2.632 1.461-1.543 1.868-2.541 3.092-7.586.426-1.758 1.077-4.36 1.446-5.781.369-1.422 1.02-3.939 1.447-5.594 2.866-11.106 3.792-14.661 3.827-14.697.021-.023.296.036.609.13.972.294 2.59.21 3.519-.181.112-.047.73 2.252 3.489 12.986 1.844 7.173 3.61 14.058 3.927 15.299.78 3.067 1.207 3.98 2.515 5.392 1.474 1.591 3.134 2.414 5.383 2.67l1.021.116-9.189 9.518-9.189 9.518z"/><path style="fill:currentColor" d="m14.747 44.355c-1.241-.075-2.63-.607-3.545-1.357-.648-.531-9.849-10.161-10.074-10.544-.268-.454-.238-1.19.063-1.56.43-.531 1.03-.703 1.639-.471.14.053 1.221 1.122 2.401 2.374l2.146 2.276.238-.23c.131-.126.227-.26.214-.298-.014-.038-1.385-1.489-3.046-3.224-2.942-3.072-3.288-3.497-3.283-4.024.003-.384.339-1.003.65-1.2.322-.202.909-.25 1.283-.102.122.048 1.613 1.54 3.313 3.314l3.091 3.226.23-.236.231-.237-3.523-3.712c-1.938-2.042-3.571-3.804-3.629-3.917-.058-.113-.106-.409-.106-.658 0-.54.237-.991.627-1.2.343-.184 1.028-.188 1.363-.008.141.076 1.826 1.79 3.744 3.808l3.487 3.671.243-.263.244-.264-2.76-2.922c-2.948-3.12-2.967-3.146-2.828-3.916.121-.666.939-1.189 1.657-1.059.314.057.846.584 5.295 5.242l4.945 5.179.671-2.81c.37-1.544.746-2.963.837-3.152.218-.452.73-.879 1.225-1.023 1.085-.315 2.198.37 2.5 1.537.111.431.093.545-.448 2.772-2.03 8.366-2.59 10.537-2.872 11.158-1.122 2.468-3.594 3.99-6.224 3.83z"/><path style="fill:currentColor" d="m49.457 44.187c-1.243-.323-2.685-1.274-3.451-2.276-.769-1.004-1.078-1.814-1.768-4.629-2.102-8.572-2.449-10.022-2.449-10.24.001-.526.208-1 .622-1.43.672-.695 1.45-.827 2.287-.386.821.431.903.62 1.69 3.921l.693 2.909 4.954-5.187c4.463-4.672 4.989-5.192 5.303-5.248.992-.177 1.835.697 1.651 1.712-.056.31-.435.746-2.834 3.26l-2.77 2.903.247.266.246.267 3.504-3.667c1.927-2.017 3.615-3.727 3.751-3.8.136-.073.444-.132.683-.132.772.001 1.296.545 1.297 1.345 0 .25-.046.545-.104.658-.058.113-1.694 1.872-3.637 3.91l-3.532 3.704.241.244.242.244 3.056-3.213c1.681-1.768 3.17-3.259 3.31-3.314.882-.347 1.69.099 1.911 1.055.151.653.04.8-3.224 4.24l-3.1 3.267.242.251.243.251 2.106-2.245c1.231-1.314 2.228-2.299 2.4-2.373.942-.408 1.932.24 1.933 1.265 0 .223-.045.498-.101.611-.117.238-1.555 1.774-6.43 6.868-3.77 3.94-4.237 4.347-5.513 4.807-.6.216-.888.26-1.93.29-.886.025-1.376-.005-1.77-.107z"/><path style="fill:currentColor" d="m15.527 25.541c-1.34-1.389-2.421-2.569-2.402-2.622.019-.054.565-.678 1.214-1.388.648-.71 1.277-1.4 1.397-1.535.119-.135 2.604-2.877 5.521-6.093 5.779-6.37 5.64-6.24 6.485-6.1.44.073.922.418 1.105.789.314.636.331.551-1.575 7.984-1.004 3.917-1.868 7.261-1.92 7.43l-.094.309-.306-.279c-1.4-1.273-3.452-1.376-4.935-.248-.883.671-1.306 1.44-1.684 3.06-.139.594-.279 1.112-.311 1.15-.032.037-1.155-1.068-2.495-2.457z"/><path style="fill:currentColor" d="m47.84 26.964c-.31-1.403-.65-2.15-1.262-2.776-1.47-1.504-3.845-1.589-5.34-.19-.199.186-.374.322-.389.302-.015-.02-.872-3.296-1.903-7.278-1.275-4.923-1.876-7.394-1.876-7.718 0-.613.355-1.158.9-1.384.968-.401 1.187-.255 3.564 2.37 1.107 1.222 2.65 2.92 3.43 3.773.78.853 2.739 3.011 4.354 4.794 1.615 1.784 3.12 3.443 3.347 3.688l.41.444-2.441 2.54c-1.343 1.398-2.466 2.541-2.496 2.541-.03 0-.164-.498-.298-1.106z"/><path style="fill:currentColor" d="m32.147 8.232c-20.801 38.168-10.401 19.084 0 0z"/><circle style="fill:currentColor" cx="33.137" cy="4.778" r="3.704"/></g></svg> 認証ページ編集
                </a>
                <a href="/app/manage/top_layout/?tenant=<?php echo h($tenantSlug); ?>"
                    class="nav-item <?php echo $currentDir === 'top_layout' ? 'active' : ''; ?>">
                    <i class="fas fa-th-large"></i> トップページ編集
                </a>
                <a href="/app/manage/settings/?tenant=<?php echo h($tenantSlug); ?>"
                    class="nav-item <?php echo $currentDir === 'settings' ? 'active' : ''; ?>">
                    <i class="fas fa-cog"></i> 店舗設定
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">ネット予約</div>
                <a href="/app/manage/reservation_management/?tenant=<?php echo h($tenantSlug); ?>"
                    class="nav-item <?php echo $currentDir === 'reservation_management' && $currentPage === 'index' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-check"></i> 予約機能設定
                </a>
                <a href="/app/manage/reservation_management/list?tenant=<?php echo h($tenantSlug); ?>"
                    class="nav-item <?php echo $currentDir === 'reservation_management' && $currentPage === 'list' ? 'active' : ''; ?>">
                    <i class="fas fa-list-alt"></i> 予約管理
                </a>
            </div>

            <hr class="nav-divider">

            <a href="/app/manage/logout" class="nav-item logout-link">
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