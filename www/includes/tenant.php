<?php
/**
 * pullcass - テナント（店舗）判別
 */

/**
 * リクエストからテナントを判別
 * 
 * 判別優先順位:
 * 1. サブドメイン（例: houman-jyukujyo.pullcass.com → houman-jyukujyo）
 * 2. カスタムドメイン（例: club-houman.com）
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
    
    // 1. サブドメインから判別（例: houman-jyukujyo.pullcass.com）
    $tenant = getTenantBySubdomain($host);
    if ($tenant) {
        return $tenant;
    }
    
    // 2. カスタムドメインから判別（例: club-houman.com）
    $tenant = getTenantByDomain($host);
    if ($tenant) {
        return $tenant;
    }
    
    return null;
}

/**
 * サブドメインからテナントを取得
 */
function getTenantBySubdomain($host) {
    // 例: houman-jyukujyo.pullcass.com → houman-jyukujyo
    if (preg_match('/^([a-z0-9_-]+)\.pullcass\.com$/i', $host, $matches)) {
        $code = strtolower($matches[1]);
        if ($code !== 'www' && $code !== 'admin') {
            return getTenantByCode($code);
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
    
    // pullcass.comは除外
    if ($domain === 'pullcass.com' || $domain === 'www.pullcass.com') {
        return null;
    }
    
    try {
        $pdo = getPlatformDb();
        if (!$pdo) return null;
        $stmt = $pdo->prepare("SELECT * FROM tenants WHERE domain = ? AND is_active = 1");
        $stmt->execute([$domain]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * コードからテナントを取得
 */
function getTenantByCode($code) {
    try {
        $pdo = getPlatformDb();
        if (!$pdo) return null;
        $stmt = $pdo->prepare("SELECT * FROM tenants WHERE code = ? AND is_active = 1");
        $stmt->execute([$code]);
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

/**
 * プラットフォームドメインかどうか判定
 */
function isPlatformDomain() {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    return ($host === 'pullcass.com' || $host === 'www.pullcass.com' || $host === 'localhost');
}
