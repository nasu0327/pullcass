-- ============================================
-- 再確認SQL（1つずつ実行）
-- ============================================

-- クエリ1: データベース選択
USE pullcass;

-- クエリ2: テーブル一覧
SHOW TABLES LIKE 'diary%';

-- クエリ3: diary_postsの構造（別の方法）
DESC diary_posts;

-- クエリ4: diary_scrape_settingsの構造
DESC diary_scrape_settings;

-- クエリ5: diary_scrape_logsの構造
DESC diary_scrape_logs;
