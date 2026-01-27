<?php
/**
 * pullcass - 店舗管理画面
 * ダッシュボード
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';

// ログイン認証チェック（テナント未指定時はスキップ）
$tenantSlug = $_GET['tenant'] ?? $_SESSION['manage_tenant_slug'] ?? null;

if (!$tenantSlug) {
    // テナントが指定されていない場合はトップページにリダイレクト
    header('Location: https://pullcass.com/');
    exit;
}

// ログイン認証チェック
requireTenantAdminLogin();

// テナント情報を取得
try {
    $pdo = getPlatformDb();
    $stmt = $pdo->prepare("SELECT * FROM tenants WHERE code = ? AND is_active = 1");
    $stmt->execute([$tenantSlug]);
    $tenant = $stmt->fetch();

    if (!$tenant) {
        die("店舗が見つかりません。");
    }

    // セッションに保存
    $_SESSION['manage_tenant_slug'] = $tenantSlug;
    $_SESSION['manage_tenant'] = $tenant;

    // 統計情報（現時点ではダミー値）
    $castCount = 0;
    $todaySchedule = 0;

} catch (PDOException $e) {
    die("データベースエラー: " . (APP_DEBUG ? $e->getMessage() : 'システムエラーが発生しました'));
}

$shopName = $tenant['name'];
$tenantId = $tenant['id'];
$pageTitle = 'ダッシュボード';

// 共通ヘッダーを読み込む
require_once __DIR__ . '/includes/header.php';
?>

<style>
    /* 統計カード */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: var(--card-bg);
        border-radius: 15px;
        padding: 25px;
        display: flex;
        align-items: center;
        gap: 20px;
        border: 1px solid var(--border-color);
    }

    .stat-icon {
        font-size: 1.5rem;
        width: 50px;
        height: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        border-radius: 12px;
        color: var(--text-light);
    }

    .stat-value {
        display: block;
        font-size: 2rem;
        font-weight: 700;
        color: var(--text-light);
    }

    .stat-label {
        font-size: 0.9rem;
        color: var(--text-muted);
    }

    /* セクションタイトル */
    .section-title {
        margin-bottom: 20px;
        color: var(--text-light);
        font-size: 1.2rem;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    /* クイックアクション */
    .quick-actions {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }

    .action-card {
        background: var(--card-bg);
        border-radius: 15px;
        padding: 25px;
        text-decoration: none;
        color: var(--text-light);
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
    }

    .action-card:hover {
        transform: translateY(-5px);
        border-color: var(--primary);
        box-shadow: 0 8px 25px rgba(255, 107, 157, 0.2);
    }

    .action-icon {
        font-size: 2rem;
        margin-bottom: 15px;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .action-title {
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 5px;
    }

    .action-desc {
        font-size: 0.9rem;
        color: var(--text-muted);
    }
</style>

<div class="page-header">
    <h1><i class="fas fa-chart-pie"></i> <?php echo h($pageTitle); ?></h1>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-user"></i></div>
        <div>
            <span class="stat-value"><?php echo $castCount; ?></span>
            <span class="stat-label">在籍キャスト</span>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
        <div>
            <span class="stat-value"><?php echo $todaySchedule; ?></span>
            <span class="stat-label">本日の出勤</span>
        </div>
    </div>
</div>

<h2 class="section-title"><i class="fas fa-bolt"></i> クイックアクション</h2>

<div class="quick-actions">
    <a href="/app/manage/cast_info_management/?tenant=<?php echo h($tenantSlug); ?>" class="action-card">
        <div class="action-icon"><i class="fas fa-users-cog"></i></div>
        <div class="action-title">キャスト情報管理</div>
        <div class="action-desc">プロフィールの編集・ウィジェット設定</div>
    </a>
    <a href="/app/manage/schedules/?tenant=<?php echo h($tenantSlug); ?>" class="action-card">
        <div class="action-icon"><i class="fas fa-calendar-alt"></i></div>
        <div class="action-title">スケジュール編集</div>
        <div class="action-desc">出勤スケジュールを管理</div>
    </a>
    <a href="/app/manage/themes/?tenant=<?php echo h($tenantSlug); ?>" class="action-card">
        <div class="action-icon"><i class="fas fa-palette"></i></div>
        <div class="action-title">デザイン変更</div>
        <div class="action-desc">サイトのテーマを編集</div>
    </a>
    <a href="https://<?php echo h($tenant['code']); ?>.pullcass.com/app/front/top.php" class="action-card"
        target="_blank">
        <div class="action-icon"><i class="fas fa-globe"></i></div>
        <div class="action-title">サイトを確認</div>
        <div class="action-desc">公開中のサイトを表示</div>
    </a>
</div>

</main>
</body>

</html>