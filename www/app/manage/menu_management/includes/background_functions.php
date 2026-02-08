<?php
/**
 * メニュー背景設定 - ヘルパー関数
 * テナントごとのメニュー背景設定を管理
 */

/**
 * メニュー背景設定を取得
 * 
 * @param PDO $pdo データベース接続
 * @param int $tenantId テナントID
 * @return array|null 設定データ、存在しない場合はnull
 */
function getMenuBackground($pdo, $tenantId)
{
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM menu_settings 
            WHERE tenant_id = ?
        ");
        $stmt->execute([$tenantId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // 設定が存在しない場合はデフォルト値を返す
        if (!$result) {
            return [
                'background_type' => 'theme',
                'background_color' => null,
                'gradient_start' => null,
                'gradient_end' => null,
                'background_image' => null,
                'overlay_color' => '#000000',
                'overlay_opacity' => 0.5
            ];
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log("メニュー背景設定取得エラー: " . $e->getMessage());
        return null;
    }
}

/**
 * メニュー背景設定を保存
 * 
 * @param PDO $pdo データベース接続
 * @param int $tenantId テナントID
 * @param array $data 設定データ
 * @return array 結果 ['success' => bool, 'message' => string]
 */
function saveMenuBackground($pdo, $tenantId, $data)
{
    try {
        // 既存の設定を確認
        $existing = getMenuBackground($pdo, $tenantId);
        
        // データの検証
        $backgroundType = $data['background_type'] ?? 'theme';
        $allowedTypes = ['theme', 'solid', 'gradient', 'image'];
        if (!in_array($backgroundType, $allowedTypes)) {
            return ['success' => false, 'message' => '不正な背景タイプです'];
        }
        
        // カラーコードの検証
        $backgroundColor = isset($data['background_color']) ? trim($data['background_color']) : null;
        $gradientStart = isset($data['gradient_start']) ? trim($data['gradient_start']) : null;
        $gradientEnd = isset($data['gradient_end']) ? trim($data['gradient_end']) : null;
        $overlayColor = isset($data['overlay_color']) ? trim($data['overlay_color']) : '#000000';
        
        // 透明度の検証
        $overlayOpacity = isset($data['overlay_opacity']) ? floatval($data['overlay_opacity']) : 0.5;
        if ($overlayOpacity < 0) $overlayOpacity = 0;
        if ($overlayOpacity > 1) $overlayOpacity = 1;
        
        // 画像パス
        $backgroundImage = $data['background_image'] ?? null;
        
        // レコードが存在する場合は更新、存在しない場合は挿入
        if ($existing && isset($existing['id'])) {
            $stmt = $pdo->prepare("
                UPDATE menu_settings SET
                    background_type = ?,
                    background_color = ?,
                    gradient_start = ?,
                    gradient_end = ?,
                    background_image = ?,
                    overlay_color = ?,
                    overlay_opacity = ?,
                    updated_at = CURRENT_TIMESTAMP
                WHERE tenant_id = ?
            ");
            $stmt->execute([
                $backgroundType,
                $backgroundColor,
                $gradientStart,
                $gradientEnd,
                $backgroundImage,
                $overlayColor,
                $overlayOpacity,
                $tenantId
            ]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO menu_settings (
                    tenant_id, background_type, background_color, 
                    gradient_start, gradient_end, background_image,
                    overlay_color, overlay_opacity
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $tenantId,
                $backgroundType,
                $backgroundColor,
                $gradientStart,
                $gradientEnd,
                $backgroundImage,
                $overlayColor,
                $overlayOpacity
            ]);
        }
        
        return ['success' => true, 'message' => '設定を保存しました'];
    } catch (PDOException $e) {
        error_log("メニュー背景設定保存エラー: " . $e->getMessage());
        return ['success' => false, 'message' => 'エラーが発生しました'];
    }
}

/**
 * 背景画像を削除
 * 
 * @param PDO $pdo データベース接続
 * @param int $tenantId テナントID
 * @return array 結果 ['success' => bool, 'message' => string]
 */
function deleteMenuBackgroundImage($pdo, $tenantId)
{
    try {
        // 現在の設定を取得
        $settings = getMenuBackground($pdo, $tenantId);
        
        // 画像ファイルを削除
        if (!empty($settings['background_image'])) {
            $imagePath = $_SERVER['DOCUMENT_ROOT'] . $settings['background_image'];
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }
        
        // データベースから画像パスを削除
        $stmt = $pdo->prepare("
            UPDATE menu_settings 
            SET background_image = NULL, background_type = 'theme'
            WHERE tenant_id = ?
        ");
        $stmt->execute([$tenantId]);
        
        return ['success' => true, 'message' => '背景画像を削除しました'];
    } catch (Exception $e) {
        error_log("メニュー背景画像削除エラー: " . $e->getMessage());
        return ['success' => false, 'message' => 'エラーが発生しました'];
    }
}

/**
 * メニュー背景CSSを生成
 * 
 * @param array $settings 設定データ
 * @return string CSS文字列
 */
function generateMenuBackgroundCSS($settings)
{
    if (!$settings) {
        return 'background: linear-gradient(135deg, var(--color-primary), var(--color-primary-light));';
    }
    
    $type = $settings['background_type'] ?? 'theme';
    
    switch ($type) {
        case 'solid':
            $color = $settings['background_color'] ?? '#f568df';
            return "background: {$color};";
            
        case 'gradient':
            $start = $settings['gradient_start'] ?? '#f568df';
            $end = $settings['gradient_end'] ?? '#ffa0f8';
            return "background: linear-gradient(135deg, {$start}, {$end});";
            
        case 'image':
            // 画像モードの場合はHTMLで処理するため、ここでは空文字を返す
            return '';
            
        case 'theme':
        default:
            return 'background: linear-gradient(135deg, var(--color-primary), var(--color-primary-light));';
    }
}
