<?php
/**
 * テーマ編集画面
 */

// POST処理を先に行う
require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();
require_once __DIR__ . '/../../../includes/theme_helper.php';

$themeId = (int)($_GET['id'] ?? 0);
$theme = getTenantThemeById($themeId, $tenantId);

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (!$theme) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'テーマが見つかりません']);
        exit;
    }
    header("Location: index.php?tenant=" . urlencode($tenantSlug) . "&error=" . urlencode('テーマが見つかりません'));
    exit;
}

$message = $_GET['message'] ?? '';
$pageTitle = 'テーマ編集';

// ===========================
// アクション: テーマ更新
// ===========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_type'])) {
    $themeName = $_POST['theme_name'];
    $notes = $_POST['notes'] ?? '';
    $saveType = $_POST['save_type']; // 'draft' or 'publish'
    
    // カラー設定
    $bgType = $_POST['bg_type'] ?? 'solid';
    $colors = [
        'primary' => $_POST['color_primary'],
        'primary_light' => $_POST['color_primary_light'],
        'text' => $_POST['color_text'],
        'btn_text' => $_POST['color_btn_text'],
        'bg' => $_POST['color_bg'],
        'overlay' => $_POST['color_overlay'],
        'bg_type' => $bgType
    ];
    
    // グラデーション背景の場合
    if ($bgType === 'gradient') {
        $colors['bg_gradient_start'] = $_POST['bg_gradient_start'];
        $colors['bg_gradient_end'] = $_POST['bg_gradient_end'];
    }
    
    // フォント設定
    $fonts = [
        'title1_en' => $_POST['font_title1_en'],
        'title1_ja' => $_POST['font_title1_ja'],
        'title2_en' => $_POST['font_title2_en'],
        'title2_ja' => $_POST['font_title2_ja'],
        'body_ja' => $_POST['font_body_ja']
    ];
    
    $themeData = [
        'colors' => $colors,
        'fonts' => $fonts,
        'version' => '1.2.0'
    ];
    
    // 変更前データを取得（監査ログ用）
    $beforeTheme = getTenantThemeById($themeId, $tenantId);
    
    try {
        $pdo->beginTransaction();
        
        if ($saveType === 'publish') {
            // 現在公開中のテーマを下書きに変更
            $stmt = $pdo->prepare("UPDATE tenant_themes SET status = 'draft' WHERE tenant_id = ? AND status = 'published'");
            $stmt->execute([$tenantId]);
            
            // このテーマを公開
            $stmt = $pdo->prepare("
                UPDATE tenant_themes 
                SET theme_name = ?, 
                    theme_data = ?, 
                    is_customized = 1,
                    notes = ?,
                    status = 'published',
                    published_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ");
            
            $stmt->execute([
                $themeName,
                json_encode($themeData),
                $notes,
                $themeId,
                $tenantId
            ]);
            
            // 監査ログ
            insertThemeAuditLog($themeId, $tenantId, 'published', $beforeTheme['theme_data'], $themeData);
            
            $message = 'テーマを公開しました！';
            
        } else {
            // 下書き保存
            $stmt = $pdo->prepare("
                UPDATE tenant_themes 
                SET theme_name = ?, 
                    theme_data = ?, 
                    is_customized = 1,
                    notes = ?
                WHERE id = ? AND tenant_id = ?
            ");
            
            $stmt->execute([
                $themeName,
                json_encode($themeData),
                $notes,
                $themeId,
                $tenantId
            ]);
            
            // 監査ログ
            insertThemeAuditLog($themeId, $tenantId, 'updated', $beforeTheme['theme_data'], $themeData);
            
            $message = '下書きを保存しました';
        }
        
        // 保存したので新規作成フラグをクリア
        $sessionKey = 'new_theme_id_' . $tenantId;
        if (isset($_SESSION[$sessionKey]) && $_SESSION[$sessionKey] == $themeId) {
            unset($_SESSION[$sessionKey]);
        }
        
        $pdo->commit();
        
        // AJAXリクエストの場合はJSONレスポンス
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => $message]);
            exit;
        }
        
        // 通常のリクエストの場合はリダイレクト
        header("Location: edit.php?id={$themeId}&tenant=" . urlencode($tenantSlug) . "&message=" . urlencode($message));
        exit;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = 'テーマ更新に失敗しました: ' . $e->getMessage();
        
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $error]);
            exit;
        }
    }
    
    // テーマデータを再取得
    $theme = getTenantThemeById($themeId, $tenantId);
}

// ヘッダーを読み込む
require_once __DIR__ . '/../includes/header.php';

// フォント一覧
$allFonts = [
    'Kranky' => 'Kranky',
    'Rampart One' => 'Rampart One',
    'Codystar' => 'Codystar',
    'Griffy' => 'Griffy',
    'MonteCarlo' => 'MonteCarlo',
    'Molle' => 'Molle',
    'Inter' => 'Inter',
    'Kaisei Decol' => 'Kaisei Decol',
    'Kaisei Opti' => 'Kaisei Opti',
    'Yuji Boku' => 'Yuji Boku',
    'Hachi Maru Pop' => 'Hachi Maru Pop',
    'Klee One' => 'Klee One',
    'Mochiy Pop One' => 'Mochiy Pop One',
    'Reggae One' => 'Reggae One',
    'Shippori Mincho' => 'Shippori Mincho',
    'M PLUS 1p' => 'M PLUS 1p',
    'Noto Sans JP' => 'Noto Sans JP',
    'BIZ UDPGothic' => 'BIZ UDPGothic'
];

$colors = $theme['theme_data']['colors'];
$fonts = $theme['theme_data']['fonts'];

// 後方互換性
if (isset($fonts['title_en']) && !isset($fonts['title1_en'])) {
    $fonts['title1_en'] = $fonts['title_en'];
    $fonts['title1_ja'] = $fonts['title_ja'] ?? 'Kaisei Decol';
    $fonts['title2_en'] = $fonts['title_en'];
    $fonts['title2_ja'] = $fonts['title_ja'] ?? 'Kaisei Decol';
    $fonts['body_ja'] = $fonts['body'] ?? 'M PLUS 1p';
}

$bgType = $colors['bg_type'] ?? 'solid';
?>

<!-- Google Fonts読み込み -->
<?php echo generateGoogleFontsLink(); ?>

<!-- Pickr カラーピッカー -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@simonwep/pickr/dist/themes/nano.min.css">

<div class="page-header">
    <h1>
        <i class="fas fa-edit"></i> テーマ編集: <?php echo h($theme['theme_name']); ?>
        <span class="theme-status-badge <?php echo $theme['status'] === 'published' ? 'badge-published' : 'badge-draft'; ?>">
            <?php echo $theme['status'] === 'published' ? '公開中' : '下書き'; ?>
        </span>
    </h1>
    <?php if ($theme['base_template_name']): ?>
        <p class="theme-meta-info">ベース: <?php echo h($theme['base_template_name']); ?></p>
    <?php else: ?>
        <p class="theme-meta-info">オリジナルテーマ</p>
    <?php endif; ?>
</div>

<!-- メッセージ -->
<?php if ($message): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?php echo h($message); ?>
    </div>
<?php endif; ?>

<!-- 編集フォーム -->
<form method="POST" id="themeForm">
    
    <!-- 基本情報 -->
    <div class="content-card">
        <h2><i class="fas fa-info-circle"></i> 基本情報</h2>
        
        <div class="form-group">
            <label class="form-label">テーマ名</label>
            <input type="text" name="theme_name" class="form-input" 
                   value="<?php echo h($theme['theme_name']); ?>" required>
        </div>
        
        <div class="form-group">
            <label class="form-label">メモ・説明</label>
            <textarea name="notes" class="form-textarea"><?php echo h($theme['notes'] ?? ''); ?></textarea>
            <div class="help-text">このテーマの用途や特徴をメモできます</div>
        </div>
    </div>
    
    <!-- カラー設定 -->
    <div class="content-card">
        <h2><i class="fas fa-palette"></i> カラー設定</h2>
        
        <?php 
            $colorFields = [
                'primary' => ['label' => 'プライマリカラー', 'help' => 'メインとなるブランドカラー'],
                'primary_light' => ['label' => 'セカンダリカラー', 'help' => 'プライマリの明るいバージョン'],
                'text' => ['label' => 'テキストカラー', 'help' => '本文や見出しの色'],
                'btn_text' => ['label' => 'ボタンテキストカラー', 'help' => 'ボタン内の文字色'],
                'overlay' => ['label' => 'オーバーレイ色', 'help' => '※インデックスページ（認証ページ）トップ画像に重ねる半透明の装飾（rgba形式）']
            ];
        ?>
        
        <?php foreach ($colorFields as $key => $field): ?>
        <div class="form-group">
            <label class="form-label"><?php echo $field['label']; ?></label>
            <div class="color-input-group">
                <div class="pickr-container" id="pickr_<?php echo $key; ?>"></div>
                <input type="text" 
                       name="color_<?php echo $key; ?>" 
                       id="color_<?php echo $key; ?>"
                       class="form-input color-text" 
                       value="<?php echo h($colors[$key]); ?>"
                       required>
            </div>
            <div class="help-text"><?php echo $field['help']; ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- 背景設定 -->
    <div class="content-card">
        <h2><i class="fas fa-fill-drip"></i> 背景設定</h2>
        
        <!-- 背景タイプ選択 -->
        <div class="form-group">
            <label class="form-label">背景タイプ</label>
            <select name="bg_type" id="bg_type" class="form-select">
                <option value="solid" <?php echo $bgType === 'solid' ? 'selected' : ''; ?>>単色</option>
                <option value="gradient" <?php echo $bgType === 'gradient' ? 'selected' : ''; ?>>グラデーション</option>
            </select>
            <div class="help-text">ページ全体の背景を単色かグラデーションから選択</div>
        </div>
        
        <!-- 単色背景 -->
        <div class="form-group" id="solid_bg_group" style="display: <?php echo $bgType === 'solid' ? 'block' : 'none'; ?>;">
            <label class="form-label">背景色</label>
            <div class="color-input-group">
                <div class="pickr-container" id="pickr_bg"></div>
                <input type="text" 
                       name="color_bg" 
                       id="color_bg"
                       class="form-input color-text" 
                       value="<?php echo h($colors['bg'] ?? '#ffffff'); ?>"
                       required>
            </div>
            <div class="help-text">ページ全体の背景色</div>
        </div>
        
        <!-- グラデーション背景 -->
        <div id="gradient_bg_group" style="display: <?php echo $bgType === 'gradient' ? 'block' : 'none'; ?>;">
            <div class="form-group">
                <label class="form-label">グラデーション開始色</label>
                <div class="color-input-group">
                    <div class="pickr-container" id="pickr_bg_gradient_start"></div>
                    <input type="text" 
                           name="bg_gradient_start" 
                           id="bg_gradient_start"
                           class="form-input color-text" 
                           value="<?php echo h($colors['bg_gradient_start'] ?? '#ffffff'); ?>">
                </div>
                <div class="help-text">グラデーションの開始色（左側）</div>
            </div>
            
            <div class="form-group">
                <label class="form-label">グラデーション終了色</label>
                <div class="color-input-group">
                    <div class="pickr-container" id="pickr_bg_gradient_end"></div>
                    <input type="text" 
                           name="bg_gradient_end" 
                           id="bg_gradient_end"
                           class="form-input color-text" 
                           value="<?php echo h($colors['bg_gradient_end'] ?? '#ffd2fe'); ?>">
                </div>
                <div class="help-text">グラデーションの終了色（右側）</div>
            </div>
        </div>
    </div>
    
    <!-- フォント設定 -->
    <div class="content-card">
        <h2><i class="fas fa-font"></i> フォント設定</h2>
        <div class="help-text" style="margin-bottom: 20px; font-size: 13px;">
            ※テキストに日本語と英語が混在する場合、文字化け防止のために英字と日本語を別々に設定することを推奨します。
        </div>
        
        <!-- タイトル1フォント -->
        <div class="font-section">
            <h3>メインタイトル</h3>
            
            <div class="form-group">
                <label class="form-label">英字フォント</label>
                <div class="font-preview">
                    <div class="font-preview-label">Preview</div>
                    <div class="font-preview-text" id="preview_title1_en" style="font-family: '<?php echo $fonts['title1_en'] ?? 'Kranky'; ?>', sans-serif;">
                        ABCDEFG abcdefg 1234567
                    </div>
                </div>
                <select name="font_title1_en" id="select_title1_en" class="form-select">
                    <?php foreach ($allFonts as $value => $label): ?>
                        <option value="<?php echo h($value); ?>" 
                                <?php echo ($fonts['title1_en'] ?? 'Kranky') === $value ? 'selected' : ''; ?>>
                            <?php echo h($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">日本語フォント</label>
                <div class="font-preview">
                    <div class="font-preview-label">プレビュー</div>
                    <div class="font-preview-text" id="preview_title1_ja" style="font-family: '<?php echo $fonts['title1_ja'] ?? 'Kaisei Decol'; ?>', sans-serif;">
                        あいうえお アイウエオ 阿伊雨恵御
                    </div>
                </div>
                <select name="font_title1_ja" id="select_title1_ja" class="form-select">
                    <?php foreach ($allFonts as $value => $label): ?>
                        <option value="<?php echo h($value); ?>" 
                                <?php echo ($fonts['title1_ja'] ?? 'Kaisei Decol') === $value ? 'selected' : ''; ?>>
                            <?php echo h($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <!-- タイトル2フォント -->
        <div class="font-section">
            <h3>サブタイトル</h3>
            
            <div class="form-group">
                <label class="form-label">英字フォント</label>
                <div class="font-preview">
                    <div class="font-preview-label">Preview</div>
                    <div class="font-preview-text" id="preview_title2_en" style="font-family: '<?php echo $fonts['title2_en'] ?? 'Kranky'; ?>', sans-serif;">
                        ABCDEFG abcdefg 1234567
                    </div>
                </div>
                <select name="font_title2_en" id="select_title2_en" class="form-select">
                    <?php foreach ($allFonts as $value => $label): ?>
                        <option value="<?php echo h($value); ?>" 
                                <?php echo ($fonts['title2_en'] ?? 'Kranky') === $value ? 'selected' : ''; ?>>
                            <?php echo h($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">日本語フォント</label>
                <div class="font-preview">
                    <div class="font-preview-label">プレビュー</div>
                    <div class="font-preview-text" id="preview_title2_ja" style="font-family: '<?php echo $fonts['title2_ja'] ?? 'Kaisei Decol'; ?>', sans-serif;">
                        あいうえお アイウエオ 阿伊雨恵御
                    </div>
                </div>
                <select name="font_title2_ja" id="select_title2_ja" class="form-select">
                    <?php foreach ($allFonts as $value => $label): ?>
                        <option value="<?php echo h($value); ?>" 
                                <?php echo ($fonts['title2_ja'] ?? 'Kaisei Decol') === $value ? 'selected' : ''; ?>>
                            <?php echo h($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <!-- 本文フォント -->
        <div class="font-section">
            <h3>本文フォント</h3>
            
            <div class="form-group">
                <div class="font-preview">
                    <div class="font-preview-label">プレビュー</div>
                    <div class="font-preview-text" id="preview_body_ja" style="font-family: '<?php echo $fonts['body_ja'] ?? 'M PLUS 1p'; ?>', sans-serif;">
                        あいう アイウ 阿伊雨 ABC abc 123
                    </div>
                </div>
                <select name="font_body_ja" id="select_body_ja" class="form-select">
                    <?php foreach ($allFonts as $value => $label): ?>
                        <option value="<?php echo h($value); ?>" 
                                <?php echo ($fonts['body_ja'] ?? 'M PLUS 1p') === $value ? 'selected' : ''; ?>>
                            <?php echo h($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="help-text">タイトル以外の全てのテキスト</div>
            </div>
        </div>
    </div>
    
    <!-- 保存ボタン -->
    <div class="action-buttons">
        <button type="submit" name="save_type" value="draft" class="btn btn-primary">
            <i class="fas fa-save"></i> 下書き保存
        </button>
        <button type="submit" name="save_type" value="publish" class="btn btn-success">
            <i class="fas fa-globe"></i> 即時公開
        </button>
        <button type="button" id="previewBtnPC" class="btn btn-preview">
            <i class="fas fa-desktop"></i> PC版プレビュー
        </button>
        <button type="button" id="previewBtnMobile" class="btn btn-preview-mobile">
            <i class="fas fa-mobile-alt"></i> スマホ版プレビュー
        </button>
        <a href="index.php?action=cancel&id=<?php echo $theme['id']; ?>&tenant=<?php echo urlencode($tenantSlug); ?>" 
           class="btn btn-secondary"
           onclick="return confirmCancel();">
            <i class="fas fa-times"></i> キャンセル
        </a>
        <?php if ($theme['base_template_id']): ?>
            <button type="button" onclick="resetToDefault()" class="btn btn-warning">
                <i class="fas fa-undo"></i> デフォルトに戻す
            </button>
        <?php endif; ?>
    </div>
</form>

<style>
.theme-status-badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    margin-left: 10px;
    vertical-align: middle;
}

.badge-published {
    background: var(--success);
    color: white;
}

.badge-draft {
    background: var(--warning);
    color: white;
}

.theme-meta-info {
    font-size: 14px;
    color: var(--text-muted);
    margin-top: 5px;
}

.color-input-group {
    display: flex;
    gap: 10px;
    align-items: center;
}

.pickr-container {
    width: 60px;
    height: 45px;
}

.pickr-container .pcr-button {
    width: 60px !important;
    height: 45px !important;
    border: 2px solid rgba(255, 255, 255, 0.3) !important;
    border-radius: 8px !important;
    cursor: pointer;
    box-shadow: none !important;
}

.pickr-container .pcr-button:after {
    border-radius: 6px !important;
    box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.1) !important;
}

.pickr-container .pcr-button:before {
    background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 2 2"><path fill="white" d="M1,0H2V1H1V0ZM0,1H1V2H0V1Z"/><path fill="gray" d="M0,0H1V1H0V0ZM1,1H2V2H1V1Z"/></svg>');
    background-size: 8px 8px;
    border-radius: 6px !important;
}

.color-text {
    flex: 1;
}

/* Pickrのダークテーマカスタマイズ */
.pcr-app {
    background: #2a2a3e;
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
}

.pcr-app .pcr-interaction input {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.pcr-app .pcr-interaction input:focus {
    border-color: var(--accent);
}

.pcr-app .pcr-type.active {
    color: white;
}

.font-section {
    background: rgba(255, 255, 255, 0.05);
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
}

.font-section h3 {
    margin: 0 0 15px 0;
    font-size: 16px;
    color: white;
}

.font-preview {
    margin-bottom: 10px;
    padding: 15px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    border-left: 3px solid var(--primary);
}

.font-preview-label {
    font-size: 11px;
    color: rgba(255, 255, 255, 0.5);
    margin-bottom: 5px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.font-preview-text {
    font-size: 24px;
    color: white;
    line-height: 1.4;
    transition: font-family 0.3s ease;
}

.action-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    justify-content: flex-end;
    margin-top: 30px;
    padding: 20px 0;
}

.action-buttons .btn {
    padding: 12px 20px;
    border: none;
    border-radius: 25px;
    cursor: pointer;
    text-decoration: none;
    font-size: 13px;
    font-weight: 400;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.action-buttons .btn:hover {
    transform: translateY(-2px);
}

.action-buttons .btn-primary {
    background: var(--accent);
    color: white;
}

.action-buttons .btn-primary:hover {
    box-shadow: 0 8px 20px rgba(39, 163, 235, 0.3);
}

.action-buttons .btn-success {
    background: var(--success);
    color: white;
}

.action-buttons .btn-success:hover {
    box-shadow: 0 8px 20px rgba(76, 175, 80, 0.3);
}

.action-buttons .btn-secondary {
    background: rgba(255, 255, 255, 0.1);
    color: white;
    border: 1px solid rgba(255, 255, 255, 0.2);
}

.action-buttons .btn-secondary:hover {
    background: rgba(255, 255, 255, 0.2);
}

.action-buttons .btn-preview {
    background: #17a2b8;
    color: white;
}

.action-buttons .btn-preview:hover {
    box-shadow: 0 8px 20px rgba(23, 162, 184, 0.3);
}

.action-buttons .btn-preview-mobile {
    background: #6f42c1;
    color: white;
}

.action-buttons .btn-preview-mobile:hover {
    box-shadow: 0 8px 20px rgba(111, 66, 193, 0.3);
}

.action-buttons .btn-warning {
    background: var(--warning);
    color: #333;
}

.action-buttons .btn-warning:hover {
    box-shadow: 0 8px 20px rgba(255, 193, 7, 0.3);
}
</style>

<script src="https://cdn.jsdelivr.net/npm/@simonwep/pickr/dist/pickr.min.js"></script>
<script>
// フォントプレビューのリアルタイム更新
document.addEventListener('DOMContentLoaded', function() {
    const fontSelects = [
        { selectId: 'select_title1_en', previewId: 'preview_title1_en' },
        { selectId: 'select_title1_ja', previewId: 'preview_title1_ja' },
        { selectId: 'select_title2_en', previewId: 'preview_title2_en' },
        { selectId: 'select_title2_ja', previewId: 'preview_title2_ja' },
        { selectId: 'select_body_ja', previewId: 'preview_body_ja' }
    ];
    
    fontSelects.forEach(function(item) {
        const selectElement = document.getElementById(item.selectId);
        const previewElement = document.getElementById(item.previewId);
        
        if (selectElement && previewElement) {
            selectElement.addEventListener('change', function() {
                const selectedFont = this.value;
                previewElement.style.fontFamily = "'" + selectedFont + "', sans-serif";
            });
        }
    });
});

// プレビューボタンの処理
document.addEventListener('DOMContentLoaded', function() {
    const previewBtnPC = document.getElementById('previewBtnPC');
    const previewBtnMobile = document.getElementById('previewBtnMobile');
    const themeForm = document.getElementById('themeForm');
    
    function handlePreview(button, previewUrl, mode) {
        if (!themeForm) return;
        
        const formData = new FormData(themeForm);
        formData.append('save_type', 'draft');
        
        button.disabled = true;
        const originalHTML = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 保存中...';
        
        fetch('edit.php?id=<?php echo $theme['id']; ?>&tenant=<?php echo urlencode($tenantSlug); ?>', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                return fetch('api_preview.php?action=start&preview_id=<?php echo $theme['id']; ?>&tenant=<?php echo urlencode($tenantSlug); ?>', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
            } else {
                throw new Error(data.message || '保存に失敗しました');
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let windowName, windowFeatures;
                if (mode === 'mobile') {
                    windowName = 'themePreviewMobile';
                    windowFeatures = 'width=520,height=1100,scrollbars=yes,resizable=yes';
                } else {
                    windowName = 'themePreviewPC';
                    windowFeatures = 'width=1200,height=800,scrollbars=yes,resizable=yes';
                }
                window.open(previewUrl, windowName, windowFeatures);
            } else {
                alert('プレビュー開始に失敗しました: ' + data.message);
            }
            
            button.disabled = false;
            button.innerHTML = originalHTML;
        })
        .catch(error => {
            console.error('エラー:', error);
            alert('エラーが発生しました: ' + error.message);
            button.disabled = false;
            button.innerHTML = originalHTML;
        });
    }
    
    if (previewBtnPC) {
        previewBtnPC.addEventListener('click', function(e) {
            e.preventDefault();
            handlePreview(this, 'https://<?php echo h($tenantSlug); ?>.pullcass.com/app/front/preview_pc.php', 'pc');
        });
    }
    
    if (previewBtnMobile) {
        previewBtnMobile.addEventListener('click', function(e) {
            e.preventDefault();
            handlePreview(this, 'https://<?php echo h($tenantSlug); ?>.pullcass.com/app/front/preview_mobile.php', 'mobile');
        });
    }
});

// キャンセル確認
function confirmCancel() {
    <?php 
    $sessionKey = 'new_theme_id_' . $tenantId;
    if (isset($_SESSION[$sessionKey]) && $_SESSION[$sessionKey] == $theme['id']): 
    ?>
        return confirm('作成をキャンセルしますか？このテーマは削除されます。');
    <?php else: ?>
        return confirm('編集をキャンセルしますか？保存していない変更は失われます。');
    <?php endif; ?>
}

// 背景タイプの切り替え
document.addEventListener('DOMContentLoaded', function() {
    const bgTypeSelect = document.getElementById('bg_type');
    const solidBgGroup = document.getElementById('solid_bg_group');
    const gradientBgGroup = document.getElementById('gradient_bg_group');
    
    if (bgTypeSelect && solidBgGroup && gradientBgGroup) {
        bgTypeSelect.addEventListener('change', function() {
            if (this.value === 'solid') {
                solidBgGroup.style.display = 'block';
                gradientBgGroup.style.display = 'none';
            } else {
                solidBgGroup.style.display = 'none';
                gradientBgGroup.style.display = 'block';
            }
        });
    }
});

// Pickrカラーピッカーの初期化
const colorFields = ['primary', 'primary_light', 'text', 'btn_text', 'bg', 'bg_gradient_start', 'bg_gradient_end', 'overlay'];
const pickrInstances = {};

colorFields.forEach(fieldName => {
    const inputId = (fieldName === 'bg_gradient_start' || fieldName === 'bg_gradient_end') 
        ? fieldName 
        : 'color_' + fieldName;
    
    const textInput = document.getElementById(inputId);
    const container = document.getElementById('pickr_' + fieldName);
    
    if (!container || !textInput) return;
    
    let initialColor = textInput.value;
    const isOverlay = fieldName === 'overlay';
    
    if (isOverlay && initialColor.includes('rgba')) {
        const match = initialColor.match(/rgba?\((\d+),\s*(\d+),\s*(\d+),?\s*([\d.]+)?\)/);
        if (match) {
            initialColor = `rgba(${match[1]}, ${match[2]}, ${match[3]}, ${match[4] || 1})`;
        } else {
            initialColor = 'rgba(244, 114, 182, 0.2)';
        }
    } else if (!initialColor.startsWith('#')) {
        initialColor = '#000000';
    }
    
    const pickr = Pickr.create({
        el: container,
        theme: 'nano',
        default: initialColor,
        useAsButton: false,
        defaultRepresentation: isOverlay ? 'RGBA' : 'HEX',
        swatches: isOverlay ? [
            'rgba(244, 114, 182, 0.2)',
            'rgba(245, 104, 223, 0.3)',
            'rgba(0, 0, 0, 0.5)',
            'rgba(255, 255, 255, 0.3)'
        ] : [
            '#f568df', '#ffb7d5', '#d4af37', '#4fc3f7',
            '#9c27b0', '#8d6e63', '#f44336', '#ffffff',
            '#000000', '#474747', '#e0e0e0', '#1a1a1a'
        ],
        components: {
            preview: true,
            opacity: isOverlay,
            hue: true,
            interaction: {
                hex: !isOverlay,
                rgba: isOverlay,
                hsla: false,
                hsva: false,
                cmyk: false,
                input: true,
                clear: false,
                save: true
            }
        },
        i18n: {
            'btn:save': '選択'
        }
    });
    
    pickr.on('init', instance => {
        pickr.setColor(initialColor);
    });
    
    pickr.on('save', (color, instance) => {
        if (color) {
            textInput.value = isOverlay ? color.toRGBA().toString() : color.toHEXA().toString();
        }
        pickr.hide();
    });
    
    pickr.on('change', (color, source, instance) => {
        if (color) {
            textInput.value = isOverlay ? color.toRGBA().toString() : color.toHEXA().toString();
        }
    });
    
    textInput.addEventListener('input', function() {
        if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
            try {
                pickr.setColor(this.value);
            } catch (e) {
                console.warn('Invalid color:', this.value);
            }
        }
    });
    
    pickrInstances[fieldName] = pickr;
});

// デフォルトに戻す
function resetToDefault() {
    if (!confirm('テンプレートのデフォルト設定に戻しますか？現在の設定は失われます。')) {
        return;
    }
    
    fetch('index.php?tenant=<?php echo urlencode($tenantSlug); ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            action: 'reset_to_default',
            theme_id: <?php echo $theme['id']; ?>
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('サーバーエラー: ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('エラー: ' + data.message);
        }
    })
    .catch(error => {
        console.error('リセットエラー:', error);
        alert('エラーが発生しました: ' + error.message);
    });
}

// フォーム送信前の確認（即時公開の場合）
document.getElementById('themeForm').addEventListener('submit', function(e) {
    const saveType = e.submitter.value;
    
    if (saveType === 'publish') {
        if (!confirm('このテーマを即時公開しますか？現在公開中のテーマは下書きに戻ります。')) {
            e.preventDefault();
            return false;
        }
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
