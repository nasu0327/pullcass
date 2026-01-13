<?php
/**
 * pullcass - スーパー管理画面
 * 店舗一覧
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

requireSuperAdminLogin();

// 店舗一覧を取得
try {
    $pdo = getPlatformDb();
    $stmt = $pdo->query("SELECT * FROM tenants ORDER BY created_at DESC");
    $tenants = $stmt->fetchAll();
} catch (PDOException $e) {
    $tenants = [];
}

$pageTitle = '店舗一覧';
include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h1>🏪 店舗一覧</h1>
</div>

<div class="content-section">
    <div class="section-header">
        <h2>登録店舗</h2>
        <a href="/admin/tenants/create.php" class="btn btn-primary">
            ➕ 新規店舗登録
        </a>
    </div>

    <?php if (empty($tenants)): ?>
    <div class="empty-state">
        <div class="empty-icon">🏪</div>
        <h3>店舗が登録されていません</h3>
        <p>「新規店舗登録」から最初の店舗を追加してください。</p>
    </div>
    <?php else: ?>
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>店舗名</th>
                    <th>スラッグ</th>
                    <th>ドメイン</th>
                    <th>DB名</th>
                    <th>ステータス</th>
                    <th>作成日</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tenants as $tenant): ?>
                <tr>
                    <td><?php echo $tenant['id']; ?></td>
                    <td><strong><?php echo h($tenant['name']); ?></strong></td>
                    <td><code><?php echo h($tenant['slug']); ?></code></td>
                    <td>
                        <?php if ($tenant['domain']): ?>
                            <a href="https://<?php echo h($tenant['domain']); ?>" target="_blank">
                                <?php echo h($tenant['domain']); ?>
                            </a>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td><code><?php echo h($tenant['db_name']); ?></code></td>
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
                        <span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span>
                    </td>
                    <td><?php echo date('Y/m/d H:i', strtotime($tenant['created_at'])); ?></td>
                    <td class="actions">
                        <a href="/admin/tenants/edit.php?id=<?php echo $tenant['id']; ?>" class="btn btn-sm btn-secondary">編集</a>
                        <a href="/admin/tenants/toggle.php?id=<?php echo $tenant['id']; ?>&csrf=<?php echo generateCsrfToken(); ?>" 
                           class="btn btn-sm btn-outline"
                           onclick="return confirm('ステータスを変更しますか？')">
                            <?php echo $tenant['status'] === 'active' ? '停止' : '有効化'; ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
