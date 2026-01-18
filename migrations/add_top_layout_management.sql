-- ========================================
-- pullcass: トップページレイアウト管理機能
-- マイグレーション（マルチテナント対応版）
-- ========================================
-- 作成日: 2026-01-18
-- 実行方法: phpMyAdminで実行
-- ========================================

-- ========================================
-- 1. top_layout_sections（セクション管理テーブル）
-- ========================================
CREATE TABLE IF NOT EXISTS `top_layout_sections` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'セクションID',
  `tenant_id` int(11) NOT NULL COMMENT 'テナントID',
  `section_key` varchar(50) NOT NULL COMMENT 'セクション識別子（英数字・アンダースコアのみ）',
  `section_type` enum('cast_list','banner','ranking','content','external','text_content','embed_widget','hero_text') NOT NULL COMMENT 'セクションタイプ',
  `default_column` enum('left','right') NOT NULL COMMENT 'デフォルト配置カラム',
  `title_en` varchar(100) NOT NULL DEFAULT '' COMMENT '英語タイトル',
  `title_ja` varchar(100) NOT NULL DEFAULT '' COMMENT '日本語タイトル',
  `admin_title` varchar(100) NOT NULL COMMENT '管理画面用タイトル',
  `is_visible` tinyint(1) DEFAULT 1 COMMENT '表示/非表示（1=表示, 0=非表示）',
  `pc_left_order` int(11) DEFAULT NULL COMMENT 'PC左カラム表示順（NULLの場合は左カラムに非表示）',
  `pc_right_order` int(11) DEFAULT NULL COMMENT 'PC右カラム表示順（NULLの場合は右カラムに非表示）',
  `mobile_order` int(11) NOT NULL DEFAULT 999 COMMENT 'スマホ表示順',
  `mobile_visible` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'スマホ表示/非表示（1=表示, 0=非表示）',
  `status` enum('draft','published') DEFAULT 'published' COMMENT '公開状態（draft=下書き, published=公開中）',
  `config` longtext DEFAULT NULL COMMENT 'セクション固有の設定（JSON形式）',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT '作成日時',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '更新日時',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_section_key` (`tenant_id`, `section_key`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_tenant_section_type` (`tenant_id`, `section_type`),
  KEY `idx_tenant_visible` (`tenant_id`, `is_visible`),
  KEY `idx_tenant_pc_left` (`tenant_id`, `pc_left_order`),
  KEY `idx_tenant_pc_right` (`tenant_id`, `pc_right_order`),
  KEY `idx_tenant_mobile` (`tenant_id`, `mobile_order`),
  KEY `idx_tenant_status` (`tenant_id`, `status`),
  CONSTRAINT `fk_top_layout_sections_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='トップページセクション管理（編集中）';

-- ========================================
-- 2. top_layout_sections_published（公開済みセクション）
-- ========================================
CREATE TABLE IF NOT EXISTS `top_layout_sections_published` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'セクションID',
  `tenant_id` int(11) NOT NULL COMMENT 'テナントID',
  `section_key` varchar(50) NOT NULL COMMENT 'セクション識別子（英数字・アンダースコアのみ）',
  `section_type` enum('cast_list','banner','ranking','content','external','text_content','embed_widget','hero_text') NOT NULL COMMENT 'セクションタイプ',
  `default_column` enum('left','right') NOT NULL COMMENT 'デフォルト配置カラム',
  `title_en` varchar(100) NOT NULL DEFAULT '' COMMENT '英語タイトル',
  `title_ja` varchar(100) NOT NULL DEFAULT '' COMMENT '日本語タイトル',
  `admin_title` varchar(100) NOT NULL COMMENT '管理画面用タイトル',
  `is_visible` tinyint(1) DEFAULT 1 COMMENT '表示/非表示（1=表示, 0=非表示）',
  `pc_left_order` int(11) DEFAULT NULL COMMENT 'PC左カラム表示順（NULLの場合は左カラムに非表示）',
  `pc_right_order` int(11) DEFAULT NULL COMMENT 'PC右カラム表示順（NULLの場合は右カラムに非表示）',
  `mobile_order` int(11) NOT NULL DEFAULT 999 COMMENT 'スマホ表示順',
  `mobile_visible` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'スマホ表示/非表示（1=表示, 0=非表示）',
  `status` enum('draft','published') DEFAULT 'published' COMMENT '公開状態（draft=下書き, published=公開中）',
  `config` longtext DEFAULT NULL COMMENT 'セクション固有の設定（JSON形式）',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT '作成日時',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '更新日時',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_section_key` (`tenant_id`, `section_key`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_tenant_section_type` (`tenant_id`, `section_type`),
  KEY `idx_tenant_visible` (`tenant_id`, `is_visible`),
  KEY `idx_tenant_pc_left` (`tenant_id`, `pc_left_order`),
  KEY `idx_tenant_pc_right` (`tenant_id`, `pc_right_order`),
  KEY `idx_tenant_mobile` (`tenant_id`, `mobile_order`),
  KEY `idx_tenant_status` (`tenant_id`, `status`),
  CONSTRAINT `fk_top_layout_sections_published_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='トップページセクション管理（公開済み）';

-- ========================================
-- 3. top_layout_sections_saved（下書き保存セクション）
-- ========================================
CREATE TABLE IF NOT EXISTS `top_layout_sections_saved` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'セクションID',
  `tenant_id` int(11) NOT NULL COMMENT 'テナントID',
  `section_key` varchar(50) NOT NULL COMMENT 'セクション識別子（英数字・アンダースコアのみ）',
  `section_type` enum('cast_list','banner','ranking','content','external','text_content','embed_widget','hero_text') NOT NULL COMMENT 'セクションタイプ',
  `default_column` enum('left','right') NOT NULL COMMENT 'デフォルト配置カラム',
  `title_en` varchar(100) NOT NULL DEFAULT '' COMMENT '英語タイトル',
  `title_ja` varchar(100) NOT NULL DEFAULT '' COMMENT '日本語タイトル',
  `admin_title` varchar(100) NOT NULL COMMENT '管理画面用タイトル',
  `is_visible` tinyint(1) DEFAULT 1 COMMENT '表示/非表示（1=表示, 0=非表示）',
  `pc_left_order` int(11) DEFAULT NULL COMMENT 'PC左カラム表示順（NULLの場合は左カラムに非表示）',
  `pc_right_order` int(11) DEFAULT NULL COMMENT 'PC右カラム表示順（NULLの場合は右カラムに非表示）',
  `mobile_order` int(11) NOT NULL DEFAULT 999 COMMENT 'スマホ表示順',
  `mobile_visible` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'スマホ表示/非表示（1=表示, 0=非表示）',
  `status` enum('draft','published') DEFAULT 'published' COMMENT '公開状態（draft=下書き, published=公開中）',
  `config` longtext DEFAULT NULL COMMENT 'セクション固有の設定（JSON形式）',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT '作成日時',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '更新日時',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_section_key` (`tenant_id`, `section_key`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_tenant_section_type` (`tenant_id`, `section_type`),
  KEY `idx_tenant_visible` (`tenant_id`, `is_visible`),
  KEY `idx_tenant_pc_left` (`tenant_id`, `pc_left_order`),
  KEY `idx_tenant_pc_right` (`tenant_id`, `pc_right_order`),
  KEY `idx_tenant_mobile` (`tenant_id`, `mobile_order`),
  KEY `idx_tenant_status` (`tenant_id`, `status`),
  CONSTRAINT `fk_top_layout_sections_saved_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='トップページセクション管理（下書き保存）';

-- ========================================
-- 4. top_layout_banners（バナー管理テーブル）
-- ========================================
CREATE TABLE IF NOT EXISTS `top_layout_banners` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'バナーID',
  `tenant_id` int(11) NOT NULL COMMENT 'テナントID',
  `section_id` int(11) NOT NULL COMMENT 'top_layout_sectionsへの外部キー',
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
  CONSTRAINT `fk_top_layout_banners_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_top_layout_banners_section` FOREIGN KEY (`section_id`) REFERENCES `top_layout_sections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='トップページバナー管理';

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
   - top_layout_sections テーブル作成
   - top_layout_sections_published テーブル作成
   - top_layout_sections_saved テーブル作成
   - top_layout_banners テーブル作成

【確認方法】

実行後、以下のSQLで各テーブルが作成されたことを確認してください：

SHOW TABLES LIKE 'top_layout%';

期待される結果：
- top_layout_banners
- top_layout_sections
- top_layout_sections_published
- top_layout_sections_saved

【テーブル構造の確認】

各テーブルの構造を確認するには：

DESCRIBE top_layout_sections;
DESCRIBE top_layout_sections_published;
DESCRIBE top_layout_sections_saved;
DESCRIBE top_layout_banners;

【重要な変更点】

参考サイトとの違い：
1. 全テーブルに `tenant_id` カラムを追加（マルチテナント対応）
2. ユニークキーが `(tenant_id, section_key)` の複合キーに変更
3. 各テーブルに `tenants` テーブルへの外部キー制約を追加
4. ON DELETE CASCADE により、テナント削除時に関連データも自動削除
5. インデックスが全て `tenant_id` を含む複合インデックスに変更

【次のステップ】

テーブル作成後、各テナントごとにデフォルトセクションを作成する必要があります。
これは別途、初期データ投入SQLまたは管理画面から実行します。

【トラブルシューティング】

エラーが発生した場合：
1. テーブルが既に存在する場合：
   - DROP TABLE IF EXISTS top_layout_banners;
   - DROP TABLE IF EXISTS top_layout_sections_saved;
   - DROP TABLE IF EXISTS top_layout_sections_published;
   - DROP TABLE IF EXISTS top_layout_sections;
   - 上記を実行してから再度このSQLを実行

2. 外部キー制約エラーの場合：
   - tenants テーブルが存在することを確認
   - SHOW TABLES LIKE 'tenants';

3. 権限エラーの場合：
   - データベースユーザーに CREATE TABLE 権限があることを確認
*/

-- ========================================
-- 参考: section_type の説明
-- ========================================
/*
- cast_list: キャスト一覧（新人、本日の出勤など）
- banner: バナー画像セクション
- ranking: ランキングセクション
- content: コンテンツセクション（口コミ、動画、日記、履歴）
- external: 外部コンテンツ
- text_content: テキストコンテンツ（HTMLエディタ対応）
- embed_widget: 埋め込みウィジェット（iframe等）
- hero_text: トップバナー下のH1タイトル・導入文
*/

-- ========================================
-- 参考: config カラムの使用例（JSON形式）
-- ========================================
/*
text_content の場合:
{
  "html_content": "<div>...</div>"
}

embed_widget の場合:
{
  "embed_code": "<iframe src='...'></iframe>",
  "embed_height": "400"
}

hero_text の場合:
{
  "h1_title": "店舗名・キャッチコピー",
  "intro_text": "お店の説明文"
}
*/
