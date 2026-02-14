<?php
/**
 * テーマ用PCプレビューラッパーページ
 * PC版のプレビュー画面（警告モーダル付き）
 */
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/theme_helper.php';
require_once __DIR__ . '/../../includes/tenant.php';

// テナント情報を取得（リクエストのサブドメインを優先）
$tenantFromRequest = getTenantFromRequest();
$tenantFromSession = getCurrentTenant();

if ($tenantFromRequest) {
    $tenant = $tenantFromRequest;
    if (!$tenantFromSession || $tenantFromSession['id'] !== $tenant['id']) {
        setCurrentTenant($tenant);
    }
} elseif ($tenantFromSession) {
    $tenant = $tenantFromSession;
} else {
    die('テナントが見つかりません');
}

$tenantId = $tenant['id'];
$tenantCode = $tenant['code'];

// テーマ情報を取得してカラーを取得
$currentTheme = getCurrentTheme($tenantId);
$primaryColor = $currentTheme['theme_data']['colors']['primary'] ?? '#f568df';
$btnTextColor = $currentTheme['theme_data']['colors']['btn_text'] ?? '#ffffff';

// プレビューURL
$previewUrl = 'https://' . $tenantCode . '.pullcass.com/top?preview=1&iframe_preview=1';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>PCプレビュー - テーマ管理</title>
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
<body class="preview-pc">
    <!-- 警告モーダル -->
    <div id="preview-modal-overlay" class="modal-overlay">
        <div id="preview-modal" class="modal-content">
            <div class="modal-icon">⚠️</div>
            <h3 class="modal-title">テーマプレビューモード</h3>
            <p class="modal-warning">
                プレビューを終了する場合は<br>
                必ず「プレビューモード ✕」で<br>
                閉じてください！
            </p>
            <p class="modal-note">
                ※ウィンドウの✕ボタンで閉じても終了できます
            </p>
            <button id="close-preview-modal" class="modal-btn">
                OK、理解しました
            </button>
        </div>
    </div>
    
    <!-- プレビューヘッダー -->
    <div class="preview-header">
        <div class="preview-info">
            <span class="material-icons">desktop_windows</span>
            PC版プレビュー
        </div>
        <button class="preview-mode-badge" onclick="exitPreview()">
            プレビューモード
            <span class="material-icons">close</span>
        </button>
    </div>
    
    <!-- プレビューコンテンツ -->
    <div class="preview-container-pc">
        <iframe src="<?php echo $previewUrl; ?>" title="PCプレビュー"></iframe>
    </div>
    
    <script>
        // 警告モーダルを閉じる
        document.addEventListener('DOMContentLoaded', function() {
            const overlay = document.getElementById('preview-modal-overlay');
            const modal = document.getElementById('preview-modal');
            const closeBtn = document.getElementById('close-preview-modal');
            
            if (closeBtn && overlay && modal) {
                closeBtn.addEventListener('click', function() {
                    modal.style.transform = 'scale(0.9)';
                    overlay.style.opacity = '0';
                    setTimeout(function() {
                        overlay.style.display = 'none';
                    }, 300);
                });
            }
        });
        
        function exitPreview() {
            fetch('/app/manage/themes/api_preview?action=stop&tenant=<?php echo urlencode($tenantCode); ?>', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                window.close();
                window.location.href = '/app/manage/themes/?tenant=<?php echo urlencode($tenantCode); ?>';
            })
            .catch(error => {
                window.location.href = '/app/manage/themes/?tenant=<?php echo urlencode($tenantCode); ?>';
            });
        }
        
        // ウィンドウを閉じるときにセッションをクリア
        window.addEventListener('beforeunload', function() {
            if (navigator.sendBeacon) {
                navigator.sendBeacon('/app/manage/themes/api_preview?action=stop&tenant=<?php echo urlencode($tenantCode); ?>');
            }
        });
    </script>
</body>
</html>
