<?php
/**
 * pullcass - 予約詳細ページ
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

$pageTitle = '予約詳細';

// 予約IDを取得
$reservationId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$reservationId) {
    header('Location: index.php?tenant=' . rawurlencode($tenantSlug) . '&error=' . rawurlencode('予約IDが指定されていません。'));
    exit;
}

// 予約データを取得
$stmt = $pdo->prepare("
    SELECT r.*, c.name as cast_name, c.img1 as cast_img
    FROM tenant_reservations r
    LEFT JOIN tenant_casts c ON r.cast_id = c.id
    WHERE r.id = ? AND r.tenant_id = ?
");
$stmt->execute([$reservationId, $tenantId]);
$reservation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reservation) {
    header('Location: index.php?tenant=' . rawurlencode($tenantSlug) . '&error=' . rawurlencode('予約が見つかりません。'));
    exit;
}

// コース名の解決（メール通知と同様：ID→表示名）
$courseRaw = $reservation['course'] ?? '';
$courseDisplayName = $courseRaw ?: '未選択';
if ($courseRaw && $pdo) {
    try {
        if ($courseRaw === 'other') {
            $courseDisplayName = 'その他';
        } elseif (is_numeric($courseRaw)) {
            $stmtCourse = $pdo->prepare("
                SELECT pt.table_name, pc.admin_title
                FROM price_tables_published pt
                LEFT JOIN price_contents_published pc ON pt.content_id = pc.id
                WHERE pt.id = ?
            ");
            $stmtCourse->execute([$courseRaw]);
            $row = $stmtCourse->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $courseDisplayName = $row['table_name'] ?: $row['admin_title'] ?: $courseDisplayName;
            }
        }
    } catch (Exception $e) {
        // 解決失敗時はそのまま
    }
}
$reservation['course_display'] = $courseDisplayName;

// 施設の表示（facility_type + facility_detail から構築、メール通知と同様）
$facilityDetail = $reservation['facility_detail'] ?? '';
$facilityType = $reservation['facility_type'] ?? 'home';
$facilityTypeText = ($facilityType === 'hotel') ? 'ホテル' : '自宅';
$reservation['facility_display'] = $facilityDetail ?: $facilityTypeText;

// ステータス更新処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $newStatus = null;
    
    switch ($action) {
        case 'confirm':
            $newStatus = 'confirmed';
            break;
        case 'complete':
            $newStatus = 'completed';
            break;
        case 'cancel':
            $newStatus = 'cancelled';
            break;
        case 'new':
            $newStatus = 'new';
            break;
    }
    
    if ($newStatus) {
        try {
            $stmt = $pdo->prepare("UPDATE tenant_reservations SET status = ? WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$newStatus, $reservationId, $tenantId]);
            
            header('Location: detail.php?tenant=' . rawurlencode($tenantSlug) . '&id=' . $reservationId . '&success=' . rawurlencode('ステータスを更新しました。'));
            exit;
        } catch (PDOException $e) {
            $error = '更新エラー: ' . $e->getMessage();
        }
    }
}

// 削除処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM tenant_reservations WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$reservationId, $tenantId]);
        
        header('Location: index.php?tenant=' . rawurlencode($tenantSlug) . '&success=' . rawurlencode('予約を削除しました。'));
        exit;
    } catch (PDOException $e) {
        $error = '削除エラー: ' . $e->getMessage();
    }
}

$success = $_GET['success'] ?? null;
$error = $error ?? ($_GET['error'] ?? null);

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
$status = $reservation['status'] ?? 'new';

require_once __DIR__ . '/../includes/header.php';
?>
<?php
require_once __DIR__ . '/../includes/breadcrumb.php';
$breadcrumbs = [
    ['label' => 'ダッシュボード', 'url' => '/app/manage/?tenant=' . $tenantSlug, 'icon' => 'fas fa-chart-pie'],
    ['label' => '予約機能管理', 'url' => '/app/manage/reservation_management/?tenant=' . $tenantSlug],
    ['label' => '予約詳細 #' . $reservationId]
];
renderBreadcrumb($breadcrumbs);
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-calendar-check"></i> <?php echo h($pageTitle); ?> #<?php echo h($reservationId); ?></h1>
        <p>予約の詳細情報と状態管理ができます。</p>
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

<div class="row">
    <!-- 左カラム: 予約情報 -->
    <div class="col-md-8">
        <!-- ステータス -->
        <div class="content-card mb-4">
            <h5 class="mb-3"><i class="fas fa-flag"></i> 予約ステータス</h5>
            <div style="display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
                <div>
                    <span style="background: <?php echo $statusColors[$status]; ?>; color: white; padding: 8px 20px; border-radius: 20px; font-size: 1.1em; font-weight: bold;">
                        <?php echo h($statusLabels[$status]); ?>
                    </span>
                </div>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <?php if ($status !== 'confirmed'): ?>
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="action" value="confirm">
                        <button type="submit" class="btn btn-success btn-sm">
                            <i class="fas fa-check"></i> 確認済みにする
                        </button>
                    </form>
                    <?php endif; ?>
                    
                    <?php if ($status !== 'completed'): ?>
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="action" value="complete">
                        <button type="submit" class="btn btn-info btn-sm">
                            <i class="fas fa-check-double"></i> 完了にする
                        </button>
                    </form>
                    <?php endif; ?>
                    
                    <?php if ($status !== 'new'): ?>
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="action" value="new">
                        <button type="submit" class="btn btn-warning btn-sm">
                            <i class="fas fa-clock"></i> 新規に戻す
                        </button>
                    </form>
                    <?php endif; ?>
                    
                    <?php if ($status !== 'cancelled'): ?>
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="action" value="cancel">
                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('キャンセルにしますか？');">
                            <i class="fas fa-times"></i> キャンセル
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- お客様情報 -->
        <div class="content-card mb-4">
            <h5 class="mb-3"><i class="fas fa-user"></i> お客様情報</h5>
            <table class="table" style="color: var(--text-primary);">
                <tr>
                    <th style="width: 150px; padding: 12px; border-bottom: 1px solid var(--border-color);">お名前</th>
                    <td style="padding: 12px; border-bottom: 1px solid var(--border-color);">
                        <strong style="font-size: 1.1em;"><?php echo h($reservation['customer_name']); ?></strong>
                    </td>
                </tr>
                <tr>
                    <th style="padding: 12px; border-bottom: 1px solid var(--border-color);">電話番号</th>
                    <td style="padding: 12px; border-bottom: 1px solid var(--border-color);">
                        <a href="tel:<?php echo h(preg_replace('/[^0-9]/', '', $reservation['customer_phone'])); ?>" style="color: var(--accent);">
                            <i class="fas fa-phone"></i> <?php echo h($reservation['customer_phone']); ?>
                        </a>
                    </td>
                </tr>
                <tr>
                    <th style="padding: 12px; border-bottom: 1px solid var(--border-color);">メールアドレス</th>
                    <td style="padding: 12px; border-bottom: 1px solid var(--border-color);">
                        <?php if ($reservation['customer_email']): ?>
                        <a href="mailto:<?php echo h($reservation['customer_email']); ?>" style="color: var(--accent);">
                            <i class="fas fa-envelope"></i> <?php echo h($reservation['customer_email']); ?>
                        </a>
                        <?php else: ?>
                        <span style="color: var(--text-muted);">未入力</span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- 予約内容 -->
        <div class="content-card mb-4">
            <h5 class="mb-3"><i class="fas fa-calendar-alt"></i> 予約内容</h5>
            <table class="table" style="color: var(--text-primary);">
                <tr>
                    <th style="width: 150px; padding: 12px; border-bottom: 1px solid var(--border-color);">予約日</th>
                    <td style="padding: 12px; border-bottom: 1px solid var(--border-color);">
                        <strong style="font-size: 1.1em;"><?php echo h($reservation['reservation_date']); ?></strong>
                    </td>
                </tr>
                <tr>
                    <th style="padding: 12px; border-bottom: 1px solid var(--border-color);">希望時刻</th>
                    <td style="padding: 12px; border-bottom: 1px solid var(--border-color);">
                        <strong style="font-size: 1.1em;"><?php echo h($reservation['reservation_time']); ?></strong>
                    </td>
                </tr>
                <tr>
                    <th style="padding: 12px; border-bottom: 1px solid var(--border-color);">指名形態</th>
                    <td style="padding: 12px; border-bottom: 1px solid var(--border-color);">
                        <?php if ($reservation['nomination_type'] === 'shimei'): ?>
                        <span style="background: var(--primary); color: white; padding: 3px 10px; border-radius: 10px;">指名あり</span>
                        <?php else: ?>
                        <span style="background: var(--text-muted); color: white; padding: 3px 10px; border-radius: 10px;">フリー</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if ($reservation['nomination_type'] === 'shimei' && $reservation['cast_name']): ?>
                <tr>
                    <th style="padding: 12px; border-bottom: 1px solid var(--border-color);">指名キャスト</th>
                    <td style="padding: 12px; border-bottom: 1px solid var(--border-color);">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <?php if ($reservation['cast_img']): ?>
                            <img src="<?php echo h($reservation['cast_img']); ?>" alt="" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                            <?php endif; ?>
                            <strong><?php echo h($reservation['cast_name']); ?></strong>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th style="padding: 12px; border-bottom: 1px solid var(--border-color);">コース</th>
                    <td style="padding: 12px; border-bottom: 1px solid var(--border-color);">
                        <?php echo h($reservation['course_display']); ?>
                    </td>
                </tr>
                <tr>
                    <th style="padding: 12px; border-bottom: 1px solid var(--border-color);">施設</th>
                    <td style="padding: 12px; border-bottom: 1px solid var(--border-color);">
                        <?php echo h($reservation['facility_display']); ?>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- 備考 -->
        <?php if (!empty($reservation['message'] ?? '')): ?>
        <div class="content-card mb-4">
            <h5 class="mb-3"><i class="fas fa-sticky-note"></i> 備考・要望</h5>
            <div style="background: var(--bg-code); padding: 15px; border-radius: 10px; white-space: pre-wrap;">
                <?php echo h($reservation['message'] ?? ''); ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- 右カラム: 操作・メタ情報 -->
    <div class="col-md-4">
        <!-- 受付情報 -->
        <div class="content-card mb-4">
            <h5 class="mb-3"><i class="fas fa-info-circle"></i> 受付情報</h5>
            <table style="width: 100%; color: var(--text-primary);">
                <tr>
                    <td style="padding: 8px 0; color: var(--text-muted);">予約番号</td>
                    <td style="padding: 8px 0; text-align: right;"><strong>#<?php echo h($reservation['id']); ?></strong></td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: var(--text-muted);">受付日時</td>
                    <td style="padding: 8px 0; text-align: right;"><?php echo h(date('Y/m/d H:i', strtotime($reservation['created_at']))); ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; color: var(--text-muted);">更新日時</td>
                    <td style="padding: 8px 0; text-align: right;"><?php echo h(date('Y/m/d H:i', strtotime($reservation['updated_at']))); ?></td>
                </tr>
            </table>
        </div>
        
        <!-- クイックアクション -->
        <div class="content-card mb-4">
            <h5 class="mb-3"><i class="fas fa-bolt"></i> クイックアクション</h5>
            <div style="display: flex; flex-direction: column; gap: 10px;">
                <a href="tel:<?php echo h(preg_replace('/[^0-9]/', '', $reservation['customer_phone'])); ?>" class="btn btn-accent" style="width: 100%;">
                    <i class="fas fa-phone"></i> 電話をかける
                </a>
                <?php if ($reservation['customer_email']): ?>
                <a href="mailto:<?php echo h($reservation['customer_email']); ?>" class="btn btn-secondary" style="width: 100%;">
                    <i class="fas fa-envelope"></i> メールを送る
                </a>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- 削除 -->
        <div class="content-card" style="border: 1px solid var(--danger);">
            <h5 class="mb-3" style="color: var(--danger);"><i class="fas fa-trash"></i> 危険な操作</h5>
            <form method="post" onsubmit="return confirm('この予約を削除しますか？この操作は取り消せません。');">
                <input type="hidden" name="delete" value="1">
                <button type="submit" class="btn btn-danger" style="width: 100%;">
                    <i class="fas fa-trash"></i> この予約を削除
                </button>
            </form>
        </div>
    </div>
</div>

<div style="margin-top: 30px; text-align: center;">
    <a href="index.php?tenant=<?php echo h($tenantSlug); ?>" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> 予約管理に戻る
    </a>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
