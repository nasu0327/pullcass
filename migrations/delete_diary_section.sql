-- ========================================
-- 写メ日記（diary）セクションの削除
-- ========================================
-- 作成日: 2026-01-18
-- 実行方法: phpMyAdminで実行
-- ========================================
-- 
-- このSQLは、豊満倶楽部（houman-club）の
-- 写メ日記（diary）セクションを削除します。
-- ========================================

-- テナントIDを取得
SET @tenant_id = (SELECT id FROM tenants WHERE code = 'houman-club');

-- セクションIDを取得
SET @section_id = (SELECT id FROM top_layout_sections WHERE tenant_id = @tenant_id AND section_key = 'diary' LIMIT 1);

-- セクションが存在するか確認
SELECT @section_id AS section_id;

-- 関連するバナーレコードを削除
DELETE FROM top_layout_banners WHERE section_id = @section_id AND tenant_id = @tenant_id;

-- セクションを削除（3つのテーブルから）
DELETE FROM top_layout_sections WHERE tenant_id = @tenant_id AND section_key = 'diary';
DELETE FROM top_layout_sections_published WHERE tenant_id = @tenant_id AND section_key = 'diary';
DELETE FROM top_layout_sections_saved WHERE tenant_id = @tenant_id AND section_key = 'diary';

-- ========================================
-- 確認用SQL
-- ========================================
-- 以下のSQLで、削除後の状態を確認できます：
--
-- SELECT 
--     section_key,
--     admin_title,
--     is_visible,
--     mobile_visible
-- FROM top_layout_sections
-- WHERE tenant_id = @tenant_id
-- ORDER BY 
--     COALESCE(pc_left_order, 999) ASC,
--     COALESCE(pc_right_order, 999) ASC,
--     mobile_order ASC;
-- ========================================
