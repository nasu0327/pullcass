<?php
/**
 * リンクパーツ（埋め込みウィジェット）編集画面（インデックスページ用）
 */

require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

$id = $_GET['id'] ?? 0;

// セクション情報を取得
try {
    $stmt = $pdo->prepare("
        SELECT * FROM index_layout_sections 
        WHERE id = ? AND tenant_id = ? AND section_type = 'embed_widget'
    ");
    $stmt->execute([$id, $tenantId]);
    $section = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$section) {
        die('セクションが見つかりません');
    }
    
    $config = json_decode($section['config'], true) ?: [];
    $embedCode = $config['embed_code'] ?? '';
    $embedHeight = $config['embed_height'] ?? '400';
    
} catch (PDOException $e) {
    die('データベースエラー: ' . $e->getMessage());
}

$pageTitle = 'リンクパーツ編集 - ' . h($section['admin_title']);
?>

<?php include __DIR__ . '/../includes/header.php'; ?>

<link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

<style>
    .container {
        max-width: 900px;
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

    .form-group input[type="text"],
    .form-group input[type="number"],
    .form-group textarea {
        width: 100%;
        padding: 14px 18px;
        background: rgba(255, 255, 255, 0.08);
        border: 2px solid rgba(255, 255, 255, 0.15);
        border-radius: 12px;
        color: #fff;
        font-size: 1rem;
        box-sizing: border-box;
    }

    .form-group textarea {
        min-height: 200px;
        font-family: monospace;
        resize: vertical;
    }

    .form-group input:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #27a3eb;
    }

    .form-group small {
        display: block;
        margin-top: 8px;
        color: rgba(255, 255, 255, 0.6);
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
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: all 0.3s ease;
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

    .required {
        color: #f44336;
        margin-left: 5px;
    }

    .preview-box {
        margin-top: 20px;
        padding: 20px;
        background: rgba(0, 0, 0, 0.3);
        border-radius: 10px;
    }

    .preview-box h3 {
        color: rgba(255, 255, 255, 0.8);
        margin-bottom: 15px;
        font-size: 1rem;
    }
</style>

<div class="container">
    <div class="header">
        <h1>リンクパーツ編集</h1>
        <p>外部ウィジェットやiframeコードを埋め込みます</p>
    </div>

    <div class="form-container">
        <h2>
            <span class="material-icons">code</span>
            コンテンツ設定
        </h2>
        
        <div class="form-group">
            <label>管理名<span class="required">*</span></label>
            <input type="text" id="adminTitle" value="<?php echo h($section['admin_title']); ?>" placeholder="例: 求人ウィジェット" required>
            <small>管理画面で表示される名前です</small>
        </div>

        <div class="form-group">
            <label>メインタイトル（任意）</label>
            <input type="text" id="titleEn" value="<?php echo h($section['title_en']); ?>" placeholder="例: RECRUIT">
            <small>フロントエンドで表示されるメインタイトルです</small>
        </div>

        <div class="form-group">
            <label>サブタイトル（任意）</label>
            <input type="text" id="titleJa" value="<?php echo h($section['title_ja']); ?>" placeholder="例: 求人情報">
            <small>フロントエンドで表示されるサブタイトルです</small>
        </div>

        <div class="form-group">
            <label>埋め込みコード<span class="required">*</span></label>
            <textarea id="embedCode" placeholder="<iframe src='...'></iframe> や <script>...</script> など"><?php echo h($embedCode); ?></textarea>
            <small>iframe、script、その他HTMLコードを入力してください</small>
        </div>

        <div class="form-group">
            <label>高さ（px）</label>
            <input type="number" id="embedHeight" value="<?php echo h($embedHeight); ?>" placeholder="400" min="100" max="2000">
            <small>埋め込みコンテンツの高さを指定します（100〜2000px）</small>
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
    const TENANT_SLUG = '<?php echo addslashes($tenantSlug); ?>';

    function saveContent() {
        const adminTitle = document.getElementById('adminTitle').value.trim();
        const embedCode = document.getElementById('embedCode').value.trim();
        
        if (!adminTitle) {
            alert('管理名は必須です');
            return;
        }

        const data = {
            id: <?php echo $id; ?>,
            admin_title: adminTitle,
            title_en: document.getElementById('titleEn').value,
            title_ja: document.getElementById('titleJa').value,
            embed_code: embedCode,
            embed_height: document.getElementById('embedHeight').value || '400'
        };

        fetch('save_embed_widget.php?tenant=' + TENANT_SLUG, {
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
