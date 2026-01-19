-- ========================================
-- トップページレイアウト管理：デフォルト表示設定の更新
-- ========================================
-- 作成日: 2026-01-18
-- 実行方法: phpMyAdminで実行
-- ========================================
-- 
-- このSQLは、houman-clubテナントのトップページレイアウト管理で
-- デフォルトで表示させるセクションを非表示に設定します。
--
-- 対象セクション（全て非表示）:
-- - バナー下テキスト（hero_text）
-- - 左カラム: 新人（new_cast）、本日の出勤（today_cast）
-- - 右カラム: 閲覧履歴（history）
-- - その他のセクションも全て非表示
-- ========================================

-- テナントIDを取得
SET @tenant_id = (SELECT id FROM tenants WHERE code = 'houman-club');

-- 全てのセクションを非表示に設定
-- top_layout_sections（編集中）
UPDATE top_layout_sections 
SET 
    is_visible = 0,
    mobile_visible = 0
WHERE tenant_id = @tenant_id;

-- top_layout_sections_published（公開済み）
UPDATE top_layout_sections_published 
SET 
    is_visible = 0,
    mobile_visible = 0
WHERE tenant_id = @tenant_id;

-- top_layout_sections_saved（下書き保存）
UPDATE top_layout_sections_saved 
SET 
    is_visible = 0,
    mobile_visible = 0
WHERE tenant_id = @tenant_id;

-- ========================================
-- 確認用SQL
-- ========================================
-- 以下のSQLで、更新後の状態を確認できます：
--
-- SELECT 
--     section_key,
--     admin_title,
--     is_visible,
--     mobile_visible,
--     pc_left_order,
--     pc_right_order
-- FROM top_layout_sections
-- WHERE tenant_id = @tenant_id
-- ORDER BY 
--     COALESCE(pc_left_order, 999) ASC,
--     COALESCE(pc_right_order, 999) ASC,
--     mobile_order ASC;
-- ========================================
