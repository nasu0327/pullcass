-- ========================================
-- ランキング機能用カラム追加
-- ========================================
-- 作成日: 2026-01-24
-- 実行方法: phpMyAdminで実行
-- ========================================

-- =====================================================
-- 1. ランキング設定テーブル（テナント別）
-- =====================================================
CREATE TABLE IF NOT EXISTS tenant_ranking_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL COMMENT 'テナントID',
    ranking_day VARCHAR(100) DEFAULT NULL COMMENT 'ランキング更新日（表示用テキスト）',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_tenant_id (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='テナント別ランキング設定';

-- =====================================================
-- 2. 各キャストデータテーブルにランキングカラム追加
-- =====================================================

-- 駅ちかテーブル
ALTER TABLE tenant_cast_data_ekichika
    ADD COLUMN repeat_ranking INT DEFAULT NULL COMMENT 'リピートランキング順位(1-10)' AFTER checked,
    ADD COLUMN attention_ranking INT DEFAULT NULL COMMENT '注目度ランキング順位(1-10)' AFTER repeat_ranking,
    ADD INDEX idx_repeat_ranking (tenant_id, repeat_ranking),
    ADD INDEX idx_attention_ranking (tenant_id, attention_ranking);

-- ヘブンネットテーブル
ALTER TABLE tenant_cast_data_heaven
    ADD COLUMN repeat_ranking INT DEFAULT NULL COMMENT 'リピートランキング順位(1-10)' AFTER checked,
    ADD COLUMN attention_ranking INT DEFAULT NULL COMMENT '注目度ランキング順位(1-10)' AFTER repeat_ranking,
    ADD INDEX idx_repeat_ranking (tenant_id, repeat_ranking),
    ADD INDEX idx_attention_ranking (tenant_id, attention_ranking);

-- デリヘルタウンテーブル
ALTER TABLE tenant_cast_data_dto
    ADD COLUMN repeat_ranking INT DEFAULT NULL COMMENT 'リピートランキング順位(1-10)' AFTER checked,
    ADD COLUMN attention_ranking INT DEFAULT NULL COMMENT '注目度ランキング順位(1-10)' AFTER repeat_ranking,
    ADD INDEX idx_repeat_ranking (tenant_id, repeat_ranking),
    ADD INDEX idx_attention_ranking (tenant_id, attention_ranking);

-- ========================================
-- 確認用SQL
-- ========================================
-- DESCRIBE tenant_cast_data_ekichika;
-- DESCRIBE tenant_cast_data_heaven;
-- DESCRIBE tenant_cast_data_dto;
-- SELECT * FROM tenant_ranking_config;
