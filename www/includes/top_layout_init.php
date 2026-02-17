<?php
/**
 * トップページレイアウト管理の初期データ作成
 * 新規テナント作成時に呼び出される
 */

/**
 * テナントのトップページレイアウト管理のデフォルトセクションを作成
 * 
 * @param PDO $pdo データベース接続
 * @param int $tenantId テナントID
 */
function initTopLayoutSections($pdo, $tenantId) {
    try {
        // テーブルが存在するか確認
        $stmt = $pdo->query("SHOW TABLES LIKE 'top_layout_sections'");
        if ($stmt->rowCount() === 0) {
            error_log("トップページレイアウト管理テーブルが存在しません。マイグレーションを実行してください。");
            return; // テーブルが存在しない場合はスキップ
        }
        
        $pdo->beginTransaction();
        
        // デフォルトセクション定義（全て非表示）
        $defaultSections = [
            // バナー下テキスト（hero_text）
            [
                'section_key' => 'hero_text',
                'section_type' => 'hero_text',
                'default_column' => null,
                'admin_title' => 'トップバナー下テキスト',
                'title_en' => '',
                'title_ja' => '',
                'is_visible' => 0,
                'mobile_visible' => 0,
                'pc_left_order' => null,
                'pc_right_order' => null,
                'mobile_order' => 1,
                'config' => json_encode([
                    'h1_title' => '',
                    'intro_text' => ''
                ])
            ],
            // 左カラム: 新人（new_cast）
            [
                'section_key' => 'new_cast',
                'section_type' => 'cast_list',
                'default_column' => 'left',
                'admin_title' => '新人キャスト',
                'title_en' => 'NEW CAST',
                'title_ja' => '新人',
                'is_visible' => 0,
                'mobile_visible' => 0,
                'pc_left_order' => 1,
                'pc_right_order' => null,
                'mobile_order' => 2,
                'config' => json_encode([])
            ],
            // 左カラム: 本日の出勤（today_cast）
            [
                'section_key' => 'today_cast',
                'section_type' => 'cast_list',
                'default_column' => 'left',
                'admin_title' => '本日の出勤キャスト',
                'title_en' => 'TODAY',
                'title_ja' => '本日の出勤',
                'is_visible' => 0,
                'mobile_visible' => 0,
                'pc_left_order' => 2,
                'pc_right_order' => null,
                'mobile_order' => 3,
                'config' => json_encode([])
            ],
            // 左カラム: 動画（videos）
            [
                'section_key' => 'videos',
                'section_type' => 'content',
                'default_column' => 'left',
                'admin_title' => '動画一覧',
                'title_en' => 'VIDEO',
                'title_ja' => '動画',
                'is_visible' => 0,
                'mobile_visible' => 0,
                'pc_left_order' => 3,
                'pc_right_order' => null,
                'mobile_order' => 5,
                'config' => json_encode([])
            ],
            // 左カラム: リピートランキング（repeat_ranking）
            [
                'section_key' => 'repeat_ranking',
                'section_type' => 'ranking',
                'default_column' => 'left',
                'admin_title' => 'リピートランキング',
                'title_en' => 'RANKING',
                'title_ja' => 'リピートランキング',
                'is_visible' => 0,
                'mobile_visible' => 0,
                'pc_left_order' => 4,
                'pc_right_order' => null,
                'mobile_order' => 6,
                'config' => json_encode([])
            ],
            // 左カラム: 注目度ランキング（attention_ranking）
            [
                'section_key' => 'attention_ranking',
                'section_type' => 'ranking',
                'default_column' => 'left',
                'admin_title' => '注目度ランキング',
                'title_en' => 'RANKING',
                'title_ja' => '注目度ランキング',
                'is_visible' => 0,
                'mobile_visible' => 0,
                'pc_left_order' => 5,
                'pc_right_order' => null,
                'mobile_order' => 7,
                'config' => json_encode([])
            ],
            // 右カラム: 閲覧履歴（history）
            [
                'section_key' => 'history',
                'section_type' => 'content',
                'default_column' => 'right',
                'admin_title' => '閲覧履歴',
                'title_en' => 'HISTORY',
                'title_ja' => '閲覧履歴',
                'is_visible' => 0,
                'mobile_visible' => 0,
                'pc_left_order' => null,
                'pc_right_order' => 1,
                'mobile_order' => 4,
                'config' => json_encode([])
            ],
            // 右カラム: 写メ日記（diary）※有料オプション・マスター管理でONでも店舗管理のデフォルトは非表示
            [
                'section_key' => 'diary',
                'section_type' => 'content',
                'default_column' => 'right',
                'admin_title' => '写メ日記',
                'title_en' => 'DIARY',
                'title_ja' => '動画・写メ日記',
                'is_visible' => 0,   // 常にデフォルト非表示（店舗が表示ONにするまで出さない）
                'mobile_visible' => 0,
                'pc_left_order' => null,
                'pc_right_order' => 2,
                'mobile_order' => 5,
                'config' => json_encode([])
            ],
            // 右カラム: 口コミ（reviews）※有料オプション
            [
                'section_key' => 'reviews',
                'section_type' => 'content',
                'default_column' => 'right',
                'admin_title' => '口コミ',
                'title_en' => 'REVIEW',
                'title_ja' => '口コミ',
                'is_visible' => 0,
                'mobile_visible' => 0,
                'pc_left_order' => null,
                'pc_right_order' => 3,
                'mobile_order' => 6,
                'config' => json_encode([])
            ]
        ];
        
        // 各セクションを3つのテーブルに挿入
        foreach ($defaultSections as $section) {
            // top_layout_sections（編集中）
            $stmt = $pdo->prepare("
                INSERT INTO top_layout_sections 
                (tenant_id, section_key, section_type, default_column, admin_title, title_en, title_ja, 
                 is_visible, mobile_visible, pc_left_order, pc_right_order, mobile_order, status, config)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?)
            ");
            $stmt->execute([
                $tenantId,
                $section['section_key'],
                $section['section_type'],
                $section['default_column'],
                $section['admin_title'],
                $section['title_en'],
                $section['title_ja'],
                $section['is_visible'],
                $section['mobile_visible'],
                $section['pc_left_order'],
                $section['pc_right_order'],
                $section['mobile_order'],
                $section['config']
            ]);
            
            // top_layout_sections_published（公開済み）
            $stmt = $pdo->prepare("
                INSERT INTO top_layout_sections_published 
                (tenant_id, section_key, section_type, default_column, admin_title, title_en, title_ja, 
                 is_visible, mobile_visible, pc_left_order, pc_right_order, mobile_order, status, config)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'published', ?)
            ");
            $stmt->execute([
                $tenantId,
                $section['section_key'],
                $section['section_type'],
                $section['default_column'],
                $section['admin_title'],
                $section['title_en'],
                $section['title_ja'],
                $section['is_visible'],
                $section['mobile_visible'],
                $section['pc_left_order'],
                $section['pc_right_order'],
                $section['mobile_order'],
                $section['config']
            ]);
        }
        
        $pdo->commit();
        error_log("トップページレイアウト管理の初期データ作成に成功しました: tenant_id={$tenantId}");
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("トップページレイアウト管理の初期データ作成に失敗しました: tenant_id={$tenantId}, error=" . $e->getMessage());
        error_log("SQLエラーコード: " . $e->getCode());
        error_log("スタックトレース: " . $e->getTraceAsString());
        // エラーが発生してもテナント作成は続行する
        // ただし、エラーログに記録される
    }
}

/**
 * 不足しているセクションを追加
 * 
 * @param PDO $pdo データベース接続
 * @param int $tenantId テナントID
 * @param array $missingSectionKeys 不足しているセクションキーの配列
 */
function addMissingSections($pdo, $tenantId, $missingSectionKeys) {
    try {
        // テーブルが存在するか確認
        $stmt = $pdo->query("SHOW TABLES LIKE 'top_layout_sections'");
        if ($stmt->rowCount() === 0) {
            error_log("トップページレイアウト管理テーブルが存在しません。マイグレーションを実行してください。");
            return;
        }
        
        // 全セクション定義
        $allSections = [
            'hero_text' => [
                'section_key' => 'hero_text',
                'section_type' => 'hero_text',
                'default_column' => null,
                'admin_title' => 'トップバナー下テキスト',
                'title_en' => '',
                'title_ja' => '',
                'is_visible' => 0,
                'mobile_visible' => 0,
                'pc_left_order' => null,
                'pc_right_order' => null,
                'mobile_order' => 1,
                'config' => json_encode(['h1_title' => '', 'intro_text' => ''])
            ],
            'new_cast' => [
                'section_key' => 'new_cast',
                'section_type' => 'cast_list',
                'default_column' => 'left',
                'admin_title' => '新人キャスト',
                'title_en' => 'NEW CAST',
                'title_ja' => '新人',
                'is_visible' => 0,
                'mobile_visible' => 0,
                'pc_left_order' => 1,
                'pc_right_order' => null,
                'mobile_order' => 2,
                'config' => json_encode([])
            ],
            'today_cast' => [
                'section_key' => 'today_cast',
                'section_type' => 'cast_list',
                'default_column' => 'left',
                'admin_title' => '本日の出勤キャスト',
                'title_en' => 'TODAY',
                'title_ja' => '本日の出勤',
                'is_visible' => 0,
                'mobile_visible' => 0,
                'pc_left_order' => 2,
                'pc_right_order' => null,
                'mobile_order' => 3,
                'config' => json_encode([])
            ],
            'videos' => [
                'section_key' => 'videos',
                'section_type' => 'content',
                'default_column' => 'left',
                'admin_title' => '動画一覧',
                'title_en' => 'VIDEO',
                'title_ja' => '動画',
                'is_visible' => 0,
                'mobile_visible' => 0,
                'pc_left_order' => 3,
                'pc_right_order' => null,
                'mobile_order' => 5,
                'config' => json_encode([])
            ],
            'repeat_ranking' => [
                'section_key' => 'repeat_ranking',
                'section_type' => 'ranking',
                'default_column' => 'left',
                'admin_title' => 'リピートランキング',
                'title_en' => 'RANKING',
                'title_ja' => 'リピートランキング',
                'is_visible' => 0,
                'mobile_visible' => 0,
                'pc_left_order' => 4,
                'pc_right_order' => null,
                'mobile_order' => 6,
                'config' => json_encode([])
            ],
            'attention_ranking' => [
                'section_key' => 'attention_ranking',
                'section_type' => 'ranking',
                'default_column' => 'left',
                'admin_title' => '注目度ランキング',
                'title_en' => 'RANKING',
                'title_ja' => '注目度ランキング',
                'is_visible' => 0,
                'mobile_visible' => 0,
                'pc_left_order' => 5,
                'pc_right_order' => null,
                'mobile_order' => 7,
                'config' => json_encode([])
            ],
            'history' => [
                'section_key' => 'history',
                'section_type' => 'content',
                'default_column' => 'right',
                'admin_title' => '閲覧履歴',
                'title_en' => 'HISTORY',
                'title_ja' => '閲覧履歴',
                'is_visible' => 0,
                'mobile_visible' => 0,
                'pc_left_order' => null,
                'pc_right_order' => 1,
                'mobile_order' => 4,
                'config' => json_encode([])
            ],
            'diary' => [
                'section_key' => 'diary',
                'section_type' => 'content',
                'default_column' => 'right',
                'admin_title' => '写メ日記',
                'title_en' => 'DIARY',
                'title_ja' => '動画・写メ日記',
                'is_visible' => 0,   // マスター管理でONでも店舗管理のデフォルトは非表示
                'mobile_visible' => 0,
                'pc_left_order' => null,
                'pc_right_order' => 2,
                'mobile_order' => 5,
                'config' => json_encode([])
            ],
            'reviews' => [
                'section_key' => 'reviews',
                'section_type' => 'content',
                'default_column' => 'right',
                'admin_title' => '口コミ',
                'title_en' => 'REVIEW',
                'title_ja' => '口コミ',
                'is_visible' => 0,
                'mobile_visible' => 0,
                'pc_left_order' => null,
                'pc_right_order' => 3,
                'mobile_order' => 6,
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
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM top_layout_sections WHERE tenant_id = ? AND section_key = ?");
            $checkStmt->execute([$tenantId, $section['section_key']]);
            if ($checkStmt->fetchColumn() > 0) {
                continue; // 既に存在する場合はスキップ
            }
            
            // top_layout_sections（編集中）
            $stmt = $pdo->prepare("
                INSERT INTO top_layout_sections 
                (tenant_id, section_key, section_type, default_column, admin_title, title_en, title_ja, 
                 is_visible, mobile_visible, pc_left_order, pc_right_order, mobile_order, status, config)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?)
            ");
            $stmt->execute([
                $tenantId,
                $section['section_key'],
                $section['section_type'],
                $section['default_column'],
                $section['admin_title'],
                $section['title_en'],
                $section['title_ja'],
                $section['is_visible'],
                $section['mobile_visible'],
                $section['pc_left_order'],
                $section['pc_right_order'],
                $section['mobile_order'],
                $section['config']
            ]);
            
            // top_layout_sections_published（公開済み）
            $stmt = $pdo->prepare("
                INSERT INTO top_layout_sections_published 
                (tenant_id, section_key, section_type, default_column, admin_title, title_en, title_ja, 
                 is_visible, mobile_visible, pc_left_order, pc_right_order, mobile_order, status, config)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'published', ?)
            ");
            $stmt->execute([
                $tenantId,
                $section['section_key'],
                $section['section_type'],
                $section['default_column'],
                $section['admin_title'],
                $section['title_en'],
                $section['title_ja'],
                $section['is_visible'],
                $section['mobile_visible'],
                $section['pc_left_order'],
                $section['pc_right_order'],
                $section['mobile_order'],
                $section['config']
            ]);
        }
        
        $pdo->commit();
        error_log("不足しているセクションを追加しました: tenant_id={$tenantId}, sections=" . implode(',', $missingSectionKeys));
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("不足しているセクション追加に失敗しました: tenant_id={$tenantId}, error=" . $e->getMessage());
    }
}
