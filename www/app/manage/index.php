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
        background: var(--bg-card);
        border-radius: 15px;
        padding: 25px;
        display: flex;
        align-items: center;
        gap: 20px;
        border: none;
        box-shadow: var(--shadow-card);
    }

    .stat-icon {
        font-size: 1.5rem;
        width: 50px;
        height: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: var(--primary-gradient);
        border-radius: 12px;
        color: var(--text-inverse);
    }

    .stat-value {
        display: block;
        font-size: 2rem;
        font-weight: 700;
        color: var(--text-primary);
    }

    .stat-label {
        font-size: 0.9rem;
        color: var(--text-muted);
    }

    /* セクションタイトル */
    .section-title {
        margin-bottom: 20px;
        color: var(--text-primary);
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
        background: var(--bg-card);
        border-radius: 15px;
        padding: 25px;
        text-decoration: none;
        color: var(--text-primary);
        border: none;
        box-shadow: var(--shadow-card);
        transition: all 0.3s ease;
    }

    .action-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-card-hover);
    }

    .action-icon {
        font-size: 1.2rem;
        margin-right: 10px;
        color: var(--primary);
    }

    .action-title {
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 5px;
        display: flex;
        align-items: center;
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
        <div class="action-title"><i class="fas fa-users-cog action-icon"></i>キャスト情報管理</div>
        <div class="action-desc">プロフィールの編集・削除</div>
    </a>
    <a href="/app/manage/cast_widgets/?tenant=<?php echo h($tenantSlug); ?>" class="action-card">
        <div class="action-title"><i class="fas fa-code action-icon"></i>ウィジェット登録</div>
        <div class="action-desc">写メ日記・口コミウィジェット</div>
    </a>
    <a href="/app/manage/schedules/?tenant=<?php echo h($tenantSlug); ?>" class="action-card">
        <div class="action-title"><i class="fas fa-calendar-alt action-icon"></i>スケジュール編集</div>
        <div class="action-desc">出勤スケジュールを管理</div>
    </a>
    <a href="/app/manage/themes/?tenant=<?php echo h($tenantSlug); ?>" class="action-card">
        <div class="action-title"><i class="fas fa-palette action-icon"></i>デザイン変更</div>
        <div class="action-desc">サイトのテーマを編集</div>
    </a>
    <a href="https://<?php echo h($tenant['code']); ?>.pullcass.com/app/front/top.php" class="action-card"
        target="_blank">
        <div class="action-title"><i class="fas fa-globe action-icon"></i>サイトを確認</div>
        <div class="action-desc">公開中のサイトを表示</div>
    </a>
</div>

</main>
</body>

</html>