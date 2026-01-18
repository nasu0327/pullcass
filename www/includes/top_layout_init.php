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
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("トップページレイアウト管理の初期データ作成に失敗しました: " . $e->getMessage());
        // エラーが発生してもテナント作成は続行する
    }
}
