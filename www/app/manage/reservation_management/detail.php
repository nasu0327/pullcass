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

// 顧客マスタから予約回数を取得（customer_id がある場合）
$reservation['customer_reservation_count'] = null;
if (!empty($reservation['customer_id']) && $pdo) {
    try {
        $stmtCust = $pdo->prepare("SELECT reservation_count FROM tenant_customers WHERE id = ? AND tenant_id = ?");
        $stmtCust->execute([$reservation['customer_id'], $tenantId]);
        $cust = $stmtCust->fetch(PDO::FETCH_ASSOC);
        if ($cust) {
            $reservation['customer_reservation_count'] = (int)($cust['reservation_count'] ?? 0);
        }
    } catch (Exception $e) { /* ignore */ }
}

// コース表示（メール通知と同様：course + course_content_id から解決）
$courseRaw = $reservation['course'] ?? '';
$courseContentId = $reservation['course_content_id'] ?? null;
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
                $baseCourseName = $row['table_name'] ?: $row['admin_title'];
                $courseDisplayName = $baseCourseName;
                if ($courseContentId) {
                    $stmtRow = $pdo->prepare("SELECT time_label, price_label FROM price_rows_published WHERE id = ?");
                    $stmtRow->execute([$courseContentId]);
                    $rowDetail = $stmtRow->fetch(PDO::FETCH_ASSOC);
                    if ($rowDetail) {
                        $parts = array_filter([$baseCourseName, $rowDetail['time_label'] ?? '', $rowDetail['price_label'] ?? '']);
                        $courseDisplayName = implode(' ', $parts);
                    }
                }
            }
        }
    } catch (Exception $e) {
        // 解決失敗時はそのまま
    }
}
$reservation['course_display'] = $courseDisplayName;

// 有料オプションの解決（メール通知と同様）
$optionDisplay = 'なし';
$optionsJson = $reservation['options'] ?? null;
if ($optionsJson) {
    $optionIds = json_decode($optionsJson, true);
    if (!empty($optionIds) && is_array($optionIds) && $pdo) {
        try {
            $placeholders = implode(',', array_fill(0, count($optionIds), '?'));
            $stmtOpt = $pdo->prepare("SELECT time_label, price_label FROM price_rows_published WHERE id IN ($placeholders)");
            $stmtOpt->execute($optionIds);
            $labels = [];
            while ($r = $stmtOpt->fetch(PDO::FETCH_ASSOC)) {
                $l = $r['time_label'] ?? '';
                if (!empty($r['price_label'])) $l .= ' (' . $r['price_label'] . ')';
                if ($l) $labels[] = $l;
            }
            if (!empty($labels)) $optionDisplay = implode('、', $labels);
        } catch (Exception $e) { /* ignore */ }
    }
}
$reservation['option_display'] = $optionDisplay;

// 施設（メール通知形式：自宅/ホテル + 詳細）
$facilityDetail = $reservation['facility_detail'] ?? '';
$facilityType = $reservation['facility_type'] ?? 'home';
$facilityLabelAdmin = ($facilityType === 'hotel') ? 'ホテル' : '自宅';
$reservation['facility_display'] = $facilityDetail ?: $facilityLabelAdmin;
$reservation['facility_label_admin'] = $facilityLabelAdmin;

// 利用形態の日本語化（メール通知と同様）
$reservation['customer_type_display'] = (($reservation['customer_type'] ?? '') === 'member') ? '2回目以降の利用' : '初めての利用';

// 合計金額（メール通知と同様）
$totalPrice = (int)($reservation['total_price'] ?? 0);
$reservation['total_amount_display'] = $totalPrice > 0 ? '¥' . number_format($totalPrice) : '';

// 予定日・受信時刻の整形（メール通知と同様）
$resDate = $reservation['reservation_date'] ?? '';
$resTime = $reservation['reservation_time'] ?? '';
$reservation['date_display'] = '';
if ($resDate && strtotime($resDate)) {
    $reservation['date_display'] = date('n/j', strtotime($resDate));
    $dayNames = ['日', '月', '火', '水', '木', '金', '土'];
    $reservation['date_display'] .= '(' . $dayNames[date('w', strtotime($resDate))] . ')';
    if ($resTime) $reservation['date_display'] .= ' ' . $resTime;
}
$reservation['created_at_display'] = !empty($reservation['created_at']) ? date('Y-m-d H:i:s', strtotime($reservation['created_at'])) : '';

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
        
        <!-- 予約詳細（メール通知と同じ形式） -->
        <div class="content-card mb-4">
            <h5 class="mb-3"><i class="fas fa-file-alt"></i> 予約内容（店舗通知メールと同じ）</h5>
            <div style="background: var(--bg-code); padding: 20px; border-radius: 10px; font-family: monospace; font-size: 0.95rem; line-height: 1.8; white-space: pre-wrap;">予定日：<?php echo h($reservation['date_display']); ?>

コールバック：<?php echo h($reservation['contact_available_time'] ?? ''); ?>

キャスト名：<?php echo h(($reservation['nomination_type'] === 'shimei' && !empty($reservation['cast_name'])) ? $reservation['cast_name'] : 'フリー'); ?>

利用形態：<?php echo h($reservation['customer_type_display']); ?>

コース：<?php echo h($reservation['course_display']); ?>

有料OP：<?php echo h($reservation['option_display']); ?>

イベント：<?php echo h($reservation['event_campaign'] ?? '') ?: 'なし'; ?>

名前：<?php echo h($reservation['customer_name'] ?? ''); ?><?php if ($reservation['customer_reservation_count'] !== null && $reservation['customer_reservation_count'] > 0): ?>（当店<?php echo (int)$reservation['customer_reservation_count']; ?>回目のご予約）<?php endif; ?>

電話：<?php echo h($reservation['customer_phone'] ?? ''); ?>

MAIL：<?php echo h($reservation['customer_email'] ?? ''); ?>

<?php echo h($reservation['facility_label_admin']); ?>：<?php echo h($reservation['facility_display']); ?>

伝達事項：<?php echo h($reservation['message'] ?? ''); ?>

合計金額：<?php echo h($reservation['total_amount_display']); ?>

受信時刻：<?php echo h($reservation['created_at_display']); ?></div>
            <div style="margin-top: 15px; display: flex; gap: 15px; flex-wrap: wrap;">
                <a href="tel:<?php echo h(preg_replace('/[^0-9]/', '', $reservation['customer_phone'] ?? '')); ?>" class="btn btn-accent btn-sm">
                    <i class="fas fa-phone"></i> 電話する
                </a>
                <?php if (!empty($reservation['customer_email'])): ?>
                <a href="mailto:<?php echo h($reservation['customer_email']); ?>" class="btn btn-secondary btn-sm">
                    <i class="fas fa-envelope"></i> メール
                </a>
                <?php endif; ?>
            </div>
        </div>
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
