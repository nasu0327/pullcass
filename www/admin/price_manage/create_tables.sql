-- 料金表管理システム - テーブル作成SQL
-- このSQLをphpMyAdminで実行してください

-- 料金セットテーブル（編集用）
CREATE TABLE IF NOT EXISTS `price_sets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `set_name` varchar(255) NOT NULL COMMENT '料金セット名',
  `set_type` enum('regular','special') NOT NULL DEFAULT 'regular' COMMENT 'タイプ（平常期間/特別期間）',
  `start_datetime` datetime DEFAULT NULL COMMENT '開始日時（特別期間のみ）',
  `end_datetime` datetime DEFAULT NULL COMMENT '終了日時（特別期間のみ）',
  `display_order` int(11) NOT NULL DEFAULT 0 COMMENT '表示順',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '有効フラグ',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_set_type` (`set_type`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_start_datetime` (`start_datetime`),
  KEY `idx_end_datetime` (`end_datetime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='料金セット';

-- 料金コンテンツテーブル（編集用）
CREATE TABLE IF NOT EXISTS `price_contents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `set_id` int(11) NOT NULL COMMENT '料金セットID',
  `content_type` enum('price_table','banner','text') NOT NULL COMMENT 'コンテンツタイプ',
  `admin_title` varchar(255) NOT NULL DEFAULT '' COMMENT '管理名',
  `display_order` int(11) NOT NULL DEFAULT 0 COMMENT '表示順',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '有効フラグ',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_set_id` (`set_id`),
  KEY `idx_display_order` (`display_order`),
  CONSTRAINT `fk_price_contents_set_id` FOREIGN KEY (`set_id`) REFERENCES `price_sets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='料金コンテンツ';

-- 料金表テーブル（編集用）
CREATE TABLE IF NOT EXISTS `price_tables` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `content_id` int(11) NOT NULL COMMENT 'コンテンツID',
  `table_name` varchar(255) NOT NULL DEFAULT '' COMMENT '表示名',
  `column1_header` varchar(255) NOT NULL DEFAULT '' COMMENT '左列ヘッダー',
  `column2_header` varchar(255) NOT NULL DEFAULT '' COMMENT '右列ヘッダー',
  `note` text COMMENT '追記事項（HTML可）',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_content_id` (`content_id`),
  CONSTRAINT `fk_price_tables_content_id` FOREIGN KEY (`content_id`) REFERENCES `price_contents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='料金表';

-- 料金表行テーブル（編集用）
CREATE TABLE IF NOT EXISTS `price_rows` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `table_id` int(11) NOT NULL COMMENT '料金表ID',
  `time_label` varchar(255) NOT NULL DEFAULT '' COMMENT '時間ラベル',
  `price_label` varchar(255) NOT NULL DEFAULT '' COMMENT '料金ラベル',
  `display_order` int(11) NOT NULL DEFAULT 0 COMMENT '表示順',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_table_id` (`table_id`),
  KEY `idx_display_order` (`display_order`),
  CONSTRAINT `fk_price_rows_table_id` FOREIGN KEY (`table_id`) REFERENCES `price_tables` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='料金表行';

-- バナーテーブル（編集用）
CREATE TABLE IF NOT EXISTS `price_banners` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `content_id` int(11) NOT NULL COMMENT 'コンテンツID',
  `image_path` varchar(500) NOT NULL DEFAULT '' COMMENT '画像パス',
  `link_url` varchar(500) NOT NULL DEFAULT '' COMMENT 'リンクURL',
  `alt_text` varchar(255) NOT NULL DEFAULT '' COMMENT 'alt属性',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_content_id` (`content_id`),
  CONSTRAINT `fk_price_banners_content_id` FOREIGN KEY (`content_id`) REFERENCES `price_contents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='料金バナー';

-- テキストコンテンツテーブル（編集用）
CREATE TABLE IF NOT EXISTS `price_texts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `content_id` int(11) NOT NULL COMMENT 'コンテンツID',
  `content` longtext COMMENT 'テキストコンテンツ（HTML可）',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_content_id` (`content_id`),
  CONSTRAINT `fk_price_texts_content_id` FOREIGN KEY (`content_id`) REFERENCES `price_contents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='料金テキスト';

-- 公開用テーブル（編集用テーブルからコピーされる）

-- 料金セットテーブル（公開用）
CREATE TABLE IF NOT EXISTS `price_sets_published` (
  `id` int(11) NOT NULL,
  `set_name` varchar(255) NOT NULL,
  `set_type` enum('regular','special') NOT NULL DEFAULT 'regular',
  `start_datetime` datetime DEFAULT NULL,
  `end_datetime` datetime DEFAULT NULL,
  `display_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_set_type` (`set_type`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_start_datetime` (`start_datetime`),
  KEY `idx_end_datetime` (`end_datetime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='料金セット（公開用）';

-- 料金コンテンツテーブル（公開用）
CREATE TABLE IF NOT EXISTS `price_contents_published` (
  `id` int(11) NOT NULL,
  `set_id` int(11) NOT NULL,
  `content_type` enum('price_table','banner','text') NOT NULL,
  `admin_title` varchar(255) NOT NULL DEFAULT '',
  `display_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_set_id` (`set_id`),
  KEY `idx_display_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='料金コンテンツ（公開用）';

-- 料金表テーブル（公開用）
CREATE TABLE IF NOT EXISTS `price_tables_published` (
  `id` int(11) NOT NULL,
  `content_id` int(11) NOT NULL,
  `table_name` varchar(255) NOT NULL DEFAULT '',
  `column1_header` varchar(255) NOT NULL DEFAULT '',
  `column2_header` varchar(255) NOT NULL DEFAULT '',
  `note` text,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_content_id` (`content_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='料金表（公開用）';

-- 料金表行テーブル（公開用）
CREATE TABLE IF NOT EXISTS `price_rows_published` (
  `id` int(11) NOT NULL,
  `table_id` int(11) NOT NULL,
  `time_label` varchar(255) NOT NULL DEFAULT '',
  `price_label` varchar(255) NOT NULL DEFAULT '',
  `display_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_table_id` (`table_id`),
  KEY `idx_display_order` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='料金表行（公開用）';

-- バナーテーブル（公開用）
CREATE TABLE IF NOT EXISTS `price_banners_published` (
  `id` int(11) NOT NULL,
  `content_id` int(11) NOT NULL,
  `image_path` varchar(500) NOT NULL DEFAULT '',
  `link_url` varchar(500) NOT NULL DEFAULT '',
  `alt_text` varchar(255) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_content_id` (`content_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='料金バナー（公開用）';

-- テキストコンテンツテーブル（公開用）
CREATE TABLE IF NOT EXISTS `price_texts_published` (
  `id` int(11) NOT NULL,
  `content_id` int(11) NOT NULL,
  `content` longtext,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_content_id` (`content_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='料金テキスト（公開用）';

-- 初期データ：平常期間料金セットを1つ作成
INSERT INTO `price_sets` (`set_name`, `set_type`, `display_order`, `is_active`) 
VALUES ('平常期間料金', 'regular', 0, 1)
ON DUPLICATE KEY UPDATE `set_name` = `set_name`;
