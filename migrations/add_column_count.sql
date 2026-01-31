-- 1カラム料金表対応 - マイグレーションSQL
-- 実行前にバックアップを取ることを推奨します

-- 編集用テーブルにカラム数フィールド追加
ALTER TABLE `price_tables` 
ADD COLUMN `column_count` TINYINT(1) NOT NULL DEFAULT 2 COMMENT 'カラム数（1または2）' AFTER `content_id`;

-- 公開用テーブルにカラム数フィールド追加
ALTER TABLE `price_tables_published` 
ADD COLUMN `column_count` TINYINT(1) NOT NULL DEFAULT 2 COMMENT 'カラム数（1または2）' AFTER `content_id`;
