<?php
/**
 * pullcass - ネット予約フォームページ
 * 参考: reference/public_html/yoyaku.php
 */

session_start();

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/theme_helper.php';

// テナント情報を取得
$tenantFromRequest = getTenantFromRequest();
$tenantFromSession = getCurrentTenant();

if ($tenantFromRequest) {
    $tenant = $tenantFromRequest;
    if (!$tenantFromSession || $tenantFromSession['id'] !== $tenant['id']) {
        setCurrentTenant($tenant);
    }
} elseif ($tenantFromSession) {
    $tenant = $tenantFromSession;
} else {
    header('Location: https://pullcass.com/');
    exit;
}

// 店舗情報
$shopName = $tenant['name'];
$shopCode = $tenant['code'];
$tenantId = $tenant['id'];
$shopTitle = $tenant['title'] ?? '';
$shopDescription = $tenant['description'] ?? '';

// ロゴ画像
$logoLargeUrl = $tenant['logo_large_url'] ?? '';
$logoSmallUrl = $tenant['logo_small_url'] ?? '';
$faviconUrl = $tenant['favicon_url'] ?? '';

// 電話番号
$phoneNumber = $tenant['phone'] ?? '';

// 営業時間
$businessHours = $tenant['business_hours'] ?? '';
$businessHoursNote = $tenant['business_hours_note'] ?? '';

// テーマを取得
$currentTheme = getCurrentTheme($tenantId);
$themeData = $currentTheme['theme_data'];

// データベース接続を取得
$pdo = getPlatformDb();

// キャストIDを取得（指名予約の場合）
$castId = filter_input(INPUT_GET, 'cast_id', FILTER_VALIDATE_INT);
$cast = null;

if ($castId && $pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, name, img1, day1, day2, day3, day4, day5, day6, day7
            FROM tenant_casts
            WHERE id = ? AND tenant_id = ? AND checked = 1
        ");
        $stmt->execute([$castId, $tenantId]);
        $cast = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Yoyaku cast fetch error: " . $e->getMessage());
    }
}

// ページタイトル
$pageTitle = 'ネット予約｜' . $shopName;
$pageDescription = $shopName . 'のネット予約フォームです。';

// セッションからエラーとフォームデータを取得
$errors = $_SESSION['reservation_errors'] ?? [];
$formData = $_SESSION['reservation_form_data'] ?? [];
unset($_SESSION['reservation_errors'], $_SESSION['reservation_form_data']);

// 予約機能設定を取得（確認電話時間のデフォルト値として使用）
$acceptStartTime = '10:30';
$acceptEndTime = '26:00'; // デフォルトは深夜2時（24+2=26）

if ($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT accept_start_time, accept_end_time FROM tenant_reservation_settings WHERE tenant_id = ?");
        $stmt->execute([$tenantId]);
        $reservationSettings = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($reservationSettings && $reservationSettings['accept_start_time']) {
            $acceptStartTime = substr($reservationSettings['accept_start_time'], 0, 5);
        }
        if ($reservationSettings && $reservationSettings['accept_end_time']) {
            $endTime = substr($reservationSettings['accept_end_time'], 0, 5);
            // 深夜時間帯（00:00〜05:59）を24時以降の表記に変換
            $endHour = (int) substr($endTime, 0, 2);
            if ($endHour >= 0 && $endHour <= 5) {
                $acceptEndTime = (24 + $endHour) . ':' . substr($endTime, 3, 2);
            } else {
                $acceptEndTime = $endTime;
            }
        }
    } catch (Exception $e) {
        error_log("Reservation settings fetch error: " . $e->getMessage());
    }
}

// 料金表からコースとオプションを取得
$courses = [];
$courseRows = [];
$options = [];

if ($pdo) {
    try {
        // 現在有効な料金セットを取得（公開テーブルから）
        $now = date('Y-m-d H:i:s');

        // 特別期間を優先
        $stmt = $pdo->prepare("
            SELECT id FROM price_sets_published
            WHERE set_type = 'special' AND is_active = 1 
            AND start_datetime <= ? AND end_datetime >= ?
            ORDER BY start_datetime ASC LIMIT 1
        ");
        $stmt->execute([$now, $now]);
        $activePriceSet = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$activePriceSet) {
            // 平常期間
            $stmt = $pdo->query("
                SELECT id FROM price_sets_published
                WHERE set_type = 'regular' AND is_active = 1 LIMIT 1
            ");
            $activePriceSet = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($activePriceSet) {
            $setId = $activePriceSet['id'];

            // ネット予約連動のコースを取得
            $stmt = $pdo->prepare("
                SELECT pc.id as content_id, pt.id as table_id, pt.table_name, pc.admin_title
                FROM price_contents_published pc
                INNER JOIN price_tables_published pt ON pt.content_id = pc.id
                WHERE pc.set_id = ? AND pc.is_active = 1 AND pt.is_reservation_linked = 1
                ORDER BY pc.display_order ASC
            ");
            $stmt->execute([$setId]);
            $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 各コースの内容（行）を取得
            foreach ($courses as $course) {
                $stmt = $pdo->prepare("
                    SELECT id, time_label, price_label
                    FROM price_rows_published
                    WHERE table_id = ?
                    ORDER BY display_order ASC
                ");
                $stmt->execute([$course['table_id']]);
                $courseRows[$course['table_id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            // オプションを取得
            $stmt = $pdo->prepare("
                SELECT pc.id as content_id, pt.id as table_id, pt.table_name, pc.admin_title
                FROM price_contents_published pc
                INNER JOIN price_tables_published pt ON pt.content_id = pc.id
                WHERE pc.set_id = ? AND pc.is_active = 1 AND pt.is_option = 1
                ORDER BY pc.display_order ASC
            ");
            $stmt->execute([$setId]);
            $optionTables = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 各オプションテーブルの行を取得
            foreach ($optionTables as $optTable) {
                $stmt = $pdo->prepare("
                    SELECT id, time_label, price_label
                    FROM price_rows_published
                    WHERE table_id = ?
                    ORDER BY display_order ASC
                ");
                $stmt->execute([$optTable['table_id']]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $options[] = [
                    'table_id' => $optTable['table_id'],
                    'table_name' => $optTable['table_name'],
                    'rows' => $rows
                ];
            }
        }
    } catch (Exception $e) {
        error_log("Price table fetch error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <?php include __DIR__ . '/includes/head.php'; ?>
    <style>
        /* 予約フォーム固有のスタイル */
        .yoyaku-form {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .form-section {
            background: rgba(255, 255, 255, 0.6);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .form-section-title {
            font-size: 1.1em;
            font-weight: bold;
            color: var(--color-primary);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--color-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-section-title .required {
            background: #e74c3c;
            color: white;
            font-size: 0.7em;
            padding: 2px 6px;
            border-radius: 3px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            color: var(--color-text);
        }

        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="tel"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            background: white;
            box-sizing: border-box;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 3px rgba(255, 107, 157, 0.2);
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .radio-group {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .radio-item {
            display: flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
        }

        .radio-item input[type="radio"] {
            width: 18px;
            height: 18px;
            accent-color: var(--color-primary);
        }

        .checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .checkbox-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--color-primary);
        }

        /* 指名形態切り替え */
        .nomination-toggle {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .nomination-btn {
            flex: 1;
            padding: 15px;
            border: 2px solid var(--color-primary);
            border-radius: 10px;
            background: white;
            color: var(--color-primary);
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }

        .nomination-btn.active {
            background: var(--color-primary);
            color: white;
        }

        .nomination-btn:hover {
            opacity: 0.8;
        }

        /* キャスト選択カード */
        .cast-select-card {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: white;
            border-radius: 10px;
            border: 2px solid #ddd;
            margin-bottom: 15px;
        }

        .cast-select-card.selected {
            border-color: var(--color-primary);
            background: rgba(255, 107, 157, 0.1);
        }

        .cast-select-card img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
        }

        .cast-select-card .cast-name {
            font-weight: bold;
            font-size: 1.1em;
        }

        /* 日付・時間選択 */
        .date-time-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .date-time-row .form-group {
            flex: 1;
            min-width: 200px;
        }

        /* 合計金額表示 */
        .total-price-section {
            background: var(--color-primary);
            color: white;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 20px;
        }

        .total-price-label {
            font-size: 1em;
            margin-bottom: 5px;
        }

        .total-price-value {
            font-size: 2em;
            font-weight: bold;
        }

        /* 送信ボタン */
        .submit-btn {
            width: 100%;
            padding: 18px;
            background: var(--color-primary);
            color: white;
            border: none;
            border-radius: 30px;
            font-size: 1.2em;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
        }

        .submit-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* 注意事項 */
        .notice-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 0.9em;
            color: #856404;
        }

        .notice-box ul {
            margin: 10px 0 0 20px;
            padding: 0;
        }

        .notice-box li {
            margin-bottom: 5px;
        }

        /* 非表示セクション */
        .hidden {
            display: none !important;
        }

        /* オプション選択用スタイル */
        .checkbox-label-inline {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            font-size: 1em;
        }

        .checkbox-label-inline input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .option-group {
            margin-bottom: 20px;
            padding: 15px;
            background: rgba(255, 255, 255, 0.5);
            border-radius: 10px;
        }

        .option-group-title {
            font-weight: bold;
            color: var(--color-primary);
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 2px solid var(--color-primary);
        }

        .option-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            margin-bottom: 5px;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .option-item:hover {
            background: rgba(255, 255, 255, 1);
            transform: translateX(5px);
        }

        .option-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .option-name {
            flex: 1;
        }

        .option-price {
            color: var(--color-primary);
            font-weight: bold;
        }

        /* レスポンシブ */
        @media screen and (max-width: 768px) {
            .yoyaku-form {
                padding: 10px;
            }

            .form-section {
                padding: 15px;
            }

            .nomination-toggle {
                flex-direction: column;
            }

            .date-time-row {
                flex-direction: column;
            }

            .date-time-row .form-group {
                min-width: 100%;
            }
        }
    </style>
</head>

<body>
    <?php include __DIR__ . '/includes/header.php'; ?>

    <main class="main-content">
        <!-- パンくず -->
        <nav class="breadcrumb">
            <a href="/app/front/index.php">ホーム</a><span>»</span>
            <a href="/app/front/top.php">トップ</a><span>»</span>
            <?php if ($cast): ?>
                <a href="/app/front/cast/list.php">キャスト一覧</a><span>»</span>
                <a
                    href="/app/front/cast/detail.php?id=<?php echo h($castId); ?>"><?php echo h($cast['name']); ?></a><span>»</span>
            <?php endif; ?>
            ネット予約 |
        </nav>

        <!-- タイトルセクション -->
        <section class="title-section" style="margin-bottom: 20px;">
            <h1>RESERVE</h1>
            <h2>ネット予約</h2>
            <div class="dot-line"></div>
        </section>

        <!-- 予約フォーム -->
        <form id="yoyaku-form" class="yoyaku-form" action="/app/front/yoyaku/submit.php" method="POST">
            <!-- エラー表示 -->
            <?php if (!empty($errors)): ?>
                <div class="error-box"
                    style="background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 10px; padding: 15px; margin-bottom: 20px; color: #721c24;">
                    <strong>⚠️ 入力内容をご確認ください</strong>
                    <ul style="margin: 10px 0 0 20px; padding: 0;">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo h($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- 注意事項 -->
            <div class="notice-box">
                <strong>⚠️ ご予約前にご確認ください</strong>
                <ul>
                    <li>ネット予約は仮予約となります。お店からの確認連絡をもって予約確定となります。</li>
                    <li>ご希望の日時・キャストが確保できない場合がございます。</li>
                    <li>お急ぎの場合はお電話でのご予約をお勧めします。</li>
                </ul>
            </div>

            <!-- 指名形態選択 -->
            <div class="form-section">
                <div class="form-section-title">
                    <span>👤</span> 指名形態
                    <span class="required">必須</span>
                </div>
                <div class="nomination-toggle">
                    <button type="button" class="nomination-btn <?php echo $cast ? 'active' : ''; ?>" data-type="shimei"
                        onclick="setNominationType('shimei')">
                        指名あり
                    </button>
                    <button type="button" class="nomination-btn <?php echo !$cast ? 'active' : ''; ?>" data-type="free"
                        onclick="setNominationType('free')">
                        フリー（指名なし）
                    </button>
                </div>
                <input type="hidden" name="nomination_type" id="nomination_type"
                    value="<?php echo $cast ? 'shimei' : 'free'; ?>">

                <!-- 指名ありの場合のキャスト表示 -->
                <div id="shimei-section" class="<?php echo $cast ? '' : 'hidden'; ?>">
                    <?php if ($cast): ?>
                        <div class="cast-select-card selected">
                            <img src="<?php echo h($cast['img1'] ?? '/img/hp/hc_logo.png'); ?>"
                                alt="<?php echo h($cast['name']); ?>">
                            <div>
                                <div class="cast-name"><?php echo h($cast['name']); ?></div>
                                <div style="font-size: 0.9em; color: #666;">指名予約</div>
                            </div>
                        </div>
                        <input type="hidden" name="cast_id" id="cast_id" value="<?php echo h($castId); ?>">
                        <input type="hidden" name="cast_name" id="cast_name" value="<?php echo h($cast['name']); ?>">
                    <?php else: ?>
                        <div class="form-group">
                            <label>キャストを選択してください</label>
                            <select name="cast_id" id="cast_id" onchange="onCastSelect(this)">
                                <option value="">-- キャストを選択 --</option>
                                <?php foreach ($allCasts as $c): ?>
                                    <option value="<?php echo h($c['id']); ?>"><?php echo h($c['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- フリーの場合 -->
                <div id="free-section" class="<?php echo $cast ? 'hidden' : ''; ?>">
                    <p style="color: #666; font-size: 0.9em;">
                        フリー予約の場合、当日の出勤状況に応じてキャストをご案内いたします。
                    </p>
                </div>
            </div>

            <!-- 利用予定日時 -->
            <div class="form-section">
                <div class="form-section-title">
                    <span>📅</span> 利用予定日時
                    <span class="required">必須</span>
                </div>
                <div class="date-time-row">
                    <div class="form-group">
                        <label>利用予定日</label>
                        <select name="reservation_date" id="reservation_date" required>
                            <?php if ($cast): ?>
                                <option value="">キャストの出勤日を選択</option>
                            <?php else: ?>
                                <option value="">-- 日付を選択 --</option>
                                <?php
                                // フリー予約の場合：明日から7日分の日付を生成
                                $dayOfWeekNames = ['日', '月', '火', '水', '木', '金', '土'];
                                for ($i = 1; $i <= 7; $i++) {
                                    $date = new DateTime();
                                    $date->modify("+{$i} days");
                                    $dateStr = $date->format('Y-m-d');
                                    $displayStr = $date->format('n/j') . '(' . $dayOfWeekNames[$date->format('w')] . ')';
                                    echo '<option value="' . h($dateStr) . '">' . h($displayStr) . '</option>';
                                }
                                ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>希望時刻</label>
                        <select name="reservation_time" id="reservation_time" required>
                            <?php if ($cast): ?>
                                <option value="">日付を選択してください</option>
                            <?php else: ?>
                                <option value="">-- 時刻を選択 --</option>
                                <?php
                                // フリー予約の場合：11:00〜翌2:00まで30分刻み
                                for ($h = 11; $h <= 25; $h++) {
                                    $displayHour = $h > 24 ? $h - 24 : $h;
                                    $prefix = $h >= 24 ? '翌' : '';
                                    for ($m = 0; $m < 60; $m += 30) {
                                        $timeStr = sprintf('%02d:%02d', $h, $m);
                                        $displayStr = $prefix . sprintf('%d:%02d', $displayHour, $m);
                                        echo '<option value="' . h($timeStr) . '">' . h($displayStr) . '</option>';
                                    }
                                }
                                ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- 確認電話可能日時 -->
            <div class="form-section">
                <div class="form-section-title">
                    <span>📞</span> 確認電話可能日時
                    <span class="required">必須</span>
                </div>
                <p style="color: #666; font-size: 0.9em; margin-bottom: 15px;">
                    お店からの確認電話が可能な日時を選択してください。
                </p>
                <div class="date-time-row">
                    <div class="form-group">
                        <label>確認電話可能日</label>
                        <select name="confirm_date" id="confirm_date" required>
                            <option value="">日付を選択してください</option>
                        </select>
                    </div>
                </div>
                <div class="date-time-row">
                    <div class="form-group">
                        <label>開始時刻</label>
                        <select name="confirm_start_time" id="confirm_start_time" required>
                            <option value="">時間を選択</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>終了時刻</label>
                        <select name="confirm_end_time" id="confirm_end_time" required>
                            <option value="">時間を選択</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- 利用形態 -->
            <div class="form-section">
                <div class="form-section-title">
                    <span>🏠</span> 利用形態
                    <span class="required">必須</span>
                </div>
                <div class="radio-group">
                    <label class="radio-item">
                        <input type="radio" name="customer_type" value="new" required>
                        <span>初めて利用</span>
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="customer_type" value="member">
                        <span>2回目以降の利用</span>
                    </label>
                </div>
            </div>

            <!-- コース選択 -->
            <div class="form-section">
                <div class="form-section-title">
                    <span>⏱️</span> コース選択
                    <span class="required">必須</span>
                </div>
                <div class="form-group">
                    <label>ご希望のコース</label>
                    <select name="course" id="course" required>
                        <option value="">-- コースを選択 --</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo h($course['table_id']); ?>" data-table-id="<?php echo h($course['table_id']); ?>">
                                <?php echo h($course['table_name'] ?: $course['admin_title']); ?>
                            </option>
                        <?php endforeach; ?>
                        <?php if (empty($courses)): ?>
                            <option value="other">その他</option>
                        <?php endif; ?>
                    </select>
                </div>
                
                <!-- コース内容選択（courseが選択されたら表示） -->
                <div class="form-group" id="course_content_wrapper" style="display: none; margin-top: 15px;">
                    <label>コース内容を選択</label>
                    <select name="course_content" id="course_content">
                        <option value="">-- コース内容を選択 --</option>
                    </select>
                </div>
            </div>

            <?php if (!empty($options)): ?>
            <!-- オプション選択 -->
            <div class="form-section">
                <div class="form-section-title">
                    <span>➕</span> オプションを追加
                </div>
                <div class="form-group">
                    <label class="checkbox-label-inline">
                        <input type="checkbox" id="option_toggle">
                        <span>オプションを追加する</span>
                    </label>
                </div>
                
                <div id="option_container" style="display: none;">
                    <?php foreach ($options as $optTable): ?>
                        <?php if (!empty($optTable['rows'])): ?>
                        <div class="option-group">
                            <div class="option-group-title"><?php echo h($optTable['table_name']); ?></div>
                            <?php foreach ($optTable['rows'] as $row): ?>
                                <label class="option-item">
                                    <input type="checkbox" name="options[]" value="<?php echo h($row['id']); ?>" 
                                           data-name="<?php echo h($row['time_label']); ?>" 
                                           data-price="<?php echo h($row['price_label']); ?>">
                                    <span class="option-name"><?php echo h($row['time_label']); ?></span>
                                    <?php if (!empty($row['price_label'])): ?>
                                        <span class="option-price"><?php echo h($row['price_label']); ?></span>
                                    <?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- 利用施設 -->
            <div class="form-section">
                <div class="form-section-title">
                    <span>🏨</span> 利用施設
                    <span class="required">必須</span>
                </div>
                <div class="radio-group">
                    <label class="radio-item">
                        <input type="radio" name="facility_type" value="home" required>
                        <span>自宅</span>
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="facility_type" value="hotel">
                        <span>ホテル</span>
                    </label>
                </div>
                <div id="facility-detail" class="form-group" style="margin-top: 15px;">
                    <label>住所・ホテル名</label>
                    <input type="text" name="facility_detail" id="facility_detail" placeholder="例：福岡市博多区〇〇 / ホテル〇〇">
                </div>
            </div>

            <!-- お客様情報 -->
            <div class="form-section">
                <div class="form-section-title">
                    <span>👤</span> お客様情報
                    <span class="required">必須</span>
                </div>
                <div class="form-group">
                    <label>お名前（ニックネーム可）</label>
                    <input type="text" name="customer_name" id="customer_name" required placeholder="例：山田">
                </div>
                <div class="form-group">
                    <label>電話番号</label>
                    <input type="tel" name="customer_phone" id="customer_phone" required placeholder="例：090-1234-5678">
                </div>
                <div class="form-group">
                    <label>メールアドレス（任意）</label>
                    <input type="email" name="customer_email" id="customer_email" placeholder="例：example@email.com">
                </div>
            </div>

            <!-- 伝達事項 -->
            <div class="form-section">
                <div class="form-section-title">
                    <span>📝</span> 伝達事項
                </div>
                <div class="form-group">
                    <label>ご要望・ご質問など</label>
                    <textarea name="message" id="message" placeholder="ご要望やご質問がございましたらご記入ください"></textarea>
                </div>
            </div>

            <!-- 合計金額（後で実装） -->
            <!--
            <div class="total-price-section">
                <div class="total-price-label">合計金額（税込）</div>
                <div class="total-price-value" id="total-price">¥0</div>
            </div>
            -->

            <!-- 送信ボタン -->
            <button type="submit" class="submit-btn" id="submit-btn">
                予約を送信する
            </button>

            <!-- 隠しフィールド -->
            <input type="hidden" name="tenant_id" value="<?php echo h($tenantId); ?>">
            <input type="hidden" name="shop_name" value="<?php echo h($shopName); ?>">
        </form>
    </main>

    <?php include __DIR__ . '/includes/footer_nav.php'; ?>
    <?php include __DIR__ . '/includes/footer.php'; ?>

    <script>
        // グローバル変数
        let currentCastSchedule = null;
        const initialCastId = <?php echo $castId ? $castId : 'null'; ?>;

        // 予約機能設定から取得した確認電話時間のデフォルト値
        const acceptStartTime = '<?php echo h($acceptStartTime); ?>';
        const acceptEndTime = '<?php echo h($acceptEndTime); ?>';

        // 受付開始・終了時刻を時間と分に分解
        function parseTime(timeStr) {
            const parts = timeStr.split(':');
            return {
                hour: parseInt(parts[0], 10),
                minute: parseInt(parts[1], 10)
            };
        }

        const acceptStart = parseTime(acceptStartTime);
        const acceptEnd = parseTime(acceptEndTime);

        // 料金表から取得したコース行データ
        const courseRowsData = <?php echo json_encode($courseRows, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

        // コース選択時にコース内容を表示
        document.getElementById('course').addEventListener('change', function() {
            const tableId = this.value;
            const contentWrapper = document.getElementById('course_content_wrapper');
            const contentSelect = document.getElementById('course_content');
            
            if (tableId && courseRowsData[tableId]) {
                // コース内容をクリアして再生成
                contentSelect.innerHTML = '<option value="">-- コース内容を選択 --</option>';
                
                courseRowsData[tableId].forEach(row => {
                    const option = document.createElement('option');
                    option.value = row.id;
                    option.textContent = row.time_label + (row.price_label ? ' - ' + row.price_label : '');
                    option.dataset.timeLabel = row.time_label;
                    option.dataset.priceLabel = row.price_label || '';
                    contentSelect.appendChild(option);
                });
                
                contentWrapper.style.display = 'block';
            } else {
                contentWrapper.style.display = 'none';
            }
        });

        // オプション表示トグル
        const optionToggle = document.getElementById('option_toggle');
        if (optionToggle) {
            optionToggle.addEventListener('change', function() {
                const container = document.getElementById('option_container');
                container.style.display = this.checked ? 'block' : 'none';
                
                // オプションのチェックを解除
                if (!this.checked) {
                    document.querySelectorAll('#option_container input[type="checkbox"]').forEach(cb => {
                        cb.checked = false;
                    });
                }
            });
        }

        // 指名形態の切り替え
        function setNominationType(type) {
            document.getElementById('nomination_type').value = type;

            // ボタンのアクティブ状態を切り替え
            document.querySelectorAll('.nomination-btn').forEach(btn => {
                btn.classList.remove('active');
                if (btn.dataset.type === type) {
                    btn.classList.add('active');
                }
            });

            // セクションの表示切り替え
            if (type === 'shimei') {
                document.getElementById('shimei-section').classList.remove('hidden');
                document.getElementById('free-section').classList.add('hidden');
            } else {
                document.getElementById('shimei-section').classList.add('hidden');
                document.getElementById('free-section').classList.remove('hidden');
                // フリーの場合はキャストIDをクリア
                const castIdInput = document.getElementById('cast_id');
                if (castIdInput && castIdInput.tagName === 'SELECT') {
                    castIdInput.value = '';
                }
                // フリー予約用の日付・時間を設定
                setFreeDates();
                setFreeTimes();
            }
        }

        // キャスト選択時の処理
        function onCastSelect(select) {
            const castId = select.value;
            if (castId) {
                console.log('Selected cast:', castId);
                loadCastSchedule(castId);
            } else {
                // キャスト未選択時は日付・時間をリセット
                clearSelect(document.getElementById('reservation_date'), 'キャストを選択してください');
                clearSelect(document.getElementById('reservation_time'), '日付を選択してください');
            }
        }

        // セレクトボックスをクリア
        function clearSelect(selectElement, placeholderText) {
            if (!selectElement) return;
            while (selectElement.options.length > 0) {
                selectElement.remove(0);
            }
            if (placeholderText) {
                const option = document.createElement('option');
                option.value = '';
                option.textContent = placeholderText;
                option.disabled = true;
                option.selected = true;
                selectElement.appendChild(option);
            }
        }

        // オプションを追加
        function addOption(selectElement, value, text) {
            if (!selectElement) return;
            const option = document.createElement('option');
            option.value = value;
            option.textContent = text;
            selectElement.appendChild(option);
        }

        // キャストスケジュールを読み込み
        async function loadCastSchedule(castId) {
            const dateSelect = document.getElementById('reservation_date');
            const timeSelect = document.getElementById('reservation_time');

            clearSelect(dateSelect, '読み込み中...');
            clearSelect(timeSelect, '日付を選択してください');

            try {
                const response = await fetch(`/app/front/cast/get_cast_schedule.php?id=${castId}`);
                const data = await response.json();

                console.log('Cast schedule:', data);

                if (data.success && data.schedule && data.schedule.length > 0) {
                    currentCastSchedule = data.schedule;

                    // 当日を除外して日付を設定
                    const today = new Date();
                    today.setHours(0, 0, 0, 0);

                    clearSelect(dateSelect, '出勤日を選択してください');

                    const availableDates = data.schedule.filter(item => {
                        const itemDate = new Date(item.normalized_day);
                        itemDate.setHours(0, 0, 0, 0);
                        return itemDate.getTime() > today.getTime();
                    });

                    if (availableDates.length > 0) {
                        availableDates.forEach(item => {
                            addOption(dateSelect, item.normalized_day, item.day);
                        });
                    } else {
                        clearSelect(dateSelect, '予約可能な出勤日がありません');
                    }
                } else {
                    currentCastSchedule = null;
                    clearSelect(dateSelect, '出勤予定がありません');
                }
            } catch (error) {
                console.error('Schedule load error:', error);
                clearSelect(dateSelect, 'エラーが発生しました');
            }
        }

        // 利用可能時間を読み込み
        async function loadAvailableTimes(castId, date) {
            const timeSelect = document.getElementById('reservation_time');

            clearSelect(timeSelect, '読み込み中...');

            try {
                const response = await fetch(`/app/front/cast/get_available_times.php?cast_id=${castId}&date=${date}`);
                const data = await response.json();

                console.log('Available times:', data);

                if (data.times && data.times.length > 0) {
                    clearSelect(timeSelect, '時刻を選択してください');
                    data.times.forEach(time => {
                        // 24時以降の表示を調整
                        let displayTime = time;
                        const hour = parseInt(time.split(':')[0]);
                        if (hour >= 24) {
                            displayTime = '翌' + (hour - 24) + ':' + time.split(':')[1];
                        }
                        addOption(timeSelect, time, displayTime);
                    });
                } else {
                    clearSelect(timeSelect, '利用可能な時間がありません');
                }
            } catch (error) {
                console.error('Times load error:', error);
                clearSelect(timeSelect, 'エラーが発生しました');
            }
        }

        // フリー予約用の日付を設定
        function setFreeDates() {
            const dateSelect = document.getElementById('reservation_date');
            clearSelect(dateSelect, '日付を選択してください');

            const dayOfWeekNames = ['日', '月', '火', '水', '木', '金', '土'];
            for (let i = 1; i <= 7; i++) {
                const date = new Date();
                date.setDate(date.getDate() + i);
                const dateStr = date.toISOString().split('T')[0];
                const displayStr = (date.getMonth() + 1) + '/' + date.getDate() + '(' + dayOfWeekNames[date.getDay()] + ')';
                addOption(dateSelect, dateStr, displayStr);
            }
        }

        // フリー予約用の時間を設定
        function setFreeTimes() {
            const timeSelect = document.getElementById('reservation_time');
            clearSelect(timeSelect, '時刻を選択してください');

            for (let h = 11; h <= 25; h++) {
                const displayHour = h > 24 ? h - 24 : h;
                const prefix = h >= 24 ? '翌' : '';
                for (let m = 0; m < 60; m += 30) {
                    const timeStr = String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
                    const displayStr = prefix + displayHour + ':' + String(m).padStart(2, '0');
                    addOption(timeSelect, timeStr, displayStr);
                }
            }
        }

        // 日付フォーマット
        function formatDate(date) {
            const month = date.getMonth() + 1;
            const day = date.getDate();
            const days = ['日', '月', '火', '水', '木', '金', '土'];
            const dayOfWeek = days[date.getDay()];
            return `${month}/${day}(${dayOfWeek})`;
        }

        // 確認電話可能日の設定（利用予定日に連動）
        function setConfirmDateLimits(useDateValue) {
            const confirmDateSelect = document.getElementById('confirm_date');
            const confirmStartTime = document.getElementById('confirm_start_time');
            const confirmEndTime = document.getElementById('confirm_end_time');

            console.log('setConfirmDateLimits:', useDateValue);

            if (!confirmDateSelect) return;

            // 確認電話関連をリセット
            clearSelect(confirmDateSelect, '日付を選択してください');
            clearSelect(confirmStartTime, '時間を選択');
            clearSelect(confirmEndTime, '時間を選択');

            if (!useDateValue) return;

            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const useDateObj = new Date(useDateValue);
            useDateObj.setHours(0, 0, 0, 0);

            // 今日から利用予定日までの日付を選択可能にする
            const currentDate = new Date(today);
            while (currentDate <= useDateObj) {
                const year = currentDate.getFullYear();
                const month = (currentDate.getMonth() + 1).toString().padStart(2, '0');
                const day = currentDate.getDate().toString().padStart(2, '0');
                const dateStr = `${year}-${month}-${day}`;
                const displayDate = formatDate(currentDate);
                addOption(confirmDateSelect, dateStr, displayDate);
                currentDate.setDate(currentDate.getDate() + 1);
            }
        }

        // 確認電話時間の設定
        function setConfirmTimeLimits() {
            const confirmDateSelect = document.getElementById('confirm_date');
            const confirmStartTime = document.getElementById('confirm_start_time');
            const confirmEndTime = document.getElementById('confirm_end_time');
            const reservationDate = document.getElementById('reservation_date');
            const reservationTime = document.getElementById('reservation_time');

            if (!confirmDateSelect || !confirmDateSelect.value) {
                clearSelect(confirmStartTime, '時間を選択');
                clearSelect(confirmEndTime, '時間を選択');
                return;
            }

            const today = new Date();
            today.setHours(0, 0, 0, 0);

            const confirmDateObj = new Date(confirmDateSelect.value);
            confirmDateObj.setHours(0, 0, 0, 0);

            const useDateObj = reservationDate && reservationDate.value ? new Date(reservationDate.value) : null;
            if (useDateObj) useDateObj.setHours(0, 0, 0, 0);

            const useTime = reservationTime && reservationTime.value ? reservationTime.value : null;

            let startHour = acceptStart.hour;
            let startMinute = acceptStart.minute;
            let endHour = acceptEnd.hour;
            let endMinute = acceptEnd.minute;

            // 今日の場合、現在時刻以降の時間のみ選択可能
            const isTodayConfirm = confirmDateObj.getTime() === today.getTime();

            if (isTodayConfirm) {
                const now = new Date();
                const currentHour = now.getHours();
                const currentMinute = now.getMinutes();

                // 現在時刻の30分後から開始
                if (currentMinute < 30) {
                    startHour = currentHour;
                    startMinute = 30;
                } else {
                    startHour = currentHour + 1;
                    startMinute = 0;
                }

                // 受付開始時刻以降の調整
                if (startHour < acceptStart.hour || (startHour === acceptStart.hour && startMinute < acceptStart.minute)) {
                    startHour = acceptStart.hour;
                    startMinute = acceptStart.minute;
                }
            }

            // 確認電話日と利用予定日が同じ場合、利用時間の1時間半前まで制限（ただしacceptEndを超えない）
            if (useDateObj && confirmDateObj.getTime() === useDateObj.getTime() && useTime) {
                const [useHour, useMinuteStr] = useTime.split(':');
                let useHourNum = parseInt(useHour);
                const useMinuteNum = parseInt(useMinuteStr);

                // 利用時間の1時間半前（90分前）を計算
                let useTotalMinutes = useHourNum * 60 + useMinuteNum;
                let limitTotalMinutes = useTotalMinutes - 90; // 90分前

                // 時間が負の場合の調整（深夜0時台をまたぐ場合）
                if (limitTotalMinutes < 0) {
                    limitTotalMinutes = 23 * 60 + 30; // 23:30に設定
                }

                let limitHour = Math.floor(limitTotalMinutes / 60);
                let limitMinute = limitTotalMinutes % 60;

                // acceptEndより早い場合のみ制限を適用
                const limitTotal = limitHour * 60 + limitMinute;
                const acceptEndTotal = acceptEnd.hour * 60 + acceptEnd.minute;
                if (limitTotal < acceptEndTotal) {
                    endHour = limitHour;
                    endMinute = limitMinute;
                }
            }

            populateConfirmTimeOptions(startHour, startMinute, endHour, endMinute);
        }

        // 確認電話時間オプションを生成
        function populateConfirmTimeOptions(startHour = acceptStart.hour, startMinute = acceptStart.minute, endHour = acceptEnd.hour, endMinute = acceptEnd.minute) {
            const confirmStartTime = document.getElementById('confirm_start_time');
            const confirmEndTime = document.getElementById('confirm_end_time');

            clearSelect(confirmStartTime, '時間を選択');
            clearSelect(confirmEndTime, '時間を選択');

            // 終了時刻を分に変換（24時以降も正しく処理）
            const endTotalMinutes = endHour * 60 + endMinute;
            // 開始時刻用の終了制限（終了時刻の1時間前まで、最低1時間の幅を確保するため）
            const startEndTotalMinutes = endTotalMinutes - 60;

            const startTimes = [];
            const endTimes = [];
            let hour = startHour;
            let minute = startMinute;

            let loopCount = 0;
            while (true) {
                loopCount++;
                if (loopCount > 100) break; // 無限ループ防止

                const currentTotalMinutes = hour * 60 + minute;

                // 終了時刻を超えたら終了（終了時刻自体は含める）
                if (currentTotalMinutes > endTotalMinutes) {
                    break;
                }

                const timeStr = `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}`;

                // 開始時刻用は終了時刻の1時間前まで
                if (currentTotalMinutes <= startEndTotalMinutes) {
                    startTimes.push(timeStr);
                }
                // 終了時刻用は全て含める
                endTimes.push(timeStr);

                minute += 30;
                if (minute >= 60) {
                    minute = 0;
                    hour += 1;
                }
            }

            startTimes.forEach(time => {
                addOption(confirmStartTime, time, time);
            });
            endTimes.forEach(time => {
                addOption(confirmEndTime, time, time);
            });
        }

        // 確認電話終了時間の更新
        function updateConfirmEndTimeOptions() {
            const confirmStartTime = document.getElementById('confirm_start_time');
            const confirmEndTime = document.getElementById('confirm_end_time');
            const confirmDateSelect = document.getElementById('confirm_date');
            const reservationDate = document.getElementById('reservation_date');
            const reservationTime = document.getElementById('reservation_time');

            if (!confirmStartTime || !confirmStartTime.value) {
                clearSelect(confirmEndTime, '時間を選択');
                return;
            }

            const startTime = confirmStartTime.value;
            const [startHour, startMinute] = startTime.split(':').map(Number);

            // 利用時間制限を取得
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const confirmDateObj = confirmDateSelect && confirmDateSelect.value ? new Date(confirmDateSelect.value) : null;
            const useDateObj = reservationDate && reservationDate.value ? new Date(reservationDate.value) : null;
            const useTime = reservationTime && reservationTime.value ? reservationTime.value : null;

            // デフォルトはacceptEnd設定値
            let endHour = acceptEnd.hour;
            let endMinute = acceptEnd.minute;

            // 確認電話日と利用日が同じ場合、利用時間の1時間半前まで制限（ただしacceptEndを超えない）
            if (confirmDateObj && useDateObj && confirmDateObj.getTime() === useDateObj.getTime() && useTime) {
                const [useHour, useMinuteStr] = useTime.split(':');
                const useHourNum = parseInt(useHour);
                const useMinuteNum = parseInt(useMinuteStr);

                // 利用時間の1時間半前（90分前）を計算
                let useTotalMinutes = useHourNum * 60 + useMinuteNum;
                let limitTotalMinutes = useTotalMinutes - 90; // 90分前

                // 時間が負の場合の調整（深夜0時台をまたぐ場合）
                if (limitTotalMinutes < 0) {
                    limitTotalMinutes = 23 * 60 + 30; // 23:30に設定
                }

                let limitHour = Math.floor(limitTotalMinutes / 60);
                let limitMinute = limitTotalMinutes % 60;

                // acceptEndより早い場合のみ制限を適用
                const limitTotal = limitHour * 60 + limitMinute;
                const acceptEndTotal = acceptEnd.hour * 60 + acceptEnd.minute;
                if (limitTotal < acceptEndTotal) {
                    endHour = limitHour;
                    endMinute = limitMinute;
                }
            }

            clearSelect(confirmEndTime, '時間を選択');

            let hour = startHour + 1; // 開始時間の1時間後から
            let minute = startMinute;

            // 終了時刻を分に変換（24時以降も正しく処理）
            const endTotalMinutes = endHour * 60 + endMinute;

            let loopCount = 0;
            while (true) {
                loopCount++;
                if (loopCount > 100) break; // 無限ループ防止

                // 現在の時刻を分に変換
                const currentTotalMinutes = hour * 60 + minute;

                // 終了時刻を超えたら終了（終了時刻自体は含める）
                if (currentTotalMinutes > endTotalMinutes) {
                    break;
                }

                const timeStr = `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}`;
                addOption(confirmEndTime, timeStr, timeStr);

                minute += 30;
                if (minute >= 60) {
                    minute = 0;
                    hour += 1;
                }
            }
        }

        // 日付選択時の処理
        document.getElementById('reservation_date').addEventListener('change', function () {
            const date = this.value;
            const castIdInput = document.getElementById('cast_id');
            const castId = castIdInput ? castIdInput.value : null;
            const nominationType = document.getElementById('nomination_type').value;

            if (nominationType === 'shimei' && castId && date) {
                // 指名予約の場合、キャストの利用可能時間を取得
                loadAvailableTimes(castId, date);
            }

            // 確認電話可能日の制限を設定
            setConfirmDateLimits(date);
        });

        // 利用開始時刻選択時の処理
        document.getElementById('reservation_time').addEventListener('change', function () {
            // 確認電話時間制限を更新（利用時刻が変更されたため）
            const confirmDateSelect = document.getElementById('confirm_date');
            if (confirmDateSelect && confirmDateSelect.value) {
                setConfirmTimeLimits();
            }
        });

        // 確認電話日選択時の処理
        document.getElementById('confirm_date').addEventListener('change', function () {
            if (this.value) {
                setConfirmTimeLimits();
            } else {
                clearSelect(document.getElementById('confirm_start_time'), '時間を選択');
                clearSelect(document.getElementById('confirm_end_time'), '時間を選択');
            }
        });

        // 確認電話開始時間選択時の処理
        document.getElementById('confirm_start_time').addEventListener('change', function () {
            if (this.value) {
                updateConfirmEndTimeOptions();
            } else {
                clearSelect(document.getElementById('confirm_end_time'), '時間を選択');
            }
        });

        // フォーム送信前のバリデーション
        document.getElementById('yoyaku-form').addEventListener('submit', function (e) {
            const nominationType = document.getElementById('nomination_type').value;

            // 指名ありの場合、キャストが選択されているか確認
            if (nominationType === 'shimei') {
                const castId = document.getElementById('cast_id').value;
                if (!castId) {
                    e.preventDefault();
                    alert('キャストを選択してください');
                    return false;
                }
            }

            // 電話番号の簡易バリデーション
            const phone = document.getElementById('customer_phone').value;
            if (!/^[\d\-]+$/.test(phone)) {
                e.preventDefault();
                alert('電話番号は数字とハイフンのみで入力してください');
                return false;
            }

            return true;
        });

        // 初期化
        document.addEventListener('DOMContentLoaded', function () {
            // 指名予約でキャストが指定されている場合、スケジュールを読み込み
            if (initialCastId) {
                loadCastSchedule(initialCastId);
            }
        });
    </script>

    <?php
    // プレビューバーを表示
    if (isset($currentTheme['is_preview']) && $currentTheme['is_preview']) {
        echo generatePreviewBar($currentTheme, $tenantId, $tenant['code']);
    }
    ?>
</body>

</html>