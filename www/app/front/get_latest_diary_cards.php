<?php
/**
 * トップページ用・最新写メ日記カード一覧API（全キャスト）
 * diary_scrape ON のテナントのみ。tenant_id で絞り、posted_at DESC で最大20件。
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

// 既存出力を捨ててJSONのみ返す
if (ob_get_level()) {
    ob_end_clean();
}
ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

require_once __DIR__ . '/../../includes/bootstrap.php';
ob_clean();

try {
    $tenant = getTenantFromRequest();
    if (!$tenant) {
        echo json_encode(['success' => false, 'error' => 'テナント情報が取得できません'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $tenantId = (int) $tenant['id'];

    $pdo = getPlatformDb();
    if (!$pdo) {
        echo json_encode(['success' => false, 'error' => 'データベース接続エラー'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare("SELECT is_enabled FROM tenant_features WHERE tenant_id = ? AND feature_code = 'diary_scrape'");
    $stmt->execute([$tenantId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || (int)$row['is_enabled'] !== 1) {
        echo json_encode(['success' => true, 'posts' => [], 'count' => 0], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 表示キャスト（checked=1）に限定して最新20件
    $stmt = $pdo->prepare("
        SELECT dp.id, dp.pd_id, dp.title, dp.cast_name, dp.posted_at, dp.created_at,
               dp.thumb_url, dp.video_url, dp.poster_url, dp.html_body, dp.has_video
        FROM diary_posts dp
        INNER JOIN tenant_casts tc ON dp.tenant_id = tc.tenant_id AND dp.cast_id = tc.id AND tc.checked = 1
        WHERE dp.tenant_id = ?
        ORDER BY dp.posted_at DESC, dp.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$tenantId]);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $normalizeUrl = function ($url) {
        if (empty($url)) return $url;
        $url = ltrim($url, '@');
        if (strpos($url, '//') === 0) return 'https:' . $url;
        return $url;
    };

    foreach ($posts as &$p) {
        $p['thumb_url'] = $normalizeUrl($p['thumb_url'] ?? '');
        $p['video_url'] = $normalizeUrl($p['video_url'] ?? '');
        $p['poster_url'] = $normalizeUrl($p['poster_url'] ?? '');
        $p['has_video'] = (int)($p['has_video'] ?? 0);
    }
    unset($p);

    echo json_encode([
        'success' => true,
        'posts' => $posts,
        'count' => count($posts),
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('get_latest_diary_cards error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'エラーが発生しました'], JSON_UNESCAPED_UNICODE);
}
