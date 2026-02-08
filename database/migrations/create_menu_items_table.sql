-- ============================================
-- メニュー管理システム - データベース構築スクリプト
-- ============================================
-- 
-- このスクリプトは以下を実行します：
-- 1. menu_items テーブルの作成
-- 2. 既存テナントへのデフォルトメニュー挿入
--
-- 実行方法：phpMyAdmin で pullcass データベースを選択し、このスクリプトを実行
-- ============================================

-- --------------------------------------------
-- 1. menu_items テーブルの作成
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS `menu_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY COMMENT 'メニュー項目ID',
    `tenant_id` INT NOT NULL COMMENT 'テナントID',
    `code` VARCHAR(50) DEFAULT NULL COMMENT 'メニューコード（例：HOME, CAST）',
    `label` VARCHAR(100) NOT NULL COMMENT '表示タイトル（例：キャスト一覧）',
    `link_type` ENUM('internal', 'external') DEFAULT 'internal' COMMENT 'リンクタイプ（internal=内部, external=外部）',
    `url` VARCHAR(500) NOT NULL COMMENT 'URL（内部リンクは相対パス、外部リンクは絶対URL）',
    `target` VARCHAR(20) DEFAULT '_self' COMMENT 'リンクターゲット（_self or _blank）',
    `order_num` INT DEFAULT 0 COMMENT '表示順序（小さい順に表示）',
    `is_active` TINYINT(1) DEFAULT 1 COMMENT '有効フラグ（1=有効, 0=無効）',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
    
    -- 外部キー制約
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
    
    -- インデックス
    INDEX `idx_tenant_order` (`tenant_id`, `order_num`, `is_active`),
    INDEX `idx_tenant_active` (`tenant_id`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='メニュー項目管理テーブル';

-- --------------------------------------------
-- 2. 既存テナントへのデフォルトメニュー挿入
-- --------------------------------------------
-- 注意：このセクションは既存のテナントに対してデフォルトメニューを作成します
-- 新規テナントは別途、テナント作成時に自動的にメニューが作成されます

-- 既存の全テナントに対してデフォルトメニューを挿入
-- （すでにメニューが存在するテナントはスキップ）
INSERT INTO `menu_items` (`tenant_id`, `code`, `label`, `link_type`, `url`, `target`, `order_num`, `is_active`)
SELECT 
    t.id AS tenant_id,
    'HOME' AS code,
    t.name AS label,
    'internal' AS link_type,
    '/app/front/index.php' AS url,
    '_self' AS target,
    1 AS order_num,
    1 AS is_active
FROM `tenants` t
WHERE NOT EXISTS (
    SELECT 1 FROM `menu_items` mi WHERE mi.tenant_id = t.id
)
AND t.is_active = 1;

INSERT INTO `menu_items` (`tenant_id`, `code`, `label`, `link_type`, `url`, `target`, `order_num`, `is_active`)
SELECT 
    t.id AS tenant_id,
    'TOP' AS code,
    'トップ' AS label,
    'internal' AS link_type,
    '/app/front/top.php' AS url,
    '_self' AS target,
    2 AS order_num,
    1 AS is_active
FROM `tenants` t
WHERE NOT EXISTS (
    SELECT 1 FROM `menu_items` mi WHERE mi.tenant_id = t.id
)
AND t.is_active = 1;

INSERT INTO `menu_items` (`tenant_id`, `code`, `label`, `link_type`, `url`, `target`, `order_num`, `is_active`)
SELECT 
    t.id AS tenant_id,
    'CAST' AS code,
    'キャスト一覧' AS label,
    'internal' AS link_type,
    '/app/front/cast/list.php' AS url,
    '_self' AS target,
    3 AS order_num,
    1 AS is_active
FROM `tenants` t
WHERE NOT EXISTS (
    SELECT 1 FROM `menu_items` mi WHERE mi.tenant_id = t.id
)
AND t.is_active = 1;

INSERT INTO `menu_items` (`tenant_id`, `code`, `label`, `link_type`, `url`, `target`, `order_num`, `is_active`)
SELECT 
    t.id AS tenant_id,
    'SCHEDULE' AS code,
    'スケジュール' AS label,
    'internal' AS link_type,
    '/app/front/schedule/day1.php' AS url,
    '_self' AS target,
    4 AS order_num,
    1 AS is_active
FROM `tenants` t
WHERE NOT EXISTS (
    SELECT 1 FROM `menu_items` mi WHERE mi.tenant_id = t.id
)
AND t.is_active = 1;

INSERT INTO `menu_items` (`tenant_id`, `code`, `label`, `link_type`, `url`, `target`, `order_num`, `is_active`)
SELECT 
    t.id AS tenant_id,
    'SYSTEM' AS code,
    '料金システム' AS label,
    'internal' AS link_type,
    '/app/front/system.php' AS url,
    '_self' AS target,
    5 AS order_num,
    1 AS is_active
FROM `tenants` t
WHERE NOT EXISTS (
    SELECT 1 FROM `menu_items` mi WHERE mi.tenant_id = t.id
)
AND t.is_active = 1;

-- --------------------------------------------
-- 完了メッセージ
-- --------------------------------------------
-- スクリプトの実行が完了しました
-- 以下のクエリで確認できます：
-- SELECT * FROM menu_items ORDER BY tenant_id, order_num;
