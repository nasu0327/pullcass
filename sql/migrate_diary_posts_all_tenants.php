<?php
/**
 * 全テナントDBにdiary_postsテーブルを作成するマイグレーションスクリプト
 * 
 * 実行方法:
 * php sql/migrate_diary_posts_all_tenants.php
 */

require_once __DIR__ . '/../www/includes/bootstrap.php';

echo "===========================================\n";
echo "写メ日記テーブル一括マイグレーション\n";
echo "===========================================\n\n";

// diary_postsテーブル作成SQL
$createTableSql = "
CREATE TABLE IF NOT EXISTS diary_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pd_id BIGINT NOT NULL COMMENT 'CityHeavenの投稿ID',
    cast_id INT COMMENT 'キャストID（cast_dataテーブル）',
    
    -- 投稿情報
    title VARCHAR(500) COMMENT 'タイトル',
    writer_name VARCHAR(100) NOT NULL COMMENT '投稿者名',
    posted_at DATETIME NOT NULL COMMENT '投稿日時',
    
    -- メディア情報
    thumb_url VARCHAR(500) COMMENT 'サムネイル画像URL',
    video_url VARCHAR(500) COMMENT '動画URL',
    poster_url VARCHAR(500) COMMENT '動画ポスター画像URL',
    has_video TINYINT(1) DEFAULT 0 COMMENT '動画有無',
    
    -- 本文
    html_body TEXT COMMENT '本文HTML',
    content_hash VARCHAR(64) COMMENT '本文ハッシュ値（重複チェック用）',
    
    -- メタ情報
    detail_url VARCHAR(500) COMMENT '詳細ページURL',
    is_my_girl_limited TINYINT(1) DEFAULT 0 COMMENT 'マイガール限定投稿',
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_pd_id (pd_id),
    INDEX idx_cast_id (cast_id),
    INDEX idx_posted_at (posted_at),
    INDEX idx_writer_name (writer_name),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (cast_id) REFERENCES cast_data(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='写メ日記投稿データ'
";

try {
    // プラットフォームDBから全テナントを取得
    $platformPdo = getPlatformDb();
    
    $stmt = $platformPdo->query("SELECT id, code, name, db_name FROM tenants WHERE is_active = 1 ORDER BY id");
    $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($tenants)) {
        echo "❌ アクティブなテナントが見つかりません。\n";
        exit(1);
    }
    
    echo "対象テナント数: " . count($tenants) . "\n\n";
    
    $successCount = 0;
    $errorCount = 0;
    $skippedCount = 0;
    
    foreach ($tenants as $tenant) {
        $tenantId = $tenant['id'];
        $tenantCode = $tenant['code'];
        $tenantName = $tenant['name'];
        $dbName = $tenant['db_name'];
        
        echo "-------------------------------------------\n";
        echo "テナント: {$tenantName} (ID: {$tenantId}, Code: {$tenantCode})\n";
        echo "DB: {$dbName}\n";
        
        try {
            // テナントDBに接続
            $tenantPdo = getTenantDb($tenantId);
            
            if (!$tenantPdo) {
                echo "❌ DB接続失敗\n";
                $errorCount++;
                continue;
            }
            
            // テーブルが既に存在するかチェック
            $stmt = $tenantPdo->query("SHOW TABLES LIKE 'diary_posts'");
            $exists = $stmt->fetch();
            
            if ($exists) {
                echo "⏭️  diary_postsテーブルは既に存在します（スキップ）\n";
                $skippedCount++;
                continue;
            }
            
            // テーブル作成
            $tenantPdo->exec($createTableSql);
            
            // 作成確認
            $stmt = $tenantPdo->query("SHOW TABLES LIKE 'diary_posts'");
            if ($stmt->fetch()) {
                echo "✅ diary_postsテーブル作成成功\n";
                $successCount++;
            } else {
                echo "❌ テーブル作成に失敗しました\n";
                $errorCount++;
            }
            
        } catch (PDOException $e) {
            echo "❌ エラー: " . $e->getMessage() . "\n";
            $errorCount++;
        }
    }
    
    echo "\n===========================================\n";
    echo "マイグレーション完了\n";
    echo "===========================================\n";
    echo "✅ 成功: {$successCount}件\n";
    echo "⏭️  スキップ: {$skippedCount}件\n";
    echo "❌ エラー: {$errorCount}件\n";
    echo "-------------------------------------------\n";
    
    if ($errorCount > 0) {
        exit(1);
    }
    
} catch (Exception $e) {
    echo "\n❌ 致命的エラー: " . $e->getMessage() . "\n";
    exit(1);
}
