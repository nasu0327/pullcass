-- ========================================
-- デフォルトセクションの表示状態を修正
-- ========================================
-- 作成日: 2026-01-18
-- 実行方法: phpMyAdminで実行
-- ========================================
-- 
-- このSQLは、豊満倶楽部（houman-club）のデフォルトセクションを
-- 全て非表示状態に設定します。
--
-- 修正内容:
-- - hero_text（バナー下テキスト）: 非表示
-- - new_cast（新人）: 非表示
-- - today_cast（本日の出勤）: 非表示、pc_left_orderを2に修正
-- - history（閲覧履歴）: 非表示
-- ========================================

-- テナントIDを取得
SET @tenant_id = (SELECT id FROM tenants WHERE code = 'houman-club');

-- テナントが存在するか確認
SELECT @tenant_id AS tenant_id;

-- hero_text（バナー下テキスト）を非表示に
UPDATE top_layout_sections 
SET is_visible = 0, mobile_visible = 0
WHERE tenant_id = @tenant_id AND section_key = 'hero_text';

UPDATE top_layout_sections_published 
SET is_visible = 0, mobile_visible = 0
WHERE tenant_id = @tenant_id AND section_key = 'hero_text';

UPDATE top_layout_sections_saved 
SET is_visible = 0, mobile_visible = 0
WHERE tenant_id = @tenant_id AND section_key = 'hero_text';

-- new_cast（新人）を非表示に
UPDATE top_layout_sections 
SET is_visible = 0, mobile_visible = 0
WHERE tenant_id = @tenant_id AND section_key = 'new_cast';

UPDATE top_layout_sections_published 
SET is_visible = 0, mobile_visible = 0
WHERE tenant_id = @tenant_id AND section_key = 'new_cast';

UPDATE top_layout_sections_saved 
SET is_visible = 0, mobile_visible = 0
WHERE tenant_id = @tenant_id AND section_key = 'new_cast';

-- today_cast（本日の出勤）を非表示に、pc_left_orderを2に修正
UPDATE top_layout_sections 
SET is_visible = 0, mobile_visible = 0, pc_left_order = 2
WHERE tenant_id = @tenant_id AND section_key = 'today_cast';

UPDATE top_layout_sections_published 
SET is_visible = 0, mobile_visible = 0, pc_left_order = 2
WHERE tenant_id = @tenant_id AND section_key = 'today_cast';

UPDATE top_layout_sections_saved 
SET is_visible = 0, mobile_visible = 0, pc_left_order = 2
WHERE tenant_id = @tenant_id AND section_key = 'today_cast';

-- history（閲覧履歴）を非表示に
UPDATE top_layout_sections 
SET is_visible = 0, mobile_visible = 0
WHERE tenant_id = @tenant_id AND section_key = 'history';

UPDATE top_layout_sections_published 
SET is_visible = 0, mobile_visible = 0
WHERE tenant_id = @tenant_id AND section_key = 'history';

UPDATE top_layout_sections_saved 
SET is_visible = 0, mobile_visible = 0
WHERE tenant_id = @tenant_id AND section_key = 'history';

-- ========================================
-- 確認用SQL
-- ========================================
-- 以下のSQLで、修正後の状態を確認できます：
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
