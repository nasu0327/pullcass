<?php
/**
 * pullcass - 店舗管理画面
 * ログインページ
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

// テナント識別（URLパラメータから）
$tenantSlug = $_GET['tenant'] ?? null;

// 既にログイン済みなら管理画面へ
if (isset($_SESSION['manage_admin_id']) && isset($_SESSION['manage_tenant_slug'])) {
    redirect('/app/manage/?tenant=' . $_SESSION['manage_tenant_slug']);
}

$error = '';
$tenant = null;

// テナント情報を取得
if ($tenantSlug) {
    try {
        $pdo = getPlatformDb();
        $stmt = $pdo->prepare("SELECT * FROM tenants WHERE code = ? AND is_active = 1");
        $stmt->execute([$tenantSlug]);
        $tenant = $stmt->fetch();
        
        if (!$tenant) {
            $error = '指定された店舗が見つかりません。';
        }
    } catch (PDOException $e) {
        $error = APP_DEBUG ? $e->getMessage() : 'システムエラーが発生しました。';
    }
}

// ログイン処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $tenantSlugPost = $_POST['tenant'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'ログインIDとパスワードを入力してください。';
    } elseif (empty($tenantSlugPost)) {
        $error = '店舗情報が不正です。';
    } else {
        try {
            $pdo = getPlatformDb();
            
            // テナント情報を再取得
            $stmt = $pdo->prepare("SELECT * FROM tenants WHERE code = ? AND is_active = 1");
            $stmt->execute([$tenantSlugPost]);
            $tenant = $stmt->fetch();
            
            if (!$tenant) {
                $error = '店舗が見つかりません。';
            } else {
                // 管理者アカウントを取得
                $stmt = $pdo->prepare("
                    SELECT * FROM tenant_admins 
                    WHERE tenant_id = ? AND username = ? AND is_active = 1
                ");
                $stmt->execute([$tenant['id'], $username]);
                $admin = $stmt->fetch();
                
                if ($admin && password_verify($password, $admin['password_hash'])) {
                    // ログイン成功
                    $_SESSION['manage_admin_id'] = $admin['id'];
                    $_SESSION['manage_admin_name'] = $admin['name'];
                    $_SESSION['manage_admin_username'] = $admin['username'];
                    $_SESSION['manage_tenant_slug'] = $tenant['code'];
                    $_SESSION['manage_tenant'] = $tenant;
                    
                    // 最終ログイン日時を更新
                    $updateStmt = $pdo->prepare("UPDATE tenant_admins SET last_login_at = NOW() WHERE id = ?");
                    $updateStmt->execute([$admin['id']]);
                    
                    redirect('/app/manage/?tenant=' . $tenant['code']);
                } else {
                    $error = 'ログインIDまたはパスワードが正しくありません。';
                }
            }
        } catch (PDOException $e) {
            $error = APP_DEBUG ? $e->getMessage() : 'システムエラーが発生しました。';
        }
    }
}

// 店舗名を表示用に取得
$shopName = $tenant ? $tenant['name'] : '店舗';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン | <?php echo h($shopName); ?> 様 管理画面</title>
    <link href="https://fonts.googleapis.com/css2?family=Zen+Kaku+Gothic+New:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary: #ff6b9d;
            --primary-dark: #e91e63;
            --secondary: #7c4dff;
            --dark: #1a1a2e;
            --darker: #0f0f1a;
            --card-bg: #16162a;
            --border-color: rgba(255, 255, 255, 0.1);
            --text-light: #ffffff;
            --text-muted: #c8c8d8;
        }
        
        body {
            font-family: 'Zen Kaku Gothic New', sans-serif;
            background: var(--darker);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: 
                radial-gradient(ellipse at 20% 80%, rgba(255, 107, 157, 0.1) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 20%, rgba(124, 77, 255, 0.1) 0%, transparent 50%);
            pointer-events: none;
        }
        
        .login-container {
            width: 100%;
            max-width: 420px;
            padding: 20px;
            position: relative;
            z-index: 1;
        }
        
        .login-card {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 50px 40px;
            border: 1px solid var(--border-color);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo h1 {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-light);
            margin-bottom: 8px;
        }
        
        .logo .shop-name {
            font-size: 1.5rem;
            font-weight: 900;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.5px;
            margin-bottom: 8px;
        }
        
        .logo p {
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-light);
            margin-bottom: 8px;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-wrapper i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }
        
        .form-group input {
            width: 100%;
            padding: 14px 16px 14px 45px;
            font-size: 1rem;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            transition: all 0.2s ease;
            background: var(--darker);
            color: var(--text-light);
        }
        
        .form-group input::placeholder {
            color: var(--text-muted);
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(255, 107, 157, 0.2);
        }
        
        .btn-login {
            width: 100%;
            padding: 16px;
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-light);
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 4px 20px rgba(255, 107, 157, 0.3);
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 30px rgba(255, 107, 157, 0.4);
        }
        
        .btn-login:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        .error-message {
            background: rgba(239, 68, 68, 0.1);
            color: #f87171;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            text-align: center;
            border: 1px solid rgba(239, 68, 68, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .back-link {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 25px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.2s ease;
        }
        
        .back-link:hover {
            color: var(--primary);
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 0;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.2s ease;
        }
        
        .password-toggle:hover {
            color: var(--primary);
        }
        
        .form-group input[type="password"],
        .form-group input[type="text"]#password {
            padding-right: 50px;
        }
        
        .no-tenant {
            text-align: center;
            padding: 20px;
        }
        
        .no-tenant i {
            font-size: 3rem;
            color: var(--text-muted);
            margin-bottom: 20px;
        }
        
        .no-tenant h2 {
            color: var(--text-light);
            margin-bottom: 10px;
        }
        
        .no-tenant p {
            color: var(--text-muted);
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <?php if (!$tenant && !$tenantSlug): ?>
            <!-- テナント未指定 -->
            <div class="no-tenant">
                <i class="fas fa-store"></i>
                <h2>店舗管理画面</h2>
                <p>ログインするにはURLに店舗コードを指定してください。</p>
                <p style="font-size: 0.85rem; color: var(--text-muted);">
                    例：<code style="background: var(--darker); padding: 2px 8px; border-radius: 4px;">/app/manage/login.php?tenant=店舗コード</code>
                </p>
            </div>
            <?php elseif (!$tenant && $tenantSlug): ?>
            <!-- テナントが見つからない -->
            <div class="logo">
                <h1>店舗管理画面</h1>
            </div>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo h($error); ?>
            </div>
            <a href="/admin/" class="back-link">
                <i class="fas fa-arrow-left"></i> マスター管理画面に戻る
            </a>
            <?php else: ?>
            <!-- ログインフォーム -->
            <div class="logo">
                <h1>店舗管理画面</h1>
                <div class="shop-name"><?php echo h($tenant['name']); ?> 様</div>
                <p><i class="fas fa-sign-in-alt"></i> ログイン</p>
            </div>
            
            <?php if ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo h($error); ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <input type="hidden" name="tenant" value="<?php echo h($tenant['code']); ?>">
                
                <div class="form-group">
                    <label for="username">ログインID</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user"></i>
                        <input type="text" id="username" name="username" required 
                               value="<?php echo h($_POST['username'] ?? ''); ?>"
                               placeholder="ログインIDを入力">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">パスワード</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" required
                               placeholder="パスワードを入力">
                        <button type="button" class="password-toggle" onclick="togglePassword()">
                            <i class="fas fa-eye" id="password-toggle-icon"></i>
                        </button>
                    </div>
                </div>
                
                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> ログイン
                </button>
            </form>
            
            <a href="<?php echo 'https://' . $tenant['code'] . '.pullcass.com/app/front/top.php'; ?>" class="back-link">
                <i class="fas fa-arrow-left"></i> サイトに戻る
            </a>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('password-toggle-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>
