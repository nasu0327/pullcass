<?php
require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

require_once __DIR__ . '/functions.php';

$pdo = getPlatformDb();
$tenantId = $tenant['id'];
$castId = $_GET['id'] ?? null;

if (!$castId) {
    header('Location: index.php');
    exit;
}

try {
    $pdo->beginTransaction();

    // アクティブなテーブル名を取得
    $tableName = getActiveCastTable($pdo, $tenantId);

    // 削除対象の存在確認と画像パス取得
    $stmt = $pdo->prepare("SELECT name, img1, img2, img3, img4, img5 FROM {$tableName} WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$castId, $tenantId]);
    $cast = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cast) {
        throw new Exception('キャストが見つかりません');
    }

    // 画像削除
    for ($i = 1; $i <= 5; $i++) {
        $img = $cast["img{$i}"];
        // URLでない場合のみ削除（ローカルファイル）
        if ($img && strpos($img, 'http') !== 0) {
            // パスが /img/... から始まっていると仮定
            // ドキュメントルートからの相対パスに変換
            $path = __DIR__ . '/../../../' . ltrim($img, '/');

            if (file_exists($path)) {
                @unlink($path);
            }
        }
    }

    // スクレイピングソーステーブルからレコード削除
    $stmt = $pdo->prepare("DELETE FROM {$tableName} WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$castId, $tenantId]);

    // 表示用テーブル（tenant_casts）からも削除（名前をキーに検索）
    $stmt = $pdo->prepare("DELETE FROM tenant_casts WHERE name = ? AND tenant_id = ?");
    $stmt->execute([$cast['name'], $tenantId]);

    $pdo->commit();
    header('Location: index.php?success=' . urlencode("キャスト「{$cast['name']}」を削除しました。"));
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // エラーハンドリング（簡易）
    echo "エラーが発生しました: " . htmlspecialchars($e->getMessage());
    echo '<br><a href="index.php">戻る</a>';
    exit;
}
