<?php
/**
 * 埋め込みウィジェット編集画面
 * 外部サービスからの埋め込みコード（iframe、script等）を管理
 */

// 認証チェック（$tenant, $tenantIdが自動設定される）
require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

$id = $_GET['id'] ?? 0;

// セクション情報を取得
try {
    $stmt = $pdo->prepare("
        SELECT * FROM top_layout_sections 
        WHERE id = ? AND tenant_id = ? AND section_type = 'embed_widget'
    ");
    $stmt->execute([$id, $tenantId]);
    $section = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$section) {
        die('セクションが見つかりません');
    }

    // configから埋め込みコードを取得
    $config = json_decode($section['config'], true) ?: [];
    $embedCode = $config['embed_code'] ?? '';
    $embedHeight = $config['embed_height'] ?? '400';

} catch (PDOException $e) {
    die('データベースエラー: ' . $e->getMessage());
}

// ページタイトル
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
        background: var(--bg-card);
        border: none;
        border-radius: 15px;
        padding: 30px;
        margin-bottom: 30px;
        box-shadow: var(--shadow-card);
    }

    .form-container h2 {
        margin: 0 0 25px 0;
        font-size: 1.5rem;
        color: var(--primary);
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
        color: var(--text-primary);
        font-weight: 600;
        font-size: 0.95rem;
    }

    .form-group input[type="text"],
    .form-group input[type="number"] {
        width: 100%;
        padding: 14px 18px;
        background: var(--bg-body);
        border: 2px solid var(--border-color);
        border-radius: 12px;
        color: var(--text-primary);
        font-size: 1rem;
        transition: all 0.3s ease;
        box-sizing: border-box;
    }

    .form-group input[type="text"]:focus,
    .form-group input[type="number"]:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: var(--primary);
        background: var(--bg-body);
        box-shadow: 0 0 0 4px var(--primary-bg);
    }

    .form-group input[type="text"]::placeholder,
    .form-group input[type="number"]::placeholder {
        color: var(--text-muted);
    }

    .form-group textarea {
        width: 100%;
        min-height: 300px;
        padding: 14px 18px;
        background: var(--bg-body);
        border: 2px solid var(--border-color);
        border-radius: 12px;
        color: var(--text-primary);
        font-size: 0.9rem;
        font-family: 'Courier New', Monaco, monospace;
        resize: vertical;
        transition: all 0.3s ease;
        line-height: 1.6;
        box-sizing: border-box;
    }

    .form-group small {
        display: block;
        margin-top: 8px;
        color: var(--text-muted);
        font-size: 0.85rem;
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
        background: var(--primary);
        color: var(--text-inverse);
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px var(--primary-bg);
    }

    .btn-secondary {
        background: var(--bg-body);
        color: var(--text-primary);
        border: 2px solid var(--border-color);
    }

    .btn-secondary:hover {
        background: var(--bg-card);
        border-color: var(--primary);
    }

    .required {
        color: var(--danger);
        margin-left: 5px;
    }
</style>

<div class="container">
    <?php
    require_once __DIR__ . '/../includes/breadcrumb.php';
    $breadcrumbs = [
        ['label' => 'ダッシュボード', 'url' => '/app/manage/?tenant=' . $tenantSlug, 'icon' => 'fas fa-chart-pie'],
        ['label' => 'トップページ編集', 'url' => '/app/manage/top_layout/?tenant=' . $tenantSlug],
        ['label' => h($section['admin_title']) . ' 編集']
    ];
    renderBreadcrumb($breadcrumbs);
    ?>
    <div class="header">
        <h1>リンクパーツ編集</h1>
        <p>風俗サイトから提供されたリンクパーツのコードをそのまま貼り付けて下さい。</p>
    </div>

    <div class="form-container">
        <h2>
            <span class="material-icons">code</span>
            埋め込みコード設定
        </h2>

        <div class="form-group">
            <label>
                管理名<span class="required">*</span>
            </label>
            <input type="text" id="adminTitle" value="<?php echo h($section['admin_title']); ?>"
                placeholder="例: 関連リンクセクション" required>
            <small>管理画面で表示される名前です</small>
        </div>

        <div class="form-group">
            <label>
                メインタイトル（任意）
            </label>
            <input type="text" id="titleEn" value="<?php echo h($section['title_en']); ?>"
                placeholder="例: Related Links">
            <small>フロントエンドで表示されるメインタイトルです</small>
        </div>

        <div class="form-group">
            <label>
                サブタイトル（任意）
            </label>
            <input type="text" id="titleJa" value="<?php echo h($section['title_ja']); ?>" placeholder="例: 関連リンク">
            <small>フロントエンドで表示されるサブタイトルです</small>
        </div>

        <div class="form-group">
            <label>
                埋め込みコード<span class="required">*</span>
            </label>
            <textarea id="embedCode" placeholder="外部サービスから提供された埋め込みコード（iframe、scriptタグなど）を貼り付けてください"
                required><?php echo h($embedCode); ?></textarea>
            <small>HTML、iframe、JavaScriptコードに対応しています</small>
        </div>

        <div class="form-group">
            <label>
                表示高さ（px）
            </label>
            <input type="number" id="embedHeight" value="<?php echo h($embedHeight); ?>" min="100" step="10"
                placeholder="400">
            <small>iframe表示時の高さを指定します（デフォルト: 400px）</small>
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
    function saveContent() {
        const adminTitle = document.getElementById('adminTitle').value.trim();

        if (!adminTitle) {
            alert('管理名は必須です');
            return;
        }

        const embedCode = document.getElementById('embedCode').value.trim();

        if (!embedCode) {
            alert('埋め込みコードを入力してください');
            return;
        }

        const data = {
            id: <?php echo $id; ?>,
            admin_title: adminTitle,
            title_en: document.getElementById('titleEn').value,
            title_ja: document.getElementById('titleJa').value,
            embed_code: embedCode,
            embed_height: document.getElementById('embedHeight').value
        };

        fetch('save_embed_widget.php', {
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