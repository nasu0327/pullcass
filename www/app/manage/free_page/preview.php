<?php
/**
 * フリーページ管理 - プレビューページ
 */

// 認証チェック（$tenant, $tenantIdが自動設定される）
require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

require_once __DIR__ . '/../../../includes/free_page_helpers.php';
require_once __DIR__ . '/../../../includes/theme_helper.php';

$pdo = getPlatformDb();

// ページ取得
if (!isset($_GET['id']) || !$_GET['id']) {
    header('Location: index.php?tenant=' . $tenantSlug);
    exit;
}

$page = getFreePage($pdo, (int) $_GET['id'], $tenantId);
if (!$page) {
    header('Location: index.php?tenant=' . $tenantSlug);
    exit;
}

// テーマを取得
$currentTheme = getCurrentTheme($tenantId);
$themeData = $currentTheme['theme_data'];

// 店舗情報
$shopName = $tenant['name'];
$pageTitle = !empty($page['main_title']) ? $page['main_title'] : $page['title'];
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($pageTitle); ?> | <?php echo h($shopName); ?> - プレビュー</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/style.css?v=<?php echo time(); ?>">

    <!-- テーマCSS変数（styleタグを含む） -->
    <?php echo generateThemeCSSVariables($themeData); ?>

    <style>
        /* プレビューバー */
        .preview-bar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
            color: #fff;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 9999;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .preview-bar .label {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
        }

        .preview-bar .actions {
            display: flex;
            gap: 10px;
        }

        .preview-bar .btn {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .preview-bar .btn-edit {
            background: #fff;
            color: #f57c00;
        }

        .preview-bar .btn-close {
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
        }

        .preview-bar .btn:hover {
            transform: translateY(-1px);
        }

        /* ページコンテンツ */
        body {
            padding-top: 50px;
            background-image: var(--color-bg-gradient);
            background-color: var(--color-bg);
            background-repeat: no-repeat;
            background-attachment: fixed;
            min-height: 100vh;
        }

        .free-page-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .page-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .page-header .sub-title {
            font-family: var(--font-title2-ja);
            font-size: 14px;
            color: var(--color-primary);
            margin-bottom: 10px;
            letter-spacing: 2px;
        }

        .page-header .main-title {
            font-family: var(--font-title1-ja);
            font-size: 28px;
            font-weight: 700;
            color: var(--color-text);
            margin: 0;
        }

        .page-header .title-divider {
            height: 10px;
            width: 100%;
            max-width: 300px;
            margin: 20px auto 0;
            background-image: repeating-radial-gradient(circle, var(--color-primary) 0 2px, transparent 2px 12px);
            background-repeat: repeat-x;
            background-size: 12px 10px;
        }

        .featured-image {
            width: 100%;
            border-radius: 12px;
            margin-bottom: 30px;
        }

        .page-content {
            font-family: var(--font-body);
            font-size: 16px;
            line-height: 1.8;
            color: var(--color-text);
        }

        .page-content img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
        }

        .page-content h1,
        .page-content h2,
        .page-content h3,
        .page-content h4,
        .page-content h5,
        .page-content h6 {
            font-family: var(--font-title1-ja);
            color: var(--color-text);
            margin: 1.5em 0 0.5em;
        }

        .page-content p {
            margin: 0 0 1em;
        }

        .page-content a {
            color: var(--color-primary);
        }

        /* レスポンシブ */
        @media (max-width: 768px) {
            .page-header .main-title {
                font-size: 22px;
            }

            .free-page-container {
                padding: 30px 15px;
            }
        }
    </style>
</head>

<body>
    <!-- プレビューバー -->
    <div class="preview-bar">
        <div class="label">
            <i class="fas fa-eye"></i>
            プレビューモード（<?php echo $page['status'] === 'published' ? '公開中' : '下書き'; ?>）
        </div>
        <div class="actions">
            <a href="post.php?tenant=<?php echo h($tenantSlug); ?>&id=<?php echo $page['id']; ?>" class="btn btn-edit">
                <i class="fas fa-edit"></i>
                編集
            </a>
            <a href="index.php?tenant=<?php echo h($tenantSlug); ?>" class="btn btn-close">
                <i class="fas fa-times"></i>
                閉じる
            </a>
        </div>
    </div>

    <div class="free-page-container">
        <!-- ページヘッダー -->
        <header class="page-header">
            <?php if (!empty($page['sub_title'])): ?>
                <div class="sub-title"><?php echo h($page['sub_title']); ?></div>
            <?php endif; ?>
            <h1 class="main-title"><?php echo h($pageTitle); ?></h1>
            <div class="title-divider"></div>
        </header>

        <!-- アイキャッチ画像 -->
        <?php if (!empty($page['featured_image'])): ?>
            <img src="<?php echo h($page['featured_image']); ?>" alt="<?php echo h($pageTitle); ?>" class="featured-image">
        <?php endif; ?>

        <!-- コンテンツ -->
        <div class="page-content">
            <?php echo $page['content']; ?>
        </div>
    </div>
</body>

</html>