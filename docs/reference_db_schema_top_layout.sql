-- ========================================
-- 参考サイト（豊満倶楽部）のトップレイアウト管理機能
-- データベース構造定義
-- ========================================
-- 作成日: 2026-01-18
-- 用途: pullcass実装時の参考資料
-- ========================================

-- ========================================
-- 1. top_layout_sections（セクション管理テーブル）
-- ========================================
CREATE TABLE `top_layout_sections` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'セクションID',
  `section_key` varchar(50) NOT NULL COMMENT 'セクション識別子（英数字・アンダースコアのみ）',
  `section_type` enum('cast_list','banner','ranking','content','external','text_content','embed_widget','hero_text') NOT NULL COMMENT 'セクションタイプ',
  `default_column` enum('left','right') NOT NULL COMMENT 'デフォルト配置カラム',
  `title_en` varchar(100) NOT NULL COMMENT '英語タイトル',
  `title_ja` varchar(100) NOT NULL COMMENT '日本語タイトル',
  `admin_title` varchar(100) NOT NULL COMMENT '管理画面用タイトル',
  `is_visible` tinyint(1) DEFAULT 1 COMMENT '表示/非表示（1=表示, 0=非表示）',
  `pc_left_order` int(11) DEFAULT NULL COMMENT 'PC左カラム表示順（NULLの場合は左カラムに非表示）',
  `pc_right_order` int(11) DEFAULT NULL COMMENT 'PC右カラム表示順（NULLの場合は右カラムに非表示）',
  `mobile_order` int(11) NOT NULL DEFAULT 999 COMMENT 'スマホ表示順',
  `status` enum('draft','published') DEFAULT 'published' COMMENT '公開状態（draft=下書き, published=公開中）',
  `config` longtext DEFAULT NULL COMMENT 'セクション固有の設定（JSON形式）',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT '作成日時',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '更新日時',
  `mobile_visible` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'スマホ表示/非表示（1=表示, 0=非表示）',
  PRIMARY KEY (`id`),
  UNIQUE KEY `section_key` (`section_key`),
  KEY `idx_section_type` (`section_type`),
  KEY `idx_is_visible` (`is_visible`),
  KEY `idx_pc_left_order` (`pc_left_order`),
  KEY `idx_pc_right_order` (`pc_right_order`),
  KEY `idx_mobile_order` (`mobile_order`),
  KEY `idx_status` (`status`),
  KEY `idx_mobile_visible` (`mobile_visible`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='トップページセクション管理';

-- ========================================
-- 2. top_layout_sections_published（公開済みセクション）
-- ========================================
-- 構造は top_layout_sections と完全に同一
CREATE TABLE `top_layout_sections_published` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'セクションID',
  `section_key` varchar(50) NOT NULL COMMENT 'セクション識別子（英数字・アンダースコアのみ）',
  `section_type` enum('cast_list','banner','ranking','content','external','text_content','embed_widget','hero_text') NOT NULL COMMENT 'セクションタイプ',
  `default_column` enum('left','right') NOT NULL COMMENT 'デフォルト配置カラム',
  `title_en` varchar(100) NOT NULL COMMENT '英語タイトル',
  `title_ja` varchar(100) NOT NULL COMMENT '日本語タイトル',
  `admin_title` varchar(100) NOT NULL COMMENT '管理画面用タイトル',
  `is_visible` tinyint(1) DEFAULT 1 COMMENT '表示/非表示（1=表示, 0=非表示）',
  `pc_left_order` int(11) DEFAULT NULL COMMENT 'PC左カラム表示順（NULLの場合は左カラムに非表示）',
  `pc_right_order` int(11) DEFAULT NULL COMMENT 'PC右カラム表示順（NULLの場合は右カラムに非表示）',
  `mobile_order` int(11) NOT NULL DEFAULT 999 COMMENT 'スマホ表示順',
  `status` enum('draft','published') DEFAULT 'published' COMMENT '公開状態（draft=下書き, published=公開中）',
  `config` longtext DEFAULT NULL COMMENT 'セクション固有の設定（JSON形式）',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT '作成日時',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '更新日時',
  `mobile_visible` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'スマホ表示/非表示（1=表示, 0=非表示）',
  PRIMARY KEY (`id`),
  UNIQUE KEY `section_key` (`section_key`),
  KEY `idx_section_type` (`section_type`),
  KEY `idx_is_visible` (`is_visible`),
  KEY `idx_pc_left_order` (`pc_left_order`),
  KEY `idx_pc_right_order` (`pc_right_order`),
  KEY `idx_mobile_order` (`mobile_order`),
  KEY `idx_status` (`status`),
  KEY `idx_mobile_visible` (`mobile_visible`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='トップページセクション管理（公開済み）';

-- ========================================
-- 3. top_layout_sections_saved（下書き保存セクション）
-- ========================================
-- 構造は top_layout_sections と完全に同一
CREATE TABLE `top_layout_sections_saved` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'セクションID',
  `section_key` varchar(50) NOT NULL COMMENT 'セクション識別子（英数字・アンダースコアのみ）',
  `section_type` enum('cast_list','banner','ranking','content','external','text_content','embed_widget','hero_text') NOT NULL COMMENT 'セクションタイプ',
  `default_column` enum('left','right') NOT NULL COMMENT 'デフォルト配置カラム',
  `title_en` varchar(100) NOT NULL COMMENT '英語タイトル',
  `title_ja` varchar(100) NOT NULL COMMENT '日本語タイトル',
  `admin_title` varchar(100) NOT NULL COMMENT '管理画面用タイトル',
  `is_visible` tinyint(1) DEFAULT 1 COMMENT '表示/非表示（1=表示, 0=非表示）',
  `pc_left_order` int(11) DEFAULT NULL COMMENT 'PC左カラム表示順（NULLの場合は左カラムに非表示）',
  `pc_right_order` int(11) DEFAULT NULL COMMENT 'PC右カラム表示順（NULLの場合は右カラムに非表示）',
  `mobile_order` int(11) NOT NULL DEFAULT 999 COMMENT 'スマホ表示順',
  `status` enum('draft','published') DEFAULT 'published' COMMENT '公開状態（draft=下書き, published=公開中）',
  `config` longtext DEFAULT NULL COMMENT 'セクション固有の設定（JSON形式）',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT '作成日時',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT '更新日時',
  `mobile_visible` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'スマホ表示/非表示（1=表示, 0=非表示）',
  PRIMARY KEY (`id`),
  UNIQUE KEY `section_key` (`section_key`),
  KEY `idx_section_type` (`section_type`),
  KEY `idx_is_visible` (`is_visible`),
  KEY `idx_pc_left_order` (`pc_left_order`),
  KEY `idx_pc_right_order` (`pc_right_order`),
  KEY `idx_mobile_order` (`mobile_order`),
  KEY `idx_status` (`status`),
  KEY `idx_mobile_visible` (`mobile_visible`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='トップページセクション管理（下書き保存）';

-- ========================================
-- 4. top_layout_banners（バナー管理テーブル）
-- ========================================
CREATE TABLE `top_layout_banners` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'バナーID',
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
  KEY `idx_section_id` (`section_id`),
  KEY `idx_display_order` (`display_order`),
  KEY `idx_is_visible` (`is_visible`),
  CONSTRAINT `fk_top_layout_banners_section` FOREIGN KEY (`section_id`) REFERENCES `top_layout_sections` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='トップページバナー管理';

-- ========================================
-- デフォルトセクション初期データ（参考）
-- ========================================
/*
左カラム（pc_left_order）:
1. new_cast (新人) - cast_list
2. cashback_banner (キャッシュバック) - banner
3. today_cast (本日の出勤) - cast_list
4. reviews (口コミ) - content
5. videos (動画) - content
6. repeat_ranking (リピートランキング) - ranking
7. attention_ranking (注目度ランキング) - ranking

右カラム（pc_right_order）:
1. comic_banner (体験漫画) - banner
2. hotel_list_banner (ホテルリスト) - banner
3. diary (写メ日記) - content
4. history (閲覧履歴) - content

特殊セクション:
- hero_text (H1タイトル・導入文) - hero_text
  ※ pc_left_order, pc_right_order が共にNULL（固定配置）
*/

-- ========================================
-- section_type 詳細
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
-- config カラムの使用例（JSON形式）
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
  "h1_title": "福岡・博多のぽっちゃり風俗デリヘル「豊満倶楽部」",
  "intro_text": "福岡・博多エリアの巨乳ぽっちゃり専門風俗デリヘル。"
}
*/

-- ========================================
-- 注意事項
-- ========================================
/*
1. 3つのセクションテーブル（編集中・公開済み・下書き保存）は
   完全に同じ構造を持ち、相互にコピーされる

2. バナーテーブルは section_id で top_layout_sections を参照
   ON DELETE CASCADE により、セクション削除時に関連バナーも自動削除

3. mobile_visible カラムは後から追加された
   （toggle_visibility.php で動的に ALTER TABLE される）

4. section_key はユニークキーであり、重複不可

5. pc_left_order と pc_right_order は排他的
   （どちらか一方のみに値が入る）

6. mobile_order は全セクション共通で、左カラム→右カラムの順に
   自動採番される（デフォルト999）
*/
