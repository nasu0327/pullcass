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

<div class="page-header">
    <h1><i class="fas fa-list"></i>
        <?php echo h($pageTitle); ?>
    </h1>
    <div class="header-actions">
        <a href="edit.php?tenant=<?php echo h($tenantSlug); ?>" class="btn btn-primary">
            <i class="fas fa-plus"></i> 新規登録
        </a>
    </div>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success">
        <?php echo h($success); ?>
    </div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert alert-danger">
        <?php echo h($error); ?>
    </div>
<?php endif; ?>

<!-- インポート・エクスポート -->
<div class="card mb-4">
    <div class="card-header bg-light">
        <strong><i class="fas fa-file-excel"></i> Excel一括操作</strong>
    </div>
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-md-6 border-right">
                <h5 class="card-title">データインポート</h5>
                <form action="import.php?tenant=<?php echo h($tenantSlug); ?>" method="post"
                    enctype="multipart/form-data" class="form-inline">
                    <div class="form-group mr-2">
                        <input type="file" name="excel_file" class="form-control-file" accept=".xlsx, .xls, .csv"
                            required>
                    </div>
                    <button type="submit" class="btn btn-success"
                        onclick="return confirm('現在のデータを上書き・更新します。よろしいですか？');">
                        <i class="fas fa-file-import"></i> アップロードしてインポート
                    </button>
                    <small class="form-text text-muted w-100 mt-2">
                        ※Excelのタブ名が「エリア」として登録されます。
                    </small>
                </form>
            </div>
            <div class="col-md-6 pl-4">
                <h5 class="card-title">データエクスポート</h5>
                <p class="text-muted">現在の登録データをExcel形式でダウンロードします。</p>
                <a href="export.php?tenant=<?php echo h($tenantSlug); ?>" class="btn btn-info">
                    <i class="fas fa-file-export"></i> Excelダウンロード
                </a>
            </div>
        </div>
    </div>
</div>

<!-- 検索カード -->
<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="form-inline">
            <input type="hidden" name="tenant" value="<?php echo h($tenantSlug); ?>">
            <input type="text" name="keyword" class="form-control mr-2" placeholder="キーワード検索"
                value="<?php echo h($keyword); ?>">

            <select name="area" class="form-control mr-2">
                <option value="">全てのエリア</option>
                <?php foreach ($areas as $area): ?>
                    <option value="<?php echo h($area); ?>" <?php echo $areaFilter === $area ? 'selected' : ''; ?>>
                        <?php echo h($area); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="symbol" class="form-control mr-2">
                <option value="">全ての状況</option>
                <option value="◯" <?php echo $symbolFilter === '◯' ? 'selected' : ''; ?>>◯ (派遣可能)</option>
                <option value="※" <?php echo $symbolFilter === '※' ? 'selected' : ''; ?>>※ (条件付き)</option>
                <option value="△" <?php echo $symbolFilter === '△' ? 'selected' : ''; ?>>△ (要確認)</option>
                <option value="×" <?php echo $symbolFilter === '×' ? 'selected' : ''; ?>>× (派遣不可)</option>
            </select>

            <button type="submit" class="btn btn-secondary">検索</button>
            <a href="index.php?tenant=<?php echo h($tenantSlug); ?>" class="btn btn-link">リセット</a>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th style="width: 50px;">ID</th>
                    <th style="width: 80px;">状況</th>
                    <th>ホテル名</th>
                    <th>エリア</th>
                    <th>コスト</th>
                    <th>ラブホ</th>
                    <th style="width: 150px;">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($hotels as $hotel): ?>
                    <tr>
                        <td>
                            <?php echo $hotel['id']; ?>
                        </td>
                        <td>
                            <span class="badge badge-<?php
                            echo $hotel['symbol'] === '◯' ? 'success' :
                                ($hotel['symbol'] === '※' ? 'info' :
                                    ($hotel['symbol'] === '△' ? 'warning' : 'danger'));
                            ?>">
                                <?php echo h($hotel['symbol']); ?>
                            </span>
                        </td>
                        <td>
                            <strong>
                                <?php echo h($hotel['name']); ?>
                            </strong><br>
                            <small class="text-muted">
                                <?php echo h($hotel['address']); ?>
                            </small>
                        </td>
                        <td>
                            <?php echo h($hotel['area']); ?>
                        </td>
                        <td>
                            <?php echo h($hotel['cost']); ?>
                        </td>
                        <td>
                            <?php echo $hotel['is_love_hotel'] ? '<span class="badge badge-pink" style="background:hotpink;color:white;">Love</span>' : '-'; ?>
                        </td>
                        <td>
                            <a href="edit.php?tenant=<?php echo h($tenantSlug); ?>&id=<?php echo $hotel['id']; ?>"
                                class="btn btn-sm btn-info">
                                <i class="fas fa-edit"></i> 編集
                            </a>
                            <form method="post" action="" style="display:inline-block;"
                                onsubmit="return confirm('本当に削除しますか？');">
                                <input type="hidden" name="delete_id" value="<?php echo $hotel['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($hotels)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">データがありません</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>