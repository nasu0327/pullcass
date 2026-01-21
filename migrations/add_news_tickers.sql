-- =====================================================
-- ニュースティッカー管理テーブル
-- 実行方法: phpMyAdminのSQLタブでこのファイルの内容を実行
-- =====================================================

-- news_tickers テーブル作成
CREATE TABLE IF NOT EXISTS news_tickers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    text TEXT NOT NULL COMMENT 'ティッカーに表示するテキスト',
    url VARCHAR(500) DEFAULT NULL COMMENT 'リンク先URL（任意）',
    media_url VARCHAR(500) DEFAULT NULL COMMENT '画像URL（任意）',
    display_order INT DEFAULT 0 COMMENT '表示順序',
    is_visible TINYINT(1) DEFAULT 1 COMMENT '表示/非表示フラグ',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant_visible (tenant_id, is_visible),
    INDEX idx_display_order (tenant_id, display_order),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ニュースティッカー管理';

-- 確認用クエリ
-- SELECT * FROM news_tickers;
