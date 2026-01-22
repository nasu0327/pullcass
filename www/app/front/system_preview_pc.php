<?php
/**
 * 料金システムページ用PCプレビューラッパーページ
 * PC版のプレビュー画面（警告モーダル付き）
 */
require_once __DIR__ . '/../../includes/bootstrap.php';

// URLパラメータからテナント情報を取得
$tenantCode = $_GET['tenant'] ?? null;
if (!$tenantCode) {
    die('テナント情報が見つかりません。URLパラメータ tenant が必要です。');
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

// テーマカラーを取得
require_once __DIR__ . '/../../includes/theme_helper.php';
$currentTheme = getCurrentTheme($tenantId);
$primaryColor = $currentTheme['theme_data']['colors']['primary'] ?? '#f568df';
$btnTextColor = $currentTheme['theme_data']['colors']['btn_text'] ?? '#ffffff';

// set_idパラメータを取得（編集画面からのプレビュー用）
$setId = isset($_GET['set_id']) ? intval($_GET['set_id']) : null;
$previewUrl = '/app/front/system_preview.php?tenant=' . urlencode($tenantSlug) . '&iframe_preview=1' . ($setId ? '&set_id=' . $setId : '');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>PCプレビュー - 料金システム</title>
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
            font-family: "Hiragino Kaku Gothic ProN", "Hiragino Sans", "Meiryo", sans-serif;
        }
        
        .preview-header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 50px;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 0 20px;
        }
        
        .preview-mode-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: <?php echo $primaryColor; ?>;
            color: <?php echo $btnTextColor; ?>;
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 14px;
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
            font-size: 18px;
            opacity: 0.8;
        }
        
        .preview-info {
            position: absolute;
            left: 20px;
            color: rgba(255,255,255,0.7);
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .preview-info .material-icons {
            font-size: 18px;
        }
        
        .preview-container {
            padding-top: 50px;
            height: 100vh;
        }
        
        .preview-container iframe {
            width: 100%;
            height: calc(100vh - 50px);
            border: none;
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
            padding: 30px 40px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            max-width: 450px;
            text-align: center;
            transform: scale(1);
            transition: transform 0.3s ease;
            border-top: 5px solid <?php echo $primaryColor; ?>;
        }
        
        .modal-icon {
            font-size: 50px;
            margin-bottom: 15px;
        }
        
        .modal-title {
            margin: 0 0 15px 0;
            font-size: 20px;
            font-weight: bold;
            color: #333;
        }
        
        .modal-warning {
            margin: 0;
            font-size: 15px;
            color: #d9534f;
            font-weight: bold;
            line-height: 1.6;
        }
        
        .modal-note {
            margin: 15px 0 0 0;
            font-size: 13px;
            color: #666;
        }
        
        .modal-btn {
            background: <?php echo $primaryColor; ?>;
            border: none;
            color: <?php echo $btnTextColor; ?>;
            padding: 12px 30px;
            border-radius: 25px;
            cursor: pointer;
            font-weight: bold;
            font-size: 15px;
            transition: all 0.3s ease;
            margin-top: 20px;
        }
        
        .modal-btn:hover {
            opacity: 0.9;
            transform: scale(1.02);
        }
    </style>
</head>
<body>
    <!-- 警告モーダル -->
    <div id="preview-modal-overlay" class="modal-overlay">
        <div id="preview-modal" class="modal-content">
            <div class="modal-icon">⚠️</div>
            <h3 class="modal-title">料金システムプレビューモード</h3>
            <p class="modal-warning">
                プレビューを終了する場合は<br>
                必ず「プレビューモード ✕」で<br>
                閉じてください！
            </p>
            <p class="modal-note">
                ※ウィンドウの✕ボタンで閉じてもOKです
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
            PC版プレビュー（料金システム）
        </div>
        <button class="preview-mode-badge" onclick="closePreview();">
            プレビューモード
            <span class="material-icons">close</span>
        </button>
    </div>
    
    <!-- プレビューコンテンツ -->
    <div class="preview-container">
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
        
        function closePreview() {
            // window.close()を試す
            if (window.opener) {
                window.close();
            } else {
                // 通常のタブで開かれている場合、管理画面に戻る
                window.location.href = '/app/manage/price_manage/index.php?tenant=<?php echo urlencode($tenantSlug); ?>';
            }
        }
    </script>
</body>
</html>
