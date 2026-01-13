<?php
/**
 * pullcass - スーパー管理画面
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
        <h1>📊 ダッシュボード</h1>
        <p class="subtitle">pullcass スーパー管理画面</p>
    </div>

    <?php if (isset($dbError)): ?>
    <div class="alert alert-warning">
        <strong>⚠️ 注意:</strong> <?php echo h($dbError); ?>
    </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">🏪</div>
            <div class="stat-content">
                <span class="stat-value"><?php echo count($tenants); ?></span>
                <span class="stat-label">登録店舗数</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">✅</div>
            <div class="stat-content">
                <span class="stat-value"><?php echo count(array_filter($tenants, fn($t) => $t['status'] === 'active')); ?></span>
                <span class="stat-label">稼働中</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🚧</div>
            <div class="stat-content">
                <span class="stat-value"><?php echo count(array_filter($tenants, fn($t) => $t['status'] === 'maintenance')); ?></span>
                <span class="stat-label">メンテナンス中</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">⏸️</div>
            <div class="stat-content">
                <span class="stat-value"><?php echo count(array_filter($tenants, fn($t) => $t['status'] === 'inactive')); ?></span>
                <span class="stat-label">停止中</span>
            </div>
        </div>
    </div>

    <div class="content-section">
        <div class="section-header">
            <h2>🏪 店舗一覧</h2>
            <a href="/admin/tenants/create.php" class="btn btn-primary">
                ➕ 新規店舗登録
            </a>
        </div>

        <?php if (empty($tenants)): ?>
        <div class="empty-state">
            <div class="empty-icon">🏪</div>
            <h3>店舗が登録されていません</h3>
            <p>「新規店舗登録」から最初の店舗を追加してください。</p>
            <a href="/admin/tenants/create.php" class="btn btn-primary">
                ➕ 最初の店舗を登録
            </a>
        </div>
        <?php else: ?>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>店舗名</th>
                        <th>スラッグ</th>
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
                            <code><?php echo h($tenant['slug']); ?></code>
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
                            <?php
                            $statusClass = match($tenant['status']) {
                                'active' => 'status-active',
                                'inactive' => 'status-inactive',
                                'maintenance' => 'status-maintenance',
                                default => ''
                            };
                            $statusLabel = match($tenant['status']) {
                                'active' => '稼働中',
                                'inactive' => '停止中',
                                'maintenance' => 'メンテナンス',
                                default => '不明'
                            };
                            ?>
                            <span class="status-badge <?php echo $statusClass; ?>">
                                <?php echo $statusLabel; ?>
                            </span>
                        </td>
                        <td><?php echo date('Y/m/d', strtotime($tenant['created_at'])); ?></td>
                        <td class="actions">
                            <a href="/admin/tenants/edit.php?id=<?php echo $tenant['id']; ?>" class="btn btn-sm btn-secondary">編集</a>
                            <a href="/app/manage/?tenant=<?php echo h($tenant['slug']); ?>" class="btn btn-sm btn-outline" target="_blank">管理画面</a>
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
