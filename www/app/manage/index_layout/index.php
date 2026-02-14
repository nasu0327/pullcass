<?php
/**
 * インデックスページ（年齢確認ページ）レイアウト管理
 * 1カラム構成
 */
require_once __DIR__ . '/../../../includes/bootstrap.php';

// テナント認証
require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

$pdo = getPlatformDb();

// 現在のステータスを判定
try {
    // 編集中のセクション数
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM index_layout_sections WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $editCount = $stmt->fetchColumn();

    // 公開済みのセクション数
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM index_layout_sections_published WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $publishedCount = $stmt->fetchColumn();

    // 公開済みテーブルが空、または内容が異なる場合は「編集中」
    if ($publishedCount == 0) {
        $currentStatus = 'new';
        $statusLabel = '未公開';
        $statusClass = 'status-draft';
    } else {
        // 簡易比較
        $stmt = $pdo->prepare("SELECT MD5(GROUP_CONCAT(CONCAT(id, '-', display_order, '-', is_visible) ORDER BY id)) FROM index_layout_sections WHERE tenant_id = ?");
        $stmt->execute([$tenantId]);
        $editHash = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT MD5(GROUP_CONCAT(CONCAT(id, '-', display_order, '-', is_visible) ORDER BY id)) FROM index_layout_sections_published WHERE tenant_id = ?");
        $stmt->execute([$tenantId]);
        $publishedHash = $stmt->fetchColumn();

        if ($editHash === $publishedHash) {
            $currentStatus = 'published';
            $statusLabel = '公開済み';
            $statusClass = 'status-published';
        } else {
            $currentStatus = 'draft';
            $statusLabel = '編集中（未保存の変更あり）';
            $statusClass = 'status-draft';
        }
    }
} catch (PDOException $e) {
    $currentStatus = 'unknown';
    $statusLabel = '状態不明';
    $statusClass = 'status-draft';
}

// デフォルトセクションのキーリスト
require_once __DIR__ . '/../../../includes/index_layout_init.php';
$defaultSectionKeys = getIndexDefaultSectionKeys();

// セクションが存在しない場合は作成
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM index_layout_sections WHERE tenant_id = ?");
    $stmt->execute([$tenantId]);
    $sectionCount = $stmt->fetchColumn();

    if ($sectionCount == 0) {
        initIndexLayoutSections($pdo, $tenantId);
    } else {
        // 必要なセクションが不足しているかチェック
        $requiredSections = ['hero', 'reciprocal_links'];
        $stmt = $pdo->prepare("SELECT section_key FROM index_layout_sections WHERE tenant_id = ?");
        $stmt->execute([$tenantId]);
        $existingSections = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $missingSections = array_diff($requiredSections, $existingSections);

        if (!empty($missingSections)) {
            addMissingIndexSections($pdo, $tenantId, $missingSections);
        }
    }
} catch (Exception $e) {
    error_log("デフォルトセクション作成エラー: " . $e->getMessage());
}

// セクション取得（display_order順）
try {
    $stmt = $pdo->prepare("
        SELECT * FROM index_layout_sections 
        WHERE tenant_id = ?
        ORDER BY display_order ASC
    ");
    $stmt->execute([$tenantId]);
    $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 上部エリア（ヒーローセクション）を分離
    $heroSection = null;
    $otherSections = [];
    foreach ($sections as $section) {
        if ($section['section_key'] === 'hero') {
            $heroSection = $section;
        } else {
            $otherSections[] = $section;
        }
    }
} catch (PDOException $e) {
    $error = "データの取得に失敗しました: " . $e->getMessage();
    $sections = [];
    $heroSection = null;
    $otherSections = [];
}

$tenantSlugJson = json_encode($tenantSlug);
?>

<?php
$pageTitle = '認証ページ編集';
require_once __DIR__ . '/../includes/header.php';
?>
<style>

        .column-section {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 25px;
            border: none;
            box-shadow: var(--shadow-card);
            margin-bottom: 30px;
        }

        .column-title {
            font-size: 1.3rem;
            font-weight: bold;
            color: var(--text-primary);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            min-height: 50px;
        }

        .section-card {
            background: var(--bg-card);
            border: none;
            border-radius: 12px;
            padding: 15px 20px;
            cursor: grab;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--shadow-card);
        }

        .section-card:active {
            cursor: grabbing;
        }

        .section-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-card-hover);
        }

        .section-card.sortable-ghost {
            opacity: 0.4;
        }

        .section-card.sortable-drag {
            opacity: 0.8;
            box-shadow: var(--shadow-lg);
        }

        .section-card.hidden {
            opacity: 0.5;
            background: var(--bg-body);
        }

        .section-info {
            display: flex;
            align-items: center;
            gap: 15px;
            flex: 1;
        }

        .admin-title-label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-bottom: 5px;
            font-weight: 500;
        }

        .section-titles {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .title-en {
            font-size: 0.9rem;
            font-weight: bold;
            color: var(--primary);
        }

        .title-ja {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .section-type-badge {
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 600;
            background: var(--primary-bg);
            color: var(--primary);
            margin-left: 8px;
            vertical-align: middle;
        }

        .section-type-default {
            background: var(--bg-hover);
            color: var(--text-secondary);
        }

        .section-type-text {
            background: var(--success-bg);
            color: var(--success);
        }

        .section-type-embed {
            background: var(--primary-bg);
            color: var(--primary);
        }

        .section-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }



        .status-indicator {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-left: 15px;
        }

        .status-draft {
            background: var(--warning-bg);
            color: var(--warning);
        }

        .status-published {
            background: var(--success-bg);
            color: var(--success);
        }

        /* ➕ボタンスタイル */
        .add-section-btn {
            width: 100%;
            padding: 12px;
            background: var(--primary-bg);
            border: 2px dashed var(--primary-border);
            border-radius: 10px;
            color: var(--primary);
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            margin: 8px 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .add-section-btn:hover {
            background: var(--primary-bg-hover);
            border-color: var(--primary);
            transform: translateY(-2px);
        }

        /* モーダルスタイル */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--bg-overlay);
            backdrop-filter: blur(5px);
            z-index: 10000;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-content {
            background: var(--bg-card);
            border-radius: 20px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            border: none;
            box-shadow: var(--shadow-xl);
        }

        .modal-header {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--text-primary);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-body {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .content-type-btn {
            padding: 20px;
            background: var(--bg-body);
            border: 2px solid var(--border-color);
            border-radius: 12px;
            color: var(--text-primary);
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .content-type-btn:hover {
            background: var(--primary-bg);
            border-color: var(--primary);
            transform: translateX(10px);
        }

        .content-type-btn .material-icons {
            font-size: 32px;
            color: var(--primary);
        }

        .content-type-info {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .content-type-title {
            font-weight: bold;
            font-size: 1.1rem;
        }

        .content-type-desc {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .modal-close {
            margin-top: 20px;
            padding: 12px;
            background: var(--bg-hover);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            cursor: pointer;
            width: 100%;
            transition: all 0.3s ease;
        }

        .modal-close:hover {
            background: var(--bg-active);
        }

        @media (max-width: 768px) {
            .section-card {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }

            .section-actions {
                width: 100%;
                justify-content: flex-end;
                flex-wrap: wrap;
            }
        }
    </style>
        <?php
        require_once __DIR__ . '/../includes/breadcrumb.php';
        $breadcrumbs = [
            ['label' => 'ダッシュボード', 'url' => '/app/manage/?tenant=' . $tenantSlug, 'icon' => 'fas fa-chart-pie'],
            ['label' => '認証ページ編集']
        ];
        renderBreadcrumb($breadcrumbs);
        ?>
        <div class="page-header">
            <div>
                <h1><svg class="page-icon-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 66.146 66.146"><g style="display:inline"><path style="fill:currentColor" d="m30.083 43.722v-7.662h-2.541-2.541v1.74 1.739h.817.817v5.922 5.923h1.724 1.724zm5.745-2.086c-.919-.54-1.02-1.969-.184-2.586.377-.278 1.115-.336 1.552-.121.937.46 1.059 1.978.21 2.605-.382.282-1.184.334-1.578.102zm-.073 6.495c-1.028-.58-1.354-1.85-.716-2.786.365-.535.85-.778 1.55-.775.475 0 .621.044.946.282 1.038.757 1.064 2.259.053 3.092-.294.243-.435.289-.953.312-.432.02-.686-.016-.88-.125zm2.53 3.307c2.096-.729 3.455-2.52 3.572-4.707.073-1.347-.24-2.421-1.009-3.458l-.333-.45.23-.406c.345-.61.527-1.34.53-2.125.006-2.035-1.402-3.8-3.49-4.376-.63-.174-1.893-.172-2.528.003-2.04.562-3.463 2.336-3.471 4.326-.003.775.122 1.304.482 2.045l.264.541-.33.446c-.665.896-1.026 1.997-1.028 3.129-.006 3.662 3.637 6.24 7.111 5.032zm2.144 3.283v-1.269h-6.897-6.897v1.27 1.269h6.897 6.897zm-16.588 1.242-9.192-9.523 1.106-.114c2.185-.226 3.894-1.063 5.38-2.632 1.461-1.543 1.868-2.541 3.092-7.586.426-1.758 1.077-4.36 1.446-5.781.369-1.422 1.02-3.939 1.447-5.594 2.866-11.106 3.792-14.661 3.827-14.697.021-.023.296.036.609.13.972.294 2.59.21 3.519-.181.112-.047.73 2.252 3.489 12.986 1.844 7.173 3.61 14.058 3.927 15.299.78 3.067 1.207 3.98 2.515 5.392 1.474 1.591 3.134 2.414 5.383 2.67l1.021.116-9.189 9.518-9.189 9.518z"/><path style="fill:currentColor" d="m14.747 44.355c-1.241-.075-2.63-.607-3.545-1.357-.648-.531-9.849-10.161-10.074-10.544-.268-.454-.238-1.19.063-1.56.43-.531 1.03-.703 1.639-.471.14.053 1.221 1.122 2.401 2.374l2.146 2.276.238-.23c.131-.126.227-.26.214-.298-.014-.038-1.385-1.489-3.046-3.224-2.942-3.072-3.288-3.497-3.283-4.024.003-.384.339-1.003.65-1.2.322-.202.909-.25 1.283-.102.122.048 1.613 1.54 3.313 3.314l3.091 3.226.23-.236.231-.237-3.523-3.712c-1.938-2.042-3.571-3.804-3.629-3.917-.058-.113-.106-.409-.106-.658 0-.54.237-.991.627-1.2.343-.184 1.028-.188 1.363-.008.141.076 1.826 1.79 3.744 3.808l3.487 3.671.243-.263.244-.264-2.76-2.922c-2.948-3.12-2.967-3.146-2.828-3.916.121-.666.939-1.189 1.657-1.059.314.057.846.584 5.295 5.242l4.945 5.179.671-2.81c.37-1.544.746-2.963.837-3.152.218-.452.73-.879 1.225-1.023 1.085-.315 2.198.37 2.5 1.537.111.431.093.545-.448 2.772-2.03 8.366-2.59 10.537-2.872 11.158-1.122 2.468-3.594 3.99-6.224 3.83z"/><path style="fill:currentColor" d="m49.457 44.187c-1.243-.323-2.685-1.274-3.451-2.276-.769-1.004-1.078-1.814-1.768-4.629-2.102-8.572-2.449-10.022-2.449-10.24.001-.526.208-1 .622-1.43.672-.695 1.45-.827 2.287-.386.821.431.903.62 1.69 3.921l.693 2.909 4.954-5.187c4.463-4.672 4.989-5.192 5.303-5.248.992-.177 1.835.697 1.651 1.712-.056.31-.435.746-2.834 3.26l-2.77 2.903.247.266.246.267 3.504-3.667c1.927-2.017 3.615-3.727 3.751-3.8.136-.073.444-.132.683-.132.772.001 1.296.545 1.297 1.345 0 .25-.046.545-.104.658-.058.113-1.694 1.872-3.637 3.91l-3.532 3.704.241.244.242.244 3.056-3.213c1.681-1.768 3.17-3.259 3.31-3.314.882-.347 1.69.099 1.911 1.055.151.653.04.8-3.224 4.24l-3.1 3.267.242.251.243.251 2.106-2.245c1.231-1.314 2.228-2.299 2.4-2.373.942-.408 1.932.24 1.933 1.265 0 .223-.045.498-.101.611-.117.238-1.555 1.774-6.43 6.868-3.77 3.94-4.237 4.347-5.513 4.807-.6.216-.888.26-1.93.29-.886.025-1.376-.005-1.77-.107z"/><path style="fill:currentColor" d="m15.527 25.541c-1.34-1.389-2.421-2.569-2.402-2.622.019-.054.565-.678 1.214-1.388.648-.71 1.277-1.4 1.397-1.535.119-.135 2.604-2.877 5.521-6.093 5.779-6.37 5.64-6.24 6.485-6.1.44.073.922.418 1.105.789.314.636.331.551-1.575 7.984-1.004 3.917-1.868 7.261-1.92 7.43l-.094.309-.306-.279c-1.4-1.273-3.452-1.376-4.935-.248-.883.671-1.306 1.44-1.684 3.06-.139.594-.279 1.112-.311 1.15-.032.037-1.155-1.068-2.495-2.457z"/><path style="fill:currentColor" d="m47.84 26.964c-.31-1.403-.65-2.15-1.262-2.776-1.47-1.504-3.845-1.589-5.34-.19-.199.186-.374.322-.389.302-.015-.02-.872-3.296-1.903-7.278-1.275-4.923-1.876-7.394-1.876-7.718 0-.613.355-1.158.9-1.384.968-.401 1.187-.255 3.564 2.37 1.107 1.222 2.65 2.92 3.43 3.773.78.853 2.739 3.011 4.354 4.794 1.615 1.784 3.12 3.443 3.347 3.688l.41.444-2.441 2.54c-1.343 1.398-2.466 2.541-2.496 2.541-.03 0-.164-.498-.298-1.106z"/><path style="fill:currentColor" d="m32.147 8.232c-20.801 38.168-10.401 19.084 0 0z"/><circle style="fill:currentColor" cx="33.137" cy="4.778" r="3.704"/></g></svg> 認証ページ編集</h1>
                <p>認証ページ（年齢確認ページ）のセクション配置を管理<?php if ($currentStatus !== 'published'): ?><span
                            class="status-indicator <?php echo $statusClass; ?>"><?php echo $statusLabel; ?></span><?php endif; ?>
                </p>
            </div>
        </div>

        <!-- アクションボタン（上部） -->
        <div class="action-buttons action-buttons-icons">
            <button type="button" class="btn-icon" data-tooltip="下書き保存" onclick="saveDraft()">
                <span class="material-icons">save</span>
            </button>
            <button type="button" class="btn-icon" data-tooltip="PC版プレビュー" onclick="openPreview()">
                <span class="material-icons">computer</span>
            </button>
            <button type="button" class="btn-icon" data-tooltip="スマホ版プレビュー" onclick="openMobilePreview()">
                <span class="material-icons">smartphone</span>
            </button>
            <button type="button" class="btn-icon btn-icon-success" data-tooltip="公開する" onclick="publishLayout()">
                <span class="material-icons">publish</span>
            </button>
            <button type="button" class="btn-icon" data-tooltip="リセット" onclick="resetLayout()">
                <span class="material-icons">restart_alt</span>
            </button>
        </div>

        <!-- 上部エリア（固定・並び替え不可） -->
        <?php if ($heroSection): ?>
            <div class="column-section">
                <div class="column-title">
                    上部エリア（固定）
                </div>
                <div class="section-card <?php echo $heroSection['is_visible'] ? '' : 'hidden'; ?>"
                    style="cursor: default;">
                    <div class="section-info">
                        <div class="section-titles">
                            <div class="admin-title-label">
                                管理名：最上段大エリア
                                <span class="section-type-badge section-type-default">デフォルト</span>
                            </div>
                            <div class="title-ja" style="color: var(--text-secondary); font-size: 0.9rem;">上部エリアデザイン</div>
                        </div>
                    </div>
                    <div class="section-actions">
                        <button class="btn-icon"
                            data-tooltip="編集"
                            onclick="window.location.href='hero_edit?tenant=<?php echo urlencode($tenantSlug); ?>&id=<?php echo $heroSection['id']; ?>'">
                            <span class="material-icons">edit</span>
                        </button>
                        <button class="visibility-toggle <?php echo $heroSection['is_visible'] ? '' : 'hidden'; ?>"
                            onclick="toggleVisibility(<?php echo $heroSection['id']; ?>, this)"
                            data-tooltip="<?php echo $heroSection['is_visible'] ? '非表示にする' : '表示する'; ?>">
                            <span
                                class="material-icons"><?php echo $heroSection['is_visible'] ? 'visibility' : 'visibility_off'; ?></span>
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- その他のセクション（並び替え可能） -->
        <div class="column-section">
            <div class="column-title">
                セクション（ドラッグで並び替え）
            </div>
            <div class="section-list" id="section-list">
                <?php foreach ($otherSections as $section): ?>
                    <?php $isDefault = isIndexDefaultSection($section['section_key']); ?>
                    <div class="section-card <?php echo $section['is_visible'] ? '' : 'hidden'; ?>"
                        data-id="<?php echo $section['id']; ?>" data-key="<?php echo $section['section_key']; ?>">
                        <div class="section-info">
                            <div class="section-titles">
                                <div class="admin-title-label">
                                    管理名：<?php echo h($section['admin_title']); ?>
                                    <?php if ($isDefault): ?>
                                        <span class="section-type-badge section-type-default">デフォルト</span>
                                    <?php elseif ($section['section_type'] === 'banner'): ?>
                                        <span class="section-type-badge">バナー</span>
                                    <?php elseif ($section['section_type'] === 'text_content'): ?>
                                        <span class="section-type-badge section-type-text">テキスト</span>
                                    <?php elseif ($section['section_type'] === 'embed_widget'): ?>
                                        <span class="section-type-badge section-type-embed">リンクパーツ</span>
                                    <?php endif; ?>
                                </div>
                                <div class="title-en">
                                    <?php echo !empty($section['title_en']) ? h($section['title_en']) : '<span style="color: var(--text-secondary);">タイトルなし</span>'; ?>
                                </div>
                                <div class="title-ja">
                                    <?php echo !empty($section['title_ja']) ? h($section['title_ja']) : '<span style="color: var(--text-secondary);">タイトルなし</span>'; ?>
                                </div>
                            </div>
                        </div>
                        <div class="section-actions">
                            <?php if ($isDefault): ?>
                                <!-- デフォルトセクション：編集ボタンのみ -->
                            <?php else: ?>
                                <button class="btn-icon btn-icon-danger"
                                    data-tooltip="削除"
                                    onclick="deleteSection(<?php echo $section['id']; ?>, '<?php echo addslashes(h($section['admin_title'])); ?>')">
                                    <span class="material-icons">delete</span>
                                </button>
                                <?php if ($section['section_type'] === 'banner'): ?>
                                    <button class="btn-icon"
                                        data-tooltip="編集"
                                        onclick="manageBanner('<?php echo h($section['section_key']); ?>')">
                                        <span class="material-icons">edit</span>
                                    </button>
                                <?php elseif ($section['section_type'] === 'text_content'): ?>
                                    <button class="btn-icon"
                                        data-tooltip="編集"
                                        onclick="window.location.href='text_content_edit?tenant=<?php echo urlencode($tenantSlug); ?>&id=<?php echo $section['id']; ?>'">
                                        <span class="material-icons">edit</span>
                                    </button>
                                <?php elseif ($section['section_type'] === 'embed_widget'): ?>
                                    <button class="btn-icon"
                                        data-tooltip="編集"
                                        onclick="window.location.href='embed_widget_edit?tenant=<?php echo urlencode($tenantSlug); ?>&id=<?php echo $section['id']; ?>'">
                                        <span class="material-icons">edit</span>
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                            <button class="visibility-toggle <?php echo $section['is_visible'] ? '' : 'hidden'; ?>"
                                onclick="toggleVisibility(<?php echo $section['id']; ?>, this)"
                                data-tooltip="<?php echo $section['is_visible'] ? '非表示にする' : '表示する'; ?>">
                                <span
                                    class="material-icons"><?php echo $section['is_visible'] ? 'visibility' : 'visibility_off'; ?></span>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>

    <script>
        const TENANT_SLUG = <?php echo $tenantSlugJson; ?>;

        // PCプレビューを別ウィンドウで開く
        function openPreview() {
            const url = '/index_preview_pc?tenant=' + TENANT_SLUG;
            window.open(url, 'indexLayoutPreview', 'width=1200,height=900,scrollbars=yes,resizable=yes');
        }

        // スマホプレビューを別ウィンドウで開く
        function openMobilePreview() {
            const url = '/index_preview_mobile?tenant=' + TENANT_SLUG;
            window.open(url, 'indexLayoutMobilePreview', 'width=550,height=950,scrollbars=yes,resizable=yes');
        }

        // Sortable.js 初期化
        const sectionList = document.getElementById('section-list');
        if (sectionList) {
            new Sortable(sectionList, {
                animation: 150,
                draggable: '.section-card',
                filter: '.visibility-toggle, .btn-icon',
                preventOnFilter: true,
                ghostClass: 'sortable-ghost',
                dragClass: 'sortable-drag',
                onEnd: function () {
                    initAddButtons();
                    autoSaveDraft();
                }
            });
        }

        // 表示/非表示切り替え
        function toggleVisibility(id, button) {
            fetch('toggle_visibility?tenant=' + TENANT_SLUG, {
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
                        const card = button.closest('.section-card');
                        const icon = button.querySelector('.material-icons');

                        if (data.is_visible) {
                            button.classList.remove('hidden');
                            card.classList.remove('hidden');
                            icon.textContent = 'visibility';
                            button.setAttribute('data-tooltip', '非表示にする');
                        } else {
                            button.classList.add('hidden');
                            card.classList.add('hidden');
                            icon.textContent = 'visibility_off';
                            button.setAttribute('data-tooltip', '表示する');
                        }
                    } else {
                        alert('エラー: ' + (data.message || '表示状態の更新に失敗しました。'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('表示状態の更新に失敗しました。');
                });
        }

        // バナー管理画面へ遷移
        function manageBanner(sectionKey) {
            window.location.href = 'banner_manage?section=' + sectionKey + '&tenant=' + TENANT_SLUG;
        }

        // セクション削除
        function deleteSection(id, adminTitle) {
            if (!confirm('「' + adminTitle + '」を削除してもよろしいですか？\n\nこの操作は取り消せません。')) {
                return;
            }

            fetch('delete_section?tenant=' + TENANT_SLUG, {
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
                        alert('セクションを削除しました！');
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

        // 下書き保存（手動）
        function saveDraft() {
            const order = Array.from(document.querySelectorAll('#section-list .section-card')).map(el => el.dataset.id);

            fetch('save_order?tenant=' + TENANT_SLUG, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    order: order,
                    autoSave: false
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('下書きを保存しました！');
                    } else {
                        alert('保存に失敗しました: ' + (data.message || '不明なエラー'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('保存に失敗しました。');
                });
        }

        // 自動保存（ドラッグ&ドロップ後）
        function autoSaveDraft() {
            const order = Array.from(document.querySelectorAll('#section-list .section-card')).map(el => el.dataset.id);

            fetch('save_order?tenant=' + TENANT_SLUG, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    order: order,
                    autoSave: true
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('並び替えを保存しました。');
                    } else {
                        alert('並び替えに失敗しました。');
                    }
                })
                .catch(error => {
                    console.error('Auto-save error:', error);
                    alert('並び替えに失敗しました。');
                });
        }

        // 公開処理
        function publishLayout() {
            if (!confirm('現在の下書き内容を公開しますか？\nインデックスページ（年齢確認ページ）に反映されます。')) {
                return;
            }

            fetch('publish?tenant=' + TENANT_SLUG, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('レイアウトを公開しました！');
                        window.open('/?tenant=' + TENANT_SLUG, '_blank');
                        location.reload();
                    } else {
                        alert('公開に失敗しました: ' + (data.message || '不明なエラー'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('公開に失敗しました。');
                });
        }

        // リセット処理
        function resetLayout() {
            if (!confirm('編集内容を破棄して、前回保存した状態に戻しますか？\n\nこの操作は取り消せません。')) {
                return;
            }

            fetch('reset?tenant=' + TENANT_SLUG, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert('リセットに失敗しました: ' + (data.message || '不明なエラー'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('リセットに失敗しました。');
                });
        }

        // ➕ボタンの初期化
        function initAddButtons() {
            const list = document.getElementById('section-list');
            if (!list) return;

            // 既存の➕ボタンを削除
            list.querySelectorAll('.add-section-btn').forEach(btn => btn.remove());

            // 各カードの前に➕ボタンを追加
            const cards = list.querySelectorAll('.section-card');
            cards.forEach((card, index) => {
                const addBtn = createAddButton(index);
                card.parentNode.insertBefore(addBtn, card);
            });

            // 最後に➕ボタンを追加
            const lastAddBtn = createAddButton(cards.length);
            list.appendChild(lastAddBtn);
        }

        function createAddButton(position) {
            const btn = document.createElement('button');
            btn.className = 'add-section-btn';
            btn.innerHTML = '<span class="material-icons">add</span> コンテンツを追加';
            btn.onclick = () => openAddModal(position);
            return btn;
        }

        // モーダル関連
        let currentPosition = 0;

        function openAddModal(position) {
            currentPosition = position;
            document.getElementById('addModal').classList.add('active');
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.remove('active');
        }

        function selectContentType(type) {
            closeAddModal();
            createNewSection(type);
        }

        function createNewSection(sectionType) {
            let adminTitle = '';
            if (sectionType === 'banner') {
                adminTitle = '新規画像セクション';
            } else if (sectionType === 'text_content') {
                adminTitle = '新規テキストセクション';
            } else if (sectionType === 'embed_widget') {
                adminTitle = '新規リンクパーツセクション';
            }

            fetch('add_section?tenant=' + TENANT_SLUG, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    section_type: sectionType,
                    admin_title: adminTitle,
                    position: currentPosition
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (sectionType === 'banner') {
                            window.location.href = 'banner_manage?section=' + data.section_key + '&tenant=' + TENANT_SLUG;
                        } else if (sectionType === 'text_content') {
                            window.location.href = 'text_content_edit?id=' + data.section_id + '&tenant=' + TENANT_SLUG;
                        } else if (sectionType === 'embed_widget') {
                            window.location.href = 'embed_widget_edit?id=' + data.section_id + '&tenant=' + TENANT_SLUG;
                        }
                    } else {
                        alert('作成に失敗しました: ' + (data.message || '不明なエラー'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('作成に失敗しました');
                });
        }

        // ページ読み込み時に➕ボタンを初期化
        document.addEventListener('DOMContentLoaded', function () {
            initAddButtons();
        });
    </script>

    <!-- コンテンツタイプ選択モーダル -->
    <div id="addModal" class="modal-overlay" onclick="if(event.target === this) closeAddModal()">
        <div class="modal-content">
            <div class="modal-header">
                <span class="material-icons">add_circle</span>
                コンテンツタイプを選択
            </div>
            <div class="modal-body">
                <button class="content-type-btn" onclick="selectContentType('banner')">
                    <span class="material-icons">image</span>
                    <div class="content-type-info">
                        <div class="content-type-title">画像</div>
                        <div class="content-type-desc">バナー画像を複数追加・管理できます</div>
                    </div>
                </button>

                <button class="content-type-btn" onclick="selectContentType('text_content')">
                    <span class="material-icons">article</span>
                    <div class="content-type-info">
                        <div class="content-type-title">テキスト</div>
                        <div class="content-type-desc">注意事項などのテキストコンテンツ（HTML対応）</div>
                    </div>
                </button>

                <button class="content-type-btn" onclick="selectContentType('embed_widget')">
                    <span class="material-icons">code</span>
                    <div class="content-type-info">
                        <div class="content-type-title">リンクパーツ</div>
                        <div class="content-type-desc">外部ウィジェットやiframeコードを埋め込み</div>
                    </div>
                </button>
            </div>
            <button class="modal-close" onclick="closeAddModal()">キャンセル</button>
        </div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>