-- テナント別「ホテルリストページ」タイトル・案内文編集用テーブル
-- ホテルリストフロントのタイトルと案内文をテナントごとにカスタマイズ可能にする

CREATE TABLE IF NOT EXISTS tenant_hotel_list_texts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    text_type VARCHAR(30) NOT NULL COMMENT 'title=タイトル, description=案内文',
    content MEDIUMTEXT COMMENT '未設定時はフロントのデフォルト文言を使用',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_tenant_type (tenant_id, text_type),
    KEY idx_tenant_id (tenant_id),
    CONSTRAINT fk_hotel_list_texts_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ホテルリストページ用テキスト（テナント単位）';
