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
        case 'ranking':
        case 'content':
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
        /* トップバナースライダー */
        .top-banner-section {
            margin-bottom: 50px; /* ドット用のスペース確保 */
        }
        
        .top-banner-container {
            position: relative;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .topBannerSwiper {
            border-radius: 15px;
            overflow: visible !important; /* ドットを外側に表示 */
            width: 100%;
        }
        
        .topBannerSwiper .swiper-wrapper {
            display: flex;
            position: relative;
            width: 100%;
            height: 100%;
            z-index: 1;
        }
        
        .topBannerSwiper .swiper-slide {
            flex-shrink: 0;
            width: 100%;
            height: 100%;
            position: relative;
        }
        
        .topBannerSwiper .swiper-slide a {
            display: block;
            width: 100%;
            height: 100%;
        }
        
        .topBannerSwiper .swiper-slide picture {
            display: block;
            width: 100%;
            height: 100%;
        }
        
        .topBannerSwiper .swiper-slide img {
            display: block;
            width: 100%;
            height: auto;
            border-radius: 15px;
        }
        
        /* Swiperナビゲーションボタン（小さく、白い丸背景） */
        .topBannerSwiper .swiper-button-next,
        .topBannerSwiper .swiper-button-prev {
            width: 35px !important;
            height: 35px !important;
            background: rgba(255, 255, 255, 0.8) !important;
            border-radius: 50% !important;
            color: var(--color-primary) !important;
        }
        
        .topBannerSwiper .swiper-button-next::after,
        .topBannerSwiper .swiper-button-prev::after {
            font-size: 16px !important;
            font-weight: bold !important;
        }
        
        /* Swiperページネーション（ドット）を画像の下に配置 */
        .topBannerSwiper .swiper-pagination {
            position: absolute !important;
            bottom: -30px !important;
            left: 0 !important;
            width: 100% !important;
            text-align: center !important;
            z-index: 10 !important; /* 画像の上に表示 */
        }
        
        .topBannerSwiper .swiper-pagination-bullet {
            background: rgba(128, 128, 128, 0.5) !important;
            opacity: 1 !important;
            width: 10px !important;
            height: 10px !important;
            margin: 0 4px !important;
        }
        
        .topBannerSwiper .swiper-pagination-bullet-active {
            background: var(--color-primary) !important;
            width: 12px !important;
            height: 12px !important;
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
        }
    </style>
    <?php
}
