<?php
require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

$pageTitle = 'キャスト情報管理';
$currentPage = 'cast_info_management';

$pdo = getPlatformDb();
$tenantId = $tenant['id'];

require_once __DIR__ . '/functions.php';

// 成功メッセージの取得
$success = isset($_GET['success']) ? $_GET['success'] : '';

// アクティブなテーブル名を取得
$tableName = getActiveCastTable($pdo, $tenantId);

// キャスト一覧を取得（sort_order順）
$stmt = $pdo->prepare("SELECT id, name, img1, age, height, cup, sort_order, checked FROM {$tableName} WHERE tenant_id = ? ORDER BY sort_order ASC, id DESC");
$stmt->execute([$tenantId]);
$casts = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../includes/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<style>
    /* 管理画面共通スタイルとの調整 */
    .container {
        max-width: 1400px;
        margin: 0 auto;
    }

    .top-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        gap: 15px;
    }

    .search-box {
        flex: 1;
        max-width: 400px;
    }

    .search-box input {
        width: 100%;
        padding: 12px 16px;
        border: 1px solid var(--border-color);
        border-radius: 25px;
        background: var(--bg-card);
        color: var(--text-primary);
        font-size: 14px;
        transition: all 0.3s ease;
    }

    .search-box input:focus {
        outline: none;
        border-color: var(--primary);
        background: var(--bg-card);
    }

    .search-box input::placeholder {
        color: var(--text-muted);
    }

    .add-button {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 24px;
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: var(--text-inverse);
        text-decoration: none;
        border-radius: 25px;
        font-weight: 600;
        transition: all 0.3s ease;
        white-space: nowrap;
        border: none;
    }

    .add-button:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-card-hover);
        color: var(--text-inverse);
    }

    /* キャストカードのグリッド表示 */
    .cast-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 30px;
    }

    .cast-card {
        background: var(--bg-card);
        border-radius: 15px;
        padding: 20px;
        border: none;
        box-shadow: var(--shadow-card);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        text-align: center;
        display: flex;
        flex-direction: column;
        align-items: center;
        cursor: move;
        /* ドラッグ可能であることを示すカーソル */
    }

    .cast-card:hover {
        transform: translateY(-5px);
        box-shadow: var(--shadow-card-hover);
        border-color: var(--primary-border);
    }

    .cast-card.hidden-cast {
        opacity: 0.6;
        border-color: var(--danger-border);
    }

    .hidden-badge {
        position: absolute;
        top: 8px;
        right: 8px;
        background: var(--danger);
        color: var(--text-inverse);
        font-size: 10px;
        font-weight: 600;
        padding: 3px 8px;
        border-radius: 10px;
        z-index: 10;
    }

    .cast-card.dragging {
        opacity: 0.5;
        box-shadow: var(--shadow-card);
    }

    .cast-image {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        object-fit: cover;
        margin-bottom: 12px;
        border: 3px solid var(--border-color);
    }

    .cast-initial {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 12px;
        border: 3px solid var(--border-color);
        font-size: 1.8rem;
        font-weight: 700;
        color: var(--text-inverse);
    }

    .cast-name {
        font-size: 1.1rem;
        font-weight: 600;
        margin-bottom: 8px;
        color: var(--text-primary);
    }

    .cast-details {
        font-size: 0.85rem;
        color: var(--text-muted);
        margin-bottom: 15px;
        line-height: 1.5;
        min-height: 1.3em;
        /* 空でも高さを確保 */
    }

    .cast-actions {
        display: flex;
        gap: 8px;
        justify-content: center;
        margin-top: auto;
        /* 下部に固定 */
    }

    .success {
        color: var(--success);
        margin-bottom: 20px;
        padding: 15px 20px;
        background: var(--success-bg);
        border-radius: 10px;
        border: 1px solid var(--success-border);
    }

    .no-casts {
        text-align: center;
        padding: 60px 20px;
        color: var(--text-muted);
        background: var(--bg-card);
        border-radius: 15px;
        grid-column: 1 / -1;
    }

    .no-casts .material-icons,
    .no-casts .fas {
        font-size: 64px;
        margin-bottom: 20px;
        opacity: 0.5;
    }

    @media (max-width: 768px) {
        .top-actions {
            flex-direction: column;
            align-items: stretch;
        }

        .search-box {
            max-width: 100%;
        }

        /* モバイルでのドラッグハンドル最適化 */
        .cast-card {
            touch-action: none;
            /* ブラウザ標準のスクロール操作等と干渉しないように */
        }
    }
</style>

<nav class="breadcrumb-nav">
    <a href="/app/manage/?tenant=<?php echo h($tenantSlug); ?>" class="breadcrumb-item">
        <i class="fas fa-chart-pie"></i> ダッシュボード
    </a>
    <span class="breadcrumb-separator"><i class="fas fa-chevron-right"></i></span>
    <span class="breadcrumb-current">キャスト情報管理</span>
</nav>

<div class="page-header">
    <div>
        <h1><i class="fas fa-users-cog"></i> キャスト情報管理</h1>
        <p>キャストの編集・削除・並び順変更（ドラッグ&ドロップで並び替え）</p>
    </div>
</div>

<div class="container">
    <?php if ($success): ?>
        <div class="success"><i class="fas fa-check-circle"></i>
            <?php echo h($success); ?>
        </div>
    <?php endif; ?>

    <div class="top-actions">
        <div class="search-box">
            <input type="text" id="castSearch" placeholder="キャスト名で検索...">
        </div>
    </div>

    <div class="cast-grid" id="castList">
        <?php if (count($casts) > 0): ?>
            <?php foreach ($casts as $cast):
                $first_letter = mb_substr($cast['name'], 0, 1, 'UTF-8');
                $isHidden = isset($cast['checked']) && $cast['checked'] == 0;
                ?>
                <div class="cast-card<?php echo $isHidden ? ' hidden-cast' : ''; ?>" data-id="<?php echo $cast['id']; ?>"
                    data-cast-name="<?php echo h($cast['name']); ?>" draggable="true">
                    <?php if ($isHidden): ?>
                        <span class="hidden-badge">非表示中</span>
                    <?php endif; ?>
                    <?php if ($cast['img1']): ?>
                        <img src="<?php echo h($cast['img1']); ?>" alt="<?php echo h($cast['name']); ?>" class="cast-image">
                    <?php else: ?>
                        <div class="cast-initial">
                            <?php echo h($first_letter); ?>
                        </div>
                    <?php endif; ?>

                    <div class="cast-name">
                        <?php echo h($cast['name']); ?>
                    </div>
                    <div class="cast-details">
                        <?php
                        $details = [];
                        if (!empty($cast['age']))
                            $details[] = $cast['age'] . '歳';
                        if (!empty($cast['height']))
                            $details[] = $cast['height'] . 'cm';
                        if (!empty($cast['cup']))
                            $details[] = $cast['cup'];
                        echo h(implode(' / ', $details));
                        ?>
                    </div>

                    <div class="cast-actions">
                        <a href="edit.php?id=<?php echo $cast['id']; ?>" class="btn-icon" data-tooltip="編集">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="delete.php?id=<?php echo $cast['id']; ?>" class="btn-icon btn-icon-danger" data-tooltip="削除"
                            onclick="return confirm('本当に「<?php echo h($cast['name']); ?>」を削除しますか？\n\nこの操作は取り消せません。');">
                            <i class="fas fa-trash-alt"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-casts">
                <i class="fas fa-user-slash"></i>
                <p>登録されているキャストはありません。</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // 成功メッセージの自動クリア
    <?php if ($success): ?>
        if (window.history.replaceState) {
            const url = new URL(window.location);
            url.searchParams.delete('success');
            window.history.replaceState({}, document.title, url.pathname);
        }
    <?php endif; ?>

    // キャスト検索機能
    document.getElementById('castSearch').addEventListener('input', function (e) {
        const searchTerm = e.target.value.toLowerCase();
        const castCards = document.querySelectorAll('.cast-card');

        castCards.forEach(card => {
            const name = card.dataset.castName.toLowerCase();
            if (name.includes(searchTerm)) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    });

    // ドラッグ&ドロップによる並び替え
    document.addEventListener('DOMContentLoaded', function () {
        const castList = document.getElementById('castList');

        // SortableJSで並び替え機能を初期化
        if (castList && castList.children.length > 0) {
            Sortable.create(castList, {
                animation: 150,
                delay: 200,
                delayOnTouchOnly: true,
                ghostClass: 'dragging',
                dragClass: 'dragging',
                handle: '.cast-card', // カード全体、あるいはハンドルでドラッグ。PCなら全体でもいいが誤操作防止でハンドルが無難か。参照元は .cast-card が handle になっていた気がするが...
                // 参照元: handle: '.cast-card' と書いてある。しかし .drag-handle もある。
                // ここでは .cast-card をハンドルにするとテキスト選択しにくくなるので、.drag-handle をハンドルにすることも検討すべきだが、
                // Mobile対応を考えるとカード全体の方が使いやすいこともある。一旦参照元通り .cast-card をハンドルにするが、
                // 参照元のCSSで .cast-card { cursor: move; } となっているので全体掴める仕様。
                onEnd: function (evt) {
                    // 並び替え後の順序を取得
                    const items = [...castList.querySelectorAll('.cast-card')];
                    const newOrder = items.map(item => item.dataset.id);

                    // サーバーに新しい順序を送信
                    fetch('update_order.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ order: newOrder })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // 成功時はアラート表示
                                alert('並び替えを保存しました');
                            } else {
                                alert('並び替えの保存に失敗しました: ' + (data.message || 'Unknown error'));
                                location.reload();
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('並び替えの保存に通信エラーが発生しました');
                        });
                }
            });
        }
    });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>