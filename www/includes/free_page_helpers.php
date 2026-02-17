<?php
/**
 * フリーページ機能用ヘルパー関数
 * マルチテナント対応
 */

// システム予約語（スラッグとして使用禁止）
define('RESERVED_SLUGS', [
    'top',
    'top.php',
    'index.php',
    'admin',
    'app',
    'assets',
    'uploads',
    'includes',
    'cron',
    'img',
    'cast',
    'schedule',
    'system',
    'yoyaku',
    'hotel_list',
    'diary',
    'reviews',
    'free'
]);

/**
 * フリーページの取得（ID指定）
 */
function getFreePage($pdo, $id, $tenantId)
{
    $stmt = $pdo->prepare("
        SELECT * FROM free_pages 
        WHERE id = ? AND tenant_id = ?
    ");
    $stmt->execute([$id, $tenantId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * フリーページの取得（スラッグ指定）
 */
function getFreePageBySlug($pdo, $slug, $tenantId)
{
    $stmt = $pdo->prepare("
        SELECT * FROM free_pages 
        WHERE slug = ? AND tenant_id = ? AND status = 'published'
    ");
    $stmt->execute([$slug, $tenantId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * 全てのフリーページを取得（ステータスフィルター対応）
 */
function getAllFreePages($pdo, $tenantId, $status = 'all', $limit = 20, $offset = 0)
{
    $sql = "SELECT * FROM free_pages WHERE tenant_id = ?";
    $params = [$tenantId];

    if ($status !== 'all') {
        $sql .= " AND status = ?";
        $params[] = $status;
    }

    $sql .= " ORDER BY sort_order ASC, created_at DESC";

    if ($limit > 0) {
        $sql .= " LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * 公開中のフリーページ一覧を取得
 */
function getPublishedFreePages($pdo, $tenantId)
{
    return getAllFreePages($pdo, $tenantId, 'published', 0, 0);
}

/**
 * フリーページの件数を取得
 */
function countFreePages($pdo, $tenantId, $status = 'all')
{
    $sql = "SELECT COUNT(*) FROM free_pages WHERE tenant_id = ?";
    $params = [$tenantId];

    if ($status !== 'all') {
        $sql .= " AND status = ?";
        $params[] = $status;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

/**
 * フリーページを作成
 */
function createFreePage($pdo, $data)
{
    $stmt = $pdo->prepare("
        INSERT INTO free_pages 
        (tenant_id, title, main_title, sub_title, slug, content, excerpt, 
         meta_description, featured_image, status, sort_order, published_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $publishedAt = ($data['status'] === 'published') ? date('Y-m-d H:i:s') : null;

    $stmt->execute([
        $data['tenant_id'],
        $data['title'],
        $data['main_title'] ?? null,
        $data['sub_title'] ?? null,
        $data['slug'],
        $data['content'] ?? null,
        $data['excerpt'] ?? null,
        $data['meta_description'] ?? null,
        $data['featured_image'] ?? null,
        $data['status'] ?? 'draft',
        $data['sort_order'] ?? 0,
        $publishedAt
    ]);

    return $pdo->lastInsertId();
}

/**
 * フリーページを更新
 */
function updateFreePage($pdo, $id, $data, $tenantId)
{
    // 現在のページ情報を取得
    $current = getFreePage($pdo, $id, $tenantId);
    if (!$current) {
        return false;
    }

    // 公開日時の処理
    $publishedAt = $current['published_at'];
    if ($data['status'] === 'published' && !$publishedAt) {
        $publishedAt = date('Y-m-d H:i:s');
    }

    $stmt = $pdo->prepare("
        UPDATE free_pages SET
            title = ?,
            main_title = ?,
            sub_title = ?,
            slug = ?,
            content = ?,
            excerpt = ?,
            meta_description = ?,
            featured_image = ?,
            status = ?,
            published_at = ?
        WHERE id = ? AND tenant_id = ?
    ");

    return $stmt->execute([
        $data['title'],
        $data['main_title'] ?? null,
        $data['sub_title'] ?? null,
        $data['slug'],
        $data['content'] ?? null,
        $data['excerpt'] ?? null,
        $data['meta_description'] ?? null,
        $data['featured_image'] ?? null,
        $data['status'] ?? 'draft',
        $publishedAt,
        $id,
        $tenantId
    ]);
}

/**
 * フリーページを削除
 */
function deleteFreePage($pdo, $id, $tenantId)
{
    $stmt = $pdo->prepare("
        DELETE FROM free_pages 
        WHERE id = ? AND tenant_id = ?
    ");
    return $stmt->execute([$id, $tenantId]);
}

/**
 * フリーページの並び順を一括更新
 */
function updateFreePageOrder($pdo, $orders, $tenantId)
{
    $stmt = $pdo->prepare("
        UPDATE free_pages SET sort_order = ? 
        WHERE id = ? AND tenant_id = ?
    ");

    foreach ($orders as $item) {
        $stmt->execute([
            $item['order'],
            $item['id'],
            $tenantId
        ]);
    }

    return true;
}

/**
 * スラッグが予約語かチェック
 */
function isSlugReserved($slug)
{
    return in_array(strtolower($slug), RESERVED_SLUGS);
}

/**
 * スラッグが利用可能かチェック（重複チェック）
 */
function isSlugAvailable($pdo, $slug, $tenantId, $excludeId = null)
{
    // 予約語チェック
    if (isSlugReserved($slug)) {
        return false;
    }

    // 重複チェック
    $sql = "SELECT COUNT(*) FROM free_pages WHERE slug = ? AND tenant_id = ?";
    $params = [$slug, $tenantId];

    if ($excludeId) {
        $sql .= " AND id != ?";
        $params[] = $excludeId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn() === 0;
}

/**
 * スラッグ形式を検証
 * 英数字、ハイフン、アンダースコアのみ許可
 */
function validateSlug($slug)
{
    if (empty($slug)) {
        return ['valid' => false, 'error' => 'スラッグは必須です'];
    }

    if (strlen($slug) > 100) {
        return ['valid' => false, 'error' => 'スラッグは100文字以内で入力してください'];
    }

    if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $slug)) {
        return ['valid' => false, 'error' => 'スラッグには英数字、ハイフン、アンダースコアのみ使用できます'];
    }

    return ['valid' => true, 'error' => null];
}

/**
 * タイトルからスラッグを生成
 */
function generateSlugFromTitle($title)
{
    // 英数字以外を除去し、スペースをハイフンに変換
    $slug = preg_replace('/[^\w\s-]/u', '', $title);
    $slug = preg_replace('/[\s_-]+/', '-', $slug);
    $slug = preg_replace('/^-+|-+$/', '', $slug);
    $slug = strtolower($slug);

    // 日本語の場合は空になるので、ランダム文字列を生成
    if (empty($slug)) {
        $slug = 'page-' . substr(uniqid(), -6);
    }

    return substr($slug, 0, 100);
}

/**
 * HTMLコンテンツ内のフリーページ用アップロード画像URLを抽出し、該当ファイルを削除する
 * （ページ削除時にTinyMCEで挿入した画像のオーファン化を防ぐ）
 *
 * @param string $content フリーページのHTMLコンテンツ
 * @param string $tenantCode テナントコード
 * @param string $basePath ドキュメントルートの絶対パス（例: $_SERVER['DOCUMENT_ROOT']）
 */
function deleteFreePageContentImages($content, $tenantCode, $basePath)
{
    if (empty($content) || empty($basePath)) {
        return;
    }
    $basePath = rtrim(str_replace('\\', '/', $basePath), '/');
    // img src および href から /uploads/tenants/{tenantCode}/free_page/ のURLを抽出
    $pattern = '#(?:src|href)=["\']([^"\']*?/uploads/tenants/' . preg_quote($tenantCode, '#') . '/free_page/[^"\']+)["\']#i';
    if (!preg_match_all($pattern, $content, $matches)) {
        return;
    }
    foreach (array_unique($matches[1]) as $url) {
        $path = parse_url($url, PHP_URL_PATH);
        if (!$path) {
            continue;
        }
        $path = '/' . ltrim($path, '/');
        // このテナントの free_page 配下のみ削除（安全性のためパスを検証）
        if (strpos($path, '/uploads/tenants/' . $tenantCode . '/free_page/') === false) {
            continue;
        }
        $fullPath = $basePath . $path;
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }
}

/**
 * フリーページ用画像をアップロード
 */
function uploadFreePageImage($file, $tenantCode)
{
    // エラーチェック
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'ファイルのアップロードに失敗しました'];
    }

    // ファイルサイズチェック（5MB以下）
    $maxSize = 5 * 1024 * 1024;
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => 'ファイルサイズが大きすぎます（最大5MB）'];
    }

    // ファイルタイプチェック
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        return ['success' => false, 'error' => '画像ファイルのみアップロード可能です（JPEG、PNG、GIF、WebP）'];
    }

    // 拡張子を取得
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp'
    ];
    $extension = $extensions[$mimeType];

    // アップロードディレクトリ作成
    $uploadDir = __DIR__ . '/../uploads/tenants/' . $tenantCode . '/free_page/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // ファイル名を生成
    $fileName = 'free_page_' . uniqid() . '_' . time() . '.' . $extension;
    $uploadPath = $uploadDir . $fileName;

    // ファイルを移動
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        return ['success' => false, 'error' => 'ファイルの保存に失敗しました'];
    }

    // 成功レスポンス
    $fileUrl = '/uploads/tenants/' . $tenantCode . '/free_page/' . $fileName;
    return ['success' => true, 'url' => $fileUrl];
}
