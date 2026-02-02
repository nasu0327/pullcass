-- 予約機能に24時間営業オプションを追加
ALTER TABLE tenant_reservation_settings 
ADD COLUMN is_24hours TINYINT(1) DEFAULT 0 AFTER is_enabled;
