-- ============================================
-- 口コミスクレイピング機能 - テーブル作成SQL（最終版）
-- 作成日: 2026-02-17
-- 設計: プラットフォームDB一元管理、写メ日記と同パターン
-- ============================================

USE pullcass;

-- ============================================
-- 1. 口コミデータテーブル（全テナント共通）
-- ============================================
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL COMMENT 'テナントID',
    user_name VARCHAR(255) NOT NULL COMMENT '投稿者名',
    cast_name VARCHAR(100) DEFAULT NULL COMMENT 'キャスト名（表示用）',
    cast_id INT DEFAULT NULL COMMENT 'キャストID（テナントDB内のID）',
    review_date DATE DEFAULT NULL COMMENT '口コミ掲載日',
    rating DECIMAL(3,1) DEFAULT NULL COMMENT '評価点',
    title VARCHAR(500) DEFAULT NULL COMMENT 'タイトル',
    content TEXT NOT NULL COMMENT '本文',
    shop_comment TEXT COMMENT 'お店からのコメント',
    source_url VARCHAR(500) COMMENT '取得元URL',
    source_id VARCHAR(64) NOT NULL COMMENT '重複防止用ハッシュ',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
    UNIQUE KEY unique_tenant_source (tenant_id, source_id),
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_tenant_cast (tenant_id, cast_id),
    INDEX idx_review_date (review_date),
    INDEX idx_tenant_review_date (tenant_id, review_date),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='口コミデータ（全テナント共通）';

-- ============================================
-- 2. 口コミスクレイピング設定テーブル
-- ============================================
CREATE TABLE IF NOT EXISTS review_scrape_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL COMMENT 'テナントID',
    reviews_base_url VARCHAR(500) NOT NULL COMMENT '口コミ一覧のベースURL（CityHeaven）',
    is_enabled TINYINT(1) DEFAULT 0 COMMENT '自動取得ON/OFF',
    scrape_interval INT DEFAULT 10 COMMENT '取得間隔（分）',
    request_delay DECIMAL(3,1) DEFAULT 1.0 COMMENT 'リクエスト間隔（秒）',
    max_pages INT DEFAULT 50 COMMENT '最大ページ数',
    timeout INT DEFAULT 30 COMMENT 'タイムアウト（秒）',
    last_executed_at DATETIME COMMENT '最終実行日時',
    last_execution_status ENUM('success', 'error', 'running') COMMENT '最終実行状態',
    last_error_message TEXT COMMENT '最終エラーメッセージ',
    total_reviews_scraped INT DEFAULT 0 COMMENT '累計取得口コミ数',
    last_reviews_count INT DEFAULT 0 COMMENT '最終取得口コミ数',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_tenant (tenant_id),
    INDEX idx_enabled (is_enabled),
    INDEX idx_last_executed (last_executed_at),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='口コミスクレイピング設定';

-- ============================================
-- 3. 口コミスクレイピング実行ログテーブル
-- ============================================
CREATE TABLE IF NOT EXISTS review_scrape_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL COMMENT 'テナントID',
    execution_type ENUM('manual', 'cron') NOT NULL COMMENT '実行タイプ',
    started_at DATETIME NOT NULL COMMENT '開始日時',
    finished_at DATETIME COMMENT '終了日時',
    execution_time DECIMAL(10,2) COMMENT '実行時間（秒）',
    status ENUM('success', 'error', 'timeout', 'running') NOT NULL COMMENT '実行結果',
    pages_processed INT DEFAULT 0 COMMENT '処理ページ数',
    reviews_found INT DEFAULT 0 COMMENT '検出口コミ数',
    reviews_saved INT DEFAULT 0 COMMENT '保存口コミ数',
    reviews_skipped INT DEFAULT 0 COMMENT 'スキップ口コミ数',
    errors_count INT DEFAULT 0 COMMENT 'エラー数',
    error_message TEXT COMMENT 'エラーメッセージ',
    memory_usage DECIMAL(10,2) COMMENT 'メモリ使用量（MB）',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_started_at (started_at),
    INDEX idx_status (status),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='口コミスクレイピング実行ログ';

-- ============================================
-- 4. 機能フラグ追加（review_scrape）
-- ============================================
INSERT INTO tenant_features (tenant_id, feature_code, is_enabled, created_at)
SELECT id, 'review_scrape', 0, NOW()
FROM tenants
WHERE NOT EXISTS (
    SELECT 1 FROM tenant_features
    WHERE tenant_id = tenants.id
    AND feature_code = 'review_scrape'
);

-- ============================================
-- 5. 確認用クエリ
-- ============================================
SELECT '口コミスクレイピング機能のテーブル作成が完了しました。' AS message;

SELECT 'プラットフォームDB(pullcass): reviews, review_scrape_settings, review_scrape_logs' AS created_tables;
