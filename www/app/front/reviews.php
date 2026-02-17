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

// 口コミ機能が有効か
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

include __DIR__ . '/includes/head.php';
?>
<body class="reviews-page">
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

        <h1 class="page-title">口コミ・体験談</h1>

        <?php if (count($casts) > 0): ?>
        <div class="review-filters" style="margin-bottom: 20px;">
            <label>キャストで絞り込み:</label>
            <select onchange="location.href='/reviews?cast='+encodeURIComponent(this.value)">
                <option value="">すべて</option>
                <?php foreach ($casts as $c): ?>
                <option value="<?php echo h($c); ?>" <?php echo $selectedCast === $c ? 'selected' : ''; ?>><?php echo h($c); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>

        <form method="get" action="/reviews" class="review-search-form" style="margin-bottom: 20px;">
            <input type="text" name="search" placeholder="キーワードで口コミを検索..." value="<?php echo h($searchKeyword); ?>">
            <input type="hidden" name="sort" value="<?php echo h($searchSort); ?>">
            <?php if (!empty($selectedCast)): ?><input type="hidden" name="cast" value="<?php echo h($selectedCast); ?>"><?php endif; ?>
            <button type="submit">検索</button>
        </form>

        <div class="reviews-list">
            <?php if (empty($reviews)): ?>
                <p>口コミが見つかりませんでした。</p>
            <?php else: ?>
                <?php foreach ($reviews as $review): ?>
                <article id="review-<?php echo (int)$review['id']; ?>" class="review-item" style="margin-bottom: 24px; padding: 16px; background: rgba(255,255,255,0.06); border-radius: 8px;">
                    <h2 style="font-size: 1.1rem; margin-bottom: 8px;"><?php echo h($review['title'] ?: 'タイトルなし'); ?></h2>
                    <div style="font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 8px;">
                        投稿者: <?php echo h($review['user_name']); ?>
                        <?php if (!empty($review['cast_name'])): ?> / キャスト: <?php echo h($review['cast_name']); ?><?php endif; ?>
                        <?php if (!empty($review['review_date'])): ?> / <?php echo date('Y年n月j日', strtotime($review['review_date'])); ?><?php endif; ?>
                    </div>
                    <?php if (!empty($review['rating'])): ?>
                    <div style="color: #FFD700; margin-bottom: 8px;"><?php echo str_repeat('★', min(5, (int)$review['rating'])) . str_repeat('☆', 5 - min(5, (int)$review['rating'])); ?></div>
                    <?php endif; ?>
                    <div class="review-content" style="line-height: 1.6;"><?php echo nl2br(h($review['content'])); ?></div>
                </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <nav class="pagination" style="margin-top: 24px;">
            <?php
            $q = $_GET;
            unset($q['page']);
            $query = http_build_query($q);
            $baseUrl = '/reviews' . ($query !== '' ? '?' . $query : '');
            $sep = ($query !== '') ? '&' : '?';
            if ($page > 1): ?>
                <a href="<?php echo $page === 2 ? $baseUrl : $baseUrl . $sep . 'page=' . ($page - 1); ?>">« 前へ</a>
            <?php endif; ?>
            <span style="margin: 0 12px;"><?php echo $page; ?> / <?php echo $totalPages; ?></span>
            <?php if ($page < $totalPages): ?>
                <a href="<?php echo $baseUrl . $sep . 'page=' . ($page + 1); ?>">次へ »</a>
            <?php endif; ?>
        </nav>
        <?php endif; ?>
    </div>
</main>
<?php include __DIR__ . '/includes/footer_nav.php'; ?>
</body>
</html>
