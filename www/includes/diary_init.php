<?php
/**
 * 写メ日記スクレイピング機能 - 初期化処理
 * 新規テナント作成時にアップロードディレクトリを自動作成
 * 
 * ※ diary_postsテーブルはプラットフォームDB（pullcass）に一元管理
 *    テナント別のテーブル作成は不要
 */

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
 * 写メ日記機能の初期化（ディレクトリ作成）
 * 
 * ※ diary_postsテーブルはプラットフォームDBに存在するため、
 *    テナント個別のテーブル作成は不要
 * 
 * @param int $tenantId テナントID
 * @return array ['table' => bool, 'directories' => bool]
 */
function initDiaryScrapeFeature($tenantId) {
    $results = [
        'table' => true, // プラットフォームDBに既に存在
        'directories' => false,
    ];
    
    // ディレクトリ作成
    $results['directories'] = initDiaryUploadDirectories($tenantId);
    
    return $results;
}
