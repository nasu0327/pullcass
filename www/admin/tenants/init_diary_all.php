<?php
/**
 * 既存の全テナントに写メ日記機能を初期化
 * ※ diary_postsテーブルはプラットフォームDBに一元管理のため、
 *    ここではアップロードディレクトリの作成のみ行う
 * 
 * 実行方法:
 * ブラウザで /admin/tenants/init_diary_all.php にアクセス
 * または
 * php www/admin/tenants/init_diary_all.php
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../../includes/diary_init.php';

// CLI実行またはスーパー管理者のみ
if (php_sapi_name() !== 'cli') {
    requireSuperAdminLogin();
}

$isWeb = php_sapi_name() !== 'cli';

if ($isWeb) {
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="ja"><head><meta charset="UTF-8"><title>写メ日記機能一括初期化</title>';
    echo '<style>body{font-family:monospace;padding:20px;background:#1a1a1a;color:#0f0;}';
    echo '.success{color:#0f0;}.error{color:#f00;}.skip{color:#ff0;}</style></head><body>';
    echo '<h1>写メ日記機能一括初期化</h1><hr>';
}

function output($message, $type = 'info') {
    global $isWeb;
    
    if ($isWeb) {
        $class = $type === 'success' ? 'success' : ($type === 'error' ? 'error' : ($type === 'skip' ? 'skip' : ''));
        echo "<div class='{$class}'>" . htmlspecialchars($message) . "</div>";
        flush();
    } else {
        echo $message . PHP_EOL;
    }
}

output("===========================================");
output("写メ日記機能一括初期化");
output("===========================================");
output("");

try {
    // プラットフォームDBから全テナントを取得
    $platformPdo = getPlatformDb();
    
    $stmt = $platformPdo->query("SELECT id, code, name FROM tenants WHERE is_active = 1 ORDER BY id");
    $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($tenants)) {
        output("❌ アクティブなテナントが見つかりません。", 'error');
        exit(1);
    }
    
    output("対象テナント数: " . count($tenants));
    output("※ diary_postsテーブルはプラットフォームDBに一元管理");
    output("");
    
    // プラットフォームDBにdiary_postsテーブルが存在するか確認
    $stmt = $platformPdo->query("SHOW TABLES LIKE 'diary_posts'");
    if ($stmt->fetch()) {
        output("✅ diary_postsテーブル: プラットフォームDBに存在します", 'success');
    } else {
        output("❌ diary_postsテーブル: プラットフォームDBに存在しません。SQLを実行してください。", 'error');
    }
    output("");
    
    $stats = [
        'dir_success' => 0,
        'dir_skip' => 0,
        'dir_error' => 0,
    ];
    
    foreach ($tenants as $tenant) {
        $tenantId = $tenant['id'];
        $tenantCode = $tenant['code'];
        $tenantName = $tenant['name'];
        
        output("-------------------------------------------");
        output("テナント: {$tenantName} (ID: {$tenantId}, Code: {$tenantCode})");
        
        // ディレクトリ作成
        try {
            $baseDir = __DIR__ . '/../../uploads/diary/' . $tenantId;
            
            if (is_dir($baseDir)) {
                output("⏭️  アップロードディレクトリは既に存在します（スキップ）", 'skip');
                $stats['dir_skip']++;
            } else {
                $result = initDiaryUploadDirectories($tenantId);
                
                if ($result) {
                    output("✅ アップロードディレクトリ作成成功", 'success');
                    $stats['dir_success']++;
                } else {
                    output("❌ アップロードディレクトリ作成失敗", 'error');
                    $stats['dir_error']++;
                }
            }
            
        } catch (Exception $e) {
            output("❌ ディレクトリ作成エラー: " . $e->getMessage(), 'error');
            $stats['dir_error']++;
        }
        
        output("");
    }
    
    output("===========================================");
    output("初期化完了");
    output("===========================================");
    output("");
    output("【ディレクトリ作成】");
    output("✅ 成功: {$stats['dir_success']}件", 'success');
    output("⏭️  スキップ: {$stats['dir_skip']}件", 'skip');
    output("❌ エラー: {$stats['dir_error']}件", 'error');
    output("-------------------------------------------");
    
    if ($stats['dir_error'] > 0) {
        output("");
        output("⚠️  エラーが発生しました。ログを確認してください。", 'error');
    }
    
    if ($isWeb) {
        echo '<hr><p><a href="/admin/tenants/">← テナント一覧に戻る</a></p>';
        echo '</body></html>';
    }
    
} catch (Exception $e) {
    output("");
    output("❌ 致命的エラー: " . $e->getMessage(), 'error');
    
    if ($isWeb) {
        echo '</body></html>';
    }
    
    exit(1);
}
