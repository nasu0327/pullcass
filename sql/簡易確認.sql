-- ============================================
-- 簡易確認SQL
-- ============================================

-- 現在のデータベースを確認
SELECT DATABASE();

-- pullcassデータベースに切り替え
USE pullcass;

-- テーブル一覧を確認
SHOW TABLES;

-- diary関連のテーブルを確認
SHOW TABLES LIKE 'diary%';
