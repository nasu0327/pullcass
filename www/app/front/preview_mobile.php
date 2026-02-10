<?php
/**
 * テーマ用スマホプレビューラッパーページ
 * iPhone 16 Pro + Safari (iOS 26 Liquid Glass) UIを擬似再現
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
$previewUrl = 'https://' . $tenantCode . '.pullcass.com/app/front/top.php?preview=1&iframe_preview=1';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>スマホプレビュー - テーマ管理</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/preview-common.css?v=<?php echo time(); ?>">
    <style>
        .preview-mode-badge { background: <?php echo $primaryColor; ?>; color: <?php echo $btnTextColor; ?>; }
        .modal-content { border-top: 5px solid <?php echo $primaryColor; ?>; }
        .modal-btn { background: <?php echo $primaryColor; ?>; color: <?php echo $btnTextColor; ?>; }
        .content-area { height: 720px; }
    </style>
</head>
<body class="preview-mobile">
    <!-- 警告モーダル -->
    <div id="preview-modal-overlay" class="modal-overlay">
        <div id="preview-modal" class="modal-content">
            <div style="margin-bottom: 15px;">
                <div style="font-size: 40px; margin-bottom: 10px;">⚠️</div>
                <h3 style="margin: 0 0 12px 0; font-size: 18px; font-weight: bold; color: #333;">テーマプレビューモード</h3>
                <p style="margin: 0; font-size: 14px; color: #d9534f; font-weight: bold; line-height: 1.5;">
                    プレビューを終了する場合は<br>
                    必ず「プレビューモード ✕」で<br>
                    閉じてください！
                </p>
                <p style="margin: 12px 0 0 0; font-size: 12px; color: #666;">
                    ※ウィンドウの✕ボタンで閉じても終了できます
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
                <button class="preview-mode-badge" onclick="exitPreview()">
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
                        <iframe src="<?php echo $previewUrl; ?>" title="スマホプレビュー"></iframe>
                    </div>
                    
                    <!-- Safari UI -->
                    <div class="safari-bottom">
                        <div class="url-bar">
                            <div class="url-input">
                                <span class="lock-icon">🔒</span>
                                <?php echo $tenantCode; ?>.pullcass.com
                            </div>
                        </div>
                        
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
                                <span class="material-icons">bookmark_border</span>
                            </div>
                            <div class="nav-btn">
                                <span class="material-icons">filter_none</span>
                            </div>
                        </div>
                        
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
            document.getElementById('status-time').textContent = hours + ':' + minutes;
        }
        
        // 警告モーダルを閉じる
        document.addEventListener('DOMContentLoaded', function() {
            updateStatusTime();
            
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
            fetch('/app/manage/themes/api_preview.php?action=stop&tenant=<?php echo urlencode($tenantCode); ?>', {
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
                navigator.sendBeacon('/app/manage/themes/api_preview.php?action=stop&tenant=<?php echo urlencode($tenantCode); ?>');
            }
        });
    </script>
</body>
</html>
