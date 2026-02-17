<?php
/**
 * pullcass - 口コミ・体験談ページ
 * 参考: reference/public_html/reviews.php
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/theme_helper.php';

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

$shopName = $tenant['name'];
$tenantId = $tenant['id'];
$tenantSlug = $tenant['code'];

// 口コミ機能が有効か（口コミは件数制限なし。写メ日記は1キャスト500件まで）
$reviewScrapeEnabled = false;
try {
    $pdo = getPlatformDb();
    $stmt = $pdo->prepare("SELECT is_enabled FROM tenant_features WHERE tenant_id = ? AND feature_code = 'review_scrape'");
    $stmt->execute([$tenantId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $reviewScrapeEnabled = $row && (int)$row['is_enabled'] === 1;
} catch (Exception $e) {
    // 無効のまま
}
if (!$reviewScrapeEnabled) {
    header('Location: /top');
    exit;
}

// ヘッダー・head 用変数（参考: diary.php）
$shopTitle = $tenant['title'] ?? '';
$logoLargeUrl = $tenant['logo_large_url'] ?? '';
$logoSmallUrl = $tenant['logo_small_url'] ?? '';
$faviconUrl = $tenant['favicon_url'] ?? '';
$phoneNumber = $tenant['phone'] ?? '';
$businessHours = $tenant['business_hours'] ?? '';
$businessHoursNote = $tenant['business_hours_note'] ?? '';
$currentTheme = getCurrentTheme($tenantId);
$themeData = $currentTheme['theme_data'];
$bodyClass = 'reviews-page';
$additionalCss = '';

// 口コミ投稿リンク用（ヘブンネット引用ブロック）
$reviewsBaseUrlForCitation = '';
try {
    $stmtUrl = $pdo->prepare("SELECT reviews_base_url FROM review_scrape_settings WHERE tenant_id = ? LIMIT 1");
    $stmtUrl->execute([$tenantId]);
    $rowUrl = $stmtUrl->fetch(PDO::FETCH_ASSOC);
    if ($rowUrl && !empty(trim($rowUrl['reviews_base_url'] ?? ''))) {
        $reviewsBaseUrlForCitation = rtrim(trim($rowUrl['reviews_base_url']), '/');
        if (strpos($reviewsBaseUrlForCitation, '?') !== false) {
            $reviewsBaseUrlForCitation .= '&lo=1&of=y';
        } else {
            $reviewsBaseUrlForCitation .= '?lo=1&of=y';
        }
    }
} catch (Exception $e) {
    // 未設定のまま
}

// 表示キャストのみ（tenant_casts.checked = 1 または cast_id なし）
$baseFrom = "reviews r LEFT JOIN tenant_casts tc ON tc.tenant_id = r.tenant_id AND tc.id = r.cast_id";
$baseWhere = "r.tenant_id = ? AND (tc.checked = 1 OR r.cast_id IS NULL)";

$searchKeyword = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchSort = isset($_GET['sort']) ? $_GET['sort'] : 'date';
$selectedCast = isset($_GET['cast']) ? trim($_GET['cast']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

if (!empty($searchKeyword)) {
    $normalizedKeyword = preg_replace('/[ 　\t]+/u', ' ', $searchKeyword);
    $normalizedKeyword = trim($normalizedKeyword);
    if ($normalizedKeyword !== $searchKeyword) {
        $redirectUrl = '/reviews?search=' . urlencode($normalizedKeyword);
        if ($searchSort !== 'date') $redirectUrl .= '&sort=' . urlencode($searchSort);
        if ($page > 1) $redirectUrl .= '&page=' . $page;
        header('Location: ' . $redirectUrl, true, 301);
        exit;
    }
    $searchKeyword = $normalizedKeyword;
}

// キャスト一覧（絞り込み用）
$castStmt = $pdo->prepare("
    SELECT DISTINCT r.cast_name FROM {$baseFrom}
    WHERE {$baseWhere} AND r.cast_name IS NOT NULL AND r.cast_name != ''
    ORDER BY r.cast_name
");
$castStmt->execute([$tenantId]);
$casts = $castStmt->fetchAll(PDO::FETCH_COLUMN);

$searchResults = [];
$searchTotalCount = 0;
$isSearchMode = !empty($searchKeyword);

if ($isSearchMode) {
    $keywords = preg_split('/[ 　\t]+/u', $searchKeyword, -1, PREG_SPLIT_NO_EMPTY);
    $keywords = array_filter($keywords, function ($k) { return trim($k) !== ''; });
    if (count($keywords) > 0) {
        $searchWhereConditions = [];
        $searchParams = [$tenantId];
        foreach ($keywords as $kw) {
            $like = '%' . trim($kw) . '%';
            $searchWhereConditions[] = "(r.title COLLATE utf8mb4_bin LIKE ? OR r.content COLLATE utf8mb4_bin LIKE ?)";
            $searchParams[] = $like;
            $searchParams[] = $like;
        }
        $searchWhereClause = $baseWhere . ' AND ' . implode(' AND ', $searchWhereConditions);
        $orderClause = ($searchSort === 'date_old') ? 'ORDER BY r.review_date ASC' : 'ORDER BY r.review_date DESC';
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM {$baseFrom} WHERE {$searchWhereClause}");
        $countStmt->execute($searchParams);
        $searchTotalCount = (int)$countStmt->fetchColumn();
        $searchParams[] = $perPage;
        $searchParams[] = $offset;
        $searchStmt = $pdo->prepare("SELECT r.id, r.title, r.content, r.rating, r.cast_name, r.cast_id, r.review_date, r.user_name FROM {$baseFrom} WHERE {$searchWhereClause} {$orderClause} LIMIT ? OFFSET ?");
        $searchStmt->execute($searchParams);
        $searchResults = $searchStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

$whereClause = $baseWhere;
$params = [$tenantId];
if (!empty($selectedCast)) {
    $whereClause .= " AND r.cast_name = ?";
    $params[] = $selectedCast;
}
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM {$baseFrom} WHERE {$whereClause}");
$countStmt->execute($params);
$totalCount = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalCount / $perPage));

$orderClause = ($searchSort === 'date_old') ? 'ORDER BY r.review_date ASC' : 'ORDER BY r.review_date DESC';
$params[] = $perPage;
$params[] = $offset;
$stmt = $pdo->prepare("SELECT r.* FROM {$baseFrom} WHERE {$whereClause} {$orderClause} LIMIT ? OFFSET ?");
$stmt->execute($params);
$reviews = $isSearchMode ? $searchResults : $stmt->fetchAll(PDO::FETCH_ASSOC);

$uniqueReviews = [];
$seenIds = [];
foreach ($reviews as $review) {
    if (!in_array($review['id'], $seenIds)) {
        $uniqueReviews[] = $review;
        $seenIds[] = $review['id'];
    }
}
$reviews = $uniqueReviews;

$displayTotal = $isSearchMode ? $searchTotalCount : $totalCount;
$pageTitle = '口コミ・体験談｜' . $shopName;
$pageDescription = $shopName . 'の口コミ・体験談一覧です。';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<?php include __DIR__ . '/includes/head.php'; ?>
<?php include __DIR__ . '/../../includes/top_section_renderer.php';
renderSectionStyles(); ?>
<style>
/* 口コミページ（参考: reference/public_html/reviews.php） */
.title-section { max-width: 1100px; margin: 0 auto; text-align: left; padding: 14px 16px 0; }
.title-section h1 { font-family: var(--font-title1); font-size: 32px; font-weight: 400; color: var(--color-primary); margin: 0; }
.title-section h2 { font-family: var(--font-title2); font-size: 16px; font-weight: 400; margin: 0; color: var(--color-text); }
.dot-line { height: 10px; background-image: repeating-radial-gradient(circle, var(--color-primary) 0 2px, transparent 2px 12px); background-repeat: repeat-x; margin: 0 0 10px 0; }
.review-filter-section { margin: 20px 0; padding: 20px; background: rgba(255,255,255,0.9); border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 1100px; margin-left: auto; margin-right: auto; }
.review-filter-section h3 { margin: 0 0 15px 0; color: var(--color-text); font-size: 18px; text-align: center; }
.review-search-container { margin: 20px 0; padding: 20px; background: rgba(255,255,255,0.9); border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 1100px; margin-left: auto; margin-right: auto; }
.review-search-container h2 { color: var(--color-text); font-size: 18px; margin-bottom: 10px; }
.search-input-group { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: 10px; }
.search-input-group input[type="text"] { flex: 1; min-width: 200px; padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
.search-input-group button, .search-input-group .btn-clear { padding: 8px 20px; background: var(--color-primary); color: var(--color-btn-text); border: none; border-radius: 5px; font-size: 14px; cursor: pointer; text-decoration: none; display: inline-block; }
.search-input-group .btn-clear { background: var(--color-text); }
.reviews-list-wrap { max-width: 1100px; margin: 20px auto; padding: 0 10px; }
/* 口コミカードはインラインで背景・枠を指定（ピックアップは#FFF8DC・金枠） */
.pickup-text { display: block; }
.review-header { display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 10px; margin-bottom: 15px; }
.review-info h4 { margin: 0 0 5px 0; color: var(--color-text); font-size: 18px; }
.review-meta { font-size: 14px; color: var(--color-text); }
.review-rating { font-size: 18px; font-weight: bold; color: var(--color-primary); }
.review-content { margin-bottom: 15px; line-height: 1.6; color: var(--color-text); text-align: left; }
.shop-comment-block { margin-top: 15px; padding: 15px; background: #f8f9fa; border-radius: 8px; border: 1px solid #ddd; }
.shop-comment-block h5 { margin: 0 0 10px 0; color: var(--color-primary); font-size: 14px; }
.review-pagination { display: flex; gap: 6px; justify-content: center; margin: 16px 0; flex-wrap: wrap; align-items: center; }
.review-pagination a, .review-pagination span { padding: 6px 10px; border-radius: 6px; text-decoration: none; font-weight: bold; }
.review-pagination a { border: 1px solid var(--color-primary); color: var(--color-primary); }
.review-pagination span.current { background: var(--color-primary); color: #fff; }
.breadcrumb { font-size: 12px; padding: 8px 10px; opacity: 0.9; }
.breadcrumb a { text-decoration: none; color: inherit; }
/* キャスト名（参考サイト準拠） */
.review-item .review-rating a[href^="/cast/"],
.cast-name-link, .cast-name-text { color: var(--color-primary) !important; font-size: 18px !important; font-weight: bold !important; text-decoration: none !important; }
.review-item .review-rating a[href^="/cast/"]:hover { text-decoration: underline !important; }
</style>
</head>
<body class="<?php echo h($bodyClass); ?>">
<?php include __DIR__ . '/includes/header.php'; ?>
<main class="main-content">
    <div class="main-content-wrapper">
        <nav class="breadcrumb">
            <a href="/"><?php echo h($shopName); ?></a><span>»</span><a href="/top">トップ</a><span>»</span>
            <?php if ($isSearchMode && !empty($searchKeyword)): ?>
                <a href="/reviews">口コミ</a><span>»</span>検索結果「<?php echo h($searchKeyword); ?>」
            <?php elseif (!empty($selectedCast)): ?>
                <a href="/reviews">口コミ</a><span>»</span><?php echo h($selectedCast); ?>の口コミ
            <?php else: ?>
                口コミ
            <?php endif; ?>
        </nav>

        <section class="title-section">
            <h1>REVIEW</h1>
            <h2>口コミ</h2>
            <div class="dot-line"></div>
        </section>

        <section style="margin-top: 25px; padding: 0 10px;">
            <?php if (count($casts) > 0): ?>
            <div class="review-filter-section">
                <h3>キャストで絞り込み</h3>
                <form method="GET" action="/reviews" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; justify-content: center;">
                    <select name="cast" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; min-width: 200px;">
                        <option value="">すべてのキャスト</option>
                        <?php foreach ($casts as $c): ?>
                        <option value="<?php echo h($c); ?>" <?php echo $selectedCast === $c ? 'selected' : ''; ?>><?php echo h($c); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" style="padding: 8px 20px; background: var(--color-primary); color: var(--color-btn-text); border: none; border-radius: 5px; cursor: pointer; font-size: 14px;">絞り込み</button>
                    <?php if (!empty($selectedCast)): ?>
                    <a href="/reviews" style="padding: 8px 20px; background: var(--color-text); color: var(--color-btn-text); text-decoration: none; border-radius: 5px; font-size: 14px;">クリア</a>
                    <?php endif; ?>
                </form>
                <p style="margin: 10px 0 0 0; color: var(--color-text); font-size: 14px; text-align: center;">キャストを選択して、そのキャストの口コミのみを表示できます。</p>
                <p style="margin: 5px 0 0 0; color: var(--color-text); font-size: 14px; text-align: center;">最新順: <?php echo number_format($totalCount); ?>件<?php if (!empty($selectedCast)): ?> (<?php echo h($selectedCast); ?>の口コミ)<?php endif; ?></p>
            </div>
            <?php endif; ?>

            <div class="review-search-container">
                <h2>口コミ検索</h2>
                <form method="GET" action="/reviews" id="reviewSearchForm">
                    <?php if (!empty($selectedCast)): ?><input type="hidden" name="cast" value="<?php echo h($selectedCast); ?>"><?php endif; ?>
                    <div class="search-input-group">
                        <input type="text" name="search" placeholder="キーワードで口コミを検索..." value="<?php echo h($searchKeyword); ?>">
                        <button type="submit">検索</button>
                        <a href="/reviews" class="btn-clear">クリア</a>
                    </div>
                    <div class="search-filters" style="display: flex; gap: 10px; align-items: center; margin-top: 10px;">
                        <label style="font-size: 14px; color: var(--color-text);">並び順:</label>
                        <select name="sort" onchange="this.form.submit()" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px;">
                            <option value="date" <?php echo $searchSort === 'date' ? 'selected' : ''; ?>>新着順</option>
                            <option value="date_old" <?php echo $searchSort === 'date_old' ? 'selected' : ''; ?>>古い順</option>
                        </select>
                    </div>
                </form>
            </div>

            <!-- ヘブンネットより引用・口コミ投稿はコチラから（参考サイト準拠） -->
            <?php if ($reviewsBaseUrlForCitation !== ''): ?>
            <div style="text-align: center; margin: 20px 0 10px 0;">
                <a href="<?php echo h($reviewsBaseUrlForCitation); ?>" target="_blank" rel="noopener noreferrer" style="text-decoration: none; color: inherit; display: inline-flex; align-items: center; gap: 5px;">
                    <img src="/img/hp/heaven.png" alt="ヘブンネット" style="height: 25px; width: auto;">
                    <span style="color: var(--color-text); font-size: 14px;">ヘブンネットより引用</span>
                </a>
                <a href="<?php echo h($reviewsBaseUrlForCitation); ?>" target="_blank" rel="noopener noreferrer" style="text-decoration: none; color: inherit; display: block; margin-top: -15px;">
                    <span style="color: var(--color-text); font-size: 14px; font-weight: bold;">口コミ投稿はコチラから</span>
                </a>
            </div>
            <?php endif; ?>

            <div class="reviews-list-wrap">
                <?php if (empty($reviews)): ?>
                <div style="text-align: center; padding: 50px; background: rgba(255,255,255,0.9); border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <p style="color: var(--color-text); font-size: 16px;">口コミが見つかりませんでした。</p>
                </div>
                <?php else: ?>
                <?php foreach ($reviews as $index => $review): ?>
                <?php $isPickup = ($index === 0 && $page <= 1 && empty($selectedCast) && !$isSearchMode); ?>
                <article id="review-<?php echo (int)$review['id']; ?>" class="review-item <?php echo $isPickup ? 'pickup-review' : ''; ?>" style="margin-bottom: 20px; padding: 20px; background: <?php echo $isPickup ? '#FFF8DC' : '#fff'; ?>; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);<?php echo $isPickup ? ' border: 2px solid #FFD700;' : ''; ?> scroll-margin-top: 120px;">
                    <?php if ($isPickup): ?>
                    <div class="pickup-text" style="color: var(--color-primary); font-size: 20px; font-weight: bold; text-align: left; margin-bottom: 0; transform: rotate(-5deg); transform-origin: left center;">
                        ピックアップ！
                    </div>
                    <?php endif; ?>
                    <div class="review-header">
                        <div class="review-info">
                            <h4><?php echo h($review['title'] ?: 'タイトルなし'); ?></h4>
                            <div class="review-meta">
                                <span>投稿者: <?php echo h($review['user_name']); ?></span>
                                <span style="margin-left: 15px;">投稿日: <?php echo $review['review_date'] ? date('Y年m月d日', strtotime($review['review_date'])) : '日付不明'; ?></span>
                            </div>
                        </div>
                        <div class="review-rating">
                            <?php
                            $rating = (float)($review['rating'] ?? 0);
                            $fullStars = (int)floor($rating);
                            $emptyStars = 5 - $fullStars;
                            echo str_repeat('★', min(5, $fullStars));
                            if ($emptyStars > 0) echo str_repeat('☆', $emptyStars);
                            echo ' ' . number_format($rating, 1);
                            ?>
                            <?php if (!empty($review['cast_name'])): ?>
                            <div style="margin-top: 4px; font-size: 14px;">
                                <?php if (!empty($review['cast_id'])): ?>
                                <a href="/cast/<?php echo (int)$review['cast_id']; ?>" style="color: var(--color-primary); font-weight: bold;"><?php echo h($review['cast_name']); ?></a>
                                <?php else: ?>
                                <span><?php echo h($review['cast_name']); ?></span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="review-content">
                        <p style="margin: 0;"><?php echo nl2br(h($review['content'])); ?></p>
                    </div>
                    <?php if (!empty($review['shop_comment'])): ?>
                    <div class="shop-comment-block">
                        <h5>お店からのコメント</h5>
                        <p style="margin: 0; line-height: 1.5; font-size: 14px;"><?php echo nl2br(h($review['shop_comment'])); ?></p>
                    </div>
                    <?php endif; ?>
                </article>
                <?php endforeach; ?>

                <?php if ($totalPages > 1): ?>
                <div class="review-pagination">
                    <?php $q = $_GET; unset($q['page']); $queryString = http_build_query($q); $baseUrl = '/reviews' . ($queryString !== '' ? '?' . $queryString : ''); $sep = ($queryString !== '') ? '&' : '?'; ?>
                    <?php if ($page > 1): ?>
                    <a href="<?php echo $page === 2 ? $baseUrl : $baseUrl . $sep . 'page=' . ($page - 1); ?>">《</a>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <?php if ($i == $page): ?>
                    <span class="current"><?php echo $i; ?></span>
                    <?php else: ?>
                    <a href="<?php echo $baseUrl . $sep . 'page=' . $i; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                    <a href="<?php echo $baseUrl . $sep . 'page=' . $totalPages; ?>">》</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>
</main>
<!-- フッターナビゲーション -->
<?php include __DIR__ . '/includes/footer_nav.php'; ?>
<!-- 固定フッター（電話ボタン） -->
<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
