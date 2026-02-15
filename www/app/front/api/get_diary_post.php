<?php
/**
 * 写メ日記 投稿データ取得API
 * 参考: reference/public_html/api/get_diary_post.php
 */
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../../includes/bootstrap.php';

// テナント判定
$tenant = getTenantFromRequest();
if (!$tenant) {
    echo json_encode(['success' => false, 'error' => 'no_tenant']);
    exit;
}

$tenantId = $tenant['id'];

$pd = isset($_GET['pd']) ? (int)$_GET['pd'] : 0;
if ($pd <= 0) {
    echo json_encode(['success' => false, 'error' => 'invalid_pd']);
    exit;
}

try {
    $pdo = getPlatformDb();
    
    $stmt = $pdo->prepare("
        SELECT dp.pd_id, dp.cast_id, dp.cast_name, dp.title, dp.posted_at,
               dp.thumb_url, dp.video_url, dp.poster_url, dp.html_body, dp.has_video
        FROM diary_posts dp
        WHERE dp.tenant_id = ? AND dp.pd_id = ?
        LIMIT 1
    ");
    $stmt->execute([$tenantId, $pd]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$post) {
        echo json_encode(['success' => false, 'error' => 'not_found']);
        exit;
    }
    
    // 日時フォーマット
    $post['posted_at_formatted'] = !empty($post['posted_at'])
        ? date('Y/m/d H:i', strtotime($post['posted_at']))
        : '-';
    
    // URL正規化（//で始まるスキーマレスURL → https:を付与）
    $normalizeUrl = function($url) {
        if (empty($url)) return $url;
        $url = ltrim($url, '@');
        if (strpos($url, '//') === 0) return 'https:' . $url;
        return $url;
    };
    
    $post['thumb_url'] = $normalizeUrl($post['thumb_url']);
    $post['video_url'] = $normalizeUrl($post['video_url']);
    $post['poster_url'] = $normalizeUrl($post['poster_url']);
    
    // フォールバック: thumb_urlが空の場合、html_bodyから画像URLを抽出
    if (empty($post['thumb_url']) && !empty($post['html_body'])) {
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $post['html_body'], $imgMatch)) {
            $post['thumb_url'] = $normalizeUrl($imgMatch[1]);
        }
    }
    // フォールバック: video_urlが空でhas_videoの場合、html_bodyから動画URLを抽出
    if (empty($post['video_url']) && !empty($post['has_video']) && !empty($post['html_body'])) {
        if (preg_match('/<video[^>]+src=["\']([^"\']+)["\']/', $post['html_body'], $vidMatch)) {
            $post['video_url'] = $normalizeUrl($vidMatch[1]);
        }
    }
    
    // html_body内のスキーマレスURLも正規化
    if (!empty($post['html_body'])) {
        $post['html_body'] = preg_replace('/src="\/\//', 'src="https://', $post['html_body']);
        $post['html_body'] = preg_replace("/src='\/\//", "src='https://", $post['html_body']);
    }
    
    echo json_encode(['success' => true, 'post' => $post], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'server_error']);
}
