<?php
/**
 * pullcass - キャスト詳細ページ
 * 参考: https://club-houman.com/cast/detail.php
 * ※参考サイトのインラインスタイルを忠実に再現
 */

session_start();

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/theme_helper.php';

// テナント情報を取得（リクエストのサブドメインを優先）
$tenantFromRequest = getTenantFromRequest();
$tenantFromSession = getCurrentTenant();

// リクエストからテナントが判別できた場合はそれを使用
if ($tenantFromRequest) {
    $tenant = $tenantFromRequest;
    // セッションと異なる場合は更新
    if (!$tenantFromSession || $tenantFromSession['id'] !== $tenant['id']) {
        setCurrentTenant($tenant);
    }
} elseif ($tenantFromSession) {
    // リクエストから判別できない場合はセッションを使用
    $tenant = $tenantFromSession;
} else {
    // どちらも無い場合はプラットフォームトップへ
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

// テーマを取得（プレビュー対応）
$currentTheme = getCurrentTheme($tenantId);
$themeData = $currentTheme['theme_data'];

// 今日の日付
$today = date('n/j');
$dayOfWeek = ['日', '月', '火', '水', '木', '金', '土'][date('w')];

// キャストIDを取得
$castId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$castId) {
    header('Location: /app/front/cast/list.php');
    exit;
}

// キャストデータを取得
$cast = null;
try {
    // 統合テーブルから取得
    $stmt = $pdo->prepare("
        SELECT *
        FROM tenant_casts
        WHERE id = ? AND tenant_id = ? AND checked = 1
    ");
    $stmt->execute([$castId, $tenantId]);
    $cast = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Cast detail error: " . $e->getMessage());
}

if (!$cast) {
    // キャストが見つからない、または非表示の場合は一覧へ
    header('Location: /app/front/cast/list.php');
    exit;
}

// 出勤スケジュールを配列に整理（7日分）
$schedule = [];
$dayOfWeekNames = ['日', '月', '火', '水', '木', '金', '土'];
for ($i = 0; $i < 7; $i++) {
    $date = new DateTime();
    $date->modify("+{$i} days");
    $dayNum = $i + 1;
    $dayKey = "day{$dayNum}";

    $time = (isset($cast[$dayKey]) && !empty($cast[$dayKey])) ? $cast[$dayKey] : '---';

    $schedule[] = [
        'date' => $date->format('n/j') . '(' . $dayOfWeekNames[$date->format('w')] . ')',
        'time' => $time
    ];
}

// 予約機能の有効/無効設定を取得
$reservationEnabled = false;
try {
    $stmt = $pdo->prepare("SELECT is_enabled FROM tenant_reservation_settings WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $reservationSettings = $stmt->fetch(PDO::FETCH_ASSOC);
    $reservationEnabled = $reservationSettings && $reservationSettings['is_enabled'] == 1;
} catch (Exception $e) {
    error_log("Reservation settings fetch error: " . $e->getMessage());
}

// ページタイトル
$pageTitle = $cast['name'] . '｜' . $shopName;
$pageDescription = $shopName . 'の' . $cast['name'] . 'のプロフィールページです。';
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta name="cast-id" content="<?php echo h($castId); ?>">
    <?php include __DIR__ . '/../includes/head.php'; ?>
    <style>
        /* キャスト詳細ページ固有のスタイル（参考サイト準拠） */
        .swiper {
            overflow: hidden !important;
            width: 100% !important;
            position: relative !important;
        }

        .swiper-wrapper {
            display: flex !important;
            width: 100% !important;
            position: relative !important;
        }

        .swiper-slide {
            flex-shrink: 0 !important;
            width: 100% !important;
            position: relative !important;
            overflow: hidden !important;
        }

        .swiper-slide img {
            width: 100% !important;
            height: auto !important;
            display: block !important;
            object-fit: cover !important;
        }

        .swiper-button-prev,
        .swiper-button-next {
            color: var(--color-primary);
            opacity: .7;
            width: 40px;
            height: 40px;
            z-index: 10;
        }

        .swiper-button-prev:hover,
        .swiper-button-next:hover {
            opacity: 1;
        }

        .swiper-button-prev::after,
        .swiper-button-next::after {
            font-size: 30px;
            font-weight: bold;
        }

        .swiper-pagination {
            position: relative;
            bottom: 0;
            margin-top: 20px;
            z-index: 10;
        }

        .swiper-pagination-bullet {
            width: 10px;
            height: 10px;
            background: var(--color-primary);
            opacity: .5;
        }

        .swiper-pagination-bullet-active {
            opacity: 1;
        }

        /* スクロールグラデーション */
        .scroll-gradient-bottom {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 30px;
            background: linear-gradient(to bottom, transparent, var(--color-background));
            pointer-events: none;
            z-index: 2;
        }

        /* レスポンシブ対応 */
        @media screen and (max-width: 768px) {
            .cast-content {
                flex-direction: column !important;
                padding: 0 20px !important;
                margin: 0 !important;
                gap: 0 !important;
            }

            .title-section {
                margin-bottom: 0 !important;
                padding-bottom: 0 !important;
            }

            .title-section h1 {
                margin-bottom: 0 !important;
            }

            .title-section h2 {
                margin-top: -7px !important;
                margin-bottom: -3px !important;
            }

            .title-section .dot-line {
                margin-bottom: 0 !important;
            }

            .cast-info-sidebar {
                min-width: 100% !important;
                margin-top: -10px !important;
                padding-top: 0 !important;
            }

            .swiper-container {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                overflow: hidden !important;
            }

            .cast-name-age h3 {
                font-size: 25px !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .cast-info-sidebar h2 {
                font-size: 1.2em !important;
                margin: 0 0 15px 0 !important;
                padding: 0 !important;
            }

            .cast-badges {
                margin: 0 0 15px 0 !important;
                padding: 0 !important;
            }

            .cast-stats-detail p {
                font-size: 1em !important;
                margin: 0 !important;
                padding: 0 !important;
                line-height: 1 !important;
                font-weight: bold !important;
            }

            .swiper-button-prev,
            .swiper-button-next {
                width: 30px !important;
                height: 30px !important;
            }

            .swiper-button-prev::after,
            .swiper-button-next::after {
                font-size: 20px !important;
            }

            .cast-pr-text p {
                line-height: 1.2 !important;
                margin: 0 !important;
                padding: 0 !important;
                font-size: 0.9em !important;
                text-align: left !important;
            }

            .pc-schedule {
                display: none !important;
            }

            .sp-schedule {
                display: block !important;
                margin-top: 10px !important;
            }

            .reserve-buttons {
                flex-direction: column !important;
                gap: 8px !important;
                padding: 0 20px !important;
                margin-top: 5px !important;
            }

            .reserve-button {
                width: 100% !important;
                padding: 10px !important;
            }

            .three-sections {
                flex-direction: column !important;
                gap: 20px !important;
            }

            .three-sections .review-section,
            .three-sections .photo-section,
            .three-sections .history-section {
                width: 100% !important;
            }
        }

        /* スマホ表示用の出勤表 */
        .sp-schedule {
            display: none;
            position: relative;
        }

        .sp-schedule-scroll-wrapper {
            position: relative;
            overflow: hidden;
        }

        .sp-schedule-scroll {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding: 0 15px;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .sp-schedule-scroll::-webkit-scrollbar {
            display: none;
        }

        .sp-schedule .scroll-gradient-right {
            position: absolute;
            top: 0;
            right: 0;
            width: 30px;
            height: 100%;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s;
            z-index: 2;
            background: linear-gradient(to right, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.8));
        }

        .sp-schedule-item {
            width: 115px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            flex-shrink: 0;
        }

        .sp-schedule-item .day {
            color: var(--color-btn-text);
            font-weight: bold;
            font-size: 0.9em;
            padding: 5px;
            text-align: center;
            background: var(--color-primary);
            border-radius: 10px 10px 0 0;
        }

        .sp-schedule-item .time {
            color: var(--color-text);
            text-align: center;
            padding: 5px 3px;
            font-size: 0.9em;
            background: rgba(255, 255, 255, 0.6);
            border-radius: 0 0 10px 10px;
        }

        /* マイキャストボタンのアニメーション */
        @keyframes doublePulse {

            0%,
            100% {
                transform: scale(1);
            }

            8% {
                transform: scale(1.2);
            }

            15% {
                transform: scale(1);
            }

            23% {
                transform: scale(1.2);
            }

            30%,
            100% {
                transform: scale(1);
            }
        }

        .favorite-button {
            animation: doublePulse 0.9s ease-in-out infinite;
        }

        /* 予約モーダル */
        .reserve-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 10000;
            align-items: center;
            justify-content: center;
        }

        .reserve-modal-overlay.active {
            display: flex;
        }

        .reserve-modal {
            background: white;
            border-radius: 15px;
            padding: 30px;
            max-width: 400px;
            width: 90%;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        }

        .reserve-modal-icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        .reserve-modal-title {
            font-size: 1.3em;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }

        .reserve-modal-message {
            color: #666;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .reserve-modal-btn {
            padding: 12px 30px;
            border: none;
            border-radius: 25px;
            font-size: 1em;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .reserve-modal-btn-close {
            background: var(--color-primary);
            color: white;
        }

        .reserve-modal-btn-close:hover {
            opacity: 0.8;
        }
    </style>
</head>

<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <main class="main-content">
        <!-- パンくず（遷移元に応じて動的に変更） -->
        <?php
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $refererPath = parse_url($referer, PHP_URL_PATH) ?? '';
        $showCastList = (strpos($refererPath, 'list.php') !== false);
        ?>
        <nav class="breadcrumb">
            <a href="/app/front/index.php">ホーム</a><span>»</span>
            <a href="/app/front/top.php">トップ</a><span>»</span>
            <?php if ($showCastList): ?>
                <a href="/app/front/cast/list.php">キャスト一覧</a><span>»</span>
            <?php endif; ?>
            <?php echo h($cast['name']); ?> |
        </nav>

        <!-- タイトルセクション -->
        <section class="title-section cast-detail-title" style="margin-bottom: 10px; padding-bottom: 0;">
            <h1>PROFILE</h1>
            <h2>「<?php echo h($cast['name']); ?>」さんのプロフィール</h2>
            <div class="dot-line"></div>
        </section>

        <!-- メインコンテンツエリア -->
        <section class="main-content" style="padding: 0; margin-top: 10px;">
            <!-- キャストコンテンツ（画像と情報の横並び） -->
            <div class="cast-content"
                style="display: flex; flex-direction: row; gap: 30px; margin: 30px auto; max-width: 1200px; padding: 0 15px;">
                <!-- スワイパー部分 -->
                <div class="swiper-container" style="flex: 1; min-width: 0;">
                    <div class="swiper mySwiper" style="max-width: 100%; margin: 0; padding: 0; position: relative;">
                        <div class="swiper-wrapper">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <?php $imgKey = "img{$i}"; ?>
                                <?php if (!empty($cast[$imgKey])): ?>
                                    <div class="swiper-slide">
                                        <div style="padding: 2px; box-sizing: border-box;">
                                            <div
                                                style="overflow: hidden; border-radius: 15px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                                                <img src="<?php echo h($cast[$imgKey]); ?>"
                                                    alt="<?php echo h($shopName . ' ' . $cast['name'] . ' 写真' . $i); ?>"
                                                    loading="lazy" style="width: 100%; height: auto; object-fit: cover;">
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>

                        <!-- マイキャスト登録ボタン -->
                        <div id="favorite-button-container"
                            style="position: absolute; bottom: 58px; right: 15px; z-index: 100;">
                            <button id="favorite-button" class="favorite-button"
                                style="background: transparent; border: none; padding: 2px; cursor: pointer; display: flex; flex-direction: column; align-items: center; gap: 2px; transition: all 0.3s ease;">
                                <span id="favorite-text"
                                    style="font-size: 10px; color: #ff1493; font-weight: 600; line-height: 1;">マイキャスト登録</span>
                                <span id="favorite-icon"
                                    style="font-size: 28px; line-height: 1; transition: all 0.3s ease; color: #ff1493;">♡</span>
                            </button>
                        </div>

                        <div class="swiper-button-next"></div>
                        <div class="swiper-button-prev"></div>
                        <div class="swiper-pagination"></div>
                    </div>
                </div>

                <!-- キャスト情報部分 -->
                <div class="cast-info-sidebar" style="flex: 1; min-width: 300px;">
                    <div class="cast-name-age" style="text-align: center; margin-bottom: 10px;">
                        <h3 style="font-size: 1.8em; margin: 0; font-family: var(--font-body);">
                            <?php echo h($cast['name']); ?>
                            <span
                                style="font-size: 0.8em; color: var(--color-text);"><?php echo h($cast['age']); ?>歳</span>
                        </h3>
                    </div>

                    <?php if ($cast['pr_title']): ?>
                        <h2 style="font-size: 1.6em; margin-bottom: 5px; font-weight: bold; font-family: var(--font-body);">
                            <?php echo h($cast['pr_title']); ?>
                        </h2>
                    <?php endif; ?>

                    <div class="cast-stats-detail" style="margin-bottom: 5px; line-height: 1.2;">
                        <p
                            style="margin: 0; padding: 0; color: var(--color-text); font-family: var(--font-body); font-size: 1.3em; font-weight: bold;">
                            <span style="font-size: 1.5em;"><?php echo h($cast['cup']); ?></span> カップ
                        </p>
                        <?php if ($cast['height'] || $cast['size']): ?>
                            <p
                                style="margin: 0; padding: 0; color: var(--color-text); font-family: var(--font-body); font-size: 1.3em; font-weight: bold;">
                                身長<?php echo h($cast['height']); ?>cm <?php echo h($cast['size']); ?>
                            </p>
                        <?php endif; ?>
                    </div>

                    <div class="cast-badges"
                        style="display: flex; flex-wrap: wrap; gap: 10px; margin-top: 5px; margin-bottom: 20px; justify-content: center;">
                        <?php if ($cast['new']): ?>
                            <span class="badge new">NEW</span>
                        <?php endif; ?>
                        <?php if ($cast['today']): ?>
                            <span class="badge today">本日出勤</span>
                        <?php endif; ?>
                        <?php if ($cast['now']): ?>
                            <span class="badge now">案内中</span>
                        <?php endif; ?>
                        <?php if ($cast['closed']): ?>
                            <span class="badge closed">受付終了</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($cast['pr_text']): ?>
                        <div class="cast-pr-text" style="margin-top: 20px;">
                            <p
                                style="color: var(--color-text); line-height: 1.2; font-family: var(--font-body); text-align: left;">
                                <?php echo nl2br(h($cast['pr_text'])); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 動画セクション -->
            <section style="margin: 0 auto; max-width: 1200px; padding: 0 15px;">
                <div style="display: flex; flex-wrap: wrap; gap: 20px;">
                    <?php if (!empty($cast['movie_1'])):
                        $poster1 = !empty($cast['movie_1_thumbnail']) ? $cast['movie_1_thumbnail'] : (!empty($cast['movie_1_seo_thumbnail']) ? $cast['movie_1_seo_thumbnail'] : '/img/hp/hc_logo.png');
                        ?>
                        <div style="flex: <?php echo (!empty($cast['movie_2'])) ? '1' : '0 0 50%'; ?>; min-width: 0;">
                            <video
                                style="width: 100%; height: auto; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);"
                                autoplay loop muted playsinline preload="metadata" poster="<?php echo h($poster1); ?>">
                                <?php
                                $m1 = $cast['movie_1'];
                                if (!preg_match('|^https?://|', $m1)) {
                                    $m1 = '/' . ltrim($m1, '/');
                                }
                                ?>
                                <source src="<?php echo h($m1); ?>" type="video/mp4">
                            </video>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($cast['movie_2'])):
                        $poster2 = !empty($cast['movie_2_thumbnail']) ? $cast['movie_2_thumbnail'] : (!empty($cast['movie_2_seo_thumbnail']) ? $cast['movie_2_seo_thumbnail'] : '/img/hp/hc_logo.png');
                        ?>
                        <div style="flex: 1; min-width: 0;">
                            <video
                                style="width: 100%; height: auto; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);"
                                autoplay loop muted playsinline preload="metadata" poster="<?php echo h($poster2); ?>">
                                <?php
                                $m2 = $cast['movie_2'];
                                if (!preg_match('|^https?://|', $m2)) {
                                    $m2 = '/' . ltrim($m2, '/');
                                }
                                ?>
                                <source src="<?php echo h($m2); ?>" type="video/mp4">
                            </video>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <style>
                @media screen and (max-width: 768px) {
                    section>div>div {
                        flex: 0 0 100% !important;
                        width: 100% !important;
                    }
                }
            </style>

            <!-- キャスト情報エリア（出勤表・予約・3カラム） -->
            <div class="cast-info" style="display: flex; flex-direction: column; gap: 30px;">
                <?php if (!empty($schedule)): ?>
                    <!-- 出勤表セクション -->
                    <section class="cast-schedule" style="margin-bottom: 0; position: relative;">
                        <div class="title-section cast-detail-title">
                            <h1>SCHEDULE</h1>
                            <h2>出勤表</h2>
                            <div class="dot-line"></div>
                        </div>

                        <!-- PC表示用 -->
                        <div class="pc-schedule"
                            style="overflow-x: auto; -webkit-overflow-scrolling: touch; margin-top: 10px;">
                            <table
                                style="width: 100%; border-collapse: separate; border-spacing: 8px 0; table-layout: fixed;">
                                <tr>
                                    <?php foreach ($schedule as $item): ?>
                                        <th
                                            style="padding: 5px 4px; text-align: center; font-weight: bold; color: var(--color-btn-text); font-family: var(--font-body); background: var(--color-primary); border-radius: 10px 10px 0 0; white-space: nowrap; font-size: 0.9em;">
                                            <?php echo h($item['date']); ?>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                                <tr>
                                    <?php foreach ($schedule as $item): ?>
                                        <td
                                            style="padding: 5px 4px; text-align: center; border-radius: 0 0 10px 10px; font-family: var(--font-body); color: var(--color-text); box-shadow: 0 4px 12px rgba(0,0,0,0.1); background: rgba(255, 255, 255, 0.6); white-space: nowrap; font-size: 0.85em;">
                                            <?php echo h($item['time']); ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            </table>
                        </div>

                        <!-- スマホ表示用 -->
                        <div class="sp-schedule"
                            style="display: none; position: relative; margin-top: 10px; margin-bottom: 0px;">
                            <div class="sp-schedule-scroll-wrapper" style="position: relative; overflow: hidden;">
                                <div class="sp-schedule-scroll"
                                    style="display: flex; gap: 10px; overflow-x: auto; padding: 0 15px; -webkit-overflow-scrolling: touch;">
                                    <?php foreach ($schedule as $item): ?>
                                        <div class="sp-schedule-item">
                                            <div class="day"><?php echo h($item['date']); ?></div>
                                            <div class="time"><?php echo h($item['time']); ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="scroll-gradient-right"></div>
                            </div>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if ($reservationEnabled): ?>
                    <!-- 予約セクション -->
                    <section class="cast-reserve" style="margin-bottom: 0;">
                        <div class="title-section cast-detail-title">
                            <h1>RESERVE</h1>
                            <h2>ネット予約</h2>
                            <div class="dot-line"></div>
                        </div>

                        <div class="reserve-buttons"
                            style="display: flex; flex-direction: row; gap: 10px; margin-top: 10px; justify-content: center;">
                            <button id="reserve-button" class="reserve-button"
                                style="padding: 12px 10px; text-align: center; background-color: rgba(255, 255, 255, 0.5); text-decoration: none; border-radius: 20px; font-weight: bold; font-family: var(--font-body); transition: all 0.3s ease; box-shadow: 0 4px 12px rgba(0,0,0,0.1); display: flex; align-items: center; justify-content: center; gap: 8px; width: 400px; border: none; cursor: pointer; color: var(--color-text);">
                                <?php if ($logoSmallUrl): ?>
                                    <img src="<?php echo h($logoSmallUrl); ?>" alt="<?php echo h($cast['name']); ?>さんを予約する"
                                        style="width: auto; height: auto; max-height: 24px;">
                                <?php endif; ?>
                                <?php echo h($cast['name']); ?>さんを予約する
                            </button>
                        </div>
                    </section>
                <?php endif; ?>

                <!-- 3つのセクションエリア -->
                <div class="three-sections"
                    style="display: flex; flex-direction: row; gap: 10px; margin: 0px auto; width: 100%; max-width: 1200px; padding: 0; box-sizing: border-box;">
                    <!-- REVIEWセクション -->
                    <section class="review-section" style="width: 30%; min-width: 0; position: relative;">
                        <div class="title-section cast-detail-title">
                            <h1>REVIEW</h1>
                            <h2>口コミ</h2>
                            <div class="dot-line"></div>
                        </div>
                        <div class="review-wrapper" style="position: relative; margin-top: 0px;">
                            <?php if (!empty($cast['review_widget_code'])): ?>
                                <div class="widget-content">
                                    <?php echo $cast['review_widget_code']; ?>
                                </div>
                            <?php else: ?>
                                <div class="review-content"
                                    style="height: 300px; overflow-y: auto; transition: max-height 0.5s cubic-bezier(0.4, 0, 0.2, 1); padding-right: 0px;">
                                    <div
                                        style="text-align: center; padding: 50px 20px; color: var(--color-text); font-size: 14px;">
                                        現在口コミはありません。
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>

                    <!-- DIARYセクション -->
                    <section class="photo-section" style="width: 40%; min-width: 0; position: relative;">
                        <div class="title-section cast-detail-title">
                            <h1>DIARY</h1>
                            <h2>動画・写メ日記</h2>
                            <div class="dot-line"></div>
                        </div>
                        <div class="shamenikki-wrapper" style="position: relative; margin-top: 0px;">
                            <?php if (!empty($cast['diary_widget_code'])): ?>
                                <div class="widget-content">
                                    <?php echo $cast['diary_widget_code']; ?>
                                </div>
                            <?php else: ?>
                                <div class="shamenikki-content"
                                    style="height: 300px; overflow-y: auto; padding-right: 0px;">
                                    <div style="text-align: center; padding: 40px; color: var(--color-text);">
                                        まだ日記が投稿されていません
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>

                    <!-- HISTORYセクション -->
                    <section class="history-section" style="width: 30%; min-width: 0; position: relative;">
                        <div class="title-section cast-detail-title">
                            <h1>HISTORY</h1>
                            <h2>閲覧履歴</h2>
                            <div class="dot-line"></div>
                        </div>
                        <div class="history-wrapper" style="position: relative; margin-top: 0px;">
                            <div class="history-content">
                                <div class="history-cards">
                                    <!-- 履歴はJavaScriptで動的に追加されます -->
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </section>
    </main>

    <?php include __DIR__ . '/../includes/footer_nav.php'; ?>

    <!-- 予約不可モーダル -->
    <div id="reserve-modal" class="reserve-modal-overlay" onclick="if(event.target === this) closeReserveModal()">
        <div class="reserve-modal">
            <div class="reserve-modal-icon">⚠️</div>
            <div class="reserve-modal-title">予約できません</div>
            <div class="reserve-modal-message">
                予約可能な<strong><?php echo h($cast['name']); ?></strong>さんの出勤情報がありません。
            </div>
            <button class="reserve-modal-btn reserve-modal-btn-close" onclick="closeReserveModal()">
                閉じる
            </button>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <!-- Swiper.js -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>
        const castSwiper = new Swiper('.mySwiper', {
            loop: true,
            slidesPerView: 1,
            spaceBetween: 0,
            speed: 300,
            autoplay: {
                delay: 4000,
                disableOnInteraction: false,
            },
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
            },
        });
    </script>

    <?php
    // プレビューバーを表示
    if (isset($currentTheme['is_preview']) && $currentTheme['is_preview']) {
        echo generatePreviewBar($currentTheme, $tenantId, $tenant['code']);
    }
    ?>

    <!-- スクロールグラデーション制御 -->
    <script src="/assets/js/top.js"></script>

    <!-- スマホ用出勤表のスクロールグラデーション制御 -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const spScheduleScroll = document.querySelector('.sp-schedule-scroll');
            const spScheduleGradient = document.querySelector('.sp-schedule .scroll-gradient-right');
            if (spScheduleScroll && spScheduleGradient) {
                const checkSpScheduleScrollable = () => {
                    const isScrollable = spScheduleScroll.scrollWidth > spScheduleScroll.clientWidth;
                    spScheduleGradient.style.opacity = isScrollable ? '1' : '0';
                };
                spScheduleScroll.addEventListener('scroll', function () {
                    const isAtEnd = spScheduleScroll.scrollLeft + spScheduleScroll.clientWidth >= spScheduleScroll.scrollWidth - 1;
                    spScheduleGradient.style.opacity = isAtEnd ? '0' : '1';
                });
                setTimeout(checkSpScheduleScrollable, 100);
                window.addEventListener('resize', checkSpScheduleScrollable);
            }
        });
    </script>

    <!-- 閲覧履歴スクリプト -->
    <script src="/assets/js/history.js"></script>

    <!-- 予約ボタン処理 -->
    <script>
        // キャスト情報
        const castId = <?php echo json_encode($castId); ?>;
        const castName = <?php echo json_encode($cast['name']); ?>;

        // DOMContentLoadedで確実にイベントを登録
        document.addEventListener('DOMContentLoaded', function () {
            console.log('予約ボタン処理を初期化中...');

            const reserveButton = document.getElementById('reserve-button');
            if (reserveButton) {
                console.log('予約ボタンを発見:', reserveButton);

                reserveButton.addEventListener('click', function (e) {
                    e.preventDefault();
                    console.log('予約ボタンがクリックされました。キャストID:', castId);
                    // 出勤情報をチェック
                    checkCastSchedule(castId);
                });
            } else {
                console.error('予約ボタンが見つかりません');
            }
        });

        // 出勤情報チェックAPI呼び出し
        function checkCastSchedule(castId) {
            console.log('出勤情報をチェック中... キャストID:', castId);

            // テナントパラメータを取得（URLかサブドメインから）
            const urlParams = new URLSearchParams(window.location.search);
            let tenantParam = urlParams.get('tenant');

            // URLにテナントがない場合はサブドメインから取得
            if (!tenantParam) {
                const hostname = window.location.hostname;
                const parts = hostname.split('.');
                if (parts.length >= 3 && parts[0] !== 'www' && parts[0] !== 'pullcass') {
                    tenantParam = parts[0];
                }
            }

            let apiUrl = '/app/front/cast/check_cast_schedule.php?cast_id=' + castId;
            if (tenantParam) {
                apiUrl += '&tenant=' + encodeURIComponent(tenantParam);
            }

            fetch(apiUrl)
                .then(response => {
                    console.log('APIレスポンス:', response);
                    return response.json();
                })
                .then(data => {
                    console.log('APIデータ:', data);
                    if (data.success && data.has_schedule) {
                        // 出勤情報がある場合は予約ページへ遷移
                        console.log('出勤情報あり。予約ページへ遷移します。');
                        let redirectUrl = '/app/front/yoyaku.php?cast_id=' + castId;
                        if (tenantParam) {
                            redirectUrl += '&tenant=' + encodeURIComponent(tenantParam);
                        }
                        window.location.href = redirectUrl;
                    } else {
                        // 出勤情報がない場合はモーダルを表示
                        console.log('出勤情報なし。モーダルを表示します。');
                        openReserveModal();
                    }
                })
                .catch(error => {
                    console.error('APIエラー:', error);
                    // エラー時もモーダルを表示
                    openReserveModal();
                });
        }

        // モーダルを開く
        function openReserveModal() {
            console.log('モーダルを開きます');
            const modal = document.getElementById('reserve-modal');
            if (modal) {
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            } else {
                console.error('モーダル要素が見つかりません');
            }
        }

        // モーダルを閉じる
        function closeReserveModal() {
            const modal = document.getElementById('reserve-modal');
            if (modal) {
                modal.classList.remove('active');
                document.body.style.overflow = '';
            }
        }

        // ESCキーでモーダルを閉じる
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeReserveModal();
            }
        });
    </script>
</body>

</html>