<?php
/**
 * テーマプレビュー API
 * プレビューモードの開始・終了を管理
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

// bootstrap読み込み
require_once __DIR__ . '/../../../includes/bootstrap.php';

// テナント認証チェック
$tenantSlug = $_GET['tenant'] ?? $_POST['tenant'] ?? '';

if (empty($tenantSlug)) {
    echo json_encode(['success' => false, 'message' => 'テナントが指定されていません']);
    exit;
}

// テナント情報を取得
try {
    $pdo = getPlatformDb();
    $stmt = $pdo->prepare("SELECT * FROM tenants WHERE code = ? AND is_active = 1");
    $stmt->execute([$tenantSlug]);
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tenant) {
        echo json_encode(['success' => false, 'message' => 'テナントが見つかりません']);
        exit;
    }
    
    $tenantId = $tenant['id'];
    
    // セッションにテナント情報を設定（まだない場合）
    if (!isset($_SESSION['current_tenant']) || $_SESSION['current_tenant']['id'] != $tenantId) {
        $_SESSION['current_tenant'] = $tenant;
        $_SESSION['manage_tenant'] = $tenant;
        $_SESSION['manage_tenant_slug'] = $tenantSlug;
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'データベースエラー: ' . $e->getMessage()]);
    exit;
}

$action = $_REQUEST['action'] ?? '';
$sessionKey = 'theme_preview_id_' . $tenantId;

switch ($action) {
    case 'start':
        // プレビュー開始
        $previewId = isset($_REQUEST['preview_id']) ? (int)$_REQUEST['preview_id'] : 0;
        
        if ($previewId <= 0) {
            echo json_encode(['success' => false, 'message' => 'プレビューIDが無効です']);
            exit;
        }
        
        // テーマがこのテナントに属しているか確認
        try {
            $stmt = $pdo->prepare("SELECT id FROM tenant_themes WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$previewId, $tenantId]);
            if (!$stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'テーマが見つかりません']);
                exit;
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'データベースエラー']);
            exit;
        }
        
        // セッションにプレビューIDを保存
        $_SESSION[$sessionKey] = $previewId;
        
        echo json_encode([
            'success' => true, 
            'message' => 'プレビューモードを開始しました',
            'preview_id' => $previewId
        ]);
        break;
        
    case 'stop':
        // プレビュー終了
        unset($_SESSION[$sessionKey]);
        // モーダル表示フラグもクリア
        $modalSessionKey = 'theme_preview_modal_shown_' . $tenantId;
        unset($_SESSION[$modalSessionKey]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'プレビューモードを終了しました'
        ]);
        break;
        
    case 'status':
        // プレビュー状態確認
        $previewId = $_SESSION[$sessionKey] ?? null;
        
        echo json_encode([
            'success' => true,
            'is_preview' => $previewId !== null,
            'preview_id' => $previewId
        ]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => '不正なアクションです']);
        break;
}
