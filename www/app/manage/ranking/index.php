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

// アクティブなテーブル名を取得
$source = 'ekichika';
try {
    $stmt = $pdo->prepare("SELECT config_value FROM tenant_scraping_config WHERE tenant_id = ? AND config_key = 'active_source'");
    $stmt->execute([$tenantId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && $row['config_value']) {
        $source = $row['config_value'];
    }
} catch (Exception $e) {
}

$validSources = ['ekichika', 'heaven', 'dto'];
if (!in_array($source, $validSources)) {
    $source = 'ekichika';
}
$tableName = "tenant_cast_data_{$source}";

// カラム存在チェックと自動修復（ランキングカラムがない場合に追加）
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM {$tableName} LIKE 'repeat_ranking'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE {$tableName} ADD COLUMN repeat_ranking INT DEFAULT NULL COMMENT 'リピートランキング', ADD COLUMN attention_ranking INT DEFAULT NULL COMMENT '注目度ランキング'");
    }
} catch (PDOException $e) {
    // テーブル自体がない場合などのエラーハンドリング
}

// キャストデータの取得（アクティブなテーブルから）
try {
    $sql = "SELECT id, name FROM {$tableName} WHERE tenant_id = ? AND checking = 1 ORDER BY sort_order ASC, name ASC";
    // checking カラムがあるかわからない（scraper_ekichikaはcheckedを入れているが、ranking/index.phpの元コードは checked=1 となっていた）
    // 元コード: checked=1
    $sql = "SELECT id, name FROM {$tableName} WHERE tenant_id = ? AND checked = 1 ORDER BY sort_order ASC, name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tenantId]);
    $casts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 現在のランキングデータの取得
    $sql = "SELECT id, name, repeat_ranking, attention_ranking 
            FROM {$tableName} 
            WHERE tenant_id = ? AND (repeat_ranking IS NOT NULL OR attention_ranking IS NOT NULL) 
            ORDER BY repeat_ranking, attention_ranking";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tenantId]);
    $ranking_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // テーブル作成とカラム追加
    $pdo->exec("CREATE TABLE IF NOT EXISTS tenant_ranking_config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tenant_id INT NOT NULL,
        display_count INT DEFAULT 10,
        repeat_title VARCHAR(255),
        attention_title VARCHAR(255),
        repeat_visible TINYINT(1) DEFAULT 1,
        attention_visible TINYINT(1) DEFAULT 1,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_tenant (tenant_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 念のためALTERでカラム追加（既存テーブル用）
    try {
        $pdo->query("SELECT display_count FROM tenant_ranking_config LIMIT 1");
    } catch (PDOException $e) {
        $pdo->exec("ALTER TABLE tenant_ranking_config ADD COLUMN display_count INT DEFAULT 10");
    }
    
    // 他のカラムもチェック
    try {
        $pdo->query("SELECT repeat_title FROM tenant_ranking_config LIMIT 1");
    } catch (PDOException $e) {
        $pdo->exec("ALTER TABLE tenant_ranking_config ADD COLUMN repeat_title VARCHAR(255), ADD COLUMN attention_title VARCHAR(255)");
    }

    try {
        $pdo->query("SELECT repeat_visible FROM tenant_ranking_config LIMIT 1");
    } catch (PDOException $e) {
        $pdo->exec("ALTER TABLE tenant_ranking_config ADD COLUMN repeat_visible TINYINT(1) DEFAULT 1, ADD COLUMN attention_visible TINYINT(1) DEFAULT 1");
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
            $display_count = (int) $config['display_count'];
        }
        $repeat_title = $config['repeat_title'] ?? '';
        $attention_title = $config['attention_title'] ?? '';
        $repeat_visible = isset($config['repeat_visible']) ? (int) $config['repeat_visible'] : 1;
        $attention_visible = isset($config['attention_visible']) ? (int) $config['attention_visible'] : 1;
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
    ['label' => 'ダッシュボード', 'url' => '/app/manage/?tenant=' . $tenantSlug, 'icon' => 'fas fa-chart-pie'],
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
    <div class="info-box" style="background: var(--warning-bg); border-color: var(--warning-border);">
        <i class="fas fa-exclamation-triangle" style="color: var(--warning);"></i>
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
                <label
                    style="display:block; margin-bottom:8px; color:var(--text-secondary); font-size:0.9rem;">ランキング表示名</label>
                <input type="text" name="repeat_title" class="title-input" value="<?php echo h($repeat_title); ?>"
                    placeholder="例: リピートランキング">
            </div>
            <?php for ($i = 1; $i <= 10; $i++): ?>
                <div class="ranking-row" data-rank="<?php echo $i; ?>">
                    <label>
                        <?php echo $i; ?>位
                    </label>
                    <div class="searchable-cast-select" data-type="repeat" data-rank="<?php echo $i; ?>">
                        <input type="hidden" name="repeat_ranking[]"
                            value="<?php echo (isset($repeat_ranking[$i - 1])) ? $repeat_ranking[$i - 1] : ''; ?>">
                        <input type="text" class="cast-search-input" placeholder="キャスト名を入力..." value="<?php
                        if (isset($repeat_ranking[$i - 1])) {
                            // IDから名前を解決
                            foreach ($casts as $c) {
                                if ($c['id'] == $repeat_ranking[$i - 1]) {
                                    echo h($c['name']);
                                    break;
                                }
                            }
                        }
                        ?>" autocomplete="off">
                        <i class="fas fa-search search-icon"></i>
                        <div class="suggestions-list"></div>
                    </div>
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
                <label
                    style="display:block; margin-bottom:8px; color:var(--text-secondary); font-size:0.9rem;">ランキング表示名</label>
                <input type="text" name="attention_title" class="title-input" value="<?php echo h($attention_title); ?>"
                    placeholder="例: 注目度ランキング">
            </div>
            <?php for ($i = 1; $i <= 10; $i++): ?>
                <div class="ranking-row" data-rank="<?php echo $i; ?>">
                    <label>
                        <?php echo $i; ?>位
                    </label>
                    <div class="searchable-cast-select" data-type="attention" data-rank="<?php echo $i; ?>">
                        <input type="hidden" name="attention_ranking[]"
                            value="<?php echo (isset($attention_ranking[$i - 1])) ? $attention_ranking[$i - 1] : ''; ?>">
                        <input type="text" class="cast-search-input" placeholder="キャスト名を入力..." value="<?php
                        if (isset($attention_ranking[$i - 1])) {
                            foreach ($casts as $c) {
                                if ($c['id'] == $attention_ranking[$i - 1]) {
                                    echo h($c['name']);
                                    break;
                                }
                            }
                        }
                        ?>" autocomplete="off">
                        <i class="fas fa-search search-icon"></i>
                        <div class="suggestions-list"></div>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
    </div>
    </div>
</form>

<style>
    .info-box {
        background: var(--primary-bg);
        border: 1px solid var(--primary-border);
        border-radius: 12px;
        padding: 15px 20px;
        margin-bottom: 25px;
        color: var(--text-primary);
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .info-box i {
        color: var(--primary);
        font-size: 18px;
    }

    .info-box strong {
        color: var(--primary);
    }

    #update_date {
        padding: 12px 20px;
        background: var(--bg-body);
        border: 1px solid var(--border-color);
        border-radius: 10px;
        color: var(--text-primary);
        font-size: 1rem;
        transition: all 0.3s ease;
        width: 250px;
        text-align: center;
    }

    #update_date:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px var(--primary-bg);
    }

    #update_date::placeholder {
        color: var(--text-muted);
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
        background: var(--bg-card);
        padding: 25px;
        border-radius: 15px;
        border: none;
        box-shadow: var(--shadow-card);
    }

    .ranking-column h2 {
        color: var(--primary);
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid var(--primary-border);
        font-size: 1.2rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .ranking-row {
        position: relative;
        z-index: 1;
        display: flex;
        align-items: center;
        margin-bottom: 12px;
        padding: 10px 12px;
        background: var(--bg-body);
        border-radius: 10px;
        border: 1px solid var(--border-color);
        transition: all 0.3s ease;
    }

    .ranking-row.focus-within {
        z-index: 10000;
        background: var(--bg-card);
        box-shadow: var(--shadow-card-hover);
    }

    .ranking-row:hover {
        background: var(--bg-card);
        transform: translateX(3px);
    }

    .ranking-row label {
        width: 50px;
        font-weight: bold;
        color: var(--primary);
        font-size: 0.95rem;
        margin-bottom: 0;
    }

    .cast-select {
        flex: 1;
        padding: 10px 12px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        background: var(--bg-body);
        font-size: 14px;
        color: var(--text-primary);
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .cast-select:focus {
        border-color: var(--primary);
        outline: none;
        box-shadow: 0 0 0 3px var(--primary-bg);
    }

    .cast-select option {
        background: var(--bg-card);
        color: var(--text-primary);
        padding: 10px;
    }

    .btn-primary {
        background: var(--primary);
        color: var(--text-inverse);
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
        box-shadow: 0 10px 25px var(--primary-bg);
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
        background: var(--bg-body);
        border: 2px solid var(--border-color);
        border-radius: 25px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .radio-label:hover {
        background: var(--bg-card);
        border-color: var(--primary);
    }

    .radio-label.active {
        background: var(--primary-bg);
        border-color: var(--primary);
        color: var(--text-primary);
    }

    .radio-label input {
        display: none;
    }

    .title-input {
        width: 100%;
        padding: 10px 12px;
        background: var(--bg-body);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        color: var(--text-primary);
        font-size: 0.95rem;
        transition: all 0.3s ease;
    }

    .title-input:focus {
        border-color: var(--primary);
        outline: none;
        box-shadow: 0 0 0 3px var(--primary-bg);
        background: var(--bg-card);
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
        background-color: var(--border-color);
        transition: .4s;
    }

    .slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 4px;
        bottom: 4px;
        background-color: var(--text-inverse);
        transition: .4s;
    }

    input:checked+.slider {
        background-color: var(--primary);
    }

    input:focus+.slider {
        box-shadow: 0 0 1px var(--primary);
    }

    input:checked+.slider:before {
        transform: translateX(24px);
    }

    .slider.round {
        border-radius: 34px;
    }

    .slider.round:before {
        border-radius: 50%;
    }

    .switch-label {
        color: var(--text-primary);
        font-weight: bold;
    }

    /* 検索付きセレクトボックスのスタイル */
    .searchable-cast-select {
        position: relative;
        flex: 1;
    }

    .cast-search-input {
        width: 100%;
        padding: 10px 12px;
        padding-right: 35px;
        /* アイコン用 */
        background: var(--bg-body);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        color: var(--text-primary);
        font-size: 14px;
        transition: all 0.3s ease;
    }

    .cast-search-input:focus {
        border-color: var(--primary);
        outline: none;
        box-shadow: 0 0 0 3px var(--primary-bg);
        background: var(--bg-card);
    }

    .search-icon {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
        pointer-events: none;
    }

    .suggestions-list {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        max-height: 250px;
        overflow-y: auto;
        z-index: 1000;
        display: none;
        box-shadow: var(--shadow-xl);
        margin-top: 5px;
    }

    .suggestion-item {
        padding: 10px 15px;
        cursor: pointer;
        border-bottom: 1px solid var(--border-color);
        transition: background 0.2s;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .suggestion-item:last-child {
        border-bottom: none;
    }

    .suggestion-item:hover {
        background: var(--primary-bg);
    }

    .suggestion-name {
        color: var(--text-primary);
        font-weight: 500;
    }

    .suggestion-id {
        font-size: 0.8em;
        color: var(--text-muted);
    }
</style>

<script>
    // キャストデータをJS配列として保持
    const allCasts = <?php echo json_encode($casts); ?>;

    // 検索UIのロジック
    document.addEventListener('DOMContentLoaded', function () {
        // ... (既存の初期化処理) ...

        // 全ての検索インプットにイベントリスナーを設定
        document.querySelectorAll('.cast-search-input').forEach(input => {
            const container = input.closest('.searchable-cast-select');
            const hiddenInput = container.querySelector('input[type="hidden"]');
            const suggestionsList = container.querySelector('.suggestions-list');

            // 入力時
            input.addEventListener('input', function () {
                const term = this.value.toLowerCase().trim();

                // 空ならクリア
                if (term === '') {
                    hiddenInput.value = '';
                    suggestionsList.style.display = 'none';
                    return;
                }

                // 絞り込み
                const matches = allCasts.filter(cast =>
                    cast.name.toLowerCase().includes(term) ||
                    (cast.name_romaji && cast.name_romaji.toLowerCase().includes(term))
                );

                renderSuggestions(matches, suggestionsList, input, hiddenInput);
            });

            // フォーカス時（全件表示 or 入力済みならそのまま）
            input.addEventListener('focus', function () {
                const row = this.closest('.ranking-row');
                if(row) row.classList.add('focus-within');

                const term = this.value.toLowerCase().trim();
                let matches = allCasts;
                if (term !== '') {
                    matches = allCasts.filter(cast =>
                        cast.name.toLowerCase().includes(term)
                    );
                }
                renderSuggestions(matches, suggestionsList, input, hiddenInput);
            });

            // フォーカスアウト時（遅延させてクリック判定を優先）
            input.addEventListener('blur', function () {
                setTimeout(() => {
                    const row = this.closest('.ranking-row');
                    if(row) row.classList.remove('focus-within');

                    suggestionsList.style.display = 'none';

                    // 入力値と一致するキャストがいなければクリア（厳密にする場合）
                    // ここでは「空欄なのにIDが残ってる」ケースだけ防ぐ
                    if (this.value === '') {
                        hiddenInput.value = '';
                    } else {
                        // 名前があってもIDがない（自由入力）は許可しないので、
                        // hiddenValueが入ってなければクリアする
                        if (hiddenInput.value === '') {
                            this.value = '';
                        }
                    }
                }, 200);
            });
        });

        function renderSuggestions(matches, list, input, hidden) {
            list.innerHTML = '';
            if (matches.length === 0) {
                const noResult = document.createElement('div');
                noResult.className = 'suggestion-item';
                noResult.textContent = '該当なし';
                noResult.style.color = 'var(--text-muted)';
                list.appendChild(noResult);
            } else {
                matches.forEach(cast => {
                    const item = document.createElement('div');
                    item.className = 'suggestion-item';
                    item.innerHTML = `<span class="suggestion-name">${escapeHtml(cast.name)}</span>`;

                    item.addEventListener('click', function () {
                        input.value = cast.name;
                        hidden.value = cast.id;
                        list.style.display = 'none';
                    });
                    list.appendChild(item);
                });
            }
            list.style.display = 'block';
        }

        function escapeHtml(text) {
            return text
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    });

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
                repeat_ranking: Array.from(document.querySelectorAll('input[name="repeat_ranking[]"]')).map(s => s.value),
                attention_ranking: Array.from(document.querySelectorAll('input[name="attention_ranking[]"]')).map(s => s.value),
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