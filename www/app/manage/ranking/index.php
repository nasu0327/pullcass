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

// キャストデータの取得（統合テーブルから）
try {
    $sql = "SELECT id, name FROM tenant_casts WHERE tenant_id = ? AND checked = 1 ORDER BY sort_order ASC, name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tenantId]);
    $casts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 現在のランキングデータの取得
    $sql = "SELECT id, name, repeat_ranking, attention_ranking 
            FROM tenant_casts 
            WHERE tenant_id = ? AND (repeat_ranking IS NOT NULL OR attention_ranking IS NOT NULL) 
            ORDER BY repeat_ranking, attention_ranking";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tenantId]);
    $ranking_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // カラム存在チェックと簡易マイグレーション
    try {
        $pdo->query("SELECT display_count FROM tenant_ranking_config LIMIT 1");
    } catch (PDOException $e) {
        try {
            $pdo->exec("ALTER TABLE tenant_ranking_config ADD COLUMN display_count INT DEFAULT 10 COMMENT 'ランキング表示件数(3,5,10)'");
        } catch (PDOException $e2) {
            // カラム追加エラーは無視（競合などで既にできている場合など）
        }
    }

    try {
        $pdo->query("SELECT repeat_title FROM tenant_ranking_config LIMIT 1");
    } catch (PDOException $e) {
        try {
            $pdo->exec("ALTER TABLE tenant_ranking_config ADD COLUMN repeat_title VARCHAR(255) DEFAULT NULL COMMENT 'リピートランキング表示名', ADD COLUMN attention_title VARCHAR(255) DEFAULT NULL COMMENT '注目度ランキング表示名'");
        } catch (PDOException $e2) {}
    }

    try {
        $pdo->query("SELECT repeat_visible FROM tenant_ranking_config LIMIT 1");
    } catch (PDOException $e) {
        try {
            $pdo->exec("ALTER TABLE tenant_ranking_config ADD COLUMN repeat_visible TINYINT(1) DEFAULT 1 COMMENT 'リピートランキング表示フラグ', ADD COLUMN attention_visible TINYINT(1) DEFAULT 1 COMMENT '注目度ランキング表示フラグ'");
        } catch (PDOException $e2) {}
    }

    // 設定取得
    $ranking_day = '';
    $display_count = 10;
    $repeat_title = '';
    $attention_title = '';
    $repeat_visible = 1;
    $attention_visible = 1;
    
    $stmt = $pdo->prepare("SELECT display_count, repeat_title, attention_title, repeat_visible, attention_visible FROM tenant_ranking_config WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($config) {
        if (isset($config['display_count'])) {
            $display_count = (int)$config['display_count'];
        }
        $repeat_title = $config['repeat_title'] ?? '';
        $attention_title = $config['attention_title'] ?? '';
        $repeat_visible = isset($config['repeat_visible']) ? (int)$config['repeat_visible'] : 1;
        $attention_visible = isset($config['attention_visible']) ? (int)$config['attention_visible'] : 1;
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

<?php if (count($casts) === 0): ?>
    <div class="info-box" style="background: rgba(241, 196, 15, 0.15); border-color: rgba(241, 196, 15, 0.4);">
        <i class="fas fa-exclamation-triangle" style="color: #f1c40f;"></i>
        <span>キャストデータがありません。先にスクレイピングを実行してください。</span>
    </div>
<?php endif; ?>

<form id="rankingForm" method="post">
    <div class="form-container">
        <div class="form-group" style="text-align: center; margin-bottom: 25px;">
            <label style="margin-bottom: 15px; display: block;"><i class="fas fa-list-ol"></i> ランキング表示数</label>
            <div class="radio-group-center">
                <label class="radio-label <?php echo $display_count == 3 ? 'active' : ''; ?>">
                    <input type="radio" name="display_count" value="3" <?php echo $display_count == 3 ? 'checked' : ''; ?>
                        onchange="updateDisplayCount(3)"> 3位まで
                </label>
                <label class="radio-label <?php echo $display_count == 5 ? 'active' : ''; ?>">
                    <input type="radio" name="display_count" value="5" <?php echo $display_count == 5 ? 'checked' : ''; ?>
                        onchange="updateDisplayCount(5)"> 5位まで
                </label>
                <label class="radio-label <?php echo $display_count == 10 ? 'active' : ''; ?>">
                    <input type="radio" name="display_count" value="10" <?php echo $display_count == 10 ? 'checked' : ''; ?> onchange="updateDisplayCount(10)"> 10位まで
                </label>
            </div>
        </div>

            </div>
        </div>
        <div style="text-align: center; margin-bottom: 30px;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> 保存する
            </button>
        </div>

        <div class="ranking-container">
            <div class="ranking-column">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <label class="switch-label">表示設定</label>
                    <label class="switch">
                        <input type="checkbox" id="repeat_visible" name="repeat_visible" <?php echo $repeat_visible ? 'checked' : ''; ?> onchange="handleVisibilityChange(this)">
                        <span class="slider round"></span>
                    </label>
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display:block; margin-bottom:8px; color:rgba(255,255,255,0.8); font-size:0.9rem;">ランキング表示名</label>
                    <input type="text" name="repeat_title" class="title-input" value="<?php echo h($repeat_title); ?>" placeholder="例: リピートランキング">
                </div>
                <?php for ($i = 1; $i <= 10; $i++): ?>
                    <div class="ranking-row" data-rank="<?php echo $i; ?>">
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
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <label class="switch-label">表示設定</label>
                    <label class="switch">
                        <input type="checkbox" id="attention_visible" name="attention_visible" <?php echo $attention_visible ? 'checked' : ''; ?> onchange="handleVisibilityChange(this)">
                        <span class="slider round"></span>
                    </label>
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display:block; margin-bottom:8px; color:rgba(255,255,255,0.8); font-size:0.9rem;">ランキング表示名</label>
                    <input type="text" name="attention_title" class="title-input" value="<?php echo h($attention_title); ?>" placeholder="例: 注目度ランキング">
                </div>
                <?php for ($i = 1; $i <= 10; $i++): ?>
                    <div class="ranking-row" data-rank="<?php echo $i; ?>">
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

    .radio-group-center {
        display: flex;
        justify-content: center;
        gap: 15px;
    }

    .radio-label {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        background: rgba(255, 255, 255, 0.05);
        border: 2px solid rgba(255, 255, 255, 0.1);
        border-radius: 25px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .radio-label:hover {
        background: rgba(255, 255, 255, 0.1);
        border-color: var(--accent);
    }

    .radio-label.active {
        background: rgba(39, 163, 235, 0.2);
        border-color: var(--accent);
        color: white;
    }

    .radio-label input {
        display: none;
    }

    .title-input {
        width: 100%;
        padding: 10px 12px;
        background: rgba(255, 255, 255, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 8px;
        color: #ffffff;
        font-size: 0.95rem;
        transition: all 0.3s ease;
    }

    .title-input:focus {
        border-color: var(--accent);
        outline: none;
        box-shadow: 0 0 0 3px rgba(39, 163, 235, 0.1);
        background: rgba(255, 255, 255, 0.15);
    }

    /* スイッチボタンのスタイル */
    .switch {
        position: relative;
        display: inline-block;
        width: 50px;
        height: 26px;
    }

    .switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(255, 255, 255, 0.2);
        transition: .4s;
    }

    .slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 4px;
        bottom: 4px;
        background-color: white;
        transition: .4s;
    }

    input:checked + .slider {
        background-color: var(--accent);
    }

    input:focus + .slider {
        box-shadow: 0 0 1px var(--accent);
    }

    input:checked + .slider:before {
        transform: translateX(24px);
    }

    .slider.round {
        border-radius: 34px;
    }

    .slider.round:before {
        border-radius: 50%;
    }
    
    .switch-label {
        color: rgba(255,255,255,0.9);
        font-weight: bold;
    }
</style>

<script>
    function handleVisibilityChange(checkbox) {
        const repeatVisible = document.getElementById('repeat_visible').checked;
        const attentionVisible = document.getElementById('attention_visible').checked;

        if (!repeatVisible && !attentionVisible) {
            alert('両方の項目を非表示にする場合はトップページ編集より非表示にして下さい');
            checkbox.checked = true; // 元に戻す
        }
    }

    function updateDisplayCount(count) {
        // ラジオボタンの見た目更新
        document.querySelectorAll('.radio-label').forEach(label => {
            const radio = label.querySelector('input');
            if (radio.value == count) {
                label.classList.add('active');
                radio.checked = true;
            } else {
                label.classList.remove('active');
            }
        });

        // 行の表示切り替え
        document.querySelectorAll('.ranking-row').forEach(row => {
            const rank = parseInt(row.getAttribute('data-rank'));
            if (rank <= count) {
                row.style.display = 'flex';
            } else {
                row.style.display = 'none';
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('rankingForm');

        // 初期表示設定
        const initialCount = document.querySelector('input[name="display_count"]:checked').value;
        updateDisplayCount(initialCount);

        form.addEventListener('submit', function (e) {
            e.preventDefault();

            // 表示件数取得
            const displayCount = parseInt(document.querySelector('input[name="display_count"]:checked').value);

            // フォームデータの取得
            const formData = {
                display_count: displayCount,
                repeat_title: document.querySelector('input[name="repeat_title"]').value,
                attention_title: document.querySelector('input[name="attention_title"]').value,
                repeat_visible: document.getElementById('repeat_visible').checked ? 1 : 0,
                attention_visible: document.getElementById('attention_visible').checked ? 1 : 0,
                repeat_ranking: Array.from(document.querySelectorAll('select[name="repeat_ranking[]"]')).map(s => s.value),
                attention_ranking: Array.from(document.querySelectorAll('select[name="attention_ranking[]"]')).map(s => s.value),
                tenant: '<?php echo $tenantSlug; ?>'
            };

            // ========== バリデーション ==========
            const errors = [];

            // 1. 空欄チェック（リピートランキング） - 表示件数分だけチェック
            formData.repeat_ranking.forEach((val, i) => {
                if (i < displayCount) { // 表示範囲内のみチェック
                    if (!val || val === '') {
                        errors.push('リピートランキング：' + (i + 1) + '位のキャストが指定されてません');
                    }
                }
            });

            // 2. 空欄チェック（注目度ランキング） - 表示件数分だけチェック
            formData.attention_ranking.forEach((val, i) => {
                if (i < displayCount) { // 表示範囲内のみチェック
                    if (!val || val === '') {
                        errors.push('注目度ランキング：' + (i + 1) + '位のキャストが指定されてません');
                    }
                }
            });

            // 3. 重複チェック（リピートランキング） - 表示件数分だけチェック
            const repeatPositions = {};
            formData.repeat_ranking.forEach((castId, i) => {
                if (i < displayCount) { // 表示範囲内のみチェック
                    if (castId) {
                        if (!repeatPositions[castId]) {
                            repeatPositions[castId] = [];
                        }
                        repeatPositions[castId].push(i + 1);
                    }
                }
            });

            for (const castId in repeatPositions) {
                if (repeatPositions[castId].length > 1) {
                    errors.push('リピートランキング：' + repeatPositions[castId].join('位と') + '位に同じキャストが指定されてます');
                }
            }

            // 4. 重複チェック（注目度ランキング） - 表示件数分だけチェック
            const attentionPositions = {};
            formData.attention_ranking.forEach((castId, i) => {
                if (i < displayCount) { // 表示範囲内のみチェック
                    if (castId) {
                        if (!attentionPositions[castId]) {
                            attentionPositions[castId] = [];
                        }
                        attentionPositions[castId].push(i + 1);
                    }
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