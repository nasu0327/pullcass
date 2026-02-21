-- ============================================
-- 口コミセクションを右カラム（写メ日記の下）に移動
-- 実行日: 2026-02-21（更新）
-- 対象: 全テナントの reviews セクション
-- 右カラム配置: history(1), diary(2), reviews(3)
-- ============================================

USE pullcass;

-- 編集中テーブル: 左カラム→右カラムに移動
UPDATE top_layout_sections
SET default_column = 'right',
    pc_left_order = NULL,
    pc_right_order = 3
WHERE section_key = 'reviews'
  AND pc_left_order IS NOT NULL;

-- 公開済みテーブル: 左カラム→右カラムに移動
UPDATE top_layout_sections_published
SET default_column = 'right',
    pc_left_order = NULL,
    pc_right_order = 3
WHERE section_key = 'reviews'
  AND pc_left_order IS NOT NULL;

-- 下書きスナップショットテーブルも更新（存在する場合）
UPDATE top_layout_sections_saved
SET default_column = 'right',
    pc_left_order = NULL,
    pc_right_order = 3
WHERE section_key = 'reviews'
  AND pc_left_order IS NOT NULL;

SELECT '口コミセクションを右カラム（写メ日記の下）に移動しました。' AS message;
