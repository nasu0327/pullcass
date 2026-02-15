<?php
/**
 * pullcass - 動画・写メ日記ページ
 * 参考: reference/public_html/diary.php
 */

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
$logoLargeUrl = $tenant['logo_large_url'] ?? '';
$logoSmallUrl = $tenant['logo_small_url'] ?? '';
$faviconUrl = $tenant['favicon_url'] ?? '';
$phoneNumber = $tenant['phone'] ?? '';
$businessHours = $tenant['business_hours'] ?? '';
$businessHoursNote = $tenant['business_hours_note'] ?? '';

// テーマを取得
$currentTheme = getCurrentTheme($tenantId);
$themeData = $currentTheme['theme_data'];

// データベース接続
try {
    $pdo = getPlatformDb();
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("データベース接続エラー");
}

// パラメータ取得
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$writer = trim((string)($_GET['writer'] ?? ''));
$searchKeyword = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = $_GET['sort'] ?? 'date';

// 検索キーワードの正規化
if (!empty($searchKeyword)) {
    $normalizedKeyword = preg_replace('/[ 　\t]+/u', ' ', $searchKeyword);
    $normalizedKeyword = trim($normalizedKeyword);
    
    if ($normalizedKeyword !== $searchKeyword) {
        $redirectUrl = '/diary?search=' . urlencode($normalizedKeyword);
        if ($sort !== 'date') $redirectUrl .= '&sort=' . urlencode($sort);
        if (!empty($writer)) $redirectUrl .= '&writer=' . urlencode($writer);
        if ($page > 1) $redirectUrl .= '&page=' . $page;
        header('Location: ' . $redirectUrl, true, 301);
        exit;
    }
    $searchKeyword = $normalizedKeyword;
}

// WHERE条件生成
$where = ['dp.tenant_id = :tenant_id'];
$params = [':tenant_id' => $tenantId];

if ($writer !== '') {
    $where[] = 'dp.cast_name = :writer';
    $params[':writer'] = $writer;
}

// キーワード検索
if (!empty($searchKeyword)) {
    $keywords = explode(' ', $searchKeyword);
    foreach ($keywords as $ki => $kw) {
        if (empty($kw)) continue;
        $key = ':kw' . $ki;
        $where[] = "(dp.title LIKE {$key} OR dp.html_body LIKE {$key})";
        $params[$key] = '%' . $kw . '%';
    }
}

// 非表示キャスト除外（tenant_castsのchecked=1のみ）
$joinSql = "INNER JOIN tenant_casts tc ON dp.tenant_id = tc.tenant_id AND dp.cast_id = tc.id AND tc.checked = 1";
$whereSql = 'WHERE ' . implode(' AND ', $where);

// ソート
$orderSql = ($sort === 'date_asc') ? 'dp.posted_at ASC' : 'dp.posted_at DESC, dp.created_at DESC';

// キャスト一覧（絞り込み用）- tenant_castsのソート順
$writers = [];
try {
    $stmtW = $pdo->prepare("
        SELECT DISTINCT tc.name
        FROM tenant_casts tc
        INNER JOIN diary_posts dp ON tc.tenant_id = dp.tenant_id AND tc.id = dp.cast_id
        WHERE tc.tenant_id = ? AND tc.checked = 1 AND tc.name <> ''
        ORDER BY tc.sort_order ASC, tc.id DESC
    ");
    $stmtW->execute([$tenantId]);
    $writers = $stmtW->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch (Exception $e) {
    $writers = [];
}

// 件数
$total = 0;
try {
    $stmtC = $pdo->prepare("SELECT COUNT(*) FROM diary_posts dp {$joinSql} {$whereSql}");
    $stmtC->execute($params);
    $total = (int)$stmtC->fetchColumn();
} catch (Exception $e) {
    $total = 0;
}

$lastPage = max(1, (int)ceil($total / $perPage));
$page = min($page, $lastPage);
$offset = ($page - 1) * $perPage;

// データ取得
$posts = [];
try {
    $sql = "
        SELECT dp.id, dp.pd_id, dp.title, dp.cast_name, dp.posted_at, dp.created_at,
               dp.thumb_url, dp.video_url, dp.poster_url, dp.html_body, dp.has_video, dp.detail_url
        FROM diary_posts dp
        {$joinSql}
        {$whereSql}
        ORDER BY {$orderSql}
        LIMIT :limit OFFSET :offset
    ";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    $posts = [];
}

// ページタイトル
$pageTitle = '動画・写メ日記一覧｜' . $shopName;
if (!empty($writer)) {
    $pageTitle = h($writer) . 'の動画・写メ日記｜' . $shopName;
}
$pageDescription = $shopName . 'の動画・写メ日記一覧ページ。キャストの最新日記を随時更新中！';

$bodyClass = '';
$additionalCss = '';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<?php include __DIR__ . '/includes/head.php'; ?>
<style>
/* パンくずリスト */
.breadcrumb {
    font-size: 12px;
    padding: 1px 10px;
    opacity: 0.7;
    text-align: left;
}
.breadcrumb a { text-decoration: none; }
.breadcrumb a:hover { text-decoration: underline; }
.breadcrumb span { margin: 0 4px; }

@media (min-width: 768px) {
    .breadcrumb {
        font-size: 12px;
        padding-top: 5px;
        padding-left: 20px;
    }
}

/* タイトルセクション */
.title-section {
    max-width: 1100px;
    margin-left: auto;
    margin-right: auto;
    text-align: left;
    padding: 14px 16px 0;
}
.title-section h1 {
    font-family: var(--font-title1);
    font-size: 32px;
    font-weight: 400;
    line-height: 31px;
    letter-spacing: -0.8px;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
    color: var(--color-primary);
    margin: 0;
}
.title-section h2 {
    font-family: var(--font-title2);
    font-size: 16px;
    font-weight: 400;
    line-height: 31px;
    letter-spacing: -0.8px;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
    margin: 0;
}
@media (min-width: 768px) {
    .title-section { text-align: left; padding-bottom: 30px; }
    .title-section h1 { font-size: 40px; }
    .title-section h2 { font-size: 20px; }
}

/* ドットライン */
.dot-line {
    height: 10px;
    background-image: repeating-radial-gradient(circle, var(--color-primary) 0 2px, transparent 2px 12px);
    background-repeat: repeat-x;
    background-size: 12px 10px;
    margin: 0;
    margin-bottom: -20px;
}

/* メインコンテンツ */
.diary-main {
    max-width: 1100px;
    margin-left: auto;
    margin-right: auto;
    padding: 0 15px;
}

/* 絞り込みセクション */
.filter-section {
    margin: 20px 0;
    padding: 20px;
    background: rgba(255,255,255,0.6);
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
.filter-section h3 {
    margin: 0 0 15px 0;
    color: var(--color-text);
    font-size: 18px;
    text-align: center;
}
.filter-form {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
    justify-content: center;
}
.filter-select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 14px;
    min-width: 200px;
    background: white;
    color: var(--color-text);
}
.filter-input {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 14px;
    min-width: 200px;
    flex: 1;
    background: white;
    color: var(--color-text);
}
.filter-btn {
    padding: 8px 20px;
    background: var(--color-primary);
    color: var(--color-btn-text);
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.2s ease;
}
.filter-btn:hover {
    opacity: 0.85;
    transform: translateY(-1px);
}
.filter-btn-clear {
    padding: 8px 20px;
    background: var(--color-text);
    color: var(--color-btn-text);
    text-decoration: none;
    border-radius: 5px;
    font-size: 14px;
    transition: all 0.2s ease;
}
.filter-btn-clear:hover {
    opacity: 0.85;
    transform: translateY(-1px);
}
.filter-count {
    margin: 10px 0 0 0;
    color: var(--color-text);
    font-size: 14px;
    text-align: center;
}

/* 日記グリッド */
.diary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 12px;
}
@media (max-width: 768px) {
    .diary-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 6px;
    }
}

/* 日記カード */
.diary-card {
    cursor: pointer;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    overflow: hidden;
    background: rgba(255,255,255,0.6);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.diary-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0,0,0,0.15);
}
.diary-card-thumb {
    position: relative;
    aspect-ratio: 1/1;
    background: #f0f0f0;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}
.diary-card-thumb img,
.diary-card-thumb video {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.diary-card-video-badge {
    position: absolute;
    right: 8px;
    bottom: 8px;
    background: rgba(0,0,0,0.8);
    color: #fff;
    padding: 4px 8px;
    font-size: 11px;
    border-radius: 4px;
    font-weight: bold;
}
.diary-card-info {
    padding: 10px 12px;
}
.diary-card-title {
    font-weight: 600;
    margin-bottom: 2px;
    color: var(--color-text);
}
.diary-card-meta {
    font-size: 13px;
    color: var(--color-text);
    line-height: 1.1;
    margin-bottom: 1px;
}
@media (max-width: 768px) {
    .diary-card-info { padding: 6px 8px; }
    .diary-card-title,
    .diary-card-meta { font-size: 11px; }
}

/* ページネーション */
.diary-pagination {
    display: flex;
    gap: 6px;
    justify-content: center;
    margin: 16px 0;
    flex-wrap: wrap;
    align-items: center;
}
.diary-pagination a {
    padding: 6px 10px;
    border: 1px solid #ddd;
    color: var(--color-text);
    border-radius: 6px;
    text-decoration: none;
    font-weight: normal;
}
.diary-pagination a.active {
    border-color: var(--color-primary);
    color: var(--color-primary);
    font-weight: bold;
}
.diary-pagination a.nav-btn {
    border-color: var(--color-primary);
    color: var(--color-primary);
    font-weight: bold;
}

/* 空の状態 */
.diary-empty {
    padding: 40px 20px;
    text-align: center;
    background: rgba(255,255,255,0.6);
    border-radius: 10px;
    color: var(--color-text);
    margin: 20px 0;
}

/* レスポンシブ：フィルター */
@media (max-width: 768px) {
    .filter-select { min-width: auto; flex: 1; }
    .filter-input { min-width: 100%; margin-bottom: 10px; }
    .filter-btn, .filter-btn-clear { padding: 8px 12px; font-size: 13px; }
    .filter-section h3 { font-size: 16px; }
}
</style>
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<main class="diary-main">
  <!-- パンくず -->
  <nav class="breadcrumb">
    <a href="/">ホーム</a><span>»</span><a href="/top">トップ</a><span>»</span>動画・写メ日記 |
  </nav>

  <!-- タイトルセクション -->
  <section class="title-section">
    <h1>DIARY</h1>
    <h2>動画・写メ日記</h2>
    <div class="dot-line"></div>
  </section>

  <!-- キャスト絞り込み -->
  <div class="filter-section">
    <h3>キャストで絞り込み</h3>
    <form method="get" action="/diary" class="filter-form">
      <select name="writer" class="filter-select">
        <option value="">すべてのキャスト</option>
        <?php foreach ($writers as $w): ?>
          <option value="<?= h($w) ?>" <?= $writer === $w ? 'selected' : '' ?>><?= h($w) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="filter-btn">絞り込み</button>
      <a href="/diary" class="filter-btn-clear">クリア</a>
      <?php if (!empty($searchKeyword)): ?>
        <input type="hidden" name="search" value="<?= h($searchKeyword) ?>">
      <?php endif; ?>
    </form>
    <p class="filter-count">
      投稿順: <?= number_format($total) ?>件
      <?php if (!empty($writer)): ?>
        (<?= h($writer) ?>の投稿)
      <?php endif; ?>
    </p>
  </div>

  <!-- キーワード検索 -->
  <div class="filter-section">
    <h3>キーワード検索</h3>
    <form method="get" action="/diary" class="filter-form">
      <input type="text" name="search" placeholder="タイトルや本文から検索..." value="<?= h($searchKeyword) ?>" class="filter-input">
      <select name="sort" class="filter-select" style="min-width: auto;">
        <option value="date" <?= $sort === 'date' ? 'selected' : '' ?>>新着順</option>
        <option value="date_asc" <?= $sort === 'date_asc' ? 'selected' : '' ?>>古い順</option>
      </select>
      <button type="submit" class="filter-btn">検索</button>
      <a href="/diary<?= !empty($writer) ? '?writer=' . urlencode($writer) : '' ?>" class="filter-btn-clear">クリア</a>
      <?php if (!empty($writer)): ?>
        <input type="hidden" name="writer" value="<?= h($writer) ?>">
      <?php endif; ?>
    </form>
    <?php if (!empty($searchKeyword)): ?>
      <p class="filter-count">「<?= h($searchKeyword) ?>」の検索結果</p>
    <?php endif; ?>
  </div>

  <!-- 日記一覧 -->
  <?php if (empty($posts)): ?>
    <div class="diary-empty">該当する投稿がありませんでした。</div>
  <?php else: ?>
    <div class="diary-grid">
      <?php foreach ($posts as $p):
          $isVideo = !empty($p['has_video']);
          // URL正規化（//で始まるスキーマレスURL → https:を付与）
          $fixUrl = function($url) {
              if (!empty($url) && strpos($url, '//') === 0) return 'https:' . $url;
              return $url;
          };
          $displayVideo = $fixUrl($p['video_url'] ?? '');
          $displayPoster = $fixUrl($p['poster_url'] ?? '');
          $displayImg = $fixUrl($p['thumb_url'] ?? '');
          
          // フォールバック: thumb_urlが空の場合、html_bodyから画像/動画を抽出
          if (empty($displayImg) && !empty($p['html_body'])) {
              if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $p['html_body'], $imgMatch)) {
                  $displayImg = $fixUrl($imgMatch[1]);
              }
          }
          if (empty($displayVideo) && $isVideo && !empty($p['html_body'])) {
              if (preg_match('/<video[^>]+src=["\']([^"\']+)["\']/', $p['html_body'], $vidMatch)) {
                  $displayVideo = $fixUrl($vidMatch[1]);
              }
              if (empty($displayPoster) && preg_match('/<video[^>]+poster=["\']([^"\']+)["\']/', $p['html_body'], $posterMatch)) {
                  $displayPoster = $fixUrl($posterMatch[1]);
              }
          }
      ?>
        <div class="diary-card" data-pd="<?= (int)$p['pd_id'] ?>">
          <div class="diary-card-thumb">
            <?php if ($isVideo && !empty($displayVideo)): ?>
              <video src="<?= h($displayVideo) ?>" <?= !empty($displayPoster) ? 'poster="' . h($displayPoster) . '"' : '' ?> autoplay muted loop playsinline preload="auto">
                お使いのブラウザは動画をサポートしていません。
              </video>
              <div class="diary-card-video-badge">🎬 動画</div>
            <?php elseif (!empty($displayImg)): ?>
              <img src="<?= h($displayImg) ?>" alt="<?= h($p['title'] ?: '日記画像') ?>" loading="lazy">
              <?php if ($isVideo): ?>
                <div class="diary-card-video-badge">🎬 動画あり</div>
              <?php endif; ?>
            <?php else: ?>
              <div style="color:#999; text-align:center;">
                <?= $isVideo ? '🎬<br>動画' : '📷<br>サムネイル' ?>
              </div>
            <?php endif; ?>
          </div>
          <div class="diary-card-info">
            <div class="diary-card-title"><?= h(mb_strimwidth($p['title'] ?: '(無題)', 0, 60, '…')) ?></div>
            <div class="diary-card-meta">投稿者: <?= h($p['cast_name'] ?: '不明') ?></div>
            <div class="diary-card-meta">投稿日時: <?= $p['posted_at'] ? date('Y/m/d H:i', strtotime($p['posted_at'])) : '-' ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- ページネーション -->
    <?php if ($lastPage > 1): ?>
      <div class="diary-pagination">
        <?php if ($page > 1):
            $qs = $_GET; $qs['page'] = 1;
        ?>
          <a href="/diary?<?= http_build_query($qs) ?>" class="nav-btn">《</a>
        <?php endif; ?>
        
        <?php
        $startPage = max(1, $page - 2);
        $endPage = min($lastPage, $page + 2);
        for ($i = $startPage; $i <= $endPage; $i++):
            $qs = $_GET; $qs['page'] = $i;
        ?>
          <a href="/diary?<?= http_build_query($qs) ?>" class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
        
        <?php if ($page < $lastPage):
            $qs = $_GET; $qs['page'] = $lastPage;
        ?>
          <a href="/diary?<?= http_build_query($qs) ?>" class="nav-btn">》</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</main>

<!-- モーダル（参考サイト準拠） -->
<div id="diary-modal" style="display:none; position:fixed; inset:0; background-color:rgba(0,0,0,0.5); backdrop-filter:blur(4px); z-index:9999; opacity:0; visibility:hidden; transition:opacity 0.3s ease, visibility 0.3s ease;">
    <div role="dialog" aria-modal="true" style="position:absolute; left:50%; top:50%; transform:translate(-50%,-50%); width:min(640px, 92vw); max-height:80vh; overflow:hidden; background:rgba(255,255,255,0.95); border-radius:10px; box-shadow:0 4px 20px rgba(0,0,0,0.2); display:flex; flex-direction:column;">
        <!-- 固定ヘッダー -->
        <div style="position:sticky; top:0; z-index:10; background:rgba(255,255,255,0.95); backdrop-filter:blur(4px); border-radius:10px 10px 0 0; border-bottom:1px solid #eee;">
            <div style="position:relative; padding:12px 14px;">
                <div id="dm-title" style="font-weight:600; font-size:16px; margin-right:40px; color:var(--color-text);">投稿を読み込み中...</div>
                <button id="dm-close" aria-label="閉じる" style="position:absolute; top:10px; right:10px; width:30px; height:30px; background:rgba(0,0,0,0.1); border:none; border-radius:50%; font-size:20px; line-height:1; cursor:pointer; color:var(--color-text); display:flex; align-items:center; justify-content:center;">×</button>
            </div>
            <div id="dm-meta" style="padding:0 14px 12px 14px; color:var(--color-text); font-size:13px;"></div>
        </div>
        <!-- スクロール可能コンテンツ -->
        <div style="flex:1; overflow-y:auto;">
            <div id="dm-body" style="padding:14px; font-size:15px; line-height:1.8; color:var(--color-text);"></div>
        </div>
    </div>
</div>

<!-- フッターナビゲーション -->
<?php include __DIR__ . '/includes/footer_nav.php'; ?>

<!-- 固定フッター -->
<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
(function() {
    'use strict';
    
    var modal = document.getElementById('diary-modal');
    var dmTitle = document.getElementById('dm-title');
    var dmMeta = document.getElementById('dm-meta');
    var dmBody = document.getElementById('dm-body');
    var dmClose = document.getElementById('dm-close');
    
    if (!modal || !dmTitle || !dmMeta || !dmBody || !dmClose) return;
    
    function openModal() {
        modal.style.display = 'block';
        modal.style.visibility = 'visible';
        modal.style.opacity = '1';
        document.body.style.overflow = 'hidden';
    }
    
    function closeModal() {
        modal.style.opacity = '0';
        modal.style.visibility = 'hidden';
        setTimeout(function() {
            modal.style.display = 'none';
            document.body.style.overflow = '';
            dmTitle.textContent = '';
            dmMeta.textContent = '';
            dmBody.innerHTML = '';
        }, 300);
    }
    
    function escapeRegex(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }
    
    // API経由で投稿データを取得してモーダルに表示
    function loadPost(pd) {
        dmTitle.textContent = '投稿を読み込み中...';
        dmMeta.textContent = '';
        dmBody.innerHTML = '';
        openModal();
        
        fetch('/api/get_diary_post.php?pd=' + encodeURIComponent(pd))
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (!data.success || !data.post) {
                    dmTitle.textContent = '読み込みエラー';
                    dmBody.innerHTML = '<div style="color:#e74c3c;">投稿の読み込みに失敗しました。</div>';
                    return;
                }
                
                var p = data.post;
                
                // タイトル
                dmTitle.textContent = (p.title && p.title.length) ? p.title : '(無題)';
                
                // メタ情報（投稿者名をテーマカラーで太字表示）
                var writerHtml = p.cast_id
                    ? '<a href="/cast/' + p.cast_id + '/" style="color:var(--color-primary); font-weight:bold; text-decoration:none;">' + (p.cast_name || '不明') + '</a>'
                    : '<span style="color:var(--color-primary); font-weight:bold;">' + (p.cast_name || '不明') + '</span>';
                dmMeta.innerHTML = '投稿者：' + writerHtml + '　投稿日時：' + (p.posted_at_formatted || '-');
                
                // メディア表示
                var mediaHtml = '';
                
                if (p.has_video && p.video_url) {
                    var posterAttr = p.poster_url ? 'poster="' + p.poster_url + '"' : '';
                    mediaHtml = '<div style="text-align:center; margin:0 0 20px; display:flex; justify-content:center;">' +
                        '<video class="diary-modal-video" src="' + p.video_url + '" ' + posterAttr + ' autoplay muted loop playsinline controlsList="nodownload" style="max-width:100%; height:auto; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.1); display:block; cursor:pointer;">お使いのブラウザは動画をサポートしていません。</video>' +
                        '</div>';
                } else if (p.thumb_url) {
                    mediaHtml = '<div style="text-align:center; margin:0 0 20px; display:flex; justify-content:center;">' +
                        '<img src="' + p.thumb_url + '" alt="画像" style="max-width:100%; height:auto; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.1); display:block;">' +
                        '</div>';
                }
                
                // 本文クリーンアップ（二重表示防止）
                var bodyContent = '';
                if (p.html_body && p.html_body.length) {
                    bodyContent = p.html_body;
                    
                    // diary_photoframe（サムネイル画像コンテナ）を除去 → メディア部分で既に表示
                    bodyContent = bodyContent.replace(/<div[^>]*class=["\'][^"\']*diary_photoframe[^"\']*["\'][^>]*>[\s\S]*?<\/div>/gi, '');
                    
                    // 動画タグ除去
                    bodyContent = bodyContent.replace(/<video[^>]*>[\s\S]*?<\/video>/gi, '');
                    bodyContent = bodyContent.replace(/<video[^>]*\/>/gi, '');
                    
                    // CityHeaven固有の構造を除去
                    bodyContent = bodyContent.replace(/<div[^>]*class=["\'][^"\']*diary_title[^"\']*["\'][^>]*>[\s\S]*?<\/div>/gi, '');
                    bodyContent = bodyContent.replace(/<div[^>]*class=["\'][^"\']*diary_headding[^"\']*["\'][^>]*>[\s\S]*?<\/div>/gi, '');
                    bodyContent = bodyContent.replace(/<h3[^>]*class=["\'][^"\']*diary_title[^"\']*["\'][^>]*>[\s\S]*?<\/h3>/gi, '');
                    
                    // thumb_urlと同じ画像を除去（二重表示防止）
                    if (p.thumb_url) {
                        // URLのファイル名部分を抽出して一致する<img>を除去
                        var thumbFile = p.thumb_url.split('/').pop().split('?')[0];
                        if (thumbFile) {
                            bodyContent = bodyContent.replace(new RegExp('<img[^>]*' + escapeRegex(thumbFile) + '[^>]*>', 'gi'), '');
                        }
                    }
                    
                    // 余分な空白・改行・空リンクを整理
                    bodyContent = bodyContent.replace(/<a[^>]*>\s*<\/a>/gi, '');
                    bodyContent = bodyContent.replace(/^\s*<br\s*\/?>\s*/gi, '');
                    bodyContent = bodyContent.replace(/\s*<br\s*\/?>\s*$/gi, '');
                    bodyContent = bodyContent.replace(/^\s+|\s+$/g, '');
                    
                    // 中身が実質空かチェック
                    var textOnly = bodyContent.replace(/<[^>]*>/g, '').replace(/\s+/g, '');
                    if (!textOnly) bodyContent = '';
                }
                
                if (!mediaHtml && !bodyContent) {
                    bodyContent = '<div style="text-align:center; padding:40px; color:#999;">表示できる内容がありません</div>';
                }
                
                // メディア + 本文を表示
                dmBody.innerHTML = mediaHtml + bodyContent;
                
                // 本文内の画像にスタイル適用
                dmBody.querySelectorAll('img').forEach(function(img) {
                    img.style.maxWidth = '100%';
                    img.style.height = 'auto';
                    img.style.borderRadius = '8px';
                    img.style.margin = '8px 0';
                    img.style.display = 'block';
                });
                
                // 動画クリックでコントロール表示
                var video = dmBody.querySelector('.diary-modal-video');
                if (video) {
                    video.addEventListener('click', function() {
                        if (!this.hasAttribute('controls')) {
                            this.setAttribute('controls', 'controls');
                        }
                    });
                }
            })
            .catch(function(err) {
                console.error('Diary post load error:', err);
                dmTitle.textContent = '読み込みエラー';
                dmBody.innerHTML = '<div style="color:#e74c3c;">投稿の読み込みに失敗しました。</div>';
            });
    }
    
    // モーダル閉じる
    dmClose.addEventListener('click', closeModal);
    modal.addEventListener('click', function(e) {
        if (e.target === modal) closeModal();
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeModal();
    });
    
    // 日記カードにクリックイベント
    document.querySelectorAll('.diary-card').forEach(function(card) {
        card.addEventListener('click', function() {
            var pd = this.getAttribute('data-pd');
            if (pd) loadPost(pd);
        });
    });
})();
</script>

</body>
</html>
