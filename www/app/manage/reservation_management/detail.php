<?php
/**
 * pullcass - 予約詳細ページ
 */

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/reservation_placeholders.php';
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

// プレースホルダー構築（メール通知と共通・admin_notify_body テンプレート用）
$tenant = $_SESSION['manage_tenant'] ?? null;
if (!$tenant && $tenantId) {
    $stmtT = $pdo->prepare("SELECT * FROM tenants WHERE id = ?");
    $stmtT->execute([$tenantId]);
    $tenant = $stmtT->fetch(PDO::FETCH_ASSOC) ?: [];
}
$placeholders = buildReservationPlaceholders($reservation, $tenant, $pdo, (int)($reservation['id'] ?? 0));

// 管理者が設定した admin_notify_body テンプレートで表示
$adminNotifyBody = '';
try {
    $stmtTpl = $pdo->prepare("SELECT admin_notify_body FROM tenant_reservation_settings WHERE tenant_id = ?");
    $stmtTpl->execute([$tenantId]);
    $row = $stmtTpl->fetch(PDO::FETCH_ASSOC);
    $adminNotifyBody = $row['admin_notify_body'] ?? '';
} catch (Exception $e) { /* ignore */ }

$reservationDetailDisplay = '';
if (trim($adminNotifyBody) !== '') {
    $reservationDetailDisplay = replaceReservationPlaceholders($adminNotifyBody, $placeholders);
} else {
    // テンプレート未設定時はデフォルト形式
    $reservationDetailDisplay = "予定日：{$placeholders['{date}']} {$placeholders['{time}']}\n";
    $reservationDetailDisplay .= "コールバック：{$placeholders['{confirm_time}']}\n";
    $reservationDetailDisplay .= "キャスト名：{$placeholders['{cast_name}']}\n";
    $reservationDetailDisplay .= "利用形態：{$placeholders['{customer_type}']}\n";
    $reservationDetailDisplay .= "コース：{$placeholders['{course}']}\n";
    $reservationDetailDisplay .= "有料OP：{$placeholders['{option}']}\n";
    $reservationDetailDisplay .= "イベント：{$placeholders['{event}']}\n";
    $reservationDetailDisplay .= "名前：{$placeholders['{customer_name}']}{$placeholders['{reservation_count}']}\n";
    $reservationDetailDisplay .= "電話：{$placeholders['{customer_phone}']}\n";
    $reservationDetailDisplay .= "MAIL：{$placeholders['{customer_email}']}\n";
    $reservationDetailDisplay .= "{$placeholders['{facility_label_admin}']}：{$placeholders['{facility}']}\n";
    $reservationDetailDisplay .= "伝達事項：{$placeholders['{notes}']}\n";
    $reservationDetailDisplay .= "合計金額：{$placeholders['{total_amount}']}\n";
    $reservationDetailDisplay .= "受信時刻：{$placeholders['{created_at}']}";
}

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
            <div style="background: var(--bg-code); padding: 20px; border-radius: 10px; font-family: monospace; font-size: 0.95rem; line-height: 1.8; white-space: pre-wrap;"><?php echo h($reservationDetailDisplay); ?></div>
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
