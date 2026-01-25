<?php
require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

$pdo = getPlatformDb();
$tenantId = $tenant['id'];
$castId = $_GET['id'] ?? null;

if (!$castId) {
    header('Location: index.php');
    exit;
}

try {
    $pdo->beginTransaction();

    // 削除対象の存在確認と画像パス取得
    $stmt = $pdo->prepare("SELECT name, img1, img2, img3, img4, img5 FROM tenant_casts WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$castId, $tenantId]);
    $cast = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cast) {
        throw new Exception('キャストが見つかりません');
    }

    // 画像削除
    for ($i = 1; $i <= 5; $i++) {
        $img = $cast["img{$i}"];
        // URLでない場合のみ削除（ローカルファイル）
        if ($img && strpos($img, 'http') === 0 && strpos($img, 'pullcass.com') === false) {
            // 外部URLは削除しない
        } elseif ($img) {
            // パスが /img/... から始まっていると仮定
            // ドキュメントルートからの相対パスに変換
            // NOTE: まだ add/edit での保存パスを決めてないが、/img/cast/%tenant%/... を想定
            $filePath = __DIR__ . '/../../../../public_html' . $img; // パス構造に依存
            // pullcass/www がドキュメントルートなら:
            $filePath = __DIR__ . '/../../../' . ltrim($img, '/');

            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }
    }

    // レコード削除
    $stmt = $pdo->prepare("DELETE FROM tenant_casts WHERE id = ? AND tenant_id = ?");
    $stmt->execute([$castId, $tenantId]);

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
