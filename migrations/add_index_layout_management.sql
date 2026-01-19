-- ========================================
-- pullcass: インデックスページ（年齢確認ページ）レイアウト管理機能
-- マイグレーション（マルチテナント対応版）
-- ========================================
-- 作成日: 2026-01-19
-- 実行方法: phpMyAdminで実行
-- ========================================

-- ========================================
-- 1. index_layout_sections（セクション管理テーブル）
-- ========================================
-- ※1カラム構成のため、pc_left_order/pc_right_order/mobile_orderは不要
-- ※display_orderで表示順を管理
CREATE TABLE IF NOT EXISTS `index_layout_sections` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'セクションID',
  `tenant_id` int(11) NOT NULL COMMENT 'テナントID',
  `section_key` varchar(50) NOT NULL COMMENT 'セクション識別子（英数字・アンダースコアのみ）',
  `section_type` enum('hero','banner','text_content','embed_widget','reciprocal_links') NOT NULL COMMENT 'セクションタイプ',
  `title_en` varchar(100) NOT NULL DEFAULT '' COMMENT '英語タイトル',
  `title_ja` varchar(100) NOT NULL DEFAULT '' COMMENT '日本語タイトル',
  `admin_title` varchar(100) NOT NULL COMMENT '管理画面用タイトル',
  `is_visible` tinyint(1) DEFAULT 1 COMMENT '表示/非表示（1=表示, 0=非表示）',
  `display_order` int(11) NOT NULL DEFAULT 999 COMMENT '表示順',
  `config` longtext DEFAULT NULL COMMENT 'セクション固有の設定（JSON形式）',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT '作成日時',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '更新日時',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_section_key` (`tenant_id`, `section_key`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_tenant_section_type` (`tenant_id`, `section_type`),
  KEY `idx_tenant_visible` (`tenant_id`, `is_visible`),
  KEY `idx_tenant_order` (`tenant_id`, `display_order`),
  CONSTRAINT `fk_index_layout_sections_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='インデックスページセクション管理（編集中）';

-- ========================================
-- 2. index_layout_sections_published（公開済みセクション）
-- ========================================
CREATE TABLE IF NOT EXISTS `index_layout_sections_published` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'セクションID',
  `tenant_id` int(11) NOT NULL COMMENT 'テナントID',
  `section_key` varchar(50) NOT NULL COMMENT 'セクション識別子（英数字・アンダースコアのみ）',
  `section_type` enum('hero','banner','text_content','embed_widget','reciprocal_links') NOT NULL COMMENT 'セクションタイプ',
  `title_en` varchar(100) NOT NULL DEFAULT '' COMMENT '英語タイトル',
  `title_ja` varchar(100) NOT NULL DEFAULT '' COMMENT '日本語タイトル',
  `admin_title` varchar(100) NOT NULL COMMENT '管理画面用タイトル',
  `is_visible` tinyint(1) DEFAULT 1 COMMENT '表示/非表示（1=表示, 0=非表示）',
  `display_order` int(11) NOT NULL DEFAULT 999 COMMENT '表示順',
  `config` longtext DEFAULT NULL COMMENT 'セクション固有の設定（JSON形式）',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT '作成日時',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '更新日時',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_section_key` (`tenant_id`, `section_key`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_tenant_section_type` (`tenant_id`, `section_type`),
  KEY `idx_tenant_visible` (`tenant_id`, `is_visible`),
  KEY `idx_tenant_order` (`tenant_id`, `display_order`),
  CONSTRAINT `fk_index_layout_sections_published_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='インデックスページセクション管理（公開済み）';

-- ========================================
-- 3. index_layout_sections_saved（下書き保存セクション）
-- ========================================
CREATE TABLE IF NOT EXISTS `index_layout_sections_saved` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'セクションID',
  `tenant_id` int(11) NOT NULL COMMENT 'テナントID',
  `section_key` varchar(50) NOT NULL COMMENT 'セクション識別子（英数字・アンダースコアのみ）',
  `section_type` enum('hero','banner','text_content','embed_widget','reciprocal_links') NOT NULL COMMENT 'セクションタイプ',
  `title_en` varchar(100) NOT NULL DEFAULT '' COMMENT '英語タイトル',
  `title_ja` varchar(100) NOT NULL DEFAULT '' COMMENT '日本語タイトル',
  `admin_title` varchar(100) NOT NULL COMMENT '管理画面用タイトル',
  `is_visible` tinyint(1) DEFAULT 1 COMMENT '表示/非表示（1=表示, 0=非表示）',
  `display_order` int(11) NOT NULL DEFAULT 999 COMMENT '表示順',
  `config` longtext DEFAULT NULL COMMENT 'セクション固有の設定（JSON形式）',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT '作成日時',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '更新日時',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_section_key` (`tenant_id`, `section_key`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_tenant_section_type` (`tenant_id`, `section_type`),
  KEY `idx_tenant_visible` (`tenant_id`, `is_visible`),
  KEY `idx_tenant_order` (`tenant_id`, `display_order`),
  CONSTRAINT `fk_index_layout_sections_saved_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='インデックスページセクション管理（下書き保存）';

-- ========================================
-- 4. index_layout_banners（バナー管理テーブル）
-- ========================================
CREATE TABLE IF NOT EXISTS `index_layout_banners` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'バナーID',
  `tenant_id` int(11) NOT NULL COMMENT 'テナントID',
  `section_id` int(11) NOT NULL COMMENT 'index_layout_sectionsへの外部キー',
  `image_path` varchar(255) NOT NULL COMMENT '画像パス（相対パスまたは絶対パス）',
  `link_url` varchar(500) DEFAULT NULL COMMENT 'リンク先URL（NULLの場合はリンクなし）',
  `target` enum('_self','_blank') DEFAULT '_self' COMMENT 'リンクのtarget属性',
  `nofollow` tinyint(1) DEFAULT 0 COMMENT 'nofollowを付与するか (0=false, 1=true)',
  `alt_text` varchar(200) DEFAULT NULL COMMENT 'alt属性（画像の説明テキスト）',
  `display_order` int(11) DEFAULT 0 COMMENT '表示順序（同一セクション内）',
  `is_visible` tinyint(1) DEFAULT 1 COMMENT '表示/非表示（1=表示, 0=非表示）',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT '作成日時',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '更新日時',
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_tenant_section` (`tenant_id`, `section_id`),
  KEY `idx_tenant_display_order` (`tenant_id`, `display_order`),
  KEY `idx_tenant_visible` (`tenant_id`, `is_visible`),
  CONSTRAINT `fk_index_layout_banners_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_index_layout_banners_section` FOREIGN KEY (`section_id`) REFERENCES `index_layout_sections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='インデックスページバナー管理';

-- ========================================
-- 実行手順
-- ========================================
/*
【phpMyAdminでの実行方法】

1. phpMyAdminにログイン
2. 左側のデータベース一覧から「pullcass」データベースを選択
3. 上部メニューの「SQL」タブをクリック
4. このSQLファイルの内容を全てコピー＆ペースト
5. 「実行」ボタンをクリック
6. 成功メッセージが4つ表示されることを確認
   - index_layout_sections テーブル作成
   - index_layout_sections_published テーブル作成
   - index_layout_sections_saved テーブル作成
   - index_layout_banners テーブル作成

【確認方法】

実行後、以下のSQLで各テーブルが作成されたことを確認してください：

SHOW TABLES LIKE 'index_layout%';

期待される結果：
- index_layout_banners
- index_layout_sections
- index_layout_sections_published
- index_layout_sections_saved

【テーブル構造の確認】

各テーブルの構造を確認するには：

DESCRIBE index_layout_sections;
DESCRIBE index_layout_sections_published;
DESCRIBE index_layout_sections_saved;
DESCRIBE index_layout_banners;

【トラブルシューティング】

エラーが発生した場合：
1. テーブルが既に存在する場合：
   - DROP TABLE IF EXISTS index_layout_banners;
   - DROP TABLE IF EXISTS index_layout_sections_saved;
   - DROP TABLE IF EXISTS index_layout_sections_published;
   - DROP TABLE IF EXISTS index_layout_sections;
   - 上記を実行してから再度このSQLを実行

2. 外部キー制約エラーの場合：
   - tenants テーブルが存在することを確認
   - SHOW TABLES LIKE 'tenants';

3. 権限エラーの場合：
   - データベースユーザーに CREATE TABLE 権限があることを確認
*/

-- ========================================
-- section_type の説明
-- ========================================
/*
- hero: ヒーローセクション（背景画像/動画、タイトル等）
- banner: バナー画像セクション（姉妹店、求人など）
- text_content: テキストコンテンツ（注意事項など、HTML対応）
- embed_widget: 埋め込みウィジェット（iframe等）
- reciprocal_links: 相互リンク（デフォルトセクション）
*/

-- ========================================
-- config カラムの使用例（JSON形式）
-- ========================================
/*
hero の場合:
{
  "background_type": "image",  // "image", "video", "theme"（テーマカラー）
  "background_image": "/uploads/tenants/xxx/index/hero_bg.jpg",
  "background_video": "/uploads/tenants/xxx/index/hero_bg.mp4",
  "video_poster": "/uploads/tenants/xxx/index/hero_poster.jpg"
}

text_content の場合:
{
  "html_content": "<div>...</div>"
}

embed_widget の場合:
{
  "embed_code": "<iframe src='...'></iframe>",
  "embed_height": "400"
}

reciprocal_links の場合:
{
  // 相互リンクは既存のreciprocal_linksテーブルを参照するため、configは空でOK
}
*/

-- ========================================
-- デフォルトセクション
-- ========================================
/*
テナント作成時に以下のデフォルトセクションを自動作成：

1. hero（ヒーローセクション）
   - section_key: 'hero'
   - section_type: 'hero'
   - display_order: 1
   - 削除不可

2. reciprocal_links（相互リンク）
   - section_key: 'reciprocal_links'
   - section_type: 'reciprocal_links'
   - display_order: 100
   - 削除不可
*/
