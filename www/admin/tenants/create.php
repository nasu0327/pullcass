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
        $slug = trim($_POST['slug'] ?? '');
        $domain = trim($_POST['domain'] ?? '') ?: null;
        
        // バリデーション
        if (empty($name)) {
            $errors[] = '店舗名を入力してください。';
        }
        
        if (empty($slug)) {
            $errors[] = 'スラッグを入力してください。';
        } elseif (!preg_match('/^[a-z0-9_-]+$/', $slug)) {
            $errors[] = 'スラッグは半角英数字、ハイフン、アンダースコアのみ使用できます。';
        }
        
        // 重複チェック
        if (empty($errors)) {
            try {
                $pdo = getPlatformDb();
                
                $stmt = $pdo->prepare("SELECT id FROM tenants WHERE slug = ?");
                $stmt->execute([$slug]);
                if ($stmt->fetch()) {
                    $errors[] = 'このスラッグは既に使用されています。';
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
                $dbName = 'pullcass_' . $slug;
                
                // テナントを登録
                $stmt = $pdo->prepare("
                    INSERT INTO tenants (name, slug, domain, db_name, status, settings)
                    VALUES (?, ?, ?, ?, 'active', '{}')
                ");
                $stmt->execute([$name, $slug, $domain, $dbName]);
                $tenantId = $pdo->lastInsertId();
                
                // テナント用データベースを作成
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                
                // テナントDBに必要なテーブルを作成
                $tenantPdo = getTenantDb($dbName);
                createTenantTables($tenantPdo);
                
                setFlash('success', "店舗「{$name}」を登録しました。");
                redirect('/admin/tenants/');
                
            } catch (PDOException $e) {
                $errors[] = APP_DEBUG ? $e->getMessage() : 'データベースエラーが発生しました。';
            }
        }
    }
}

/**
 * テナント用テーブルを作成
 */
function createTenantTables($pdo) {
    // 店舗管理者テーブル
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS admins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(100) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            name VARCHAR(100) DEFAULT NULL,
            role ENUM('owner', 'manager', 'staff') DEFAULT 'staff',
            is_active TINYINT(1) DEFAULT 1,
            last_login_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // キャストテーブル
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS casts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            name_kana VARCHAR(100) DEFAULT NULL,
            age TINYINT UNSIGNED DEFAULT NULL,
            height SMALLINT UNSIGNED DEFAULT NULL,
            bust SMALLINT UNSIGNED DEFAULT NULL,
            waist SMALLINT UNSIGNED DEFAULT NULL,
            hip SMALLINT UNSIGNED DEFAULT NULL,
            cup VARCHAR(5) DEFAULT NULL,
            blood_type ENUM('A', 'B', 'O', 'AB', '不明') DEFAULT '不明',
            profile_image VARCHAR(255) DEFAULT NULL,
            sub_images JSON DEFAULT NULL,
            catch_copy VARCHAR(255) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            status ENUM('active', 'inactive', 'retired') DEFAULT 'active',
            display_order INT DEFAULT 0,
            heaven_id VARCHAR(50) DEFAULT NULL COMMENT 'シティヘブンID',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // スケジュールテーブル
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS schedules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cast_id INT NOT NULL,
            work_date DATE NOT NULL,
            start_time TIME DEFAULT NULL,
            end_time TIME DEFAULT NULL,
            status ENUM('scheduled', 'working', 'finished', 'cancelled') DEFAULT 'scheduled',
            note VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_cast_date (cast_id, work_date),
            FOREIGN KEY (cast_id) REFERENCES casts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // 料金テーブル
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS prices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            course_name VARCHAR(100) NOT NULL,
            duration INT NOT NULL COMMENT '時間（分）',
            price INT NOT NULL COMMENT '料金（円）',
            description TEXT DEFAULT NULL,
            is_popular TINYINT(1) DEFAULT 0,
            display_order INT DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // テーマテーブル
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS themes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            theme_name VARCHAR(100) NOT NULL,
            status ENUM('draft', 'published') DEFAULT 'draft',
            theme_data JSON NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // 店舗設定テーブル
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT DEFAULT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // デフォルト設定を挿入
    $pdo->exec("
        INSERT INTO settings (setting_key, setting_value) VALUES
        ('shop_name', '店舗名'),
        ('phone', '000-0000-0000'),
        ('email', ''),
        ('open_time', '10:00'),
        ('close_time', '24:00'),
        ('area', ''),
        ('description', '')
        ON DUPLICATE KEY UPDATE setting_key = setting_key
    ");
    
    // デフォルトテーマを挿入
    $defaultTheme = json_encode([
        'colors' => [
            'primary' => '#e94560',
            'primary_light' => '#ff6b6b',
            'text' => '#333333',
            'btn_text' => '#ffffff',
            'bg' => '#ffffff',
            'overlay' => 'rgba(233, 69, 96, 0.2)'
        ],
        'fonts' => [
            'title1_en' => 'Poppins',
            'title1_ja' => 'Noto Sans JP',
            'title2_en' => 'Poppins',
            'title2_ja' => 'Noto Sans JP',
            'body_ja' => 'Noto Sans JP'
        ]
    ], JSON_UNESCAPED_UNICODE);
    
    $stmt = $pdo->prepare("INSERT INTO themes (theme_name, status, theme_data) VALUES ('デフォルト', 'published', ?)");
    $stmt->execute([$defaultTheme]);
}

$pageTitle = '新規店舗登録';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>➕ 新規店舗登録</h1>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
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
            <input type="text" id="name" name="name" required
                   value="<?php echo h($_POST['name'] ?? ''); ?>"
                   placeholder="例: 豊満倶楽部">
            <small class="form-help">お店の正式名称を入力してください</small>
        </div>
        
        <div class="form-group">
            <label for="slug">スラッグ（URL識別子） <span class="required">*</span></label>
            <input type="text" id="slug" name="slug" required
                   pattern="[a-z0-9_-]+"
                   value="<?php echo h($_POST['slug'] ?? ''); ?>"
                   placeholder="例: houman">
            <small class="form-help">
                半角英数字、ハイフン、アンダースコアのみ使用可能<br>
                → <code>houman.pullcass.com</code> のようにURLに使用されます
            </small>
        </div>
        
        <div class="form-group">
            <label for="domain">カスタムドメイン（任意）</label>
            <input type="text" id="domain" name="domain"
                   value="<?php echo h($_POST['domain'] ?? ''); ?>"
                   placeholder="例: club-houman.com">
            <small class="form-help">独自ドメインを使用する場合に設定（後から設定可能）</small>
        </div>
        
        <div class="form-actions">
            <a href="/admin/tenants/" class="btn btn-secondary">キャンセル</a>
            <button type="submit" class="btn btn-primary">
                🏪 店舗を登録
            </button>
        </div>
    </form>
</div>

<style>
    .form {
        max-width: 600px;
    }
    
    .form-group {
        margin-bottom: 25px;
    }
    
    .form-group label {
        display: block;
        font-weight: 600;
        margin-bottom: 8px;
        color: #333;
    }
    
    .form-group .required {
        color: var(--primary);
    }
    
    .form-group input {
        width: 100%;
        padding: 12px 15px;
        font-size: 1rem;
        border: 2px solid #e0e0e0;
        border-radius: 10px;
        transition: all 0.2s ease;
    }
    
    .form-group input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 4px rgba(233, 69, 96, 0.1);
    }
    
    .form-help {
        display: block;
        margin-top: 8px;
        font-size: 0.85rem;
        color: #666;
    }
    
    .form-actions {
        display: flex;
        gap: 15px;
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #eee;
    }
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
