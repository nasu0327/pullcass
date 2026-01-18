-- =====================================================
-- pullcass - 店舗管理者テーブル追加
-- 作成日: 2026-01-18
-- 説明: 店舗管理画面へのログイン認証用テーブル
-- =====================================================

CREATE TABLE IF NOT EXISTS tenant_admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL COMMENT 'テナントID',
    username VARCHAR(50) NOT NULL COMMENT 'ログインID',
    password_hash VARCHAR(255) NOT NULL COMMENT 'パスワードハッシュ（bcrypt）',
    name VARCHAR(100) DEFAULT NULL COMMENT '管理者名',
    email VARCHAR(100) DEFAULT NULL COMMENT 'メールアドレス',
    is_active TINYINT(1) DEFAULT 1 COMMENT '有効フラグ',
    last_login_at TIMESTAMP NULL COMMENT '最終ログイン日時',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- インデックス
    UNIQUE KEY uk_tenant_username (tenant_id, username),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_tenant_active (tenant_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='店舗管理者';

-- テナント機能設定テーブルも作成（まだ存在しない場合）
CREATE TABLE IF NOT EXISTS tenant_features (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL COMMENT 'テナントID',
    feature_code VARCHAR(50) NOT NULL COMMENT '機能コード',
    is_enabled TINYINT(1) DEFAULT 1 COMMENT '有効フラグ',
    enabled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '有効化日時',
    
    -- インデックス
    UNIQUE KEY uk_tenant_feature (tenant_id, feature_code),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_tenant_enabled (tenant_id, is_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='テナント機能設定';
