<?php
/**
 * テーマ管理システム - メイン管理画面
 * 
 * @package Theme Management System
 * @version 1.0.0
 */

// POST処理を先に行う（header()を使用するため）
require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();
require_once __DIR__ . '/../../../includes/theme_helper.php';

// ページ情報
$pageTitle = 'テーマ管理';

// アクション処理
$action = $_REQUEST['action'] ?? 'list';
$message = '';
$error = '';

// URLパラメータからメッセージを取得
if (isset($_GET['message'])) {
    $message = $_GET['message'];
}
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}

// ===========================
// 未保存テーマのクリーンアップ
// ===========================
$sessionKey = 'new_theme_id_' . $tenantId;
if ($action === 'list' && isset($_SESSION[$sessionKey])) {
    $newThemeId = $_SESSION[$sessionKey];
    try {
        $stmt = $pdo->prepare("SELECT is_customized FROM tenant_themes WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$newThemeId, $tenantId]);
        $theme = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($theme && $theme['is_customized'] == 0) {
            $stmt = $pdo->prepare("DELETE FROM tenant_themes WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$newThemeId, $tenantId]);
        }
    } catch (PDOException $e) {
        error_log("テーマクリーンアップエラー: " . $e->getMessage());
    }
    unset($_SESSION[$sessionKey]);
}

// ===========================
// アクション: テーマ作成（テンプレートベース）
// ===========================
if ($action === 'create_from_template' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $templateId = (int) $_POST['template_id'];

    $template = getThemeTemplateById($templateId);
    if ($template) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO tenant_themes 
                (tenant_id, base_template_id, theme_name, theme_type, status, theme_data, created_by)
                VALUES (?, ?, ?, 'template_based', 'draft', ?, ?)
            ");

            $stmt->execute([
                $tenantId,
                $templateId,
                $template['template_name'],
                json_encode($template['template_data']),
                $_SESSION['tenant_admin_id'] ?? 0
            ]);

            $newThemeId = $pdo->lastInsertId();

            // 監査ログ
            insertThemeAuditLog($newThemeId, $tenantId, 'created', null, $template['template_data']);

            // 新規作成フラグをセッションに保存
            $_SESSION[$sessionKey] = $newThemeId;

            header("Location: edit.php?id={$newThemeId}&tenant=" . urlencode($tenantSlug));
            exit;

        } catch (PDOException $e) {
            $error = 'テーマ作成に失敗しました: ' . $e->getMessage();
        }
    } else {
        $error = 'テンプレートが見つかりません';
    }
}

// ===========================
// アクション: テーマ作成（オリジナル）
// ===========================
if ($action === 'create_original' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $defaultTemplate = getDefaultTemplate();

    try {
        $stmt = $pdo->prepare("
            INSERT INTO tenant_themes 
            (tenant_id, base_template_id, theme_name, theme_type, status, theme_data, created_by)
            VALUES (?, NULL, 'オリジナルテーマ', 'original', 'draft', ?, ?)
        ");

        $stmt->execute([
            $tenantId,
            json_encode($defaultTemplate['theme_data']),
            $_SESSION['tenant_admin_id'] ?? 0
        ]);

        $newThemeId = $pdo->lastInsertId();

        // 監査ログ
        insertThemeAuditLog($newThemeId, $tenantId, 'created', null, $defaultTemplate['theme_data']);

        // 新規作成フラグをセッションに保存
        $_SESSION[$sessionKey] = $newThemeId;

        header("Location: edit.php?id={$newThemeId}&tenant=" . urlencode($tenantSlug));
        exit;

    } catch (PDOException $e) {
        $error = 'テーマ作成に失敗しました: ' . $e->getMessage();
    }
}

// ===========================
// アクション: クイック公開
// ===========================
if ($action === 'quick_publish' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $themeId = (int) $_POST['theme_id'];

    $beforeTheme = getTenantThemeById($themeId, $tenantId);

    if (!$beforeTheme) {
        echo json_encode(['success' => false, 'message' => 'テーマが見つかりません']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 現在公開中のテーマを下書きに変更
        $stmt = $pdo->prepare("UPDATE tenant_themes SET status = 'draft' WHERE tenant_id = ? AND status = 'published'");
        $stmt->execute([$tenantId]);

        // このテーマを公開
        $stmt = $pdo->prepare("
            UPDATE tenant_themes 
            SET status = 'published', published_at = NOW()
            WHERE id = ? AND tenant_id = ?
        ");
        $stmt->execute([$themeId, $tenantId]);

        // 監査ログ
        insertThemeAuditLog($themeId, $tenantId, 'published', null, $beforeTheme['theme_data']);

        $pdo->commit();

        echo json_encode(['success' => true, 'message' => 'テーマを公開しました']);
        exit;

    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// ===========================
// アクション: テーマ削除
// ===========================
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $themeId = (int) $_POST['theme_id'];

    $theme = getTenantThemeById($themeId, $tenantId);
    if (!$theme) {
        echo json_encode(['success' => false, 'message' => 'テーマが見つかりません']);
        exit;
    }

    if ($theme['status'] === 'published') {
        echo json_encode(['success' => false, 'message' => '公開中のテーマは削除できません']);
        exit;
    }

    try {
        // 監査ログ
        insertThemeAuditLog($themeId, $tenantId, 'deleted', $theme['theme_data'], null);

        $stmt = $pdo->prepare("DELETE FROM tenant_themes WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$themeId, $tenantId]);

        echo json_encode(['success' => true, 'message' => 'テーマを削除しました']);
        exit;

    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// ===========================
// アクション: キャンセル
// ===========================
if ($action === 'cancel' && isset($_GET['id'])) {
    $themeId = (int) $_GET['id'];

    if (isset($_SESSION[$sessionKey]) && $_SESSION[$sessionKey] == $themeId) {
        try {
            $stmt = $pdo->prepare("DELETE FROM tenant_themes WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$themeId, $tenantId]);

            unset($_SESSION[$sessionKey]);

            header("Location: index.php?tenant=" . urlencode($tenantSlug) . "&message=" . urlencode('キャンセルしました'));
            exit;
        } catch (PDOException $e) {
            header("Location: index.php?tenant=" . urlencode($tenantSlug) . "&error=" . urlencode('キャンセルに失敗しました'));
            exit;
        }
    }

    header("Location: index.php?tenant=" . urlencode($tenantSlug));
    exit;
}

// ===========================
// アクション: デフォルトに戻す
// ===========================
if ($action === 'reset_to_default' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $themeId = (int) $_POST['theme_id'];

    $theme = getTenantThemeById($themeId, $tenantId);
    if ($theme && $theme['base_template_id']) {
        $template = getThemeTemplateById($theme['base_template_id']);

        if ($template) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE tenant_themes 
                    SET theme_data = ?, is_customized = 0
                    WHERE id = ? AND tenant_id = ?
                ");

                $stmt->execute([
                    json_encode($template['template_data']),
                    $themeId,
                    $tenantId
                ]);

                echo json_encode(['success' => true, 'message' => 'デフォルトに戻しました']);
                exit;

            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }
        }
    }

    echo json_encode(['success' => false, 'message' => 'テンプレートベースのテーマではありません']);
    exit;
}

// ===========================
// 画面表示処理
// ===========================

// テンプレート選択画面
if ($action === 'select_template') {
    $templates = getAllThemeTemplates();
    include __DIR__ . '/template_select.php';
    exit;
}

// テーマ一覧画面（デフォルト）
$themes = getAllTenantThemes($tenantId);
$templates = getAllThemeTemplates();

// ヘッダーを読み込む
require_once __DIR__ . '/../includes/header.php';
?>

<?php
require_once __DIR__ . '/../includes/breadcrumb.php';
$breadcrumbs = [
    ['label' => 'ホーム', 'url' => '/app/manage/?tenant=' . $tenantSlug, 'icon' => 'fas fa-home'],
    ['label' => 'テーマ管理']
];
renderBreadcrumb($breadcrumbs);
?>

<div class="page-header">
    <h1><i class="fas fa-palette"></i> <?php echo h($pageTitle); ?></h1>
    <p>サイトのデザイン（色・フォント）を管理</p>
</div>

<!-- メッセージ -->
<?php if ($message): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?php echo h($message); ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo h($error); ?>
    </div>
<?php endif; ?>

<!-- 新規作成 -->
<div class="create-theme-grid">
    <a href="?action=select_template&tenant=<?php echo urlencode($tenantSlug); ?>" class="create-theme-card">
        <div class="icon">
            <i class="fas fa-palette"></i>
        </div>
        <div class="title">テンプレートから作成</div>
        <div class="desc">プリセットから選んで簡単カスタマイズ</div>
    </a>

    <form method="POST" action="?action=create_original&tenant=<?php echo urlencode($tenantSlug); ?>"
        style="display: contents;">
        <button type="submit" class="create-theme-card">
            <div class="icon">
                <i class="fas fa-plus-circle"></i>
            </div>
            <div class="title">ゼロから新規作成</div>
            <div class="desc">完全オリジナルのテーマを作成</div>
        </button>
    </form>
</div>

<!-- 公開中のテーマ -->
<?php
$publishedThemes = array_filter($themes, function ($t) {
    return $t['status'] === 'published'; });
if (!empty($publishedThemes)):
    ?>
    <div class="content-card">
        <h2><i class="fas fa-globe"></i> 現在公開中のテーマ</h2>
        <div class="theme-list">
            <?php foreach ($publishedThemes as $theme): ?>
                <div class="theme-item published">
                    <div class="theme-header">
                        <div class="theme-name"><?php echo h($theme['theme_name']); ?></div>
                        <span class="theme-badge badge-published">公開中</span>
                    </div>
                    <div class="theme-meta">
                        <?php if ($theme['base_template_name']): ?>
                            ベース: <?php echo h($theme['base_template_name']); ?>
                        <?php else: ?>
                            オリジナルテーマ
                        <?php endif; ?>
                        <?php if ($theme['published_at']): ?>
                            | 公開日: <?php echo date('Y-m-d H:i', strtotime($theme['published_at'])); ?>
                        <?php endif; ?>
                    </div>
                    <div class="theme-actions">
                        <a href="edit.php?id=<?php echo $theme['id']; ?>&tenant=<?php echo urlencode($tenantSlug); ?>"
                            class="btn btn-primary">
                            <i class="fas fa-edit"></i> 編集
                        </a>
                        <button onclick="startPreview(<?php echo $theme['id']; ?>, 'pc')" class="btn btn-secondary"
                            title="PC版プレビュー">
                            <i class="fas fa-desktop"></i> PC版プレビュー
                        </button>
                        <button onclick="startPreview(<?php echo $theme['id']; ?>, 'mobile')" class="btn btn-mobile"
                            title="スマホ版プレビュー">
                            <i class="fas fa-mobile-alt"></i> スマホ版プレビュー
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- 下書きテーマ -->
<?php
$draftThemes = array_filter($themes, function ($t) {
    return $t['status'] === 'draft'; });
if (!empty($draftThemes)):
    ?>
    <div class="content-card">
        <h2><i class="fas fa-file-alt"></i> 下書きテーマ</h2>
        <div class="theme-list">
            <?php foreach ($draftThemes as $theme): ?>
                <div class="theme-item">
                    <div class="theme-header">
                        <div class="theme-name"><?php echo h($theme['theme_name']); ?></div>
                        <span class="theme-badge badge-draft">下書き</span>
                    </div>
                    <div class="theme-meta">
                        <?php if ($theme['base_template_name']): ?>
                            ベース: <?php echo h($theme['base_template_name']); ?> |
                        <?php endif; ?>
                        作成: <?php echo date('Y-m-d', strtotime($theme['created_at'])); ?> |
                        更新: <?php echo date('Y-m-d H:i', strtotime($theme['updated_at'])); ?>
                    </div>
                    <?php if (!empty($theme['notes'])): ?>
                        <div class="theme-memo">
                            <strong>メモ:</strong> <?php echo nl2br(h($theme['notes'])); ?>
                        </div>
                    <?php endif; ?>
                    <div class="theme-actions">
                        <a href="edit.php?id=<?php echo $theme['id']; ?>&tenant=<?php echo urlencode($tenantSlug); ?>"
                            class="btn btn-primary">
                            <i class="fas fa-edit"></i> 編集
                        </a>
                        <button onclick="startPreview(<?php echo $theme['id']; ?>, 'pc')" class="btn btn-secondary"
                            title="PC版プレビュー">
                            <i class="fas fa-desktop"></i> PC版プレビュー
                        </button>
                        <button onclick="startPreview(<?php echo $theme['id']; ?>, 'mobile')" class="btn btn-mobile"
                            title="スマホ版プレビュー">
                            <i class="fas fa-mobile-alt"></i> スマホ版プレビュー
                        </button>
                        <button onclick="publishTheme(<?php echo $theme['id']; ?>)" class="btn btn-success">
                            <i class="fas fa-globe"></i> 公開する
                        </button>
                        <button onclick="deleteTheme(<?php echo $theme['id']; ?>)" class="btn btn-danger">
                            <i class="fas fa-trash"></i> 削除
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<?php if (empty($themes)): ?>
    <div class="content-card">
        <div class="empty-state">
            <i class="fas fa-palette"></i>
            <h3>テーマがありません</h3>
            <p>上のボタンからテーマを作成してください。</p>
        </div>
    </div>
<?php endif; ?>

<style>
    .create-theme-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
        margin-bottom: 30px;
    }

    @media (max-width: 768px) {
        .create-theme-grid {
            grid-template-columns: 1fr;
        }
    }

    .create-theme-card {
        background: var(--card-bg);
        border: 2px dashed rgba(255, 255, 255, 0.2);
        border-radius: 20px;
        padding: 40px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        color: white;
        font: inherit;
    }

    .create-theme-card:hover {
        background: rgba(255, 255, 255, 0.05);
        border-color: rgba(255, 255, 255, 0.4);
        transform: translateY(-5px);
    }

    .create-theme-card .icon {
        font-size: 48px;
        margin-bottom: 15px;
        opacity: 0.9;
    }

    .create-theme-card .icon i {
        color: var(--primary);
    }

    .create-theme-card .title {
        font-size: 20px;
        font-weight: 600;
        margin-bottom: 10px;
    }

    .create-theme-card .desc {
        font-size: 14px;
        opacity: 0.8;
    }

    .theme-list {
        display: grid;
        gap: 20px;
    }

    .theme-item {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid var(--border-color);
        border-radius: 15px;
        padding: 25px;
        transition: all 0.3s ease;
    }

    .theme-item:hover {
        background: rgba(255, 255, 255, 0.05);
        border-color: rgba(255, 255, 255, 0.2);
    }

    .theme-item.published {
        border-color: var(--success);
        background: rgba(76, 175, 80, 0.05);
    }

    .theme-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }

    .theme-name {
        font-size: 18px;
        font-weight: 600;
        color: white;
    }

    .theme-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }

    .badge-published {
        background: var(--success);
        color: white;
    }

    .badge-draft {
        background: var(--warning);
        color: white;
    }

    .theme-meta {
        font-size: 13px;
        color: var(--text-muted);
        margin-bottom: 15px;
    }

    .theme-memo {
        font-size: 13px;
        color: rgba(255, 255, 255, 0.8);
        background: rgba(255, 255, 255, 0.05);
        padding: 10px 15px;
        border-radius: 8px;
        border-left: 3px solid var(--primary);
        margin-bottom: 15px;
        line-height: 1.6;
    }

    .theme-memo strong {
        color: white;
    }

    .theme-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .theme-actions .btn {
        padding: 8px 16px;
        border: none;
        border-radius: 20px;
        cursor: pointer;
        text-decoration: none;
        font-size: 13px;
        font-weight: 400;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
    }

    .theme-actions .btn:hover {
        transform: translateY(-2px);
    }

    .theme-actions .btn-primary {
        background: #27a3eb;
        color: white;
    }

    .theme-actions .btn-primary:hover {
        box-shadow: 0 8px 20px rgba(39, 163, 235, 0.4);
    }

    .theme-actions .btn-secondary {
        background: linear-gradient(45deg, #9C27B0, #E91E63);
        color: white;
    }

    .theme-actions .btn-secondary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(156, 39, 176, 0.4);
    }

    .theme-actions .btn-mobile {
        background: linear-gradient(45deg, #9C27B0, #E91E63);
        color: white;
    }

    .theme-actions .btn-mobile:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(156, 39, 176, 0.4);
    }

    .theme-actions .btn-success {
        background: linear-gradient(45deg, #4CAF50, #45a049);
        color: white;
    }

    .theme-actions .btn-success:hover {
        box-shadow: 0 8px 20px rgba(76, 175, 80, 0.4);
    }

    .theme-actions .btn-danger {
        background: rgba(244, 67, 54, 0.1);
        border: 2px solid rgba(244, 67, 54, 0.4);
        color: #f44336;
    }

    .theme-actions .btn-danger:hover {
        background: rgba(244, 67, 54, 0.2);
        border-color: #f44336;
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--text-muted);
    }

    .empty-state i {
        font-size: 4rem;
        margin-bottom: 20px;
        opacity: 0.3;
    }

    .empty-state h3 {
        font-size: 1.5rem;
        margin-bottom: 10px;
        color: white;
    }
</style>

<script>
    // プレビュー開始
    function startPreview(themeId, mode) {
        fetch('api_preview.php?action=start&preview_id=' + themeId + '&tenant=<?php echo urlencode($tenantSlug); ?>', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let url, windowName, windowFeatures;
                    if (mode === 'mobile') {
                        url = 'https://<?php echo h($tenantSlug); ?>.pullcass.com/app/front/preview_mobile.php';
                        windowName = 'themePreviewMobile';
                        windowFeatures = 'width=550,height=1100,scrollbars=yes,resizable=yes';
                    } else {
                        url = 'https://<?php echo h($tenantSlug); ?>.pullcass.com/app/front/preview_pc.php';
                        windowName = 'themePreviewPC';
                        windowFeatures = 'width=1400,height=900,scrollbars=yes,resizable=yes';
                    }
                    window.open(url, windowName, windowFeatures);
                } else {
                    alert('プレビュー開始に失敗しました: ' + data.message);
                }
            })
            .catch(error => {
                console.error('プレビュー開始エラー:', error);
                alert('エラーが発生しました');
            });
    }

    function publishTheme(themeId) {
        if (!confirm('このテーマを公開しますか？現在公開中のテーマは下書きに戻ります。')) {
            return;
        }

        fetch('index.php?tenant=<?php echo urlencode($tenantSlug); ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'quick_publish', theme_id: themeId })
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
                console.error('公開エラー:', error);
                alert('エラーが発生しました: ' + error.message);
            });
    }

    function deleteTheme(themeId) {
        if (!confirm('このテーマを削除しますか？この操作は取り消せません。')) {
            return;
        }

        fetch('index.php?tenant=<?php echo urlencode($tenantSlug); ?>', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'delete', theme_id: themeId })
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('サーバーエラー: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('エラー: ' + data.message);
                }
            })
            .catch(error => {
                console.error('削除エラー:', error);
                alert('エラーが発生しました: ' + error.message);
            });
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>