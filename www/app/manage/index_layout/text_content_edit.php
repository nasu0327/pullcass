<?php
/**
 * テキストコンテンツ編集画面（インデックスページ用）
 */

require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

$id = $_GET['id'] ?? 0;

// セクション情報を取得
try {
    $stmt = $pdo->prepare("
        SELECT * FROM index_layout_sections 
        WHERE id = ? AND tenant_id = ? AND section_type = 'text_content'
    ");
    $stmt->execute([$id, $tenantId]);
    $section = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$section) {
        die('セクションが見つかりません');
    }

    $config = json_decode($section['config'], true) ?: [];
    $htmlContent = $config['html_content'] ?? '';

} catch (PDOException $e) {
    die('データベースエラー: ' . $e->getMessage());
}

$pageTitle = 'テキストコンテンツ編集 - ' . h($section['admin_title']);
?>

<?php include __DIR__ . '/../includes/header.php'; ?>

<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
<script src="/assets/tinymce/tinymce.min.js"></script>
<script src="/assets/js/tinymce-config.js?v=<?php echo time(); ?>"></script>

<style>
    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 40px 20px;
    }

    .form-container {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 15px;
        padding: 30px;
        margin-bottom: 30px;
    }

    .form-container h2 {
        margin: 0 0 25px 0;
        font-size: 1.5rem;
        color: #27a3eb;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .form-group {
        margin-bottom: 25px;
    }

    .form-group label {
        display: block;
        margin-bottom: 10px;
        color: rgba(255, 255, 255, 0.9);
        font-weight: 600;
    }

    .form-group input[type="text"] {
        width: 100%;
        padding: 14px 18px;
        background: rgba(255, 255, 255, 0.08);
        border: 2px solid rgba(255, 255, 255, 0.15);
        border-radius: 12px;
        color: #fff;
        font-size: 1rem;
        box-sizing: border-box;
    }

    .form-group input[type="text"]:focus {
        outline: none;
        border-color: #27a3eb;
    }

    .form-group small {
        display: block;
        margin-top: 8px;
        color: rgba(255, 255, 255, 0.6);
    }

    .editor-wrapper {
        border-radius: 12px;
        overflow: hidden;
        border: 2px solid rgba(39, 163, 235, 0.3);
    }

    .buttons {
        display: flex;
        gap: 15px;
        margin-top: 30px;
    }

    .buttons .btn {
        flex: 1;
        padding: 14px 28px;
        border-radius: 12px;
    }

    .required {
        color: #f44336;
        margin-left: 5px;
    }
</style>

<div class="container">
    <?php
    require_once __DIR__ . '/../includes/breadcrumb.php';
    $breadcrumbs = [
        ['label' => 'ホーム', 'url' => '/app/manage/?tenant=' . $tenantSlug, 'icon' => 'fas fa-home'],
        ['label' => '認証ページ編集', 'url' => '/app/manage/index_layout/?tenant=' . $tenantSlug],
        ['label' => h($section['admin_title']) . ' 編集']
    ];
    renderBreadcrumb($breadcrumbs);
    ?>
    <div class="header">
        <h1>テキストコンテンツ編集</h1>
        <p>ソースコードにも対応してます。</p>
    </div>

    <div class="form-container">
        <h2>
            <span class="material-icons">article</span>
            コンテンツ設定
        </h2>

        <div class="form-group">
            <label>管理名<span class="required">*</span></label>
            <input type="text" id="adminTitle" value="<?php echo h($section['admin_title']); ?>"
                placeholder="例: 注意事項セクション" required>
            <small>管理画面で表示される名前です</small>
        </div>

        <div class="form-group">
            <label>メインタイトル（任意）</label>
            <input type="text" id="titleEn" value="<?php echo h($section['title_en']); ?>" placeholder="例: NOTICE">
            <small>フロントエンドで表示されるメインタイトルです</small>
        </div>

        <div class="form-group">
            <label>サブタイトル（任意）</label>
            <input type="text" id="titleJa" value="<?php echo h($section['title_ja']); ?>" placeholder="例: 注意事項">
            <small>フロントエンドで表示されるサブタイトルです</small>
        </div>

        <div class="form-group">
            <label>コンテンツ<span class="required">*</span></label>
            <div class="editor-wrapper">
                <textarea id="htmlContent"><?php echo h($htmlContent); ?></textarea>
            </div>
        </div>

        <div class="buttons">
            <button type="button" class="btn btn-secondary"
                onclick="window.location.href='index.php?tenant=<?php echo urlencode($tenantSlug); ?>'">
                <span class="material-icons">arrow_back</span>
                戻る
            </button>
            <button type="button" class="btn btn-primary" onclick="saveContent()">
                <span class="material-icons">save</span>
                保存する
            </button>
        </div>
    </div>
</div>

<script>
    const TENANT_SLUG = '<?php echo addslashes($tenantSlug); ?>';

    // TinyMCE初期化
    const topLayoutConfig = addImageUploadConfig(TinyMCEConfig.full, 'api/upload_image.php', '/');

    tinymce.init({
        selector: '#htmlContent',
        height: 500,
        ...topLayoutConfig,
        content_style: 'body { font-family: "M PLUS 1p", sans-serif; font-size: 16px; line-height: 1.4; margin: 0; padding: 20px; box-sizing: border-box; }',
        toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline | forecolor backcolor | alignleft aligncenter alignright | bullist numlist | link image | code'
    });

    function saveContent() {
        const adminTitle = document.getElementById('adminTitle').value.trim();

        if (!adminTitle) {
            alert('管理名は必須です');
            return;
        }

        const data = {
            id: <?php echo $id; ?>,
            admin_title: adminTitle,
            title_en: document.getElementById('titleEn').value,
            title_ja: document.getElementById('titleJa').value,
            html_content: tinymce.get('htmlContent').getContent()
        };

        fetch('save_text_content.php?tenant=' + TENANT_SLUG, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert('保存しました');
                } else {
                    alert('保存に失敗しました: ' + (result.message || '不明なエラー'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('保存に失敗しました');
            });
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>