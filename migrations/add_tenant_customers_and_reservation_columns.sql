-- ============================================
-- 顧客マスタ追加 + 予約テーブル拡張
-- 実行日: 2026-02-XX
-- 目的: リピート顧客の紐付け、予約詳細表示の強化
--
-- 注意: add_reservation_detail_columns.sql を既に実行済みの場合、
--       「2.」の course_content_id と event_campaign の ALTER は
--       "Duplicate column" エラーになるため、該当2行をスキップしてください。
-- ============================================

-- --------------------------------------------------
-- 1. 顧客マスタテーブルの作成（テナントごとに電話番号で一意）
-- --------------------------------------------------
CREATE TABLE IF NOT EXISTS `tenant_customers` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL COMMENT 'テナントID',
    phone VARCHAR(20) NOT NULL COMMENT '電話番号（数字のみ正規化して格納）',
    email VARCHAR(255) DEFAULT NULL COMMENT 'メールアドレス',
    name VARCHAR(100) DEFAULT NULL COMMENT 'お名前（最新予約から更新）',
    memo TEXT DEFAULT NULL COMMENT 'メモ（将来用）',
    first_reservation_at DATETIME DEFAULT NULL COMMENT '初回予約日時',
    last_reservation_at DATETIME DEFAULT NULL COMMENT '最終予約日時',
    reservation_count INT DEFAULT 0 COMMENT '予約回数',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_tenant_phone (tenant_id, phone),
    INDEX idx_tenant_email (tenant_id, email),
    INDEX idx_tenant_id (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='顧客マスタ（電話番号ベース、テナント単位）';

-- --------------------------------------------------
-- 2. 予約テーブルにカラム追加
-- --------------------------------------------------

-- 顧客ID（tenant_customers への紐付け）
ALTER TABLE `tenant_reservations` 
    ADD COLUMN `customer_id` INT DEFAULT NULL 
    COMMENT '顧客ID（tenant_customers.id）' 
    AFTER `tenant_id`;

ALTER TABLE `tenant_reservations` 
    ADD INDEX `idx_customer_id` (`customer_id`);

-- コース内容ID（90分 17,000円 などの行を識別）
ALTER TABLE `tenant_reservations` 
    ADD COLUMN `course_content_id` INT DEFAULT NULL 
    COMMENT 'コース内容ID（price_rows_published.id）' 
    AFTER `course`;

-- イベント・キャンペーン名
ALTER TABLE `tenant_reservations` 
    ADD COLUMN `event_campaign` VARCHAR(255) DEFAULT NULL 
    COMMENT 'イベント・キャンペーン名' 
    AFTER `message`;

-- --------------------------------------------------
-- 3. 既存予約データの紐付け（オプション）
-- --------------------------------------------------
-- 既存データを顧客マスタに紐づける場合は、migrations/backfill_reservation_customers.php
-- を実行するか、手動で顧客を作成後 customer_id を更新してください。
