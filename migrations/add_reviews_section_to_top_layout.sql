-- ============================================
-- 既存テナントに「口コミ」セクションを追加
-- トップページ編集でカードが表示されない場合に実行
-- ============================================
-- 実行前: top_layout_sections に既に何かしらあるテナントのうち、
--         section_key = 'reviews' が無いテナントにのみ 1 行追加します。
-- ============================================

USE pullcass;

-- 編集中テーブルに reviews セクションを追加
INSERT INTO top_layout_sections
  (tenant_id, section_key, section_type, default_column, admin_title, title_en, title_ja,
   is_visible, mobile_visible, pc_left_order, pc_right_order, mobile_order, status, config)
SELECT t.id, 'reviews', 'content', 'right', '口コミ', 'REVIEW', '口コミ',
   0, 0, NULL, 3, 6, 'draft', '{}'
FROM tenants t
WHERE EXISTS (SELECT 1 FROM top_layout_sections s WHERE s.tenant_id = t.id)
  AND NOT EXISTS (SELECT 1 FROM top_layout_sections s2 WHERE s2.tenant_id = t.id AND s2.section_key = 'reviews');

-- 公開済みテーブルにも同じ内容で追加
INSERT INTO top_layout_sections_published
  (tenant_id, section_key, section_type, default_column, admin_title, title_en, title_ja,
   is_visible, mobile_visible, pc_left_order, pc_right_order, mobile_order, status, config)
SELECT t.id, 'reviews', 'content', 'right', '口コミ', 'REVIEW', '口コミ',
   0, 0, NULL, 3, 6, 'published', '{}'
FROM tenants t
WHERE EXISTS (SELECT 1 FROM top_layout_sections_published s WHERE s.tenant_id = t.id)
  AND NOT EXISTS (SELECT 1 FROM top_layout_sections_published s2 WHERE s2.tenant_id = t.id AND s2.section_key = 'reviews');

-- 確認（追加された行数は環境により 0 またはテナント数）
SELECT '口コミセクションの追加が完了しました。' AS message;
