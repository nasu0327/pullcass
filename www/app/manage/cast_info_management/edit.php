<?php
require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

require_once __DIR__ . '/functions.php';

$pdo = getPlatformDb();
$tenantId = $tenant['id'];
$tenantCode = $tenant['code'];
$castId = $_GET['id'] ?? null;

if (!$castId) {
    header('Location: index.php');
    exit;
}

// アクティブなテーブル名を取得
$tableName = getActiveCastTable($pdo, $tenantId);

// データ取得
$stmt = $pdo->prepare("SELECT * FROM {$tableName} WHERE id = ? AND tenant_id = ?");
$stmt->execute([$castId, $tenantId]);
$cast = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cast) {
    header('Location: index.php?error=' . urlencode('キャストが見つかりません。'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // データ取得とバリデーション
    $name = trim($_POST['name'] ?? '');
    if (empty($name)) {
        $error = 'キャスト名は必須です。';
    } else {
        try {
            $pdo->beginTransaction();

            // 画像アップロードディレクトリ
            $uploadRelPath = "/img/cast/{$tenantCode}/";
            $uploadAbsPath = __DIR__ . '/../../../' . ltrim($uploadRelPath, '/');

            if (!file_exists($uploadAbsPath)) {
                mkdir($uploadAbsPath, 0755, true);
            }

            $images = [];
            for ($i = 1; $i <= 5; $i++) {
                $fileKey = "img{$i}";
                $deleteKey = "delete_img{$i}";
                $currentImg = $cast[$fileKey];
                $finalImg = $currentImg; // デフォルトは維持

                // 削除チェック
                if (isset($_POST[$deleteKey])) {
                    // 削除フラグがあればNULLにする
                    // ローカルファイルなら実ファイルも削除
                    if ($currentImg && strpos($currentImg, 'http') !== 0) {
                        $filePath = __DIR__ . '/../../../' . ltrim($currentImg, '/');
                        if (file_exists($filePath))
                            @unlink($filePath);
                    }
                    $finalImg = null;
                }

                // 新規アップロードチェック（削除フラグがあってもアップロードがあれば上書き）
                if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
                    // 古いローカル画像があれば削除 (削除フラグで既に消えてるかもしれないが念のため)
                    if ($currentImg && strpos($currentImg, 'http') !== 0 && $finalImg !== null) {
                        $filePath = __DIR__ . '/../../../' . ltrim($currentImg, '/');
                        if (file_exists($filePath))
                            @unlink($filePath);
                    }

                    $ext = pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION);
                    $filename = uniqid("cast_{$i}_") . '.' . $ext;
                    if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $uploadAbsPath . $filename)) {
                        $finalImg = $uploadRelPath . $filename;
                    }
                }

                $images[$fileKey] = $finalImg;
            }

            // UPDATE実行
            $sql = "UPDATE {$tableName} SET 
                name = ?, age = ?, height = ?, cup = ?, size = ?, 
                pr_title = ?, pr_text = ?, 
                day1 = ?, time1 = ?, day2 = ?, time2 = ?, day3 = ?, time3 = ?, 
                day4 = ?, time4 = ?, day5 = ?, time5 = ?, day6 = ?, time6 = ?, day7 = ?, time7 = ?,
                img1 = ?, img2 = ?, img3 = ?, img4 = ?, img5 = ?
                WHERE id = ? AND tenant_id = ?";

            $params = [
                $name,
                $_POST['age'] ?? null,
                $_POST['height'] ?? null,
                $_POST['cup'] ?? null,
                $_POST['size'] ?? null,
                $_POST['pr_title'] ?? null,
                $_POST['pr_text'] ?? null,
                $_POST['day_1'] ?? null,
                $_POST['time_1'] ?? null,
                $_POST['day_2'] ?? null,
                $_POST['time_2'] ?? null,
                $_POST['day_3'] ?? null,
                $_POST['time_3'] ?? null,
                $_POST['day_4'] ?? null,
                $_POST['time_4'] ?? null,
                $_POST['day_5'] ?? null,
                $_POST['time_5'] ?? null,
                $_POST['day_6'] ?? null,
                $_POST['time_6'] ?? null,
                $_POST['day_7'] ?? null,
                $_POST['time_7'] ?? null,
                $images['img1'],
                $images['img2'],
                $images['img3'],
                $images['img4'],
                $images['img5'],
                $castId,
                $tenantId
            ];

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $pdo->commit();
            header('Location: index.php?success=' . urlencode("キャスト「{$name}」を更新しました。"));
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'エラーが発生しました: ' . $e->getMessage();
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<style>
    .form-container {
        max-width: 800px;
        margin: 0 auto;
        background: rgba(255, 255, 255, 0.05);
        backdrop-filter: blur(20px);
        padding: 40px;
        border-radius: 20px;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }

    .form-section {
        margin-bottom: 40px;
        padding-bottom: 30px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .form-section:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }

    .form-section h3 {
        color: #fff;
        font-size: 1.2rem;
        margin-bottom: 25px;
        padding-left: 15px;
        border-left: 4px solid var(--primary);
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        color: rgba(255, 255, 255, 0.8);
        font-weight: 500;
        font-size: 0.95rem;
    }

    .form-control {
        width: 100%;
        padding: 12px 15px;
        background: rgba(0, 0, 0, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 8px;
        color: #fff;
        font-size: 1rem;
        transition: all 0.3s ease;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--primary);
        background: rgba(0, 0, 0, 0.3);
        box-shadow: 0 0 0 2px rgba(255, 107, 157, 0.2);
    }

    textarea.form-control {
        min-height: 120px;
        resize: vertical;
    }

    .grid-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
    }

    .schedule-grid {
        display: grid;
        gap: 15px;
    }

    .schedule-day {
        display: grid;
        grid-template-columns: 80px 1fr 1fr;
        gap: 15px;
        align-items: center;
    }

    .image-upload {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 20px;
    }

    .image-item {
        background: rgba(255, 255, 255, 0.05);
        padding: 15px;
        border-radius: 10px;
        text-align: center;
    }

    .image-preview {
        width: 100%;
        height: 150px;
        background: rgba(0, 0, 0, 0.2);
        border-radius: 8px;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .image-preview img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .image-upload-input {
        display: none;
    }

    .image-upload-label {
        display: block;
        padding: 8px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 5px;
        cursor: pointer;
        font-size: 0.9rem;
        transition: all 0.3s;
    }

    .image-upload-label:hover {
        background: rgba(255, 255, 255, 0.2);
    }

    .checkbox-group {
        margin-top: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        color: rgba(255, 255, 255, 0.7);
        font-size: 0.9rem;
    }

    .btn-submit {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: white;
        padding: 15px 40px;
        border: none;
        border-radius: 30px;
        font-size: 1.1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 10px;
    }

    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 30px rgba(255, 107, 157, 0.4);
    }

    .btn-cancel {
        background: rgba(255, 255, 255, 0.1);
        color: white;
        padding: 15px 30px;
        border: none;
        border-radius: 30px;
        font-size: 1rem;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
        margin-right: 15px;
    }

    @media (max-width: 768px) {
        .form-container {
            padding: 20px;
        }

        .schedule-day {
            grid-template-columns: 1fr;
            gap: 5px;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
    }
</style>

<div class="page-header">
    <div>
        <h1><i class="fas fa-edit"></i> キャスト情報編集</h1>
        <p>
            <?php echo h($cast['name']); ?>さんの情報を編集します
        </p>
    </div>
</div>

<div class="container">
    <div class="form-container">
        <?php if (isset($error)): ?>
            <div
                style="background: rgba(244, 67, 54, 0.1); border: 1px solid rgba(244, 67, 54, 0.3); color: #f44336; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo h($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <!-- 基本情報 -->
            <div class="form-section">
                <h3>基本情報</h3>
                <div class="form-group">
                    <label>名前 <span style="color: #f44336">*</span></label>
                    <input type="text" name="name" class="form-control" required
                        value="<?php echo h($cast['name']); ?>">
                </div>

                <div class="grid-row">
                    <div class="form-group">
                        <label>年齢</label>
                        <input type="number" name="age" class="form-control" min="18" max="99"
                            value="<?php echo h($cast['age']); ?>">
                    </div>
                    <div class="form-group">
                        <label>身長 (cm)</label>
                        <input type="number" name="height" class="form-control" min="140" max="200"
                            value="<?php echo h($cast['height']); ?>">
                    </div>
                    <div class="form-group">
                        <label>カップ</label>
                        <select name="cup" class="form-control">
                            <option value="">未選択</option>
                            <?php foreach (range('A', 'P') as $cup): ?>
                                <option value="<?php echo $cup; ?>" <?php echo ($cast['cup'] === $cup) ? 'selected' : ''; ?>>
                                    <?php echo $cup; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>スリーサイズ</label>
                    <input type="text" name="size" class="form-control" value="<?php echo h($cast['size']); ?>"
                        placeholder="例: B88 W58 H88">
                </div>
            </div>

            <!-- PR情報 -->
            <div class="form-section">
                <h3>PR情報</h3>
                <div class="form-group">
                    <label>PRタイトル</label>
                    <input type="text" name="pr_title" class="form-control" value="<?php echo h($cast['pr_title']); ?>">
                </div>
                <div class="form-group">
                    <label>PR本文</label>
                    <textarea name="pr_text" class="form-control"><?php echo h($cast['pr_text']); ?></textarea>
                </div>
            </div>

            <!-- 出勤スケジュール -->
            <div class="form-section">
                <h3>出勤スケジュール</h3>
                <div class="schedule-grid">
                    <?php
                    $days = ['月', '火', '水', '木', '金', '土', '日'];
                    for ($i = 1; $i <= 7; $i++):
                        ?>
                        <div class="schedule-day">
                            <label>
                                <?php echo $days[$i - 1]; ?>曜日
                            </label>
                            <input type="text" name="day_<?php echo $i; ?>" class="form-control"
                                value="<?php echo h($cast["day{$i}"]); ?>" placeholder="日付">
                            <input type="text" name="time_<?php echo $i; ?>" class="form-control"
                                value="<?php echo h($cast["time{$i}"]); ?>" placeholder="時間">
                        </div>
                    <?php endfor; ?>
                </div>
            </div>



            <!-- 画像 -->
            <div class="form-section">
                <h3>画像</h3>
                <div class="image-upload">
                    <?php for ($i = 1; $i <= 5; $i++):
                        $imgSrc = $cast["img{$i}"];
                        ?>
                        <div class="image-item">
                            <div class="image-preview" id="preview_<?php echo $i; ?>">
                                <?php if ($imgSrc): ?>
                                    <img src="<?php echo h($imgSrc); ?>" alt="画像<?php echo $i; ?>">
                                <?php else: ?>
                                    <span style="color: rgba(255,255,255,0.5);">No Image</span>
                                <?php endif; ?>
                            </div>
                            <input type="file" name="img<?php echo $i; ?>" id="img<?php echo $i; ?>"
                                class="image-upload-input" accept="image/*"
                                onchange="previewImage(this, <?php echo $i; ?>)">
                            <label for="img<?php echo $i; ?>" class="image-upload-label">
                                <i class="fas fa-camera"></i> 画像
                                <?php echo $i; ?>を選択（変更）
                            </label>

                            <?php if ($imgSrc): ?>
                                <div class="checkbox-group">
                                    <input type="checkbox" name="delete_img<?php echo $i; ?>" id="delete_img<?php echo $i; ?>"
                                        value="1">
                                    <label for="delete_img<?php echo $i; ?>">画像を削除</label>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>

            <div style="text-align: center; margin-top: 40px;">
                <a href="index.php" class="btn-cancel">キャンセル</a>
                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> 更新する
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function previewImage(input, index) {
        const preview = document.getElementById('preview_' + index);
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function (e) {
                preview.innerHTML = `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover;">`;
            }
            reader.readAsDataURL(input.files[0]);
        }
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>