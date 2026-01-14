-- 営業時間カラムを追加
-- 実行日: 2026-01-14

ALTER TABLE tenants 
ADD COLUMN business_hours VARCHAR(100) DEFAULT NULL COMMENT '営業時間（例：10:00〜LAST）' AFTER phone,
ADD COLUMN business_hours_note TEXT DEFAULT NULL COMMENT '営業時間下テキスト（例：電話予約受付中！）' AFTER business_hours;

-- 確認用
-- SELECT id, name, business_hours, business_hours_note FROM tenants;
