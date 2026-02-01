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
        header('Location: index.php?tenant=' . $tenantSlug);
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
                header('Location: post.php?tenant=' . $tenantSlug . '&id=' . $newId . '&saved=1');
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
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php echo $isEdit ? 'ページ編集' : '新規ページ作成'; ?> |
        <?php echo h($tenant['name']); ?>
    </title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/manage.css?v=<?php echo time(); ?>">

    <!-- TinyMCE -->
    <script src="/assets/tinymce/tinymce.min.js"></script>
    <script src="/assets/js/tinymce-config.js?v=<?php echo time(); ?>"></script>

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
            color: #fff;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-container {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 30px;
        }

        .form-container h2 {
            font-size: 1.2rem;
            color: #fff;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            color: #fff;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }

        .form-group .required {
            color: #f44336;
            margin-left: 5px;
        }

        .form-group input[type="text"],
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-group input[type="text"]:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #27a3eb;
            background: rgba(255, 255, 255, 0.12);
            box-shadow: 0 0 0 4px rgba(39, 163, 235, 0.1);
        }

        .form-group input[type="text"]::placeholder,
        .form-group textarea::placeholder {
            color: rgba(255, 255, 255, 0.4);
        }

        .form-group small {
            display: block;
            margin-top: 8px;
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.85rem;
        }

        .form-group .slug-preview {
            margin-top: 8px;
            padding: 10px 15px;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 8px;
            font-family: monospace;
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.7);
        }

        .form-group .slug-preview span {
            color: #27a3eb;
        }

        /* 画像アップロード */
        .image-upload-area {
            border: 2px dashed rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .image-upload-area:hover {
            border-color: rgba(39, 163, 235, 0.5);
            background: rgba(39, 163, 235, 0.05);
        }

        .image-upload-area.has-image {
            padding: 10px;
        }

        .image-upload-area .upload-icon {
            font-size: 3rem;
            color: rgba(255, 255, 255, 0.3);
            margin-bottom: 15px;
        }

        .image-upload-area .upload-text {
            color: rgba(255, 255, 255, 0.6);
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
            background: rgba(244, 67, 54, 0.2);
            color: #f44336;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.85rem;
        }

        .image-upload-area .remove-image:hover {
            background: rgba(244, 67, 54, 0.3);
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

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #27a3eb 0%, #1e88e5 100%);
            color: #fff;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(39, 163, 235, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #4caf50 0%, #43a047 100%);
            color: #fff;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(76, 175, 80, 0.4);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.15);
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
            background: rgba(76, 175, 80, 0.2);
            border: 1px solid rgba(76, 175, 80, 0.3);
            color: #4caf50;
        }

        .alert-error {
            background: rgba(244, 67, 54, 0.2);
            border: 1px solid rgba(244, 67, 54, 0.3);
            color: #f44336;
        }

        @media (max-width: 768px) {
            .form-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>

<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="container">
            <?php
            require_once __DIR__ . '/../includes/breadcrumb.php';
            $breadcrumbs = [
                ['label' => 'ダッシュボード', 'url' => '/app/manage/?tenant=' . $tenantSlug, 'icon' => 'fas fa-chart-pie'],
                ['label' => 'フリーページ', 'url' => 'index.php?tenant=' . $tenantSlug, 'icon' => 'fas fa-file-alt'],
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
                            <div>
                                <?php echo h($error); ?>
                            </div>
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
                            value="<?php echo h($page['title'] ?? ($_POST['title'] ?? '')); ?>"
                            placeholder="例: お問い合わせページ" required>
                        <small>管理画面での識別用タイトルです</small>
                    </div>

                    <div class="form-group">
                        <label>
                            URLスラッグ<span class="required">*</span>
                        </label>
                        <input type="text" name="slug" id="slug"
                            value="<?php echo h($page['slug'] ?? ($_POST['slug'] ?? '')); ?>" placeholder="例: contact"
                            required pattern="[a-zA-Z0-9\-_]+" title="英数字、ハイフン、アンダースコアのみ使用できます">
                        <div class="slug-preview">
                            URL: <span id="slug-preview-url">
                                <?php echo h($tenant['domain'] ?? 'your-domain.com'); ?>/
                                <?php echo h($page['slug'] ?? ''); ?>
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
                    <a href="index.php?tenant=<?php echo h($tenantSlug); ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        戻る
                    </a>
                    <?php if ($isEdit): ?>
                        <a href="preview.php?tenant=<?php echo h($tenantSlug); ?>&id=<?php echo $page['id']; ?>"
                            target="_blank" class="btn btn-secondary">
                            <i class="fas fa-eye"></i>
                            プレビュー
                        </a>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        保存
                    </button>
                </div>
            </form>
        </div>
    </main>

    <script>
        // TinyMCE初期化
        const uploadConfig = addImageUploadConfig(
            TinyMCEConfig.full,
            '/app/manage/free_page/api/upload_image.php?tenant=<?php echo h($tenantSlug); ?>',
            '/'
        );

        tinymce.init({
            selector: '#content',
            height: 500,
            ...uploadConfig,
            line_height_formats: '0.5 0.6 0.7 0.8 0.9 1 1.2 1.4 1.6 1.8 2 2.5 3',
            image_caption: true,
            image_advtab: true,
            setup: function (editor) {
                editor.on('init', function () {
                    const editorBody = editor.getBody();
                    editorBody.style.padding = '20px';
                    editorBody.style.boxSizing = 'border-box';
                });
            }
        });

        // スラッグプレビュー更新
        const slugInput = document.getElementById('slug');
        const slugPreview = document.getElementById('slug-preview-url');
        const domain = '<?php echo h($tenant['domain'] ?? 'your-domain.com'); ?>';

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
            this.style.borderColor = 'rgba(39, 163, 235, 0.7)';
            this.style.background = 'rgba(39, 163, 235, 0.1)';
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

            fetch('/app/manage/free_page/api/upload_image.php?tenant=<?php echo h($tenantSlug); ?>', {
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
    </script>
</body>

</html>