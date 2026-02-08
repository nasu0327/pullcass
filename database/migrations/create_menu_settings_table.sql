-- ============================================
-- メニュー背景設定テーブル - データベース構築スクリプト
-- ============================================
-- 
-- このスクリプトはメニューの背景カスタマイズ設定を管理するテーブルを作成します。
-- 各テナントごとに1レコードで設定を保存します。
--
-- 実行方法：phpMyAdmin で pullcass データベースを選択し、このスクリプトを実行
-- ============================================

-- --------------------------------------------
-- menu_settings テーブルの作成
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS `menu_settings` (
    `id` INT AUTO_INCREMENT PRIMARY KEY COMMENT '設定ID',
    `tenant_id` INT NOT NULL UNIQUE COMMENT 'テナントID（ユニーク）',
    `background_type` ENUM('theme', 'solid', 'gradient', 'image') DEFAULT 'theme' COMMENT '背景タイプ',
    `background_color` VARCHAR(7) DEFAULT NULL COMMENT '単色モードの背景色（例：#f568df）',
    `gradient_start` VARCHAR(7) DEFAULT NULL COMMENT 'グラデーション開始色',
    `gradient_end` VARCHAR(7) DEFAULT NULL COMMENT 'グラデーション終了色',
    `background_image` VARCHAR(500) DEFAULT NULL COMMENT '背景画像パス',
    `overlay_color` VARCHAR(7) DEFAULT '#000000' COMMENT 'オーバーレイ色（画像モード用）',
    `overlay_opacity` DECIMAL(3,2) DEFAULT 0.50 COMMENT 'オーバーレイ透明度（0.00～1.00）',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
    
    -- 外部キー制約
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
    
    -- インデックス
    INDEX `idx_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='メニュー背景設定テーブル';

-- --------------------------------------------
-- 完了メッセージ
-- --------------------------------------------
-- テーブルが正常に作成されました
-- デフォルト値として全てのテナントは 'theme' タイプ（テーマカラー使用）になります
