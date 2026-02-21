<?php
/**
 * トップページ セクションレンダリング関数（pullcass版・マルチテナント対応）
 */

/**
 * メインレンダリング関数
 */
function renderSection($section, $pdo, $tenantId)
{
    $sectionKey = $section['section_key'];
    $sectionType = $section['section_type'];

    // section_typeで判定
    switch ($sectionType) {
        case 'hero_text':
            renderHeroTextSection($section);
            break;
        case 'banner':
            renderBannerSection($section, $pdo, $tenantId);
            break;
        case 'text_content':
            renderTextContentSection($section);
            break;
        case 'embed_widget':
            renderEmbedWidgetSection($section);
            break;
        case 'cast_list':
            // キャストリストセクション（section_keyで判定）
            if ($sectionKey === 'new_cast') {
                renderNewCastSection($section, $pdo, $tenantId);
            } elseif ($sectionKey === 'today_cast') {
                renderTodayCastSection($section, $pdo, $tenantId);
            } else {
                renderPlaceholderSection($section);
            }
            break;
        case 'content':
            // コンテンツセクション（section_keyで判定）
            if ($sectionKey === 'history') {
                renderHistorySection($section);
            } elseif ($sectionKey === 'diary') {
                renderDiarySection($section);
            } elseif ($sectionKey === 'reviews') {
                renderReviewsSection($section, $pdo, $tenantId);
            } elseif ($sectionKey === 'videos') {
                renderVideosSection($section, $pdo, $tenantId);
            } else {
                renderPlaceholderSection($section);
            }
            break;
        case 'ranking':
            renderRankingSection($section, $pdo, $tenantId);
            break;
        case 'videos':
            renderVideosSection($section, $pdo, $tenantId);
            break;
        case 'external':
            // デフォルトセクション（現時点では準備中表示）
            renderPlaceholderSection($section);
            break;
        default:
            echo '<!-- 未定義セクション: ' . htmlspecialchars($sectionKey) . ' -->';
    }
}

/**
 * H1テキストセクション（hero_text）
 */
function renderHeroTextSection($section)
{
    $config = json_decode($section['config'], true) ?: [];
    $h1Title = trim($config['h1_title'] ?? '');
    $introText = trim($config['intro_text'] ?? '');

    // データが空の場合は何も表示しない
    if (empty($h1Title) && empty($introText)) {
        return;
    }
    ?>
    <div class="hero-text-section"
        style="background: rgba(255, 255, 255, 0.05); padding: 20px; border-radius: 12px; margin-bottom: 20px; text-align: center;">
        <?php if (!empty($h1Title)): ?>
            <h1 style="font-size: 20px; font-weight: bold; color: var(--color-text); margin: 0 0 10px 0; line-height: 1.4;">
                <?php echo nl2br(h($h1Title)); ?>
            </h1>
        <?php endif; ?>

        <?php if (!empty($introText)): ?>
            <p style="font-size: 1rem; color: var(--color-text); opacity: 0.8; margin: 0; line-height: 1.6;">
                <?php echo nl2br(h($introText)); ?>
            </p>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * バナーセクション
 */
function renderBannerSection($section, $pdo, $tenantId)
{
    $sectionId = $section['id'];
    $titleEn = h($section['title_en']);
    $titleJa = h($section['title_ja']);

    // バナー画像を取得
    $stmt = $pdo->prepare("
        SELECT * FROM top_layout_banners 
        WHERE section_id = ? AND tenant_id = ? AND is_visible = 1 
        ORDER BY display_order ASC
    ");
    $stmt->execute([$sectionId, $tenantId]);
    $banners = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // タイトルも画像もない場合は表示しない
    $hasTitle = !empty($titleEn) || !empty($titleJa);
    $hasContent = !empty($banners);
    if (!$hasTitle && !$hasContent) {
        return;
    }

    echo '<div class="section-card">';

    // タイトル表示
    if ($hasTitle) {
        echo '<div class="section-title">';
        if (!empty($titleEn)) {
            echo '<div class="title-en">' . $titleEn . '</div>';
        }
        if (!empty($titleJa)) {
            echo '<div class="title-ja">' . $titleJa . '</div>';
        }
        echo '<div class="dot-line"></div>';
        echo '</div>';
    }

    // バナー画像表示
    foreach ($banners as $banner) {
        $imagePath = h($banner['image_path']);
        $linkUrl = h($banner['link_url'] ?? '');
        $altText = h($banner['alt_text'] ?: $titleJa);
        $target = h($banner['target'] ?? '_self');
        $nofollow = $banner['nofollow'] ?? 0;

        if ($linkUrl) {
            $relAttr = $nofollow ? ' rel="nofollow"' : '';
            echo '<a href="' . $linkUrl . '" target="' . $target . '"' . $relAttr . ' class="link-block">';
            echo '<img src="' . $imagePath . '" alt="' . $altText . '" class="img-banner" style="width: 100%; height: auto; display: block; border-radius: 8px; margin-bottom: 10px;">';
            echo '</a>';
        } else {
            echo '<img src="' . $imagePath . '" alt="' . $altText . '" class="img-banner" style="width: 100%; height: auto; display: block; border-radius: 8px; margin-bottom: 10px;">';
        }
    }

    echo '</div>';
}

/**
 * テキストコンテンツセクション
 */
function renderTextContentSection($section)
{
    $config = json_decode($section['config'], true) ?: [];
    $htmlContent = $config['html_content'] ?? '';
    $titleEn = $section['title_en'] ?? '';
    $titleJa = $section['title_ja'] ?? '';

    // タイトルもコンテンツもない場合は表示しない
    $hasTitle = !empty($titleEn) || !empty($titleJa);
    $hasContent = !empty(trim($htmlContent));
    if (!$hasTitle && !$hasContent) {
        return;
    }
    ?>
    <div class="section-card text-content-section">
        <?php if ($hasTitle): ?>
            <div class="section-title">
                <?php if (!empty($titleEn)): ?>
                    <div class="title-en"><?php echo h($titleEn); ?></div>
                <?php endif; ?>
                <?php if (!empty($titleJa)): ?>
                    <div class="title-ja"><?php echo h($titleJa); ?></div>
                <?php endif; ?>
                <div class="dot-line"></div>
            </div>
        <?php endif; ?>

        <?php if ($hasContent): ?>
            <div class="text-content">
                <?php echo $htmlContent; // HTMLコンテンツをそのまま出力（TinyMCEのスタイルを尊重） ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * 埋め込みウィジェットセクション
 */
function renderEmbedWidgetSection($section)
{
    $config = json_decode($section['config'], true) ?: [];
    $embedCode = $config['embed_code'] ?? '';
    $embedHeight = $config['embed_height'] ?? '400';
    $titleEn = $section['title_en'] ?? '';
    $titleJa = $section['title_ja'] ?? '';

    // タイトルも埋め込みコードもない場合は表示しない
    $hasTitle = !empty($titleEn) || !empty($titleJa);
    $hasContent = !empty(trim($embedCode));
    if (!$hasTitle && !$hasContent) {
        return;
    }
    ?>
    <div class="section-card embed-widget-section">
        <?php if ($hasTitle): ?>
            <div class="section-title">
                <?php if (!empty($titleEn)): ?>
                    <div class="title-en"><?php echo h($titleEn); ?></div>
                <?php endif; ?>
                <?php if (!empty($titleJa)): ?>
                    <div class="title-ja"><?php echo h($titleJa); ?></div>
                <?php endif; ?>
                <div class="dot-line"></div>
            </div>
        <?php endif; ?>

        <?php if ($hasContent): ?>
            <div class="embed-wrapper" style="min-height: <?php echo h($embedHeight); ?>px; overflow: hidden;">
                <?php echo $embedCode; // 埋め込みコードをそのまま出力 ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * 新人キャストセクション
 */
function renderNewCastSection($section, $pdo, $tenantId)
{
    // 新人キャストを取得（統合テーブルから）
    $newCasts = [];
    try {
        $stmt = $pdo->prepare("
            SELECT id, name, age, height, size, cup, pr_title, img1
            FROM tenant_casts
            WHERE tenant_id = ? AND checked = 1 AND `new` LIKE '%新人%'
            ORDER BY sort_order ASC
            LIMIT 10
        ");
        $stmt->execute([$tenantId]);
        $newCasts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // エラー時は空配列のまま
    }

    $titleEn = h($section['title_en'] ?? 'NEW CAST');
    $titleJa = h($section['title_ja'] ?? '新人');
    ?>
    <div class="section-card">
        <div class="section-title">
            <div class="title-en"><?php echo $titleEn; ?></div>
            <div class="title-ja"><?php echo $titleJa; ?></div>
            <div class="dot-line"></div>
        </div>
        <?php if (!empty($newCasts)): ?>
            <div class="scroll-wrapper">
                <div class="scroll-container-x">
                    <div class="cast-cards cards-inline-flex">
                        <?php foreach ($newCasts as $cast): ?>
                            <div class="cast-card">
                                <a href="<?php echo castDetailUrl($cast['id']); ?>" class="link-block">
                                    <div class="cast-image">
                                        <?php if ($cast['img1']): ?>
                                            <img src="<?php echo h($cast['img1']); ?>" alt="<?php echo h($cast['name']); ?>"
                                                loading="eager">
                                        <?php else: ?>
                                            <div
                                                style="width: 100%; height: 200px; background: rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,0.5);">
                                                <i class="fas fa-user" style="font-size: 3rem;"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="cast-info">
                                        <div class="cast-name"><?php echo h($cast['name']); ?></div>
                                        <div class="cast-stats">
                                            <span><?php echo h($cast['age']); ?>歳</span>
                                            <?php if ($cast['cup']): ?>
                                                <span><?php echo h($cast['cup']); ?>カップ</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($cast['pr_title']): ?>
                                            <div class="cast-pr"><?php echo h($cast['pr_title']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="scroll-gradient-right"></div>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * 本日の出勤キャストセクション
 */
function renderTodayCastSection($section, $pdo, $tenantId)
{
    // テナントのスクレイピング設定を取得
    $activeSource = 'ekichika'; // デフォルト
    try {
        $stmt = $pdo->prepare("SELECT config_value FROM tenant_scraping_config WHERE tenant_id = ? AND config_key = 'active_source'");
        $stmt->execute([$tenantId]);
        $settings = $stmt->fetch();
        $activeSource = $settings['config_value'] ?? 'ekichika';
    } catch (PDOException $e) {
        // デフォルト値を使用
    }

    // 本日の出勤キャストを取得（統合テーブルから）
    $todayCasts = [];
    try {
        // 今日はday1
        $stmt = $pdo->prepare("
            SELECT id, name, age, cup, pr_title, img1, day1, `now`, closed
            FROM tenant_casts
            WHERE tenant_id = ? 
              AND checked = 1
              AND day1 IS NOT NULL
              AND day1 != ''
            ORDER BY day1 ASC
        ");
        $stmt->execute([$tenantId]);
        $todayCasts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // エラー時は空配列のまま
    }

    // 日付情報
    date_default_timezone_set('Asia/Tokyo');
    $today = date('n/j');
    $dayOfWeek = ['日', '月', '火', '水', '木', '金', '土'][date('w')];

    $titleEn = h($section['title_en'] ?? 'TODAY');
    $titleJa = h($section['title_ja'] ?? '本日の出勤');
    ?>
    <div class="section-card">
        <div class="section-title">
            <div class="title-en"><?php echo $titleEn; ?></div>
            <div class="title-ja"><?php echo $titleJa; ?><span
                    style="display: inline-block; margin-left: 10px; font-size: 0.8em;"><?php echo h($today); ?>(<?php echo h($dayOfWeek); ?>)</span>
            </div>
            <div class="dot-line"></div>
        </div>
        <?php if (!empty($todayCasts)): ?>
            <div class="scroll-wrapper">
                <div class="scroll-container-x">
                    <div class="cast-cards cards-inline-flex">
                        <?php foreach ($todayCasts as $cast): ?>
                            <div class="cast-card">
                                <a href="<?php echo castDetailUrl($cast['id']); ?>" class="link-block">
                                    <div class="cast-image">
                                        <?php if ($cast['img1']): ?>
                                            <img src="<?php echo h($cast['img1']); ?>" alt="<?php echo h($cast['name']); ?>"
                                                loading="eager">
                                        <?php else: ?>
                                            <div
                                                style="width: 100%; height: 200px; background: rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,0.5);">
                                                <i class="fas fa-user" style="font-size: 3rem;"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="cast-info">
                                        <div class="cast-name"><?php echo h($cast['name']); ?></div>
                                        <div class="cast-stats">
                                            <span><?php echo h($cast['age']); ?>歳</span>
                                            <?php if ($cast['cup']): ?>
                                                <span><?php echo h($cast['cup']); ?>カップ</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($cast['pr_title']): ?>
                                            <div class="cast-pr"><?php echo h($cast['pr_title']); ?></div>
                                        <?php endif; ?>
                                        <?php if ($cast['day1']): ?>
                                            <div class="cast-time"><?php echo h($cast['day1']); ?></div>
                                        <?php endif; ?>
                                        <div
                                            style="text-align: center; display: flex; flex-wrap: wrap; justify-content: center; gap: 2px; padding: 0; margin: 4px 0 0 0;">
                                            <?php if ($cast['now']): ?>
                                                <span class="badge now">案内中</span>
                                            <?php elseif ($cast['closed']): ?>
                                                <span class="badge closed">受付終了</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="scroll-gradient-right"></div>
            </div>
        <?php else: ?>
            <div class="coming-soon-card">
                <i class="fas fa-calendar-day"></i>
                <h3>本日の出勤情報なし</h3>
                <p>本日の出勤キャストがいません。</p>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * ランキングセクション
 */
function renderRankingSection($section, $pdo, $tenantId)
{
    $sectionKey = $section['section_key'];

    // ランキングタイプを決定
    $rankingType = '';
    if ($sectionKey === 'ranking_repeat' || $sectionKey === 'repeat_ranking') {
        $rankingType = 'repeat_ranking';
    } elseif ($sectionKey === 'ranking_access' || $sectionKey === 'attention_ranking') {
        $rankingType = 'attention_ranking';
    } else {
        // デフォルトまたは不明な場合はプレースホルダー
        renderPlaceholderSection($section);
        return;
    }

    // アクティブなテーブル名を取得
    $activeSource = 'ekichika';
    $tableName = 'tenant_casts'; // デフォルト

    try {
        $stmt = $pdo->prepare("SELECT config_value FROM tenant_scraping_config WHERE tenant_id = ? AND config_key = 'active_source'");
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['config_value']) {
            $activeSource = $row['config_value'];
        }

        $validSources = ['ekichika', 'heaven', 'dto'];
        if (in_array($activeSource, $validSources)) {
            $tableName = "tenant_cast_data_{$activeSource}";
        }
    } catch (Exception $e) {
    }

    // ランキングデータを取得（詳細ページのリンク用に tenant_casts.id を取得）
    $rankingCasts = [];
    try {
        if ($tableName === 'tenant_casts') {
            $stmt = $pdo->prepare("
                SELECT id, id AS link_id, name, age, cup, pr_title, img1, {$rankingType} as rank
                FROM {$tableName}
                WHERE tenant_id = ?
                  AND checked = 1
                  AND {$rankingType} IS NOT NULL
                ORDER BY {$rankingType} ASC
                LIMIT 10
            ");
        } else {
            $stmt = $pdo->prepare("
                SELECT d.id, d.name, d.age, d.cup, d.pr_title, d.img1, d.{$rankingType} as rank, c.id AS link_id
                FROM {$tableName} d
                LEFT JOIN tenant_casts c ON c.tenant_id = d.tenant_id AND c.name = d.name AND c.checked = 1
                WHERE d.tenant_id = ?
                  AND d.checked = 1
                  AND d.{$rankingType} IS NOT NULL
                ORDER BY d.{$rankingType} ASC
                LIMIT 10
            ");
        }
        $stmt->execute([$tenantId]);
        $rankingCasts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log($e->getMessage());
    }

    $titleEn = h($section['title_en']);
    $titleJa = h($section['title_ja']);

    // カスタムタイトルと表示設定を取得
    try {
        $stmtConfig = $pdo->prepare("SELECT repeat_title, attention_title, repeat_visible, attention_visible FROM tenant_ranking_config WHERE tenant_id = ?");
        $stmtConfig->execute([$tenantId]);
        $rankingConfig = $stmtConfig->fetch(PDO::FETCH_ASSOC);

        if ($rankingConfig) {
            // 表示非表示チェック
            if ($rankingType === 'repeat_ranking' && isset($rankingConfig['repeat_visible']) && $rankingConfig['repeat_visible'] == 0) {
                return;
            }
            if ($rankingType === 'attention_ranking' && isset($rankingConfig['attention_visible']) && $rankingConfig['attention_visible'] == 0) {
                return;
            }

            // タイトル上書き
            if ($rankingType === 'repeat_ranking' && !empty($rankingConfig['repeat_title'])) {
                $titleJa = h($rankingConfig['repeat_title']);
            } elseif ($rankingType === 'attention_ranking' && !empty($rankingConfig['attention_title'])) {
                $titleJa = h($rankingConfig['attention_title']);
            }
        }
    } catch (PDOException $e) {
        // カラムが存在しない場合などは無視してデフォルトタイトルを使用
    }

    ?>
    <div class="section-card">
        <div class="section-title">
            <div class="title-en"><?php echo $titleEn; ?></div>
            <div class="title-ja"><?php echo $titleJa; ?></div>
            <div class="dot-line"></div>
        </div>

        <?php if (!empty($rankingCasts)): ?>
            <div class="scroll-wrapper">
                <div class="scroll-container-x">
                    <div class="cast-cards cards-inline-flex">
                        <?php foreach ($rankingCasts as $cast): ?>
                            <?php $detailId = !empty($cast['link_id']) ? (int)$cast['link_id'] : null; ?>
                            <div class="cast-card ranking-card">
                                <a href="<?php echo $detailId ? castDetailUrl($detailId) : '/cast/list'; ?>" class="link-block">
                                    <div class="ranking-number">
                                        No.<?php echo h($cast['rank']); ?>
                                    </div>
                                    <div class="cast-image">
                                        <?php if ($cast['img1']): ?>
                                            <img src="<?php echo h($cast['img1']); ?>" alt="<?php echo h($cast['name']); ?>"
                                                loading="lazy">
                                        <?php else: ?>
                                            <div
                                                style="width: 100%; height: 200px; background: rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,0.5);">
                                                <i class="fas fa-user" style="font-size: 3rem;"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="cast-info">
                                        <div class="cast-name"><?php echo h($cast['name']); ?></div>
                                        <div class="cast-stats">
                                            <span><?php echo h($cast['age']); ?>歳</span>
                                            <?php if ($cast['cup']): ?>
                                                <span><?php echo h($cast['cup']); ?>カップ</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($cast['pr_title']): ?>
                                            <div class="cast-pr"><?php echo h($cast['pr_title']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="scroll-gradient-right"></div>
            </div>

            <style>
                /* リファレンスサイト準拠のランキングスタイル */
                .ranking-card {
                    position: relative;
                }

                .ranking-number {
                    font-family: 'MonteCarlo', cursive;
                    font-size: 24px;
                    color: var(--color-primary);
                    text-align: center;
                    padding: 1px 0;
                    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);
                    /* 背景色を追加して画像との境界を明確にする場合（必要に応じて調整） */
                    /* background: rgba(255,255,255,0.9); */
                }

                .ranking-card .cast-info {
                    padding: 4px 0 10px 0;
                }
            </style>

        <?php else: ?>
            <div class="coming-soon-card">
                <i class="fas fa-trophy"></i>
                <h3>ランキング集計中</h3>
                <p>現在データを集計しております。</p>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * 閲覧履歴セクション
 */
function renderHistorySection($section)
{
    $titleEn = h($section['title_en'] ?? 'HISTORY');
    $titleJa = h($section['title_ja'] ?? '閲覧履歴');
    ?>
    <div class="section-card">
        <div class="section-title">
            <div class="title-en"><?php echo $titleEn; ?></div>
            <div class="title-ja"><?php echo $titleJa; ?></div>
            <div class="dot-line"></div>
        </div>
        <div class="history-wrapper">
            <div class="history-content">
                <div class="history-cards">
                    <!-- 履歴カードはJavaScriptで動的に生成されます -->
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * 口コミセクション（トップ左カラム・口コミ一覧）
 * scrapeモード: スクレイピングデータ表示 / widgetモード: 店舗ウィジェット表示
 */
function renderReviewsSection($section, $pdo, $tenantId)
{
    $titleEn = h($section['title_en'] ?? 'REVIEW');
    $titleJa = h($section['title_ja'] ?? '口コミ');
    $renderMode = $section['_render_mode'] ?? 'scrape';

    if ($renderMode === 'widget') {
        $widgetCode = $section['_widget_code'] ?? '';
        if (empty(trim($widgetCode))) return;
        ?>
        <div class="section-card">
            <div class="section-title">
                <div class="title-en"><?php echo $titleEn; ?></div>
                <div class="title-ja"><?php echo $titleJa; ?></div>
                <div class="dot-line"></div>
            </div>
            <div class="widget-content"><?php echo $widgetCode; ?></div>
        </div>
        <?php
        return;
    }

    $platformPdo = function_exists('getPlatformDb') ? getPlatformDb() : null;
    $reviews = [];
    if ($platformPdo) {
        try {
            $stmt = $platformPdo->prepare("
                SELECT id, title, user_name, review_date, rating, cast_name, content
                FROM reviews WHERE tenant_id = ? ORDER BY id ASC LIMIT 10
            ");
            $stmt->execute([$tenantId]);
            $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // ignore
        }
    }
    ?>
    <div class="section-card">
        <div class="section-title">
            <div class="title-en"><?php echo $titleEn; ?></div>
            <div class="title-ja"><?php echo $titleJa; ?></div>
            <div class="dot-line"></div>
        </div>
        <div class="scroll-wrapper">
            <div class="review-scroll-container scroll-container-x">
                <div class="review-cards cards-inline-flex">
                <?php foreach ($reviews as $index => $review): ?>
                    <?php
                    $content = $review['content'];
                    $rating = (int)($review['rating'] ?? 0);
                    $stars = str_repeat('★', min(5, $rating)) . str_repeat('☆', 5 - min(5, $rating));
                    $isPickup = ($index === 0);
                    ?>
                    <div class="review-card" style="flex: 0 0 280px; white-space: normal; background: <?php echo $isPickup ? '#FFF8DC' : 'rgba(255,255,255,0.6)'; ?>; border-radius: 8px; box-shadow: 0 3px 8px rgba(0,0,0,0.1); overflow: hidden; cursor: pointer;" onclick="window.location.href='/reviews#review-<?php echo (int)$review['id']; ?>'">
                        <?php if ($isPickup): ?>
                        <div class="pickup-text" style="color: #F568DF; font-size: 16px; font-weight: bold; text-align: left; margin: 8px 12px 0 12px;">ピックアップ！</div>
                        <?php else: ?>
                        <div style="height: 24px;"></div>
                        <?php endif; ?>
                        <div class="review-header" style="padding: 12px 12px 0 12px; border-bottom: 1px solid #eee;">
                            <div class="review-title" style="font-weight: bold; font-size: 14px; margin: 0 0 4px 0; line-height: 1.2; color: var(--color-text);"><?php echo h($review['title'] ?: 'タイトルなし'); ?></div>
                            <div style="font-size: 11px; color: var(--color-text); margin: 0 0 4px 0;">投稿者: <?php echo h($review['user_name']); ?></div>
                            <div style="font-size: 11px; color: var(--color-text); margin: 0 0 4px 0;">投稿日: <?php echo $review['review_date'] ? date('Y年m月d日', strtotime($review['review_date'])) : '日付不明'; ?></div>
                            <div class="review-rating" style="font-size: 12px; color: #FFD700; margin: 0 0 4px 0;"><?php echo $stars; ?></div>
                            <?php if (!empty($review['cast_name'])): ?>
                            <div class="review-cast" style="font-size: 12px; color: #F568DF; font-weight: bold; margin: 0 0 8px 0;"><?php echo h($review['cast_name']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="review-content" style="padding: 8px 12px 12px 12px;">
                            <div class="review-text" style="font-size: 12px; color: var(--color-text); line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; max-height: 4.2em;"><?php echo nl2br(h($content)); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="review-more-card" style="flex: 0 0 280px; background: linear-gradient(135deg, var(--color-primary), var(--color-primary-light)); border-radius: 8px; display: flex; align-items: center; justify-content: center; padding: 10px; cursor: pointer; height: 100px; align-self: center;" onclick="window.location.href='/reviews'">
                    <div style="border: 2px dashed var(--color-btn-text); border-radius: 6px; padding: 15px; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;">
                        <div style="color: var(--color-btn-text); font-size: 16px; font-weight: bold;">全ての口コミを見る</div>
                    </div>
                </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * 写メ日記セクション（トップ右カラム・全キャストの写メ日記一覧）
 * scrapeモード: スクレイピングデータ表示 / widgetモード: 店舗ウィジェット表示
 */
function renderDiarySection($section)
{
    $titleEn = h($section['title_en'] ?? 'DIARY');
    $titleJa = h($section['title_ja'] ?? '動画・写メ日記');
    $renderMode = $section['_render_mode'] ?? 'scrape';

    if ($renderMode === 'widget') {
        $widgetCode = $section['_widget_code'] ?? '';
        if (empty(trim($widgetCode))) return;
        ?>
        <div class="section-card">
            <div class="section-title">
                <div class="title-en"><?php echo $titleEn; ?></div>
                <div class="title-ja"><?php echo $titleJa; ?></div>
                <div class="dot-line"></div>
            </div>
            <div class="widget-content"><?php echo $widgetCode; ?></div>
        </div>
        <?php
        return;
    }
    ?>
    <div class="section-card">
        <div class="section-title">
            <div class="title-en"><?php echo $titleEn; ?></div>
            <div class="title-ja"><?php echo $titleJa; ?></div>
            <div class="dot-line"></div>
        </div>
        <div class="shamenikki-wrapper">
            <div class="shamenikki-content scroll-container-y" style="max-height: 350px; transition: max-height 0.5s cubic-bezier(0.4, 0, 0.2, 1);">
                <div id="diary-cards-container" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; padding: 10px;">
                    <div id="diary-loading" style="text-align: center; padding: 40px; color: var(--color-text); grid-column: 1 / -1;">
                        日記を読み込み中...
                    </div>
                </div>
                <div id="view-all-diary-wrapper" style="grid-column: 1 / -1; text-align: center; margin-top: 10px;"></div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * プレースホルダーセクション（デフォルトセクション用・準備中表示）
 */
function renderPlaceholderSection($section)
{
    $titleEn = h($section['title_en'] ?? 'SECTION');
    $titleJa = h($section['admin_title'] ?? 'セクション');
    ?>
    <div class="section-card">
        <div class="section-title">
            <div class="title-en"><?php echo $titleEn; ?></div>
            <div class="title-ja"><?php echo $titleJa; ?></div>
            <div class="dot-line"></div>
        </div>
        <div style="padding: 40px; text-align: center; color: rgba(255, 255, 255, 0.6);">
            <p style="margin: 0; font-size: 1.1rem;">このセクションは準備中です</p>
            <p style="margin: 10px 0 0 0; font-size: 0.9rem;">コンテンツは順次追加予定です</p>
        </div>
    </div>
    <?php
}

/**
 * セクション共通スタイル
 */
function renderSectionStyles()
{
    ?>
    <style>
        /* トップバナースライダー（参考サイト準拠） */
        .top-banner-section {
            margin-bottom: 20px;
        }

        .top-banner-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .topBannerSwiper {
            width: 100%;
            height: auto;
        }

        .topBannerSwiper .swiper-slide {
            width: 100%;
            padding: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .topBannerSwiper .swiper-slide img {
            width: 100%;
            height: auto;
            display: block;
            border-radius: 15px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        /* Swiperナビゲーションボタン（小さく、白い丸背景） */
        .topBannerSwiper .swiper-button-next,
        .topBannerSwiper .swiper-button-prev {
            width: 35px;
            height: 35px;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 50%;
            color: var(--color-primary);
        }

        /* 左右の矢印ボタンと画像の間に隙間を開ける */
        .topBannerSwiper .swiper-button-prev {
            left: 15px;
        }

        .topBannerSwiper .swiper-button-next {
            right: 15px;
        }

        .topBannerSwiper .swiper-button-next::after,
        .topBannerSwiper .swiper-button-prev::after {
            font-size: 16px;
            font-weight: bold;
        }

        /* Swiperページネーション（ドット）を画像の下に配置 */
        .topBannerSwiper .swiper-pagination {
            position: relative;
            bottom: 0;
            margin-top: -5px;
        }

        .topBannerSwiper .swiper-pagination-bullet {
            width: 10px;
            height: 10px;
            background: var(--color-primary);
            opacity: 0.5;
        }

        .topBannerSwiper .swiper-pagination-bullet-active {
            opacity: 1;
        }

        /* フッターの中央寄せ修正 */
        .fixed-footer-container {
            margin: 0 auto !important;
        }

        .section-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .section-title {
            text-align: center;
            margin-bottom: 20px;
        }

        .title-en {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--color-primary);
            margin-bottom: 5px;
            letter-spacing: 0.1em;
        }

        .title-ja {
            font-size: 1rem;
            color: var(--color-text);
            opacity: 0.9;
            margin-bottom: 10px;
        }

        .dot-line {
            width: 100%;
            max-width: 100%;
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--color-primary), transparent);
            margin: 10px auto 0;
        }

        .link-block {
            display: block;
            text-decoration: none;
        }

        .img-banner {
            width: 100%;
            height: auto;
            display: block;
            border-radius: 8px;
            margin-bottom: 10px;
            transition: transform 0.3s ease;
        }

        .img-banner:hover {
            transform: scale(1.02);
        }

        /* スクロールコンテナ */
        .scroll-wrapper {
            position: relative;
            overflow: visible;
            /* グラデーションを外側に表示 */
        }

        .scroll-container-x {
            text-align: center;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
            scrollbar-color: var(--color-primary) #f0f0f0;
            padding: 10px 0;
        }

        .scroll-container-x::-webkit-scrollbar {
            height: 6px;
        }

        .scroll-container-x::-webkit-scrollbar-track {
            background: #f0f0f0;
            border-radius: 3px;
        }

        .scroll-container-x::-webkit-scrollbar-thumb {
            background-color: var(--color-primary);
            border-radius: 3px;
        }

        .scroll-gradient-right {
            position: absolute;
            top: 0;
            right: 0;
            width: 50px;
            height: 100%;
            background: linear-gradient(to left, rgba(255, 255, 255, 0.8), transparent);
            pointer-events: none;
            z-index: 1;
        }

        /* キャストカード（横スクロール用） */
        .cast-cards.cards-inline-flex {
            display: inline-flex;
            gap: 15px;
            padding-right: 30px;
        }

        .cast-cards .cast-card {
            flex: 0 0 180px;
            width: 180px;
            background: rgba(255, 255, 255, 0.6);
            border-radius: 12px;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .cast-cards .cast-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        .cast-cards .cast-card a {
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .cast-image {
            width: 100%;
            height: 200px;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.1);
        }

        .cast-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .cast-info {
            padding: 12px;
            text-align: center;
        }

        .cast-name {
            font-size: 1rem;
            font-weight: bold;
            color: var(--color-text);
            margin-bottom: 6px;
        }

        .cast-stats {
            display: flex;
            justify-content: center;
            gap: 8px;
            font-size: 0.85rem;
            color: var(--color-text);
            opacity: 0.8;
            margin-bottom: 6px;
        }

        .cast-pr {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--color-text);
            opacity: 0.7;
            margin-top: 4px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .cast-time {
            font-size: 0.85rem;
            color: var(--color-primary);
            font-weight: 600;
            margin-top: 4px;
        }

        .badge {
            font-family: var(--font-body);
            display: inline-block;
            width: fit-content;
            padding: 0 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            color: var(--color-primary);
            background-color: transparent;
            border: 1px solid var(--color-primary);
            line-height: 1.5;
            margin: 0;
        }

        .badge.closed {
            color: var(--color-primary);
            border-color: var(--color-primary);
        }

        /* 準備中カード */
        .coming-soon-card {
            background: rgba(255, 255, 255, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            padding: 30px 20px;
            text-align: center;
        }

        .coming-soon-card i {
            font-size: 2.5rem;
            color: var(--color-primary);
            opacity: 0.4;
            margin-bottom: 12px;
        }

        .coming-soon-card h3 {
            font-size: 1rem;
            color: var(--color-text);
            margin-bottom: 8px;
        }

        .coming-soon-card p {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.85rem;
        }

        @media (max-width: 767px) {
            .section-card {
                padding: 15px;
                margin-bottom: 15px;
            }

            .title-en {
                font-size: 1rem;
            }

            .title-ja {
                font-size: 0.9rem;
            }

            .cast-cards .cast-card {
                flex: 0 0 140px;
                width: 140px;
            }

            .cast-image {
                height: 160px;
            }
        }
    </style>
    <?php
}

/**
 * 動画セクション
 */
function renderVideosSection($section, $pdo, $tenantId)
{
    $titleEn = !empty($section['title_en']) ? h($section['title_en']) : 'VIDEO';
    $titleJa = !empty($section['title_ja']) ? h($section['title_ja']) : '動画';
    ?>
    <div class="section-card">
        <div class="section-title">
            <div class="title-en"><?php echo $titleEn; ?></div>
            <div class="title-ja"><?php echo $titleJa; ?></div>
            <div class="dot-line"></div>
        </div>
        <div class="video-grid">
            <?php
            // 動画を持つキャストを取得
            // movie_1 または movie_2 が NULL でなく、空文字でないもの
            $videos = [];
            try {
                $stmt = $pdo->prepare("
                    SELECT id, name, movie_1, movie_2, movie_1_thumbnail, movie_2_thumbnail, movie_1_seo_thumbnail, movie_2_seo_thumbnail
                    FROM tenant_casts
                    WHERE tenant_id = ?
                    AND checked = 1 
                    AND (
                        (movie_1 IS NOT NULL AND movie_1 != '') 
                        OR 
                        (movie_2 IS NOT NULL AND movie_2 != '')
                    )
                    ORDER BY sort_order ASC, id DESC
                    LIMIT 12
                ");
                $stmt->execute([$tenantId]);
                $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                // エラー時は空配列
            }

            // DEBUG OUTPUT
        
            foreach ($videos as $video):
                // 動画1のサムネイル表示
                if (!empty($video['movie_1'])):
                    $thumb1 = !empty($video['movie_1_thumbnail']) ? $video['movie_1_thumbnail'] : $video['movie_1_seo_thumbnail'];
                    // パスの正規化（先頭にスラッシュをつける）
                    if ($thumb1 && !preg_match('|^https?://|', $thumb1)) {
                        $thumb1 = '/' . ltrim($thumb1, '/');
                    }
                    ?>
                    <div class="video-item">
                        <a href="<?php echo castDetailUrl($video['id']); ?>" class="video-link">
                            <div class="video-thumbnail">
                                <?php if (!empty($thumb1)): ?>
                                    <img src="<?php echo h($thumb1); ?>?v=<?php echo time(); ?>"
                                        alt="<?php echo h($video['name']); ?>の動画" loading="lazy"
                                        style="width: 100%; height: 100%; object-fit: cover;"
                                        onerror="this.style.display='none'; this.parentNode.innerHTML='<div style=&quot;display:flex;align-items:center;justify-content:center;height:100%;background:#f0f0f0;color:var(--color-text);&quot;>動画準備中</div>';">
                                <?php else: ?>
                                    <?php
                                    $m1 = $video['movie_1'];
                                    if (!preg_match('|^https?://|', $m1)) {
                                        $m1 = '/' . ltrim($m1, '/');
                                    }
                                    ?>
                                    <video data-src="<?php echo h($m1); ?>" muted loop playsinline preload="none"
                                        style="width: 100%; height: 100%; object-fit: contain; background: #f0f0f0;" class="lazy-video"
                                        onerror="this.style.display='none'; this.parentNode.innerHTML='<div style=&quot;display:flex;align-items:center;justify-content:center;height:100%;background:#f0f0f0;color:var(--color-text);&quot;>動画準備中</div>';">
                                    </video>
                                <?php endif; ?>
                            </div>
                        </a>
                    </div>
                <?php endif;

                // 動画2のサムネイル表示
                if (!empty($video['movie_2'])):
                    $thumb2 = !empty($video['movie_2_thumbnail']) ? $video['movie_2_thumbnail'] : $video['movie_2_seo_thumbnail'];
                    // パスの正規化
                    if ($thumb2 && !preg_match('|^https?://|', $thumb2)) {
                        $thumb2 = '/' . ltrim($thumb2, '/');
                    }
                    ?>
                    <div class="video-item">
                        <a href="<?php echo castDetailUrl($video['id']); ?>" class="video-link">
                            <div class="video-thumbnail">
                                <?php if (!empty($thumb2)): ?>
                                    <img src="<?php echo h($thumb2); ?>?v=<?php echo time(); ?>"
                                        alt="<?php echo h($video['name']); ?>の動画" loading="lazy"
                                        style="width: 100%; height: 100%; object-fit: cover;"
                                        onerror="this.style.display='none'; this.parentNode.innerHTML='<div style=&quot;display:flex;align-items:center;justify-content:center;height:100%;background:#f0f0f0;color:var(--color-text);&quot;>動画準備中</div>';">
                                <?php else: ?>
                                    <?php
                                    $m2 = $video['movie_2'];
                                    if (!preg_match('|^https?://|', $m2)) {
                                        $m2 = '/' . ltrim($m2, '/');
                                    }
                                    ?>
                                    <video data-src="<?php echo h($m2); ?>" muted loop playsinline preload="none"
                                        style="width: 100%; height: 100%; object-fit: contain; background: #f0f0f0;" class="lazy-video"
                                        onerror="this.style.display='none'; this.parentNode.innerHTML='<div style=&quot;display:flex;align-items:center;justify-content:center;height:100%;background:#f0f0f0;color:var(--color-text);&quot;>動画準備中</div>';">
                                    </video>
                                <?php endif; ?>
                            </div>
                        </a>
                    </div>
                <?php endif;
            endforeach;

            ?>
        </div>
    </div>

    <style>
        .video-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            padding: 0px 0;
        }

        .video-item {
            position: relative;
            width: 100%;
            padding-top: 56.25%;
            /* 16:9 Aspect Ratio */
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
            background: #f0f0f0;
        }

        .video-item:hover {
            transform: translateY(-3px);
        }

        .video-link {
            text-decoration: none;
            color: inherit;
            display: block;
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }

        .video-thumbnail {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #f0f0f0;
            overflow: hidden;
        }

        .video-thumbnail video,
        .video-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        @media (max-width: 767px) {
            .video-grid {
                grid-template-columns: repeat(2, 1fr);
                /* スマホは2列で */
                gap: 5px;
            }
        }
    </style>
    <?php
}
