<?php
/**
 * pullcass - 予約送信API
 * 参考: reference/public_html/yoyaku/submit_reservation.php
 */

session_start();

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/mail_helper.php';
require_once __DIR__ . '/../../../includes/reservation_placeholders.php';

// POSTリクエストのみ許可
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /yoyaku');
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
    header('Location: /yoyaku' . (isset($_POST['cast_id']) && $_POST['cast_id'] ? '?cast_id=' . (int)$_POST['cast_id'] : ''));
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
$eventCampaign = filter_input(INPUT_POST, 'event_campaign', FILTER_SANITIZE_SPECIAL_CHARS) ?: '';
$courseContentId = filter_input(INPUT_POST, 'course_content', FILTER_VALIDATE_INT) ?: null;
$optionIds = $_POST['options'] ?? []; // 配列で受け取る

// 予約日時を文字列に整形
$reservationDateFormatted = '';
$reservationTimeFormatted = $reservationTime;

if ($reservationDate) {
    if (strtotime($reservationDate)) {
        $reservationDateFormatted = date('n/j', strtotime($reservationDate));
        $dayOfWeekNames = ['日', '月', '火', '水', '木', '金', '土'];
        $reservationDateFormatted .= '(' . $dayOfWeekNames[date('w', strtotime($reservationDate))] . ')';
    } else {
        $reservationDateFormatted = $reservationDate; // パースできない場合はそのまま
    }
}

// 金額パース用関数
function parsePriceAndInt($str) {
    if (empty($str)) return 0;
    if (strpos($str, '無料') !== false) return 0;
    // 全角数字を半角に
    $str = mb_convert_kana($str, 'n');
    // 数字以外を除去
    $str = preg_replace('/[^0-9]/', '', $str);
    return (int)$str;
}


// バリデーション
$errors = [];

if (empty($reservationDate)) {
    $errors[] = '利用予定日を選択してください';
}

if (empty($reservationTime)) {
    $errors[] = '希望時刻を選択してください';
}

if (empty($confirmDate)) {
    $errors[] = '確認電話可能日を選択してください';
}
if (empty($confirmStartTime)) {
    $errors[] = '確認電話開始時刻を選択してください';
}
if (empty($confirmEndTime)) {
    $errors[] = '確認電話終了時刻を選択してください';
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

if (empty($customerEmail)) {
    $errors[] = 'メールアドレスを入力してください';
} elseif (!filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'メールアドレスの形式が正しくありません';
}

if ($nominationType === 'shimei' && empty($castId)) {
    $errors[] = 'キャストを選択してください';
}

// エラーがある場合は戻る
if (!empty($errors)) {
    $_SESSION['reservation_errors'] = $errors;
    $_SESSION['reservation_form_data'] = $_POST;
    header('Location: /yoyaku' . ($castId ? '?cast_id=' . $castId : ''));
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

// 顧客マスタの検索または作成（電話番号で紐付け、テナント単位）
$customerId = null;
$customerReservationCount = null; // プレースホルダー用（当店N回目表示）
if ($customerPhone && $pdo) {
    try {
        $phoneNormalized = preg_replace('/[^0-9]/', '', $customerPhone);
        if ($phoneNormalized !== '') {
            $stmt = $pdo->prepare("SELECT id, reservation_count FROM tenant_customers WHERE tenant_id = ? AND phone = ?");
            $stmt->execute([$tenantId, $phoneNormalized]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                $customerId = (int)$existing['id'];
                $newCount = (int)($existing['reservation_count'] ?? 0) + 1;
                $customerReservationCount = $newCount;
                $stmtUpd = $pdo->prepare("UPDATE tenant_customers SET name = ?, email = ?, reservation_count = ?, last_reservation_at = NOW(), updated_at = NOW() WHERE id = ?");
                $stmtUpd->execute([$customerName, $customerEmail ?: null, $newCount, $customerId]);
            } else {
                $stmtIns = $pdo->prepare("INSERT INTO tenant_customers (tenant_id, phone, email, name, reservation_count, first_reservation_at, last_reservation_at) VALUES (?, ?, ?, ?, 1, NOW(), NOW())");
                $stmtIns->execute([$tenantId, $phoneNormalized, $customerEmail ?: null, $customerName]);
                $customerId = (int)$pdo->lastInsertId();
                $customerReservationCount = 1;
            }
        }
    } catch (PDOException $e) {
        // tenant_customers が未作成の場合はスキップ
        $customerId = null;
    }
}

// メール通知と同様のロジックでコース名・オプション・合計をINSERT前に計算
$courseDisplay = $course ?: '未選択';
$coursePriceVal = 0;
$optionsJson = json_encode($optionIds ?: []);
$optionsPriceVal = 0;

// オプション金額を計算
if (!empty($optionIds) && is_array($optionIds) && $pdo) {
    try {
        $placeholdersStr = implode(',', array_fill(0, count($optionIds), '?'));
        $stmtOpt = $pdo->prepare("SELECT time_label, price_label FROM price_rows_published WHERE id IN ($placeholdersStr)");
        $stmtOpt->execute($optionIds);
        while ($row = $stmtOpt->fetch(PDO::FETCH_ASSOC)) {
            $optionsPriceVal += parsePriceAndInt($row['price_label'] ?? '');
        }
    } catch (Exception $e) { /* ignore */ }
}

// コース名・金額を取得（メール通知と完全同一ロジック）
if ($course && $pdo) {
    try {
        if ($course === 'other') {
            $courseDisplay = 'その他';
        } elseif (is_numeric($course)) {
            $stmt = $pdo->prepare("
                SELECT pt.table_name, pc.admin_title
                FROM price_tables_published pt
                LEFT JOIN price_contents_published pc ON pt.content_id = pc.id
                WHERE pt.id = ?
            ");
            $stmt->execute([$course]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $baseCourseName = $row['table_name'] ?: $row['admin_title'];
                $courseDisplay = $baseCourseName;
                if ($courseContentId) {
                    $stmtRow = $pdo->prepare("SELECT time_label, price_label FROM price_rows_published WHERE id = ?");
                    $stmtRow->execute([$courseContentId]);
                    $rowDetail = $stmtRow->fetch(PDO::FETCH_ASSOC);
                    if ($rowDetail) {
                        $courseTimeLabel = $rowDetail['time_label'] ?? '';
                        $coursePriceLabel = $rowDetail['price_label'] ?? '';
                        $coursePriceVal = parsePriceAndInt($coursePriceLabel);
                        $parts = array_filter([$baseCourseName, $courseTimeLabel, $coursePriceLabel]);
                        $courseDisplay = implode(' ', $parts);
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("Course detail fetch (pre-insert) error: " . $e->getMessage());
    }
}
$totalAmountVal = $coursePriceVal + $optionsPriceVal;

// データベースに保存（メール通知と同様の情報をすべて保存）
try {
    $stmt = $pdo->prepare("
        INSERT INTO tenant_reservations (
            tenant_id, customer_id, cast_id, cast_name, nomination_type,
            reservation_date, reservation_time, contact_available_time,
            customer_type, course, course_content_id, facility_type, facility_detail,
            customer_name, customer_phone, customer_email, message, event_campaign,
            options, options_price, total_price, status, created_at
        ) VALUES (
            :tenant_id, :customer_id, :cast_id, :cast_name, :nomination_type,
            :reservation_date, :reservation_time, :contact_available_time,
            :customer_type, :course, :course_content_id, :facility_type, :facility_detail,
            :customer_name, :customer_phone, :customer_email, :message, :event_campaign,
            :options, :options_price, :total_price, 'new', NOW()
        )
    ");

    $stmt->execute([
        'tenant_id' => $tenantId,
        'customer_id' => $customerId,
        'cast_id' => $castId,
        'cast_name' => $castName,
        'nomination_type' => $nominationType,
        'reservation_date' => $reservationDate,
        'reservation_time' => $reservationTime,
        'contact_available_time' => $contactAvailableTime,
        'customer_type' => $customerType,
        'course' => $course,
        'course_content_id' => $courseContentId,
        'facility_type' => $facilityType,
        'facility_detail' => $facilityDetail,
        'customer_name' => $customerName,
        'customer_phone' => $customerPhone,
        'customer_email' => $customerEmail,
        'message' => $message,
        'event_campaign' => $eventCampaign,
        'options' => $optionsJson,
        'options_price' => $optionsPriceVal,
        'total_price' => $totalAmountVal
    ]);

    $reservationId = $pdo->lastInsertId();

} catch (PDOException $e) {
    // マイグレーション未適用時は旧形式でリトライ
    $msg = $e->getMessage();
    if (strpos($msg, 'course_content_id') !== false || strpos($msg, 'event_campaign') !== false || strpos($msg, 'customer_id') !== false) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO tenant_reservations (
                    tenant_id, cast_id, cast_name, nomination_type,
                    reservation_date, reservation_time, contact_available_time,
                    customer_type, course, facility_type, facility_detail,
                    customer_name, customer_phone, customer_email, message,
                    options, options_price, total_price, status, created_at
                ) VALUES (
                    :tenant_id, :cast_id, :cast_name, :nomination_type,
                    :reservation_date, :reservation_time, :contact_available_time,
                    :customer_type, :course, :facility_type, :facility_detail,
                    :customer_name, :customer_phone, :customer_email, :message,
                    :options, :options_price, :total_price, 'new', NOW()
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
                'message' => $message,
                'options' => $optionsJson,
                'options_price' => $optionsPriceVal,
                'total_price' => $totalAmountVal
            ]);
            $reservationId = $pdo->lastInsertId();
        } catch (PDOException $e2) {
            error_log("Reservation insert (fallback) error: " . $e2->getMessage());
            $_SESSION['reservation_errors'] = ['予約の保存に失敗しました。しばらく経ってから再度お試しください。'];
            $_SESSION['reservation_form_data'] = $_POST;
            header('Location: /yoyaku' . ($castId ? '?cast_id=' . $castId : ''));
            exit;
        }
    } else {
        error_log("Reservation insert error: " . $e->getMessage());
        $_SESSION['reservation_errors'] = ['予約の保存に失敗しました。しばらく経ってから再度お試しください。'];
        $_SESSION['reservation_form_data'] = $_POST;
        header('Location: /yoyaku' . ($castId ? '?cast_id=' . $castId : ''));
        exit;
    }
}

// メール送信用プレースホルダー（共通関数で構築）
$reservationForPlaceholders = [
    'customer_name' => $customerName,
    'customer_reservation_count' => $customerReservationCount,
    'customer_phone' => $customerPhone,
    'customer_email' => $customerEmail,
    'reservation_date' => $reservationDate,
    'reservation_time' => $reservationTime,
    'contact_available_time' => $contactAvailableTime,
    'nomination_type' => $nominationType,
    'cast_name' => $castName,
    'customer_type' => $customerType,
    'course' => $course,
    'course_content_id' => $courseContentId,
    'facility_type' => $facilityType,
    'facility_detail' => $facilityDetail,
    'message' => $message,
    'event_campaign' => $eventCampaign,
    'options' => $optionsJson,
    'total_price' => $totalAmountVal,
    'created_at' => date('Y-m-d H:i:s'),
];
$placeholders = buildReservationPlaceholders($reservationForPlaceholders, $tenant, $pdo, $reservationId);

// テンプレートの取得
$templateSettings = [];
try {
    $stmt = $pdo->prepare("SELECT auto_reply_subject, auto_reply_body, admin_notify_subject, admin_notify_body FROM tenant_reservation_settings WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $templateSettings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    $templateSettings = [];
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
    $adminBody = replaceReservationPlaceholders($adminBody, $placeholders);

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
    $customerBody = replaceReservationPlaceholders($customerBody, $placeholders);

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
    window.location.href = '/yoyaku';
</script>";
exit;
