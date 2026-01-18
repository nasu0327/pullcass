<?php
/**
 * pullcass - マスター管理画面
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
        $agency_name = trim($_POST['agency_name'] ?? '') ?: null;
        $agency_contact = trim($_POST['agency_contact'] ?? '') ?: null;
        $agency_phone = trim($_POST['agency_phone'] ?? '') ?: null;
        
        // バリデーション
        if (empty($name)) {
            $errors[] = '店舗名を入力してください。';
        }
        
        if (empty($code)) {
            $errors[] = 'テスト用サブドメインを入力してください。';
        } elseif (!preg_match('/^[a-z0-9_-]+$/', $code)) {
            $errors[] = 'テスト用サブドメインは半角英数字、ハイフン、アンダースコアのみ使用できます。';
        }
        
        // 重複チェック
        if (empty($errors)) {
            try {
                $pdo = getPlatformDb();
                
                $stmt = $pdo->prepare("SELECT id FROM tenants WHERE code = ?");
                $stmt->execute([$code]);
                if ($stmt->fetch()) {
                    $errors[] = 'このサブドメインは既に使用されています。';
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
                    INSERT INTO tenants (name, code, domain, is_active, settings, agency_name, agency_contact, agency_phone)
                    VALUES (?, ?, NULL, 1, '{}', ?, ?, ?)
                ");
                $stmt->execute([$name, $code, $agency_name, $agency_contact, $agency_phone]);
                $tenantId = $pdo->lastInsertId();
                
                // トップページレイアウト管理のデフォルトセクションを作成（全て非表示）
                try {
                    require_once __DIR__ . '/../../includes/top_layout_init.php';
                    initTopLayoutSections($pdo, $tenantId);
                } catch (Exception $e) {
                    // セクション作成に失敗してもテナント作成は続行
                    error_log("トップページレイアウト管理の初期データ作成に失敗: " . $e->getMessage());
                }
                
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
        
        <div class="form-section-title"><i class="fas fa-store"></i> 店舗情報</div>
        
        <div class="form-group">
            <label for="name">店舗名 <span class="required">*</span></label>
            <input type="text" id="name" name="name" class="form-control" required
                   value="<?php echo h($_POST['name'] ?? ''); ?>"
                   placeholder="正式店舗名">
            <small class="form-help">お店の正式名称を入力してください</small>
        </div>
        
        <div class="form-group">
            <label for="code">テスト用サブドメイン <span class="required">*</span></label>
            <input type="text" id="code" name="code" class="form-control" required
                   pattern="[a-z0-9_-]+"
                   value="<?php echo h($_POST['code'] ?? ''); ?>"
                   placeholder="例：deriheru_xxx">
            <small class="form-help">
                半角英数字、ハイフン、アンダースコアのみ使用可能<br>
                <code>deriheru_xxx.pullcass.com</code> としてテストページが作成されます
            </small>
        </div>
        
        <div class="form-section-title"><i class="fas fa-building"></i> 登録代理店情報</div>
        
        <div class="form-group">
            <label for="agency_name">登録代理店会社名</label>
            <input type="text" id="agency_name" name="agency_name" class="form-control"
                   value="<?php echo h($_POST['agency_name'] ?? ''); ?>"
                   placeholder="代理店会社名">
            <small class="form-help">代理店経由の登録の場合に入力してください</small>
        </div>
        
        <div class="form-group">
            <label for="agency_contact">担当者名</label>
            <input type="text" id="agency_contact" name="agency_contact" class="form-control"
                   value="<?php echo h($_POST['agency_contact'] ?? ''); ?>"
                   placeholder="担当者名">
        </div>
        
        <div class="form-group">
            <label for="agency_phone">担当者電話番号</label>
            <input type="tel" id="agency_phone" name="agency_phone" class="form-control"
                   value="<?php echo h($_POST['agency_phone'] ?? ''); ?>"
                   placeholder="例：090-1234-5678">
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
    
    .form-section-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text-light);
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .form-section-title:not(:first-of-type) {
        margin-top: 35px;
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
