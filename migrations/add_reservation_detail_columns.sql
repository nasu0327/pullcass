-- 予約詳細表示用カラム追加（メール通知と同様の情報を保存）
-- 実行日: 2026-02-XX

ALTER TABLE `tenant_reservations` ADD COLUMN `course_content_id` INT DEFAULT NULL COMMENT 'コース内容ID（price_rows_published.id）' AFTER `course`;
ALTER TABLE `tenant_reservations` ADD COLUMN `event_campaign` VARCHAR(255) DEFAULT NULL COMMENT 'イベント・キャンペーン名' AFTER `message`;
