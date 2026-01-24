<?php
/**
 * ランキング更新API
 */

// エラー表示を抑制
error_reporting(0);
ini_set('display_errors', 0);

// JSONヘッダーを設定
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

// ログイン認証チェック
if (!isset($_SESSION['tenant_admin_id'])) {
    echo json_encode(['success' => false, 'message' => '認証エラー'], JSON_UNESCAPED_UNICODE);
    exit;
}

// テナント情報取得
$tenantSlug = $_GET['tenant'] ?? '';
if (empty($tenantSlug)) {
    // POSTデータからテナントを取得
    $input = json_decode(file_get_contents('php://input'), true);
    $tenantSlug = $input['tenant'] ?? '';
}

if (empty($tenantSlug)) {
    echo json_encode(['success' => false, 'message' => 'テナント情報が不足しています'], JSON_UNESCAPED_UNICODE);
    exit;
}

// テナントIDを取得
$pdo = getPlatformDb();
$stmt = $pdo->prepare("SELECT id FROM tenants WHERE code = ? OR slug = ?");
$stmt->execute([$tenantSlug, $tenantSlug]);
$tenantId = $stmt->fetchColumn();

if (!$tenantId) {
    echo json_encode(['success' => false, 'message' => 'テナントが見つかりません'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // POSTデータの取得
    $input = json_decode(file_get_contents('php://input'), true);
    $update_date = $input['update_date'] ?? '';
    $repeat_ranking = $input['repeat_ranking'] ?? [];
    $attention_ranking = $input['attention_ranking'] ?? [];

    // アクティブソースを取得
    $activeSource = 'ekichika';
    $stmt = $pdo->prepare("SELECT config_value FROM tenant_scraping_config WHERE tenant_id = ? AND config_key = 'active_source'");
    $stmt->execute([$tenantId]);
    $result = $stmt->fetchColumn();
    if ($result) {
        $activeSource = $result;
    }

    // ソーステーブル名を決定
    $sourceTableMap = [
        'ekichika' => 'tenant_cast_data_ekichika',
        'heaven' => 'tenant_cast_data_heaven',
        'dto' => 'tenant_cast_data_dto'
    ];
    $castTable = $sourceTableMap[$activeSource] ?? 'tenant_cast_data_ekichika';

    // ========== バリデーション ==========

    // 1. 空欄チェック（リピートランキング）
    for ($i = 0; $i < count($repeat_ranking); $i++) {
        if (empty($repeat_ranking[$i]) || $repeat_ranking[$i] === '') {
            throw new Exception('リピートランキング：' . ($i + 1) . '位のキャストが指定されてません');
        }
    }

    // 2. 空欄チェック（注目度ランキング）
    for ($i = 0; $i < count($attention_ranking); $i++) {
        if (empty($attention_ranking[$i]) || $attention_ranking[$i] === '') {
            throw new Exception('注目度ランキング：' . ($i + 1) . '位のキャストが指定されてません');
        }
    }

    // 3. 重複チェック（リピートランキング）
    $repeat_positions = [];
    foreach ($repeat_ranking as $index => $cast_id) {
        if (!isset($repeat_positions[$cast_id])) {
            $repeat_positions[$cast_id] = [];
        }
        $repeat_positions[$cast_id][] = $index + 1;
    }

    foreach ($repeat_positions as $cast_id => $positions) {
        if (count($positions) > 1) {
            throw new Exception('リピートランキング：' . implode('位と', $positions) . '位に同じキャストが指定されてます');
        }
    }

    // 4. 重複チェック（注目度ランキング）
    $attention_positions = [];
    foreach ($attention_ranking as $index => $cast_id) {
        if (!isset($attention_positions[$cast_id])) {
            $attention_positions[$cast_id] = [];
        }
        $attention_positions[$cast_id][] = $index + 1;
    }

    foreach ($attention_positions as $cast_id => $positions) {
        if (count($positions) > 1) {
            throw new Exception('注目度ランキング：' . implode('位と', $positions) . '位に同じキャストが指定されてます');
        }
    }

    // ========== バリデーション通過 ==========

    // トランザクション開始
    $pdo->beginTransaction();

    try {
        // まず該当テナントの全てのランキングをリセット
        $reset_sql = "UPDATE {$castTable} SET repeat_ranking = NULL, attention_ranking = NULL WHERE tenant_id = ?";
        $reset_stmt = $pdo->prepare($reset_sql);
        $reset_stmt->execute([$tenantId]);

        // リピートランキングの更新
        if (!empty($repeat_ranking)) {
            foreach ($repeat_ranking as $rank => $cast_id) {
                if (!empty($cast_id)) {
                    $sql = "UPDATE {$castTable} SET repeat_ranking = :rank WHERE id = :cast_id AND tenant_id = :tenant_id";
                    $stmt = $pdo->prepare($sql);
                    $rank_num = $rank + 1; // 0-based indexを1-basedに変換
                    $stmt->bindParam(':rank', $rank_num, PDO::PARAM_INT);
                    $stmt->bindParam(':cast_id', $cast_id, PDO::PARAM_INT);
                    $stmt->bindParam(':tenant_id', $tenantId, PDO::PARAM_INT);
                    $stmt->execute();
                }
            }
        }

        // 注目度ランキングの更新
        if (!empty($attention_ranking)) {
            foreach ($attention_ranking as $rank => $cast_id) {
                if (!empty($cast_id)) {
                    $sql = "UPDATE {$castTable} SET attention_ranking = :rank WHERE id = :cast_id AND tenant_id = :tenant_id";
                    $stmt = $pdo->prepare($sql);
                    $rank_num = $rank + 1; // 0-based indexを1-basedに変換
                    $stmt->bindParam(':rank', $rank_num, PDO::PARAM_INT);
                    $stmt->bindParam(':cast_id', $cast_id, PDO::PARAM_INT);
                    $stmt->bindParam(':tenant_id', $tenantId, PDO::PARAM_INT);
                    $stmt->execute();
                }
            }
        }

        // 更新日を保存
        $stmt = $pdo->prepare("SELECT id FROM tenant_ranking_config WHERE tenant_id = ?");
        $stmt->execute([$tenantId]);
        $exists = $stmt->fetchColumn();

        if ($exists) {
            $stmt = $pdo->prepare("UPDATE tenant_ranking_config SET ranking_day = ?, updated_at = NOW() WHERE tenant_id = ?");
            $stmt->execute([$update_date, $tenantId]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO tenant_ranking_config (tenant_id, ranking_day) VALUES (?, ?)");
            $stmt->execute([$tenantId, $update_date]);
        }

        // トランザクションをコミット
        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'ランキングを更新しました'
        ], JSON_UNESCAPED_UNICODE);
        exit;

    } catch (Exception $e) {
        // エラー時はロールバック
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

} catch (Exception $e) {
    error_log('ranking update error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>