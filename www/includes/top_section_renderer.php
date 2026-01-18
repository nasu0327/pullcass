<?php
/**
 * トップページ セクションレンダリング関数（pullcass版・マルチテナント対応）
 */

/**
 * メインレンダリング関数
 */
function renderSection($section, $pdo, $tenantId) {
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
            } else {
                renderPlaceholderSection($section);
            }
            break;
        case 'ranking':
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
function renderHeroTextSection($section) {
    $config = json_decode($section['config'], true) ?: [];
    $h1Title = trim($config['h1_title'] ?? '');
    $introText = trim($config['intro_text'] ?? '');
    
    // データが空の場合は何も表示しない
    if (empty($h1Title) && empty($introText)) {
        return;
    }
    ?>
    <div class="hero-text-section" style="background: rgba(255, 255, 255, 0.05); padding: 20px; border-radius: 12px; margin-bottom: 20px; text-align: center;">
        <?php if (!empty($h1Title)): ?>
        <h1 style="font-size: 1.5rem; font-weight: bold; color: var(--color-text); margin: 0 0 10px 0; line-height: 1.4;">
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
function renderBannerSection($section, $pdo, $tenantId) {
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
function renderTextContentSection($section) {
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
        <div class="text-content" style="padding: 15px; line-height: 1.6; color: rgba(255, 255, 255, 0.9);">
            <?php echo $htmlContent; // HTMLコンテンツをそのまま出力 ?>
        </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * 埋め込みウィジェットセクション
 */
function renderEmbedWidgetSection($section) {
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
function renderNewCastSection($section, $pdo, $tenantId) {
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
    
    // データソースに応じたテーブル名
    $tableMap = [
        'ekichika' => 'tenant_cast_data_ekichika',
        'heaven' => 'tenant_cast_data_heaven',
        'dto' => 'tenant_cast_data_dto'
    ];
    $tableName = $tableMap[$activeSource] ?? 'tenant_cast_data_ekichika';
    
    // 新人キャストを取得
    $newCasts = [];
    try {
        $stmt = $pdo->prepare("
            SELECT id, name, age, height, size, cup, pr_title, img1
            FROM {$tableName}
            WHERE tenant_id = ? AND checked = 1 AND `new` = '新人'
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
                        <a href="/app/front/cast/detail.php?id=<?php echo h($cast['id']); ?>" class="link-block">
                            <div class="cast-image">
                                <?php if ($cast['img1']): ?>
                                    <img src="<?php echo h($cast['img1']); ?>" 
                                         alt="<?php echo h($cast['name']); ?>"
                                         loading="eager">
                                <?php else: ?>
                                    <div style="width: 100%; height: 200px; background: rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,0.5);">
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
function renderTodayCastSection($section, $pdo, $tenantId) {
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
    
    // データソースに応じたテーブル名
    $tableMap = [
        'ekichika' => 'tenant_cast_data_ekichika',
        'heaven' => 'tenant_cast_data_heaven',
        'dto' => 'tenant_cast_data_dto'
    ];
    $tableName = $tableMap[$activeSource] ?? 'tenant_cast_data_ekichika';
    
    // 本日の出勤キャストを取得
    $todayCasts = [];
    try {
        // 今日はday1
        $stmt = $pdo->prepare("
            SELECT id, name, age, cup, pr_title, img1, day1, `now`, closed
            FROM {$tableName}
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
            <div class="title-ja"><?php echo $titleJa; ?><span style="display: inline-block; margin-left: 10px; font-size: 0.8em;"><?php echo h($today); ?>(<?php echo h($dayOfWeek); ?>)</span></div>
            <div class="dot-line"></div>
        </div>
        <?php if (!empty($todayCasts)): ?>
        <div class="scroll-wrapper">
            <div class="scroll-container-x">
                <div class="cast-cards cards-inline-flex">
                    <?php foreach ($todayCasts as $cast): ?>
                    <div class="cast-card">
                        <a href="/app/front/cast/detail.php?id=<?php echo h($cast['id']); ?>" class="link-block">
                            <div class="cast-image">
                                <?php if ($cast['img1']): ?>
                                    <img src="<?php echo h($cast['img1']); ?>" 
                                         alt="<?php echo h($cast['name']); ?>"
                                         loading="eager">
                                <?php else: ?>
                                    <div style="width: 100%; height: 200px; background: rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,0.5);">
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
                                <div style="text-align: center; display: flex; flex-wrap: wrap; justify-content: center; gap: 2px; padding: 0; margin: 4px 0 0 0;">
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
 * 閲覧履歴セクション
 */
function renderHistorySection($section) {
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
 * プレースホルダーセクション（デフォルトセクション用・準備中表示）
 */
function renderPlaceholderSection($section) {
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
function renderSectionStyles() {
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
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
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
            overflow: visible; /* グラデーションを外側に表示 */
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
