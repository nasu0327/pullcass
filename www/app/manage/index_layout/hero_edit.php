<?php
/**
 * ヒーローセクション編集（背景画像/動画）
 */
require_once __DIR__ . '/../../../includes/bootstrap.php';

// テナント認証
require_once __DIR__ . '/../includes/auth.php';
requireTenantAdminLogin();

$pdo = getPlatformDb();
$sectionId = $_GET['id'] ?? 0;

// セクション情報を取得
$stmt = $pdo->prepare("SELECT * FROM index_layout_sections WHERE id = ? AND tenant_id = ? AND section_key = 'hero'");
$stmt->execute([$sectionId, $tenantId]);
$section = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$section) {
    header('Location: index.php?tenant=' . urlencode($tenantSlug));
    exit;
}

$config = json_decode($section['config'], true) ?: [
    'background_type' => 'theme',
    'background_image' => '',
    'background_video' => '',
    'video_poster' => ''
];

$message = '';
$error = '';

// POST処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $backgroundType = $_POST['background_type'] ?? 'theme';
        
        // アップロードディレクトリ
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/tenants/' . $tenantSlug . '/index/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // 背景画像のアップロード
        if (!empty($_FILES['background_image']['name'])) {
            $ext = strtolower(pathinfo($_FILES['background_image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $filename = 'hero_bg_' . time() . '.' . $ext;
                $filepath = $uploadDir . $filename;
                
                // 古い画像を削除
                if (!empty($config['background_image'])) {
                    $oldFile = $_SERVER['DOCUMENT_ROOT'] . $config['background_image'];
                    if (file_exists($oldFile)) {
                        unlink($oldFile);
                    }
                }
                
                if (move_uploaded_file($_FILES['background_image']['tmp_name'], $filepath)) {
                    $config['background_image'] = '/uploads/tenants/' . $tenantSlug . '/index/' . $filename;
                }
            }
        }
        
        // 背景動画のアップロード
        if (!empty($_FILES['background_video']['name'])) {
            $ext = strtolower(pathinfo($_FILES['background_video']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['mp4', 'webm', 'mov'])) {
                $filename = 'hero_video_' . time() . '.' . $ext;
                $filepath = $uploadDir . $filename;
                
                // 古い動画を削除
                if (!empty($config['background_video'])) {
                    $oldFile = $_SERVER['DOCUMENT_ROOT'] . $config['background_video'];
                    if (file_exists($oldFile)) {
                        unlink($oldFile);
                    }
                }
                
                if (move_uploaded_file($_FILES['background_video']['tmp_name'], $filepath)) {
                    $config['background_video'] = '/uploads/tenants/' . $tenantSlug . '/index/' . $filename;
                }
            }
        }
        
        // 動画ポスター画像のアップロード
        if (!empty($_FILES['video_poster']['name'])) {
            $ext = strtolower(pathinfo($_FILES['video_poster']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $filename = 'hero_poster_' . time() . '.' . $ext;
                $filepath = $uploadDir . $filename;
                
                // 古いポスターを削除
                if (!empty($config['video_poster'])) {
                    $oldFile = $_SERVER['DOCUMENT_ROOT'] . $config['video_poster'];
                    if (file_exists($oldFile)) {
                        unlink($oldFile);
                    }
                }
                
                if (move_uploaded_file($_FILES['video_poster']['tmp_name'], $filepath)) {
                    $config['video_poster'] = '/uploads/tenants/' . $tenantSlug . '/index/' . $filename;
                }
            }
        }
        
        // 画像削除
        if (isset($_POST['delete_image']) && $_POST['delete_image'] === '1') {
            if (!empty($config['background_image'])) {
                $oldFile = $_SERVER['DOCUMENT_ROOT'] . $config['background_image'];
                if (file_exists($oldFile)) {
                    unlink($oldFile);
                }
                $config['background_image'] = '';
            }
        }
        
        // 動画削除
        if (isset($_POST['delete_video']) && $_POST['delete_video'] === '1') {
            if (!empty($config['background_video'])) {
                $oldFile = $_SERVER['DOCUMENT_ROOT'] . $config['background_video'];
                if (file_exists($oldFile)) {
                    unlink($oldFile);
                }
                $config['background_video'] = '';
            }
            if (!empty($config['video_poster'])) {
                $oldFile = $_SERVER['DOCUMENT_ROOT'] . $config['video_poster'];
                if (file_exists($oldFile)) {
                    unlink($oldFile);
                }
                $config['video_poster'] = '';
            }
        }
        
        $config['background_type'] = $backgroundType;
        
        // DB更新
        $stmt = $pdo->prepare("UPDATE index_layout_sections SET config = ? WHERE id = ? AND tenant_id = ?");
        $stmt->execute([json_encode($config), $sectionId, $tenantId]);
        
        $message = '保存しました！';
        
    } catch (Exception $e) {
        $error = 'エラー: ' . $e->getMessage();
    }
}

$tenantSlugJson = json_encode($tenantSlug);
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($tenant['name']); ?> ヒーローセクション編集</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/admin.css?v=<?php echo time(); ?>">
    <style>
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .form-section {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 25px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 30px;
        }
        
        .form-section h2 {
            color: #27a3eb;
            margin-bottom: 20px;
            font-size: 1.3rem;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
        }
        
        .form-group input[type="file"] {
            display: block;
            width: 100%;
            padding: 12px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            color: #fff;
        }
        
        .radio-group {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .radio-option {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 15px 20px;
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .radio-option:hover {
            border-color: rgba(39, 163, 235, 0.4);
        }
        
        .radio-option.selected {
            border-color: #27a3eb;
            background: rgba(39, 163, 235, 0.1);
        }
        
        .radio-option input {
            display: none;
        }
        
        .preview-box {
            margin-top: 15px;
            padding: 15px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 10px;
        }
        
        .preview-box img,
        .preview-box video {
            max-width: 100%;
            max-height: 300px;
            border-radius: 8px;
        }
        
        .delete-btn {
            margin-top: 10px;
            padding: 8px 16px;
            background: rgba(244, 67, 54, 0.2);
            border: 1px solid rgba(244, 67, 54, 0.4);
            color: #f44336;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.85rem;
        }
        
        .delete-btn:hover {
            background: rgba(244, 67, 54, 0.3);
        }
        
        .btn-group {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }
        
        .btn {
            padding: 15px 40px;
            border: none;
            border-radius: 25px;
            font-size: 11px;
            font-weight: 400;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .btn-save {
            background: linear-gradient(45deg, #4CAF50, #45a049);
            color: white;
        }
        
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(76, 175, 80, 0.4);
        }
        
        .btn-back {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .btn-back:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        
        .message {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .message.success {
            background: rgba(76, 175, 80, 0.2);
            color: #4CAF50;
        }
        
        .message.error {
            background: rgba(244, 67, 54, 0.2);
            color: #f44336;
        }
        
        .hint {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.5);
            margin-top: 5px;
        }
    </style>
</head>
<body class="admin-body">
<?php include __DIR__ . '/../includes/header.php'; ?>
    <div class="container">
        <div class="header">
            <h1>ヒーローセクション編集</h1>
            <p>インデックスページの背景画像・動画を設定</p>
        </div>
        
        <?php if ($message): ?>
        <div class="message success"><?php echo h($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
        <div class="message error"><?php echo h($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="form-section">
                <h2>背景タイプ</h2>
                <div class="form-group">
                    <div class="radio-group">
                        <label class="radio-option <?php echo $config['background_type'] === 'theme' ? 'selected' : ''; ?>">
                            <input type="radio" name="background_type" value="theme" <?php echo $config['background_type'] === 'theme' ? 'checked' : ''; ?>>
                            <span class="material-icons">palette</span>
                            テーマカラー
                        </label>
                        <label class="radio-option <?php echo $config['background_type'] === 'image' ? 'selected' : ''; ?>">
                            <input type="radio" name="background_type" value="image" <?php echo $config['background_type'] === 'image' ? 'checked' : ''; ?>>
                            <span class="material-icons">image</span>
                            背景画像
                        </label>
                        <label class="radio-option <?php echo $config['background_type'] === 'video' ? 'selected' : ''; ?>">
                            <input type="radio" name="background_type" value="video" <?php echo $config['background_type'] === 'video' ? 'checked' : ''; ?>>
                            <span class="material-icons">videocam</span>
                            背景動画
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="form-section" id="image-section" style="<?php echo $config['background_type'] !== 'image' ? 'display:none;' : ''; ?>">
                <h2>背景画像</h2>
                <div class="form-group">
                    <label>画像をアップロード</label>
                    <input type="file" name="background_image" accept="image/*">
                    <p class="hint">推奨サイズ: 1920x1080px以上、JPG/PNG/WebP形式</p>
                </div>
                
                <?php if (!empty($config['background_image'])): ?>
                <div class="preview-box">
                    <p style="color: rgba(255,255,255,0.7); margin-bottom: 10px;">現在の画像:</p>
                    <img src="<?php echo h($config['background_image']); ?>" alt="背景画像">
                    <br>
                    <button type="button" class="delete-btn" onclick="deleteImage()">
                        <span class="material-icons" style="font-size: 14px; vertical-align: middle;">delete</span>
                        画像を削除
                    </button>
                    <input type="hidden" name="delete_image" id="delete_image" value="0">
                </div>
                <?php endif; ?>
            </div>
            
            <div class="form-section" id="video-section" style="<?php echo $config['background_type'] !== 'video' ? 'display:none;' : ''; ?>">
                <h2>背景動画</h2>
                <div class="form-group">
                    <label>動画をアップロード</label>
                    <input type="file" name="background_video" accept="video/mp4,video/webm,video/quicktime">
                    <p class="hint">推奨: MP4形式、10MB以下、10秒程度のループ動画</p>
                </div>
                
                <div class="form-group">
                    <label>ポスター画像（動画読み込み中に表示）</label>
                    <input type="file" name="video_poster" accept="image/*">
                </div>
                
                <?php if (!empty($config['background_video'])): ?>
                <div class="preview-box">
                    <p style="color: rgba(255,255,255,0.7); margin-bottom: 10px;">現在の動画:</p>
                    <video src="<?php echo h($config['background_video']); ?>" controls muted loop <?php echo !empty($config['video_poster']) ? 'poster="' . h($config['video_poster']) . '"' : ''; ?>></video>
                    <br>
                    <button type="button" class="delete-btn" onclick="deleteVideo()">
                        <span class="material-icons" style="font-size: 14px; vertical-align: middle;">delete</span>
                        動画を削除
                    </button>
                    <input type="hidden" name="delete_video" id="delete_video" value="0">
                </div>
                <?php endif; ?>
            </div>
            
            <div class="btn-group">
                <a href="index.php?tenant=<?php echo urlencode($tenantSlug); ?>" class="btn btn-back">
                    <span class="material-icons" style="vertical-align: middle; margin-right: 5px;">arrow_back</span>
                    戻る
                </a>
                <button type="submit" class="btn btn-save">
                    <span class="material-icons" style="vertical-align: middle; margin-right: 5px;">save</span>
                    保存する
                </button>
            </div>
        </form>
    </div>
    
    <script>
        // ラジオボタンの選択状態を反映
        document.querySelectorAll('.radio-option input').forEach(radio => {
            radio.addEventListener('change', function() {
                document.querySelectorAll('.radio-option').forEach(opt => opt.classList.remove('selected'));
                this.closest('.radio-option').classList.add('selected');
                
                // セクション表示切り替え
                document.getElementById('image-section').style.display = this.value === 'image' ? 'block' : 'none';
                document.getElementById('video-section').style.display = this.value === 'video' ? 'block' : 'none';
            });
        });
        
        function deleteImage() {
            if (confirm('画像を削除しますか？')) {
                document.getElementById('delete_image').value = '1';
                document.querySelector('form').submit();
            }
        }
        
        function deleteVideo() {
            if (confirm('動画を削除しますか？')) {
                document.getElementById('delete_video').value = '1';
                document.querySelector('form').submit();
            }
        }
    </script>
</body>
</html>
