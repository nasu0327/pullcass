-- newカラムをTINYINTからVARCHARに変更
-- 実行日: 2026-01-15

-- 駅ちかテーブル
ALTER TABLE tenant_cast_data_ekichika 
MODIFY COLUMN `new` VARCHAR(50) DEFAULT NULL COMMENT '新人フラグ（新人の場合は"新人"）';

-- ヘブンネットテーブル
ALTER TABLE tenant_cast_data_heaven 
MODIFY COLUMN `new` VARCHAR(50) DEFAULT NULL COMMENT '新人フラグ（新人の場合は"新人"）';

-- デリヘルタウンテーブル
ALTER TABLE tenant_cast_data_dto 
MODIFY COLUMN `new` VARCHAR(50) DEFAULT NULL COMMENT '新人フラグ（新人の場合は"新人"）';
