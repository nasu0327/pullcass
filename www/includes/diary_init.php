<?php
/**
 * 写メ日記スクレイピング機能 - 初期化処理
 * 新規テナント作成時に自動的にdiary_postsテーブルを作成
 */

/**
 * テナントDBにdiary_postsテーブルを作成
 * 
 * @param int $tenantId テナントID
 * @return bool 成功した場合true
 */
function initDiaryPostsTable($tenantId) {
    try {
        // テナントDBに接続
        $tenantPdo = getTenantDb($tenantId);
        
        if (!$tenantPdo) {
            error_log("diary_init: テナントDB接続失敗 (tenant_id: {$tenantId})");
            return false;
        }
        
        // diary_postsテーブル作成SQL
        $sql = "
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
        
        // テーブル作成実行
        $tenantPdo->exec($sql);
        
        // 作成確認
        $stmt = $tenantPdo->query("SHOW TABLES LIKE 'diary_posts'");
        if ($stmt->fetch()) {
            error_log("diary_init: diary_postsテーブル作成成功 (tenant_id: {$tenantId})");
            return true;
        } else {
            error_log("diary_init: diary_postsテーブル作成失敗 (tenant_id: {$tenantId})");
            return false;
        }
        
    } catch (PDOException $e) {
        error_log("diary_init: エラー (tenant_id: {$tenantId}): " . $e->getMessage());
        return false;
    }
}

/**
 * アップロードディレクトリを作成
 * 
 * @param int $tenantId テナントID
 * @return bool 成功した場合true
 */
function initDiaryUploadDirectories($tenantId) {
    try {
        $baseDir = __DIR__ . '/../uploads/diary/' . $tenantId;
        
        $directories = [
            $baseDir . '/thumbs',
            $baseDir . '/images',
            $baseDir . '/videos',
            $baseDir . '/deco',
        ];
        
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0755, true)) {
                    error_log("diary_init: ディレクトリ作成失敗: {$dir}");
                    return false;
                }
            }
        }
        
        error_log("diary_init: アップロードディレクトリ作成成功 (tenant_id: {$tenantId})");
        return true;
        
    } catch (Exception $e) {
        error_log("diary_init: ディレクトリ作成エラー (tenant_id: {$tenantId}): " . $e->getMessage());
        return false;
    }
}

/**
 * 写メ日記機能の初期化（テーブル作成 + ディレクトリ作成）
 * 
 * @param int $tenantId テナントID
 * @return array ['table' => bool, 'directories' => bool]
 */
function initDiaryScrapeFeature($tenantId) {
    $results = [
        'table' => false,
        'directories' => false,
    ];
    
    // テーブル作成
    $results['table'] = initDiaryPostsTable($tenantId);
    
    // ディレクトリ作成
    $results['directories'] = initDiaryUploadDirectories($tenantId);
    
    return $results;
}
