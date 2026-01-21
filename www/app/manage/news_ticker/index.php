<?php
/**
 * pullcass - ニュースティッカー管理
 * 店舗管理画面
 */

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

// ログイン認証チェック
requireTenantAdminLogin();

$error = '';
$success = '';
$text = '';
$url = '';

// 新規登録処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $text = $_POST['text'] ?? '';
    $url = $_POST['url'] ?? '';
    
    if (empty($text)) {
        $error = 'テキストを入力してください。';
    } else {
        try {
            // 既存のdisplay_orderを+1
            $pdo->prepare("UPDATE news_tickers SET display_order = display_order + 1 WHERE tenant_id = ?")->execute([$tenantId]);
            
            // 新規登録（先頭に追加）
            $sql = "INSERT INTO news_tickers (tenant_id, text, url, display_order) VALUES (?, ?, ?, 0)";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$tenantId, $text, $url])) {
                header('Location: index.php?tenant=' . urlencode($tenantSlug) . '&success=1');
                exit;
            } else {
                $error = 'データベースへの保存に失敗しました。';
            }
        } catch (PDOException $e) {
            $error = APP_DEBUG ? $e->getMessage() : 'データベースへの保存に失敗しました。';
        }
    }
}

// 更新処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $id = $_POST['id'] ?? '';
    $text = $_POST['text'] ?? '';
    $url = $_POST['url'] ?? '';
    
    if (empty($text)) {
        $error = 'テキストを入力してください。';
    } else {
        try {
            $sql = "UPDATE news_tickers SET text = ?, url = ? WHERE id = ? AND tenant_id = ?";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([$text, $url, $id, $tenantId])) {
                header('Location: index.php?tenant=' . urlencode($tenantSlug) . '&success=2');
                exit;
            } else {
                $error = 'データベースの更新に失敗しました。';
            }
        } catch (PDOException $e) {
            $error = APP_DEBUG ? $e->getMessage() : 'データベースの更新に失敗しました。';
        }
    }
}

// ニュース一覧の取得
try {
    $stmt = $pdo->prepare("SELECT * FROM news_tickers WHERE tenant_id = ? ORDER BY display_order ASC");
    $stmt->execute([$tenantId]);
    $news = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $news = [];
    $error = APP_DEBUG ? $e->getMessage() : 'データの取得に失敗しました。';
}

$pageTitle = 'ニュースティッカー管理';
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    .news-list {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    
    .news-item {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid var(--border-color);
        border-radius: 15px;
        padding: 20px;
        padding-left: 45px;
        display: flex;
        align-items: center;
        gap: 20px;
        position: relative;
        transition: all 0.3s ease;
    }
    
    .news-item:hover {
        transform: translateY(-2px);
        border-color: rgba(255, 107, 157, 0.3);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
    }
    
    .news-item.hidden-item {
        opacity: 0.5;
    }
    
    .news-item.dragging {
        opacity: 0.5;
    }
    
    .drag-handle {
        position: absolute;
        top: 50%;
        left: 12px;
        transform: translateY(-50%);
        color: var(--text-muted);
        cursor: grab;
    }
    
    .drag-handle:active {
        cursor: grabbing;
    }
    
    .news-text {
        flex: 1;
        min-width: 0;
        word-break: break-word;
    }
    
    .news-text .text-content {
        color: var(--text-light);
        margin-bottom: 5px;
    }
    
    .news-text .url-link {
        font-size: 0.85rem;
        color: var(--accent);
        text-decoration: none;
    }
    
    .news-text .url-link:hover {
        text-decoration: underline;
    }
    
    .news-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-shrink: 0;
    }
    
    .no-news {
        text-align: center;
        padding: 50px 20px;
        color: var(--text-muted);
    }
    
    .no-news i {
        font-size: 3rem;
        margin-bottom: 15px;
        opacity: 0.5;
    }
</style>

<div class="page-header">
    <div>
        <h1><i class="fas fa-bullhorn"></i> <?php echo h($pageTitle); ?></h1>
        <p style="color: var(--text-muted); margin-top: 5px;">トップページに表示される流れるニュースを管理</p>
    </div>
    <a href="https://<?php echo h($tenant['code']); ?>.pullcass.com/app/front/top.php" class="btn btn-secondary" target="_blank">
        <i class="fas fa-external-link-alt"></i> サイトで確認
    </a>
</div>

<?php if ($error): ?>
<div class="alert alert-error">
    <i class="fas fa-exclamation-circle"></i> <?php echo h($error); ?>
</div>
<?php endif; ?>

<?php if (isset($_GET['success'])): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i>
    <?php if ($_GET['success'] == 1): ?>
        ニュースを登録しました。
    <?php elseif ($_GET['success'] == 2): ?>
        ニュースを更新しました。
    <?php elseif ($_GET['success'] == 3): ?>
        ニュースを削除しました。
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- 新規登録フォーム -->
<div class="form-container">
    <h2><i class="fas fa-plus-circle"></i> 新規登録</h2>
    <form method="POST">
        <div class="form-group">
            <label for="text">テキスト <span style="color: var(--danger);">*</span></label>
            <input type="text" id="text" name="text" class="form-control" value="<?php echo h($text); ?>" required placeholder="ニュースのテキストを入力（絵文字も使用可能）">
        </div>
        <div class="form-group">
            <label for="url">リンクURL</label>
            <input type="url" id="url" name="url" class="form-control" value="<?php echo h($url); ?>" placeholder="https://example.com（任意）">
            <p class="form-help">クリック時に遷移するURLを設定できます（任意）</p>
        </div>
        <button type="submit" name="register" class="btn btn-primary">
            <i class="fas fa-plus"></i> 登録する
        </button>
    </form>
</div>

<!-- ニュース一覧 -->
<div class="content-card">
    <h2><i class="fas fa-list"></i> 登録済みニュース</h2>
    
    <div class="news-list" id="newsList">
        <?php if (count($news) > 0): ?>
            <?php foreach ($news as $item): ?>
                <div class="news-item <?php echo $item['is_visible'] ? '' : 'hidden-item'; ?>" data-id="<?php echo $item['id']; ?>">
                    <div class="drag-handle">
                        <i class="fas fa-grip-vertical"></i>
                    </div>
                    <div class="news-text">
                        <div class="text-content"><?php echo h($item['text']); ?></div>
                        <?php if (!empty($item['url'])): ?>
                        <a href="<?php echo h($item['url']); ?>" class="url-link" target="_blank" rel="noopener noreferrer">
                            <i class="fas fa-external-link-alt"></i> <?php echo h($item['url']); ?>
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="news-actions">
                        <button onclick="toggleVisibility(<?php echo $item['id']; ?>, this)" class="visibility-btn <?php echo $item['is_visible'] ? 'visible' : 'hidden'; ?>">
                            <?php echo $item['is_visible'] ? '表示中' : '非表示'; ?>
                        </button>
                        <button class="btn btn-accent btn-sm edit-btn" data-id="<?php echo $item['id']; ?>" data-text="<?php echo h($item['text']); ?>" data-url="<?php echo h($item['url'] ?? ''); ?>">
                            <i class="fas fa-edit"></i> 編集
                        </button>
                        <a href="delete.php?tenant=<?php echo h($tenantSlug); ?>&id=<?php echo $item['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('本当に削除しますか？');">
                            <i class="fas fa-trash"></i> 削除
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-news">
                <i class="fas fa-bullhorn"></i>
                <p>ニュースが登録されていません。</p>
                <p style="font-size: 0.9rem;">上のフォームから最初のニュースを登録してください。</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- 編集モーダル -->
<div class="modal-overlay" id="editModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-edit"></i> ニュース編集</h2>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form id="editForm" method="POST">
            <input type="hidden" name="id" id="editId">
            <input type="hidden" name="update" value="1">
            <div class="form-group">
                <label for="editText">テキスト <span style="color: var(--danger);">*</span></label>
                <input type="text" id="editText" name="text" class="form-control" required placeholder="ニュースのテキストを入力">
            </div>
            <div class="form-group">
                <label for="editUrl">リンクURL</label>
                <input type="url" id="editUrl" name="url" class="form-control" placeholder="https://example.com（任意）">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">キャンセル</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> 更新する
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// 表示/非表示の切り替え
function toggleVisibility(id, button) {
    fetch('toggle_visibility.php?tenant=<?php echo urlencode($tenantSlug); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ id: id })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const newsItem = button.closest('.news-item');
            if (data.is_visible) {
                button.textContent = '表示中';
                button.classList.remove('hidden');
                button.classList.add('visible');
                newsItem.classList.remove('hidden-item');
            } else {
                button.textContent = '非表示';
                button.classList.remove('visible');
                button.classList.add('hidden');
                newsItem.classList.add('hidden-item');
            }
        } else {
            alert('表示状態の更新に失敗しました。');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('表示状態の更新に失敗しました。');
    });
}

// モーダル関連
const modal = document.getElementById('editModal');

document.querySelectorAll('.edit-btn').forEach(button => {
    button.addEventListener('click', function() {
        const id = this.dataset.id;
        const text = this.dataset.text;
        const url = this.dataset.url;

        document.getElementById('editId').value = id;
        document.getElementById('editText').value = text;
        document.getElementById('editUrl').value = url;

        modal.classList.add('active');
    });
});

function closeModal() {
    modal.classList.remove('active');
}

// モーダル外クリックで閉じる
modal.addEventListener('click', function(e) {
    if (e.target === modal) {
        closeModal();
    }
});

// ドラッグ&ドロップによる並び替え
document.addEventListener('DOMContentLoaded', function() {
    const newsList = document.getElementById('newsList');
    
    if (newsList && newsList.querySelectorAll('.news-item').length > 0) {
        Sortable.create(newsList, {
            animation: 150,
            delay: 200,
            delayOnTouchOnly: true,
            ghostClass: 'dragging',
            handle: '.drag-handle',
            onEnd: function(evt) {
                const items = [...newsList.querySelectorAll('.news-item')];
                const newOrder = items.map(item => item.dataset.id);
                
                fetch('update_order.php?tenant=<?php echo urlencode($tenantSlug); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ order: newOrder })
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        alert('並び替えに失敗しました。');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('並び替えに失敗しました。');
                });
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
