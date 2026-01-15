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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .preview-container {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 20px;
            min-height: 100vh;
        }
        
        .preview-wrapper {
            position: relative;
        }
        
        .device-info {
            text-align: center;
            color: rgba(255,255,255,0.8);
            margin-bottom: 12px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .preview-mode-info {
            text-align: center;
            margin-bottom: 12px;
        }
        
        .preview-mode-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: <?php echo $primaryColor; ?>;
            color: <?php echo $btnTextColor; ?>;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
        }
        
        .preview-mode-badge:hover {
            opacity: 0.9;
            transform: scale(1.02);
        }
        
        .preview-mode-badge .material-icons {
            font-size: 14px;
            opacity: 0.8;
        }
        
        /* iPhone 16 Pro フレーム - チタニウム風 */
        .phone-frame {
            background: linear-gradient(145deg, #3a3a3c 0%, #1c1c1e 50%, #2c2c2e 100%);
            border-radius: 55px;
            padding: 10px;
            box-shadow: 
                0 30px 60px rgba(0,0,0,0.4),
                0 0 0 1px rgba(255,255,255,0.1),
                inset 0 1px 0 rgba(255,255,255,0.1);
            position: relative;
        }
        
        /* サイドボタン */
        .phone-frame::before {
            content: '';
            position: absolute;
            right: -3px;
            top: 140px;
            width: 3px;
            height: 80px;
            background: linear-gradient(180deg, #4a4a4c, #2a2a2c);
            border-radius: 0 2px 2px 0;
        }
        
        .phone-frame::after {
            content: '';
            position: absolute;
            left: -3px;
            top: 120px;
            width: 3px;
            height: 35px;
            background: linear-gradient(180deg, #4a4a4c, #2a2a2c);
            border-radius: 2px 0 0 2px;
            box-shadow: 0 55px 0 #3a3a3c, 0 100px 0 #3a3a3c;
        }
        
        .phone-inner {
            background: #000;
            border-radius: 47px;
            overflow: hidden;
            width: 393px;
            position: relative;
        }
        
        /* iOS 26 Liquid Glass ステータスバー */
        .status-bar {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 54px;
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            padding: 0 28px 8px 28px;
            z-index: 100;
            background: linear-gradient(180deg, rgba(255,255,255,0.95) 0%, rgba(255,255,255,0.85) 100%);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }
        
        /* Dynamic Island - iOS 26 */
        .dynamic-island {
            position: absolute;
            top: 11px;
            left: 50%;
            transform: translateX(-50%);
            width: 126px;
            height: 37px;
            background: #000;
            border-radius: 22px;
            box-shadow: 0 0 0 1px rgba(0,0,0,0.1);
        }
        
        .status-left {
            font-size: 16px;
            font-weight: 600;
            color: #000;
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', sans-serif;
            letter-spacing: -0.3px;
        }
        
        .status-right {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        /* 電波アイコン */
        .signal-bars {
            display: flex;
            align-items: flex-end;
            gap: 1.5px;
            height: 12px;
        }
        
        .signal-bar {
            width: 3px;
            background: #000;
            border-radius: 1.5px;
        }
        
        .signal-bar:nth-child(1) { height: 3px; }
        .signal-bar:nth-child(2) { height: 5px; }
        .signal-bar:nth-child(3) { height: 8px; }
        .signal-bar:nth-child(4) { height: 11px; }
        
        /* Wi-Fiアイコン */
        .wifi-icon {
            width: 17px;
            height: 12px;
            position: relative;
        }
        
        .wifi-icon::before {
            content: '';
            position: absolute;
            width: 17px;
            height: 17px;
            border: 2.5px solid transparent;
            border-top-color: #000;
            border-radius: 50%;
            top: -3px;
            left: 0;
            transform: rotate(-45deg);
        }
        
        .wifi-icon::after {
            content: '';
            position: absolute;
            width: 10px;
            height: 10px;
            border: 2px solid transparent;
            border-top-color: #000;
            border-radius: 50%;
            top: 1px;
            left: 3.5px;
            transform: rotate(-45deg);
        }
        
        .wifi-dot {
            position: absolute;
            width: 4px;
            height: 4px;
            background: #000;
            border-radius: 50%;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
        }
        
        /* バッテリーアイコン */
        .battery-icon {
            display: flex;
            align-items: center;
        }
        
        .battery-body {
            width: 27px;
            height: 13px;
            border: 1.5px solid rgba(0,0,0,0.4);
            border-radius: 4px;
            position: relative;
            padding: 2px;
        }
        
        .battery-level {
            background: #000;
            height: 100%;
            width: 85%;
            border-radius: 2px;
        }
        
        .battery-cap {
            width: 2px;
            height: 5px;
            background: rgba(0,0,0,0.4);
            border-radius: 0 2px 2px 0;
            margin-left: 1px;
        }
        
        /* コンテンツエリア（iframe） */
        .content-area {
            height: 720px;
            background: #fff;
            margin-top: 54px;
            position: relative;
        }
        
        .content-area iframe {
            width: 100%;
            height: 100%;
            border: none;
        }
        
        /* Safari UI（下部） */
        .safari-bottom {
            position: relative;
            background: linear-gradient(180deg, rgba(250,250,252,0.92) 0%, rgba(245,245,247,0.98) 100%);
            backdrop-filter: blur(30px) saturate(180%);
            -webkit-backdrop-filter: blur(30px) saturate(180%);
            border-top: 0.5px solid rgba(0,0,0,0.1);
        }
        
        .url-bar {
            height: 52px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 8px 12px;
        }
        
        .url-input {
            background: rgba(120, 120, 128, 0.12);
            border-radius: 12px;
            height: 36px;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            color: #3c3c43;
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Text', sans-serif;
            font-weight: 400;
            letter-spacing: -0.2px;
        }
        
        .url-input .lock-icon {
            font-size: 13px;
            margin-right: 5px;
            opacity: 0.6;
        }
        
        .nav-bar {
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: space-around;
            padding: 0 8px;
        }
        
        .nav-btn {
            width: 50px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #007aff;
            font-size: 22px;
            border-radius: 10px;
            transition: background 0.15s ease;
        }
        
        .nav-btn.disabled {
            color: rgba(60, 60, 67, 0.3);
        }
        
        .home-indicator-area {
            height: 34px;
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding-top: 8px;
        }
        
        .home-indicator {
            width: 134px;
            height: 5px;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 100px;
        }
        
        /* 警告モーダル */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(5px);
            z-index: 10001;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 1;
            transition: opacity 0.3s ease;
        }
        
        .modal-content {
            background: #fff;
            color: #333;
            padding: 25px 30px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            max-width: 400px;
            text-align: center;
            transform: scale(1);
            transition: transform 0.3s ease;
            border-top: 5px solid <?php echo $primaryColor; ?>;
        }
        
        .modal-btn {
            background: <?php echo $primaryColor; ?>;
            border: none;
            color: <?php echo $btnTextColor; ?>;
            padding: 10px 25px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: bold;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .modal-btn:hover {
            opacity: 0.9;
            transform: scale(1.02);
        }
        
        @media (max-width: 500px) {
            .preview-container {
                padding: 10px 0;
            }
            .phone-frame {
                border-radius: 0;
                padding: 0;
                box-shadow: none;
            }
            .phone-frame::before,
            .phone-frame::after {
                display: none;
            }
            .phone-inner {
                border-radius: 0;
                width: 100vw;
            }
            .content-area {
                height: calc(100vh - 200px);
            }
            .device-info {
                display: none;
            }
        }
    </style>
</head>
<body>
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
    
    <div class="preview-container">
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
