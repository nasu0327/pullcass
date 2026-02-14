-- ============================================
-- 詳細確認SQL
-- ============================================

USE pullcass;

-- ============================================
-- 1. diary_postsテーブルの構造確認
-- ============================================
SHOW COLUMNS FROM diary_posts;

-- ============================================
-- 2. diary_scrape_settingsテーブルの構造確認
-- ============================================
SHOW COLUMNS FROM diary_scrape_settings;

-- ============================================
-- 3. diary_scrape_logsテーブルの構造確認
-- ============================================
SHOW COLUMNS FROM diary_scrape_logs;

-- ============================================
-- 4. diary_postsのインデックス確認
-- ============================================
SHOW INDEX FROM diary_posts;

-- ============================================
-- 5. 外部キー制約の確認
-- ============================================
SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = 'pullcass'
AND TABLE_NAME IN ('diary_posts', 'diary_scrape_settings', 'diary_scrape_logs')
AND REFERENCED_TABLE_NAME IS NOT NULL;

-- ============================================
-- 6. 機能フラグの確認
-- ============================================
SELECT 
    t.id AS tenant_id,
    t.name AS tenant_name,
    t.code AS tenant_code,
    tf.feature_code,
    tf.is_enabled
FROM tenants t
LEFT JOIN tenant_features tf ON t.id = tf.tenant_id
WHERE tf.feature_code = 'diary_scrape'
ORDER BY t.id;

-- ============================================
-- 7. テーブル情報の確認
-- ============================================
SELECT 
    TABLE_NAME,
    ENGINE,
    TABLE_COLLATION,
    TABLE_COMMENT
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'pullcass'
AND TABLE_NAME LIKE 'diary%'
ORDER BY TABLE_NAME;
