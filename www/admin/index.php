<?php
/**
 * pullcass - マスター管理画面
 * ダッシュボード
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';

// 認証チェック
requireSuperAdminLogin();

// テナント一覧を取得
try {
    $pdo = getPlatformDb();
    $stmt = $pdo->query("SELECT * FROM tenants ORDER BY created_at DESC");
    $tenants = $stmt->fetchAll();
} catch (PDOException $e) {
    $tenants = [];
    $dbError = APP_DEBUG ? $e->getMessage() : 'データベースに接続できません';
}

$pageTitle = 'ダッシュボード';
include __DIR__ . '/includes/header.php';
?>

<div class="dashboard">
    <div class="page-header">
        <h1><i class="fas fa-chart-pie"></i> ダッシュボード</h1>
        <p class="subtitle">pullcass マスター管理画面</p>
    </div>

    <?php if (isset($dbError)): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle"></i>
        <strong>注意:</strong> <?php echo h($dbError); ?>
    </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-store"></i></div>
            <div class="stat-content">
                <span class="stat-value"><?php echo count($tenants); ?></span>
                <span class="stat-label">登録店舗数</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-content">
                <span class="stat-value"><?php echo count(array_filter($tenants, fn($t) => ($t['is_active'] ?? 0) == 1)); ?></span>
                <span class="stat-label">稼働中</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-pause-circle"></i></div>
            <div class="stat-content">
                <span class="stat-value"><?php echo count(array_filter($tenants, fn($t) => ($t['is_active'] ?? 0) == 0)); ?></span>
                <span class="stat-label">停止中</span>
            </div>
        </div>
    </div>

    <div class="content-section">
        <div class="section-header">
            <h2><i class="fas fa-store"></i> 店舗一覧</h2>
            <a href="/admin/tenants/create.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> 新規店舗登録
            </a>
        </div>

        <?php if (empty($tenants)): ?>
        <div class="empty-state">
            <div class="empty-icon"><i class="fas fa-store"></i></div>
            <h3>店舗が登録されていません</h3>
            <p>「新規店舗登録」から最初の店舗を追加してください。</p>
            <a href="/admin/tenants/create.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> 最初の店舗を登録
            </a>
        </div>
        <?php else: ?>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>店舗名</th>
                        <th>コード</th>
                        <th>ドメイン</th>
                        <th>ステータス</th>
                        <th>作成日</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tenants as $tenant): ?>
                    <tr>
                        <td class="tenant-name">
                            <strong><?php echo h($tenant['name']); ?></strong>
                        </td>
                        <td>
                            <code><?php echo h($tenant['code']); ?></code>
                        </td>
                        <td>
                            <?php if ($tenant['domain']): ?>
                                <a href="https://<?php echo h($tenant['domain']); ?>" target="_blank">
                                    <?php echo h($tenant['domain']); ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">未設定</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($tenant['is_active']): ?>
                                <span class="status-badge status-active">
                                    <i class="fas fa-check"></i> 稼働中
                                </span>
                            <?php else: ?>
                                <span class="status-badge status-inactive">
                                    <i class="fas fa-pause"></i> 停止中
                                </span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('Y/m/d', strtotime($tenant['created_at'])); ?></td>
                        <td class="actions">
                            <a href="/admin/tenants/edit.php?id=<?php echo $tenant['id']; ?>" class="btn btn-sm btn-secondary">
                                <i class="fas fa-edit"></i> 編集
                            </a>
                            <a href="/app/manage/?tenant=<?php echo h($tenant['code']); ?>" class="btn btn-sm btn-outline" target="_blank">
                                <i class="fas fa-external-link-alt"></i> 店舗管理画面
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
