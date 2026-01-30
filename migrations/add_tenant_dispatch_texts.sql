-- テナント別「派遣状況テキスト」編集用テーブル
-- ホテルリストフロントの「ご利用の流れ」等の文言をテナントごとにカスタマイズ可能にする

CREATE TABLE IF NOT EXISTS tenant_dispatch_texts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    dispatch_type VARCHAR(20) NOT NULL COMMENT 'full=○派遣可能, conditional=※カードキー, limited=△要確認, none=×派遣不可',
    content MEDIUMTEXT COMMENT 'HTML可。未設定時はフロントのデフォルト文言を使用',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_tenant_type (tenant_id, dispatch_type),
    KEY idx_tenant_id (tenant_id),
    CONSTRAINT fk_dispatch_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='派遣状況別表示テキスト（テナント単位）';
