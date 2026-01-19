<?php
/**
 * Hero Text（H1タイトル・導入文）編集画面
 */

// 認証チェック（$tenant, $tenantIdが自動設定される）
require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

// セクションIDを取得
$id = $_GET['id'] ?? 0;

// セクションデータを取得
try {
    $stmt = $pdo->prepare("
        SELECT * FROM top_layout_sections 
        WHERE id = ? AND tenant_id = ? AND section_key = 'hero_text'
    ");
    $stmt->execute([$id, $tenantId]);
    $section = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$section) {
        die('セクションが見つかりません');
    }
    
    // configからH1タイトルと導入文を取得
    $config = json_decode($section['config'], true) ?? [];
    $h1_title = $config['h1_title'] ?? '';
    $intro_text = $config['intro_text'] ?? '';
    
} catch (PDOException $e) {
    die('データベースエラー: ' . $e->getMessage());
}

// ページタイトル
$pageTitle = 'トップバナー下テキスト編集';
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
        font-size: 0.95rem;
    }

    .form-group input[type="text"],
    .form-group textarea {
        width: 100%;
        padding: 14px 18px;
        background: rgba(255, 255, 255, 0.08);
        border: 2px solid rgba(255, 255, 255, 0.15);
        border-radius: 12px;
        color: #fff;
        font-size: 1rem;
        transition: all 0.3s ease;
        box-sizing: border-box;
        font-family: inherit;
    }

    .form-group input[type="text"]:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #27a3eb;
        background: rgba(255, 255, 255, 0.12);
        box-shadow: 0 0 0 4px rgba(39, 163, 235, 0.1);
    }

    .form-group input[type="text"]::placeholder,
    .form-group textarea::placeholder {
        color: rgba(255, 255, 255, 0.4);
    }

    .form-group textarea {
        min-height: 120px;
        resize: vertical;
        line-height: 1.6;
    }

    .form-group small {
        display: block;
        margin-top: 8px;
        color: rgba(255, 255, 255, 0.6);
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
        font-size: 11px;
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
</style>

<div class="container">
    <div class="header">
        <h1>トップバナー下テキスト編集</h1>
        <p>※基本表示です。表示させたくない場合はレイアウト管理のトップで「👁️」で非表示にして下さい。</p>
    </div>

    <div class="form-container">
        <h2>
            <span class="material-icons">description</span>
            H1タイトル・導入文設定
        </h2>
        
        <form id="heroTextForm">
            <input type="hidden" name="id" value="<?php echo h($id); ?>">
            
            <div class="form-group">
                <label>
                    H1タイトル<span class="required">*</span>
                </label>
                <input 
                    type="text" 
                    id="h1Title" 
                    name="h1_title" 
                    value="<?php echo h($h1_title); ?>"
                    placeholder="例: 福岡・博多のぽっちゃり風俗デリヘル「豊満倶楽部」｜百名店認定の人気店"
                    required
                >
                <small>トップページの最上部に表示されるメインタイトルです（SEO重要）</small>
            </div>

            <div class="form-group">
                <label>
                    導入文<span class="required">*</span>
                </label>
                <textarea 
                    id="introText" 
                    name="intro_text"
                    placeholder="例: 福岡・博多エリアの巨乳ぽっちゃり専門風俗デリヘル。創業15年以上の実績と百名店認定で安心。"
                    required
                ><?php echo h($intro_text); ?></textarea>
                <small>タイトルの下に表示される説明文です</small>
            </div>

            <div class="buttons">
                <button type="button" class="btn btn-secondary" onclick="window.location.href='index.php?tenant=<?php echo urlencode($tenantSlug); ?>'">
                    <span class="material-icons">arrow_back</span>
                    戻る
                </button>
                <button type="submit" class="btn btn-primary">
                    <span class="material-icons">save</span>
                    保存する
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // フォーム送信
    document.getElementById('heroTextForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const data = {
            id: formData.get('id'),
            h1_title: formData.get('h1_title'),
            intro_text: formData.get('intro_text')
        };
        
        try {
            const response = await fetch('save_hero_text.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                alert('保存しました');
            } else {
                alert('保存に失敗しました: ' + (result.message || '不明なエラー'));
            }
            
        } catch (error) {
            console.error('Error:', error);
            alert('保存に失敗しました');
        }
    });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
