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
    
    // 管理者アカウントを取得
    $stmt = $pdo->prepare("SELECT * FROM tenant_admins WHERE tenant_id = ? LIMIT 1");
    $stmt->execute([$tenantId]);
    $tenantAdmin = $stmt->fetch();
    
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
            $title = trim($_POST['title'] ?? '') ?: null;
            $description = trim($_POST['description'] ?? '') ?: null;
            $domain = trim($_POST['domain'] ?? '') ?: null;
            $phone = trim($_POST['phone'] ?? '') ?: null;
            $email = trim($_POST['email'] ?? '') ?: null;
            $businessHours = trim($_POST['business_hours'] ?? '') ?: null;
            $businessHoursNote = trim($_POST['business_hours_note'] ?? '') ?: null;
            $agencyName = trim($_POST['agency_name'] ?? '') ?: null;
            $agencyContact = trim($_POST['agency_contact'] ?? '') ?: null;
            $agencyPhone = trim($_POST['agency_phone'] ?? '') ?: null;
            
            if (empty($name)) {
                $errors[] = '店舗名を入力してください。';
            }
            if (empty($code)) {
                $errors[] = 'サブドメインを入力してください。';
            }
            
            // 画像アップロード処理
            $uploadDir = __DIR__ . '/../../uploads/tenants/' . $tenantId . '/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $logoLargeUrl = $tenant['logo_large_url'] ?? null;
            $logoSmallUrl = $tenant['logo_small_url'] ?? null;
            $faviconUrl = $tenant['favicon_url'] ?? null;
            
            // ロゴ画像（大）
            if (!empty($_FILES['logo_large']['name']) && $_FILES['logo_large']['error'] === UPLOAD_ERR_OK) {
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (in_array($_FILES['logo_large']['type'], $allowedTypes)) {
                    $ext = pathinfo($_FILES['logo_large']['name'], PATHINFO_EXTENSION);
                    $filename = 'logo_large_' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES['logo_large']['tmp_name'], $uploadDir . $filename)) {
                        $logoLargeUrl = '/uploads/tenants/' . $tenantId . '/' . $filename;
                    }
                } else {
                    $errors[] = 'ロゴ画像（大）は JPEG, PNG, GIF, WebP 形式のみ対応しています。';
                }
            }
            
            // ロゴ画像（小）
            if (!empty($_FILES['logo_small']['name']) && $_FILES['logo_small']['error'] === UPLOAD_ERR_OK) {
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (in_array($_FILES['logo_small']['type'], $allowedTypes)) {
                    $ext = pathinfo($_FILES['logo_small']['name'], PATHINFO_EXTENSION);
                    $filename = 'logo_small_' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES['logo_small']['tmp_name'], $uploadDir . $filename)) {
                        $logoSmallUrl = '/uploads/tenants/' . $tenantId . '/' . $filename;
                    }
                } else {
                    $errors[] = 'ロゴ画像（小）は JPEG, PNG, GIF, WebP 形式のみ対応しています。';
                }
            }
            
            // ファビコン
            if (!empty($_FILES['favicon']['name']) && $_FILES['favicon']['error'] === UPLOAD_ERR_OK) {
                $allowedTypes = ['image/x-icon', 'image/png', 'image/ico', 'image/vnd.microsoft.icon'];
                $allowedExt = ['ico', 'png'];
                $ext = strtolower(pathinfo($_FILES['favicon']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, $allowedExt)) {
                    $filename = 'favicon_' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES['favicon']['tmp_name'], $uploadDir . $filename)) {
                        $faviconUrl = '/uploads/tenants/' . $tenantId . '/' . $filename;
                    }
                } else {
                    $errors[] = 'ファビコンは ICO, PNG 形式のみ対応しています。';
                }
            }
            
            if (empty($errors)) {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE tenants 
                        SET name = ?, code = ?, title = ?, description = ?, domain = ?, phone = ?, email = ?, 
                            business_hours = ?, business_hours_note = ?,
                            logo_large_url = ?, logo_small_url = ?, favicon_url = ?,
                            agency_name = ?, agency_contact = ?, agency_phone = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$name, $code, $title, $description, $domain, $phone, $email, 
                                   $businessHours, $businessHoursNote,
                                   $logoLargeUrl, $logoSmallUrl, $faviconUrl, 
                                   $agencyName, $agencyContact, $agencyPhone, $tenantId]);
                    
                    setFlash('success', '店舗情報を更新しました。');
                    redirect('/admin/tenants/edit?id=' . $tenantId);
                } catch (PDOException $e) {
                    $errors[] = 'データベースエラーが発生しました。';
                }
            }
        } elseif ($action === 'update_admin') {
            // 管理者アカウント更新
            $adminUsername = trim($_POST['admin_username'] ?? '');
            $adminPassword = trim($_POST['admin_password'] ?? '');
            $adminName = trim($_POST['admin_name'] ?? '') ?: null;
            $adminEmail = trim($_POST['admin_email'] ?? '') ?: null;
            
            if (empty($adminUsername)) {
                $errors[] = 'ログインIDを入力してください。';
            }
            
            if (empty($errors)) {
                try {
                    if ($tenantAdmin) {
                        // 既存アカウントを更新
                        if (!empty($adminPassword)) {
                            $stmt = $pdo->prepare("
                                UPDATE tenant_admins 
                                SET username = ?, password_hash = ?, name = ?, email = ?
                                WHERE id = ?
                            ");
                            $stmt->execute([$adminUsername, password_hash($adminPassword, PASSWORD_DEFAULT), 
                                           $adminName, $adminEmail, $tenantAdmin['id']]);
                        } else {
                            $stmt = $pdo->prepare("
                                UPDATE tenant_admins 
                                SET username = ?, name = ?, email = ?
                                WHERE id = ?
                            ");
                            $stmt->execute([$adminUsername, $adminName, $adminEmail, $tenantAdmin['id']]);
                        }
                    } else {
                        // 新規アカウント作成
                        if (empty($adminPassword)) {
                            $errors[] = '新規作成時はパスワードを入力してください。';
                        } else {
                            $stmt = $pdo->prepare("
                                INSERT INTO tenant_admins (tenant_id, username, password_hash, name, email)
                                VALUES (?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([$tenantId, $adminUsername, password_hash($adminPassword, PASSWORD_DEFAULT),
                                           $adminName, $adminEmail]);
                        }
                    }
                    
                    if (empty($errors)) {
                        setFlash('success', '管理者アカウントを更新しました。');
                        redirect('/admin/tenants/edit?id=' . $tenantId);
                    }
                } catch (PDOException $e) {
                    $errors[] = 'データベースエラーが発生しました。';
                }
            }
        } elseif ($action === 'update_features') {
            // 機能設定更新
            $features = $_POST['features'] ?? [];
            
            try {
                // 写メ日記をOFFにする場合、店舗のトップページ編集の写メ日記表示も強制OFF（整合性維持）
                $diaryTurnedOff = !in_array('diary_scrape', $features);
                if ($diaryTurnedOff) {
                    $stmt = $pdo->prepare("UPDATE top_layout_sections SET is_visible = 0, mobile_visible = 0 WHERE tenant_id = ? AND section_key = 'diary'");
                    $stmt->execute([$tenantId]);
                    $stmt = $pdo->prepare("UPDATE top_layout_sections_published SET is_visible = 0, mobile_visible = 0 WHERE tenant_id = ? AND section_key = 'diary'");
                    $stmt->execute([$tenantId]);
                }

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
                
                setFlash('success', '機能設定を更新しました。' . ($diaryTurnedOff ? ' 写メ日記をOFFにしたため、トップページ編集の写メ日記表示もOFFにしました。' : ''));
                redirect('/admin/tenants/edit?id=' . $tenantId);
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

<!-- 店舗管理画面ログインURL -->
<?php $loginUrl = 'https://pullcass.com/app/manage/login.php?tenant=' . $tenant['code']; ?>
<div class="login-url-card">
    <div class="login-url-info">
        <div class="login-url-label"><i class="fas fa-sign-in-alt"></i> 店舗管理画面 ログインURL</div>
        <div class="login-url-text" id="loginUrl"><?php echo h($loginUrl); ?></div>
        <small class="login-url-help">このURLを店舗担当者に共有してください</small>
    </div>
    <button type="button" class="btn btn-accent" onclick="copyLoginUrl()">
        <i class="fas fa-copy"></i> URLをコピー
    </button>
</div>

<!-- 基本情報 -->
<div class="content-section">
    <div class="section-header">
        <h2><i class="fas fa-store"></i> 基本情報</h2>
    </div>
    
    <form method="POST" action="" class="form" enctype="multipart/form-data">
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
        
        <div class="form-section-title"><i class="fas fa-heading"></i> サイト表示設定</div>
        
        <div class="form-group">
            <label for="title"><i class="fas fa-heading"></i> 店舗タイトル</label>
            <textarea id="title" name="title" class="form-control" rows="2"
                      placeholder="例：関東最大級のデリヘル"><?php echo h($tenant['title'] ?? ''); ?></textarea>
            <small class="form-help">インデックスページのロゴ上に表示されます（改行可、空欄の場合は非表示）</small>
        </div>
        
        <div class="form-group">
            <label for="description"><i class="fas fa-align-left"></i> 店舗紹介文</label>
            <textarea id="description" name="description" class="form-control" rows="4"
                      placeholder="例：ビジュアルとホスピタリティーにこだわり、厳選された最上級のキャストであなたを魅惑の世界にエスコートします。"><?php echo h($tenant['description'] ?? ''); ?></textarea>
            <small class="form-help">インデックスページのENTER/LEAVEボタン下に表示されます（改行可、空欄の場合は非表示）</small>
        </div>
        
        <div class="form-section-title"><i class="fas fa-address-card"></i> 連絡先情報</div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="phone"><i class="fas fa-phone"></i> 電話番号</label>
                <input type="tel" id="phone" name="phone" class="form-control"
                       value="<?php echo h($tenant['phone'] ?? ''); ?>"
                       placeholder="例：092-xxx-xxxx">
            </div>
            
            <div class="form-group">
                <label for="email"><i class="fas fa-envelope"></i> メールアドレス</label>
                <input type="email" id="email" name="email" class="form-control"
                       value="<?php echo h($tenant['email'] ?? ''); ?>"
                       placeholder="例：info@shop.com">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="business_hours"><i class="fas fa-clock"></i> 営業時間</label>
                <input type="text" id="business_hours" name="business_hours" class="form-control"
                       value="<?php echo h($tenant['business_hours'] ?? ''); ?>"
                       placeholder="例：10:00〜LAST">
                <small class="form-help">トップページの固定フッターに表示されます</small>
            </div>
            
            <div class="form-group">
                <label for="business_hours_note"><i class="fas fa-comment"></i> 営業時間下テキスト</label>
                <input type="text" id="business_hours_note" name="business_hours_note" class="form-control"
                       value="<?php echo h($tenant['business_hours_note'] ?? ''); ?>"
                       placeholder="例：電話予約受付中！">
                <small class="form-help">営業時間の下に表示される補足テキスト</small>
            </div>
        </div>
        
        <div class="form-group">
            <label for="domain">カスタムドメイン</label>
            <input type="text" id="domain" name="domain" class="form-control"
                   value="<?php echo h($tenant['domain'] ?? ''); ?>"
                   placeholder="例：your-shop.com">
            <small class="form-help">独自ドメインを使用する場合に設定（DNS設定が必要）</small>
        </div>
        
        <div class="form-section-title"><i class="fas fa-image"></i> ロゴ・ファビコン</div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="logo_large"><i class="fas fa-image"></i> ロゴ画像（大）</label>
                <div class="file-upload-wrapper">
                    <input type="file" id="logo_large" name="logo_large" class="file-input" accept="image/*">
                    <label for="logo_large" class="file-upload-btn">
                        <i class="fas fa-cloud-upload-alt"></i> ファイルを選択
                    </label>
                    <span class="file-name" id="logo_large_name">選択されていません</span>
                </div>
                <?php if (!empty($tenant['logo_large_url'])): ?>
                <div class="current-image">
                    <img src="<?php echo h($tenant['logo_large_url']); ?>" alt="現在のロゴ（大）">
                    <span class="current-label">現在の画像</span>
                </div>
                <?php endif; ?>
                <small class="form-help">ヘッダー等に表示される大きいロゴ（推奨: 300x100px）</small>
            </div>
            
            <div class="form-group">
                <label for="logo_small"><i class="fas fa-image"></i> ロゴ画像（小） <span class="badge-info">正方形推奨</span></label>
                <div class="file-upload-wrapper">
                    <input type="file" id="logo_small" name="logo_small" class="file-input" accept="image/*">
                    <label for="logo_small" class="file-upload-btn">
                        <i class="fas fa-cloud-upload-alt"></i> ファイルを選択
                    </label>
                    <span class="file-name" id="logo_small_name">選択されていません</span>
                </div>
                <?php if (!empty($tenant['logo_small_url'])): ?>
                <div class="current-image current-image-small">
                    <img src="<?php echo h($tenant['logo_small_url']); ?>" alt="現在のロゴ（小）">
                    <span class="current-label">現在の画像</span>
                </div>
                <?php endif; ?>
                <small class="form-help">アイコン等に使用（推奨: 100x100px 正方形）</small>
            </div>
        </div>
        
        <div class="form-group" style="max-width: 400px;">
            <label for="favicon"><i class="fas fa-star"></i> ファビコン</label>
            <div class="file-upload-wrapper">
                <input type="file" id="favicon" name="favicon" class="file-input" accept=".ico,.png">
                <label for="favicon" class="file-upload-btn">
                    <i class="fas fa-cloud-upload-alt"></i> ファイルを選択
                </label>
                <span class="file-name" id="favicon_name">選択されていません</span>
            </div>
            <?php if (!empty($tenant['favicon_url'])): ?>
            <div class="current-image current-image-favicon">
                <img src="<?php echo h($tenant['favicon_url']); ?>" alt="現在のファビコン">
                <span class="current-label">現在のファビコン</span>
            </div>
            <?php endif; ?>
            <small class="form-help">ブラウザタブに表示（ICO または PNG、推奨: 32x32px）</small>
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

<!-- 管理者アカウント -->
<div class="content-section">
    <div class="section-header">
        <h2><i class="fas fa-user-shield"></i> 店舗管理画面 ログイン設定</h2>
    </div>
    
    <form method="POST" action="" class="form">
        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
        <input type="hidden" name="action" value="update_admin">
        
        <?php if ($tenantAdmin): ?>
        <div class="admin-status">
            <i class="fas fa-check-circle"></i> 管理者アカウント登録済み
            <?php if ($tenantAdmin['last_login_at']): ?>
            <span class="last-login">最終ログイン: <?php echo h($tenantAdmin['last_login_at']); ?></span>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="admin-status admin-status-warning">
            <i class="fas fa-exclamation-triangle"></i> 管理者アカウント未登録
        </div>
        <?php endif; ?>
        
        <div class="form-row">
            <div class="form-group">
                <label for="admin_username">ログインID <span class="required">*</span></label>
                <input type="text" id="admin_username" name="admin_username" class="form-control" required
                       value="<?php echo h($tenantAdmin['username'] ?? ''); ?>"
                       placeholder="例：admin">
            </div>
            
            <div class="form-group">
                <label for="admin_password">パスワード <?php if (!$tenantAdmin): ?><span class="required">*</span><?php endif; ?></label>
                <div class="password-input-wrapper">
                    <input type="password" id="admin_password" name="admin_password" class="form-control"
                           placeholder="<?php echo $tenantAdmin ? '変更する場合のみ入力' : '新規作成時は必須'; ?>">
                    <button type="button" class="password-toggle" onclick="togglePassword('admin_password')">
                        <i class="fas fa-eye" id="admin_password_icon"></i>
                    </button>
                </div>
                <small class="form-help"><?php echo $tenantAdmin ? '空欄の場合は変更しません' : '8文字以上を推奨'; ?></small>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="admin_name">管理者名</label>
                <input type="text" id="admin_name" name="admin_name" class="form-control"
                       value="<?php echo h($tenantAdmin['name'] ?? ''); ?>"
                       placeholder="例：店舗管理者">
            </div>
            
            <div class="form-group">
                <label for="admin_email">管理者メールアドレス</label>
                <input type="email" id="admin_email" name="admin_email" class="form-control"
                       value="<?php echo h($tenantAdmin['email'] ?? ''); ?>"
                       placeholder="例：admin@shop.com">
            </div>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> <?php echo $tenantAdmin ? '管理者情報を更新' : '管理者アカウントを作成'; ?>
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
    
    /* 店舗管理画面ログインURL */
    .login-url-card {
        background: var(--card-bg);
        border: 2px solid var(--accent);
        border-radius: 12px;
        padding: 20px 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
    }
    
    .login-url-info {
        flex: 1;
        min-width: 0;
    }
    
    .login-url-label {
        font-size: 0.9rem;
        color: var(--accent);
        font-weight: 600;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .login-url-text {
        font-size: 1rem;
        font-weight: 500;
        color: var(--text-light);
        background: rgba(39, 163, 235, 0.1);
        padding: 10px 15px;
        border-radius: 8px;
        word-break: break-all;
        font-family: 'Courier New', monospace;
        border: 1px solid rgba(39, 163, 235, 0.3);
    }
    
    .login-url-help {
        display: block;
        font-size: 0.8rem;
        color: var(--text-muted);
        margin-top: 8px;
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
    
    /* 管理者アカウントステータス */
    .admin-status {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 16px;
        border-radius: 8px;
        background: rgba(46, 204, 113, 0.15);
        color: #2ecc71;
        margin-bottom: 20px;
        font-weight: 500;
    }
    
    .admin-status .last-login {
        margin-left: auto;
        font-size: 0.85rem;
        opacity: 0.8;
    }
    
    .admin-status-warning {
        background: rgba(241, 196, 15, 0.15);
        color: #f1c40f;
    }
    
    /* ファイルアップロード */
    .file-upload-wrapper {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 10px;
    }
    
    .file-input {
        display: none;
    }
    
    .file-upload-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 18px;
        background: rgba(255, 255, 255, 0.1);
        border: 1px dashed rgba(255, 255, 255, 0.3);
        border-radius: 8px;
        color: var(--text-light);
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 0.9rem;
    }
    
    .file-upload-btn:hover {
        background: rgba(255, 255, 255, 0.15);
        border-color: var(--primary);
        color: var(--primary);
    }
    
    .file-name {
        color: var(--text-muted);
        font-size: 0.85rem;
    }
    
    .file-name.selected {
        color: var(--primary);
    }
    
    .current-image {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 10px;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        margin-bottom: 10px;
    }
    
    .current-image img {
        max-height: 50px;
        max-width: 150px;
        object-fit: contain;
        border-radius: 4px;
    }
    
    .current-image-small img {
        max-width: 50px;
        max-height: 50px;
    }
    
    .current-image-favicon img {
        max-width: 32px;
        max-height: 32px;
    }
    
    .current-label {
        font-size: 0.8rem;
        color: var(--text-muted);
    }
    
    .badge-info {
        display: inline-block;
        padding: 2px 8px;
        background: rgba(52, 152, 219, 0.2);
        color: #3498db;
        border-radius: 10px;
        font-size: 0.75rem;
        font-weight: 500;
        margin-left: 5px;
    }
    
    /* パスワード表示/非表示 */
    .password-input-wrapper {
        position: relative;
    }
    
    .password-input-wrapper .form-control {
        padding-right: 50px;
    }
    
    .password-toggle {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: var(--text-muted);
        cursor: pointer;
        padding: 5px;
        transition: color 0.2s ease;
    }
    
    .password-toggle:hover {
        color: var(--primary);
    }
    
    /* カスタム成功モーダル */
    .success-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(3px);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        animation: fadeIn 0.2s ease;
    }
    
    .success-modal-overlay.closing {
        animation: fadeOut 0.2s ease forwards;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    
    @keyframes fadeOut {
        from { opacity: 1; }
        to { opacity: 0; }
    }
    
    .success-modal {
        background: var(--card-bg);
        border: 1px solid var(--border-color);
        border-radius: 16px;
        padding: 40px 50px;
        text-align: center;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
        animation: modalSlideIn 0.3s ease;
        max-width: 400px;
    }
    
    @keyframes modalSlideIn {
        from { 
            opacity: 0;
            transform: scale(0.9) translateY(-20px);
        }
        to { 
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }
    
    .success-modal-icon {
        font-size: 3rem;
        color: #2ecc71;
        margin-bottom: 20px;
    }
    
    .success-modal-message {
        font-size: 1.1rem;
        color: var(--text-light);
        margin-bottom: 25px;
        line-height: 1.5;
    }
    
    .success-modal-btn {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: white;
        border: none;
        padding: 12px 40px;
        border-radius: 8px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    
    .success-modal-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 20px rgba(255, 107, 157, 0.4);
    }
</style>

<script>
    // ファイル選択時にファイル名を表示
    document.querySelectorAll('.file-input').forEach(function(input) {
        input.addEventListener('change', function() {
            const fileNameSpan = document.getElementById(this.id + '_name');
            if (this.files && this.files[0]) {
                fileNameSpan.textContent = this.files[0].name;
                fileNameSpan.classList.add('selected');
            } else {
                fileNameSpan.textContent = '選択されていません';
                fileNameSpan.classList.remove('selected');
            }
        });
    });
    
    // パスワード表示/非表示切り替え
    function togglePassword(inputId) {
        const input = document.getElementById(inputId);
        const icon = document.getElementById(inputId + '_icon');
        
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
    
    <?php 
    $flashSuccess = getFlash('success');
    if (!empty($flashSuccess)): ?>
    // 成功メッセージをカスタムモーダルで表示
    window.addEventListener('DOMContentLoaded', function() {
        showSuccessModal('<?php echo addslashes($flashSuccess); ?>');
    });
    <?php endif; ?>
    
    // カスタム成功モーダル
    function showSuccessModal(message) {
        const overlay = document.createElement('div');
        overlay.className = 'success-modal-overlay';
        overlay.innerHTML = `
            <div class="success-modal">
                <div class="success-modal-icon"><i class="fas fa-check-circle"></i></div>
                <div class="success-modal-message">${message}</div>
                <button class="success-modal-btn" onclick="closeSuccessModal()">OK</button>
            </div>
        `;
        document.body.appendChild(overlay);
        
        // オーバーレイクリックで閉じる
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) {
                closeSuccessModal();
            }
        });
    }
    
    function closeSuccessModal() {
        const overlay = document.querySelector('.success-modal-overlay');
        if (overlay) {
            overlay.classList.add('closing');
            setTimeout(() => overlay.remove(), 200);
        }
    }
    
    // ログインURLをクリップボードにコピー
    function copyLoginUrl() {
        const loginUrlText = document.getElementById('loginUrl').textContent;
        
        // クリップボードにコピー
        navigator.clipboard.writeText(loginUrlText).then(function() {
            // 成功時にモーダル表示
            showSuccessModal('ログインURLをコピーしました！<br><small style="opacity: 0.8; font-size: 0.9rem;">店舗担当者に共有してください</small>');
        }).catch(function(err) {
            // エラー時の代替処理（古いブラウザ対応）
            const textArea = document.createElement('textarea');
            textArea.value = loginUrlText;
            textArea.style.position = 'fixed';
            textArea.style.opacity = '0';
            document.body.appendChild(textArea);
            textArea.select();
            try {
                document.execCommand('copy');
                showSuccessModal('ログインURLをコピーしました！<br><small style="opacity: 0.8; font-size: 0.9rem;">店舗担当者に共有してください</small>');
            } catch (err) {
                alert('コピーに失敗しました。手動でコピーしてください。');
            }
            document.body.removeChild(textArea);
        });
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
