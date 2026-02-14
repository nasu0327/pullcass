<?php
/**
 * pullcass - 予約管理（予約統計・予約一覧）
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

$pageTitle = '予約管理';

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

// 予約統計（予約管理用）
$stmtStats = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM tenant_reservations
    WHERE tenant_id = ?
");
$stmtStats->execute([$tenantId]);
$stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

// コース名の解決（メール通知・詳細ページと同様：ID→表示名）
$courseNames = [];
$courseIds = array_unique(array_filter(array_column($reservations, 'course'), function ($v) {
    return $v !== '' && $v !== null && is_numeric($v);
}));
if (!empty($courseIds) && $pdo) {
    try {
        $placeholders = implode(',', array_fill(0, count($courseIds), '?'));
        $stmtCourse = $pdo->prepare("
            SELECT pt.id, pt.table_name, pc.admin_title
            FROM price_tables_published pt
            LEFT JOIN price_contents_published pc ON pt.content_id = pc.id
            WHERE pt.id IN ($placeholders)
        ");
        $stmtCourse->execute(array_values($courseIds));
        while ($row = $stmtCourse->fetch(PDO::FETCH_ASSOC)) {
            $courseNames[$row['id']] = $row['table_name'] ?: $row['admin_title'] ?: (string)$row['id'];
        }
    } catch (Exception $e) {
        // 解決失敗時はスキップ
    }
}
foreach ($reservations as &$r) {
    $courseRaw = $r['course'] ?? '';
    if ($courseRaw === 'other') {
        $r['course_display'] = 'その他';
    } elseif (isset($courseNames[$courseRaw])) {
        $r['course_display'] = $courseNames[$courseRaw];
    } else {
        $r['course_display'] = $courseRaw ?: '-';
    }
}
unset($r);

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
    ['label' => '予約管理']
];
renderBreadcrumb($breadcrumbs);
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-list-alt"></i> <?php echo h($pageTitle); ?></h1>
        <p>予約統計と予約一覧の確認・管理ができます。（全<?php echo number_format($totalCount); ?>件）</p>
    </div>
    <a href="index?tenant=<?php echo h($tenantSlug); ?>" class="btn btn-secondary">
        <i class="fas fa-calendar-check"></i> 予約機能設定
    </a>
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

<!-- 予約統計 -->
<div class="content-card mb-4">
    <h5 class="mb-3"><i class="fas fa-chart-bar"></i> 予約統計</h5>
    <div class="d-flex flex-wrap gap-3" style="justify-content: center;">
        <div style="background: var(--bg-body); border-radius: 10px; padding: 20px 30px; text-align: center; min-width: 120px;">
            <div style="font-size: 2em; font-weight: bold; color: var(--text-primary);"><?php echo number_format($stats['total'] ?? 0); ?></div>
            <div style="color: var(--text-muted); font-size: 0.9em;">総予約数</div>
        </div>
        <div style="background: var(--warning-bg); border-radius: 10px; padding: 20px 30px; text-align: center; min-width: 120px;">
            <div style="font-size: 2em; font-weight: bold; color: var(--warning);"><?php echo number_format($stats['pending'] ?? 0); ?></div>
            <div style="color: var(--text-muted); font-size: 0.9em;">未確認</div>
        </div>
        <div style="background: var(--success-bg); border-radius: 10px; padding: 20px 30px; text-align: center; min-width: 120px;">
            <div style="font-size: 2em; font-weight: bold; color: var(--success);"><?php echo number_format($stats['confirmed'] ?? 0); ?></div>
            <div style="color: var(--text-muted); font-size: 0.9em;">確定済み</div>
        </div>
        <div style="background: var(--danger-bg); border-radius: 10px; padding: 20px 30px; text-align: center; min-width: 120px;">
            <div style="font-size: 2em; font-weight: bold; color: var(--danger);"><?php echo number_format($stats['cancelled'] ?? 0); ?></div>
            <div style="color: var(--text-muted); font-size: 0.9em;">キャンセル</div>
        </div>
    </div>
</div>

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
                        <a href="list?tenant=<?php echo h($tenantSlug); ?>" class="btn btn-secondary">
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
            <table class="table reservation-list-table" style="color: var(--text-primary); width: 100%;">
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
                            <small><?php echo h($r['course_display'] ?? $r['course'] ?? '-'); ?></small>
                        </td>
                        <td style="padding: 15px;">
                            <small><?php echo h(date('Y/m/d H:i', strtotime($r['created_at']))); ?></small>
                        </td>
                        <td style="padding: 15px;">
                            <a href="detail?tenant=<?php echo h($tenantSlug); ?>&id=<?php echo $r['id']; ?>" class="btn btn-sm btn-secondary">
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
            $baseUrl = 'list?' . http_build_query($queryParams);
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
    <a href="index?tenant=<?php echo h($tenantSlug); ?>" class="btn btn-secondary">
        <i class="fas fa-calendar-check"></i> 予約機能設定へ
    </a>
</div>

<style>
    /* 予約一覧：奇数行に背景色を適用（グレー始まり） */
    .reservation-list-table tbody tr:nth-child(odd) {
        background: var(--bg-body);
    }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
