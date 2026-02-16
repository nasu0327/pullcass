<?php
/**
 * pullcass - キャスト別写メ日記カード一覧API（キャスト詳細ページ用）
 * diary_scrape 機能ONのテナントのみ。tenant_id + cast_id で絞り込み、posted_at DESC で最大20件返す。
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

ob_start();
require_once __DIR__ . '/../../../includes/bootstrap.php';
ob_clean();

try {
    $tenant = getTenantFromRequest();
    if (!$tenant) {
        echo json_encode(['success' => false, 'error' => 'テナント情報が取得できません'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $tenantId = (int) $tenant['id'];

    $castId = filter_input(INPUT_GET, 'cast_id', FILTER_VALIDATE_INT);
    if (!$castId) {
        echo json_encode(['success' => false, 'error' => '無効なキャストIDです'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pdo = getPlatformDb();
    if (!$pdo) {
        echo json_encode(['success' => false, 'error' => 'データベース接続エラー'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 写メ日記機能が有効なテナントのみ返す（他テナントの悪用防止）
    $stmt = $pdo->prepare("SELECT is_enabled FROM tenant_features WHERE tenant_id = ? AND feature_code = 'diary_scrape'");
    $stmt->execute([$tenantId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || (int)$row['is_enabled'] !== 1) {
        echo json_encode(['success' => true, 'posts' => [], 'count' => 0], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT id, pd_id, title, cast_name, posted_at, created_at,
               thumb_url, video_url, poster_url, html_body, has_video
        FROM diary_posts
        WHERE tenant_id = ? AND cast_id = ?
        ORDER BY posted_at DESC, created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$tenantId, $castId]);
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
    error_log('get_cast_diary_cards error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'エラーが発生しました'], JSON_UNESCAPED_UNICODE);
}
