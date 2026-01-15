<?php
/**
 * テーマ管理システム - ヘルパー関数
 * 
 * テーマの取得・操作・CSS生成などの機能を提供（マルチテナント対応）
 * 
 * @package Theme Management System
 * @version 1.0.0
 * @date 2026-01-14
 */

// データベース接続を確保
global $pdo;
if (!isset($pdo) || $pdo === null) {
    if (function_exists('getPlatformDb')) {
        $pdo = getPlatformDb();
    } else {
        require_once __DIR__ . '/database.php';
    }
}

/**
 * 現在公開中のテーマを取得（テナント別）
 * 
 * @param int $tenantId テナントID
 * @return array|null テーマデータ（公開中のテーマがない場合はデフォルト）
 */
function getPublishedTheme($tenantId) {
    global $pdo;
    
    // データベース接続がない場合はデフォルトテンプレートを返す
    if (!$pdo) {
        return getDefaultTemplate();
    }
    
    try {
        // テーブルの存在確認
        $checkTable = $pdo->query("SHOW TABLES LIKE 'tenant_themes'");
        if ($checkTable->rowCount() === 0) {
            return getDefaultTemplate();
        }
        
        $stmt = $pdo->prepare("
            SELECT 
                id, 
                tenant_id,
                base_template_id,
                theme_name, 
                theme_type, 
                status, 
                theme_data, 
                is_customized,
                notes,
                published_at,
                updated_at
            FROM tenant_themes 
            WHERE tenant_id = ? AND status = 'published'
            LIMIT 1
        ");
        $stmt->execute([$tenantId]);
        $theme = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($theme) {
            $theme['theme_data'] = json_decode($theme['theme_data'], true);
            return $theme;
        }
        
        // 公開テーマがない場合はデフォルトテンプレートを返す
        return getDefaultTemplate();
        
    } catch (PDOException $e) {
        error_log("Error fetching published theme: " . $e->getMessage());
        return getDefaultTemplate();
    }
}

/**
 * プレビュー用のテーマを取得
 * 
 * @param int $themeId テーマID
 * @param int $tenantId テナントID（セキュリティチェック用）
 * @return array|null テーマデータ
 */
function getPreviewTheme($themeId, $tenantId = null) {
    global $pdo;
    
    try {
        $sql = "
            SELECT 
                id, 
                tenant_id,
                base_template_id,
                theme_name, 
                theme_type, 
                status, 
                theme_data, 
                is_customized,
                notes,
                created_at,
                updated_at
            FROM tenant_themes 
            WHERE id = ?
        ";
        
        $params = [$themeId];
        
        // テナントIDが指定されている場合はセキュリティチェック
        if ($tenantId !== null) {
            $sql .= " AND tenant_id = ?";
            $params[] = $tenantId;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $theme = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($theme) {
            $theme['theme_data'] = json_decode($theme['theme_data'], true);
            return $theme;
        }
        
        return null;
        
    } catch (PDOException $e) {
        error_log("Error fetching preview theme: " . $e->getMessage());
        return null;
    }
}

/**
 * デフォルトテンプレートを取得（フォールバック用）
 * 
 * @return array デフォルトテンプレートのデータ
 */
function getDefaultTemplate() {
    global $pdo;
    
    try {
        if ($pdo) {
            $stmt = $pdo->prepare("
                SELECT 
                    id,
                    template_name as theme_name,
                    template_slug,
                    description,
                    template_data as theme_data
                FROM tenant_theme_templates 
                WHERE template_slug = 'default'
                LIMIT 1
            ");
            $stmt->execute();
            $template = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($template) {
                $template['theme_data'] = json_decode($template['theme_data'], true);
                $template['theme_type'] = 'template_based';
                $template['status'] = 'published';
                return $template;
            }
        }
    } catch (PDOException $e) {
        error_log("Error fetching default template: " . $e->getMessage());
    }
    
    // データベースから取得できない場合はハードコードされたデフォルト値を返す
    return [
        'id' => 0,
        'theme_name' => 'デフォルトテーマ',
        'theme_type' => 'template_based',
        'status' => 'published',
        'theme_data' => [
            'colors' => [
                'primary' => '#f568df',
                'primary_light' => '#ffa0f8',
                'text' => '#474747',
                'btn_text' => '#ffffff',
                'bg' => '#ffffff',
                'overlay' => 'rgba(244, 114, 182, 0.2)',
                'bg_type' => 'solid'
            ],
            'fonts' => [
                'title1_en' => 'Kranky',
                'title1_ja' => 'Kaisei Decol',
                'title2_en' => 'Kranky',
                'title2_ja' => 'Kaisei Decol',
                'body_ja' => 'M PLUS 1p'
            ],
            'version' => '1.2.0'
        ]
    ];
}

/**
 * 現在適用すべきテーマを取得（テナント別）
 * プレビュー中の場合はプレビューテーマ、通常は公開テーマを返す
 * 
 * @param int $tenantId テナントID
 * @return array テーマデータ
 */
function getCurrentTheme($tenantId) {
    // セッション開始（まだ開始されていない場合）
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $sessionKey = 'theme_preview_id_' . $tenantId;
    
    // プレビューモードのチェック
    // 1. セッションにプレビューIDが保存されている場合（最優先 - 全ページで維持）
    //    ※プレビュー開始時にapi_preview.phpで管理者チェックを行うため、
    //      ここではセッション変数の存在のみで判定
    if (isset($_SESSION[$sessionKey])) {
        $previewId = (int)$_SESSION[$sessionKey];
        $previewTheme = getPreviewTheme($previewId, $tenantId);
        if ($previewTheme) {
            $previewTheme['is_preview'] = true;
            return $previewTheme;
        }
    }
    
    // 2. URLパラメータでプレビューIDが指定されている場合（管理者のみ）
    if (isset($_GET['preview_id']) && isTenantAdminLoggedIn($tenantId)) {
        $previewId = (int)$_GET['preview_id'];
        $previewTheme = getPreviewTheme($previewId, $tenantId);
        if ($previewTheme) {
            $previewTheme['is_preview'] = true;
            // セッションにも保存して全ページで維持
            $_SESSION[$sessionKey] = $previewId;
            return $previewTheme;
        }
    }
    
    // 通常は公開テーマを返す
    $theme = getPublishedTheme($tenantId);
    $theme['is_preview'] = false;
    return $theme;
}

/**
 * テナント管理者ログインチェック
 * 
 * @param int $tenantId テナントID
 * @return bool テナント管理者としてログインしているか
 */
function isTenantAdminLoggedIn($tenantId) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // テナント管理画面からのアクセスをチェック
    if (isset($_SESSION['current_tenant']) && $_SESSION['current_tenant']['id'] == $tenantId) {
        return true;
    }
    
    return false;
}

/**
 * テーマデータからCSS変数を生成
 * 
 * @param array $themeData テーマデータ
 * @return string CSS変数のスタイルタグ
 */
function generateThemeCSSVariables($themeData) {
    if (!isset($themeData['colors']) || !isset($themeData['fonts'])) {
        return '';
    }
    
    $colors = $themeData['colors'];
    $fonts = $themeData['fonts'];
    
    // 後方互換性: 古い構造のテーマの場合は新しい構造に変換
    if (isset($fonts['title_en']) && !isset($fonts['title1_en'])) {
        $fonts['title1_en'] = $fonts['title_en'];
        $fonts['title1_ja'] = $fonts['title_ja'] ?? 'Kaisei Decol';
        $fonts['title2_en'] = $fonts['title_en'];
        $fonts['title2_ja'] = $fonts['title_ja'] ?? 'Kaisei Decol';
        $fonts['body_ja'] = $fonts['body'] ?? 'M PLUS 1p';
    }
    
    // 背景タイプを取得（デフォルトは単色）
    $bgType = $colors['bg_type'] ?? 'solid';
    
    // 背景CSS生成
    $backgroundCss = '';
    if ($bgType === 'gradient') {
        $gradientStart = $colors['bg_gradient_start'] ?? $colors['bg'];
        $gradientEnd = $colors['bg_gradient_end'] ?? $colors['bg'];
        $backgroundCss = "background: linear-gradient(90deg, {$gradientStart} 0%, {$gradientEnd} 100%);";
    } else {
        $backgroundCss = "background-color: {$colors['bg']};";
    }
    
    $css = <<<CSS
<style id="theme-variables">
    :root {
        /* テーマカラー */
        --color-primary: {$colors['primary']};
        --color-primary-light: {$colors['primary_light']};
        --color-text: {$colors['text']};
        --color-btn-text: {$colors['btn_text']};
        --color-bg: {$colors['bg']};
        --color-overlay: {$colors['overlay']};
        
        /* テーマフォント - タイトル1（英字と日本語を組み合わせ） */
        --font-title1: '{$fonts['title1_en']}', '{$fonts['title1_ja']}', sans-serif;
        --font-title1-en: '{$fonts['title1_en']}', sans-serif;
        --font-title1-ja: '{$fonts['title1_ja']}', sans-serif;
        
        /* テーマフォント - タイトル2（英字と日本語を組み合わせ） */
        --font-title2: '{$fonts['title2_en']}', '{$fonts['title2_ja']}', sans-serif;
        --font-title2-en: '{$fonts['title2_en']}', sans-serif;
        --font-title2-ja: '{$fonts['title2_ja']}', sans-serif;
        
        /* テーマフォント - 本文 */
        --font-body: '{$fonts['body_ja']}', sans-serif;
    }
    
    /* 背景設定 */
    body {
        {$backgroundCss}
    }
    
    /* デフォルト：全て本文フォント */
    body, h1, h2, h3, h4, h5, h6 {
        font-family: var(--font-body);
    }
    
    /* タイトルセクションのみテーマフォント適用 */
    .title-section h1,
    .logo-main-title {
        font-family: var(--font-title1) !important;
    }
    
    .title-section h2 {
        font-family: var(--font-title2) !important;
    }
    
    /* ナビゲーションラベル（英字・日本語混在対応） */
    .nav-item-label {
        font-family: var(--font-title2) !important;
    }
CSS;
    
    // Codystarフォントが選択されている場合、太字を適用
    $codystarFonts = [];
    if (($fonts['title1_en'] ?? '') === 'Codystar') $codystarFonts[] = 'title1-en';
    if (($fonts['title1_ja'] ?? '') === 'Codystar') $codystarFonts[] = 'title1-ja';
    if (($fonts['title2_en'] ?? '') === 'Codystar') $codystarFonts[] = 'title2-en';
    if (($fonts['title2_ja'] ?? '') === 'Codystar') $codystarFonts[] = 'title2-ja';
    if (($fonts['body_ja'] ?? '') === 'Codystar') $codystarFonts[] = 'body';
    
    if (!empty($codystarFonts)) {
        $css .= "\n    /* Codystarフォント用の極太設定（text-shadowで視覚的に太くする） */\n";
        
        if (in_array('title1-en', $codystarFonts) || in_array('title1-ja', $codystarFonts)) {
            $css .= "    .section-title .title-en,\n";
            $css .= "    .logo-main-title,\n";
            $css .= "    .title-section h1 {\n";
            $css .= "        font-weight: 900 !important;\n";
            $css .= "        -webkit-text-stroke: 1px currentColor !important;\n";
            $css .= "        text-shadow: \n";
            $css .= "            1px 1px 0 currentColor,\n";
            $css .= "            -1px -1px 0 currentColor,\n";
            $css .= "            1px -1px 0 currentColor,\n";
            $css .= "            -1px 1px 0 currentColor !important;\n";
            $css .= "    }\n";
        }
        
        if (in_array('title2-en', $codystarFonts) || in_array('title2-ja', $codystarFonts)) {
            $css .= "    .section-title .title-ja,\n";
            $css .= "    .nav-item-label,\n";
            $css .= "    .title-section h2 {\n";
            $css .= "        font-weight: 900 !important;\n";
            $css .= "        -webkit-text-stroke: 1px currentColor !important;\n";
            $css .= "        text-shadow: \n";
            $css .= "            1px 1px 0 currentColor,\n";
            $css .= "            -1px -1px 0 currentColor,\n";
            $css .= "            1px -1px 0 currentColor,\n";
            $css .= "            -1px 1px 0 currentColor !important;\n";
            $css .= "    }\n";
        }
        
        if (in_array('body', $codystarFonts)) {
            $css .= "    body {\n";
            $css .= "        font-weight: 900 !important;\n";
            $css .= "        -webkit-text-stroke: 0.5px currentColor !important;\n";
            $css .= "        text-shadow: \n";
            $css .= "            0.5px 0.5px 0 currentColor,\n";
            $css .= "            -0.5px -0.5px 0 currentColor,\n";
            $css .= "            0.5px -0.5px 0 currentColor,\n";
            $css .= "            -0.5px 0.5px 0 currentColor !important;\n";
            $css .= "    }\n";
        }
    }
    
    $css .= "</style>\n";
    
    return $css;
}

/**
 * プレビューバーを生成（管理者がプレビュー中の場合のみ）
 * 
 * @param array $theme テーマデータ
 * @param int $tenantId テナントID
 * @param string $tenantSlug テナントスラッグ
 * @return string プレビューバーのHTML
 */
function generatePreviewBar($theme, $tenantId, $tenantSlug) {
    if (!isset($theme['is_preview']) || !$theme['is_preview']) {
        return '';
    }
    
    $themeName = htmlspecialchars($theme['theme_name'], ENT_QUOTES, 'UTF-8');
    $themeId = (int)$theme['id'];
    
    // テーマのカラー設定を取得
    $primaryColor = $theme['theme_data']['colors']['primary'] ?? '#ff6b35';
    $btnTextColor = $theme['theme_data']['colors']['btn_text'] ?? '#ffffff';
    
    // iframe内プレビューの場合はバッジを表示しない
    if (isset($_GET['iframe_preview']) && $_GET['iframe_preview'] == '1') {
        return '';
    }
    
    // モーダル表示条件（プレビューモードでは常に表示、ただしセッションで既に表示済みの場合はスキップ）
    $modalSessionKey = 'theme_preview_modal_shown_' . $tenantId;
    $showModal = !isset($_SESSION[$modalSessionKey]);
    if ($showModal) {
        $_SESSION[$modalSessionKey] = true;
    }
    
    $html = '';
    
    // パンくずに「プレビューモード」を追加するスクリプト
    $html .= <<<HTML
<style>
    .preview-mode-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: {$primaryColor};
        color: {$btnTextColor};
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: bold;
        margin-right: 8px;
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .preview-mode-badge:hover {
        opacity: 0.9;
        transform: scale(1.02);
    }
    .preview-mode-badge .exit-icon {
        font-size: 12px;
        opacity: 0.8;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // パンくずに「プレビューモード」バッジを追加
    const breadcrumb = document.querySelector('.breadcrumb');
    if (breadcrumb) {
        const previewBadge = document.createElement('span');
        previewBadge.className = 'preview-mode-badge';
        previewBadge.innerHTML = 'プレビューモード <span class="exit-icon">✕</span>';
        previewBadge.title = 'クリックでプレビュー終了';
        previewBadge.addEventListener('click', function() {
            fetch('/app/manage/themes/api_preview.php?action=stop&tenant={$tenantSlug}', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const currentUrl = window.location.pathname;
                    if (currentUrl.includes('_preview.php') || currentUrl.includes('preview.php')) {
                        window.close();
                        setTimeout(function() {
                            window.location.href = '/app/manage/themes/?tenant={$tenantSlug}';
                        }, 100);
                    } else {
                        window.location.reload();
                    }
                } else {
                    alert('エラー: ' + data.message);
                }
            })
            .catch(error => {
                console.error('プレビュー終了エラー:', error);
                window.location.reload();
            });
        });
        breadcrumb.insertBefore(previewBadge, breadcrumb.firstChild);
    }
    
    // ウィンドウを閉じるときにセッションをクリア
    window.addEventListener('beforeunload', function() {
        if (navigator.sendBeacon) {
            navigator.sendBeacon('/app/manage/themes/api_preview.php?action=stop&tenant={$tenantSlug}');
        }
    });
});
</script>
HTML;
    
    // モーダル表示が有効な場合のみ追加
    if ($showModal) {
        $html .= <<<HTML

<!-- テーマプレビュー用モーダル警告 -->
<div id="preview-modal-overlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(5px); z-index: 10001; display: flex; align-items: center; justify-content: center; opacity: 1; transition: opacity 0.3s ease;">
    <div id="preview-modal" style="background: #fff; color: #333; padding: 30px 40px; border-radius: 15px; box-shadow: 0 10px 40px rgba(0,0,0,0.3); max-width: 500px; text-align: center; transform: scale(1); transition: transform 0.3s ease; border-top: 5px solid {$primaryColor};">
        <div style="margin-bottom: 20px;">
            <div style="font-size: 48px; margin-bottom: 10px;"><i class="fas fa-exclamation-triangle" style="color: {$primaryColor};"></i></div>
            <h3 style="margin: 0 0 15px 0; font-size: 20px; font-weight: bold; color: #333;">テーマプレビューモード</h3>
            <p style="margin: 0; font-size: 15px; color: #d9534f; font-weight: bold; line-height: 1.6;">
                プレビューを終了する場合は<br>
                必ず「プレビューモード ✕」で<br>
                閉じてください！
            </p>
            <p style="margin: 15px 0 0 0; font-size: 13px; color: #666;">
                ※ウィンドウの✕ボタンで閉じても終了できます
            </p>
        </div>
        <button id="close-preview-modal" style="background: {$primaryColor}; border: none; color: #fff; padding: 12px 30px; border-radius: 25px; cursor: pointer; font-weight: bold; font-size: 15px; transition: all 0.3s ease;">
            OK、理解しました
        </button>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const overlay = document.getElementById('preview-modal-overlay');
    const modal = document.getElementById('preview-modal');
    const closeBtn = document.getElementById('close-preview-modal');
    
    if (closeBtn && overlay && modal) {
        closeBtn.addEventListener('click', function() {
            modal.style.transform = 'scale(0.9)';
            overlay.style.opacity = '0';
            setTimeout(function() {
                overlay.style.display = 'none';
            }, 300);
        });
        
        closeBtn.addEventListener('mouseover', function() {
            this.style.opacity = '0.9';
            this.style.transform = 'scale(1.05)';
        });
        closeBtn.addEventListener('mouseout', function() {
            this.style.opacity = '1';
            this.style.transform = 'scale(1)';
        });
    }
});
</script>
HTML;
    }
    
    return $html;
}

/**
 * テーマ監査ログを記録
 * 
 * @param int $themeId テーマID
 * @param int $tenantId テナントID
 * @param string $action アクション
 * @param array|null $beforeData 変更前データ
 * @param array|null $afterData 変更後データ
 * @return bool 成功したか
 */
function insertThemeAuditLog($themeId, $tenantId, $action, $beforeData = null, $afterData = null) {
    global $pdo;
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $adminId = $_SESSION['tenant_admin_id'] ?? 0;
    $adminName = $_SESSION['tenant_admin_name'] ?? 'unknown';
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO tenant_theme_audit_log 
            (theme_id, tenant_id, action, admin_id, admin_name, before_data, after_data, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $themeId,
            $tenantId,
            $action,
            $adminId,
            $adminName,
            $beforeData ? json_encode($beforeData) : null,
            $afterData ? json_encode($afterData) : null,
            $ipAddress,
            $userAgent
        ]);
        
        return true;
        
    } catch (PDOException $e) {
        error_log("Error inserting theme audit log: " . $e->getMessage());
        return false;
    }
}

/**
 * すべてのテーマテンプレートを取得
 * 
 * @return array テンプレートの配列
 */
function getAllThemeTemplates() {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                id,
                template_name,
                template_slug,
                description,
                thumbnail_url,
                template_data,
                display_order
            FROM tenant_theme_templates
            WHERE is_active = 1
            ORDER BY display_order ASC
        ");
        $stmt->execute();
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($templates as &$template) {
            $template['template_data'] = json_decode($template['template_data'], true);
        }
        
        return $templates;
        
    } catch (PDOException $e) {
        error_log("Error fetching templates: " . $e->getMessage());
        return [];
    }
}

/**
 * テンプレートIDからテンプレートデータを取得
 * 
 * @param int $templateId テンプレートID
 * @return array|null テンプレートデータ
 */
function getThemeTemplateById($templateId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                id,
                template_name,
                template_slug,
                description,
                template_data
            FROM tenant_theme_templates
            WHERE id = ?
        ");
        $stmt->execute([$templateId]);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($template) {
            $template['template_data'] = json_decode($template['template_data'], true);
            return $template;
        }
        
        return null;
        
    } catch (PDOException $e) {
        error_log("Error fetching template: " . $e->getMessage());
        return null;
    }
}

/**
 * テナントのすべてのテーマを取得（管理画面用）
 * 
 * @param int $tenantId テナントID
 * @return array テーマの配列
 */
function getAllTenantThemes($tenantId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                t.id,
                t.tenant_id,
                t.base_template_id,
                t.theme_name,
                t.theme_type,
                t.status,
                t.theme_data,
                t.is_customized,
                t.notes,
                t.created_at,
                t.updated_at,
                t.published_at,
                tt.template_name as base_template_name
            FROM tenant_themes t
            LEFT JOIN tenant_theme_templates tt ON t.base_template_id = tt.id
            WHERE t.tenant_id = ?
            ORDER BY 
                CASE WHEN t.status = 'published' THEN 0 ELSE 1 END,
                t.updated_at DESC
        ");
        $stmt->execute([$tenantId]);
        $themes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($themes as &$theme) {
            $theme['theme_data'] = json_decode($theme['theme_data'], true);
        }
        
        return $themes;
        
    } catch (PDOException $e) {
        error_log("Error fetching all themes: " . $e->getMessage());
        return [];
    }
}

/**
 * テーマIDからテーマデータを取得
 * 
 * @param int $themeId テーマID
 * @param int|null $tenantId テナントID（セキュリティチェック用）
 * @return array|null テーマデータ
 */
function getTenantThemeById($themeId, $tenantId = null) {
    global $pdo;
    
    try {
        $sql = "
            SELECT 
                t.id,
                t.tenant_id,
                t.base_template_id,
                t.theme_name,
                t.theme_type,
                t.status,
                t.theme_data,
                t.is_customized,
                t.notes,
                t.created_at,
                t.updated_at,
                t.published_at,
                tt.template_name as base_template_name
            FROM tenant_themes t
            LEFT JOIN tenant_theme_templates tt ON t.base_template_id = tt.id
            WHERE t.id = ?
        ";
        
        $params = [$themeId];
        
        if ($tenantId !== null) {
            $sql .= " AND t.tenant_id = ?";
            $params[] = $tenantId;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $theme = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($theme) {
            $theme['theme_data'] = json_decode($theme['theme_data'], true);
            return $theme;
        }
        
        return null;
        
    } catch (PDOException $e) {
        error_log("Error fetching theme by ID: " . $e->getMessage());
        return null;
    }
}

/**
 * フォント用のGoogle Fonts読み込みタグを生成
 * 
 * @return string Google Fontsのlinkタグ
 */
function generateGoogleFontsLink() {
    $fonts = [
        'Kranky',
        'Rampart+One',
        'Codystar:wght@300;400',
        'Griffy',
        'MonteCarlo',
        'Molle:ital@1',
        'Inter:wght@400;500;600;700',
        'Kaisei+Decol:wght@400;500;700',
        'Kaisei+Opti:wght@400;500;700',
        'Yuji+Boku',
        'Hachi+Maru+Pop',
        'Klee+One:wght@400;600',
        'Mochiy+Pop+One',
        'Reggae+One',
        'Shippori+Mincho:wght@400;500;600;700',
        'M+PLUS+1p:wght@400;500;700',
        'Noto+Sans+JP:wght@400;500;700',
        'BIZ+UDPGothic:wght@400;700'
    ];
    
    $fontString = implode('&family=', $fonts);
    
    return '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n" .
           '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n" .
           '<link href="https://fonts.googleapis.com/css2?family=' . $fontString . '&display=swap" rel="stylesheet">';
}
