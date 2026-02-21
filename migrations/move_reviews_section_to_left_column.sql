-- ============================================
-- 口コミセクションを右カラムから左カラムに移動
-- 実行日: 2026-02-21
-- 対象: 全テナントの reviews セクション
-- ============================================

USE pullcass;

-- 編集中テーブル: 右カラム→左カラムに移動
UPDATE top_layout_sections
SET default_column = 'left',
    pc_left_order = 6,
    pc_right_order = NULL
WHERE section_key = 'reviews'
  AND pc_right_order IS NOT NULL;

-- 公開済みテーブル: 右カラム→左カラムに移動
UPDATE top_layout_sections_published
SET default_column = 'left',
    pc_left_order = 6,
    pc_right_order = NULL
WHERE section_key = 'reviews'
  AND pc_right_order IS NOT NULL;

-- 下書きスナップショットテーブルも更新（存在する場合）
UPDATE top_layout_sections_saved
SET default_column = 'left',
    pc_left_order = 6,
    pc_right_order = NULL
WHERE section_key = 'reviews'
  AND pc_right_order IS NOT NULL;

SELECT '口コミセクションを左カラムに移動しました。' AS message;
