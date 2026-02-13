<?php
/**
 * スマホプレビュー用ラッパーページ（フリーページ）
 * iPhone 16 Pro UIを擬似再現
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

// URLパラメータからテナント情報を取得
$tenantCode = $_GET['tenant'] ?? null;
$pageId = $_GET['id'] ?? null;

if (!$tenantCode) {
    die('テナント情報が見つかりません。URLパラメータ tenant が必要です。');
}

if (!$pageId) {
    die('ページIDが見つかりません。URLパラメータ id が必要です。');
}

$pdo = getPlatformDb();
$stmt = $pdo->prepare("SELECT * FROM tenants WHERE code = ? AND is_active = 1");
$stmt->execute([$tenantCode]);
$tenant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tenant) {
    die('指定されたテナントが見つかりません。');
}

$tenantId = $tenant['id'];
$tenantSlug = $tenant['code'];

// キャッシュ無効化（管理画面のため常に最新を取得）
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

// テーマカラーを取得
require_once __DIR__ . '/../../includes/theme_helper.php';
$currentTheme = getCurrentTheme($tenantId);
$primaryColor = $currentTheme['theme_data']['colors']['primary'] ?? '#f568df';
$btnTextColor = $currentTheme['theme_data']['colors']['btn_text'] ?? '#ffffff';
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>スマホプレビュー - フリーページ</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/preview-common.css?v=<?php echo time(); ?>">
    <script>
    (function(){
        var t = localStorage.getItem('manage-theme');
        if (t === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
    })();
    </script>
    <style>
        .preview-mode-badge { background: <?php echo $primaryColor; ?>; color: <?php echo $btnTextColor; ?>; }
        .modal-content { border-top: 5px solid <?php echo $primaryColor; ?>; }
        .modal-btn { background: <?php echo $primaryColor; ?>; color: <?php echo $btnTextColor; ?>; }
    </style>
</head>

<body class="preview-mobile">
    <!-- 警告モーダル -->
    <div id="preview-modal-overlay" class="modal-overlay">
        <div id="preview-modal" class="modal-content">
            <div style="margin-bottom: 15px;">
                <div style="font-size: 40px; margin-bottom: 10px;">📱</div>
                <h3 style="margin: 0 0 12px 0; font-size: 18px; font-weight: bold; color: #333;">フリーページプレビューモード</h3>
                <p style="margin: 0; font-size: 14px; color: #d9534f; font-weight: bold; line-height: 1.5;">
                    プレビューを終了する場合は<br>
                    「プレビューモード ✕」で<br>
                    閉じてください！
                </p>
                <p style="margin: 12px 0 0 0; font-size: 12px; color: #666;">
                    ※ウィンドウの✕ボタンで閉じてもOKです
                </p>
            </div>
            <button id="close-preview-modal" class="modal-btn">
                OK、理解しました
            </button>
        </div>
    </div>

    <div class="preview-container-mobile">
        <div class="preview-wrapper">
            <div class="device-info">
                iPhone 16 Pro
            </div>
            <div class="preview-mode-info">
                <button class="preview-mode-badge" onclick="window.close();">
                    プレビューモード
                    <span class="material-icons">close</span>
                </button>
            </div>

            <div class="phone-frame">
                <div class="phone-inner">
                    <!-- ステータスバー -->
                    <div class="status-bar">
                        <div class="dynamic-island"></div>
                        <div class="status-left" id="status-time">9:41</div>
                        <div class="status-right">
                            <div class="signal-bars">
                                <div class="signal-bar"></div>
                                <div class="signal-bar"></div>
                                <div class="signal-bar"></div>
                                <div class="signal-bar"></div>
                            </div>
                            <div class="wifi-icon">
                                <div class="wifi-dot"></div>
                            </div>
                            <div class="battery-icon">
                                <div class="battery-body">
                                    <div class="battery-level"></div>
                                </div>
                                <div class="battery-cap"></div>
                            </div>
                        </div>
                    </div>

                    <!-- コンテンツエリア -->
                    <div class="content-area">
                        <iframe
                            src="/app/front/free_preview.php?tenant=<?php echo urlencode($tenantSlug); ?>&id=<?php echo urlencode($pageId); ?>"
                            title="スマホプレビュー"></iframe>
                    </div>

                    <!-- Safari UI -->
                    <div class="safari-bottom">
                        <!-- URLバー -->
                        <div class="url-bar">
                            <div class="url-input">
                                <span class="lock-icon">🔒</span>
                                <?php echo h($tenantSlug); ?>.pullcass.com
                            </div>
                        </div>

                        <!-- ナビゲーションバー -->
                        <div class="nav-bar">
                            <div class="nav-btn disabled">
                                <span class="material-icons">chevron_left</span>
                            </div>
                            <div class="nav-btn disabled">
                                <span class="material-icons">chevron_right</span>
                            </div>
                            <div class="nav-btn">
                                <span class="material-icons">ios_share</span>
                            </div>
                            <div class="nav-btn">
                                <span class="material-icons">auto_stories</span>
                            </div>
                            <div class="nav-btn">
                                <span class="material-icons">tab</span>
                            </div>
                        </div>

                        <!-- ホームインジケーター -->
                        <div class="home-indicator-area">
                            <div class="home-indicator"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // 現在時刻を表示
        function updateStatusTime() {
            const now = new Date();
            const hours = now.getHours();
            const minutes = now.getMinutes().toString().padStart(2, '0');
            const timeStr = hours + ':' + minutes;
            const timeEl = document.getElementById('status-time');
            if (timeEl) {
                timeEl.textContent = timeStr;
            }
        }

        // ページ読み込み時に時刻を設定
        document.addEventListener('DOMContentLoaded', function () {
            updateStatusTime();

            // 警告モーダルを閉じる
            const overlay = document.getElementById('preview-modal-overlay');
            const modal = document.getElementById('preview-modal');
            const closeBtn = document.getElementById('close-preview-modal');

            if (closeBtn && overlay && modal) {
                closeBtn.addEventListener('click', function () {
                    modal.style.transform = 'scale(0.9)';
                    overlay.style.opacity = '0';
                    setTimeout(function () {
                        overlay.style.display = 'none';
                    }, 300);
                });
            }
        });
    </script>
</body>

</html>