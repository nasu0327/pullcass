<?php
/**
 * テンプレート選択画面
 * ※index.phpからインクルードされる
 */

// ヘッダーを読み込む
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Google Fonts読み込み -->
<?php echo generateGoogleFontsLink(); ?>

<?php
require_once __DIR__ . '/../includes/breadcrumb.php';
$breadcrumbs = [
    ['label' => 'ダッシュボード', 'url' => '/app/manage/?tenant=' . $tenantSlug, 'icon' => 'fas fa-chart-pie'],
    ['label' => 'テーマ管理', 'url' => '/app/manage/themes/?tenant=' . $tenantSlug],
    ['label' => 'テンプレート選択']
];
renderBreadcrumb($breadcrumbs);
?>

<div class="page-header">
    <h1><i class="fas fa-palette"></i> テンプレート選択</h1>
    <p>プリセットから選んで、自由にカスタマイズできます</p>
</div>

<div class="info-box">
    <i class="fas fa-lightbulb"></i>
    <strong>ヒント:</strong> テンプレートを選択後、色やフォントを自由に変更できます。
</div>

<div style="margin-bottom: 20px; text-align: right;">
    <a href="index.php?tenant=<?php echo urlencode($tenantSlug); ?>" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> キャンセル
    </a>
</div>

<div class="content-card">
    <div class="template-grid">
        <?php foreach ($templates as $template): ?>
            <?php
            $colors = $template['template_data']['colors'];
            $fonts = $template['template_data']['fonts'];
            $isDefault = $template['template_slug'] === 'default';

            // 後方互換性
            if (isset($fonts['title_ja']) && !isset($fonts['title1_ja'])) {
                $fonts['title1_ja'] = $fonts['title_ja'];
            }
            ?>
            <div class="template-card">
                <?php if ($isDefault): ?>
                    <span class="template-badge">デフォルト</span>
                <?php endif; ?>

                <div class="template-preview" style="background: <?php echo h($colors['bg']); ?>;">
                    <div class="color-sample">
                        <div class="color-dot" style="background: <?php echo h($colors['primary']); ?>;"></div>
                        <div class="color-dot" style="background: <?php echo h($colors['primary_light']); ?>;"></div>
                        <div class="color-dot" style="background: <?php echo h($colors['text']); ?>;"></div>
                    </div>
                    <div class="template-font-sample"
                        style="color: <?php echo h($colors['text']); ?>; font-family: '<?php echo h($fonts['title1_ja'] ?? 'Kaisei Decol'); ?>', sans-serif;">
                        <?php echo h($template['template_name']); ?>
                    </div>
                </div>

                <div class="template-name">
                    <?php echo h($template['template_name']); ?>
                </div>

                <div class="template-description">
                    <?php echo h($template['description']); ?>
                </div>

                <form method="POST"
                    action="index.php?action=create_from_template&tenant=<?php echo urlencode($tenantSlug); ?>">
                    <input type="hidden" name="template_id" value="<?php echo $template['id']; ?>">
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-check"></i> このテンプレートを選択
                    </button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
    .info-box {
        background: var(--primary-bg);
        border: 1px solid var(--primary-border);
        border-radius: 10px;
        padding: 15px 20px;
        margin-bottom: 30px;
        color: var(--text-primary);
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .info-box i {
        color: var(--accent);
        font-size: 18px;
    }

    .template-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 25px;
    }

    .template-card {
        background: var(--bg-card);
        border: none;
        border-radius: 15px;
        padding: 25px;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
        box-shadow: var(--shadow-card);
    }

    .template-card:hover {
        background: var(--bg-card);
        border-color: var(--primary-border);
        transform: translateY(-5px);
        box-shadow: var(--shadow-xl);
    }

    .template-preview {
        width: 100%;
        height: 150px;
        border-radius: 10px;
        margin-bottom: 15px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        gap: 10px;
    }

    .color-sample {
        display: flex;
        gap: 8px;
    }

    .color-dot {
        width: 35px;
        height: 35px;
        border-radius: 50%;
        box-shadow: var(--shadow-card);
    }

    .template-font-sample {
        font-size: 16px;
        font-weight: bold;
        text-align: center;
    }

    .template-name {
        font-size: 18px;
        font-weight: 600;
        margin-bottom: 8px;
        color: var(--text-primary);
    }

    .template-description {
        font-size: 14px;
        color: var(--text-muted);
        margin-bottom: 15px;
        line-height: 1.5;
    }

    .template-badge {
        position: absolute;
        top: 15px;
        right: 15px;
        background: var(--success);
        color: var(--text-inverse);
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }

    .btn {
        padding: 10px 20px;
        border: none;
        border-radius: 25px;
        cursor: pointer;
        text-decoration: none;
        font-size: 13px;
        font-weight: 400;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .btn:hover {
        transform: translateY(-2px);
    }

    .btn-primary {
        background: var(--primary-gradient);
        color: var(--text-inverse);
    }

    .btn-primary:hover {
        box-shadow: 0 5px 15px var(--primary-bg);
    }

    .btn-secondary {
        background: var(--bg-body);
        color: var(--text-primary);
        border: 1px solid var(--border-color);
    }

    .btn-secondary:hover {
        background: var(--border-color);
    }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>