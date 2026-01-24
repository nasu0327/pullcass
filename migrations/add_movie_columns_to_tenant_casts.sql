-- ========================================
-- tenant_castsテーブルに動画関連カラムを追加
-- ========================================
-- 作成日: 2026-01-24
-- 目的: 動画管理機能の実装
-- ========================================

-- 動画ファイルパス (動画1)
ALTER TABLE tenant_casts
ADD COLUMN IF NOT EXISTS movie_1 VARCHAR(255) DEFAULT NULL COMMENT '動画1パス' AFTER img5;

-- 動画1サムネイルパス
ALTER TABLE tenant_casts
ADD COLUMN IF NOT EXISTS movie_1_thumbnail VARCHAR(255) DEFAULT NULL COMMENT '動画1サムネイルパス' AFTER movie_1;

-- 動画1ミニ版パス (軽量版など)
ALTER TABLE tenant_casts
ADD COLUMN IF NOT EXISTS movie_1_mini VARCHAR(255) DEFAULT NULL COMMENT '動画1ミニ版パス' AFTER movie_1_thumbnail;

-- 動画1 SEO用サムネイルパス
ALTER TABLE tenant_casts
ADD COLUMN IF NOT EXISTS movie_1_seo_thumbnail VARCHAR(255) DEFAULT NULL COMMENT '動画1SEO用サムネイルパス' AFTER movie_1_mini;

-- 動画ファイルパス (動画2)
ALTER TABLE tenant_casts
ADD COLUMN IF NOT EXISTS movie_2 VARCHAR(255) DEFAULT NULL COMMENT '動画2パス' AFTER movie_1_seo_thumbnail;

-- 動画2サムネイルパス
ALTER TABLE tenant_casts
ADD COLUMN IF NOT EXISTS movie_2_thumbnail VARCHAR(255) DEFAULT NULL COMMENT '動画2サムネイルパス' AFTER movie_2;

-- 動画2ミニ版パス
ALTER TABLE tenant_casts
ADD COLUMN IF NOT EXISTS movie_2_mini VARCHAR(255) DEFAULT NULL COMMENT '動画2ミニ版パス' AFTER movie_2_thumbnail;

-- 動画2 SEO用サムネイルパス
ALTER TABLE tenant_casts
ADD COLUMN IF NOT EXISTS movie_2_seo_thumbnail VARCHAR(255) DEFAULT NULL COMMENT '動画2SEO用サムネイルパス' AFTER movie_2_mini;

-- インデックス追加（検索高速化のため動画有無フラグとしても使えるように）
-- 必要に応じて追加
