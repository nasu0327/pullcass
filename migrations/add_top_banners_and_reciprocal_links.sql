-- トップバナーと相互リンクテーブルを追加
-- 実行日: 2026-01-14

-- トップバナーテーブル
CREATE TABLE IF NOT EXISTS top_banners (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    pc_image VARCHAR(500) NOT NULL COMMENT 'PC用バナー画像パス',
    sp_image VARCHAR(500) NOT NULL COMMENT 'SP用バナー画像パス',
    pc_url VARCHAR(500) NOT NULL COMMENT 'PCリンクURL',
    sp_url VARCHAR(500) NOT NULL COMMENT 'SPリンクURL',
    alt_text VARCHAR(200) DEFAULT NULL COMMENT 'alt属性（画像の説明）',
    display_order INT DEFAULT 0 COMMENT '表示順',
    is_visible TINYINT(1) DEFAULT 1 COMMENT '表示/非表示',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant_order (tenant_id, display_order),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='トップバナー';

-- 相互リンクテーブル
CREATE TABLE IF NOT EXISTS reciprocal_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    banner_image VARCHAR(500) DEFAULT NULL COMMENT 'バナー画像パス',
    alt_text VARCHAR(200) DEFAULT NULL COMMENT 'ALTテキスト/管理用名前',
    link_url VARCHAR(500) DEFAULT NULL COMMENT 'リンクURL',
    custom_code TEXT DEFAULT NULL COMMENT 'カスタムHTMLコード',
    nofollow TINYINT(1) DEFAULT 1 COMMENT 'nofollow属性',
    display_order INT DEFAULT 0 COMMENT '表示順',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant_order (tenant_id, display_order),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='相互リンク';

-- 確認用
-- SELECT * FROM top_banners;
-- SELECT * FROM reciprocal_links;
