-- ========================================
-- 既存テナント向け：動画とランキングセクションの追加
-- ========================================
-- 作成日: 2026-01-18
-- 実行方法: phpMyAdminで実行
-- ========================================
-- 
-- このSQLは、既存のテナント（豊満倶楽部）に対して
-- 動画とランキングセクションを非表示状態で追加します。
--
-- 追加するセクション（全て非表示）:
-- - 動画（videos）
-- - リピートランキング（repeat_ranking）
-- - 注目度ランキング（attention_ranking）
-- ========================================

-- テナントIDを取得（例: houman-club）
-- 必要に応じて、テナントコードを変更してください
SET @tenant_id = (SELECT id FROM tenants WHERE code = 'houman-club');

-- テナントが存在するか確認
SELECT @tenant_id AS tenant_id;

-- 動画（videos）
INSERT INTO top_layout_sections 
(tenant_id, section_key, section_type, default_column, admin_title, title_en, title_ja, 
 is_visible, mobile_visible, pc_left_order, pc_right_order, mobile_order, status, config)
VALUES 
(@tenant_id, 'videos', 'content', 'left', '動画一覧', 'VIDEO', '動画', 
 0, 0, 3, NULL, 5, 'draft', '{}')
ON DUPLICATE KEY UPDATE
    admin_title = '動画一覧',
    is_visible = 0,
    mobile_visible = 0;

INSERT INTO top_layout_sections_published 
(tenant_id, section_key, section_type, default_column, admin_title, title_en, title_ja, 
 is_visible, mobile_visible, pc_left_order, pc_right_order, mobile_order, status, config)
VALUES 
(@tenant_id, 'videos', 'content', 'left', '動画一覧', 'VIDEO', '動画', 
 0, 0, 3, NULL, 5, 'published', '{}')
ON DUPLICATE KEY UPDATE
    admin_title = '動画一覧',
    is_visible = 0,
    mobile_visible = 0;

-- リピートランキング（repeat_ranking）
INSERT INTO top_layout_sections 
(tenant_id, section_key, section_type, default_column, admin_title, title_en, title_ja, 
 is_visible, mobile_visible, pc_left_order, pc_right_order, mobile_order, status, config)
VALUES 
(@tenant_id, 'repeat_ranking', 'ranking', 'left', 'リピートランキング', 'RANKING', 'リピートランキング', 
 0, 0, 4, NULL, 6, 'draft', '{}')
ON DUPLICATE KEY UPDATE
    admin_title = 'リピートランキング',
    is_visible = 0,
    mobile_visible = 0;

INSERT INTO top_layout_sections_published 
(tenant_id, section_key, section_type, default_column, admin_title, title_en, title_ja, 
 is_visible, mobile_visible, pc_left_order, pc_right_order, mobile_order, status, config)
VALUES 
(@tenant_id, 'repeat_ranking', 'ranking', 'left', 'リピートランキング', 'RANKING', 'リピートランキング', 
 0, 0, 4, NULL, 6, 'published', '{}')
ON DUPLICATE KEY UPDATE
    admin_title = 'リピートランキング',
    is_visible = 0,
    mobile_visible = 0;

-- 注目度ランキング（attention_ranking）
INSERT INTO top_layout_sections 
(tenant_id, section_key, section_type, default_column, admin_title, title_en, title_ja, 
 is_visible, mobile_visible, pc_left_order, pc_right_order, mobile_order, status, config)
VALUES 
(@tenant_id, 'attention_ranking', 'ranking', 'left', '注目度ランキング', 'RANKING', '注目度ランキング', 
 0, 0, 5, NULL, 7, 'draft', '{}')
ON DUPLICATE KEY UPDATE
    admin_title = '注目度ランキング',
    is_visible = 0,
    mobile_visible = 0;

INSERT INTO top_layout_sections_published 
(tenant_id, section_key, section_type, default_column, admin_title, title_en, title_ja, 
 is_visible, mobile_visible, pc_left_order, pc_right_order, mobile_order, status, config)
VALUES 
(@tenant_id, 'attention_ranking', 'ranking', 'left', '注目度ランキング', 'RANKING', '注目度ランキング', 
 0, 0, 5, NULL, 7, 'published', '{}')
ON DUPLICATE KEY UPDATE
    admin_title = '注目度ランキング',
    is_visible = 0,
    mobile_visible = 0;

-- ========================================
-- 確認用SQL
-- ========================================
-- 以下のSQLで、追加後の状態を確認できます：
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
