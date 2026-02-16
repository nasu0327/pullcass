-- ============================================
-- メニュー管理: 料金システムのURLを正規化
-- ============================================
-- 内部リンクのURLは /system であるべき（/app/front/system は誤り）
-- 既存データで /app/front/system になっているものを /system に更新
-- ============================================

UPDATE menu_items
SET url = '/system'
WHERE link_type = 'internal'
  AND (url = '/app/front/system' OR url LIKE '%/app/front/system%');
