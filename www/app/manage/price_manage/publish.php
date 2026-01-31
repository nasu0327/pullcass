<?php
/**
 * pullcass - マスター管理画面
 * 料金表管理 - 公開API
 * 編集用テーブル → 公開用テーブルにデータをコピー
 */

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// ログイン認証チェック
requireTenantAdminLogin();

$pdo = getPlatformDb();
if (!$pdo) {
    echo json_encode(['success' => false, 'message' => 'データベースに接続できません']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 公開用テーブルをクリア
    $pdo->exec("DELETE FROM price_texts_published");
    $pdo->exec("DELETE FROM price_banners_published");
    $pdo->exec("DELETE FROM price_rows_published");
    $pdo->exec("DELETE FROM price_tables_published");
    $pdo->exec("DELETE FROM price_contents_published");
    $pdo->exec("DELETE FROM price_sets_published");

    // price_sets をコピー
    $pdo->exec("
        INSERT INTO price_sets_published 
        (id, set_name, set_type, start_datetime, end_datetime, display_order, is_active, created_at, updated_at)
        SELECT id, set_name, set_type, start_datetime, end_datetime, display_order, is_active, created_at, updated_at
        FROM price_sets
    ");

    // price_contents をコピー
    $pdo->exec("
        INSERT INTO price_contents_published 
        (id, set_id, content_type, admin_title, display_order, is_active, created_at, updated_at)
        SELECT id, set_id, content_type, admin_title, display_order, is_active, created_at, updated_at
        FROM price_contents
    ");

    // price_tables をコピー
    $pdo->exec("
        INSERT INTO price_tables_published 
        (id, content_id, column_count, table_name, column1_header, column2_header, note, is_reservation_linked, is_option, created_at, updated_at)
        SELECT id, content_id, column_count, table_name, column1_header, column2_header, note, is_reservation_linked, is_option, created_at, updated_at
        FROM price_tables
    ");

    // price_rows をコピー
    $pdo->exec("
        INSERT INTO price_rows_published 
        (id, table_id, time_label, price_label, display_order, created_at, updated_at)
        SELECT id, table_id, time_label, price_label, display_order, created_at, updated_at
        FROM price_rows
    ");

    // price_banners をコピー
    $pdo->exec("
        INSERT INTO price_banners_published 
        (id, content_id, image_path, link_url, alt_text, created_at, updated_at)
        SELECT id, content_id, image_path, link_url, alt_text, created_at, updated_at
        FROM price_banners
    ");

    // price_texts をコピー
    $pdo->exec("
        INSERT INTO price_texts_published 
        (id, content_id, content, created_at, updated_at)
        SELECT id, content_id, content, created_at, updated_at
        FROM price_texts
    ");

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => '公開しました']);

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('Publish error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'データベースエラーが発生しました: ' . $e->getMessage()]);
}
