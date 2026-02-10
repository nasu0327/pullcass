<?php
/**
 * フリーページ用PCプレビューラッパーページ
 * PC版のプレビュー画面（警告モーダル付き）
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

// テーマカラーを取得
require_once __DIR__ . '/../../includes/theme_helper.php';
$currentTheme = getCurrentTheme($tenantId);
$primaryColor = $currentTheme['theme_data']['colors']['primary'] ?? '#f568df';
$btnTextColor = $currentTheme['theme_data']['colors']['btn_text'] ?? '#ffffff';

// プレビューURL（iframe内で表示）
$previewUrl = '/app/front/free_preview.php?tenant=' . urlencode($tenantSlug) . '&id=' . urlencode($pageId);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>PCプレビュー - フリーページ</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/preview-common.css?v=<?php echo time(); ?>">
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
            <h3 class="modal-title">フリーページプレビューモード</h3>
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
            PC版プレビュー
        </div>
        <button class="preview-mode-badge" onclick="closePreview();">
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
        
        function closePreview() {
            if (window.opener) {
                window.close();
            } else {
                window.location.href = '/app/manage/free_page/?tenant=<?php echo urlencode($tenantSlug); ?>';
            }
        }
    </script>
</body>
</html>
