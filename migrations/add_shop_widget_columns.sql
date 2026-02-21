-- ============================================
-- 店舗用ウィジェットコード保存用カラムを追加
-- テナントのトップページで写メ日記・口コミのスクレイピングがOFFの場合に
-- 店舗レベルのウィジェットコードを表示するため
-- 実行日: 2026-02-21
-- ============================================

USE pullcass;

ALTER TABLE tenants
ADD COLUMN shop_diary_widget_code TEXT DEFAULT NULL COMMENT '店舗用写メ日記ウィジェットコード（トップページ表示用）',
ADD COLUMN shop_review_widget_code TEXT DEFAULT NULL COMMENT '店舗用口コミウィジェットコード（トップページ表示用）';

SELECT '店舗用ウィジェットカラムの追加が完了しました。' AS message;
