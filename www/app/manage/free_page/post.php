<?php
/**
 * フリーページ管理 - 新規/編集ページ
 */

// 認証チェック（$tenant, $tenantIdが自動設定される）
require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

require_once __DIR__ . '/../../../includes/free_page_helpers.php';

$pdo = getPlatformDb();

$page = null;
$isEdit = false;
$errors = [];
$success = false;

// 編集モードの場合
if (isset($_GET['id']) && $_GET['id']) {
    $page = getFreePage($pdo, (int) $_GET['id'], $tenantId);
    if (!$page) {
        header('Location: index?tenant=' . $tenantSlug);
        exit;
    }
    $isEdit = true;
}

// フォーム送信処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $mainTitle = trim($_POST['main_title'] ?? '');
    $subTitle = trim($_POST['sub_title'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $content = $_POST['content'] ?? '';
    $metaDescription = trim($_POST['meta_description'] ?? '');
    $featuredImage = trim($_POST['featured_image'] ?? '');
    $status = $_POST['status'] ?? 'draft';

    // バリデーション
    if (empty($title)) {
        $errors[] = '管理用タイトルは必須です';
    }

    // スラッグ検証
    $slugValidation = validateSlug($slug);
    if (!$slugValidation['valid']) {
        $errors[] = $slugValidation['error'];
    } elseif (isSlugReserved($slug)) {
        $errors[] = 'このスラッグはシステムで予約されているため使用できません';
    } elseif (!isSlugAvailable($pdo, $slug, $tenantId, $isEdit ? $page['id'] : null)) {
        $errors[] = 'このスラッグは既に使用されています';
    }

    if (empty($errors)) {
        $data = [
            'tenant_id' => $tenantId,
            'title' => $title,
            'main_title' => $mainTitle,
            'sub_title' => $subTitle,
            'slug' => $slug,
            'content' => $content,
            'excerpt' => '',
            'meta_description' => $metaDescription,
            'featured_image' => $featuredImage,
            'status' => $status
        ];

        try {
            if ($isEdit) {
                updateFreePage($pdo, $page['id'], $data, $tenantId);
                $success = true;
                $page = getFreePage($pdo, $page['id'], $tenantId);
            } else {
                $newId = createFreePage($pdo, $data);
                header('Location: post?tenant=' . $tenantSlug . '&id=' . $newId . '&saved=1');
                exit;
            }
        } catch (PDOException $e) {
            $errors[] = '保存に失敗しました: ' . $e->getMessage();
        }
    }
}

// 保存成功メッセージ
if (isset($_GET['saved'])) {
    $success = true;
}

$shopName = $tenant['name'];
$pageTitle = $isEdit ? 'ページ編集' : '新規ページ作成';

// 共通ヘッダーを読み込む
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .container {
        max-width: 1000px;
        margin: 0 auto;
        padding: 40px 20px;
    }

    .header {
        margin-bottom: 30px;
    }

    .header h1 {
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--text-primary);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .form-group {
        margin-bottom: 25px;
    }

    .form-group label {
        display: block;
        color: var(--text-primary);
        font-weight: 600;
        margin-bottom: 8px;
        font-size: 0.95rem;
    }

    .form-group .required {
        color: var(--danger);
        margin-left: 5px;
    }

    .form-group input[type="text"],
    .form-group textarea,
    .form-group select {
        width: 100%;
        padding: 12px 15px;
        border-radius: 8px;
        border: 1px solid var(--border-color);
        background: var(--bg-input);
        color: var(--text-primary);
        font-size: 1rem;
        transition: all var(--transition-base);
    }

    .form-group input[type="text"]:focus,
    .form-group textarea:focus,
    .form-group select:focus {
        outline: none;
        border-color: var(--border-color-focus);
        background: var(--bg-input-focus);
        box-shadow: 0 0 0 3px var(--primary-bg);
    }

    .form-group input[type="text"]::placeholder,
    .form-group textarea::placeholder {
        color: var(--text-placeholder);
    }

    .form-group small {
        display: block;
        margin-top: 8px;
        color: var(--text-muted);
        font-size: 0.85rem;
    }

    .form-group .slug-preview {
        margin-top: 8px;
        padding: 10px 15px;
        background: var(--bg-code);
        border-radius: 8px;
        font-family: monospace;
        font-size: 0.9rem;
        color: var(--text-secondary);
    }

    .form-group .slug-preview span {
        color: var(--primary);
    }

    /* 画像アップロード */
    .image-upload-area {
        border: 2px dashed var(--border-color);
        border-radius: 12px;
        padding: 30px;
        text-align: center;
        cursor: pointer;
        transition: all var(--transition-base);
        background: var(--bg-upload);
    }

    .image-upload-area:hover {
        border-color: var(--primary);
        background: var(--bg-upload-hover);
    }

    .image-upload-area.has-image {
        padding: 10px;
    }

    .image-upload-area .upload-icon {
        font-size: 3rem;
        color: var(--text-muted);
        margin-bottom: 15px;
    }

    .image-upload-area .upload-text {
        color: var(--text-secondary);
        font-size: 0.95rem;
    }

    .image-upload-area .preview-image {
        max-width: 100%;
        max-height: 200px;
        border-radius: 8px;
    }

    .image-upload-area .remove-image {
        margin-top: 10px;
        padding: 8px 16px;
        background: var(--danger-bg);
        color: var(--danger);
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 0.85rem;
    }

    .image-upload-area .remove-image:hover {
        background: var(--danger-border);
    }

    /* エディタ */
    .editor-wrapper {
        border-radius: 12px;
        overflow: hidden;
    }

    /* ボタン */
    .form-actions {
        display: flex;
        gap: 15px;
        justify-content: flex-end;
        flex-wrap: wrap;
    }

    /* アラート */
    .alert {
        padding: 15px 20px;
        border-radius: 12px;
        margin-bottom: 25px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .alert-success {
        background: var(--success-bg);
        border: 1px solid var(--success-border);
        color: var(--success-text);
    }

    .alert-error {
        background: var(--danger-bg);
        border: 1px solid var(--danger-border);
        color: var(--danger-text);
    }

    @media (max-width: 768px) {
        .form-actions {
            flex-direction: column;
        }

        .form-actions .btn {
            width: 100%;
            justify-content: center;
        }
    }
</style>

<div class="container">
    <?php
    require_once __DIR__ . '/../includes/breadcrumb.php';
    $breadcrumbs = [
        ['label' => 'ダッシュボード', 'url' => '/app/manage/?tenant=' . $tenantSlug, 'icon' => 'fas fa-chart-pie'],
        ['label' => 'フリーページ', 'url' => 'index?tenant=' . $tenantSlug, 'icon' => 'fas fa-file-alt'],
        ['label' => $isEdit ? '編集' : '新規作成', 'url' => '', 'icon' => $isEdit ? 'fas fa-edit' : 'fas fa-plus']
    ];
    renderBreadcrumb($breadcrumbs);
    ?>

    <div class="header">
        <h1>
            <i class="fas fa-<?php echo $isEdit ? 'edit' : 'plus'; ?>"></i>
            <?php echo $isEdit ? 'ページ編集' : '新規ページ作成'; ?>
        </h1>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            保存しました
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <div>
                <?php foreach ($errors as $error): ?>
                    <div><?php echo h($error); ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <form method="post" id="pageForm">
        <!-- 基本情報 -->
        <div class="form-container">
            <h2>
                <span class="material-icons">article</span>
                基本情報
            </h2>

            <div class="form-group">
                <label>
                    管理用タイトル<span class="required">*</span>
                </label>
                <input type="text" name="title" id="title"
                    value="<?php echo h($page['title'] ?? ($_POST['title'] ?? '')); ?>" placeholder="例: お問い合わせページ"
                    required>
                <small>管理画面での識別用タイトルです</small>
            </div>

            <div class="form-group">
                <label>
                    URLスラッグ<span class="required">*</span>
                </label>
                <input type="text" name="slug" id="slug"
                    value="<?php echo h($page['slug'] ?? ($_POST['slug'] ?? '')); ?>" placeholder="例: contact" required
                    pattern="[a-zA-Z0-9\-_]+" title="英数字、ハイフン、アンダースコアのみ使用できます">
                <div class="slug-preview">
                    URL: <span id="slug-preview-url">
                        <?php echo h($tenant['domain'] ?? $tenant['code'] . '.pullcass.com'); ?>/<?php echo h($page['slug'] ?? ''); ?>
                    </span>
                </div>
                <small>URLに使用される文字列です（英数字、ハイフン、アンダースコアのみ）</small>
            </div>

            <div class="form-group">
                <label>ステータス</label>
                <select name="status" id="status">
                    <option value="draft" <?php echo ($page['status'] ?? $_POST['status'] ?? 'draft') === 'draft' ? 'selected' : ''; ?>>
                        下書き
                    </option>
                    <option value="published" <?php echo ($page['status'] ?? $_POST['status'] ?? '') === 'published' ? 'selected' : ''; ?>>
                        公開
                    </option>
                </select>
            </div>
        </div>

        <!-- ページ表示設定 -->
        <div class="form-container">
            <h2>
                <span class="material-icons">visibility</span>
                ページ表示設定
            </h2>

            <div class="form-group">
                <label>メインタイトル</label>
                <input type="text" name="main_title" id="main_title"
                    value="<?php echo h($page['main_title'] ?? ($_POST['main_title'] ?? '')); ?>"
                    placeholder="例: お問い合わせ">
                <small>ページ上部に大きく表示されるタイトルです（空の場合は管理用タイトルを使用）</small>
            </div>

            <div class="form-group">
                <label>サブタイトル</label>
                <input type="text" name="sub_title" id="sub_title"
                    value="<?php echo h($page['sub_title'] ?? ($_POST['sub_title'] ?? '')); ?>"
                    placeholder="例: Contact">
                <small>メインタイトルの上または下に表示される補助テキストです</small>
            </div>

            <div class="form-group">
                <label>アイキャッチ画像</label>
                <input type="hidden" name="featured_image" id="featured_image"
                    value="<?php echo h($page['featured_image'] ?? ($_POST['featured_image'] ?? '')); ?>">
                <div class="image-upload-area <?php echo !empty($page['featured_image'] ?? $_POST['featured_image'] ?? '') ? 'has-image' : ''; ?>"
                    id="image-upload-area">
                    <?php if (!empty($page['featured_image'] ?? $_POST['featured_image'] ?? '')): ?>
                        <img src="<?php echo h($page['featured_image'] ?? $_POST['featured_image']); ?>"
                            class="preview-image" id="preview-image">
                        <br>
                        <button type="button" class="remove-image" onclick="removeImage()">
                            <i class="fas fa-trash"></i> 画像を削除
                        </button>
                    <?php else: ?>
                        <div class="upload-icon">
                            <i class="fas fa-cloud-upload-alt"></i>
                        </div>
                        <div class="upload-text">
                            クリックまたはドラッグ&ドロップで画像をアップロード
                        </div>
                    <?php endif; ?>
                </div>
                <input type="file" id="image-input" accept="image/*" style="display: none;">
                <small>OGP画像やページヘッダーに使用されます（推奨: 1200x630px）</small>
            </div>
        </div>

        <!-- コンテンツ -->
        <div class="form-container">
            <h2>
                <span class="material-icons">edit_note</span>
                コンテンツ
            </h2>

            <div class="form-group">
                <label>本文</label>
                <div class="editor-wrapper">
                    <textarea name="content"
                        id="content"><?php echo h($page['content'] ?? ($_POST['content'] ?? '')); ?></textarea>
                </div>
            </div>
        </div>

        <!-- SEO設定 -->
        <div class="form-container">
            <h2>
                <span class="material-icons">search</span>
                SEO設定
            </h2>

            <div class="form-group">
                <label>メタデスクリプション</label>
                <textarea name="meta_description" id="meta_description" rows="3"
                    placeholder="ページの説明文を入力してください（検索結果に表示されます）"><?php echo h($page['meta_description'] ?? ($_POST['meta_description'] ?? '')); ?></textarea>
                <small>検索結果に表示される説明文です（120文字程度推奨）</small>
            </div>
        </div>

        <!-- アクション -->
        <div class="form-actions">
            <a href="index?tenant=<?php echo h($tenantSlug); ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i>
                戻る
            </a>
            <?php if ($isEdit): ?>
                <button type="button" onclick="openPreview('pc')" class="btn btn-secondary">
                    <i class="fas fa-desktop"></i>
                    PC版プレビュー
                </button>
                <button type="button" onclick="openPreview('mobile')" class="btn btn-secondary">
                    <i class="fas fa-mobile-alt"></i>
                    スマホ版プレビュー
                </button>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i>
                保存
            </button>
        </div>
    </form>
</div>

<!-- TinyMCE -->
<script src="/assets/tinymce/tinymce.min.js"></script>
<script src="/assets/js/tinymce-config.js?v=<?php echo time(); ?>"></script>

<script>
    // TinyMCE初期化（フル機能版・画像アップロード対応）
    // ※トップページ編集（text_content_edit.php）と完全に同じ設定
    const uploadConfig = addImageUploadConfig(
        TinyMCEConfig.full,
        '/app/manage/free_page/api/upload_image?tenant=<?php echo h($tenantSlug); ?>',
        '/'
    );

    tinymce.init({
        selector: '#content',
        height: 500,
        ...uploadConfig,
        content_style: 'body { font-family: "M PLUS 1p", sans-serif; font-size: 16px; line-height: 1.4; margin: 0; padding: 20px; box-sizing: border-box; } .page-background { padding: 20px; margin: -20px; box-sizing: border-box; } p { margin: 0 0 0.5em 0; padding: 0; font-size: 16px; } h1, h2, h3, h4, h5, h6 { margin: 0 0 0.5em 0; padding: 0; font-weight: bold; display: block; } h1 { font-size: 24px; } h2 { font-size: 20px; } h3 { font-size: 18px; } h4 { font-size: 16px; } h5 { font-size: 14px; } h6 { font-size: 14px; } ul, ol { margin: 0 0 0.5em 0; padding: 0 0 0 1.5em; } li { margin: 0; padding: 0; font-size: 16px; } img { max-width: 100%; height: auto; } img.img-align-left { float: left !important; margin: 0 15px 10px 0 !important; } img.img-align-center { display: block !important; margin: 10px auto !important; float: none !important; } img.img-align-right { float: right !important; margin: 0 0 10px 15px !important; } img[style*="float: left"]:not(.img-align-center):not(.img-align-right) { float: left; margin: 0 15px 10px 0; } img[style*="float: right"]:not(.img-align-center):not(.img-align-left) { float: right; margin: 0 0 10px 15px; }',
        line_height_formats: '0.5 0.6 0.7 0.8 0.9 1 1.2 1.4 1.6 1.8 2 2.5 3',
        image_caption: true,
        image_advtab: true,
        quickbars_image_toolbar: 'alignleft aligncenter alignright | rotateleft rotateright | imageoptions',
        image_class_list: [
            { title: 'なし', value: '' },
            { title: '左揃え', value: 'img-align-left' },
            { title: '中央揃え', value: 'img-align-center' },
            { title: '右揃え', value: 'img-align-right' }
        ],
        menubar: 'file edit view insert format tools table',
        toolbar_mode: 'wrap',
        menu: {
            file: { title: 'ファイル', items: 'print' },
            edit: { title: '編集', items: 'undo redo | cut copy paste pastetext | selectall | searchreplace' },
            view: { title: '表示', items: 'visualblocks visualchars' },
            insert: { title: '挿入', items: 'image link media | table hr | charmap emoticons' },
            format: { title: '書式', items: 'bold italic underline strikethrough | superscript subscript | styles blocks fontfamily fontsize lineheight | forecolor backcolor | removeformat' },
            tools: { title: 'ツール', items: 'code' },
            table: { title: '表', items: 'inserttable | cell row column | tableprops deletetable' }
        },
        toolbar: 'undo redo | blocks fontfamily fontsize lineheight | ' +
            'bold italic underline strikethrough | forecolor backcolor backgroundcolor gradientcolor | ' +
            'alignleft aligncenter alignright alignjustify | ' +
            'bullist numlist outdent indent | ' +
            'link image media table | ' +
            'charmap emoticons | ' +
            'searchreplace visualblocks code',
        setup: function (editor) {
            editor.on('init', function () {
                // エディタのbodyに確実に20pxのpaddingを設定
                const editorBody = editor.getBody();
                editorBody.style.padding = '20px';
                editorBody.style.boxSizing = 'border-box';

                setTimeout(function () {
                    addBackdropClickListener();
                }, 500);
            });

            // 画像配置コマンドをカスタマイズ
            editor.on('ExecCommand', function (e) {
                const cmd = e.command;
                if (cmd === 'JustifyLeft' || cmd === 'JustifyCenter' || cmd === 'JustifyRight') {
                    const selectedNode = editor.selection.getNode();
                    if (selectedNode.nodeName === 'IMG') {
                        // 既存のスタイルとクラスをクリア
                        selectedNode.style.float = '';
                        selectedNode.style.display = '';
                        selectedNode.style.marginLeft = '';
                        selectedNode.style.marginRight = '';
                        selectedNode.classList.remove('img-align-left', 'img-align-center', 'img-align-right');

                        // 新しい配置を適用
                        if (cmd === 'JustifyLeft') {
                            selectedNode.classList.add('img-align-left');
                        } else if (cmd === 'JustifyCenter') {
                            selectedNode.classList.add('img-align-center');
                        } else if (cmd === 'JustifyRight') {
                            selectedNode.classList.add('img-align-right');
                        }

                        // pタグ内にある場合、画像を独立させる
                        const parent = selectedNode.parentNode;
                        if (parent && parent.nodeName === 'P') {
                            const grandParent = parent.parentNode;
                            if (grandParent) {
                                // 画像の前後のテキストを分割
                                const beforeText = [];
                                const afterText = [];
                                let foundImg = false;

                                Array.from(parent.childNodes).forEach(child => {
                                    if (child === selectedNode) {
                                        foundImg = true;
                                    } else if (!foundImg) {
                                        beforeText.push(child.cloneNode(true));
                                    } else {
                                        afterText.push(child.cloneNode(true));
                                    }
                                });

                                // 前のテキストがある場合、新しいpタグを作成
                                if (beforeText.length > 0 && beforeText.some(n => n.textContent.trim())) {
                                    const beforeP = editor.dom.create('p');
                                    beforeText.forEach(n => beforeP.appendChild(n));
                                    grandParent.insertBefore(beforeP, parent);
                                }

                                // 画像を独立した要素として挿入
                                grandParent.insertBefore(selectedNode, parent);

                                // 後のテキストがある場合、元のpタグを再利用
                                if (afterText.length > 0 && afterText.some(n => n.textContent.trim())) {
                                    parent.innerHTML = '';
                                    afterText.forEach(n => parent.appendChild(n));
                                } else {
                                    grandParent.removeChild(parent);
                                }
                            }
                        }
                    }
                }
            });

            editor.on('init', function () {
                // グラデーション背景色選択用のボタンを追加
                editor.ui.registry.addButton('gradientcolor', {
                    text: 'グラデーション',
                    tooltip: 'グラデーション背景を設定',
                    onAction: function () {
                        editor.windowManager.open({
                            title: 'グラデーション背景を選択',
                            body: {
                                type: 'panel',
                                items: [{
                                    type: 'selectbox',
                                    name: 'gradient',
                                    label: 'グラデーション',
                                    items: [
                                        { value: 'linear-gradient(45deg, #ff9a9e, #fecfef)', text: 'ピンク系' },
                                        { value: 'linear-gradient(45deg, #a8edea, #fed6e3)', text: 'ミント系' },
                                        { value: 'linear-gradient(45deg, #ffecd2, #fcb69f)', text: 'オレンジ系' },
                                        { value: 'linear-gradient(45deg, #ff9a9e, #fad0c4)', text: 'サンセット' },
                                        { value: 'linear-gradient(45deg, #a8edea, #fed6e3)', text: 'スプリング' },
                                        { value: 'linear-gradient(45deg, #d299c2, #fef9d7)', text: 'パステル' },
                                        { value: 'linear-gradient(45deg, #89f7fe, #66a6ff)', text: 'オーシャン' },
                                        { value: 'linear-gradient(45deg, #f093fb, #f5576c)', text: 'マゼンタ' },
                                        { value: 'linear-gradient(45deg, #4facfe, #00f2fe)', text: 'スカイブルー' },
                                        { value: 'linear-gradient(45deg, #43e97b, #38f9d7)', text: 'エメラルド' },
                                        { value: 'linear-gradient(45deg, #fa709a, #fee140)', text: 'サンライズ' },
                                        { value: 'linear-gradient(45deg, #667eea, #764ba2)', text: 'パープル' }
                                    ]
                                }]
                            },
                            buttons: [
                                {
                                    type: 'submit',
                                    text: '適用'
                                },
                                {
                                    type: 'cancel',
                                    text: 'キャンセル'
                                }
                            ],
                            onSubmit: function (api) {
                                var gradient = api.getData().gradient;
                                // エディタの背景を変更
                                const editorBody = editor.getBody();
                                editorBody.style.background = gradient;
                                // paddingを再設定（確実に維持）
                                editorBody.style.padding = '20px';
                                editorBody.style.boxSizing = 'border-box';

                                // コンテンツ全体を背景付きdivで包む
                                var content = editor.getContent();
                                // 既存の背景divを削除
                                content = content.replace(/<div class="page-background"[^>]*>([\s\S]*)<\/div>$/i, '$1');
                                // 新しい背景divで包む
                                var wrappedContent = '<div class="page-background" style="background: ' + gradient + '; min-height: 300px; padding: 20px; margin: -20px;">' + content + '</div>';
                                editor.setContent(wrappedContent);

                                api.close();
                            }
                        });
                    }
                });

                // 背景色選択用のメニューボタンを追加
                editor.ui.registry.addMenuButton('backgroundcolor', {
                    text: '背景色',
                    tooltip: 'エディタの背景色を変更',
                    fetch: function (callback) {
                        var items = [
                            {
                                type: 'menuitem',
                                text: '基本色',
                                onAction: function () {
                                    editor.windowManager.open({
                                        title: '背景色を選択',
                                        body: {
                                            type: 'panel',
                                            items: [{
                                                type: 'colorinput',
                                                name: 'color',
                                                label: '色を選択'
                                            }]
                                        },
                                        buttons: [
                                            {
                                                type: 'submit',
                                                text: '適用'
                                            }
                                        ],
                                        onSubmit: function (api) {
                                            var color = api.getData().color;
                                            // エディタの背景を変更
                                            const editorBody = editor.getBody();
                                            editorBody.style.backgroundColor = color;
                                            // paddingを再設定（確実に維持）
                                            editorBody.style.padding = '20px';
                                            editorBody.style.boxSizing = 'border-box';

                                            // コンテンツ全体を背景付きdivで包む
                                            var content = editor.getContent();
                                            // 既存の背景divを削除
                                            content = content.replace(/<div class="page-background"[^>]*>([\s\S]*)<\/div>$/i, '$1');
                                            // 新しい背景divで包む
                                            var wrappedContent = '<div class="page-background" style="background-color: ' + color + '; min-height: 300px; padding: 20px; margin: -20px;">' + content + '</div>';
                                            editor.setContent(wrappedContent);

                                            api.close();
                                        }
                                    });
                                }
                            },
                            {
                                type: 'menuitem',
                                text: 'カラーコード入力',
                                onAction: function () {
                                    editor.windowManager.open({
                                        title: 'カラーコードを入力',
                                        body: {
                                            type: 'panel',
                                            items: [{
                                                type: 'input',
                                                name: 'colorcode',
                                                label: 'カラーコード（例: #ffffff）'
                                            }]
                                        },
                                        buttons: [
                                            {
                                                type: 'submit',
                                                text: '適用'
                                            }
                                        ],
                                        onSubmit: function (api) {
                                            var colorcode = api.getData().colorcode;
                                            // エディタの背景を変更
                                            const editorBody = editor.getBody();
                                            editorBody.style.backgroundColor = colorcode;
                                            // paddingを再設定（確実に維持）
                                            editorBody.style.padding = '20px';
                                            editorBody.style.boxSizing = 'border-box';

                                            // コンテンツ全体を背景付きdivで包む
                                            var content = editor.getContent();
                                            // 既存の背景divを削除
                                            content = content.replace(/<div class="page-background"[^>]*>([\s\S]*)<\/div>$/i, '$1');
                                            // 新しい背景divで包む
                                            var wrappedContent = '<div class="page-background" style="background-color: ' + colorcode + '; min-height: 300px; padding: 20px; margin: -20px;">' + content + '</div>';
                                            editor.setContent(wrappedContent);

                                            api.close();
                                        }
                                    });
                                }
                            }
                        ];
                        callback(items);
                    }
                });
            });
        }
    });

    // プレビューダイアログの背景クリックで閉じる機能（トップページ編集と同じ）
    function addBackdropClickListener() {
        const observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                if (mutation.addedNodes && mutation.addedNodes.length > 0) {
                    mutation.addedNodes.forEach(function (node) {
                        if (node.classList && (node.classList.contains('tox-dialog-wrap') || node.classList.contains('tox-dialog__backdrop'))) {
                            node.addEventListener('click', function (e) {
                                if (e.target === node || e.target.classList.contains('tox-dialog-wrap__backdrop') || e.target.classList.contains('tox-dialog__backdrop')) {
                                    const closeBtn = document.querySelector('.tox-dialog__footer .tox-button[data-mce-name="Close"]');
                                    if (closeBtn) {
                                        closeBtn.click();
                                    } else {
                                        const headerCloseBtn = document.querySelector('.tox-dialog__header .tox-button[data-mce-name="close"]');
                                        if (headerCloseBtn) {
                                            headerCloseBtn.click();
                                        }
                                    }
                                }
                            });
                        }
                    });
                }
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    // スラッグプレビュー更新
    const slugInput = document.getElementById('slug');
    const slugPreview = document.getElementById('slug-preview-url');
    const domain = '<?php echo h($tenant['domain'] ?? $tenant['code'] . '.pullcass.com'); ?>';

    slugInput.addEventListener('input', function () {
        slugPreview.textContent = domain + '/' + this.value;
    });

    // タイトルからスラッグ自動生成（新規作成時のみ）
    <?php if (!$isEdit): ?>
        const titleInput = document.getElementById('title');
        let slugManuallyEdited = false;

        slugInput.addEventListener('input', function () {
            slugManuallyEdited = true;
        });

        titleInput.addEventListener('input', function () {
            if (!slugManuallyEdited && !slugInput.value) {
                const title = this.value;
                const slug = title.toLowerCase()
                    .replace(/[^\w\s-]/g, '')
                    .replace(/[\s_-]+/g, '-')
                    .replace(/^-+|-+$/g, '');
                if (slug) {
                    slugInput.value = slug;
                    slugPreview.textContent = domain + '/' + slug;
                }
            }
        });
    <?php endif; ?>

    // 画像アップロード
    const imageUploadArea = document.getElementById('image-upload-area');
    const imageInput = document.getElementById('image-input');
    const featuredImageInput = document.getElementById('featured_image');

    imageUploadArea.addEventListener('click', function () {
        if (!this.classList.contains('has-image')) {
            imageInput.click();
        }
    });

    imageUploadArea.addEventListener('dragover', function (e) {
        e.preventDefault();
        this.style.borderColor = 'var(--primary)';
        this.style.background = 'var(--primary-bg)';
    });

    imageUploadArea.addEventListener('dragleave', function () {
        this.style.borderColor = '';
        this.style.background = '';
    });

    imageUploadArea.addEventListener('drop', function (e) {
        e.preventDefault();
        this.style.borderColor = '';
        this.style.background = '';

        const files = e.dataTransfer.files;
        if (files.length > 0 && files[0].type.startsWith('image/')) {
            uploadImage(files[0]);
        }
    });

    imageInput.addEventListener('change', function () {
        if (this.files.length > 0) {
            uploadImage(this.files[0]);
        }
    });

    function uploadImage(file) {
        const formData = new FormData();
        formData.append('file', file);
        formData.append('type', 'featured');

        fetch('/app/manage/free_page/api/upload_image?tenant=<?php echo h($tenantSlug); ?>', {
            method: 'POST',
            body: formData
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    featuredImageInput.value = data.url;
                    imageUploadArea.innerHTML = `
                        <img src="${data.url}" class="preview-image" id="preview-image">
                        <br>
                        <button type="button" class="remove-image" onclick="removeImage()">
                            <i class="fas fa-trash"></i> 画像を削除
                        </button>
                    `;
                    imageUploadArea.classList.add('has-image');
                } else {
                    alert('アップロードに失敗しました: ' + (data.error || '不明なエラー'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('アップロードに失敗しました');
            });
    }

    function removeImage() {
        featuredImageInput.value = '';
        imageUploadArea.innerHTML = `
            <div class="upload-icon">
                <i class="fas fa-cloud-upload-alt"></i>
            </div>
            <div class="upload-text">
                クリックまたはドラッグ&ドロップで画像をアップロード
            </div>
        `;
        imageUploadArea.classList.remove('has-image');
    }

    // プレビューを別ウィンドウで開く
    <?php if ($isEdit): ?>
        const PAGE_ID = <?php echo json_encode($page['id']); ?>;
        const TENANT_SLUG = <?php echo json_encode($tenantSlug); ?>;

        function openPreview(mode) {
            let url, windowName, windowFeatures;
            if (mode === 'mobile') {
                url = '/app/front/free_preview_mobile?tenant=' + TENANT_SLUG + '&id=' + PAGE_ID;
                windowName = 'freePagePreviewMobile';
                windowFeatures = 'width=550,height=1100,scrollbars=yes,resizable=yes';
            } else {
                url = '/app/front/free_preview_pc?tenant=' + TENANT_SLUG + '&id=' + PAGE_ID;
                windowName = 'freePagePreviewPC';
                windowFeatures = 'width=1400,height=900,scrollbars=yes,resizable=yes';
            }
            window.open(url, windowName, windowFeatures);
        }
    <?php endif; ?>
</script>

</main>
</body>

</html>