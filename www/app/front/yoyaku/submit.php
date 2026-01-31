<?php
/**
 * pullcass - 予約送信API
 * 参考: reference/public_html/yoyaku/submit_reservation.php
 */

session_start();

require_once __DIR__ . '/../../../includes/bootstrap.php';

// POSTリクエストのみ許可
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /app/front/yoyaku.php');
    exit;
}

// テナント情報を取得
$tenantFromRequest = getTenantFromRequest();
$tenantFromSession = getCurrentTenant();

if ($tenantFromRequest) {
    $tenant = $tenantFromRequest;
} elseif ($tenantFromSession) {
    $tenant = $tenantFromSession;
} else {
    header('Location: https://pullcass.com/');
    exit;
}

$tenantId = $tenant['id'];
$shopName = $tenant['name'];
$shopEmail = $tenant['email'] ?? '';
$shopPhone = $tenant['phone'] ?? '';

// フォームデータを取得
$nominationType = filter_input(INPUT_POST, 'nomination_type', FILTER_SANITIZE_SPECIAL_CHARS) ?: 'free';
$castId = filter_input(INPUT_POST, 'cast_id', FILTER_VALIDATE_INT) ?: null;
$castName = filter_input(INPUT_POST, 'cast_name', FILTER_SANITIZE_SPECIAL_CHARS) ?: '';
$reservationDate = filter_input(INPUT_POST, 'reservation_date', FILTER_SANITIZE_SPECIAL_CHARS);
$reservationTime = filter_input(INPUT_POST, 'reservation_time', FILTER_SANITIZE_SPECIAL_CHARS);
// 確認電話可能日時（新形式：日付、開始時刻、終了時刻）
$confirmDate = filter_input(INPUT_POST, 'confirm_date', FILTER_SANITIZE_SPECIAL_CHARS) ?: '';
$confirmStartTime = filter_input(INPUT_POST, 'confirm_start_time', FILTER_SANITIZE_SPECIAL_CHARS) ?: '';
$confirmEndTime = filter_input(INPUT_POST, 'confirm_end_time', FILTER_SANITIZE_SPECIAL_CHARS) ?: '';

// 確認電話可能日時を文字列に整形
$contactAvailableTime = '';
if ($confirmDate && $confirmStartTime && $confirmEndTime) {
    $confirmDateFormatted = date('n/j', strtotime($confirmDate));
    $dayOfWeekNames = ['日', '月', '火', '水', '木', '金', '土'];
    $confirmDateFormatted .= '(' . $dayOfWeekNames[date('w', strtotime($confirmDate))] . ')';
    $contactAvailableTime = "{$confirmDateFormatted} {$confirmStartTime}〜{$confirmEndTime}";
}
$customerType = filter_input(INPUT_POST, 'customer_type', FILTER_SANITIZE_SPECIAL_CHARS) ?: 'new';
$course = filter_input(INPUT_POST, 'course', FILTER_SANITIZE_SPECIAL_CHARS);
$facilityType = filter_input(INPUT_POST, 'facility_type', FILTER_SANITIZE_SPECIAL_CHARS) ?: 'home';
$facilityDetail = filter_input(INPUT_POST, 'facility_detail', FILTER_SANITIZE_SPECIAL_CHARS) ?: '';
$customerName = filter_input(INPUT_POST, 'customer_name', FILTER_SANITIZE_SPECIAL_CHARS);
$customerPhone = filter_input(INPUT_POST, 'customer_phone', FILTER_SANITIZE_SPECIAL_CHARS);
$customerEmail = filter_input(INPUT_POST, 'customer_email', FILTER_SANITIZE_EMAIL) ?: '';
$message = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_SPECIAL_CHARS) ?: '';

// バリデーション
$errors = [];

if (empty($reservationDate)) {
    $errors[] = '利用予定日を選択してください';
}

if (empty($reservationTime)) {
    $errors[] = '希望時刻を選択してください';
}

if (empty($course)) {
    $errors[] = 'コースを選択してください';
}

if (empty($customerName)) {
    $errors[] = 'お名前を入力してください';
}

if (empty($customerPhone)) {
    $errors[] = '電話番号を入力してください';
} elseif (!preg_match('/^[\d\-]+$/', $customerPhone)) {
    $errors[] = '電話番号は数字とハイフンのみで入力してください';
}

if (!empty($customerEmail) && !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'メールアドレスの形式が正しくありません';
}

if ($nominationType === 'shimei' && empty($castId)) {
    $errors[] = 'キャストを選択してください';
}

// エラーがある場合は戻る
if (!empty($errors)) {
    $_SESSION['reservation_errors'] = $errors;
    $_SESSION['reservation_form_data'] = $_POST;
    header('Location: /app/front/yoyaku.php' . ($castId ? '?cast_id=' . $castId : ''));
    exit;
}

// キャスト名を取得（指名ありの場合）
if ($nominationType === 'shimei' && $castId && empty($castName)) {
    try {
        $stmt = $pdo->prepare("SELECT name FROM tenant_casts WHERE id = ? AND tenant_id = ?");
        $stmt->execute([$castId, $tenantId]);
        $castData = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($castData) {
            $castName = $castData['name'];
        }
    } catch (Exception $e) {
        error_log("Cast name fetch error: " . $e->getMessage());
    }
}

// データベースに保存
try {
    $stmt = $pdo->prepare("
        INSERT INTO tenant_reservations (
            tenant_id, cast_id, cast_name, nomination_type,
            reservation_date, reservation_time, contact_available_time,
            customer_type, course, facility_type, facility_detail,
            customer_name, customer_phone, customer_email, message,
            status, created_at
        ) VALUES (
            :tenant_id, :cast_id, :cast_name, :nomination_type,
            :reservation_date, :reservation_time, :contact_available_time,
            :customer_type, :course, :facility_type, :facility_detail,
            :customer_name, :customer_phone, :customer_email, :message,
            'new', NOW()
        )
    ");
    
    $stmt->execute([
        'tenant_id' => $tenantId,
        'cast_id' => $castId,
        'cast_name' => $castName,
        'nomination_type' => $nominationType,
        'reservation_date' => $reservationDate,
        'reservation_time' => $reservationTime,
        'contact_available_time' => $contactAvailableTime,
        'customer_type' => $customerType,
        'course' => $course,
        'facility_type' => $facilityType,
        'facility_detail' => $facilityDetail,
        'customer_name' => $customerName,
        'customer_phone' => $customerPhone,
        'customer_email' => $customerEmail,
        'message' => $message
    ]);
    
    $reservationId = $pdo->lastInsertId();
    
} catch (PDOException $e) {
    error_log("Reservation insert error: " . $e->getMessage());
    $_SESSION['reservation_errors'] = ['予約の保存に失敗しました。しばらく経ってから再度お試しください。'];
    $_SESSION['reservation_form_data'] = $_POST;
    header('Location: /app/front/yoyaku.php' . ($castId ? '?cast_id=' . $castId : ''));
    exit;
}

// メール送信用のデータ整形
$reservationDateFormatted = date('Y年n月j日', strtotime($reservationDate));
$dayOfWeekNames = ['日', '月', '火', '水', '木', '金', '土'];
$reservationDateFormatted .= '(' . $dayOfWeekNames[date('w', strtotime($reservationDate))] . ')';

// 時刻の整形（25:00以降は翌日表示）
$timeHour = intval(substr($reservationTime, 0, 2));
$timeMin = substr($reservationTime, 3, 2);
if ($timeHour >= 24) {
    $reservationTimeFormatted = '翌' . ($timeHour - 24) . ':' . $timeMin;
} else {
    $reservationTimeFormatted = $reservationTime;
}

$nominationTypeText = $nominationType === 'shimei' ? '指名あり' : 'フリー（指名なし）';
$customerTypeText = $customerType === 'new' ? '初めて利用' : '会員';
$facilityTypeText = $facilityType === 'home' ? '自宅' : 'ホテル';

// 管理者向けメール本文
$adminMailBody = <<<EOT
【ネット予約が入りました】

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
予約ID: {$reservationId}
受付日時: {$_SERVER['REQUEST_TIME']}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

■ 指名形態
{$nominationTypeText}
EOT;

if ($nominationType === 'shimei' && $castName) {
    $adminMailBody .= "\n指名キャスト: {$castName}";
}

$adminMailBody .= <<<EOT


■ 利用予定日時
{$reservationDateFormatted} {$reservationTimeFormatted}

■ 確認電話可能日時
{$contactAvailableTime}

■ 利用形態
{$customerTypeText}

■ コース
{$course}

■ 利用施設
{$facilityTypeText}
{$facilityDetail}

■ お客様情報
お名前: {$customerName}
電話番号: {$customerPhone}
メールアドレス: {$customerEmail}

■ 伝達事項
{$message}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
このメールはネット予約フォームから自動送信されています。
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
EOT;

// お客様向けメール本文
$customerMailBody = <<<EOT
{$customerName} 様

この度は{$shopName}をご利用いただき、誠にありがとうございます。
以下の内容でネット予約を承りました。

※このメールは仮予約の受付確認です。
お店からの確認連絡をもって予約確定となります。

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
■ ご予約内容
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

【指名形態】
{$nominationTypeText}
EOT;

if ($nominationType === 'shimei' && $castName) {
    $customerMailBody .= "\n指名キャスト: {$castName}";
}

$customerMailBody .= <<<EOT


【利用予定日時】
{$reservationDateFormatted} {$reservationTimeFormatted}

【コース】
{$course}

【利用施設】
{$facilityTypeText}
{$facilityDetail}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

ご不明な点がございましたら、お気軽にお問い合わせください。

{$shopName}
TEL: {$shopPhone}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
このメールは自動送信されています。
このメールに返信されても対応できませんのでご了承ください。
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
EOT;

// メール送信
$mailSent = false;
$customerMailSent = false;

// 管理者向けメール送信
if (!empty($shopEmail)) {
    $adminSubject = "【ネット予約】{$customerName}様 - {$reservationDateFormatted}";
    $adminHeaders = [
        'From' => 'noreply@pullcass.com',
        'Reply-To' => $customerEmail ?: 'noreply@pullcass.com',
        'Content-Type' => 'text/plain; charset=UTF-8',
        'X-Mailer' => 'PHP/' . phpversion()
    ];
    
    $mailSent = @mb_send_mail(
        $shopEmail,
        $adminSubject,
        $adminMailBody,
        implode("\r\n", array_map(function($k, $v) { return "$k: $v"; }, array_keys($adminHeaders), $adminHeaders))
    );
    
    if (!$mailSent) {
        error_log("Admin mail send failed to: {$shopEmail}");
    }
}

// お客様向けメール送信
if (!empty($customerEmail)) {
    $customerSubject = "【{$shopName}】ご予約受付のお知らせ";
    $customerHeaders = [
        'From' => 'noreply@pullcass.com',
        'Content-Type' => 'text/plain; charset=UTF-8',
        'X-Mailer' => 'PHP/' . phpversion()
    ];
    
    $customerMailSent = @mb_send_mail(
        $customerEmail,
        $customerSubject,
        $customerMailBody,
        implode("\r\n", array_map(function($k, $v) { return "$k: $v"; }, array_keys($customerHeaders), $customerHeaders))
    );
    
    if (!$customerMailSent) {
        error_log("Customer mail send failed to: {$customerEmail}");
    }
}

// 完了ページへリダイレクト
$_SESSION['reservation_complete'] = [
    'reservation_id' => $reservationId,
    'customer_name' => $customerName,
    'reservation_date' => $reservationDateFormatted,
    'reservation_time' => $reservationTimeFormatted,
    'cast_name' => $castName,
    'nomination_type' => $nominationType,
    'mail_sent' => $mailSent,
    'customer_mail_sent' => $customerMailSent
];

header('Location: /app/front/yoyaku/complete.php');
exit;
