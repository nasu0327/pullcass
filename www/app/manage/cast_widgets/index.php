<?php
require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

// ログイン認証チェック
requireTenantAdminLogin();

$pdo = getPlatformDb();
$tenantId = $tenant['id'];
$tenantCode = $tenant['code'];

// アクティブなテーブル名を取得（通常は tenant_casts）
// functions.phpが同じ階層にないので cast_info_management のものを使うか、直接クエリするか。
// 依存関係を避けるため直接テナントIDでクエリします。
$tableName = 'tenant_casts'; // このシステムでは恐らく固定テーブル名ではなくテナントごとのビューやテーブルを使っている可能性があるが
// edit.php を参照すると `getActiveCastTable` を使っているので、それを簡易的に再現、あるいは `tenant_casts` で統一されているか確認。
// detail.php では `tenant_casts` を直接参照しているので、ここでも `tenant_casts` を使用します。

// 更新処理
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // 店舗用ウィジェット保存
        $shopDiary = $_POST['shop_diary_widget'] ?? null;
        $shopReview = $_POST['shop_review_widget'] ?? null;
        if ($shopDiary === '') $shopDiary = null;
        if ($shopReview === '') $shopReview = null;
        $stmtShop = $pdo->prepare("UPDATE tenants SET shop_diary_widget_code = ?, shop_review_widget_code = ? WHERE id = ?");
        $stmtShop->execute([$shopDiary, $shopReview, $tenantId]);

        // キャスト用ウィジェット保存
        $updates = $_POST['widgets'] ?? [];

        $stmt = $pdo->prepare("UPDATE tenant_casts SET review_widget_code = ?, diary_widget_code = ? WHERE id = ? AND tenant_id = ?");

        foreach ($updates as $castId => $data) {
            $reviewCode = $data['review'] ?? null;
            $diaryCode = $data['diary'] ?? null;

            if ($reviewCode === '')
                $reviewCode = null;
            if ($diaryCode === '')
                $diaryCode = null;

            $stmt->execute([$reviewCode, $diaryCode, $castId, $tenantId]);
        }

        $pdo->commit();
        $successMessage = 'ウィジェット設定を保存しました。';
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errorMessage = '保存中にエラーが発生しました: ' . $e->getMessage();
    }
}

// 店舗用ウィジェット取得
$shopWidgets = ['shop_diary_widget_code' => '', 'shop_review_widget_code' => ''];
try {
    $stmtShop = $pdo->prepare("SELECT shop_diary_widget_code, shop_review_widget_code FROM tenants WHERE id = ?");
    $stmtShop->execute([$tenantId]);
    $row = $stmtShop->fetch(PDO::FETCH_ASSOC);
    if ($row) $shopWidgets = $row;
} catch (Exception $e) {
    // カラム未追加の場合は空のまま
}

// キャスト一覧取得 (sort_order順)
$stmt = $pdo->prepare("SELECT id, name, img1, review_widget_code, diary_widget_code FROM tenant_casts WHERE tenant_id = ? ORDER BY sort_order ASC, id DESC");
$stmt->execute([$tenantId]);
$casts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'ウィジェット登録';
// header.phpの読み込みパス調整
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .widget-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0 15px;
    }

    .widget-row {
        background: var(--bg-card);
        border: none;
        box-shadow: var(--shadow-card);
        transition: all 0.3s ease;
    }

    .widget-row:hover {
        box-shadow: var(--shadow-card-hover);
        border-color: var(--primary-border);
    }

    .widget-cell {
        padding: 20px;
        vertical-align: top;
        border-top: 1px solid var(--border-color);
        border-bottom: 1px solid var(--border-color);
    }

    .widget-cell:first-child {
        border-left: 1px solid var(--border-color);
        border-top-left-radius: 15px;
        border-bottom-left-radius: 15px;
        width: 200px;
    }

    .widget-cell:last-child {
        border-right: 1px solid var(--border-color);
        border-top-right-radius: 15px;
        border-bottom-right-radius: 15px;
    }

    .cast-info {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 10px;
        text-align: center;
    }

    .cast-img {
        width: 80px;
        height: 80px;
        object-fit: cover;
        border-radius: 50%;
        border: 2px solid var(--border-color);
    }

    .cast-initial {
        width: 80px;
        height: 80px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        border: 2px solid var(--border-color);
        background: var(--primary-gradient);
        font-size: 1.5rem;
        font-weight: bold;
        color: var(--text-inverse);
    }

    .cast-name {
        font-weight: bold;
        font-size: 1.1em;
    }

    .widget-inputs {
        display: flex;
        gap: 20px;
        width: 100%;
    }

    .widget-input-group {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .widget-label {
        font-size: 0.85em;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .widget-textarea {
        width: 100%;
        background: var(--bg-body);
        border: 1px solid var(--border-color);
        border-radius: 8px;
        color: var(--text-primary);
        padding: 10px;
        font-size: 0.9em;
        line-height: 1.4;
        height: 4.2em;
        /* 約2.5行分 */
        resize: vertical;
        transition: border-color 0.3s;
    }

    .widget-textarea:focus {
        outline: none;
        border-color: var(--primary);
        background: var(--bg-body);
    }

    .floating-save-btn {
        position: fixed;
        bottom: 30px;
        right: 30px;
        background: var(--primary-gradient);
        color: var(--text-inverse);
        padding: 15px 30px;
        border-radius: 50px;
        font-weight: bold;
        box-shadow: var(--shadow-card);
        border: none;
        cursor: pointer;
        z-index: 100;
        display: flex;
        align-items: center;
        gap: 10px;
        transition: transform 0.2s;
    }

    .floating-save-btn:hover {
        background: var(--primary-gradient-hover);
        transform: translateY(-3px);
        box-shadow: var(--shadow-primary);
    }

    @media (max-width: 768px) {
        .widget-inputs {
            flex-direction: column;
        }

        .widget-cell:first-child {
            width: 120px;
        }

        .cast-img,
        .cast-initial {
            width: 60px;
            height: 60px;
            font-size: 1.2rem;
        }
    }
</style>

<nav class="breadcrumb-nav">
    <a href="/app/manage/?tenant=<?php echo h($tenantSlug); ?>" class="breadcrumb-item">
        <i class="fas fa-chart-pie"></i> ダッシュボード
    </a>
    <span class="breadcrumb-separator"><i class="fas fa-chevron-right"></i></span>
    <span class="breadcrumb-current">ウィジェット登録</span>
</nav>

<div class="page-header">
    <div>
        <h1><i class="fas fa-code"></i> ウィジェット登録</h1>
        <p>店舗用・キャスト個人の写メ日記・口コミ用ウィジェットコードを登録して下さい。</p>
    </div>
</div>

<div class="container">
    <?php if ($successMessage): ?>
        <script>alert('<?php echo addslashes($successMessage); ?>');</script>
    <?php endif; ?>
    <?php if ($errorMessage): ?>
        <script>alert('<?php echo addslashes($errorMessage); ?>');</script>
    <?php endif; ?>

    <form method="POST">
        <!-- 店舗用ウィジェット -->
        <div class="widget-row" style="display: flex; margin-bottom: 25px; border-radius: 15px; overflow: hidden; border: 2px solid var(--primary); box-shadow: var(--shadow-card);">
            <div class="widget-cell"
                style="width: 150px; background: linear-gradient(135deg, var(--primary), var(--primary-dark, var(--primary))); display: flex; align-items: center; justify-content: center;">
                <div class="cast-info">
                    <div style="width: 80px; height: 80px; display: flex; align-items: center; justify-content: center; border-radius: 50%; border: 2px solid rgba(255,255,255,0.5); background: rgba(255,255,255,0.2); font-size: 1.8rem; color: var(--text-inverse);">
                        <i class="fas fa-store"></i>
                    </div>
                    <div class="cast-name" style="color: var(--text-inverse);">店舗用</div>
                </div>
            </div>
            <div class="widget-cell" style="flex: 1;">
                <div style="margin-bottom: 8px; font-size: 0.85em; color: var(--text-muted);">
                    <i class="fas fa-info-circle"></i> トップページの写メ日記・口コミセクションに表示されるウィジェットです
                </div>
                <div class="widget-inputs">
                    <div class="widget-input-group">
                        <label class="widget-label"><i class="fas fa-camera"></i> 写メ日記ウィジェット（トップページ用）</label>
                        <textarea name="shop_diary_widget" class="widget-textarea" style="height: 5.6em;"
                            placeholder="<script>..."><?php echo h($shopWidgets['shop_diary_widget_code'] ?? ''); ?></textarea>
                    </div>
                    <div class="widget-input-group">
                        <label class="widget-label"><i class="fas fa-comments"></i> 口コミウィジェット（トップページ用）</label>
                        <textarea name="shop_review_widget" class="widget-textarea" style="height: 5.6em;"
                            placeholder="<script>..."><?php echo h($shopWidgets['shop_review_widget_code'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div style="border-top: 1px solid var(--border-color); margin: 10px 0 20px; padding-top: 15px;">
            <h3 style="margin: 0 0 5px; font-size: 1em; color: var(--text-muted);"><i class="fas fa-users"></i> キャスト個人用ウィジェット</h3>
        </div>

        <div class="widget-list">
            <?php foreach ($casts as $cast):
                $first_letter = mb_substr($cast['name'], 0, 1, 'UTF-8');
                ?>
                <div class="widget-row" style="display: flex; margin-bottom: 15px; border-radius: 15px; overflow: hidden;">
                    <!-- キャスト情報 -->
                    <div class="widget-cell"
                        style="width: 150px; background: var(--primary-bg); display: flex; align-items: center; justify-content: center;">
                        <div class="cast-info">
                            <?php if ($cast['img1']): ?>
                                <img src="<?php echo h($cast['img1']); ?>" alt="<?php echo h($cast['name']); ?>"
                                    class="cast-img">
                            <?php else: ?>
                                <div class="cast-initial">
                                    <?php echo h($first_letter); ?>
                                </div>
                            <?php endif; ?>
                            <div class="cast-name">
                                <?php echo h($cast['name']); ?>
                            </div>
                        </div>
                    </div>

                    <!-- ウィジェット入力 -->
                    <div class="widget-cell" style="flex: 1;">
                        <div class="widget-inputs">
                            <div class="widget-input-group">
                                <label class="widget-label"><i class="fas fa-camera"></i> 写メ日記ウィジェット</label>
                                <textarea name="widgets[<?php echo $cast['id']; ?>][diary]" class="widget-textarea"
                                    placeholder="<script>..."><?php echo h($cast['diary_widget_code']); ?></textarea>
                            </div>
                            <div class="widget-input-group">
                                <label class="widget-label"><i class="fas fa-comments"></i> 口コミウィジェット</label>
                                <textarea name="widgets[<?php echo $cast['id']; ?>][review]" class="widget-textarea"
                                    placeholder="<script>..."><?php echo h($cast['review_widget_code']); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <button type="submit" class="floating-save-btn">
            <i class="fas fa-save"></i> 一括保存する
        </button>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>