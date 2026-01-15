-- キャストスクレイピング機能用テーブル
-- 実行日: 2026-01-15

-- =====================================================
-- 1. スクレイピング設定テーブル（テナント別）
-- =====================================================
CREATE TABLE IF NOT EXISTS tenant_scraping_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL COMMENT 'テナントID',
    config_key VARCHAR(100) NOT NULL COMMENT '設定キー',
    config_value TEXT COMMENT '設定値',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_tenant_config (tenant_id, config_key),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_tenant_id (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='テナント別スクレイピング設定';

-- 設定キーの種類:
-- ekichika_list_url, heaven_list_url, dto_list_url - スクレイピングURL
-- ekichika_enabled, heaven_enabled, dto_enabled - 有効/無効 (1/0)
-- active_source - 現在のアクティブソース (ekichika/heaven/dto)

-- =====================================================
-- 2. スクレイピングステータステーブル（テナント別）
-- =====================================================
CREATE TABLE IF NOT EXISTS tenant_scraping_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL COMMENT 'テナントID',
    scraping_type VARCHAR(50) NOT NULL COMMENT 'スクレイピングタイプ (ekichika/heaven/dto)',
    status ENUM('idle', 'running', 'completed', 'error') DEFAULT 'idle' COMMENT 'ステータス',
    start_time DATETIME COMMENT '開始時刻',
    end_time DATETIME COMMENT '終了時刻',
    success_count INT DEFAULT 0 COMMENT '成功件数',
    error_count INT DEFAULT 0 COMMENT 'エラー件数',
    last_error TEXT COMMENT '最後のエラー内容',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_tenant_type (tenant_id, scraping_type),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_tenant_id (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='テナント別スクレイピングステータス';

-- =====================================================
-- 3. キャストデータテーブル（駅ちか）
-- =====================================================
CREATE TABLE IF NOT EXISTS tenant_cast_data_ekichika (
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
    day2 VARCHAR(50) COMMENT '2日目出勤',
    day3 VARCHAR(50) COMMENT '3日目出勤',
    day4 VARCHAR(50) COMMENT '4日目出勤',
    day5 VARCHAR(50) COMMENT '5日目出勤',
    day6 VARCHAR(50) COMMENT '6日目出勤',
    day7 VARCHAR(50) COMMENT '7日目出勤',
    checked TINYINT(1) DEFAULT 1 COMMENT '有効フラグ',
    source_url VARCHAR(500) COMMENT '元ページURL',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_tenant_name (tenant_id, name),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_tenant_checked (tenant_id, checked),
    INDEX idx_tenant_sort (tenant_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='キャストデータ（駅ちか）';

-- =====================================================
-- 4. キャストデータテーブル（ヘブンネット）
-- =====================================================
CREATE TABLE IF NOT EXISTS tenant_cast_data_heaven (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL COMMENT 'テナントID',
    heaven_id VARCHAR(50) COMMENT 'ヘブンネットID',
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
    day2 VARCHAR(50) COMMENT '2日目出勤',
    day3 VARCHAR(50) COMMENT '3日目出勤',
    day4 VARCHAR(50) COMMENT '4日目出勤',
    day5 VARCHAR(50) COMMENT '5日目出勤',
    day6 VARCHAR(50) COMMENT '6日目出勤',
    day7 VARCHAR(50) COMMENT '7日目出勤',
    checked TINYINT(1) DEFAULT 1 COMMENT '有効フラグ',
    source_url VARCHAR(500) COMMENT '元ページURL',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_tenant_name (tenant_id, name),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_tenant_checked (tenant_id, checked),
    INDEX idx_tenant_sort (tenant_id, sort_order),
    INDEX idx_heaven_id (tenant_id, heaven_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='キャストデータ（ヘブンネット）';

-- =====================================================
-- 5. キャストデータテーブル（デリヘルタウン）
-- =====================================================
CREATE TABLE IF NOT EXISTS tenant_cast_data_dto (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL COMMENT 'テナントID',
    dto_id VARCHAR(50) COMMENT 'デリヘルタウンID',
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
    day2 VARCHAR(50) COMMENT '2日目出勤',
    day3 VARCHAR(50) COMMENT '3日目出勤',
    day4 VARCHAR(50) COMMENT '4日目出勤',
    day5 VARCHAR(50) COMMENT '5日目出勤',
    day6 VARCHAR(50) COMMENT '6日目出勤',
    day7 VARCHAR(50) COMMENT '7日目出勤',
    checked TINYINT(1) DEFAULT 1 COMMENT '有効フラグ',
    source_url VARCHAR(500) COMMENT '元ページURL',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_tenant_name (tenant_id, name),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_tenant_checked (tenant_id, checked),
    INDEX idx_tenant_sort (tenant_id, sort_order),
    INDEX idx_dto_id (tenant_id, dto_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='キャストデータ（デリヘルタウン）';

-- =====================================================
-- 6. 統合キャストマスターテーブル（閲覧履歴等で使用）
-- =====================================================
CREATE TABLE IF NOT EXISTS tenant_casts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL COMMENT 'テナントID',
    source_type ENUM('ekichika', 'heaven', 'dto', 'manual') NOT NULL COMMENT 'データソース',
    source_id INT COMMENT 'ソーステーブルのID',
    name VARCHAR(100) NOT NULL COMMENT 'キャスト名',
    name_romaji VARCHAR(100) COMMENT 'ローマ字名',
    display_order INT DEFAULT 0 COMMENT '表示順',
    is_active TINYINT(1) DEFAULT 1 COMMENT '有効フラグ',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_tenant_source_name (tenant_id, source_type, name),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_tenant_active (tenant_id, is_active),
    INDEX idx_tenant_order (tenant_id, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='統合キャストマスター';

-- =====================================================
-- 7. キャスト閲覧履歴テーブル
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

-- =====================================================
-- 8. スクレイピングログテーブル
-- =====================================================
CREATE TABLE IF NOT EXISTS tenant_scraping_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL COMMENT 'テナントID',
    scraping_type VARCHAR(50) NOT NULL COMMENT 'スクレイピングタイプ',
    log_level ENUM('info', 'warning', 'error') DEFAULT 'info' COMMENT 'ログレベル',
    message TEXT COMMENT 'ログメッセージ',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_tenant_type (tenant_id, scraping_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='スクレイピングログ';
