<?php
/**
 * インデックスページ（年齢確認ページ）レイアウト管理の初期データ作成
 * 新規テナント作成時に呼び出される
 */

/**
 * テナントのインデックスページレイアウト管理のデフォルトセクションを作成
 * 
 * @param PDO $pdo データベース接続
 * @param int $tenantId テナントID
 */
function initIndexLayoutSections($pdo, $tenantId) {
    try {
        // テーブルが存在するか確認
        $stmt = $pdo->query("SHOW TABLES LIKE 'index_layout_sections'");
        if ($stmt->rowCount() === 0) {
            error_log("インデックスページレイアウト管理テーブルが存在しません。マイグレーションを実行してください。");
            return; // テーブルが存在しない場合はスキップ
        }
        
        $pdo->beginTransaction();
        
        // デフォルトセクション定義
        $defaultSections = [
            // ヒーローセクション（背景画像/動画）
            [
                'section_key' => 'hero',
                'section_type' => 'hero',
                'admin_title' => 'ヒーローセクション',
                'title_en' => '',
                'title_ja' => '',
                'is_visible' => 1,
                'display_order' => 1,
                'config' => json_encode([
                    'background_type' => 'theme', // "image", "video", "theme"（テーマカラー）
                    'background_image' => '',
                    'background_video' => '',
                    'video_poster' => ''
                ])
            ],
            // 相互リンク
            [
                'section_key' => 'reciprocal_links',
                'section_type' => 'reciprocal_links',
                'admin_title' => '相互リンク',
                'title_en' => '',
                'title_ja' => '相互リンク',
                'is_visible' => 1,
                'display_order' => 100,
                'config' => json_encode([])
            ]
        ];
        
        // 各セクションを2つのテーブルに挿入（編集中と公開済み）
        foreach ($defaultSections as $section) {
            // index_layout_sections（編集中）
            $stmt = $pdo->prepare("
                INSERT INTO index_layout_sections 
                (tenant_id, section_key, section_type, admin_title, title_en, title_ja, 
                 is_visible, display_order, config)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $tenantId,
                $section['section_key'],
                $section['section_type'],
                $section['admin_title'],
                $section['title_en'],
                $section['title_ja'],
                $section['is_visible'],
                $section['display_order'],
                $section['config']
            ]);
            
            // index_layout_sections_published（公開済み）
            $stmt = $pdo->prepare("
                INSERT INTO index_layout_sections_published 
                (tenant_id, section_key, section_type, admin_title, title_en, title_ja, 
                 is_visible, display_order, config)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $tenantId,
                $section['section_key'],
                $section['section_type'],
                $section['admin_title'],
                $section['title_en'],
                $section['title_ja'],
                $section['is_visible'],
                $section['display_order'],
                $section['config']
            ]);
        }
        
        $pdo->commit();
        error_log("インデックスページレイアウト管理の初期データ作成に成功しました: tenant_id={$tenantId}");
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("インデックスページレイアウト管理の初期データ作成に失敗しました: tenant_id={$tenantId}, error=" . $e->getMessage());
        error_log("SQLエラーコード: " . $e->getCode());
        error_log("スタックトレース: " . $e->getTraceAsString());
    }
}

/**
 * 不足しているセクションを追加
 * 
 * @param PDO $pdo データベース接続
 * @param int $tenantId テナントID
 * @param array $missingSectionKeys 不足しているセクションキーの配列
 */
function addMissingIndexSections($pdo, $tenantId, $missingSectionKeys) {
    try {
        // テーブルが存在するか確認
        $stmt = $pdo->query("SHOW TABLES LIKE 'index_layout_sections'");
        if ($stmt->rowCount() === 0) {
            error_log("インデックスページレイアウト管理テーブルが存在しません。マイグレーションを実行してください。");
            return;
        }
        
        // 全セクション定義
        $allSections = [
            'hero' => [
                'section_key' => 'hero',
                'section_type' => 'hero',
                'admin_title' => 'ヒーローセクション',
                'title_en' => '',
                'title_ja' => '',
                'is_visible' => 1,
                'display_order' => 1,
                'config' => json_encode([
                    'background_type' => 'theme',
                    'background_image' => '',
                    'background_video' => '',
                    'video_poster' => ''
                ])
            ],
            'reciprocal_links' => [
                'section_key' => 'reciprocal_links',
                'section_type' => 'reciprocal_links',
                'admin_title' => '相互リンク',
                'title_en' => '',
                'title_ja' => '相互リンク',
                'is_visible' => 1,
                'display_order' => 100,
                'config' => json_encode([])
            ]
        ];
        
        $pdo->beginTransaction();
        
        // 不足しているセクションのみを追加
        foreach ($missingSectionKeys as $sectionKey) {
            if (!isset($allSections[$sectionKey])) {
                continue; // 定義されていないセクションはスキップ
            }
            
            $section = $allSections[$sectionKey];
            
            // 既に存在するかチェック
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM index_layout_sections WHERE tenant_id = ? AND section_key = ?");
            $checkStmt->execute([$tenantId, $section['section_key']]);
            if ($checkStmt->fetchColumn() > 0) {
                continue; // 既に存在する場合はスキップ
            }
            
            // index_layout_sections（編集中）
            $stmt = $pdo->prepare("
                INSERT INTO index_layout_sections 
                (tenant_id, section_key, section_type, admin_title, title_en, title_ja, 
                 is_visible, display_order, config)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $tenantId,
                $section['section_key'],
                $section['section_type'],
                $section['admin_title'],
                $section['title_en'],
                $section['title_ja'],
                $section['is_visible'],
                $section['display_order'],
                $section['config']
            ]);
            
            // index_layout_sections_published（公開済み）
            $stmt = $pdo->prepare("
                INSERT INTO index_layout_sections_published 
                (tenant_id, section_key, section_type, admin_title, title_en, title_ja, 
                 is_visible, display_order, config)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $tenantId,
                $section['section_key'],
                $section['section_type'],
                $section['admin_title'],
                $section['title_en'],
                $section['title_ja'],
                $section['is_visible'],
                $section['display_order'],
                $section['config']
            ]);
        }
        
        $pdo->commit();
        error_log("不足しているインデックスセクションを追加しました: tenant_id={$tenantId}, sections=" . implode(',', $missingSectionKeys));
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("不足しているインデックスセクション追加に失敗しました: tenant_id={$tenantId}, error=" . $e->getMessage());
    }
}

/**
 * デフォルトセクションのキー一覧を取得
 * 
 * @return array デフォルトセクションキーの配列
 */
function getIndexDefaultSectionKeys() {
    return ['hero', 'reciprocal_links'];
}

/**
 * セクションがデフォルトかどうかを判定
 * 
 * @param string $sectionKey セクションキー
 * @return bool デフォルトセクションの場合true
 */
function isIndexDefaultSection($sectionKey) {
    return in_array($sectionKey, getIndexDefaultSectionKeys());
}
