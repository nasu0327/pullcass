-- ============================================
-- 写メ日記スクレイピング機能を有効化
-- ============================================

USE pullcass_platform;

-- --------------------------------------------
-- 1. 特定のテナントで機能を有効化する場合
-- --------------------------------------------

-- 例: テナントID=1で有効化
-- UPDATE tenant_features 
-- SET is_enabled = 1 
-- WHERE tenant_id = 1 AND feature_code = 'diary_scrape';

-- --------------------------------------------
-- 2. 全テナントで機能を有効化する場合
-- --------------------------------------------

-- UPDATE tenant_features 
-- SET is_enabled = 1 
-- WHERE feature_code = 'diary_scrape';

-- --------------------------------------------
-- 3. 機能フラグの確認
-- --------------------------------------------

SELECT 
    t.id AS tenant_id,
    t.code AS tenant_code,
    t.name AS tenant_name,
    tf.feature_code,
    tf.is_enabled,
    tf.created_at
FROM tenants t
LEFT JOIN tenant_features tf ON t.id = tf.tenant_id AND tf.feature_code = 'diary_scrape'
WHERE t.is_active = 1
ORDER BY t.id;

-- --------------------------------------------
-- 4. 写メ日記設定の確認
-- --------------------------------------------

SELECT 
    dss.id,
    t.name AS tenant_name,
    dss.cityheaven_login_id,
    dss.shop_url,
    dss.is_enabled,
    dss.scrape_interval,
    dss.last_executed_at,
    dss.last_execution_status,
    dss.total_posts_scraped
FROM diary_scrape_settings dss
JOIN tenants t ON dss.tenant_id = t.id
ORDER BY dss.tenant_id;
