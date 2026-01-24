<?php
/**
 * 店舗管理画面 - ランキング管理
 */

require_once __DIR__ . '/../../../includes/bootstrap.php';

// テナント認証
require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

$pdo = getPlatformDb();
$error = '';
$success = '';

// アクティブソースを取得
$activeSource = 'ekichika'; // デフォルト
$stmt = $pdo->prepare("SELECT config_value FROM tenant_scraping_config WHERE tenant_id = ? AND config_key = 'active_source'");
$stmt->execute([$tenantId]);
$result = $stmt->fetchColumn();
if ($result) {
    $activeSource = $result;
}

// ソーステーブル名を決定
$sourceTableMap = [
    'ekichika' => 'tenant_cast_data_ekichika',
    'heaven' => 'tenant_cast_data_heaven',
    'dto' => 'tenant_cast_data_dto'
];
$castTable = $sourceTableMap[$activeSource] ?? 'tenant_cast_data_ekichika';

// キャストデータの取得
try {
    $sql = "SELECT id, name FROM {$castTable} WHERE tenant_id = ? AND checked = 1 ORDER BY sort_order ASC, name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tenantId]);
    $casts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 現在のランキングデータの取得
    $sql = "SELECT id, name, repeat_ranking, attention_ranking 
            FROM {$castTable} 
            WHERE tenant_id = ? AND (repeat_ranking IS NOT NULL OR attention_ranking IS NOT NULL) 
            ORDER BY repeat_ranking, attention_ranking";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tenantId]);
    $ranking_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 更新日の取得
    $ranking_day = '';
    $stmt = $pdo->prepare("SELECT ranking_day FROM tenant_ranking_config WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $result = $stmt->fetchColumn();
    if ($result) {
        $ranking_day = $result;
    }

    // ランキングデータを配列に変換
    $repeat_ranking = [];
    $attention_ranking = [];

    foreach ($ranking_data as $data) {
        if ($data['repeat_ranking'] !== null) {
            $repeat_ranking[$data['repeat_ranking'] - 1] = $data['id'];
        }
        if ($data['attention_ranking'] !== null) {
            $attention_ranking[$data['attention_ranking'] - 1] = $data['id'];
        }
    }

} catch (PDOException $e) {
    error_log('ranking DB error: ' . $e->getMessage());
    $error = 'データの取得に失敗しました。';
    $casts = [];
    $repeat_ranking = [];
    $attention_ranking = [];
    $ranking_day = '';
}

// 成功メッセージ
if (isset($_GET['success'])) {
    $success = 'ランキングを更新しました。';
}

// ソース名の表示用
$sourceNames = [
    'ekichika' => '駅ちか',
    'heaven' => 'ヘブンネット',
    'dto' => 'デリヘルタウン'
];
$activeSourceName = $sourceNames[$activeSource] ?? $activeSource;

// ヘッダー読み込み
$pageTitle = 'ランキング管理';
require_once __DIR__ . '/../includes/header.php';
?>

<?php
require_once __DIR__ . '/../includes/breadcrumb.php';
$breadcrumbs = [
    ['label' => 'ホーム', 'url' => '/app/manage/?tenant=' . $tenantSlug, 'icon' => 'fas fa-home'],
    ['label' => 'ランキング管理']
];
renderBreadcrumb($breadcrumbs);
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-trophy"></i> ランキング管理</h1>
        <p>リピートランキング・注目度ランキングの設定</p>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo h($error); ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?php echo h($success); ?>
    </div>
<?php endif; ?>

<div class="info-box">
    <i class="fas fa-info-circle"></i>
    <span>現在のデータソース: <strong>
            <?php echo h($activeSourceName); ?>
        </strong>（
        <?php echo count($casts); ?>人）
    </span>
</div>

<form id="rankingForm" method="post">
    <div class="form-container">
        <div class="form-group" style="text-align: center;">
            <label for="update_date"><i class="fas fa-calendar-alt"></i> 更新日</label>
            <input type="text" id="update_date" name="update_date" value="<?php echo h($ranking_day); ?>"
                placeholder="例: 2024年1月1日更新">
        </div>
        <div style="text-align: center; margin-bottom: 30px;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> 保存する
            </button>
        </div>

        <div class="ranking-container">
            <div class="ranking-column">
                <h2><i class="fas fa-redo"></i> リピートランキング</h2>
                <?php for ($i = 1; $i <= 10; $i++): ?>
                    <div class="ranking-row">
                        <label>
                            <?php echo $i; ?>位
                        </label>
                        <select name="repeat_ranking[]" class="cast-select">
                            <option value="">キャストを選択▼</option>
                            <?php foreach ($casts as $cast): ?>
                                <option value="<?php echo $cast['id']; ?>" <?php echo (isset($repeat_ranking[$i - 1]) && $repeat_ranking[$i - 1] == $cast['id']) ? 'selected' : ''; ?>>
                                    <?php echo h($cast['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endfor; ?>
            </div>
            <div class="ranking-column">
                <h2><i class="fas fa-star"></i> 注目度ランキング</h2>
                <?php for ($i = 1; $i <= 10; $i++): ?>
                    <div class="ranking-row">
                        <label>
                            <?php echo $i; ?>位
                        </label>
                        <select name="attention_ranking[]" class="cast-select">
                            <option value="">キャストを選択▼</option>
                            <?php foreach ($casts as $cast): ?>
                                <option value="<?php echo $cast['id']; ?>" <?php echo (isset($attention_ranking[$i - 1]) && $attention_ranking[$i - 1] == $cast['id']) ? 'selected' : ''; ?>>
                                    <?php echo h($cast['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</form>

<style>
    .info-box {
        background: rgba(39, 163, 235, 0.15);
        border: 1px solid rgba(39, 163, 235, 0.4);
        border-radius: 12px;
        padding: 15px 20px;
        margin-bottom: 25px;
        color: white;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .info-box i {
        color: var(--accent);
        font-size: 18px;
    }

    .info-box strong {
        color: var(--accent);
    }

    #update_date {
        padding: 12px 20px;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 10px;
        color: #ffffff;
        font-size: 1rem;
        transition: all 0.3s ease;
        width: 250px;
        text-align: center;
    }

    #update_date:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(39, 163, 235, 0.1);
    }

    #update_date::placeholder {
        color: rgba(255, 255, 255, 0.5);
    }

    .ranking-container {
        display: flex;
        gap: 30px;
        margin: 20px 0;
    }

    @media (max-width: 768px) {
        .ranking-container {
            flex-direction: column;
            gap: 20px;
        }
    }

    .ranking-column {
        flex: 1;
        background: rgba(255, 255, 255, 0.05);
        padding: 25px;
        border-radius: 15px;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .ranking-column h2 {
        color: var(--accent);
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid rgba(39, 163, 235, 0.3);
        font-size: 1.2rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .ranking-row {
        display: flex;
        align-items: center;
        margin-bottom: 12px;
        padding: 10px 12px;
        background: rgba(255, 255, 255, 0.08);
        border-radius: 10px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        transition: all 0.3s ease;
    }

    .ranking-row:hover {
        background: rgba(255, 255, 255, 0.12);
        transform: translateX(3px);
    }

    .ranking-row label {
        width: 50px;
        font-weight: bold;
        color: var(--accent);
        font-size: 0.95rem;
        margin-bottom: 0;
    }

    .cast-select {
        flex: 1;
        padding: 10px 12px;
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.1);
        font-size: 14px;
        color: #ffffff;
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .cast-select:focus {
        border-color: var(--accent);
        outline: none;
        box-shadow: 0 0 0 3px rgba(39, 163, 235, 0.1);
    }

    .cast-select option {
        background: #2d2d2d;
        color: #ffffff;
        padding: 10px;
    }

    .btn-primary {
        background: var(--accent);
        color: white;
        border: none;
        padding: 14px 30px;
        border-radius: 25px;
        cursor: pointer;
        font-size: 1rem;
        font-weight: 600;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(39, 163, 235, 0.3);
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('rankingForm');

        form.addEventListener('submit', function (e) {
            e.preventDefault();

            // フォームデータの取得
            const formData = {
                update_date: document.getElementById('update_date').value,
                repeat_ranking: Array.from(document.querySelectorAll('select[name="repeat_ranking[]"]')).map(s => s.value),
                attention_ranking: Array.from(document.querySelectorAll('select[name="attention_ranking[]"]')).map(s => s.value),
                tenant: '<?php echo $tenantSlug; ?>'
            };

            // ========== バリデーション ==========
            const errors = [];

            // 1. 空欄チェック（リピートランキング）
            formData.repeat_ranking.forEach((val, i) => {
                if (!val || val === '') {
                    errors.push('リピートランキング：' + (i + 1) + '位のキャストが指定されてません');
                }
            });

            // 2. 空欄チェック（注目度ランキング）
            formData.attention_ranking.forEach((val, i) => {
                if (!val || val === '') {
                    errors.push('注目度ランキング：' + (i + 1) + '位のキャストが指定されてません');
                }
            });

            // 3. 重複チェック（リピートランキング）
            const repeatPositions = {};
            formData.repeat_ranking.forEach((castId, i) => {
                if (castId) {
                    if (!repeatPositions[castId]) {
                        repeatPositions[castId] = [];
                    }
                    repeatPositions[castId].push(i + 1);
                }
            });

            for (const castId in repeatPositions) {
                if (repeatPositions[castId].length > 1) {
                    errors.push('リピートランキング：' + repeatPositions[castId].join('位と') + '位に同じキャストが指定されてます');
                }
            }

            // 4. 重複チェック（注目度ランキング）
            const attentionPositions = {};
            formData.attention_ranking.forEach((castId, i) => {
                if (castId) {
                    if (!attentionPositions[castId]) {
                        attentionPositions[castId] = [];
                    }
                    attentionPositions[castId].push(i + 1);
                }
            });

            for (const castId in attentionPositions) {
                if (attentionPositions[castId].length > 1) {
                    errors.push('注目度ランキング：' + attentionPositions[castId].join('位と') + '位に同じキャストが指定されてます');
                }
            }

            // エラーがある場合は全て表示
            if (errors.length > 0) {
                let errorMessage = '以下のエラーを修正してください：\n\n';
                errors.forEach(err => {
                    errorMessage += '• ' + err + '\n';
                });
                alert(errorMessage);
                return false;
            }

            // ========== バリデーション通過 ==========

            // 送信ボタンを無効化
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 保存中...';

            // AJAXで送信
            fetch('update.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(formData)
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        window.location.href = 'index.php?tenant=<?php echo urlencode($tenantSlug); ?>&success=1';
                    } else {
                        alert('エラーが発生しました: ' + (data.message || '不明なエラー'));
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('エラーが発生しました');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                });
        });
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>