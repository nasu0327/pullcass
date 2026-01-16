-- missing_count カラムを追加（3回連続取得失敗で非表示にするため）

-- 駅ちか
ALTER TABLE tenant_cast_data_ekichika 
ADD COLUMN IF NOT EXISTS missing_count INT DEFAULT 0 COMMENT '取得失敗回数（3回で非表示）';

-- ヘブンネット
ALTER TABLE tenant_cast_data_heaven 
ADD COLUMN IF NOT EXISTS missing_count INT DEFAULT 0 COMMENT '取得失敗回数（3回で非表示）';

-- デリヘルタウン
ALTER TABLE tenant_cast_data_dto 
ADD COLUMN IF NOT EXISTS missing_count INT DEFAULT 0 COMMENT '取得失敗回数（3回で非表示）';
