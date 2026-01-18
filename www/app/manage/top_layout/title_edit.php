<?php
/**
 * セクションタイトル編集画面
 */

// 認証チェック
require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

// 共通ファイル読み込み
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/tenant.php';

// テナント情報取得
$tenantAdmin = getCurrentTenantAdmin();
$tenantId = $tenantAdmin['tenant_id'];

// セクションIDを取得
$sectionId = $_GET['id'] ?? '';

if (empty($sectionId)) {
    header('Location: index.php');
    exit;
}

// セクション情報を取得
try {
    $stmt = $pdo->prepare("SELECT * FROM top_layout_sections WHERE id = ? AND tenant_id = ? LIMIT 1");
    $stmt->execute([$sectionId, $tenantId]);
    $section = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$section) {
        header('Location: index.php');
        exit;
    }
    
} catch (PDOException $e) {
    die("エラー: " . $e->getMessage());
}

// ページタイトル
$pageTitle = 'セクション設定 - ' . h($section['admin_title']);
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

    .required {
        color: #f44336;
        margin-left: 5px;
    }
</style>

<div class="container">
    <div class="header">
        <h1>セクション設定</h1>
        <p>※基本表示です。表示させたくない場合はレイアウト管理のトップで「👁️」で非表示にして下さい。</p>
    </div>

    <div class="form-container">
        <h2>
            <span class="material-icons">edit</span>
            タイトル設定
        </h2>
        
        <form id="titleForm">
            <div class="form-group">
                <label for="adminTitle">
                    管理名<span class="required">*</span>
                </label>
                <input 
                    type="text" 
                    id="adminTitle" 
                    value="<?php echo h($section['admin_title']); ?>" 
                    placeholder="例: 本日の出勤キャスト一覧" 
                    required
                >
                <small>管理画面で表示される名前です</small>
            </div>

            <div class="form-group">
                <label for="titleEn">
                    メインタイトル（任意）
                </label>
                <input 
                    type="text" 
                    id="titleEn" 
                    value="<?php echo h($section['title_en']); ?>" 
                    placeholder="例: Today's Cast"
                >
                <small>フロントエンドで表示されるメインタイトルです</small>
            </div>

            <div class="form-group">
                <label for="titleJa">
                    サブタイトル（任意）
                </label>
                <input 
                    type="text" 
                    id="titleJa" 
                    value="<?php echo h($section['title_ja']); ?>" 
                    placeholder="例: 本日の出勤"
                >
                <small>フロントエンドで表示されるサブタイトルです</small>
            </div>

            <div class="buttons">
                <button type="button" class="btn btn-primary" onclick="saveTitles()">
                    <span class="material-icons">save</span>
                    保存する
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // タイトル保存
    function saveTitles() {
        const adminTitle = document.getElementById('adminTitle').value.trim();
        const titleEn = document.getElementById('titleEn').value.trim();
        const titleJa = document.getElementById('titleJa').value.trim();
        const sectionId = <?php echo $section['id']; ?>;
        
        if (!adminTitle) {
            alert('管理名は必須です。');
            return;
        }
        
        fetch('edit_title.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                id: sectionId,
                admin_title: adminTitle,
                title_en: titleEn,
                title_ja: titleJa
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('保存しました');
            } else {
                alert('保存に失敗しました: ' + (data.message || '不明なエラー'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('保存に失敗しました');
        });
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
