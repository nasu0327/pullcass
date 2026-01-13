<?php
/**
 * pullcass - スーパー管理画面
 * ログインページ
 */

require_once __DIR__ . '/../includes/bootstrap.php';

// 既にログイン済みならダッシュボードへ
if (isset($_SESSION['super_admin_id'])) {
    redirect('/admin/');
}

$error = '';

// ログイン処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'ユーザー名とパスワードを入力してください。';
    } else {
        try {
            $pdo = getPlatformDb();
            if (!$pdo) {
                $error = 'データベースに接続できません。';
            } else {
                $stmt = $pdo->prepare("SELECT * FROM super_admins WHERE username = ? AND is_active = 1");
                $stmt->execute([$username]);
                $admin = $stmt->fetch();
                
                if ($admin && password_verify($password, $admin['password_hash'])) {
                    // ログイン成功
                    $_SESSION['super_admin_id'] = $admin['id'];
                    $_SESSION['super_admin_name'] = $admin['name'];
                    $_SESSION['super_admin_username'] = $admin['username'];
                    
                    // 最終ログイン日時を更新
                    $updateStmt = $pdo->prepare("UPDATE super_admins SET last_login_at = NOW() WHERE id = ?");
                    $updateStmt->execute([$admin['id']]);
                    
                    redirect('/admin/');
                } else {
                    $error = 'ユーザー名またはパスワードが正しくありません。';
                }
            }
        } catch (PDOException $e) {
            $error = APP_DEBUG ? $e->getMessage() : 'システムエラーが発生しました。';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン | pullcass スーパー管理画面</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary: #e94560;
            --primary-dark: #d63a54;
            --text-dark: #1a1a2e;
            --text-medium: #4a5568;
            --text-light: #718096;
            --border-color: #e2e8f0;
            --bg-light: #f7fafc;
        }
        
        body {
            font-family: 'Noto Sans JP', sans-serif;
            background: var(--bg-light);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            width: 100%;
            max-width: 420px;
            padding: 20px;
        }
        
        .login-card {
            background: #fff;
            border-radius: 12px;
            padding: 50px 40px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo h1 {
            font-size: 2.5rem;
            font-weight: 900;
            color: var(--primary);
            letter-spacing: -1px;
        }
        
        .logo p {
            color: var(--text-light);
            font-size: 0.9rem;
            margin-top: 5px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-dark);
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
            color: var(--text-light);
        }
        
        .form-group input {
            width: 100%;
            padding: 14px 16px 14px 45px;
            font-size: 1rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            transition: all 0.2s ease;
            background: #fff;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(233, 69, 96, 0.1);
        }
        
        .btn-login {
            width: 100%;
            padding: 14px;
            font-size: 1rem;
            font-weight: 600;
            color: #fff;
            background: var(--primary);
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-login:hover {
            background: var(--primary-dark);
        }
        
        .error-message {
            background: #fef2f2;
            color: #dc2626;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            text-align: center;
            border: 1px solid #fca5a5;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .back-link {
            display: block;
            text-align: center;
            margin-top: 25px;
            color: var(--text-light);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.2s ease;
        }
        
        .back-link:hover {
            color: var(--primary);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="logo">
                <h1>pullcass</h1>
                <p>スーパー管理画面</p>
            </div>
            
            <?php if ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo h($error); ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">ユーザー名</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user"></i>
                        <input type="text" id="username" name="username" required 
                               value="<?php echo h($_POST['username'] ?? ''); ?>"
                               placeholder="admin">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">パスワード</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" required
                               placeholder="••••••••">
                    </div>
                </div>
                
                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> ログイン
                </button>
            </form>
            
            <a href="/" class="back-link">
                <i class="fas fa-arrow-left"></i> トップページに戻る
            </a>
        </div>
    </div>
</body>
</html>
