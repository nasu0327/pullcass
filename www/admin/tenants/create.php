<?php
/**
 * pullcass - スーパー管理画面
 * 新規店舗登録
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

requireSuperAdminLogin();

$errors = [];
$success = false;

// フォーム送信処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF検証
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = '不正なリクエストです。';
    } else {
        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $domain = trim($_POST['domain'] ?? '') ?: null;
        
        // バリデーション
        if (empty($name)) {
            $errors[] = '店舗名を入力してください。';
        }
        
        if (empty($code)) {
            $errors[] = 'コードを入力してください。';
        } elseif (!preg_match('/^[a-z0-9_-]+$/', $code)) {
            $errors[] = 'コードは半角英数字、ハイフン、アンダースコアのみ使用できます。';
        }
        
        // 重複チェック
        if (empty($errors)) {
            try {
                $pdo = getPlatformDb();
                
                $stmt = $pdo->prepare("SELECT id FROM tenants WHERE code = ?");
                $stmt->execute([$code]);
                if ($stmt->fetch()) {
                    $errors[] = 'このコードは既に使用されています。';
                }
                
                if ($domain) {
                    $stmt = $pdo->prepare("SELECT id FROM tenants WHERE domain = ?");
                    $stmt->execute([$domain]);
                    if ($stmt->fetch()) {
                        $errors[] = 'このドメインは既に使用されています。';
                    }
                }
            } catch (PDOException $e) {
                $errors[] = 'データベースエラーが発生しました。';
            }
        }
        
        // 登録処理
        if (empty($errors)) {
            try {
                $pdo = getPlatformDb();
                
                // テナントを登録
                $stmt = $pdo->prepare("
                    INSERT INTO tenants (name, code, domain, is_active, settings)
                    VALUES (?, ?, ?, 1, '{}')
                ");
                $stmt->execute([$name, $code, $domain]);
                $tenantId = $pdo->lastInsertId();
                
                setFlash('success', "店舗「{$name}」を登録しました。");
                redirect('/admin/tenants/');
                
            } catch (PDOException $e) {
                $errors[] = APP_DEBUG ? $e->getMessage() : 'データベースエラーが発生しました。';
            }
        }
    }
}

$pageTitle = '新規店舗登録';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-plus-circle"></i> 新規店舗登録</h1>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <i class="fas fa-exclamation-circle"></i>
    <ul style="margin: 0; padding-left: 20px;">
        <?php foreach ($errors as $error): ?>
        <li><?php echo h($error); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="content-section">
    <form method="POST" action="" class="form">
        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
        
        <div class="form-group">
            <label for="name">店舗名 <span class="required">*</span></label>
            <input type="text" id="name" name="name" class="form-control" required
                   value="<?php echo h($_POST['name'] ?? ''); ?>"
                   placeholder="例: 豊満倶楽部">
            <small class="form-help">お店の正式名称を入力してください</small>
        </div>
        
        <div class="form-group">
            <label for="code">コード（URL識別子） <span class="required">*</span></label>
            <input type="text" id="code" name="code" class="form-control" required
                   pattern="[a-z0-9_-]+"
                   value="<?php echo h($_POST['code'] ?? ''); ?>"
                   placeholder="例: houman">
            <small class="form-help">
                半角英数字、ハイフン、アンダースコアのみ使用可能<br>
                → <code>houman.pullcass.com</code> のようにURLに使用されます
            </small>
        </div>
        
        <div class="form-group">
            <label for="domain">カスタムドメイン（任意）</label>
            <input type="text" id="domain" name="domain" class="form-control"
                   value="<?php echo h($_POST['domain'] ?? ''); ?>"
                   placeholder="例: club-houman.com">
            <small class="form-help">独自ドメインを使用する場合に設定（後から設定可能）</small>
        </div>
        
        <div class="form-actions">
            <a href="/admin/tenants/" class="btn btn-secondary">
                <i class="fas fa-times"></i> キャンセル
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-store"></i> 店舗を登録
            </button>
        </div>
    </form>
</div>

<style>
    .form {
        max-width: 600px;
    }
    
    .form-group .required {
        color: var(--primary);
    }
    
    .form-help {
        display: block;
        margin-top: 8px;
        font-size: 0.85rem;
        color: rgba(255, 255, 255, 0.7);
    }
    
    .form-actions {
        display: flex;
        gap: 15px;
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid var(--border-color);
    }
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
