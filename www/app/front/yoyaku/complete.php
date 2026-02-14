<?php
/**
 * pullcass - 予約完了ページ
 */

session_start();

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../../../includes/theme_helper.php';

// 完了データがない場合はトップへ
if (!isset($_SESSION['reservation_complete'])) {
    header('Location: /app/front/top');
    exit;
}

$completeData = $_SESSION['reservation_complete'];
unset($_SESSION['reservation_complete']); // 一度表示したら削除

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

$shopName = $tenant['name'];
$tenantId = $tenant['id'];
$phoneNumber = $tenant['phone'] ?? '';

// テーマを取得
$currentTheme = getCurrentTheme($tenantId);
$themeData = $currentTheme['theme_data'];

// ページタイトル
$pageTitle = '予約完了｜' . $shopName;
$pageDescription = $shopName . 'のネット予約が完了しました。';
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <?php include __DIR__ . '/../includes/head.php'; ?>
    <style>
        .complete-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        .complete-card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 20px;
            padding: 40px 30px;
            text-align: center;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        .complete-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        .complete-title {
            font-size: 1.5em;
            font-weight: bold;
            color: var(--color-primary);
            margin-bottom: 15px;
        }

        .complete-message {
            color: #666;
            line-height: 1.8;
            margin-bottom: 30px;
        }

        .reservation-summary {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            text-align: left;
        }

        .reservation-summary-title {
            font-weight: bold;
            color: var(--color-primary);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--color-primary);
        }

        .reservation-summary-item {
            display: flex;
            margin-bottom: 10px;
            font-size: 0.95em;
        }

        .reservation-summary-label {
            width: 100px;
            color: #666;
            flex-shrink: 0;
        }

        .reservation-summary-value {
            color: #333;
            font-weight: 500;
        }

        .notice-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 30px;
            font-size: 0.9em;
            color: #856404;
            text-align: left;
        }

        .notice-box strong {
            display: block;
            margin-bottom: 5px;
        }

        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .action-btn {
            display: block;
            padding: 15px 30px;
            border-radius: 30px;
            font-weight: bold;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .action-btn-primary {
            background: var(--color-primary);
            color: white;
        }

        .action-btn-primary:hover {
            opacity: 0.8;
        }

        .action-btn-secondary {
            background: white;
            color: var(--color-primary);
            border: 2px solid var(--color-primary);
        }

        .action-btn-secondary:hover {
            background: var(--color-primary);
            color: white;
        }

        .phone-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--color-primary);
            font-weight: bold;
            text-decoration: none;
            font-size: 1.2em;
        }

        .phone-link:hover {
            opacity: 0.8;
        }
    </style>
</head>

<body>
    <?php include __DIR__ . '/../includes/header.php'; ?>

    <main class="main-content">
        <!-- パンくず -->
        <nav class="breadcrumb">
            <a href="/app/front/index">ホーム</a><span>»</span>
            <a href="/app/front/top">トップ</a><span>»</span>
            予約完了 |
        </nav>

        <!-- タイトルセクション -->
        <section class="title-section" style="margin-bottom: 20px;">
            <h1>COMPLETE</h1>
            <h2>予約完了</h2>
            <div class="dot-line"></div>
        </section>

        <div class="complete-container">
            <div class="complete-card">
                <div class="complete-icon">✅</div>
                <div class="complete-title">ご予約を受け付けました</div>
                <div class="complete-message">
                    <?php echo h($completeData['customer_name']); ?>様、ネット予約のお申し込みありがとうございます。<br>
                    お店からの確認連絡をお待ちください。
                </div>

                <!-- 予約内容サマリー -->
                <div class="reservation-summary">
                    <div class="reservation-summary-title">📋 ご予約内容</div>
                    <div class="reservation-summary-item">
                        <span class="reservation-summary-label">予約番号</span>
                        <span class="reservation-summary-value">#<?php echo h($completeData['reservation_id']); ?></span>
                    </div>
                    <div class="reservation-summary-item">
                        <span class="reservation-summary-label">利用予定日</span>
                        <span class="reservation-summary-value"><?php echo h($completeData['reservation_date']); ?></span>
                    </div>
                    <div class="reservation-summary-item">
                        <span class="reservation-summary-label">希望時刻</span>
                        <span class="reservation-summary-value"><?php echo h($completeData['reservation_time']); ?></span>
                    </div>
                    <?php if ($completeData['nomination_type'] === 'shimei' && !empty($completeData['cast_name'])): ?>
                    <div class="reservation-summary-item">
                        <span class="reservation-summary-label">指名</span>
                        <span class="reservation-summary-value"><?php echo h($completeData['cast_name']); ?>さん</span>
                    </div>
                    <?php else: ?>
                    <div class="reservation-summary-item">
                        <span class="reservation-summary-label">指名</span>
                        <span class="reservation-summary-value">フリー（指名なし）</span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- 注意事項 -->
                <div class="notice-box">
                    <strong>⚠️ ご注意ください</strong>
                    このネット予約は<strong>仮予約</strong>です。<br>
                    お店からの確認連絡をもって予約確定となります。<br>
                    ご希望の日時・キャストが確保できない場合がございます。
                </div>

                <?php if ($phoneNumber): ?>
                <p style="margin-bottom: 20px; color: #666;">
                    お急ぎの場合はお電話でお問い合わせください
                </p>
                <p style="margin-bottom: 30px;">
                    <a href="tel:<?php echo h(preg_replace('/[^0-9]/', '', $phoneNumber)); ?>" class="phone-link">
                        📞 <?php echo h($phoneNumber); ?>
                    </a>
                </p>
                <?php endif; ?>

                <!-- アクションボタン -->
                <div class="action-buttons">
                    <a href="/app/front/top" class="action-btn action-btn-primary">
                        トップページへ戻る
                    </a>
                    <a href="/app/front/cast/list" class="action-btn action-btn-secondary">
                        キャスト一覧を見る
                    </a>
                </div>
            </div>
        </div>
    </main>

    <?php include __DIR__ . '/../includes/footer_nav.php'; ?>
    <?php include __DIR__ . '/../includes/footer.php'; ?>

    <?php
    // プレビューバーを表示
    if (isset($currentTheme['is_preview']) && $currentTheme['is_preview']) {
        echo generatePreviewBar($currentTheme, $tenantId, $tenant['code']);
    }
    ?>
</body>

</html>
