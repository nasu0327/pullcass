<?php
/**
 * 写メ日記スクレイピング - 動作確認テストページ
 */

require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

$pageTitle = '写メ日記スクレイピング - 動作確認';

$platformPdo = getPlatformDb();
$tenantId = $tenant['id'];

// テスト結果
$results = [];

// =====================================================
// テスト1: データベース接続確認
// =====================================================
$results['db_connection'] = [
    'name' => 'データベース接続確認',
    'status' => 'success',
    'message' => 'プラットフォームDBに接続成功'
];

// =====================================================
// テスト2: テーブル存在確認
// =====================================================
try {
    $tables = ['diary_posts', 'diary_scrape_settings', 'diary_scrape_logs'];
    $existingTables = [];
    
    foreach ($tables as $table) {
        $stmt = $platformPdo->query("SHOW TABLES LIKE '{$table}'");
        if ($stmt->fetch()) {
            $existingTables[] = $table;
        }
    }
    
    if (count($existingTables) === count($tables)) {
        $results['tables'] = [
            'name' => 'テーブル存在確認',
            'status' => 'success',
            'message' => '全テーブルが存在します: ' . implode(', ', $existingTables)
        ];
    } else {
        $results['tables'] = [
            'name' => 'テーブル存在確認',
            'status' => 'error',
            'message' => '一部のテーブルが存在しません。存在するテーブル: ' . implode(', ', $existingTables)
        ];
    }
} catch (Exception $e) {
    $results['tables'] = [
        'name' => 'テーブル存在確認',
        'status' => 'error',
        'message' => $e->getMessage()
    ];
}

// =====================================================
// テスト3: 設定確認
// =====================================================
try {
    $stmt = $platformPdo->prepare("SELECT * FROM diary_scrape_settings WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $settings = $stmt->fetch();
    
    if ($settings) {
        $results['settings'] = [
            'name' => '設定確認',
            'status' => 'success',
            'message' => '設定が存在します',
            'data' => [
                'ログインID' => $settings['cityheaven_login_id'] ?: '未設定',
                '店舗URL' => $settings['shop_url'] ?: '未設定',
                '自動取得' => $settings['is_enabled'] ? 'ON' : 'OFF',
            ]
        ];
    } else {
        $results['settings'] = [
            'name' => '設定確認',
            'status' => 'warning',
            'message' => '設定がまだ作成されていません'
        ];
    }
} catch (Exception $e) {
    $results['settings'] = [
        'name' => '設定確認',
        'status' => 'error',
        'message' => $e->getMessage()
    ];
}

// =====================================================
// テスト4: キャストデータ確認（プラットフォームDB内のtenant_casts）
// =====================================================
try {
    $stmt = $platformPdo->prepare("SELECT COUNT(*) as count FROM tenant_casts WHERE tenant_id = ? AND checked = 1");
    $stmt->execute([$tenantId]);
    $castCount = $stmt->fetch()['count'];
    
    if ($castCount > 0) {
        $results['tenant_casts'] = [
            'name' => 'キャストデータ確認',
            'status' => 'success',
            'message' => "tenant_castsテーブルにキャストデータが存在します",
            'data' => [
                'アクティブキャスト数' => $castCount . '人'
            ]
        ];
    } else {
        $results['tenant_casts'] = [
            'name' => 'キャストデータ確認',
            'status' => 'warning',
            'message' => 'アクティブなキャストが0人です。キャストデータを取得してから写メ日記スクレイピングを実行してください。',
            'data' => [
                'アクティブキャスト数' => '0人'
            ]
        ];
    }
} catch (Exception $e) {
    $results['tenant_casts'] = [
        'name' => 'キャストデータ確認',
        'status' => 'error',
        'message' => $e->getMessage()
    ];
}

// =====================================================
// テスト5: アップロードディレクトリ確認
// =====================================================
try {
    $uploadDir = __DIR__ . '/../../../uploads/diary/' . $tenantId;
    $requiredDirs = ['thumbs', 'images', 'videos', 'deco'];
    $existingDirs = [];
    $writableDirs = [];
    
    foreach ($requiredDirs as $dir) {
        $path = $uploadDir . '/' . $dir;
        if (is_dir($path)) {
            $existingDirs[] = $dir;
            if (is_writable($path)) {
                $writableDirs[] = $dir;
            }
        }
    }
    
    if (count($existingDirs) === count($requiredDirs) && count($writableDirs) === count($requiredDirs)) {
        $results['upload_dirs'] = [
            'name' => 'アップロードディレクトリ確認',
            'status' => 'success',
            'message' => '全ディレクトリが存在し、書き込み可能です',
            'data' => [
                'ディレクトリパス' => $uploadDir,
                '存在するディレクトリ' => implode(', ', $existingDirs)
            ]
        ];
    } else {
        $results['upload_dirs'] = [
            'name' => 'アップロードディレクトリ確認',
            'status' => 'warning',
            'message' => '一部のディレクトリが存在しないか、書き込み不可です',
            'data' => [
                'ディレクトリパス' => $uploadDir,
                '存在するディレクトリ' => implode(', ', $existingDirs),
                '書き込み可能' => implode(', ', $writableDirs)
            ]
        ];
    }
} catch (Exception $e) {
    $results['upload_dirs'] = [
        'name' => 'アップロードディレクトリ確認',
        'status' => 'error',
        'message' => $e->getMessage()
    ];
}

// =====================================================
// テスト6: cURL拡張確認
// =====================================================
if (extension_loaded('curl')) {
    $results['curl'] = [
        'name' => 'cURL拡張確認',
        'status' => 'success',
        'message' => 'cURL拡張が有効です'
    ];
} else {
    $results['curl'] = [
        'name' => 'cURL拡張確認',
        'status' => 'error',
        'message' => 'cURL拡張が無効です。PHPの設定を確認してください。'
    ];
}

// =====================================================
// テスト7: DOM拡張確認
// =====================================================
if (extension_loaded('dom')) {
    $results['dom'] = [
        'name' => 'DOM拡張確認',
        'status' => 'success',
        'message' => 'DOM拡張が有効です'
    ];
} else {
    $results['dom'] = [
        'name' => 'DOM拡張確認',
        'status' => 'error',
        'message' => 'DOM拡張が無効です。PHPの設定を確認してください。'
    ];
}

include __DIR__ . '/../includes/header.php';
?>

<style>
.container {
    max-width: 1000px;
    margin: 0 auto;
    padding: 20px;
}

.test-result {
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(20px);
    padding: 20px;
    border-radius: 15px;
    border: 1px solid rgba(255, 255, 255, 0.1);
    margin-bottom: 20px;
}

.test-result.success {
    border-left: 4px solid #28a745;
}

.test-result.warning {
    border-left: 4px solid #ffc107;
}

.test-result.error {
    border-left: 4px solid #dc3545;
}

.test-result h3 {
    margin: 0 0 10px 0;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 10px;
}

.test-result .status-icon {
    font-size: 1.2em;
}

.test-result .status-icon.success {
    color: #28a745;
}

.test-result .status-icon.warning {
    color: #ffc107;
}

.test-result .status-icon.error {
    color: #dc3545;
}

.test-result .message {
    color: rgba(255, 255, 255, 0.8);
    margin-bottom: 10px;
}

.test-result .data {
    background: rgba(0, 0, 0, 0.3);
    padding: 15px;
    border-radius: 8px;
    margin-top: 10px;
}

.test-result .data-item {
    display: flex;
    margin-bottom: 8px;
    color: rgba(255, 255, 255, 0.9);
}

.test-result .data-item:last-child {
    margin-bottom: 0;
}

.test-result .data-label {
    font-weight: bold;
    min-width: 150px;
    color: #27a3eb;
}

.summary {
    background: linear-gradient(135deg, rgba(39, 163, 235, 0.2), rgba(156, 39, 176, 0.2));
    padding: 25px;
    border-radius: 15px;
    margin-bottom: 30px;
    text-align: center;
}

.summary h2 {
    margin: 0 0 15px 0;
    color: #fff;
}

.summary-stats {
    display: flex;
    justify-content: center;
    gap: 30px;
    margin-top: 15px;
}

.summary-stat {
    text-align: center;
}

.summary-stat .number {
    font-size: 2em;
    font-weight: bold;
    margin-bottom: 5px;
}

.summary-stat .number.success {
    color: #28a745;
}

.summary-stat .number.warning {
    color: #ffc107;
}

.summary-stat .number.error {
    color: #dc3545;
}

.summary-stat .label {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.9em;
}

.btn {
    padding: 15px 30px;
    border: none;
    border-radius: 25px;
    cursor: pointer;
    font-size: 1rem;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.btn-primary {
    background: #27a3eb;
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(39, 163, 235, 0.3);
}
</style>

<div class="container">
    <div class="header">
        <h1>写メ日記スクレイピング - 動作確認</h1>
        <p>システムの動作状況をチェックします</p>
    </div>

    <?php
    $successCount = 0;
    $warningCount = 0;
    $errorCount = 0;
    
    foreach ($results as $result) {
        if ($result['status'] === 'success') $successCount++;
        elseif ($result['status'] === 'warning') $warningCount++;
        elseif ($result['status'] === 'error') $errorCount++;
    }
    ?>

    <div class="summary">
        <h2>テスト結果サマリー</h2>
        <div class="summary-stats">
            <div class="summary-stat">
                <div class="number success"><?= $successCount ?></div>
                <div class="label">成功</div>
            </div>
            <div class="summary-stat">
                <div class="number warning"><?= $warningCount ?></div>
                <div class="label">警告</div>
            </div>
            <div class="summary-stat">
                <div class="number error"><?= $errorCount ?></div>
                <div class="label">エラー</div>
            </div>
        </div>
    </div>

    <?php foreach ($results as $result): ?>
    <div class="test-result <?= $result['status'] ?>">
        <h3>
            <span class="status-icon <?= $result['status'] ?>">
                <?php if ($result['status'] === 'success'): ?>
                    ✅
                <?php elseif ($result['status'] === 'warning'): ?>
                    ⚠️
                <?php else: ?>
                    ❌
                <?php endif; ?>
            </span>
            <?= h($result['name']) ?>
        </h3>
        <div class="message">
            <?= h($result['message']) ?>
        </div>
        <?php if (!empty($result['data'])): ?>
        <div class="data">
            <?php foreach ($result['data'] as $label => $value): ?>
            <div class="data-item">
                <span class="data-label"><?= h($label) ?>:</span>
                <span class="data-value"><?= h($value) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <div style="text-align: center; margin-top: 30px;">
        <a href="index.php" class="btn btn-primary">
            ← 管理画面に戻る
        </a>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
