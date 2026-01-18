-- ========================================
-- 既存テナント向け：トップページレイアウト管理の初期データ作成
-- ========================================
-- 作成日: 2026-01-18
-- 実行方法: phpMyAdminで実行
-- ========================================
-- 
-- このSQLは、既存のテナントに対してトップページレイアウト管理の
-- デフォルトセクションを作成します。
--
-- 対象セクション（全て非表示）:
-- - バナー下テキスト（hero_text）
-- - 左カラム: 新人（new_cast）、本日の出勤（today_cast）
-- - 右カラム: 閲覧履歴（history）
-- ========================================

-- テナントIDを取得（例: houman-club）
-- 必要に応じて、テナントコードを変更してください
SET @tenant_id = (SELECT id FROM tenants WHERE code = 'houman-club');

-- テナントが存在するか確認
SELECT @tenant_id AS tenant_id;

-- 既存のセクションを削除（オプション：既存データを保持したい場合はコメントアウト）
-- DELETE FROM top_layout_sections WHERE tenant_id = @tenant_id;
-- DELETE FROM top_layout_sections_published WHERE tenant_id = @tenant_id;
-- DELETE FROM top_layout_sections_saved WHERE tenant_id = @tenant_id;

-- バナー下テキスト（hero_text）
INSERT INTO top_layout_sections 
(tenant_id, section_key, section_type, default_column, admin_title, title_en, title_ja, 
 is_visible, mobile_visible, pc_left_order, pc_right_order, mobile_order, status, config)
VALUES 
(@tenant_id, 'hero_text', 'hero_text', NULL, 'トップバナー下テキスト', '', '', 
 0, 0, NULL, NULL, 1, 'draft', '{"h1_title": "", "intro_text": ""}')
ON DUPLICATE KEY UPDATE
    admin_title = 'トップバナー下テキスト',
    is_visible = 0,
    mobile_visible = 0;

INSERT INTO top_layout_sections_published 
(tenant_id, section_key, section_type, default_column, admin_title, title_en, title_ja, 
 is_visible, mobile_visible, pc_left_order, pc_right_order, mobile_order, status, config)
VALUES 
(@tenant_id, 'hero_text', 'hero_text', NULL, 'トップバナー下テキスト', '', '', 
 0, 0, NULL, NULL, 1, 'published', '{"h1_title": "", "intro_text": ""}')
ON DUPLICATE KEY UPDATE
    admin_title = 'トップバナー下テキスト',
    is_visible = 0,
    mobile_visible = 0;

-- 左カラム: 新人（new_cast）
INSERT INTO top_layout_sections 
(tenant_id, section_key, section_type, default_column, admin_title, title_en, title_ja, 
 is_visible, mobile_visible, pc_left_order, pc_right_order, mobile_order, status, config)
VALUES 
(@tenant_id, 'new_cast', 'cast_list', 'left', '新人キャスト', 'NEW CAST', '新人', 
 0, 0, 1, NULL, 2, 'draft', '{}')
ON DUPLICATE KEY UPDATE
    admin_title = '新人キャスト',
    is_visible = 0,
    mobile_visible = 0;

INSERT INTO top_layout_sections_published 
(tenant_id, section_key, section_type, default_column, admin_title, title_en, title_ja, 
 is_visible, mobile_visible, pc_left_order, pc_right_order, mobile_order, status, config)
VALUES 
(@tenant_id, 'new_cast', 'cast_list', 'left', '新人キャスト', 'NEW CAST', '新人', 
 0, 0, 1, NULL, 2, 'published', '{}')
ON DUPLICATE KEY UPDATE
    admin_title = '新人キャスト',
    is_visible = 0,
    mobile_visible = 0;

-- 左カラム: 本日の出勤（today_cast）
INSERT INTO top_layout_sections 
(tenant_id, section_key, section_type, default_column, admin_title, title_en, title_ja, 
 is_visible, mobile_visible, pc_left_order, pc_right_order, mobile_order, status, config)
VALUES 
(@tenant_id, 'today_cast', 'cast_list', 'left', '本日の出勤キャスト', 'TODAY', '本日の出勤', 
 0, 0, 2, NULL, 3, 'draft', '{}')
ON DUPLICATE KEY UPDATE
    admin_title = '本日の出勤キャスト',
    is_visible = 0,
    mobile_visible = 0;

INSERT INTO top_layout_sections_published 
(tenant_id, section_key, section_type, default_column, admin_title, title_en, title_ja, 
 is_visible, mobile_visible, pc_left_order, pc_right_order, mobile_order, status, config)
VALUES 
(@tenant_id, 'today_cast', 'cast_list', 'left', '本日の出勤キャスト', 'TODAY', '本日の出勤', 
 0, 0, 2, NULL, 3, 'published', '{}')
ON DUPLICATE KEY UPDATE
    admin_title = '本日の出勤キャスト',
    is_visible = 0,
    mobile_visible = 0;

-- 右カラム: 閲覧履歴（history）
INSERT INTO top_layout_sections 
(tenant_id, section_key, section_type, default_column, admin_title, title_en, title_ja, 
 is_visible, mobile_visible, pc_left_order, pc_right_order, mobile_order, status, config)
VALUES 
(@tenant_id, 'history', 'content', 'right', '閲覧履歴', 'HISTORY', '閲覧履歴', 
 0, 0, NULL, 1, 4, 'draft', '{}')
ON DUPLICATE KEY UPDATE
    admin_title = '閲覧履歴',
    is_visible = 0,
    mobile_visible = 0;

INSERT INTO top_layout_sections_published 
(tenant_id, section_key, section_type, default_column, admin_title, title_en, title_ja, 
 is_visible, mobile_visible, pc_left_order, pc_right_order, mobile_order, status, config)
VALUES 
(@tenant_id, 'history', 'content', 'right', '閲覧履歴', 'HISTORY', '閲覧履歴', 
 0, 0, NULL, 1, 4, 'published', '{}')
ON DUPLICATE KEY UPDATE
    admin_title = '閲覧履歴',
    is_visible = 0,
    mobile_visible = 0;

-- ========================================
-- 確認用SQL
-- ========================================
-- 以下のSQLで、作成されたセクションを確認できます：
--
-- SELECT 
--     section_key,
--     admin_title,
--     is_visible,
--     mobile_visible,
--     pc_left_order,
--     pc_right_order,
--     mobile_order
-- FROM top_layout_sections
-- WHERE tenant_id = @tenant_id
-- ORDER BY 
--     COALESCE(pc_left_order, 999) ASC,
--     COALESCE(pc_right_order, 999) ASC,
--     mobile_order ASC;
-- ========================================
