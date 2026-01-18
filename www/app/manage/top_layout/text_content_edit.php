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
<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>

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
        font-size: 1rem;
        font-weight: 600;
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
</style>

<div class="container">
    <div class="header">
        <h1>テキストコンテンツ編集</h1>
        <p>「＜＞」ボタンからhtmlコードにも対応してます。</p>
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
            <input 
                type="text" 
                id="adminTitle" 
                value="<?php echo h($section['admin_title']); ?>" 
                placeholder="例: お店紹介セクション" 
                required
            >
            <small>管理画面で表示される名前です</small>
        </div>

        <div class="form-group">
            <label>
                メインタイトル（任意）
            </label>
            <input 
                type="text" 
                id="titleEn" 
                value="<?php echo h($section['title_en']); ?>" 
                placeholder="例: About Our Shop"
            >
            <small>フロントエンドで表示されるメインタイトルです</small>
        </div>

        <div class="form-group">
            <label>
                サブタイトル（任意）
            </label>
            <input 
                type="text" 
                id="titleJa" 
                value="<?php echo h($section['title_ja']); ?>" 
                placeholder="例: お店紹介"
            >
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
            <button type="button" class="btn btn-secondary" onclick="window.location.href='index.php?tenant=<?php echo urlencode($tenantSlug); ?>'">
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
    // TinyMCE初期化
    tinymce.init({
        selector: '#htmlContent',
        height: 500,
        menubar: 'file edit view insert format tools table',
        plugins: [
            'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
            'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
            'insertdatetime', 'media', 'table', 'code', 'help', 'wordcount'
        ],
        toolbar: 'undo redo | blocks | ' +
            'bold italic forecolor backcolor | alignleft aligncenter ' +
            'alignright alignjustify | bullist numlist outdent indent | ' +
            'removeformat | code | image media table | help',
        content_style: 'body { font-family: "M PLUS 1p", sans-serif; font-size: 16px; line-height: 1.6; }',
        
        // 画像アップロード設定
        images_upload_url: 'api/upload_image.php',
        automatic_uploads: true,
        images_reuse_filename: false,
        images_upload_handler: function (blobInfo, success, failure) {
            const xhr = new XMLHttpRequest();
            xhr.withCredentials = false;
            xhr.open('POST', 'api/upload_image.php');
            
            xhr.onload = function() {
                if (xhr.status != 200) {
                    failure('HTTP Error: ' + xhr.status);
                    return;
                }
                
                const json = JSON.parse(xhr.responseText);
                
                if (!json || typeof json.location != 'string') {
                    failure('Invalid JSON: ' + xhr.responseText);
                    return;
                }
                
                success(json.location);
            };
            
            const formData = new FormData();
            formData.append('file', blobInfo.blob(), blobInfo.filename());
            
            xhr.send(formData);
        }
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
