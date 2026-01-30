<?php
require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

// ログイン認証
requireTenantAdminLogin();

$pdo = getPlatformDb();
$tenantSlug = $_GET['tenant'] ?? $_SESSION['manage_tenant_slug'] ?? null;
if (!$tenantSlug) {
    header('Location: /');
    exit;
}

$pageTitle = 'ホテルリスト管理';

// リダイレクト後の success/error 表示用
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}
if (isset($_GET['error'])) {
    $error = $_GET['error'];
}

// 削除処理
if (isset($_POST['delete_id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM hotels WHERE id = ? AND tenant_id = ?");
        $stmt->execute([(int) $_POST['delete_id'], $tenantId]);
        $success = '削除しました。';
    } catch (PDOException $e) {
        $error = '削除エラー: ' . $e->getMessage();
    }
}

// ホテルリスト一括削除
if (isset($_POST['delete_all_hotels'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM hotels WHERE tenant_id = ?");
        $stmt->execute([$tenantId]);
        $deleted = $stmt->rowCount();
        header('Location: index.php?tenant=' . rawurlencode($tenantSlug) . '&success=' . rawurlencode($deleted . '件のホテルを一括削除しました。'));
        exit;
    } catch (PDOException $e) {
        $error = '一括削除エラー: ' . $e->getMessage();
    }
}

// 検索・フィルタリング
$keyword = $_GET['keyword'] ?? '';
$areaFilter = $_GET['area'] ?? '';
$symbolFilter = $_GET['symbol'] ?? '';

$sql = "SELECT * FROM hotels WHERE tenant_id = ?";
$params = [$tenantId];

if ($keyword) {
    $sql .= " AND (name LIKE ? OR address LIKE ? OR area LIKE ?)";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
}
if ($areaFilter) {
    $sql .= " AND area = ?";
    $params[] = $areaFilter;
}
if ($symbolFilter) {
    $sql .= " AND symbol = ?";
    $params[] = $symbolFilter;
}

$sql .= " ORDER BY sort_order ASC, id DESC"; // 表示順
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$hotels = $stmt->fetchAll();

// エリア一覧取得（フィルター用）
$areas = $pdo->prepare("SELECT DISTINCT area FROM hotels WHERE tenant_id = ? AND area IS NOT NULL AND area != '' ORDER BY area");
$areas->execute([$tenantId]);
$areas = $areas->fetchAll(PDO::FETCH_COLUMN);

// エリア別登録件数の取得（全量）
$areaCountsStmt = $pdo->prepare("SELECT area, COUNT(*) as cnt FROM hotels WHERE tenant_id = ? GROUP BY area ORDER BY area");
$areaCountsStmt->execute([$tenantId]);
$areaCounts = $areaCountsStmt->fetchAll(PDO::FETCH_KEY_PAIR);
$totalHotels = array_sum($areaCounts);

// データ正規化
$circles = ['◯', '○', '〇', '◎', '●'];
foreach ($hotels as &$h) {
    $h['symbol'] = trim($h['symbol']);
    // ラブホテルの場合はシンボルが空なら○をセット
    if ($h['is_love_hotel'] == 1 && (empty($h['symbol']) || !in_array($h['symbol'], array_merge($circles, ['※', '△', '×'])))) {
        $h['symbol'] = '◯';
    }
    // 丸記号の統一
    if (in_array($h['symbol'], $circles)) {
        $h['symbol'] = '◯';
    }
}
unset($h);

require_once __DIR__ . '/../includes/header.php';
?>
<?php
require_once __DIR__ . '/../includes/breadcrumb.php';
$breadcrumbs = [
    ['label' => 'ダッシュボード', 'url' => '/app/manage/?tenant=' . $tenantSlug, 'icon' => 'fas fa-chart-pie'],
    ['label' => 'ホテルリスト管理']
];
renderBreadcrumb($breadcrumbs);
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-list"></i> <?php echo h($pageTitle); ?></h1>
        <p>ホテルの登録状況の確認・編集・エクスポートが行えます。</p>
    </div>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?php echo h($success); ?>
    </div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i> <?php echo h($error); ?>
    </div>
<?php endif; ?>

<!-- 派遣方法テキスト編集 -->
<div class="content-card mb-4">
    <h5 class="mb-3"><i class="fas fa-edit"></i> 派遣方法テキスト編集</h5>
    <p class="mb-3" style="font-size: 0.9rem; color: var(--text-muted);">
        ホテルリストのホテル詳細ページで表示する「派遣方法」等の文言を、派遣状況ごとに編集できます。ボタンをクリックしてポップアップ画面で編集してください。
    </p>
    <div style="display: flex; flex-wrap: wrap; justify-content: center; gap: 12px;">
        <button type="button" class="btn btn-secondary dispatch-edit-btn" data-type="full" data-label="○ 派遣可能"
            style="border-color: #28a745; color: #28a745;">
            ○ 派遣可能
        </button>
        <button type="button" class="btn btn-secondary dispatch-edit-btn" data-type="conditional" data-label="※ カードキー"
            style="border-color: #17a2b8; color: #17a2b8;">
            ※ カードキー
        </button>
        <button type="button" class="btn btn-secondary dispatch-edit-btn" data-type="limited" data-label="△ 要確認"
            style="border-color: #ffc107; color: #856404;">
            △ 要確認
        </button>
        <button type="button" class="btn btn-secondary dispatch-edit-btn" data-type="none" data-label="× 派遣不可"
            style="border-color: #dc3545; color: #dc3545;">
            × 派遣不可
        </button>
        <button type="button" class="btn btn-secondary dispatch-edit-btn" data-type="love_hotel" data-label="ラブホテル"
            style="border-color: var(--primary); color: var(--primary);">
            ラブホテル
        </button>
    </div>
</div>

<!-- 派遣方法テキスト編集モーダル -->
<div id="dispatchTextModal" class="modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 9999;">
    <div class="modal-content" style="max-width: 800px; max-height: 90vh; overflow: hidden; display: flex; flex-direction: column;">
        <div class="modal-header" style="padding: 16px; border-bottom: 1px solid var(--border-color);">
            <h4 style="margin: 0;"><i class="fas fa-edit"></i> <span id="dispatchModalTitle">派遣状況テキスト</span></h4>
            <button type="button" class="modal-close" onclick="closeDispatchModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted);">&times;</button>
        </div>
        <div class="modal-body" style="padding: 16px; overflow-y: auto; flex: 1;">
            <textarea id="dispatchTextArea" rows="18" class="form-control" style="font-family: inherit; font-size: 14px;"></textarea>
        </div>
        <div class="modal-footer" style="padding: 16px; border-top: 1px solid var(--border-color); display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
            <div>
                <button type="button" class="btn btn-outline-secondary" id="dispatchResetBtn"><i class="fas fa-undo"></i> 基本テキストに戻す</button>
            </div>
            <div>
                <button type="button" class="btn btn-secondary" onclick="closeDispatchModal()">キャンセル</button>
                <button type="button" class="btn btn-primary" id="dispatchSaveBtn"><i class="fas fa-save"></i> 保存</button>
            </div>
        </div>
    </div>
</div>

<!-- 登録状況サマリー -->
<div class="mb-3 pl-2">
    <div style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 5px;">
        <i class="fas fa-info-circle"></i> エリア別登録件数 (合計: <strong
            style="color:var(--text-light);"><?php echo number_format($totalHotels); ?></strong>件)
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?php foreach ($areaCounts as $areaName => $count): ?>
            <span
                style="background: rgba(0,0,0,0.2); border: 1px solid var(--border-color); padding: 2px 10px; border-radius: 4px; font-size: 0.8rem; color: var(--text-muted);">
                <?php echo h($areaName ?: '未設定'); ?>: <strong
                    style="color: var(--text-light);"><?php echo number_format($count); ?></strong>
            </span>
        <?php endforeach; ?>
    </div>
</div>

<!-- 検索・一括操作エリア -->
<div class="content-card mb-4">
    <div class="row">
        <!-- 検索 -->
        <div class="col-md-7 border-right">
            <h5 class="mb-3"><i class="fas fa-search"></i> リアルタイム検索</h5>
            <div class="d-flex flex-wrap gap-2">
                <input type="text" id="hotelSearch" class="form-control" style="max-width:300px;"
                    placeholder="キーワード（名前・エリア・住所）">

                <select id="areaFilter" class="form-control" style="max-width:200px;">
                    <option value="">全てのエリア</option>
                    <?php foreach ($areas as $area): ?>
                        <option value="<?php echo h($area); ?>"><?php echo h($area); ?></option>
                    <?php endforeach; ?>
                </select>

                <select id="symbolFilter" class="form-control" style="max-width:150px;">
                    <option value="">全ての状況</option>
                    <option value="◯">◯ (派遣可能 - 青)</option>
                    <option value="※">※ (条件付き - 緑)</option>
                    <option value="△">△ (要確認 - 黄)</option>
                    <option value="×">× (派遣不可 - 赤)</option>
                </select>
                <button type="button" class="btn btn-secondary" onclick="resetFilters()">リセット</button>
            </div>
        </div>
        <!-- エクスポート -->
        <div class="col-md-5 pl-4">
            <h5 class="mb-3"><i class="fas fa-file-excel"></i> Excel操作</h5>
            <div style="display: flex; flex-wrap: wrap; justify-content: center; gap: 12px;">
                <a href="export.php?tenant=<?php echo h($tenantSlug); ?>" class="btn btn-accent">
                    <i class="fas fa-file-export"></i> Excelファイルダウンロード
                </a>
                <button type="button" class="btn btn-success" onclick="document.getElementById('importFile').click()">
                    <i class="fas fa-file-import"></i> Excelファイルアップロード
                </button>
                <form id="importForm" action="import.php?tenant=<?php echo h($tenantSlug); ?>" method="post"
                    enctype="multipart/form-data" style="display:none;">
                    <input type="file" id="importFile" name="excel_file" accept=".xlsx, .xls, .csv"
                        onchange="this.form.submit();">
                </form>
            </div>
            <div style="margin-top: 16px; text-align: center;">
                <form method="post" action="index.php?tenant=<?php echo h($tenantSlug); ?>"
                    onsubmit="return confirm('登録されているホテルをすべて削除します。この操作は取り消せません。よろしいですか？');">
                    <input type="hidden" name="delete_all_hotels" value="1">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash-alt"></i> ホテルリスト一括削除
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="content-card">
    <div style="text-align: center; margin-bottom: 16px;">
        <a href="edit.php?tenant=<?php echo h($tenantSlug); ?>" class="btn btn-primary">
            <i class="fas fa-plus"></i> 新規登録
        </a>
    </div>
    <div class="table-responsive">
        <table class="table" style="color:var(--text-light); width:100%;">
            <thead>
                <tr style="border-bottom: 1px solid var(--border-color);">
                    <th style="padding:15px; width: 60px;">ID</th>
                    <th style="padding:15px; width: 80px;">状況</th>
                    <th style="padding:15px;">ホテル名</th>
                    <th style="padding:15px;">エリア</th>
                    <th style="padding:15px;">交通費</th>
                    <th style="padding:15px; width: 150px;">操作</th>
                </tr>
            </thead>
            <tbody id="hotelTableBody">
                <?php foreach ($hotels as $hotel): ?>
                    <tr class="hotel-row" data-name="<?php echo h($hotel['name']); ?>"
                        data-area="<?php echo h($hotel['area']); ?>" data-address="<?php echo h($hotel['address']); ?>"
                        data-symbol="<?php echo h($hotel['symbol']); ?>"
                        data-is-love-hotel="<?php echo $hotel['is_love_hotel']; ?>"
                        style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding:15px;"><?php echo $hotel['id']; ?></td>
                        <td style="padding:15px;">
                            <span style="color:<?php
                            $s = $hotel['symbol'];
                            echo ($s === '◯') ? 'var(--accent)' :
                                ($s === '※' ? 'var(--success)' :
                                    ($s === '△' ? 'var(--warning)' : 'var(--danger)'));
                            ?>; font-weight: bold; font-size: 1.2rem;">
                                <?php echo h($hotel['symbol']); ?>
                            </span>
                        </td>
                        <td style="padding:15px;">
                            <strong style="font-size:1.1rem;"><?php echo h($hotel['name']); ?></strong>
                            <?php if ($hotel['is_love_hotel']): ?>
                                <span class="badge"
                                    style="background:hotpink; color:white; font-size:0.7rem; margin-left:5px; vertical-align:middle;">LOVE</span>
                            <?php endif; ?><br>
                            <small style="color:var(--text-muted);"><?php echo h($hotel['address']); ?></small>
                        </td>
                        <td style="padding:15px;"><?php echo h($hotel['area']); ?></td>
                        <td style="padding:15px;"><?php echo h($hotel['cost']); ?></td>
                        <td style="padding:15px;">
                            <div class="d-flex gap-2">
                                <a href="edit.php?tenant=<?php echo h($tenantSlug); ?>&id=<?php echo $hotel['id']; ?>"
                                    class="edit-title-btn">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <form method="post" action="" style="display:inline-block;"
                                    onsubmit="return confirm('本当に削除しますか？');">
                                    <input type="hidden" name="delete_id" value="<?php echo $hotel['id']; ?>">
                                    <button type="submit" class="delete-section-btn" style="padding: 6px 12px;">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    <?php if (!empty($success) && strpos($success, 'インポート') !== false): ?>window.showUploadAlert = true;<?php endif; ?>
    function resetFilters() {
        document.getElementById('hotelSearch').value = '';
        document.getElementById('areaFilter').value = '';
        document.getElementById('symbolFilter').value = '';
        applyFilters();
    }

    function applyFilters() {
        const keyword = document.getElementById('hotelSearch').value.toLowerCase();
        const area = document.getElementById('areaFilter').value;
        const symbol = document.getElementById('symbolFilter').value;

        const rows = document.querySelectorAll('.hotel-row');

        rows.forEach(row => {
            const name = row.dataset.name.toLowerCase();
            const address = row.dataset.address.toLowerCase();
            const rowArea = row.dataset.area;
            const rowSymbol = row.dataset.symbol;
            const isLoveHotel = row.dataset.isLoveHotel === '1';

            const keywordMatch = !keyword || name.includes(keyword) || address.includes(keyword) || rowArea.toLowerCase().includes(keyword);
            const areaMatch = !area || rowArea === area;

            // 派遣状況フィルター：○が選択された場合、ラブホテルもヒットさせる
            let symbolMatch = true;
            if (symbol) {
                if (symbol === '◯' || symbol === '○') {
                    symbolMatch = (rowSymbol === '◯' || rowSymbol === '○' || isLoveHotel);
                } else {
                    symbolMatch = (rowSymbol === symbol);
                }
            }

            if (keywordMatch && areaMatch && symbolMatch) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    document.getElementById('hotelSearch').addEventListener('input', applyFilters);
    document.getElementById('areaFilter').addEventListener('change', applyFilters);
    document.getElementById('symbolFilter').addEventListener('change', applyFilters);

    // 派遣方法テキスト編集モーダル
    const tenantSlug = <?php echo json_encode($tenantSlug); ?>;
    let currentDispatchType = null;

    const DISPATCH_EDITOR_ID = 'dispatchTextArea';

    document.querySelectorAll('.dispatch-edit-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const type = this.dataset.type;
            const label = this.dataset.label;
            currentDispatchType = type;
            document.getElementById('dispatchModalTitle').textContent = label + ' のテキスト編集';
            document.getElementById('dispatchTextModal').style.display = 'flex';
            document.getElementById(DISPATCH_EDITOR_ID).value = '';
            fetch('dispatch_texts.php?tenant=' + encodeURIComponent(tenantSlug) + '&type=' + encodeURIComponent(type))
                .then(r => r.json())
                .then(data => {
                    document.getElementById(DISPATCH_EDITOR_ID).value = data.content || '';
                })
                .catch(() => { document.getElementById(DISPATCH_EDITOR_ID).value = ''; });
        });
    });

    document.getElementById('dispatchSaveBtn').addEventListener('click', function () {
        if (!currentDispatchType) return;
        const content = document.getElementById(DISPATCH_EDITOR_ID).value;
        const formData = new FormData();
        formData.append('tenant', tenantSlug);
        formData.append('type', currentDispatchType);
        formData.append('content', content);
        fetch('dispatch_texts.php', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('保存しました。');
                    closeDispatchModal();
                } else {
                    alert('保存に失敗しました: ' + (data.error || data.message || ''));
                }
            })
            .catch(() => alert('保存に失敗しました。'));
    });

    function closeDispatchModal() {
        document.getElementById('dispatchTextModal').style.display = 'none';
        currentDispatchType = null;
    }

    document.getElementById('dispatchTextModal').addEventListener('click', function (e) {
        if (e.target === this) closeDispatchModal();
    });

    document.getElementById('dispatchResetBtn').addEventListener('click', function () {
        if (!currentDispatchType) return;
        if (!confirm('基本テキストに戻します。反映するには「保存」を押してください。')) return;
        fetch('dispatch_texts.php?tenant=' + encodeURIComponent(tenantSlug) + '&type=' + encodeURIComponent(currentDispatchType) + '&default=1')
            .then(r => r.json())
            .then(data => {
                document.getElementById(DISPATCH_EDITOR_ID).value = data.content || '';
            })
            .catch(() => alert('取得に失敗しました。'));
    });

    if (window.showUploadAlert) {
        alert('アップロードしました。');
        var u = new URL(window.location.href);
        u.searchParams.delete('success');
        u.searchParams.delete('error');
        window.history.replaceState({}, '', u.toString());
    }
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>