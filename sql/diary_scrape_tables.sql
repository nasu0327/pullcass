-- ============================================
-- 写メ日記スクレイピング機能 - テーブル作成SQL
-- 作成日: 2026-02-14
-- ============================================

-- ============================================
-- 1. プラットフォームDB用テーブル
-- ============================================

USE pullcass_platform;

-- --------------------------------------------
-- 写メ日記スクレイピング設定テーブル
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS diary_scrape_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL COMMENT 'テナントID',
    
    -- CityHeavenログイン情報
    cityheaven_login_id VARCHAR(255) NOT NULL COMMENT 'ログインID（メールアドレス）',
    cityheaven_password VARCHAR(500) NOT NULL COMMENT 'パスワード（暗号化）',
    
    -- スクレイピング対象URL
    shop_url VARCHAR(500) NOT NULL COMMENT '店舗URL（例: /fukuoka/A4001/A400101/houmantengoku/）',
    
    -- 実行設定
    is_enabled TINYINT(1) DEFAULT 0 COMMENT '自動取得ON/OFF',
    scrape_interval INT DEFAULT 10 COMMENT '取得間隔（分）',
    request_delay DECIMAL(3,1) DEFAULT 0.5 COMMENT 'リクエスト間隔（秒）',
    max_pages INT DEFAULT 50 COMMENT '最大ページ数',
    timeout INT DEFAULT 30 COMMENT 'タイムアウト（秒）',
    
    -- Cookie管理
    cookie_data TEXT COMMENT 'Cookie情報（JSON）',
    cookie_updated_at DATETIME COMMENT 'Cookie更新日時',
    
    -- 実行状態
    last_executed_at DATETIME COMMENT '最終実行日時',
    last_execution_status ENUM('success', 'error', 'running') COMMENT '最終実行状態',
    last_error_message TEXT COMMENT '最終エラーメッセージ',
    
    -- 統計情報
    total_posts_scraped INT DEFAULT 0 COMMENT '累計取得投稿数',
    last_posts_count INT DEFAULT 0 COMMENT '最終取得投稿数',
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_tenant (tenant_id),
    INDEX idx_enabled (is_enabled),
    INDEX idx_last_executed (last_executed_at),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='写メ日記スクレイピング設定';

-- --------------------------------------------
-- スクレイピング実行ログテーブル
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS diary_scrape_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL COMMENT 'テナントID',
    
    -- 実行情報
    execution_type ENUM('manual', 'cron') NOT NULL COMMENT '実行タイプ',
    started_at DATETIME NOT NULL COMMENT '開始日時',
    finished_at DATETIME COMMENT '終了日時',
    execution_time DECIMAL(10,2) COMMENT '実行時間（秒）',
    
    -- 実行結果
    status ENUM('success', 'error', 'timeout', 'running') NOT NULL COMMENT '実行結果',
    pages_processed INT DEFAULT 0 COMMENT '処理ページ数',
    posts_found INT DEFAULT 0 COMMENT '検出投稿数',
    posts_saved INT DEFAULT 0 COMMENT '保存投稿数',
    posts_skipped INT DEFAULT 0 COMMENT 'スキップ投稿数',
    errors_count INT DEFAULT 0 COMMENT 'エラー数',
    
    -- エラー情報
    error_message TEXT COMMENT 'エラーメッセージ',
    
    -- メタ情報
    memory_usage DECIMAL(10,2) COMMENT 'メモリ使用量（MB）',
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_started_at (started_at),
    INDEX idx_status (status),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='スクレイピング実行ログ';

-- ============================================
-- 2. テナントDB用テーブル（各テナントDBで実行）
-- ============================================

-- 注意: 以下のSQLは各テナントのデータベースで実行してください
-- 例: USE tenant_houman; 等

-- --------------------------------------------
-- 写メ日記投稿データテーブル
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS diary_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pd_id BIGINT NOT NULL COMMENT 'CityHeavenの投稿ID',
    cast_id INT COMMENT 'キャストID（cast_dataテーブル）',
    
    -- 投稿情報
    title VARCHAR(500) COMMENT 'タイトル',
    writer_name VARCHAR(100) NOT NULL COMMENT '投稿者名',
    posted_at DATETIME NOT NULL COMMENT '投稿日時',
    
    -- メディア情報
    thumb_url VARCHAR(500) COMMENT 'サムネイル画像URL',
    video_url VARCHAR(500) COMMENT '動画URL',
    poster_url VARCHAR(500) COMMENT '動画ポスター画像URL',
    has_video TINYINT(1) DEFAULT 0 COMMENT '動画有無',
    
    -- 本文
    html_body TEXT COMMENT '本文HTML',
    content_hash VARCHAR(64) COMMENT '本文ハッシュ値（重複チェック用）',
    
    -- メタ情報
    detail_url VARCHAR(500) COMMENT '詳細ページURL',
    is_my_girl_limited TINYINT(1) DEFAULT 0 COMMENT 'マイガール限定投稿',
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_pd_id (pd_id),
    INDEX idx_cast_id (cast_id),
    INDEX idx_posted_at (posted_at),
    INDEX idx_writer_name (writer_name),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (cast_id) REFERENCES cast_data(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='写メ日記投稿データ';

-- ============================================
-- 3. 機能フラグ追加（tenant_features）
-- ============================================

USE pullcass_platform;

-- diary_scrape機能を追加
INSERT INTO tenant_features (tenant_id, feature_code, is_enabled, created_at)
SELECT id, 'diary_scrape', 0, NOW()
FROM tenants
WHERE NOT EXISTS (
    SELECT 1 FROM tenant_features 
    WHERE tenant_id = tenants.id 
    AND feature_code = 'diary_scrape'
);

-- ============================================
-- 完了メッセージ
-- ============================================
SELECT '写メ日記スクレイピング機能のテーブル作成が完了しました。' AS message;
SELECT 'プラットフォームDB: diary_scrape_settings, diary_scrape_logs' AS platform_tables;
SELECT 'テナントDB: diary_posts（各テナントDBで実行してください）' AS tenant_tables;
