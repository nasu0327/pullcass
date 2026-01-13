-- pullcass マルチテナントシステム 初期化SQL
-- 作成日: 2026-01-13

-- ============================================
-- プラットフォームデータベース（スーパー管理用）
-- ============================================

-- テナント（店舗）管理テーブル
CREATE TABLE IF NOT EXISTS tenants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(50) NOT NULL UNIQUE COMMENT 'URLスラッグ（例: houman）',
    name VARCHAR(100) NOT NULL COMMENT '店舗名（例: 豊満倶楽部）',
    domain VARCHAR(100) DEFAULT NULL COMMENT 'カスタムドメイン（例: club-houman.com）',
    db_name VARCHAR(100) NOT NULL COMMENT 'テナント用DB名',
    status ENUM('active', 'inactive', 'maintenance') DEFAULT 'active' COMMENT 'ステータス',
    settings JSON DEFAULT NULL COMMENT '店舗設定（JSON）',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='テナント（店舗）管理';

-- スーパー管理者テーブル
CREATE TABLE IF NOT EXISTS super_admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(100) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    last_login_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='スーパー管理者';

-- デフォルトのスーパー管理者を作成（パスワード: admin123）
INSERT INTO super_admins (username, email, password_hash, name) VALUES 
('admin', 'admin@pullcass.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'システム管理者');

-- ============================================
-- テナント用テーブルテンプレート
-- （各テナントDBに作成される）
-- ============================================

-- Note: 実際のテナントDB作成時に以下のテーブルを作成
-- - casts（キャスト情報）
-- - schedules（スケジュール）
-- - prices（料金）
-- - themes（テーマ設定）
-- - admins（店舗管理者）
-- など
