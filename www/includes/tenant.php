<?php
/**
 * pullcass - テナント（店舗）判別
 */

/**
 * リクエストからテナントを判別
 * 
 * 判別優先順位:
 * 1. サブドメイン（例: houman.pullcass.com → houman）
 * 2. カスタムドメイン（例: club-houman.com）
 * 3. URLパス（例: /houman/ → houman）※開発用
 * 
 * @return array|null テナント情報、見つからない場合はnull
 */
function getTenantFromRequest() {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    
    // 管理画面へのアクセスはテナント判別不要
    if (strpos($uri, '/admin/') === 0 || strpos($uri, '/app/manage/') === 0) {
        return null;
    }
    
    // 1. サブドメインから判別（例: houman.pullcass.com）
    $tenant = getTenantBySubdomain($host);
    if ($tenant) {
        return $tenant;
    }
    
    // 2. カスタムドメインから判別（例: club-houman.com）
    $tenant = getTenantByDomain($host);
    if ($tenant) {
        return $tenant;
    }
    
    // 3. URLパスから判別（開発用: /houman/）
    if (preg_match('/^\/([a-z0-9_-]+)\/?/', $uri, $matches)) {
        $slug = $matches[1];
        // システムパスは除外
        if (!in_array($slug, ['admin', 'app', 'api', 'assets', 'includes'])) {
            $tenant = getTenantBySlug($slug);
            if ($tenant) {
                return $tenant;
            }
        }
    }
    
    return null;
}

/**
 * サブドメインからテナントを取得
 */
function getTenantBySubdomain($host) {
    // 例: houman.pullcass.com → houman
    // localhost:8080 の場合は対象外
    if (preg_match('/^([a-z0-9_-]+)\.pullcass\.(com|local)$/i', $host, $matches)) {
        $slug = strtolower($matches[1]);
        if ($slug !== 'www' && $slug !== 'admin') {
            return getTenantBySlug($slug);
        }
    }
    return null;
}

/**
 * カスタムドメインからテナントを取得
 */
function getTenantByDomain($host) {
    // ポート番号を除去
    $domain = preg_replace('/:\d+$/', '', $host);
    
    try {
        $pdo = getPlatformDb();
        if (!$pdo) return null;
        $stmt = $pdo->prepare("SELECT * FROM tenants WHERE domain = ? AND status = 'active'");
        $stmt->execute([$domain]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * スラッグからテナントを取得
 */
function getTenantBySlug($slug) {
    try {
        $pdo = getPlatformDb();
        if (!$pdo) return null;
        $stmt = $pdo->prepare("SELECT * FROM tenants WHERE slug = ? AND status = 'active'");
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * 現在のテナントを取得（セッションから）
 */
function getCurrentTenant() {
    return $_SESSION['current_tenant'] ?? null;
}

/**
 * 現在のテナントを設定（セッションに保存）
 */
function setCurrentTenant($tenant) {
    $_SESSION['current_tenant'] = $tenant;
}
