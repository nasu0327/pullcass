-- テーマ管理システム用テーブル
-- 実行日: 2026-01-14

-- テーマテンプレートテーブル（プリセットテーマ）
CREATE TABLE IF NOT EXISTS tenant_theme_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_name VARCHAR(100) NOT NULL COMMENT 'テンプレート名',
    template_slug VARCHAR(50) NOT NULL UNIQUE COMMENT 'スラッグ（識別子）',
    description TEXT COMMENT '説明',
    thumbnail_url VARCHAR(255) COMMENT 'サムネイル画像URL',
    template_data JSON NOT NULL COMMENT 'テーマ設定データ（colors, fonts）',
    display_order INT DEFAULT 0 COMMENT '表示順',
    is_active TINYINT(1) DEFAULT 1 COMMENT '有効フラグ',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='テーマテンプレート';

-- テナント別テーマテーブル
CREATE TABLE IF NOT EXISTS tenant_themes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL COMMENT 'テナントID',
    base_template_id INT COMMENT 'ベースとなるテンプレートID',
    theme_name VARCHAR(100) NOT NULL COMMENT 'テーマ名',
    theme_type ENUM('template_based', 'original') DEFAULT 'template_based' COMMENT 'テーマタイプ',
    status ENUM('draft', 'published') DEFAULT 'draft' COMMENT 'ステータス',
    theme_data JSON NOT NULL COMMENT 'テーマ設定データ',
    is_customized TINYINT(1) DEFAULT 0 COMMENT 'カスタマイズ済みフラグ',
    notes TEXT COMMENT 'メモ',
    created_by INT COMMENT '作成者ID',
    published_at TIMESTAMP NULL COMMENT '公開日時',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (base_template_id) REFERENCES tenant_theme_templates(id) ON DELETE SET NULL,
    INDEX idx_tenant_status (tenant_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='テナント別テーマ';

-- テーマ監査ログテーブル
CREATE TABLE IF NOT EXISTS tenant_theme_audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    theme_id INT NOT NULL COMMENT 'テーマID',
    tenant_id INT NOT NULL COMMENT 'テナントID',
    action VARCHAR(50) NOT NULL COMMENT 'アクション（created, updated, published, deleted）',
    admin_id INT COMMENT '管理者ID',
    admin_name VARCHAR(100) COMMENT '管理者名',
    before_data JSON COMMENT '変更前データ',
    after_data JSON COMMENT '変更後データ',
    ip_address VARCHAR(45) COMMENT 'IPアドレス',
    user_agent TEXT COMMENT 'ユーザーエージェント',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_theme_id (theme_id),
    INDEX idx_tenant_id (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='テーマ変更監査ログ';

-- デフォルトテンプレートデータの挿入
INSERT INTO tenant_theme_templates (template_name, template_slug, description, display_order, template_data) VALUES
('スタンダード（ピンク）', 'default', '華やかなピンクをベースにした定番デザイン。女性らしい柔らかな印象を与えます。', 1, JSON_OBJECT(
    'colors', JSON_OBJECT(
        'primary', '#f568df',
        'primary_light', '#ffa0f8',
        'text', '#474747',
        'btn_text', '#ffffff',
        'bg', '#ffffff',
        'overlay', 'rgba(244, 114, 182, 0.2)',
        'bg_type', 'solid'
    ),
    'fonts', JSON_OBJECT(
        'title1_en', 'Kranky',
        'title1_ja', 'Kaisei Decol',
        'title2_en', 'Kranky',
        'title2_ja', 'Kaisei Decol',
        'body_ja', 'M PLUS 1p'
    ),
    'version', '1.2.0'
)),
('エレガントゴールド', 'elegant-gold', '高級感のあるゴールドをアクセントにした上品なデザイン。', 2, JSON_OBJECT(
    'colors', JSON_OBJECT(
        'primary', '#d4af37',
        'primary_light', '#f0d77a',
        'text', '#333333',
        'btn_text', '#ffffff',
        'bg', '#fffef5',
        'overlay', 'rgba(212, 175, 55, 0.2)',
        'bg_type', 'solid'
    ),
    'fonts', JSON_OBJECT(
        'title1_en', 'MonteCarlo',
        'title1_ja', 'Shippori Mincho',
        'title2_en', 'MonteCarlo',
        'title2_ja', 'Shippori Mincho',
        'body_ja', 'Noto Sans JP'
    ),
    'version', '1.2.0'
)),
('クールブルー', 'cool-blue', '清潔感のあるブルーを基調としたモダンなデザイン。', 3, JSON_OBJECT(
    'colors', JSON_OBJECT(
        'primary', '#4fc3f7',
        'primary_light', '#b3e5fc',
        'text', '#37474f',
        'btn_text', '#ffffff',
        'bg', '#f5faff',
        'overlay', 'rgba(79, 195, 247, 0.2)',
        'bg_type', 'solid'
    ),
    'fonts', JSON_OBJECT(
        'title1_en', 'Inter',
        'title1_ja', 'BIZ UDPGothic',
        'title2_en', 'Inter',
        'title2_ja', 'BIZ UDPGothic',
        'body_ja', 'BIZ UDPGothic'
    ),
    'version', '1.2.0'
)),
('ミスティックパープル', 'mystic-purple', '神秘的な紫を使った幻想的なデザイン。', 4, JSON_OBJECT(
    'colors', JSON_OBJECT(
        'primary', '#9c27b0',
        'primary_light', '#ce93d8',
        'text', '#4a4a4a',
        'btn_text', '#ffffff',
        'bg', '#faf5ff',
        'overlay', 'rgba(156, 39, 176, 0.2)',
        'bg_type', 'solid'
    ),
    'fonts', JSON_OBJECT(
        'title1_en', 'Molle',
        'title1_ja', 'Yuji Boku',
        'title2_en', 'Molle',
        'title2_ja', 'Yuji Boku',
        'body_ja', 'Klee One'
    ),
    'version', '1.2.0'
)),
('ナチュラルブラウン', 'natural-brown', '温かみのあるブラウンを使った落ち着いたデザイン。', 5, JSON_OBJECT(
    'colors', JSON_OBJECT(
        'primary', '#8d6e63',
        'primary_light', '#bcaaa4',
        'text', '#5d4037',
        'btn_text', '#ffffff',
        'bg', '#fffaf5',
        'overlay', 'rgba(141, 110, 99, 0.2)',
        'bg_type', 'solid'
    ),
    'fonts', JSON_OBJECT(
        'title1_en', 'Griffy',
        'title1_ja', 'Kaisei Opti',
        'title2_en', 'Griffy',
        'title2_ja', 'Kaisei Opti',
        'body_ja', 'M PLUS 1p'
    ),
    'version', '1.2.0'
)),
('ポップ＆キュート', 'pop-cute', '明るくポップな配色の可愛らしいデザイン。', 6, JSON_OBJECT(
    'colors', JSON_OBJECT(
        'primary', '#ff6b9d',
        'primary_light', '#ffb7d5',
        'text', '#555555',
        'btn_text', '#ffffff',
        'bg', '#fff5f8',
        'overlay', 'rgba(255, 107, 157, 0.25)',
        'bg_type', 'solid'
    ),
    'fonts', JSON_OBJECT(
        'title1_en', 'Kranky',
        'title1_ja', 'Hachi Maru Pop',
        'title2_en', 'Kranky',
        'title2_ja', 'Mochiy Pop One',
        'body_ja', 'M PLUS 1p'
    ),
    'version', '1.2.0'
)),
('ダークエレガンス', 'dark-elegance', 'ダークモードベースの高級感あるデザイン。', 7, JSON_OBJECT(
    'colors', JSON_OBJECT(
        'primary', '#f44336',
        'primary_light', '#ff7961',
        'text', '#e0e0e0',
        'btn_text', '#ffffff',
        'bg', '#1a1a1a',
        'overlay', 'rgba(244, 67, 54, 0.3)',
        'bg_type', 'solid'
    ),
    'fonts', JSON_OBJECT(
        'title1_en', 'Codystar',
        'title1_ja', 'Reggae One',
        'title2_en', 'Codystar',
        'title2_ja', 'Reggae One',
        'body_ja', 'Noto Sans JP'
    ),
    'version', '1.2.0'
)),
('グラデーションピンク', 'gradient-pink', 'ピンクのグラデーションを使った華やかなデザイン。', 8, JSON_OBJECT(
    'colors', JSON_OBJECT(
        'primary', '#f568df',
        'primary_light', '#ffa0f8',
        'text', '#474747',
        'btn_text', '#ffffff',
        'bg', '#ffffff',
        'bg_gradient_start', '#ffffff',
        'bg_gradient_end', '#ffd2fe',
        'overlay', 'rgba(244, 114, 182, 0.2)',
        'bg_type', 'gradient'
    ),
    'fonts', JSON_OBJECT(
        'title1_en', 'Kranky',
        'title1_ja', 'Kaisei Decol',
        'title2_en', 'Kranky',
        'title2_ja', 'Kaisei Decol',
        'body_ja', 'M PLUS 1p'
    ),
    'version', '1.2.0'
));
