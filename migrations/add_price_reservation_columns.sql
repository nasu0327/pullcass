-- 予約フォームと料金表管理の連動機能 - マイグレーションSQL
-- 実行前にバックアップを取ることを推奨します

-- 編集用テーブルにカラム追加
ALTER TABLE `price_tables` 
ADD COLUMN `is_reservation_linked` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'ネット予約と連動' AFTER `note`,
ADD COLUMN `is_option` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'オプションとして登録' AFTER `is_reservation_linked`;

-- 公開用テーブルにカラム追加
ALTER TABLE `price_tables_published` 
ADD COLUMN `is_reservation_linked` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'ネット予約と連動' AFTER `note`,
ADD COLUMN `is_option` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'オプションとして登録' AFTER `is_reservation_linked`;

-- インデックス追加（パフォーマンス向上のため）
ALTER TABLE `price_tables` ADD INDEX `idx_reservation_linked` (`is_reservation_linked`);
ALTER TABLE `price_tables` ADD INDEX `idx_is_option` (`is_option`);
ALTER TABLE `price_tables_published` ADD INDEX `idx_reservation_linked` (`is_reservation_linked`);
ALTER TABLE `price_tables_published` ADD INDEX `idx_is_option` (`is_option`);
