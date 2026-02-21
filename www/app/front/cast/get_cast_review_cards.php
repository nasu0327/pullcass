<?php
/**
 * pullcass - キャスト別口コミカード一覧API（キャスト詳細ページ用）
 * review_scrape 機能ONのテナントのみ。tenant_id + cast_id で絞り込み、review_date DESC で最大20件返す。
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

    $stmt = $pdo->prepare("SELECT is_enabled FROM tenant_features WHERE tenant_id = ? AND feature_code = 'review_scrape'");
    $stmt->execute([$tenantId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || (int)$row['is_enabled'] !== 1) {
        echo json_encode(['success' => true, 'reviews' => [], 'count' => 0], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT id, title, user_name, review_date, rating, cast_name, content, shop_comment
        FROM reviews
        WHERE tenant_id = ? AND cast_id = ?
        ORDER BY review_date DESC, id DESC
        LIMIT 20
    ");
    $stmt->execute([$tenantId, $castId]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    echo json_encode([
        'success' => true,
        'reviews' => $reviews,
        'count' => count($reviews),
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log('get_cast_review_cards error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'エラーが発生しました'], JSON_UNESCAPED_UNICODE);
}
