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

    // トップページ編集のis_visibleと同期（top_layout_sectionsが正とする）
    try {
        $stmt = $pdo->prepare("SELECT section_key, is_visible FROM top_layout_sections WHERE tenant_id = ? AND section_key IN ('repeat_ranking', 'attention_ranking')");
        $stmt->execute([$tenantId]);
        $layoutSections = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($layoutSections as $ls) {
            if ($ls['section_key'] === 'repeat_ranking') {
                $repeat_visible = (int) $ls['is_visible'];
            }
            if ($ls['section_key'] === 'attention_ranking') {
                $attention_visible = (int) $ls['is_visible'];
            }
        }
        // tenant_ranking_configにも反映しておく
        if ($config && !empty($layoutSections)) {
            $stmt = $pdo->prepare("UPDATE tenant_ranking_config SET repeat_visible = ?, attention_visible = ? WHERE tenant_id = ?");
            $stmt->execute([$repeat_visible, $attention_visible, $tenantId]);
        }
    } catch (PDOException $e) {
        // top_layout_sectionsが無い場合は既存値をそのまま使う
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


<?php if (count($casts) === 0): ?>
    <div class="info-box" style="background: var(--warning-bg); border-color: var(--warning-border);">
        <i class="fas fa-exclamation-triangle" style="color: var(--warning);"></i>
        <span>キャストデータがありません。先にスクレイピングを実行してください。</span>
    </div>
<?php endif; ?>

<form id="rankingForm" method="post">
    <div class="ranking-save-bar">
        <button type="submit" class="btn btn-primary btn-save">
            <i class="fas fa-save"></i> 保存
        </button>
    </div>

    <div class="ranking-header-bar">
        <div class="display-count-group">
            <span class="display-count-label"><i class="fas fa-list-ol"></i> 表示数</span>
            <div class="radio-segment">
                <label class="radio-segment-item <?php echo $display_count == 3 ? 'active' : ''; ?>">
                    <input type="radio" name="display_count" value="3" <?php echo $display_count == 3 ? 'checked' : ''; ?> onchange="updateDisplayCount(3)">3
                </label>
                <label class="radio-segment-item <?php echo $display_count == 5 ? 'active' : ''; ?>">
                    <input type="radio" name="display_count" value="5" <?php echo $display_count == 5 ? 'checked' : ''; ?> onchange="updateDisplayCount(5)">5
                </label>
                <label class="radio-segment-item <?php echo $display_count == 10 ? 'active' : ''; ?>">
                    <input type="radio" name="display_count" value="10" <?php echo $display_count == 10 ? 'checked' : ''; ?> onchange="updateDisplayCount(10)">10
                </label>
            </div>
            <span class="display-count-suffix">位まで</span>
        </div>
    </div>

    <div class="ranking-container">
        <div class="ranking-column">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <label class="switch-label">表示設定</label>
                <label class="switch">
                    <input type="checkbox" id="repeat_visible" name="repeat_visible" <?php echo $repeat_visible ? 'checked' : ''; ?> onchange="handleVisibilityChange(this)">
                    <span class="slider round"></span>
                </label>
            </div>
            <div class="form-group" style="margin-bottom: 12px;">
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
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <label class="switch-label">表示設定</label>
                <label class="switch">
                    <input type="checkbox" id="attention_visible" name="attention_visible" <?php echo $attention_visible ? 'checked' : ''; ?> onchange="handleVisibilityChange(this)">
                    <span class="slider round"></span>
                </label>
            </div>
            <div class="form-group" style="margin-bottom: 12px;">
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
        gap: 24px;
        margin: 0;
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
        padding: 20px;
        border-radius: 12px;
        border: 1px solid var(--border-color);
        box-shadow: var(--shadow-sm);
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
        margin-bottom: 8px;
        padding: 8px 12px;
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

    /* 保存ボタンバー */
    .ranking-save-bar {
        display: flex;
        justify-content: center;
        margin-bottom: 20px;
    }

    /* 上部バー：表示数 */
    .ranking-header-bar {
        display: flex;
        align-items: center;
        justify-content: center;
        flex-wrap: wrap;
        gap: 16px;
        padding: 16px 20px;
        background: var(--bg-card);
        border-radius: 12px;
        margin-bottom: 24px;
        box-shadow: var(--shadow-card);
    }

    .display-count-group {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .display-count-label {
        font-size: 0.9rem;
        color: var(--text-secondary);
        font-weight: 500;
    }

    .radio-segment {
        display: inline-flex;
        background: var(--bg-body);
        border-radius: 8px;
        padding: 2px;
        border: 1px solid var(--border-color);
    }

    .radio-segment-item {
        padding: 6px 14px;
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text-muted);
        cursor: pointer;
        transition: all var(--transition-fast);
        border-radius: 6px;
    }

    .radio-segment-item:first-of-type { border-radius: 6px 0 0 6px; }
    .radio-segment-item:last-of-type { border-radius: 0 6px 6px 0; }

    .radio-segment-item:hover {
        color: var(--text-primary);
    }

    .radio-segment-item.active {
        background: var(--primary-gradient);
        color: var(--text-inverse);
    }

    .radio-segment-item input {
        display: none;
    }

    .display-count-suffix {
        font-size: 0.85rem;
        color: var(--text-muted);
    }

    .btn-primary {
        background: var(--primary-gradient);
        color: var(--text-inverse);
        border: none;
        padding: 10px 24px;
        border-radius: 10px;
        cursor: pointer;
        font-size: 0.95rem;
        font-weight: 600;
        transition: all var(--transition-fast);
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .btn-primary:hover {
        background: var(--primary-gradient-hover);
        transform: translateY(-1px);
        box-shadow: var(--shadow-primary);
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
        background: var(--primary-gradient);
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

    .suggestion-item:hover,
    .suggestion-item.keyboard-active {
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

    /* サジェストリスト テキスト視認性の強化 */
    .suggestions-list .suggestion-item {
        color: var(--text-primary);
    }

    /* ダークモード明示指定 */
    [data-theme="dark"] .suggestions-list {
        background: #1e1e3a;
        border-color: #3a3a5c;
    }

    [data-theme="dark"] .suggestions-list .suggestion-item {
        color: #f3f4f6;
        border-bottom-color: #3a3a5c;
    }

    [data-theme="dark"] .suggestions-list .suggestion-item:hover,
    [data-theme="dark"] .suggestions-list .suggestion-item.keyboard-active {
        background: #2a2a4a;
    }

    [data-theme="dark"] .suggestions-list .suggestion-name {
        color: #f3f4f6;
    }

    /* ライトモード明示指定 */
    :root:not([data-theme="dark"]) .suggestions-list {
        background: #ffffff;
        border-color: #d1d5db;
    }

    :root:not([data-theme="dark"]) .suggestions-list .suggestion-item {
        color: #1d1d1f;
        border-bottom-color: #e5e7eb;
    }

    :root:not([data-theme="dark"]) .suggestions-list .suggestion-item:hover,
    :root:not([data-theme="dark"]) .suggestions-list .suggestion-item.keyboard-active {
        background: #f0f4ff;
    }

    :root:not([data-theme="dark"]) .suggestions-list .suggestion-name {
        color: #1d1d1f;
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

            // IME変換中フラグ
            let isComposing = false;
            // キーボード選択用インデックス
            let activeIndex = -1;

            // IME変換開始 → サジェストを隠す
            input.addEventListener('compositionstart', function () {
                isComposing = true;
                suggestionsList.style.display = 'none';
            });

            // IME変換確定 → サジェストを表示
            input.addEventListener('compositionend', function () {
                isComposing = false;
                // 変換確定後に検索を実行
                const term = this.value.toLowerCase().trim();
                if (term === '') {
                    hiddenInput.value = '';
                    suggestionsList.style.display = 'none';
                    return;
                }
                const matches = allCasts.filter(cast =>
                    cast.name.toLowerCase().includes(term) ||
                    (cast.name_romaji && cast.name_romaji.toLowerCase().includes(term))
                );
                activeIndex = -1;
                renderSuggestions(matches, suggestionsList, input, hiddenInput);
            });

            // 入力時（IME変換中は無視）
            input.addEventListener('input', function () {
                if (isComposing) return; // IME変換中はスキップ

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

                activeIndex = -1;
                renderSuggestions(matches, suggestionsList, input, hiddenInput);
            });

            // キーボードで候補を選択（↑↓キー、Enterキー対応）
            input.addEventListener('keydown', function (e) {
                if (isComposing) return; // IME変換中はスキップ

                const items = suggestionsList.querySelectorAll('.suggestion-item');
                if (items.length === 0 || suggestionsList.style.display === 'none') return;

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    activeIndex = Math.min(activeIndex + 1, items.length - 1);
                    updateActiveItem(items, activeIndex);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    activeIndex = Math.max(activeIndex - 1, 0);
                    updateActiveItem(items, activeIndex);
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (activeIndex >= 0 && activeIndex < items.length) {
                        items[activeIndex].click();
                    }
                } else if (e.key === 'Escape') {
                    suggestionsList.style.display = 'none';
                    activeIndex = -1;
                }
            });

            // フォーカス時（IME変換中でなければ候補表示）
            input.addEventListener('focus', function () {
                const row = this.closest('.ranking-row');
                if(row) row.classList.add('focus-within');

                if (isComposing) return;

                const term = this.value.toLowerCase().trim();
                let matches = allCasts;
                if (term !== '') {
                    matches = allCasts.filter(cast =>
                        cast.name.toLowerCase().includes(term)
                    );
                }
                activeIndex = -1;
                renderSuggestions(matches, suggestionsList, input, hiddenInput);
            });

            // フォーカスアウト時（遅延させてクリック判定を優先）
            input.addEventListener('blur', function () {
                setTimeout(() => {
                    const row = this.closest('.ranking-row');
                    if(row) row.classList.remove('focus-within');

                    suggestionsList.style.display = 'none';
                    activeIndex = -1;

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

        // キーボード選択時のハイライト更新
        function updateActiveItem(items, index) {
            items.forEach((item, i) => {
                item.classList.toggle('keyboard-active', i === index);
                if (i === index) {
                    item.scrollIntoView({ block: 'nearest' });
                }
            });
        }

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
        document.querySelectorAll('.radio-segment-item').forEach(label => {
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
                        window.location.href = 'index.php?tenant=<?php echo urlencode($tenantSlug); ?>';
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