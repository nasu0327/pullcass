<?php
/**
 * テキストコンテンツ編集画面
 * TinyMCEエディタを使用してHTML対応のリッチテキストを編集
 */

// 認証チェック（$tenant, $tenantIdが自動設定される）
require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

$id = $_GET['id'] ?? 0;

// セクション情報を取得
try {
    $stmt = $pdo->prepare("
        SELECT * FROM top_layout_sections 
        WHERE id = ? AND tenant_id = ? AND section_type = 'text_content'
    ");
    $stmt->execute([$id, $tenantId]);
    $section = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$section) {
        die('セクションが見つかりません');
    }

    // configからテキストコンテンツを取得
    $config = json_decode($section['config'], true) ?: [];
    $htmlContent = $config['html_content'] ?? '';

} catch (PDOException $e) {
    die('データベースエラー: ' . $e->getMessage());
}

// ページタイトル
$pageTitle = 'テキストコンテンツ編集 - ' . h($section['admin_title']);
?>

<?php include __DIR__ . '/../includes/header.php'; ?>

<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

<!-- TinyMCE -->
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
        font-size: 0.95rem;
    }

    .form-group input[type="text"] {
        width: 100%;
        padding: 14px 18px;
        background: rgba(255, 255, 255, 0.08);
        border: 2px solid rgba(255, 255, 255, 0.15);
        border-radius: 12px;
        color: #fff;
        font-size: 1rem;
        transition: all 0.3s ease;
        box-sizing: border-box;
    }

    .form-group input[type="text"]:focus {
        outline: none;
        border-color: #27a3eb;
        background: rgba(255, 255, 255, 0.12);
        box-shadow: 0 0 0 4px rgba(39, 163, 235, 0.1);
    }

    .form-group input[type="text"]::placeholder {
        color: rgba(255, 255, 255, 0.4);
    }

    .form-group small {
        display: block;
        margin-top: 8px;
        color: rgba(255, 255, 255, 0.6);
        font-size: 0.85rem;
    }

    .editor-wrapper {
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        border: 2px solid rgba(39, 163, 235, 0.3);
    }

    .buttons {
        display: flex;
        gap: 15px;
        margin-top: 30px;
    }

    .btn {
        flex: 1;
        padding: 14px 28px;
        border: none;
        border-radius: 12px;
        font-size: 13px;
        font-weight: 400;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .btn-primary {
        background: linear-gradient(135deg, #27a3eb 0%, #1e88c7 100%);
        color: white;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(39, 163, 235, 0.4);
    }

    .btn-secondary {
        background: rgba(255, 255, 255, 0.1);
        color: rgba(255, 255, 255, 0.9);
        border: 2px solid rgba(255, 255, 255, 0.2);
    }

    .btn-secondary:hover {
        background: rgba(255, 255, 255, 0.15);
        border-color: rgba(255, 255, 255, 0.3);
    }

    .required {
        color: #f44336;
        margin-left: 5px;
    }

    /* TinyMCEプレビューダイアログの背景ぼかし効果 */
    .tox-dialog-wrap__backdrop,
    .tox-dialog__backdrop {
        backdrop-filter: blur(8px) !important;
        -webkit-backdrop-filter: blur(8px) !important;
        background-color: rgba(0, 0, 0, 0.5) !important;
    }

    .tox .tox-dialog {
        border-radius: 15px !important;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4) !important;
    }

    .tox .tox-dialog__body-content,
    .tox .tox-dialog__body-content iframe,
    .tox-dialog__body-content {
        background-color: #2d2d2d !important;
    }

    .tox-dialog__body-content iframe {
        background: #2d2d2d !important;
    }

    .tox .tox-dialog__header {
        background-color: #2d2d2d !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
    }

    .tox .tox-dialog__title {
        font-size: 0.85rem !important;
        font-weight: 700 !important;
        color: #ffffff !important;
    }

    .tox .tox-dialog__header .tox-button {
        color: rgba(255, 255, 255, 0.8) !important;
    }

    .tox .tox-dialog__header .tox-button:hover {
        background-color: rgba(255, 255, 255, 0.1) !important;
        color: #ffffff !important;
    }

    .tox .tox-dialog__header .tox-button svg {
        fill: rgba(255, 255, 255, 0.8) !important;
    }

    .tox .tox-dialog__header .tox-button:hover svg {
        fill: #ffffff !important;
    }

    .tox .tox-dialog__footer {
        background-color: #2d2d2d !important;
        border-top: 1px solid rgba(255, 255, 255, 0.1) !important;
    }

    .tox .tox-dialog__footer .tox-button {
        background-color: rgba(255, 255, 255, 0.1) !important;
        color: #ffffff !important;
        border: 1px solid rgba(255, 255, 255, 0.2) !important;
    }

    .tox .tox-dialog__footer .tox-button:hover {
        background-color: rgba(255, 255, 255, 0.2) !important;
        border-color: rgba(255, 255, 255, 0.3) !important;
    }
</style>

<div class="container">
    <?php
    require_once __DIR__ . '/../includes/breadcrumb.php';
    $breadcrumbs = [
        ['label' => 'ホーム', 'url' => '/app/manage/?tenant=' . $tenantSlug, 'icon' => 'fas fa-home'],
        ['label' => 'トップページ編集', 'url' => '/app/manage/top_layout/?tenant=' . $tenantSlug],
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
            <label>
                管理名<span class="required">*</span>
            </label>
            <input type="text" id="adminTitle" value="<?php echo h($section['admin_title']); ?>"
                placeholder="例: お店紹介セクション" required>
            <small>管理画面で表示される名前です</small>
        </div>

        <div class="form-group">
            <label>
                メインタイトル（任意）
            </label>
            <input type="text" id="titleEn" value="<?php echo h($section['title_en']); ?>"
                placeholder="例: About Our Shop">
            <small>フロントエンドで表示されるメインタイトルです</small>
        </div>

        <div class="form-group">
            <label>
                サブタイトル（任意）
            </label>
            <input type="text" id="titleJa" value="<?php echo h($section['title_ja']); ?>" placeholder="例: お店紹介">
            <small>フロントエンドで表示されるサブタイトルです</small>
        </div>

        <div class="form-group">
            <label>
                コンテンツ<span class="required">*</span>
            </label>
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
    // TinyMCE初期化（フル機能版・画像アップロード対応）
    // ※参考サイトと完全に同じ設定
    const topLayoutConfig = addImageUploadConfig(TinyMCEConfig.full, 'api/upload_image.php', '/');

    tinymce.init({
        selector: '#htmlContent',
        height: 500,
        ...topLayoutConfig,
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

    // プレビューダイアログの背景クリックで閉じる機能
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

        fetch('save_text_content.php', {
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