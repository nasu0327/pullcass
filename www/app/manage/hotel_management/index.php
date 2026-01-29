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

// 削除処理
if (isset($_POST['delete_id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM hotels WHERE id = ?");
        $stmt->execute([(int) $_POST['delete_id']]);
        $success = '削除しました。';
    } catch (PDOException $e) {
        $error = '削除エラー: ' . $e->getMessage();
    }
}

// 検索・フィルタリング
$keyword = $_GET['keyword'] ?? '';
$areaFilter = $_GET['area'] ?? '';
$symbolFilter = $_GET['symbol'] ?? '';

$sql = "SELECT * FROM hotels WHERE 1=1";
$params = [];

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
$areas = $pdo->query("SELECT DISTINCT area FROM hotels WHERE area IS NOT NULL AND area != '' ORDER BY area")->fetchAll(PDO::FETCH_COLUMN);

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
    <div class="header-actions">
        <a href="edit.php?tenant=<?php echo h($tenantSlug); ?>" class="btn btn-primary">
            <i class="fas fa-plus"></i> 新規登録
        </a>
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
                    <option value="◯">◯ (派遣可能)</option>
                    <option value="※">※ (条件付き)</option>
                    <option value="△">△ (要確認)</option>
                    <option value="×">× (派遣不可)</option>
                </select>
                <button type="button" class="btn btn-secondary" onclick="resetFilters()">リセット</button>
            </div>
        </div>
        <!-- エクスポート -->
        <div class="col-md-5 pl-4">
            <h5 class="mb-3"><i class="fas fa-file-excel"></i> Excel操作</h5>
            <div class="d-flex gap-2">
                <a href="export.php?tenant=<?php echo h($tenantSlug); ?>" class="btn btn-accent">
                    <i class="fas fa-file-export"></i> Excel出力
                </a>
                <button type="button" class="btn btn-success" onclick="document.getElementById('importFile').click()">
                    <i class="fas fa-file-import"></i> インポート
                </button>
                <form id="importForm" action="import.php?tenant=<?php echo h($tenantSlug); ?>" method="post"
                    enctype="multipart/form-data" style="display:none;">
                    <input type="file" id="importFile" name="excel_file" accept=".xlsx, .xls, .csv"
                        onchange="if(confirm('現在のデータを上書きします。よろしいですか？')) this.form.submit();">
                </form>
            </div>
        </div>
    </div>
</div>

<div class="content-card">
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
                        style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding:15px;"><?php echo $hotel['id']; ?></td>
                        <td style="padding:15px;">
                            <span class="badge" style="background:<?php
                            echo $hotel['symbol'] === '◯' ? 'var(--success)' :
                                ($hotel['symbol'] === '※' ? 'var(--accent)' :
                                    ($hotel['symbol'] === '△' ? 'var(--warning)' : 'var(--danger)'));
                            ?>; color:white; padding:4px 10px; border-radius:10px;">
                                <?php echo h($hotel['symbol']); ?>
                            </span>
                        </td>
                        <td style="padding:15px;">
                            <strong style="font-size:1.1rem;"><?php echo h($hotel['name']); ?></strong><br>
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

            const keywordMatch = !keyword || name.includes(keyword) || address.includes(keyword) || rowArea.toLowerCase().includes(keyword);
            const areaMatch = !area || rowArea === area;
            const symbolMatch = !symbol || rowSymbol === symbol;

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
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>