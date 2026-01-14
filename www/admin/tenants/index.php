<?php
/**
 * pullcass - マスター管理画面
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
    <h1><i class="fas fa-store"></i> 店舗一覧</h1>
</div>

<div class="content-section">
    <div class="section-header">
        <h2>登録店舗</h2>
        <a href="/admin/tenants/create.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> 新規店舗登録
        </a>
    </div>

    <?php if (empty($tenants)): ?>
    <div class="empty-state">
        <div class="empty-icon"><i class="fas fa-store"></i></div>
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
                    <th>サブドメイン</th>
                    <th>代理店</th>
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
                    <td>
                        <a href="https://<?php echo h($tenant['code']); ?>.pullcass.com" target="_blank" class="subdomain-link">
                            <code><?php echo h($tenant['code']); ?></code>
                        </a>
                    </td>
                    <td>
                        <?php if (!empty($tenant['agency_name'])): ?>
                            <span class="agency-info" title="担当: <?php echo h($tenant['agency_contact'] ?? '-'); ?> / <?php echo h($tenant['agency_phone'] ?? '-'); ?>">
                                <?php echo h($tenant['agency_name']); ?>
                            </span>
                        <?php else: ?>
                            <span class="text-muted">直接契約</span>
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
                    <td><?php echo date('Y/m/d H:i', strtotime($tenant['created_at'])); ?></td>
                    <td class="actions">
                        <a href="/admin/tenants/edit.php?id=<?php echo $tenant['id']; ?>" class="btn btn-sm btn-secondary">
                            <i class="fas fa-edit"></i> 編集
                        </a>
                        <a href="/admin/tenants/toggle.php?id=<?php echo $tenant['id']; ?>&csrf=<?php echo generateCsrfToken(); ?>" 
                           class="btn btn-sm btn-outline"
                           onclick="return confirm('ステータスを変更しますか？')">
                            <?php echo $tenant['is_active'] ? '<i class="fas fa-pause"></i> 停止' : '<i class="fas fa-play"></i> 有効化'; ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<style>
    .subdomain-link {
        color: var(--primary);
        text-decoration: none;
    }
    .subdomain-link:hover {
        text-decoration: underline;
    }
    .agency-info {
        cursor: help;
        border-bottom: 1px dotted var(--text-muted);
    }
</style>

<?php 
$flashSuccess = getFlash('success');
$flashError = getFlash('error');
?>
<?php if ($flashSuccess || $flashError): ?>
<style>
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
        from { opacity: 0; transform: scale(0.9) translateY(-20px); }
        to { opacity: 1; transform: scale(1) translateY(0); }
    }
    .success-modal-icon {
        font-size: 3rem;
        margin-bottom: 20px;
    }
    .success-modal-icon.success { color: #2ecc71; }
    .success-modal-icon.error { color: #e74c3c; }
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
    function showModal(message, isError) {
        const overlay = document.createElement('div');
        overlay.className = 'success-modal-overlay';
        const iconClass = isError ? 'error' : 'success';
        const icon = isError ? 'fa-exclamation-circle' : 'fa-check-circle';
        overlay.innerHTML = `
            <div class="success-modal">
                <div class="success-modal-icon ${iconClass}"><i class="fas ${icon}"></i></div>
                <div class="success-modal-message">${message}</div>
                <button class="success-modal-btn" onclick="closeModal()">OK</button>
            </div>
        `;
        document.body.appendChild(overlay);
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) closeModal();
        });
    }
    function closeModal() {
        const overlay = document.querySelector('.success-modal-overlay');
        if (overlay) {
            overlay.classList.add('closing');
            setTimeout(() => overlay.remove(), 200);
        }
    }
    window.addEventListener('DOMContentLoaded', function() {
        <?php if ($flashSuccess): ?>
        showModal('<?php echo addslashes($flashSuccess); ?>', false);
        <?php endif; ?>
        <?php if ($flashError): ?>
        showModal('<?php echo addslashes($flashError); ?>', true);
        <?php endif; ?>
    });
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
