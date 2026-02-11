<?php
/**
 * 予約プレースホルダー - メール通知・管理画面共通
 *
 * tenant_reservation_settings の admin_notify_body / auto_reply_body で
 * 使用するプレースホルダーを構築する共通関数
 *
 * 使用箇所:
 * - 予約送信時メール (submit.php)
 * - 予約詳細画面 (detail.php)
 */

if (!function_exists('buildReservationPlaceholders')) {

/**
 * 予約データからプレースホルダー配列を構築
 *
 * @param array $reservation 予約データ（DB取得分 or 送信時組み立て）
 *   必須キー: customer_name, customer_phone, reservation_date, reservation_time, ...
 * @param array $tenant テナント情報（name, domain/code, phone, business_hours 等）
 * @param PDO $pdo DB接続
 * @param int|null $reservationId 予約ID（送信直後は lastInsertId、詳細表示時は $reservation['id']）
 * @return array ['{placeholder}' => 'value', ...]
 */
function buildReservationPlaceholders(array $reservation, array $tenant, PDO $pdo, ?int $reservationId = null) {
    $id = $reservationId ?? ($reservation['id'] ?? '');
    $customerName = $reservation['customer_name'] ?? '';
    $reservationCount = $reservation['customer_reservation_count'] ?? null;
    // 利用回数（「名前：{customer_name}{reservation_count}」のように使用。店舗・顧客両方で利用可）
    $reservationCountStr = '';
    if ($reservationCount !== null && (int)$reservationCount > 0) {
        $reservationCountStr = '（ネット予約' . (int)$reservationCount . '回目のご予約）';
    }
    $customerPhone = $reservation['customer_phone'] ?? '';
    $customerEmail = $reservation['customer_email'] ?? '';
    $resDate = $reservation['reservation_date'] ?? '';
    $resTime = $reservation['reservation_time'] ?? '';
    $contactTime = $reservation['contact_available_time'] ?? '';
    $nominationType = $reservation['nomination_type'] ?? 'free';
    $castName = $reservation['cast_name'] ?? '';
    $customerType = $reservation['customer_type'] ?? 'new';
    $courseRaw = $reservation['course'] ?? '';
    $courseContentId = $reservation['course_content_id'] ?? null;
    $facilityType = $reservation['facility_type'] ?? 'home';
    $facilityDetail = $reservation['facility_detail'] ?? '';
    $message = $reservation['message'] ?? '';
    $eventCampaign = $reservation['event_campaign'] ?? '';
    $optionsJson = $reservation['options'] ?? null;
    $totalPrice = (int)($reservation['total_price'] ?? 0);
    $createdAt = $reservation['created_at'] ?? '';

    $tenantId = (int)($tenant['id'] ?? 0);

    // 日付・時刻の整形
    $dateFormatted = '';
    if ($resDate && strtotime($resDate)) {
        $dateFormatted = date('n/j', strtotime($resDate));
        $dayNames = ['日', '月', '火', '水', '木', '金', '土'];
        $dateFormatted .= '(' . $dayNames[date('w', strtotime($resDate))] . ')';
    }
    $timeFormatted = $resTime ?: '';

    // キャスト名
    $castDisplay = ($nominationType === 'shimei' && $castName) ? $castName : 'フリー';

    // 利用形態
    $customerTypeText = ($customerType === 'member') ? '2回目以降の利用' : '初めての利用';

    // コース名の解決
    $courseDisplay = $courseRaw ?: '未選択';
    if ($courseRaw && $pdo) {
        try {
            if ($courseRaw === 'other') {
                $courseDisplay = 'その他';
            } elseif (is_numeric($courseRaw)) {
                $stmt = $pdo->prepare("
                    SELECT pt.table_name, pc.admin_title
                    FROM price_tables_published pt
                    LEFT JOIN price_contents_published pc ON pt.content_id = pc.id
                    WHERE pt.id = ?
                ");
                $stmt->execute([$courseRaw]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $base = $row['table_name'] ?: $row['admin_title'];
                    $courseDisplay = $base;
                    if ($courseContentId) {
                        $stmtR = $pdo->prepare("SELECT time_label, price_label FROM price_rows_published WHERE id = ?");
                        $stmtR->execute([$courseContentId]);
                        $rd = $stmtR->fetch(PDO::FETCH_ASSOC);
                        if ($rd) {
                            $parts = array_filter([$base, $rd['time_label'] ?? '', $rd['price_label'] ?? '']);
                            $courseDisplay = implode(' ', $parts);
                        }
                    }
                }
            }
        } catch (Exception $e) { /* ignore */ }
    }

    // オプションの解決
    $optionDisplay = 'なし';
    if ($optionsJson) {
        $optionIds = is_string($optionsJson) ? json_decode($optionsJson, true) : $optionsJson;
        if (!empty($optionIds) && is_array($optionIds) && $pdo) {
            try {
                $ph = implode(',', array_fill(0, count($optionIds), '?'));
                $stmt = $pdo->prepare("SELECT time_label, price_label FROM price_rows_published WHERE id IN ($ph)");
                $stmt->execute(array_values($optionIds));
                $labels = [];
                while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $l = $r['time_label'] ?? '';
                    if (!empty($r['price_label'])) $l .= ' (' . $r['price_label'] . ')';
                    if ($l) $labels[] = $l;
                }
                if (!empty($labels)) $optionDisplay = implode('、', $labels);
            } catch (Exception $e) { /* ignore */ }
        }
    }

    // 施設
    $facilityLabelAdmin = ($facilityType === 'hotel') ? 'ホテル' : '自宅';
    $facilityDisplay = $facilityDetail ?: $facilityLabelAdmin;

    // 合計金額
    $totalAmountStr = $totalPrice > 0 ? '¥' . number_format($totalPrice) : '';

    // 受信時刻
    $createdAtFormatted = $createdAt ? date('Y-m-d H:i:s', strtotime($createdAt)) : date('Y-m-d H:i:s');

    // 店舗情報
    $tenantName = $tenant['name'] ?? '';
    $domainOrSubdomain = trim($tenant['domain'] ?? '') ?: (($tenant['code'] ?? $tenant['slug'] ?? '') . '.pullcass.com');
    $tenantHp = 'https://' . $domainOrSubdomain . '/';
    $tenantTel = $tenant['phone'] ?? '';
    $tenantHours = $tenant['business_hours'] ?? '';

    if ($tenantHours === '' && $tenantId && $pdo) {
        try {
            $stmt = $pdo->prepare("SELECT accept_start_time, accept_end_time FROM tenant_reservation_settings WHERE tenant_id = ?");
            $stmt->execute([$tenantId]);
            $s = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($s) {
                $st = substr($s['accept_start_time'] ?? '10:00', 0, 5);
                $et = substr($s['accept_end_time'] ?? '02:00', 0, 5);
                $eh = (int)substr($et, 0, 2);
                if ($eh < (int)substr($st, 0, 2)) {
                    $et = '翌' . $et;
                }
                $tenantHours = "{$st}〜{$et}";
            }
        } catch (Exception $e) { /* ignore */ }
    }

    return [
        '{reservation_id}' => $id,
        '{customer_name}' => $customerName,
        '{reservation_count}' => $reservationCountStr,
        '{customer_phone}' => $customerPhone,
        '{customer_email}' => $customerEmail,
        '{date}' => $dateFormatted,
        '{time}' => $timeFormatted,
        '{confirm_time}' => $contactTime,
        '{cast_name}' => $castDisplay,
        '{customer_type}' => $customerTypeText,
        '{course}' => $courseDisplay,
        '{option}' => $optionDisplay,
        '{event}' => $eventCampaign ?: 'なし',
        '{facility}' => $facilityDisplay,
        '{facility_label_admin}' => $facilityLabelAdmin,
        '{notes}' => $message,
        '{total_amount}' => $totalAmountStr,
        '{created_at}' => $createdAtFormatted,
        '{tenant_name}' => $tenantName,
        '{tenant_hp}' => $tenantHp,
        '{tenant_tel}' => $tenantTel,
        '{tenant_hours}' => $tenantHours,
    ];
}

/**
 * テキスト内のプレースホルダーを置換
 */
function replaceReservationPlaceholders(string $text, array $placeholders): string {
    return str_replace(array_keys($placeholders), array_values($placeholders), $text);
}

}
