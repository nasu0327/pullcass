-- ========================================
-- 統合キャストテーブルのリファクタリング
-- ========================================
-- 作成日: 2026-01-24
-- 目的: リファレンスサイトと同様に統合キャストテーブルを使用する
-- 実行方法: phpMyAdminで実行
-- ========================================

-- =====================================================
-- 1. 統合キャストテーブルの作成
--    ※ 既存の tenant_casts テーブルを再作成
-- =====================================================

-- 既存テーブルを削除（データがある場合は注意）
DROP TABLE IF EXISTS tenant_cast_view_history;
DROP TABLE IF EXISTS tenant_casts;

-- 統合キャストテーブル（リファレンスの cast_data と同等）
CREATE TABLE tenant_casts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL COMMENT 'テナントID',
    name VARCHAR(100) NOT NULL COMMENT 'キャスト名',
    name_romaji VARCHAR(100) COMMENT 'ローマ字名',
    sort_order INT DEFAULT 0 COMMENT '並び順',
    cup VARCHAR(10) COMMENT 'カップ',
    age INT COMMENT '年齢',
    height INT COMMENT '身長(cm)',
    size VARCHAR(50) COMMENT 'サイズ(B/W/H)',
    pr_title VARCHAR(255) COMMENT 'PRタイトル',
    pr_text TEXT COMMENT 'PRテキスト',
    `new` TINYINT(1) DEFAULT 0 COMMENT '新人フラグ',
    today VARCHAR(50) COMMENT '本日出勤時間',
    `now` VARCHAR(50) COMMENT '今すぐ案内状態',
    closed TINYINT(1) DEFAULT 0 COMMENT '受付終了',
    img1 VARCHAR(500) COMMENT '画像URL1',
    img2 VARCHAR(500) COMMENT '画像URL2',
    img3 VARCHAR(500) COMMENT '画像URL3',
    img4 VARCHAR(500) COMMENT '画像URL4',
    img5 VARCHAR(500) COMMENT '画像URL5',
    day1 VARCHAR(50) COMMENT '1日目出勤',
    time1 VARCHAR(50) COMMENT '1日目時間',
    day2 VARCHAR(50) COMMENT '2日目出勤',
    time2 VARCHAR(50) COMMENT '2日目時間',
    day3 VARCHAR(50) COMMENT '3日目出勤',
    time3 VARCHAR(50) COMMENT '3日目時間',
    day4 VARCHAR(50) COMMENT '4日目出勤',
    time4 VARCHAR(50) COMMENT '4日目時間',
    day5 VARCHAR(50) COMMENT '5日目出勤',
    time5 VARCHAR(50) COMMENT '5日目時間',
    day6 VARCHAR(50) COMMENT '6日目出勤',
    time6 VARCHAR(50) COMMENT '6日目時間',
    day7 VARCHAR(50) COMMENT '7日目出勤',
    time7 VARCHAR(50) COMMENT '7日目時間',
    checked TINYINT(1) DEFAULT 1 COMMENT '表示フラグ（1=表示, 0=非表示）',
    missing_count INT DEFAULT 0 COMMENT 'スクレイピング欠損カウント',
    repeat_ranking INT DEFAULT NULL COMMENT 'リピートランキング順位(1-10)',
    attention_ranking INT DEFAULT NULL COMMENT '注目度ランキング順位(1-10)',
    source_url VARCHAR(500) COMMENT '元ページURL',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_tenant_name (tenant_id, name),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_tenant_checked (tenant_id, checked),
    INDEX idx_tenant_sort (tenant_id, sort_order),
    INDEX idx_repeat_ranking (tenant_id, repeat_ranking),
    INDEX idx_attention_ranking (tenant_id, attention_ranking)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='統合キャストデータ（メインテーブル）';

-- =====================================================
-- 2. 各ソーステーブルにtime列を追加（未追加の場合）
-- =====================================================

-- 駅ちかテーブルにtime列を追加
ALTER TABLE tenant_cast_data_ekichika
    ADD COLUMN IF NOT EXISTS time1 VARCHAR(50) COMMENT '1日目時間' AFTER day1,
    ADD COLUMN IF NOT EXISTS time2 VARCHAR(50) COMMENT '2日目時間' AFTER day2,
    ADD COLUMN IF NOT EXISTS time3 VARCHAR(50) COMMENT '3日目時間' AFTER day3,
    ADD COLUMN IF NOT EXISTS time4 VARCHAR(50) COMMENT '4日目時間' AFTER day4,
    ADD COLUMN IF NOT EXISTS time5 VARCHAR(50) COMMENT '5日目時間' AFTER day5,
    ADD COLUMN IF NOT EXISTS time6 VARCHAR(50) COMMENT '6日目時間' AFTER day6,
    ADD COLUMN IF NOT EXISTS time7 VARCHAR(50) COMMENT '7日目時間' AFTER day7,
    ADD COLUMN IF NOT EXISTS missing_count INT DEFAULT 0 COMMENT 'スクレイピング欠損カウント' AFTER checked;

-- ヘブンネットテーブルにtime列を追加
ALTER TABLE tenant_cast_data_heaven
    ADD COLUMN IF NOT EXISTS time1 VARCHAR(50) COMMENT '1日目時間' AFTER day1,
    ADD COLUMN IF NOT EXISTS time2 VARCHAR(50) COMMENT '2日目時間' AFTER day2,
    ADD COLUMN IF NOT EXISTS time3 VARCHAR(50) COMMENT '3日目時間' AFTER day3,
    ADD COLUMN IF NOT EXISTS time4 VARCHAR(50) COMMENT '4日目時間' AFTER day4,
    ADD COLUMN IF NOT EXISTS time5 VARCHAR(50) COMMENT '5日目時間' AFTER day5,
    ADD COLUMN IF NOT EXISTS time6 VARCHAR(50) COMMENT '6日目時間' AFTER day6,
    ADD COLUMN IF NOT EXISTS time7 VARCHAR(50) COMMENT '7日目時間' AFTER day7,
    ADD COLUMN IF NOT EXISTS missing_count INT DEFAULT 0 COMMENT 'スクレイピング欠損カウント' AFTER checked;

-- デリヘルタウンテーブルにtime列を追加
ALTER TABLE tenant_cast_data_dto
    ADD COLUMN IF NOT EXISTS time1 VARCHAR(50) COMMENT '1日目時間' AFTER day1,
    ADD COLUMN IF NOT EXISTS time2 VARCHAR(50) COMMENT '2日目時間' AFTER day2,
    ADD COLUMN IF NOT EXISTS time3 VARCHAR(50) COMMENT '3日目時間' AFTER day3,
    ADD COLUMN IF NOT EXISTS time4 VARCHAR(50) COMMENT '4日目時間' AFTER day4,
    ADD COLUMN IF NOT EXISTS time5 VARCHAR(50) COMMENT '5日目時間' AFTER day5,
    ADD COLUMN IF NOT EXISTS time6 VARCHAR(50) COMMENT '6日目時間' AFTER day6,
    ADD COLUMN IF NOT EXISTS time7 VARCHAR(50) COMMENT '7日目時間' AFTER day7,
    ADD COLUMN IF NOT EXISTS missing_count INT DEFAULT 0 COMMENT 'スクレイピング欠損カウント' AFTER checked;

-- =====================================================
-- 3. ランキング設定テーブル（更新日保存用）
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
-- 4. 閲覧履歴テーブルの再作成
-- =====================================================
CREATE TABLE IF NOT EXISTS tenant_cast_view_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL COMMENT 'テナントID',
    cast_id INT NOT NULL COMMENT 'キャストID',
    session_id VARCHAR(100) COMMENT 'セッションID',
    member_id INT COMMENT '会員ID',
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (cast_id) REFERENCES tenant_casts(id) ON DELETE CASCADE,
    INDEX idx_tenant_session (tenant_id, session_id),
    INDEX idx_tenant_member (tenant_id, member_id),
    INDEX idx_viewed_at (viewed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='キャスト閲覧履歴';

-- ========================================
-- 確認用SQL
-- ========================================
-- DESCRIBE tenant_casts;
-- SELECT COUNT(*) FROM tenant_casts;
