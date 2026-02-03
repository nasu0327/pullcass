<?php
/**
 * pullcass - 予約一覧ページ
 */

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

// ログイン認証
requireTenantAdminLogin();

$pdo = getPlatformDb();
$tenantSlug = $_GET['tenant'] ?? $_SESSION['manage_tenant_slug'] ?? null;
if (!$tenantSlug) {
    header('Location: /');
    exit;
}

$pageTitle = '予約一覧';

$success = $_GET['success'] ?? null;
$error = $_GET['error'] ?? null;

// フィルター
$statusFilter = $_GET['status'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$keyword = $_GET['keyword'] ?? '';

// ページネーション
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// 予約一覧を取得
$sql = "
    SELECT r.*, c.name as cast_name
    FROM tenant_reservations r
    LEFT JOIN tenant_casts c ON r.cast_id = c.id
    WHERE r.tenant_id = ?
";
$params = [$tenantId];

if ($statusFilter) {
    $sql .= " AND r.status = ?";
    $params[] = $statusFilter;
}

if ($dateFrom) {
    $sql .= " AND r.reservation_date >= ?";
    $params[] = $dateFrom;
}

if ($dateTo) {
    $sql .= " AND r.reservation_date <= ?";
    $params[] = $dateTo;
}

if ($keyword) {
    $sql .= " AND (r.customer_name LIKE ? OR r.customer_phone LIKE ? OR r.customer_email LIKE ?)";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
}

// 総件数を取得
$countSql = str_replace("SELECT r.*, c.name as cast_name", "SELECT COUNT(*)", $sql);
$countSql = str_replace("LEFT JOIN tenant_casts c ON r.cast_id = c.id", "", $countSql);
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalCount = $stmt->fetchColumn();
$totalPages = ceil($totalCount / $perPage);

// データ取得
$sql .= " ORDER BY r.created_at DESC LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ステータス情報
$statusColors = [
    'new' => '#ffc107',
    'confirmed' => '#28a745',
    'completed' => '#17a2b8',
    'cancelled' => '#dc3545'
];
$statusLabels = [
    'new' => '新規',
    'confirmed' => '確認済み',
    'completed' => '完了',
    'cancelled' => 'キャンセル'
];

require_once __DIR__ . '/../includes/header.php';
?>
<?php
require_once __DIR__ . '/../includes/breadcrumb.php';
$breadcrumbs = [
    ['label' => 'ダッシュボード', 'url' => '/app/manage/?tenant=' . $tenantSlug, 'icon' => 'fas fa-chart-pie'],
    ['label' => '予約機能管理', 'url' => '/app/manage/reservation_management/?tenant=' . $tenantSlug],
    ['label' => '予約一覧']
];
renderBreadcrumb($breadcrumbs);
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-list"></i> <?php echo h($pageTitle); ?></h1>
        <p>すべての予約を確認・管理できます。（全<?php echo number_format($totalCount); ?>件）</p>
    </div>
</div>

<!-- メッセージ表示 -->
<?php if ($success): ?>
<script>
    alert('<?php echo h($success); ?>');
</script>
<?php endif; ?>
<?php if ($error): ?>
<script>
    alert('<?php echo h($error); ?>');
</script>
<?php endif; ?>

<!-- 検索・フィルター -->
<div class="content-card mb-4">
    <h5 class="mb-3"><i class="fas fa-search"></i> 検索・フィルター</h5>
    <form method="get" action="">
        <input type="hidden" name="tenant" value="<?php echo h($tenantSlug); ?>">
        <div class="row">
            <div class="col-md-3">
                <div class="form-group mb-3">
                    <label class="form-label">ステータス</label>
                    <select name="status" class="form-control">
                        <option value="">すべて</option>
                        <option value="new" <?php echo $statusFilter === 'new' ? 'selected' : ''; ?>>新規</option>
                        <option value="confirmed" <?php echo $statusFilter === 'confirmed' ? 'selected' : ''; ?>>確認済み</option>
                        <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>完了</option>
                        <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>キャンセル</option>
                    </select>
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group mb-3">
                    <label class="form-label">予約日（から）</label>
                    <input type="date" name="date_from" class="form-control" value="<?php echo h($dateFrom); ?>">
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group mb-3">
                    <label class="form-label">予約日（まで）</label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo h($dateTo); ?>">
                </div>
            </div>
            <div class="col-md-3">
                <div class="form-group mb-3">
                    <label class="form-label">キーワード</label>
                    <input type="text" name="keyword" class="form-control" value="<?php echo h($keyword); ?>" placeholder="名前・電話番号・メール">
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group mb-3">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> 検索
                        </button>
                        <a href="list.php?tenant=<?php echo h($tenantSlug); ?>" class="btn btn-secondary">
                            リセット
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- 予約一覧 -->
<div class="content-card">
    <?php if (empty($reservations)): ?>
        <div style="text-align: center; padding: 40px; color: var(--text-muted);">
            <i class="fas fa-inbox" style="font-size: 3em; margin-bottom: 15px;"></i>
            <p>条件に一致する予約がありません。</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table" style="color: var(--text-light); width: 100%;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <th style="padding: 15px;">ID</th>
                        <th style="padding: 15px;">状態</th>
                        <th style="padding: 15px;">お客様名</th>
                        <th style="padding: 15px;">電話番号</th>
                        <th style="padding: 15px;">予約日時</th>
                        <th style="padding: 15px;">キャスト</th>
                        <th style="padding: 15px;">コース</th>
                        <th style="padding: 15px;">受付日時</th>
                        <th style="padding: 15px;">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reservations as $r): ?>
                    <?php $status = $r['status'] ?? 'new'; ?>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 15px;">#<?php echo h($r['id']); ?></td>
                        <td style="padding: 15px;">
                            <span style="background: <?php echo $statusColors[$status] ?? '#6c757d'; ?>; color: white; padding: 3px 10px; border-radius: 15px; font-size: 0.8em;">
                                <?php echo h($statusLabels[$status] ?? $status); ?>
                            </span>
                        </td>
                        <td style="padding: 15px;">
                            <strong><?php echo h($r['customer_name']); ?></strong>
                        </td>
                        <td style="padding: 15px;">
                            <a href="tel:<?php echo h(preg_replace('/[^0-9]/', '', $r['customer_phone'])); ?>" style="color: var(--accent);">
                                <?php echo h($r['customer_phone']); ?>
                            </a>
                        </td>
                        <td style="padding: 15px;">
                            <?php echo h($r['reservation_date']); ?><br>
                            <small style="color: var(--text-muted);"><?php echo h($r['reservation_time']); ?></small>
                        </td>
                        <td style="padding: 15px;">
                            <?php if ($r['nomination_type'] === 'shimei' && $r['cast_name']): ?>
                                <?php echo h($r['cast_name']); ?>
                            <?php else: ?>
                                <span style="color: var(--text-muted);">フリー</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 15px;">
                            <small><?php echo h($r['course'] ?: '-'); ?></small>
                        </td>
                        <td style="padding: 15px;">
                            <small><?php echo h(date('Y/m/d H:i', strtotime($r['created_at']))); ?></small>
                        </td>
                        <td style="padding: 15px;">
                            <a href="detail.php?tenant=<?php echo h($tenantSlug); ?>&id=<?php echo $r['id']; ?>" class="btn btn-sm btn-secondary">
                                <i class="fas fa-eye"></i> 詳細
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- ページネーション -->
        <?php if ($totalPages > 1): ?>
        <div style="display: flex; justify-content: center; margin-top: 20px; gap: 5px;">
            <?php
            $queryParams = $_GET;
            unset($queryParams['page']);
            $baseUrl = 'list.php?' . http_build_query($queryParams);
            ?>
            
            <?php if ($page > 1): ?>
            <a href="<?php echo h($baseUrl . '&page=' . ($page - 1)); ?>" class="btn btn-sm btn-secondary">
                <i class="fas fa-chevron-left"></i>
            </a>
            <?php endif; ?>
            
            <?php
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            ?>
            
            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
            <a href="<?php echo h($baseUrl . '&page=' . $i); ?>" 
               class="btn btn-sm <?php echo $i === $page ? 'btn-primary' : 'btn-secondary'; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
            
            <?php if ($page < $totalPages): ?>
            <a href="<?php echo h($baseUrl . '&page=' . ($page + 1)); ?>" class="btn btn-sm btn-secondary">
                <i class="fas fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<div style="margin-top: 30px; text-align: center;">
    <a href="index.php?tenant=<?php echo h($tenantSlug); ?>" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> 予約管理に戻る
    </a>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
