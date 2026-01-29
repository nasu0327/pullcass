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

// 初期値
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$hotel = [
    'name' => '',
    'symbol' => '◯',
    'area' => '博多区のビジネスホテル',
    'address' => '',
    'phone' => '',
    'cost' => '',
    'method' => '',
    'is_love_hotel' => 0,
    'hotel_description' => '',
    'lat' => '',
    'lng' => '',
    'sort_order' => 0
];
$error = null;
$success = null;

// POST処理（保存）
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // データ取得
    $hotel['name'] = trim($_POST['name'] ?? '');
    $hotel['symbol'] = $_POST['symbol'] ?? '◯';
    $hotel['area'] = trim($_POST['area'] ?? '');
    $hotel['address'] = trim($_POST['address'] ?? '');
    $hotel['phone'] = trim($_POST['phone'] ?? '');
    $hotel['cost'] = trim($_POST['cost'] ?? '');
    $hotel['method'] = trim($_POST['method'] ?? '');
    $hotel['is_love_hotel'] = isset($_POST['is_love_hotel']) ? 1 : 0;
    $hotel['hotel_description'] = trim($_POST['hotel_description'] ?? '');
    $hotel['lat'] = $_POST['lat'] === '' ? null : $_POST['lat'];
    $hotel['lng'] = $_POST['lng'] === '' ? null : $_POST['lng'];
    $hotel['sort_order'] = (int) ($_POST['sort_order'] ?? 0);

    // バリデーション
    if ($hotel['name'] === '') {
        $error = 'ホテル名を入力してください。';
    }

    if (!$error) {
        try {
            if ($id) {
                // 更新
                $stmt = $pdo->prepare("UPDATE hotels SET 
                    name = ?, symbol = ?, area = ?, address = ?, phone = ?, 
                    cost = ?, method = ?, is_love_hotel = ?, hotel_description = ?, 
                    lat = ?, lng = ?, sort_order = ?
                    WHERE id = ?");
                $stmt->execute([
                    $hotel['name'],
                    $hotel['symbol'],
                    $hotel['area'],
                    $hotel['address'],
                    $hotel['phone'],
                    $hotel['cost'],
                    $hotel['method'],
                    $hotel['is_love_hotel'],
                    $hotel['hotel_description'],
                    $hotel['lat'],
                    $hotel['lng'],
                    $hotel['sort_order'],
                    $id
                ]);
                $success = '更新しました。';
            } else {
                // 新規作成
                $stmt = $pdo->prepare("INSERT INTO hotels (
                    name, symbol, area, address, phone, cost, method, 
                    is_love_hotel, hotel_description, lat, lng, sort_order
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $hotel['name'],
                    $hotel['symbol'],
                    $hotel['area'],
                    $hotel['address'],
                    $hotel['phone'],
                    $hotel['cost'],
                    $hotel['method'],
                    $hotel['is_love_hotel'],
                    $hotel['hotel_description'],
                    $hotel['lat'],
                    $hotel['lng'],
                    $hotel['sort_order']
                ]);
                $id = $pdo->lastInsertId();
                $success = '登録しました。';
                // リダイレクトして重複送信防止
                header("Location: edit.php?tenant={$tenantSlug}&id={$id}&saved=1");
                exit;
            }
        } catch (PDOException $e) {
            $error = '保存エラー: ' . $e->getMessage();
        }
    }
} else {
    // GET処理（表示）
    if (isset($_GET['saved'])) {
        $success = 'データを保存しました。';
    }

    if ($id) {
        $stmt = $pdo->prepare("SELECT * FROM hotels WHERE id = ?");
        $stmt->execute([$id]);
        $fetched = $stmt->fetch();
        if ($fetched) {
            $hotel = $fetched;
        } else {
            $error = '指定されたホテルが見つかりません。';
        }
    }
}

$pageTitle = $id ? 'ホテル編集' : 'ホテル新規登録';
require_once __DIR__ . '/../includes/header.php';
?>

<?php
require_once __DIR__ . '/../includes/breadcrumb.php';
$breadcrumbs = [
    ['label' => 'ダッシュボード', 'url' => '/app/manage/?tenant=' . $tenantSlug, 'icon' => 'fas fa-chart-pie'],
    ['label' => 'ホテルリスト管理', 'url' => 'index.php?tenant=' . $tenantSlug],
    ['label' => $pageTitle]
];
renderBreadcrumb($breadcrumbs);
?>

<div class="page-header">
    <div>
        <h1><i class="fas fa-hotel"></i> <?php echo h($pageTitle); ?></h1>
        <p>ホテルの詳細情報を編集します。</p>
    </div>
    <a href="index.php?tenant=<?php echo h($tenantSlug); ?>" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> 一覧に戻る
    </a>
</div>

<?php if ($error): ?>
    <div class="alert alert-error">
        <i class="fas fa-exclamation-circle"></i> <?php echo h($error); ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?php echo h($success); ?>
    </div>
<?php endif; ?>

<div class="form-container">
    <form method="post" action="">
        <div class="form-group row">
            <label class="col-md-3 col-form-label">ホテル名 <span class="badge"
                    style="background:var(--danger); color:white;">必須</span></label>
            <div class="col-md-9">
                <input type="text" name="name" class="form-control" value="<?php echo h($hotel['name']); ?>" required>
            </div>
        </div>

        <div class="form-group row">
            <label class="col-md-3 col-form-label">エリア</label>
            <div class="col-md-9">
                <input type="text" name="area" class="form-control" value="<?php echo h($hotel['area']); ?>"
                    list="area-list" placeholder="例：博多区のビジネスホテル">
                <datalist id="area-list">
                    <option value="博多区のビジネスホテル">
                    <option value="中央区のビジネスホテル">
                    <option value="その他エリアのビジネスホテル">
                    <option value="ラブホテル一覧">
                </datalist>
            </div>
        </div>

        <div class="form-group row">
            <label class="col-md-3 col-form-label">派遣状況</label>
            <div class="col-md-9">
                <div class="d-flex flex-wrap gap-2">
                    <label class="btn btn-secondary <?php echo $hotel['symbol'] === '◯' ? 'active' : ''; ?>"
                        style="<?php echo $hotel['symbol'] === '◯' ? 'background:var(--success);border-color:var(--success);' : ''; ?>">
                        <input type="radio" name="symbol" value="◯" <?php echo $hotel['symbol'] === '◯' ? 'checked' : ''; ?> style="display:none;"> ◯ (派遣可能)
                    </label>
                    <label class="btn btn-secondary <?php echo $hotel['symbol'] === '※' ? 'active' : ''; ?>"
                        style="<?php echo $hotel['symbol'] === '※' ? 'background:var(--accent);border-color:var(--accent);' : ''; ?>">
                        <input type="radio" name="symbol" value="※" <?php echo $hotel['symbol'] === '※' ? 'checked' : ''; ?> style="display:none;"> ※ (条件付き)
                    </label>
                    <label class="btn btn-secondary <?php echo $hotel['symbol'] === '△' ? 'active' : ''; ?>"
                        style="<?php echo $hotel['symbol'] === '△' ? 'background:var(--warning);border-color:var(--warning);' : ''; ?>">
                        <input type="radio" name="symbol" value="△" <?php echo $hotel['symbol'] === '△' ? 'checked' : ''; ?> style="display:none;"> △ (要確認)
                    </label>
                    <label class="btn btn-secondary <?php echo $hotel['symbol'] === '×' ? 'active' : ''; ?>"
                        style="<?php echo $hotel['symbol'] === '×' ? 'background:var(--danger);border-color:var(--danger);' : ''; ?>">
                        <input type="radio" name="symbol" value="×" <?php echo $hotel['symbol'] === '×' ? 'checked' : ''; ?> style="display:none;"> × (派遣不可)
                    </label>
                </div>
            </div>
        </div>

        <div class="form-group row">
            <label class="col-md-3 col-form-label">住所</label>
            <div class="col-md-9">
                <input type="text" name="address" class="form-control" value="<?php echo h($hotel['address']); ?>">
            </div>
        </div>

        <div class="form-group row">
            <label class="col-md-3 col-form-label">電話番号</label>
            <div class="col-md-9">
                <input type="text" name="phone" class="form-control" value="<?php echo h($hotel['phone']); ?>">
            </div>
        </div>

        <div class="form-group row">
            <label class="col-md-3 col-form-label">交通費</label>
            <div class="col-md-9">
                <input type="text" name="cost" class="form-control" value="<?php echo h($hotel['cost']); ?>">
            </div>
        </div>

        <div class="form-group row">
            <label class="col-md-3 col-form-label">入室方法/利用方法</label>
            <div class="col-md-9">
                <textarea name="method" class="form-control" rows="3"><?php echo h($hotel['method']); ?></textarea>
                <small class="help-text">カードキー、待ち合わせ情報など</small>
            </div>
        </div>

        <div class="form-group row">
            <label class="col-md-3 col-form-label">詳細説明</label>
            <div class="col-md-9">
                <textarea name="hotel_description" class="form-control"
                    rows="5"><?php echo h($hotel['hotel_description']); ?></textarea>
                <small class="help-text">個別ページに表示される詳細文章。<code>[URL:https://...|リンク名]</code> でリンク作成可能。</small>
            </div>
        </div>

        <div class="form-group row align-items-center">
            <label class="col-md-3 col-form-label">オプション</label>
            <div class="col-md-9">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_love_hotel" <?php echo $hotel['is_love_hotel'] ? 'checked' : ''; ?>>
                    <span>ラブホテルとして扱う</span>
                </label>
            </div>
        </div>

        <div class="form-group row">
            <label class="col-md-3 col-form-label">緯度・経度</label>
            <div class="col-md-4">
                <input type="text" name="lat" class="form-control" placeholder="緯度 (lat)"
                    value="<?php echo h($hotel['lat']); ?>">
            </div>
            <div class="col-md-4">
                <input type="text" name="lng" class="form-control" placeholder="経度 (lng)"
                    value="<?php echo h($hotel['lng']); ?>">
            </div>
        </div>

        <div class="form-group row">
            <label class="col-md-3 col-form-label">表示順</label>
            <div class="col-md-3">
                <input type="number" name="sort_order" class="form-control"
                    value="<?php echo h($hotel['sort_order']); ?>">
            </div>
        </div>

        <div class="text-center mt-5">
            <button type="submit" class="btn btn-primary btn-lg" style="padding: 12px 60px; font-weight:600;">
                <i class="fas fa-save"></i> 保存する
            </button>
        </div>
    </form>
</div>

<script>
    // ラジオボタンの選択状態を視覚的に更新
    document.querySelectorAll('input[name="symbol"]').forEach(radio => {
        radio.addEventListener('change', function () {
            document.querySelectorAll('input[name="symbol"]').forEach(r => {
                const label = r.parentElement;
                label.classList.remove('active');
                label.style.background = '';
                label.style.borderColor = '';
            });

            const label = this.parentElement;
            label.classList.add('active');
            if (this.value === '◯') { label.style.background = 'var(--success)'; label.style.borderColor = 'var(--success)'; }
            else if (this.value === '※') { label.style.background = 'var(--accent)'; label.style.borderColor = 'var(--accent)'; }
            else if (this.value === '△') { label.style.background = 'var(--warning)'; label.style.borderColor = 'var(--warning)'; }
            else if (this.value === '×') { label.style.background = 'var(--danger)'; label.style.borderColor = 'var(--danger)'; }
        });
    });
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>