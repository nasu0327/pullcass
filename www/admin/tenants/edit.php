<?php
/**
 * pullcass - マスター管理画面
 * 店舗編集・機能設定
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

requireSuperAdminLogin();

$tenantId = $_GET['id'] ?? null;

if (!$tenantId) {
    setFlash('error', '店舗IDが指定されていません。');
    redirect('/admin/tenants/');
}

// 店舗情報を取得
try {
    $pdo = getPlatformDb();
    $stmt = $pdo->prepare("SELECT * FROM tenants WHERE id = ?");
    $stmt->execute([$tenantId]);
    $tenant = $stmt->fetch();
    
    if (!$tenant) {
        setFlash('error', '店舗が見つかりません。');
        redirect('/admin/tenants/');
    }
    
    // 機能設定を取得
    $stmt = $pdo->prepare("SELECT feature_code, is_enabled FROM tenant_features WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $featuresRaw = $stmt->fetchAll();
    $enabledFeatures = [];
    foreach ($featuresRaw as $f) {
        $enabledFeatures[$f['feature_code']] = $f['is_enabled'];
    }
    
} catch (PDOException $e) {
    setFlash('error', 'データベースエラーが発生しました。');
    redirect('/admin/tenants/');
}

// 課金機能一覧
$premiumFeatures = [
    'info_update' => [
        'name' => '情報更新',
        'features' => [
            'review_scrape' => ['name' => '口コミ更新', 'desc' => '口コミサイトから自動取得'],
            'diary_scrape' => ['name' => '写メ日記', 'desc' => '写メ日記を自動取得'],
        ]
    ],
    'cast_premium' => [
        'name' => 'キャスト管理（拡張）',
        'features' => [
            'cast_mypage' => ['name' => 'マイページ登録状況', 'desc' => 'キャストのマイページ連携'],
            'cast_proxy_login' => ['name' => 'キャスト代理ログイン', 'desc' => 'キャストとして代理操作'],
        ]
    ],
    'member' => [
        'name' => '会員管理',
        'features' => [
            'member_manage' => ['name' => '会員管理', 'desc' => '会員登録・管理機能'],
        ]
    ],
    'talk' => [
        'name' => 'トーク管理',
        'features' => [
            'talk_manage' => ['name' => 'トーク管理', 'desc' => 'チャット・メッセージ機能'],
        ]
    ],
    'analytics' => [
        'name' => 'アクセス解析',
        'features' => [
            'schedule_check' => ['name' => 'スケジュールチェック', 'desc' => 'スケジュール閲覧解析'],
        ]
    ],
];

$errors = [];
$success = false;

// フォーム送信処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = '不正なリクエストです。';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update_info') {
            // 基本情報更新
            $name = trim($_POST['name'] ?? '');
            $code = trim($_POST['code'] ?? '');
            $domain = trim($_POST['domain'] ?? '') ?: null;
            $agencyName = trim($_POST['agency_name'] ?? '') ?: null;
            $agencyContact = trim($_POST['agency_contact'] ?? '') ?: null;
            $agencyPhone = trim($_POST['agency_phone'] ?? '') ?: null;
            
            if (empty($name)) {
                $errors[] = '店舗名を入力してください。';
            }
            if (empty($code)) {
                $errors[] = 'サブドメインを入力してください。';
            }
            
            if (empty($errors)) {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE tenants 
                        SET name = ?, code = ?, domain = ?, agency_name = ?, agency_contact = ?, agency_phone = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$name, $code, $domain, $agencyName, $agencyContact, $agencyPhone, $tenantId]);
                    
                    // 更新後のデータを再取得
                    $stmt = $pdo->prepare("SELECT * FROM tenants WHERE id = ?");
                    $stmt->execute([$tenantId]);
                    $tenant = $stmt->fetch();
                    
                    setFlash('success', '店舗情報を更新しました。');
                } catch (PDOException $e) {
                    $errors[] = 'データベースエラーが発生しました。';
                }
            }
        } elseif ($action === 'update_features') {
            // 機能設定更新
            $features = $_POST['features'] ?? [];
            
            try {
                // 全機能を一旦削除
                $stmt = $pdo->prepare("DELETE FROM tenant_features WHERE tenant_id = ?");
                $stmt->execute([$tenantId]);
                
                // 有効な機能を登録
                $stmt = $pdo->prepare("
                    INSERT INTO tenant_features (tenant_id, feature_code, is_enabled, enabled_at)
                    VALUES (?, ?, 1, NOW())
                ");
                
                foreach ($features as $featureCode) {
                    $stmt->execute([$tenantId, $featureCode]);
                }
                
                // 更新後のデータを再取得
                $stmt = $pdo->prepare("SELECT feature_code, is_enabled FROM tenant_features WHERE tenant_id = ?");
                $stmt->execute([$tenantId]);
                $featuresRaw = $stmt->fetchAll();
                $enabledFeatures = [];
                foreach ($featuresRaw as $f) {
                    $enabledFeatures[$f['feature_code']] = $f['is_enabled'];
                }
                
                setFlash('success', '機能設定を更新しました。');
            } catch (PDOException $e) {
                $errors[] = 'データベースエラーが発生しました。';
            }
        }
    }
}

// サイトURL
$siteUrl = $tenant['domain'] 
    ? 'https://' . $tenant['domain'] 
    : 'https://' . $tenant['code'] . '.pullcass.com';

$pageTitle = '店舗編集: ' . $tenant['name'];
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1><i class="fas fa-edit"></i> 店舗編集</h1>
    <p class="subtitle"><?php echo h($tenant['name']); ?></p>
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

<?php if ($flash = getFlash('success')): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i>
    <?php echo h($flash); ?>
</div>
<?php endif; ?>

<!-- HP確認リンク -->
<div class="site-link-card">
    <div class="site-link-info">
        <div class="site-link-label"><i class="fas fa-globe"></i> 店舗サイト</div>
        <a href="<?php echo h($siteUrl); ?>" target="_blank" class="site-link-url">
            <?php echo h($siteUrl); ?> <i class="fas fa-external-link-alt"></i>
        </a>
    </div>
    <a href="<?php echo h($siteUrl); ?>" target="_blank" class="btn btn-primary">
        <i class="fas fa-eye"></i> サイトを確認
    </a>
</div>

<!-- 基本情報 -->
<div class="content-section">
    <div class="section-header">
        <h2><i class="fas fa-store"></i> 基本情報</h2>
    </div>
    
    <form method="POST" action="" class="form">
        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
        <input type="hidden" name="action" value="update_info">
        
        <div class="form-row">
            <div class="form-group">
                <label for="name">店舗名 <span class="required">*</span></label>
                <input type="text" id="name" name="name" class="form-control" required
                       value="<?php echo h($tenant['name']); ?>">
            </div>
            
            <div class="form-group">
                <label for="code">テスト用サブドメイン <span class="required">*</span></label>
                <input type="text" id="code" name="code" class="form-control" required
                       value="<?php echo h($tenant['code']); ?>">
                <small class="form-help"><?php echo h($tenant['code']); ?>.pullcass.com</small>
            </div>
        </div>
        
        <div class="form-group">
            <label for="domain">カスタムドメイン</label>
            <input type="text" id="domain" name="domain" class="form-control"
                   value="<?php echo h($tenant['domain'] ?? ''); ?>"
                   placeholder="例：your-shop.com">
            <small class="form-help">独自ドメインを使用する場合に設定（DNS設定が必要）</small>
        </div>
        
        <div class="form-section-title"><i class="fas fa-building"></i> 登録代理店情報</div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="agency_name">代理店会社名</label>
                <input type="text" id="agency_name" name="agency_name" class="form-control"
                       value="<?php echo h($tenant['agency_name'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="agency_contact">担当者名</label>
                <input type="text" id="agency_contact" name="agency_contact" class="form-control"
                       value="<?php echo h($tenant['agency_contact'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="agency_phone">担当者電話番号</label>
                <input type="tel" id="agency_phone" name="agency_phone" class="form-control"
                       value="<?php echo h($tenant['agency_phone'] ?? ''); ?>">
            </div>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> 基本情報を保存
            </button>
        </div>
    </form>
</div>

<!-- 課金機能設定 -->
<div class="content-section">
    <div class="section-header">
        <h2><i class="fas fa-puzzle-piece"></i> 追加機能（課金オプション）</h2>
    </div>
    
    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
        <input type="hidden" name="action" value="update_features">
        
        <div class="features-grid">
            <?php foreach ($premiumFeatures as $categoryKey => $category): ?>
            <div class="feature-category">
                <div class="feature-category-title"><?php echo h($category['name']); ?></div>
                
                <?php foreach ($category['features'] as $featureCode => $feature): ?>
                <label class="feature-item">
                    <input type="checkbox" name="features[]" value="<?php echo h($featureCode); ?>"
                           <?php echo isset($enabledFeatures[$featureCode]) && $enabledFeatures[$featureCode] ? 'checked' : ''; ?>>
                    <div class="feature-info">
                        <div class="feature-name"><?php echo h($feature['name']); ?></div>
                        <div class="feature-desc"><?php echo h($feature['desc']); ?></div>
                    </div>
                    <div class="feature-toggle">
                        <span class="toggle-label toggle-off">OFF</span>
                        <span class="toggle-label toggle-on">ON</span>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> 機能設定を保存
            </button>
        </div>
    </form>
</div>

<div class="form-actions" style="margin-top: 30px;">
    <a href="/admin/tenants/" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> 店舗一覧に戻る
    </a>
</div>

<style>
    .site-link-card {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        border-radius: 12px;
        padding: 20px 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
    }
    
    .site-link-label {
        font-size: 0.85rem;
        opacity: 0.9;
        margin-bottom: 5px;
    }
    
    .site-link-url {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text-light);
        text-decoration: none;
    }
    
    .site-link-url:hover {
        text-decoration: underline;
    }
    
    .form {
        max-width: 800px;
    }
    
    .form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
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
        margin: 30px 0 20px;
        padding-bottom: 10px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .form-actions {
        display: flex;
        gap: 15px;
        margin-top: 25px;
        padding-top: 20px;
        border-top: 1px solid var(--border-color);
    }
    
    /* 機能設定 */
    .features-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
    }
    
    .feature-category {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 20px;
    }
    
    .feature-category-title {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-light);
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 1px solid var(--border-color);
    }
    
    .feature-item {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 12px;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s ease;
        margin-bottom: 8px;
    }
    
    .feature-item:hover {
        background: rgba(255, 255, 255, 0.05);
    }
    
    .feature-item input[type="checkbox"] {
        display: none;
    }
    
    .feature-info {
        flex: 1;
    }
    
    .feature-name {
        font-weight: 500;
        color: var(--text-light);
        margin-bottom: 3px;
    }
    
    .feature-desc {
        font-size: 0.8rem;
        color: var(--text-muted);
    }
    
    .feature-toggle {
        display: flex;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        padding: 4px;
        font-size: 0.75rem;
        font-weight: 600;
    }
    
    .toggle-label {
        padding: 4px 12px;
        border-radius: 16px;
        transition: all 0.2s ease;
    }
    
    .toggle-off {
        color: var(--text-muted);
    }
    
    .toggle-on {
        color: var(--text-muted);
    }
    
    .feature-item input:checked ~ .feature-toggle .toggle-on {
        background: var(--primary);
        color: var(--text-light);
    }
    
    .feature-item input:not(:checked) ~ .feature-toggle .toggle-off {
        background: rgba(255, 255, 255, 0.2);
        color: var(--text-light);
    }
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>
