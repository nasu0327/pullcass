<?php
/**
 * pullcass - マスター管理画面
 * 料金表管理 - 一覧ページ
 */

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

// ログイン認証チェック
requireTenantAdminLogin();

// データベース接続
$pdo = getPlatformDb();
if (!$pdo) {
    die('データベースに接続できません。');
}

// 料金セット一覧を取得
try {
    // 平常期間
    $stmtRegular = $pdo->query("
        SELECT * FROM price_sets 
        WHERE set_type = 'regular' 
        ORDER BY id ASC
    ");
    $regularSets = $stmtRegular->fetchAll(PDO::FETCH_ASSOC);
    
    // 特別期間
    $stmtSpecial = $pdo->query("
        SELECT * FROM price_sets 
        WHERE set_type = 'special' 
        ORDER BY start_datetime ASC
    ");
    $specialSets = $stmtSpecial->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = "データの取得に失敗しました: " . $e->getMessage();
}

// 現在表示中の料金セットを判定
function getCurrentActiveSetId($pdo) {
    $now = date('Y-m-d H:i:s');
    
    $stmt = $pdo->prepare("
        SELECT id FROM price_sets 
        WHERE set_type = 'special' 
          AND is_active = 1 
          AND start_datetime <= ? 
          AND end_datetime >= ?
        ORDER BY start_datetime ASC 
        LIMIT 1
    ");
    $stmt->execute([$now, $now]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        return $result['id'];
    }
    
    $stmt = $pdo->query("
        SELECT id FROM price_sets 
        WHERE set_type = 'regular' 
          AND is_active = 1 
        LIMIT 1
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['id'] : null;
}

$currentActiveId = getCurrentActiveSetId($pdo);

$pageTitle = '料金表管理';
require_once __DIR__ . '/../includes/header.php';
?>
<style>
    .section-title {
        font-size: 1.4rem;
        font-weight: 600;
        color: var(--text-light);
        margin: 30px 0 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid var(--border-color);
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .price-set-list {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .price-set-card {
        background: var(--card-bg);
        border-radius: 12px;
        padding: 20px 25px;
        border: 1px solid var(--border-color);
        display: flex;
        align-items: center;
        justify-content: space-between;
        transition: all 0.3s ease;
    }

    .price-set-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
        border-color: var(--primary);
    }

    .price-set-card.active {
        border-color: var(--success);
        background: rgba(16, 185, 129, 0.1);
    }

    .price-set-info {
        flex: 1;
    }

    .price-set-name {
        font-size: 1.2rem;
        font-weight: 600;
        color: var(--text-light);
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .price-set-meta {
        font-size: 0.9rem;
        color: var(--text-muted);
    }

    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .status-active {
        background: rgba(16, 185, 129, 0.2);
        color: var(--success);
    }

    .status-inactive {
        background: rgba(245, 158, 11, 0.2);
        color: var(--warning);
    }

    .status-current {
        background: rgba(39, 163, 235, 0.2);
        color: #27a3eb;
    }

    .price-set-actions {
        display: flex;
        gap: 10px;
    }

    .add-section {
        text-align: center;
        margin: 30px 0;
    }

    .preview-buttons {
        display: flex;
        gap: 15px;
        margin: 30px 0;
        justify-content: center;
        flex-wrap: wrap;
    }

    .info-box {
        background: rgba(39, 163, 235, 0.1);
        border: 1px solid rgba(39, 163, 235, 0.3);
        border-radius: 10px;
        padding: 15px 20px;
        margin-bottom: 20px;
        font-size: 0.9rem;
        color: var(--text-muted);
        display: flex;
        align-items: flex-start;
        gap: 10px;
    }

    .info-box i {
        color: #27a3eb;
        font-size: 20px;
        margin-top: 2px;
    }

    /* モーダル */
    .modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(5px);
        z-index: 10000;
        align-items: center;
        justify-content: center;
    }

    .modal-overlay.active {
        display: flex;
    }

    .modal-content {
        background: var(--card-bg);
        border-radius: 20px;
        padding: 30px;
        max-width: 500px;
        width: 90%;
        border: 1px solid var(--border-color);
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
    }

    .modal-header {
        font-size: 1.3rem;
        font-weight: 600;
        color: var(--text-light);
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .modal-body {
        margin-bottom: 25px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--text-light);
        margin-bottom: 8px;
    }

    .form-group input {
        width: 100%;
        padding: 12px 15px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        background: var(--darker);
        color: var(--text-light);
        font-size: 1rem;
    }

    .form-group input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(255, 107, 157, 0.2);
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }

    .modal-footer {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
    }

    .warning-text {
        color: var(--danger);
        font-size: 0.85rem;
        margin-top: 5px;
    }

    .checkbox-group {
        margin-top: 10px;
    }

    .checkbox-label {
        display: flex;
        align-items: center;
        gap: 10px;
        cursor: pointer;
        font-weight: normal;
    }

    .checkbox-label input[type="checkbox"] {
        width: 20px;
        height: 20px;
        accent-color: var(--primary);
        cursor: pointer;
    }

    .checkbox-text {
        font-size: 0.95rem;
        color: var(--text-light);
    }

    .checkbox-hint {
        font-size: 0.8rem;
        color: var(--text-muted);
        margin-top: 5px;
        margin-left: 30px;
    }

    @media (max-width: 768px) {
        .price-set-card {
            flex-direction: column;
            align-items: flex-start;
            gap: 15px;
        }
        
        .price-set-actions {
            width: 100%;
            justify-content: flex-end;
        }
        
        .form-row {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="content-wrapper">
    <div class="page-header">
        <h1><i class="fas fa-yen-sign"></i> 料金表管理</h1>
        <p class="subtitle">料金表の作成・編集・期間設定を行います</p>
    </div>

    <!-- アクションボタン -->
    <div class="preview-buttons">
        <a href="/system_preview.php" target="_blank" class="btn btn-secondary">
            <i class="fas fa-desktop"></i> PC版プレビュー
        </a>
        <a href="/system_preview_mobile.php" target="_blank" class="btn btn-secondary">
            <i class="fas fa-mobile-alt"></i> スマホ版プレビュー
        </a>
        <button class="btn btn-primary" onclick="publishPrices()">
            <i class="fas fa-paper-plane"></i> 公開する
        </button>
    </div>

    <!-- 説明 -->
    <div class="info-box">
        <i class="fas fa-info-circle"></i>
        <div>
            <strong>料金表の仕組み：</strong><br>
            「平常期間料金」は通常時に表示されます。「特別期間料金」を設定すると、その期間中は特別料金が優先表示されます。<br>
            例：年末年始料金を12/28〜1/5に設定すると、その期間のみ特別料金が表示されます。
        </div>
    </div>

    <!-- 平常期間料金 -->
    <h2 class="section-title">
        <i class="fas fa-clock"></i>
        平常期間料金（通常表示）
    </h2>
    
    <div class="price-set-list">
        <?php if (empty($regularSets)): ?>
        <div class="price-set-card">
            <div class="price-set-info">
                <div class="price-set-name">平常期間料金が設定されていません</div>
            </div>
        </div>
        <?php else: ?>
        <?php foreach ($regularSets as $set): ?>
        <div class="price-set-card <?php echo ($set['id'] == $currentActiveId) ? 'active' : ''; ?>">
            <div class="price-set-info">
                <div class="price-set-name">
                    <?php echo h($set['set_name']); ?>
                    <?php if ($set['id'] == $currentActiveId): ?>
                    <span class="status-badge status-current">現在表示中</span>
                    <?php endif; ?>
                    <?php if (!$set['is_active']): ?>
                    <span class="status-badge status-inactive">無効</span>
                    <?php endif; ?>
                </div>
                <div class="price-set-meta">
                    ※平常期間は特別期間外に常に表示されます
                </div>
            </div>
            <div class="price-set-actions">
                <a href="edit.php?id=<?php echo $set['id']; ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> 編集
                </a>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- 特別期間料金 -->
    <h2 class="section-title">
        <i class="fas fa-calendar-alt"></i>
        特別期間料金（期間限定表示）
    </h2>

    <div class="price-set-list">
        <?php if (empty($specialSets)): ?>
        <div class="price-set-card">
            <div class="price-set-info">
                <div class="price-set-name" style="color: var(--text-muted);">特別期間料金はまだ設定されていません</div>
                <div class="price-set-meta">下のボタンから追加してください</div>
            </div>
        </div>
        <?php else: ?>
        <?php foreach ($specialSets as $set): ?>
        <div class="price-set-card <?php echo ($set['id'] == $currentActiveId) ? 'active' : ''; ?>">
            <div class="price-set-info">
                <div class="price-set-name">
                    <?php echo h($set['set_name']); ?>
                    <?php if ($set['id'] == $currentActiveId): ?>
                    <span class="status-badge status-current">現在表示中</span>
                    <?php endif; ?>
                    <?php if (!$set['is_active']): ?>
                    <span class="status-badge status-inactive">無効</span>
                    <?php endif; ?>
                </div>
                <div class="price-set-meta">
                    <i class="fas fa-calendar"></i>
                    <?php echo date('Y/m/d H:i', strtotime($set['start_datetime'])); ?> 〜 
                    <?php echo date('Y/m/d H:i', strtotime($set['end_datetime'])); ?>
                </div>
            </div>
            <div class="price-set-actions">
                <a href="edit.php?id=<?php echo $set['id']; ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> 編集
                </a>
                <button class="btn btn-danger" onclick="deleteSet(<?php echo $set['id']; ?>, '<?php echo h($set['set_name'], ENT_QUOTES); ?>')">
                    <i class="fas fa-trash"></i> 削除
                </button>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="add-section">
        <button class="btn btn-primary" onclick="openAddModal()">
            <i class="fas fa-plus-circle"></i> 特別期間料金を追加
        </button>
    </div>
</div>

<!-- 特別期間追加モーダル -->
<div id="addModal" class="modal-overlay" onclick="if(event.target === this) closeModal()">
    <div class="modal-content">
        <div class="modal-header">
            <i class="fas fa-plus-circle"></i>
            特別期間料金を追加
        </div>
        <form id="addForm" onsubmit="return submitAddForm()">
            <div class="modal-body">
                <div class="form-group">
                    <label>料金セット名 <span style="color: var(--danger);">*</span></label>
                    <input type="text" id="setName" name="set_name" required placeholder="例：年末年始特別料金">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>開始日時 <span style="color: var(--danger);">*</span></label>
                        <input type="datetime-local" id="startDatetime" name="start_datetime" required>
                    </div>
                    <div class="form-group">
                        <label>終了日時 <span style="color: var(--danger);">*</span></label>
                        <input type="datetime-local" id="endDatetime" name="end_datetime" required>
                    </div>
                </div>
                <div class="form-group checkbox-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="copyFromRegular" name="copy_from_regular">
                        <span class="checkbox-text">平常期間料金からコピーする</span>
                    </label>
                    <p class="checkbox-hint">チェックすると、平常期間料金の内容をコピーして作成します</p>
                </div>
                <div id="dateError" class="warning-text" style="display: none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">キャンセル</button>
                <button type="submit" class="btn btn-primary">作成して編集へ</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openAddModal() {
        document.getElementById('addModal').classList.add('active');
    }

    function closeModal() {
        document.getElementById('addModal').classList.remove('active');
        document.getElementById('addForm').reset();
        document.getElementById('dateError').style.display = 'none';
    }

    function submitAddForm() {
        const setName = document.getElementById('setName').value.trim();
        const startDatetime = document.getElementById('startDatetime').value;
        const endDatetime = document.getElementById('endDatetime').value;
        const dateError = document.getElementById('dateError');

        // バリデーション
        if (!setName || !startDatetime || !endDatetime) {
            dateError.textContent = '全ての項目を入力してください。';
            dateError.style.display = 'block';
            return false;
        }

        if (new Date(startDatetime) >= new Date(endDatetime)) {
            dateError.textContent = '終了日時は開始日時より後に設定してください。';
            dateError.style.display = 'block';
            return false;
        }

        const copyFromRegular = document.getElementById('copyFromRegular').checked;

        // サーバーへ送信
        fetch('add_set.php?tenant=<?php echo h($tenantSlug); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                set_name: setName,
                start_datetime: startDatetime,
                end_datetime: endDatetime,
                copy_from_regular: copyFromRegular
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // 編集ページへリダイレクト
                window.location.href = 'edit.php?tenant=<?php echo h($tenantSlug); ?>&id=' + data.id;
            } else {
                dateError.textContent = data.message || '作成に失敗しました。';
                dateError.style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            dateError.textContent = '通信エラーが発生しました。';
            dateError.style.display = 'block';
        });

        return false;
    }

    function deleteSet(id, name) {
        if (!confirm(`「${name}」を削除してもよろしいですか？\n\nこの操作は取り消せません。`)) {
            return;
        }

        fetch('delete_set.php?tenant=<?php echo h($tenantSlug); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('削除しました。');
                location.reload();
            } else {
                alert('削除に失敗しました: ' + (data.message || '不明なエラー'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('削除に失敗しました。');
        });
    }

    function publishPrices() {
        if (!confirm('現在の編集内容を公開しますか？\n\n公開後、料金ページに反映されます。')) {
            return;
        }

        fetch('publish.php?tenant=<?php echo h($tenantSlug); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('公開しました！\n料金ページで確認できます。');
                window.open('/system', '_blank');
            } else {
                alert('公開に失敗しました: ' + (data.message || '不明なエラー'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('公開に失敗しました。');
        });
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
