<?php
/**
 * 動画表示トラブルシューティング・修正ツール
 */
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/top_section_renderer.php';

// メッセージ表示用
$message = '';
$messageType = '';

// DB接続取得
try {
    $pdo = getPlatformDb();
    
    // サブドメインからテナント特定（簡易）
    $tenantId = 1; // デフォルト
    $stmt = $pdo->query("SELECT id, name, code FROM tenants LIMIT 1");
    if ($row = $stmt->fetch()) {
        $tenantId = $row['id'];
        $tenantName = $row['name'];
    }

    // POST処理（修正実行）
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['add_section'])) {
            try {
                // 既存チェック
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM top_layout_sections WHERE tenant_id = ? AND section_key = 'videos'");
                $stmt->execute([$tenantId]);
                if ($stmt->fetchColumn() == 0) {
                    $stmt = $pdo->prepare("INSERT INTO top_layout_sections (tenant_id, section_key, section_type, title_ja, title_en, is_visible, display_order) VALUES (?, 'videos', 'videos', '動画', 'VIDEO', 1, 10)");
                    $stmt->execute([$tenantId]);
                }

                $stmt = $pdo->prepare("SELECT COUNT(*) FROM top_layout_sections_published WHERE tenant_id = ? AND section_key = 'videos'");
                $stmt->execute([$tenantId]);
                if ($stmt->fetchColumn() == 0) {
                    $stmt = $pdo->prepare("INSERT INTO top_layout_sections_published (tenant_id, section_key, section_type, title_ja, title_en, is_visible, display_order) VALUES (?, 'videos', 'videos', '動画', 'VIDEO', 1, 10)");
                    $stmt->execute([$tenantId]);
                }
                
                $message = "動画セクションを追加しました！トップページを確認してください。";
                $messageType = "success";
            } catch (PDOException $e) {
                $message = "エラーが発生しました: " . $e->getMessage();
                $messageType = "error";
            }
        }
    }

    // 現状確認
    // 1. トップページセクション
    $hasSectionMaster = false;
    $hasSectionPub = false;
    
    $stmt = $pdo->prepare("SELECT * FROM top_layout_sections WHERE tenant_id = ? AND section_key = 'videos'");
    $stmt->execute([$tenantId]);
    $secMaster = $stmt->fetch();
    if ($secMaster) $hasSectionMaster = true;

    $stmt = $pdo->prepare("SELECT * FROM top_layout_sections_published WHERE tenant_id = ? AND section_key = 'videos'");
    $stmt->execute([$tenantId]);
    $secPub = $stmt->fetch();
    if ($secPub) $hasSectionPub = true;

    // 2. 動画データ
    $stmt = $pdo->prepare("SELECT id, name, movie_1, movie_2 FROM tenant_casts WHERE tenant_id = ? AND (movie_1 IS NOT NULL AND movie_1 != '' OR movie_2 IS NOT NULL AND movie_2 != '')");
    $stmt->execute([$tenantId]);
    $casts = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>Pullcass Video Debugger</title>
    <style>
        body { font-family: sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; line-height: 1.6; }
        h1 { border-bottom: 2px solid #ccc; padding-bottom: 10px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .status-box { background: #f8f9fa; padding: 20px; border-radius: 5px; margin-bottom: 20px; border: 1px solid #ddd; }
        .status-item { margin-bottom: 10px; }
        .ok { color: green; font-weight: bold; }
        .ng { color: red; font-weight: bold; }
        button { background: #007bff; color: white; border: none; padding: 10px 20px; font-size: 16px; border-radius: 5px; cursor: pointer; }
        button:hover { background: #0056b3; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f2f2f2; }
    </style>
</head>
<body>
    <h1>動画表示トラブルシューティング</h1>
    
    <?php if ($message): ?>
        <div class="<?php echo $messageType; ?>"><?php echo h($message); ?></div>
    <?php endif; ?>

    <div class="status-box">
        <h2>1. トップページ設定状況</h2>
        <div class="status-item">
            マスター設定 (top_layout_sections): 
            <?php if ($hasSectionMaster): ?>
                <span class="ok">OK</span> (ID: <?php echo $secMaster['id']; ?>, Visible: <?php echo $secMaster['is_visible']; ?>)
            <?php else: ?>
                <span class="ng">未設定</span>
            <?php endif; ?>
        </div>
        <div class="status-item">
            公開設定 (top_layout_sections_published): 
            <?php if ($hasSectionPub): ?>
                <span class="ok">OK</span> (ID: <?php echo $secPub['id']; ?>, Visible: <?php echo $secPub['is_visible']; ?>)
            <?php else: ?>
                <span class="ng">未設定</span>
            <?php endif; ?>
        </div>

        <?php if (!$hasSectionMaster || !$hasSectionPub): ?>
            <div style="margin-top: 15px;">
                <p>動画セクションの設定が見つかりません。以下のボタンを押すと自動的に追加します。</p>
                <form method="post">
                    <button type="submit" name="add_section">動画セクション設定を追加する</button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <div class="status-box">
        <h2>2. 動画データ登録状況</h2>
        <p>テナント: <?php echo h($tenantName); ?> (ID: <?php echo $tenantId; ?>)</p>
        
        <?php if (empty($casts)): ?>
            <p class="ng">動画が登録されているキャストがいません。</p>
            <p>管理画面からキャスト編集で動画をアップロードしてください。</p>
        <?php else: ?>
            <p class="ok"><?php echo count($casts); ?> 名のキャストに動画が登録されています。</p>
            <table>
                <tr>
                    <th>ID</th>
                    <th>名前</th>
                    <th>動画1</th>
                    <th>動画2</th>
                    <th>確認リンク</th>
                </tr>
                <?php foreach ($casts as $cast): ?>
                <tr>
                    <td><?php echo h($cast['id']); ?></td>
                    <td><?php echo h($cast['name']); ?></td>
                    <td><?php echo $cast['movie_1'] ? 'あり' : '-'; ?></td>
                    <td><?php echo $cast['movie_2'] ? 'あり' : '-'; ?></td>
                    <td><a href="/app/front/cast/detail.php?id=<?php echo h($cast['id']); ?>" target="_blank">詳細ページへ</a></td>
                </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
    
    <div style="text-align: center; margin-top: 20px;">
        <a href="/app/front/top.php" target="_blank">トップページを確認する</a>
    </div>

</body>
</html>
