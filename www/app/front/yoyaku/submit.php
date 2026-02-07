<?php
/**
 * pullcass - 予約送信API
 * 参考: reference/public_html/yoyaku/submit_reservation.php
 */

session_start();

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/mail_helper.php';

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
$shopPhone = $tenant['phone'] ?? '';

// DB接続を取得
$pdo = getPlatformDb();
if (!$pdo) {
    $_SESSION['reservation_errors'] = ['システムエラーが発生しました。しばらく経ってから再度お試しください。'];
    $_SESSION['reservation_form_data'] = $_POST;
    header('Location: /app/front/yoyaku.php' . (isset($_POST['cast_id']) && $_POST['cast_id'] ? '?cast_id=' . (int)$_POST['cast_id'] : ''));
    exit;
}

// 管理者通知先メールアドレス（予約設定の notification_emails を優先、なければテナントの email）
$adminEmails = [];
try {
    $stmt = $pdo->prepare("SELECT notification_emails FROM tenant_reservation_settings WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $notificationEmails = $row['notification_emails'] ?? '';
    if (trim($notificationEmails) !== '') {
        foreach (preg_split('/[\s,]+/', $notificationEmails, -1, PREG_SPLIT_NO_EMPTY) as $addr) {
            $addr = trim($addr);
            if ($addr !== '' && filter_var($addr, FILTER_VALIDATE_EMAIL)) {
                $adminEmails[] = $addr;
            }
        }
    }
    if (empty($adminEmails) && !empty(trim($tenant['email'] ?? '')) && filter_var(trim($tenant['email']), FILTER_VALIDATE_EMAIL)) {
        $adminEmails[] = trim($tenant['email']);
    }
} catch (Exception $e) {
    if (!empty(trim($tenant['email'] ?? '')) && filter_var(trim($tenant['email']), FILTER_VALIDATE_EMAIL)) {
        $adminEmails[] = trim($tenant['email']);
    }
}

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

// 施設ラベルの決定（管理者通知用）
// 自宅以外（ホテルなど）の場合は「ホテル」、それ以外は「自宅」とする（ユーザー要望のテンプレートに合わせる）
$facilityLabelAdmin = ($facilityType === 'hotel') ? 'ホテル' : '自宅';

// メール送信用プレースホルダーの準備
$placeholders = [
    '{reservation_id}' => $reservationId,
    '{customer_name}' => $customerName,
    '{customer_phone}' => $customerPhone,
    '{customer_email}' => $customerEmail,
    '{date}' => $reservationDateFormatted,
    '{time}' => $reservationTimeFormatted,
    '{cast_name}' => ($nominationType === 'shimei' && $castName) ? $castName : 'フリー', // 「フリー（指名なし）」から「フリー」へ変更（要望合わせ）
    '{course}' => $course,
    '{facility}' => $facilityDetail ? $facilityDetail : $facilityTypeText, // 施設詳細があればそれをValueとして使用
    '{facility_label_admin}' => $facilityLabelAdmin, // 管理者通知用の動的ラベル
    '{notes}' => $message,
    '{created_at}' => date('Y-m-d H:i:s'),
    '{total_amount}' => '', // 現時点では空（空文字なら行ごと非表示）
    '{option}' => '', // 現時点では空（空文字なら行ごと非表示）
    '{event}' => '', // 現時点では空（空文字なら行ごと非表示）
    '{tenant_name}' => $shopName,
    '{tenant_hp}' => 'https://' . ($tenant['domain'] ?? ($tenant['code'] . '.pullcass.com')) . '/', // 末尾スラッシュ追加
    '{tenant_tel}' => $shopPhone,
    '{confirm_time}' => $contactAvailableTime,
    '{customer_type}' => $customerTypeText
];

// 営業時間（{tenant_hours}）の取得
try {
    $stmt = $pdo->prepare("SELECT accept_start_time, accept_end_time FROM tenant_reservation_settings WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($settings) {
        $startTime = substr($settings['accept_start_time'], 0, 5);
        $endTime = substr($settings['accept_end_time'], 0, 5);
        
        // 終了時間が24:00を超える場合の処理
        $endH = (int)substr($endTime, 0, 2);
        $endM = substr($endTime, 3, 2);
        if ($endH < (int)substr($startTime, 0, 2)) {
            // 日付をまたぐ場合（例: 10:00 -> 02:00）は翌表記にする
            $endTime = '翌' . $endTime;
        }
        
        $placeholders['{tenant_hours}'] = "{$startTime}〜{$endTime}";
    } else {
        $placeholders['{tenant_hours}'] = '';
    }
    
    // テンプレートの取得
    $stmt = $pdo->prepare("SELECT auto_reply_subject, auto_reply_body, admin_notify_subject, admin_notify_body FROM tenant_reservation_settings WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $templateSettings = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    // エラー時はデフォルト値を設定
    $templateSettings = [];
}

// テンプレート置換関数
function replacePlaceholders($text, $placeholders) {
    // まずプレースホルダーを置換
    $text = str_replace(array_keys($placeholders), array_values($placeholders), $text);
    
    // 値が空の行（"ラベル：\n" または "ラベル:\n" のような行）を削除する処理
    // {option}, {event}, {total_amount} などが空の場合、行ごと非表示にするため
    $lines = explode("\n", $text);
    $cleanedLines = [];
    foreach ($lines as $line) {
        $trimmedLine = trim($line);
        // 行末が「：」または「:」で終わっている（値がない）場合はスキップ
        // ただし、意図的に空行を入れている場合は残す（行自体が空の場合は除く）
        if ($trimmedLine !== '' && (mb_substr($trimmedLine, -1) === '：' || substr($trimmedLine, -1) === ':')) {
            continue;
        }
        $cleanedLines[] = $line;
    }
    return implode("\n", $cleanedLines);
}

// メール送信（日本語・UTF-8 で送信するため mbstring を設定）
if (function_exists('mb_language')) {
    mb_language('Japanese');
}
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

$mailSent = false;
$customerMailSent = false;

// From ヘッダー（テナントごとのドメイン対応）
$mailDomain = 'pullcass.com';
if (!empty(trim($tenant['domain'] ?? ''))) {
    $mailDomain = trim($tenant['domain']);
}
$fromHeader = getenv('MAIL_FROM') ?: ('Pullcass <noreply@' . $mailDomain . '>');

// 管理者向けメール送信
foreach ($adminEmails as $adminTo) {
    $adminSubject = $templateSettings['admin_notify_subject'] ?? "ネット予約";
    $adminSubject = str_replace(array_keys($placeholders), array_values($placeholders), $adminSubject); // 件名は単純置換
    
    $adminBody = $templateSettings['admin_notify_body'] ?? "";
    if (empty($adminBody)) {
        // DBに設定がない場合のフォールバック
        $adminBody = "予定日：{date} {time}\nコールバック：{confirm_time}\nキャスト名：{cast_name}\nコース：{course}\n（省略）";
    }
    $adminBody = replacePlaceholders($adminBody, $placeholders);

    $adminHeaders = [
        'From' => $fromHeader,
        'Reply-To' => $customerEmail ?: ('noreply@' . $mailDomain),
        'Content-Type' => 'text/plain; charset=UTF-8',
        'X-Mailer' => 'PHP/' . phpversion()
    ];
    $headerStr = implode("\r\n", array_map(function ($k, $v) {
        return $k . ': ' . $v;
    }, array_keys($adminHeaders), $adminHeaders));

    $sent = send_reservation_mail($adminTo, $adminSubject, $adminBody, $headerStr);
    if ($sent) {
        $mailSent = true;
    } else {
        error_log("Reservation submit: Admin mail send failed to: {$adminTo}");
    }
}

// お客様向けメール送信
if (!empty($customerEmail)) {
    $customerSubject = $templateSettings['auto_reply_subject'] ?? "ネット予約";
    $customerSubject = str_replace(array_keys($placeholders), array_values($placeholders), $customerSubject);
    
    $customerBody = $templateSettings['auto_reply_body'] ?? "";
    if (empty($customerBody)) {
         // フォールバック
         $customerBody = "{customer_name} 様\n\nこの度は{tenant_name}をご利用いただき...";
    }
    $customerBody = replacePlaceholders($customerBody, $placeholders);

    $customerHeaders = [
        'From' => $fromHeader,
        'Content-Type' => 'text/plain; charset=UTF-8',
        'X-Mailer' => 'PHP/' . phpversion()
    ];
    $customerHeaderStr = implode("\r\n", array_map(function ($k, $v) {
        return $k . ': ' . $v;
    }, array_keys($customerHeaders), $customerHeaders));

    $customerMailSent = send_reservation_mail(
        $customerEmail,
        $customerSubject,
        $customerBody,
        $customerHeaderStr
    );

    if (!$customerMailSent) {
        error_log("Reservation submit: Customer mail send failed to: {$customerEmail}");
    }
}


// 完了ページへリダイレクト
// 完了ページへリダイレクトせず、アラートを表示してフォームへ戻る
echo "<script>
    alert('予約を送信しました');
    window.location.href = '/app/front/yoyaku.php';
</script>";
exit;
