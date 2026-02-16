-- ============================================
-- メニュー管理: 内部リンクURLの正規化（/app/front/ プレフィックス除去）
-- ============================================
-- 内部リンクはルート相対パスであるべき（/top, /system, /cast/list 等）
-- /app/front/ で始まるURLは誤りなので、プレフィックスを除去して正規化する
-- 例: /app/front/system → /system, /app/front/cast/list → /cast/list
-- ============================================

UPDATE menu_items
SET url = CONCAT('/', TRIM(LEADING '/' FROM SUBSTRING(url, 12)))
WHERE link_type = 'internal'
  AND url LIKE '/app/front%'
  AND LENGTH(url) >= 10;
