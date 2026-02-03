<?php
/**
 * pullcass - 予約機能管理
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

$pageTitle = '予約機能管理';

// 成功・エラーメッセージ
$success = $_GET['success'] ?? null;
$error = $_GET['error'] ?? null;

// 現在の設定を取得
$stmt = $pdo->prepare("SELECT * FROM tenant_reservation_settings WHERE tenant_id = ?");
$stmt->execute([$tenantId]);
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

// 設定がない場合はデフォルト値を作成
if (!$settings) {
    $defaultAutoReply = "この度はご予約いただき、誠にありがとうございます。\n\n以下の内容でご予約を受け付けました。\n\n【ご予約内容】\n予約番号: {reservation_id}\nご利用日: {date}\nご希望時刻: {time}\n指名: {cast_name}\nコース: {course}\n\n※このご予約は仮予約です。\nお店からの確認連絡をもって予約確定となります。\n\nご不明な点がございましたら、お気軽にお問い合わせください。";
    
    $defaultAdminNotify = "【新規ネット予約】\n\n予約番号: {reservation_id}\n\n【お客様情報】\nお名前: {customer_name}\n電話番号: {customer_phone}\nメール: {customer_email}\n\n【ご予約内容】\nご利用日: {date}\nご希望時刻: {time}\n指名: {cast_name}\nコース: {course}\n施設: {facility}\n\n【備考】\n{notes}";
    
    $defaultNotice = "・このネット予約は仮予約です。お店からの確認連絡をもって予約確定となります。\n・ご希望の日時・キャストが確保できない場合がございます。\n・キャンセルや変更はお電話にてご連絡ください。";
    
    $stmt = $pdo->prepare("
        INSERT INTO tenant_reservation_settings 
        (tenant_id, notification_emails, auto_reply_body, admin_notify_body, notice_text) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $tenantId,
        $tenant['email'] ?? '',
        $defaultAutoReply,
        $defaultAdminNotify,
        $defaultNotice
    ]);
    
    // 再取得
    $stmt = $pdo->prepare("SELECT * FROM tenant_reservation_settings WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
}

// POST処理（設定保存）
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $isEnabled = isset($_POST['is_enabled']) ? 1 : 0;
        $is24hours = isset($_POST['is_24hours']) ? 1 : 0;
        $notificationEmails = trim($_POST['notification_emails'] ?? '');
        $acceptStartTime = $_POST['accept_start_time'] ?? '10:00';
        $acceptEndTime = $_POST['accept_end_time'] ?? '02:00';
        $advanceDays = (int)($_POST['advance_days'] ?? 7);
        $autoReplySubject = trim($_POST['auto_reply_subject'] ?? '');
        $autoReplyBody = trim($_POST['auto_reply_body'] ?? '');
        $adminNotifySubject = trim($_POST['admin_notify_subject'] ?? '');
        $adminNotifyBody = trim($_POST['admin_notify_body'] ?? '');
        $noticeText = trim($_POST['notice_text'] ?? '');
        $completeMessage = trim($_POST['complete_message'] ?? '');
        
        $stmt = $pdo->prepare("
            UPDATE tenant_reservation_settings SET
                is_enabled = ?,
                is_24hours = ?,
                notification_emails = ?,
                accept_start_time = ?,
                accept_end_time = ?,
                advance_days = ?,
                auto_reply_subject = ?,
                auto_reply_body = ?,
                admin_notify_subject = ?,
                admin_notify_body = ?,
                notice_text = ?,
                complete_message = ?
            WHERE tenant_id = ?
        ");
        $stmt->execute([
            $isEnabled,
            $is24hours,
            $notificationEmails,
            $acceptStartTime . ':00',
            $acceptEndTime . ':00',
            $advanceDays,
            $autoReplySubject,
            $autoReplyBody,
            $adminNotifySubject,
            $adminNotifyBody,
            $noticeText,
            $completeMessage,
            $tenantId
        ]);
        
        header('Location: index.php?tenant=' . rawurlencode($tenantSlug) . '&success=' . rawurlencode('設定を保存しました。'));
        exit;
    } catch (PDOException $e) {
        $error = '保存エラー: ' . $e->getMessage();
    }
}

// 予約一覧を取得（最新20件）
$stmt = $pdo->prepare("
    SELECT r.*, c.name as cast_name
    FROM tenant_reservations r
    LEFT JOIN tenant_casts c ON r.cast_id = c.id
    WHERE r.tenant_id = ?
    ORDER BY r.created_at DESC
    LIMIT 20
");
$stmt->execute([$tenantId]);
$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 予約統計
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM tenant_reservations
    WHERE tenant_id = ?
");
$stmt->execute([$tenantId]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>
<?php
require_once __DIR__ . '/../includes/breadcrumb.php';
$breadcrumbs = [
    ['label' => 'ダッシュボード', 'url' => '/app/manage/?tenant=' . $tenantSlug, 'icon' => 'fas fa-chart-pie'],
    ['label' => '予約機能管理']
];
renderBreadcrumb($breadcrumbs);
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-calendar-check"></i> <?php echo h($pageTitle); ?></h1>
        <p>ネット予約機能の設定と予約一覧の確認ができます。</p>
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

<!-- 予約機能ON/OFF設定（最重要設定） -->
<form method="post" action="">
    <div class="content-card mb-4" style="border: 3px solid <?php echo ($settings['is_enabled'] ?? 1) ? '#28a745' : '#dc3545'; ?>; position: relative;">
        <div style="position: absolute; top: -12px; left: 20px; background: <?php echo ($settings['is_enabled'] ?? 1) ? '#28a745' : '#dc3545'; ?>; color: white; padding: 4px 15px; border-radius: 15px; font-size: 0.85em; font-weight: bold;">
            <?php echo ($settings['is_enabled'] ?? 1) ? '✓ 有効' : '✕ 無効'; ?>
        </div>
        <h5 class="mb-3" style="margin-top: 10px;"><i class="fas fa-power-off"></i> 予約機能のON/OFF</h5>
        
        <div style="display: flex; align-items: center; gap: 20px; padding: 15px; background: rgba(0,0,0,0.1); border-radius: 10px;">
            <label class="form-check-label" style="display: flex; align-items: center; gap: 15px; cursor: pointer; flex: 1;">
                <input type="checkbox" name="is_enabled" value="1" <?php echo ($settings['is_enabled'] ?? 1) ? 'checked' : ''; ?> 
                       style="width: 30px; height: 30px; accent-color: #28a745;">
                <div>
                    <span style="font-weight: bold; font-size: 1.2em; display: block;">予約機能を有効にする</span>
                    <small style="color: var(--text-muted); display: block; margin-top: 3px;">
                        <i class="fas fa-info-circle"></i> 無効にすると、キャスト詳細ページの「ネット予約」セクションと予約ボタンが非表示になります。
                    </small>
                </div>
            </label>
            <button type="submit" class="btn btn-primary" style="white-space: nowrap;">
                <i class="fas fa-save"></i> 保存
            </button>
        </div>
    </div>

<!-- 予約統計 -->
<div class="content-card mb-4">
    <h5 class="mb-3"><i class="fas fa-chart-bar"></i> 予約統計</h5>
    <div class="d-flex flex-wrap gap-3" style="justify-content: center;">
        <div style="background: rgba(0,0,0,0.2); border-radius: 10px; padding: 20px 30px; text-align: center; min-width: 120px;">
            <div style="font-size: 2em; font-weight: bold; color: var(--text-light);"><?php echo number_format($stats['total'] ?? 0); ?></div>
            <div style="color: var(--text-muted); font-size: 0.9em;">総予約数</div>
        </div>
        <div style="background: rgba(255,193,7,0.2); border-radius: 10px; padding: 20px 30px; text-align: center; min-width: 120px;">
            <div style="font-size: 2em; font-weight: bold; color: #ffc107;"><?php echo number_format($stats['pending'] ?? 0); ?></div>
            <div style="color: var(--text-muted); font-size: 0.9em;">未確認</div>
        </div>
        <div style="background: rgba(40,167,69,0.2); border-radius: 10px; padding: 20px 30px; text-align: center; min-width: 120px;">
            <div style="font-size: 2em; font-weight: bold; color: #28a745;"><?php echo number_format($stats['confirmed'] ?? 0); ?></div>
            <div style="color: var(--text-muted); font-size: 0.9em;">確定済み</div>
        </div>
        <div style="background: rgba(220,53,69,0.2); border-radius: 10px; padding: 20px 30px; text-align: center; min-width: 120px;">
            <div style="font-size: 2em; font-weight: bold; color: #dc3545;"><?php echo number_format($stats['cancelled'] ?? 0); ?></div>
            <div style="color: var(--text-muted); font-size: 0.9em;">キャンセル</div>
        </div>
    </div>
</div>

<!-- 基本設定 -->
    <div class="content-card mb-4">
        <h5 class="mb-3"><i class="fas fa-cog"></i> 基本設定</h5>
        
        <div class="form-group mb-3">
            <label class="form-label"><i class="fas fa-envelope"></i> 通知メールアドレス</label>
            <input type="text" name="notification_emails" class="form-control" 
                   value="<?php echo h($settings['notification_emails'] ?? ''); ?>"
                   placeholder="example@shop.com, staff@shop.com">
            <small style="color: var(--text-muted);">
                予約が入った際に通知を受け取るメールアドレスを入力してください。複数の場合はカンマ区切りで入力できます。
            </small>
        </div>
        
        <div class="form-group mb-3">
            <label class="form-label" style="margin-bottom: 15px;">
                <i class="fas fa-clock"></i> 受付時間設定
            </label>
            
            <!-- 24時間チェックボックス -->
            <div style="margin-bottom: 15px;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                    <input type="checkbox" name="is_24hours" id="is_24hours" value="1" 
                           <?php echo ($settings['is_24hours'] ?? 0) ? 'checked' : ''; ?>
                           style="width: 20px; height: 20px; accent-color: var(--primary);"
                           onchange="toggle24Hours(this.checked)">
                    <span style="font-weight: bold; color: var(--text-light);">24時間営業</span>
                </label>
            </div>
            
            <!-- 時間選択（横並び） -->
            <div id="time_settings_row" style="display: flex; gap: 20px; flex-wrap: wrap; align-items: center; <?php echo ($settings['is_24hours'] ?? 0) ? 'opacity: 0.5; pointer-events: none;' : ''; ?>">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="white-space: nowrap; font-weight: bold;">受付開始</span>
                    <select name="accept_start_time" id="accept_start_time" class="form-control" style="width: 120px;" onchange="updateEndTimeOptions()">
                        <?php
                        $currentStart = substr($settings['accept_start_time'] ?? '10:00:00', 0, 5);
                        for ($h = 0; $h < 24; $h++) {
                            for ($m = 0; $m < 60; $m += 30) {
                                $time = sprintf('%02d:%02d', $h, $m);
                                $selected = ($time === $currentStart) ? 'selected' : '';
                                echo "<option value=\"{$time}\" {$selected}>{$time}</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
                <span style="font-size: 1.2em;">〜</span>
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span style="white-space: nowrap; font-weight: bold;">受付終了</span>
                    <select name="accept_end_time" id="accept_end_time" class="form-control" style="width: 120px;">
                        <!-- JavaScript で動的に生成 -->
                    </select>
                </div>
            </div>
            <small style="color: var(--text-muted); display: block; margin-top: 10px;">
                <i class="fas fa-info-circle"></i> 終了時刻は開始時刻の1時間後から最大22時間後まで設定可能です。それ以上は24時間営業をお選びください。
            </small>
        </div>
        

    </div>
    
<script>
// 現在保存されている終了時間
const savedEndTime = <?php echo json_encode(substr($settings['accept_end_time'] ?? '02:00:00', 0, 5)); ?>;

function toggle24Hours(is24h) {
    const row = document.getElementById('time_settings_row');
    if (is24h) {
        row.style.opacity = '0.5';
        row.style.pointerEvents = 'none';
    } else {
        row.style.opacity = '1';
        row.style.pointerEvents = 'auto';
    }
}

function updateEndTimeOptions() {
    const startSelect = document.getElementById('accept_start_time');
    const endSelect = document.getElementById('accept_end_time');
    const startValue = startSelect.value;
    
    // 開始時間を分に変換
    const [startH, startM] = startValue.split(':').map(Number);
    const startMinutes = startH * 60 + startM;
    
    // 終了時間の選択肢をクリア
    endSelect.innerHTML = '';
    
    // 開始時間の1時間後から22時間後まで（30分刻み）
    const minEndMinutes = startMinutes + 60; // 1時間後
    const maxEndMinutes = startMinutes + (22 * 60); // 22時間後
    
    for (let mins = minEndMinutes; mins <= maxEndMinutes; mins += 30) {
        const option = document.createElement('option');
        
        // 24時間を超える場合は翌日表記
        let displayMins = mins;
        let prefix = '';
        if (mins >= 24 * 60) {
            displayMins = mins - (24 * 60);
            prefix = '翌';
        }
        
        const h = Math.floor(displayMins / 60);
        const m = displayMins % 60;
        const valueTime = String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
        const displayTime = prefix + h + ':' + String(m).padStart(2, '0');
        
        option.value = valueTime;
        option.textContent = displayTime;
        
        // 保存された値を選択
        if (valueTime === savedEndTime) {
            option.selected = true;
        }
        
        endSelect.appendChild(option);
    }
    
    // 選択がない場合は最初のオプションを選択
    if (!endSelect.value && endSelect.options.length > 0) {
        endSelect.options[0].selected = true;
    }
}

// ページ読み込み時に終了時間オプションを初期化
document.addEventListener('DOMContentLoaded', function() {
    updateEndTimeOptions();
});
</script>
    
    <!-- 注意事項設定 -->
    <div class="content-card mb-4">
        <h5 class="mb-3"><i class="fas fa-exclamation-triangle"></i> 注意事項設定</h5>
        
        <div class="form-group mb-3">
            <label class="form-label">予約フォームに表示する注意事項</label>
            <textarea name="notice_text" class="form-control" rows="5" 
                      placeholder="・このネット予約は仮予約です。&#10;・ご希望の日時・キャストが確保できない場合がございます。"><?php echo h($settings['notice_text'] ?? ''); ?></textarea>
            <small style="color: var(--text-muted);">
                予約フォームの上部に表示される注意事項です。改行で箇条書きにできます。
            </small>
        </div>
        

    </div>
    
    <!-- 自動返信メール設定 -->
    <div class="content-card mb-4">
        <h5 class="mb-3"><i class="fas fa-reply"></i> お客様向け自動返信メール</h5>
        <p style="color: var(--text-muted); font-size: 0.9em; margin-bottom: 15px;">
            予約完了時にお客様に送信されるメールの内容を設定します。
        </p>
        
        <div class="form-group mb-3">
            <label class="form-label">件名</label>
            <input type="text" name="auto_reply_subject" class="form-control" 
                   value="<?php echo h($settings['auto_reply_subject'] ?? 'ご予約を受け付けました'); ?>"
                   placeholder="ご予約を受け付けました">
        </div>
        
        <div class="form-group mb-3">
            <label class="form-label">本文</label>
            <textarea name="auto_reply_body" class="form-control" rows="10"><?php echo h($settings['auto_reply_body'] ?? ''); ?></textarea>
            <small style="color: var(--text-muted);">
                使用可能なプレースホルダー: {reservation_id}, {customer_name}, {date}, {time}, {cast_name}, {course}, {facility}, {notes}
            </small>
        </div>
    </div>
    
    <!-- 管理者通知メール設定 -->
    <div class="content-card mb-4">
        <h5 class="mb-3"><i class="fas fa-bell"></i> 管理者向け通知メール</h5>
        <p style="color: var(--text-muted); font-size: 0.9em; margin-bottom: 15px;">
            予約が入った際に管理者に送信されるメールの内容を設定します。
        </p>
        
        <div class="form-group mb-3">
            <label class="form-label">件名</label>
            <input type="text" name="admin_notify_subject" class="form-control" 
                   value="<?php echo h($settings['admin_notify_subject'] ?? '【新規予約】ネット予約が入りました'); ?>"
                   placeholder="【新規予約】ネット予約が入りました">
        </div>
        
        <div class="form-group mb-3">
            <label class="form-label">本文</label>
            <textarea name="admin_notify_body" class="form-control" rows="10"><?php echo h($settings['admin_notify_body'] ?? ''); ?></textarea>
            <small style="color: var(--text-muted);">
                使用可能なプレースホルダー: {reservation_id}, {customer_name}, {customer_phone}, {customer_email}, {date}, {time}, {cast_name}, {course}, {facility}, {notes}
            </small>
        </div>
    </div>
    
    <div class="text-center mb-4">
        <button type="submit" class="btn btn-primary btn-lg">
            <i class="fas fa-save"></i> 設定を保存
        </button>
    </div>
</form>

<!-- 予約一覧 -->
<div class="content-card">
    <h5 class="mb-3"><i class="fas fa-list"></i> 最新の予約一覧</h5>
    
    <?php if (empty($reservations)): ?>
        <div style="text-align: center; padding: 40px; color: var(--text-muted);">
            <i class="fas fa-inbox" style="font-size: 3em; margin-bottom: 15px;"></i>
            <p>まだ予約がありません。</p>
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
                        <th style="padding: 15px;">受付日時</th>
                        <th style="padding: 15px;">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reservations as $r): ?>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 15px;">#<?php echo h($r['id']); ?></td>
                        <td style="padding: 15px;">
                        <?php
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
                            $status = $r['status'] ?? 'new';
                            ?>
                            <span style="background: <?php echo $statusColors[$status] ?? '#6c757d'; ?>; color: white; padding: 3px 10px; border-radius: 15px; font-size: 0.8em;">
                                <?php echo h($statusLabels[$status] ?? $status); ?>
                            </span>
                        </td>
                        <td style="padding: 15px;"><?php echo h($r['customer_name']); ?></td>
                        <td style="padding: 15px;"><?php echo h($r['customer_phone']); ?></td>
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
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="list.php?tenant=<?php echo h($tenantSlug); ?>" class="btn btn-secondary">
                <i class="fas fa-list"></i> すべての予約を見る
            </a>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
