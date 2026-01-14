-- 店舗タイトルと紹介文カラムを追加
-- 実行日: 2026-01-14

ALTER TABLE tenants 
ADD COLUMN title TEXT DEFAULT NULL COMMENT '店舗タイトル（インデックスページ表示用、改行可）' AFTER name,
ADD COLUMN description TEXT DEFAULT NULL COMMENT '店舗紹介文（インデックスページ表示用、改行可）' AFTER title;

-- 確認用
-- SELECT id, name, title, description FROM tenants;
