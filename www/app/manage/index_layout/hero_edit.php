<?php
/**
 * 上部エリア編集（背景画像/動画）
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
    'video_poster' => '',
    'video_overlay_color' => '#000000',
    'video_overlay_opacity' => 0.4,
    'image_overlay_color' => '#000000',
    'image_overlay_opacity' => 0.5
];

// デフォルト値を設定（既存データ対応）
if (!isset($config['video_overlay_color'])) {
    $config['video_overlay_color'] = '#000000';
}
if (!isset($config['video_overlay_opacity'])) {
    $config['video_overlay_opacity'] = 0.4;
}
if (!isset($config['image_overlay_color'])) {
    $config['image_overlay_color'] = '#000000';
}
if (!isset($config['image_overlay_opacity'])) {
    $config['image_overlay_opacity'] = 0.5;
}

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

        // 動画用オーバーレイ設定
        if (isset($_POST['video_overlay_color'])) {
            $config['video_overlay_color'] = $_POST['video_overlay_color'];
        }
        if (isset($_POST['video_overlay_opacity'])) {
            $config['video_overlay_opacity'] = floatval($_POST['video_overlay_opacity']);
        }

        // 画像用オーバーレイ設定
        if (isset($_POST['image_overlay_color_input'])) {
            $config['image_overlay_color'] = $_POST['image_overlay_color_input'];
        }
        if (isset($_POST['image_overlay_opacity_input'])) {
            $config['image_overlay_opacity'] = floatval($_POST['image_overlay_opacity_input']);
        }

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
    <title><?php echo h($tenant['name']); ?> 上部エリア編集</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/admin.css?v=<?php echo time(); ?>">
    <style>
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .form-container {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
        }

        .form-container h2 {
            margin: 0 0 25px 0;
            font-size: 1.5rem;
            color: #27a3eb;
            display: flex;
            align-items: center;
            gap: 10px;
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

        .buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .buttons .btn {
            flex: 1;
            padding: 14px 28px;
            border-radius: 12px;
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
        <?php
        require_once __DIR__ . '/../includes/breadcrumb.php';
        $breadcrumbs = [
            ['label' => 'ホーム', 'url' => '/app/manage/?tenant=' . $tenantSlug, 'icon' => 'fas fa-home'],
            ['label' => '認証ページ編集', 'url' => '/app/manage/index_layout/?tenant=' . $tenantSlug],
            ['label' => 'ヒーロー編集']
        ];
        renderBreadcrumb($breadcrumbs);
        ?>
        <div class="header">
            <h1>上部エリア編集</h1>
            <p>認証ページの背景画像・動画を設定</p>
        </div>

        <?php if ($message): ?>
            <div class="message success"><?php echo h($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error"><?php echo h($error); ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="form-container">
                <h2>
                    <span class="material-icons">image</span>
                    背景設定
                </h2>

                <div class="form-group">
                    <label>背景タイプ</label>
                    <div class="radio-group">
                        <label
                            class="radio-option <?php echo $config['background_type'] === 'theme' ? 'selected' : ''; ?>">
                            <input type="radio" name="background_type" value="theme" <?php echo $config['background_type'] === 'theme' ? 'checked' : ''; ?>>
                            <span class="material-icons">palette</span>
                            テーマカラー
                        </label>
                        <label
                            class="radio-option <?php echo $config['background_type'] === 'image' ? 'selected' : ''; ?>">
                            <input type="radio" name="background_type" value="image" <?php echo $config['background_type'] === 'image' ? 'checked' : ''; ?>>
                            <span class="material-icons">image</span>
                            背景画像
                        </label>
                        <label
                            class="radio-option <?php echo $config['background_type'] === 'video' ? 'selected' : ''; ?>">
                            <input type="radio" name="background_type" value="video" <?php echo $config['background_type'] === 'video' ? 'checked' : ''; ?>>
                            <span class="material-icons">videocam</span>
                            背景動画
                        </label>
                    </div>
                </div>

                <div id="image-section"
                    style="<?php echo $config['background_type'] !== 'image' ? 'display:none;' : ''; ?> margin-top: 25px; padding-top: 25px; border-top: 1px solid rgba(255,255,255,0.1);">
                    <h3 style="color: #27a3eb; font-size: 1.1rem; margin-bottom: 15px;">背景画像</h3>
                    <div class="form-group">
                        <label>画像をアップロード</label>
                        <div class="banner-upload-area"
                            onclick="document.getElementById('background_image_input').click()">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <div class="banner-upload-text">クリックして画像を選択</div>
                            <div class="banner-upload-subtext">またはドラッグ＆ドロップ (推奨: 1920x1080px以上)</div>
                            <div id="background_image_name"
                                style="margin-top: 10px; color: var(--accent); font-weight: bold;"></div>
                        </div>
                        <input type="file" name="background_image" id="background_image_input" accept="image/*"
                            style="display: none;" onchange="updateFileName(this, 'background_image_name')">
                        <p class="hint">推奨サイズ: 1920x1080px以上、JPG/PNG/WebP形式</p>
                    </div>

                    <div class="preview-box" id="image-preview-box"
                        style="text-align: center; <?php echo empty($config['background_image']) ? 'display:none;' : ''; ?>">
                        <p style="color: rgba(255,255,255,0.7); margin-bottom: 10px;">現在の画像:</p>
                        <img src="<?php echo h($config['background_image'] ?? ''); ?>" alt="背景画像" id="image-preview"
                            style="display: block; margin: 0 auto;">
                        <br>
                        <button type="button" class="delete-btn" onclick="deleteImage()" style="display: inline-block;">
                            <span class="material-icons" style="font-size: 14px; vertical-align: middle;">delete</span>
                            画像を削除
                        </button>
                        <input type="hidden" name="delete_image" id="delete_image" value="0">
                    </div>

                    <!-- オーバーレイ設定（画像用） -->
                    <div class="overlay-settings"
                        style="margin-top: 25px; padding-top: 25px; border-top: 1px solid rgba(255,255,255,0.1);">
                        <h3 style="color: #27a3eb; font-size: 1.1rem; margin-bottom: 15px;">
                            <span class="material-icons"
                                style="vertical-align: middle; margin-right: 5px;">layers</span>
                            オーバーレイ設定
                            <span
                                style="font-size: 0.75rem; color: rgba(255,255,255,0.5); font-weight: normal; margin-left: 10px;">※任意</span>
                        </h3>
                        <p class="hint" style="margin-bottom: 15px;">画像の上に重ねる色と透明度を設定できます。テキストを読みやすくするために使用します。</p>

                        <div class="form-group">
                            <label>オーバーレイカラー</label>
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <input type="color" name="image_overlay_color_input" id="image_overlay_color"
                                    value="<?php echo h($config['image_overlay_color'] ?? '#000000'); ?>"
                                    style="width: 60px; height: 40px; border: none; border-radius: 8px; cursor: pointer; background: transparent;">
                                <input type="text" id="image_overlay_color_text"
                                    value="<?php echo h($config['image_overlay_color'] ?? '#000000'); ?>"
                                    style="width: 100px; padding: 10px; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); border-radius: 8px; color: #fff; font-family: monospace;">
                                <div class="color-presets" style="display: flex; gap: 8px;">
                                    <button type="button" class="color-preset-image" data-color="#000000"
                                        style="width: 30px; height: 30px; background: #000000; border: 2px solid rgba(255,255,255,0.3); border-radius: 5px; cursor: pointer;"
                                        title="黒"></button>
                                    <button type="button" class="color-preset-image" data-color="#1a1a2e"
                                        style="width: 30px; height: 30px; background: #1a1a2e; border: 2px solid rgba(255,255,255,0.3); border-radius: 5px; cursor: pointer;"
                                        title="ダークネイビー"></button>
                                    <button type="button" class="color-preset-image" data-color="#16213e"
                                        style="width: 30px; height: 30px; background: #16213e; border: 2px solid rgba(255,255,255,0.3); border-radius: 5px; cursor: pointer;"
                                        title="ミッドナイト"></button>
                                    <button type="button" class="color-preset-image" data-color="#4a0e4e"
                                        style="width: 30px; height: 30px; background: #4a0e4e; border: 2px solid rgba(255,255,255,0.3); border-radius: 5px; cursor: pointer;"
                                        title="ダークパープル"></button>
                                    <button type="button" class="color-preset-image" data-color="#f568df"
                                        style="width: 30px; height: 30px; background: #f568df; border: 2px solid rgba(255,255,255,0.3); border-radius: 5px; cursor: pointer;"
                                        title="ピンク"></button>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>透明度: <span
                                    id="image-opacity-value"><?php echo round(($config['image_overlay_opacity'] ?? 0.5) * 100); ?>%</span></label>
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <input type="range" name="image_overlay_opacity_input" id="image_overlay_opacity"
                                    min="0" max="1" step="0.05"
                                    value="<?php echo h($config['image_overlay_opacity'] ?? 0.5); ?>"
                                    style="flex: 1; height: 8px; -webkit-appearance: none; background: linear-gradient(to right, transparent, <?php echo h($config['image_overlay_color'] ?? '#000000'); ?>); border-radius: 4px; cursor: pointer;">
                                <span style="color: rgba(255,255,255,0.5); font-size: 0.85rem; min-width: 80px;">0% ～
                                    100%</span>
                            </div>
                            <p class="hint">0%: 完全に透明（オーバーレイなし） / 100%: 完全に不透明（画像が見えない）</p>
                        </div>

                        <div class="preview-overlay" style="margin-top: 20px;">
                            <label>プレビュー</label>
                            <div id="image-overlay-preview"
                                style="position: relative; width: 100%; height: 150px; border-radius: 10px; overflow: hidden; margin-top: 10px;">
                                <div
                                    style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(45deg, #333 25%, #444 25%, #444 50%, #333 50%, #333 75%, #444 75%); background-size: 20px 20px;">
                                </div>
                                <div id="image-overlay-preview-color"
                                    style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-color: <?php echo h($config['image_overlay_color'] ?? '#000000'); ?>; opacity: <?php echo h($config['image_overlay_opacity'] ?? 0.5); ?>;">
                                </div>
                                <div
                                    style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #fff; text-shadow: 0 2px 10px rgba(0,0,0,0.5); font-size: 1.2rem; font-weight: bold; z-index: 10;">
                                    サンプルテキスト</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="video-section"
                    style="<?php echo $config['background_type'] !== 'video' ? 'display:none;' : ''; ?> margin-top: 25px; padding-top: 25px; border-top: 1px solid rgba(255,255,255,0.1);">
                    <h3 style="color: #27a3eb; font-size: 1.1rem; margin-bottom: 15px;">背景動画</h3>
                    <div class="form-group">
                        <label>動画をアップロード</label>
                        <div class="banner-upload-area"
                            onclick="document.getElementById('background_video_input').click()">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <div class="banner-upload-text">クリックして動画を選択</div>
                            <div class="banner-upload-subtext">またはドラッグ＆ドロップ (推奨: 10MB以下)</div>
                            <div id="background_video_name"
                                style="margin-top: 10px; color: var(--accent); font-weight: bold;"></div>
                        </div>
                        <input type="file" name="background_video" id="background_video_input"
                            accept="video/mp4,video/webm,video/quicktime" style="display: none;"
                            onchange="updateFileName(this, 'background_video_name')">
                        <p class="hint">推奨: MP4形式、10MB以下、10秒程度のループ動画</p>
                    </div>

                    <div class="form-group">
                        <label>ポスター画像（動画読み込み中に表示）</label>
                        <div class="banner-upload-area" onclick="document.getElementById('video_poster_input').click()">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <div class="banner-upload-text">クリックして画像を選択</div>
                            <div class="banner-upload-subtext">またはドラッグ＆ドロップ</div>
                            <div id="video_poster_name"
                                style="margin-top: 10px; color: var(--accent); font-weight: bold;"></div>
                        </div>
                        <input type="file" name="video_poster" id="video_poster_input" accept="image/*"
                            style="display: none;" onchange="updateFileName(this, 'video_poster_name')">
                        <p class="hint">※任意：動画の読み込み中に表示されるサムネイル画像</p>
                    </div>

                    <?php if (!empty($config['background_video'])): ?>
                        <div class="preview-box" style="text-align: center;">
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

                    <div class="overlay-settings"
                        style="margin-top: 25px; padding-top: 25px; border-top: 1px solid rgba(255,255,255,0.1);">
                        <h3 style="color: #27a3eb; font-size: 1.1rem; margin-bottom: 15px;">
                            <span class="material-icons"
                                style="vertical-align: middle; margin-right: 5px;">layers</span>
                            オーバーレイ設定
                            <span
                                style="font-size: 0.75rem; color: rgba(255,255,255,0.5); font-weight: normal; margin-left: 10px;">※任意</span>
                        </h3>
                        <p class="hint" style="margin-bottom: 15px;">動画の上に重ねる色と透明度を設定できます。テキストを読みやすくするために使用します。</p>

                        <div class="form-group">
                            <label>オーバーレイカラー</label>
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <input type="color" name="video_overlay_color" id="video_overlay_color"
                                    value="<?php echo h($config['video_overlay_color']); ?>"
                                    style="width: 60px; height: 40px; border: none; border-radius: 8px; cursor: pointer; background: transparent;">
                                <input type="text" id="video_overlay_color_text"
                                    value="<?php echo h($config['video_overlay_color']); ?>"
                                    style="width: 100px; padding: 10px; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); border-radius: 8px; color: #fff; font-family: monospace;">
                                <div class="color-presets" style="display: flex; gap: 8px;">
                                    <button type="button" class="color-preset" data-color="#000000"
                                        style="width: 30px; height: 30px; background: #000000; border: 2px solid rgba(255,255,255,0.3); border-radius: 5px; cursor: pointer;"
                                        title="黒"></button>
                                    <button type="button" class="color-preset" data-color="#1a1a2e"
                                        style="width: 30px; height: 30px; background: #1a1a2e; border: 2px solid rgba(255,255,255,0.3); border-radius: 5px; cursor: pointer;"
                                        title="ダークネイビー"></button>
                                    <button type="button" class="color-preset" data-color="#16213e"
                                        style="width: 30px; height: 30px; background: #16213e; border: 2px solid rgba(255,255,255,0.3); border-radius: 5px; cursor: pointer;"
                                        title="ミッドナイト"></button>
                                    <button type="button" class="color-preset" data-color="#4a0e4e"
                                        style="width: 30px; height: 30px; background: #4a0e4e; border: 2px solid rgba(255,255,255,0.3); border-radius: 5px; cursor: pointer;"
                                        title="ダークパープル"></button>
                                    <button type="button" class="color-preset" data-color="#f568df"
                                        style="width: 30px; height: 30px; background: #f568df; border: 2px solid rgba(255,255,255,0.3); border-radius: 5px; cursor: pointer;"
                                        title="ピンク"></button>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>透明度: <span
                                    id="opacity-value"><?php echo round($config['video_overlay_opacity'] * 100); ?>%</span></label>
                            <div style="display: flex; align-items: center; gap: 15px;">
                                <input type="range" name="video_overlay_opacity" id="video_overlay_opacity" min="0"
                                    max="1" step="0.05" value="<?php echo h($config['video_overlay_opacity']); ?>"
                                    style="flex: 1; height: 8px; -webkit-appearance: none; background: linear-gradient(to right, transparent, <?php echo h($config['video_overlay_color']); ?>); border-radius: 4px; cursor: pointer;">
                                <span style="color: rgba(255,255,255,0.5); font-size: 0.85rem; min-width: 80px;">0% ～
                                    100%</span>
                            </div>
                            <p class="hint">0%: 完全に透明（オーバーレイなし） / 100%: 完全に不透明（動画が見えない）</p>
                        </div>

                        <div class="preview-overlay" style="margin-top: 20px;">
                            <label>プレビュー</label>
                            <div id="overlay-preview"
                                style="position: relative; width: 100%; height: 150px; border-radius: 10px; overflow: hidden; margin-top: 10px;">
                                <div
                                    style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(45deg, #333 25%, #444 25%, #444 50%, #333 50%, #333 75%, #444 75%); background-size: 20px 20px;">
                                </div>
                                <div id="overlay-preview-color"
                                    style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-color: <?php echo h($config['video_overlay_color']); ?>; opacity: <?php echo h($config['video_overlay_opacity']); ?>;">
                                </div>
                                <div
                                    style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #fff; text-shadow: 0 2px 10px rgba(0,0,0,0.5); font-size: 1.2rem; font-weight: bold; z-index: 10;">
                                    サンプルテキスト</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="buttons">
                    <button type="button" class="btn btn-secondary"
                        onclick="window.location.href='index.php?tenant=<?php echo urlencode($tenantSlug); ?>'">
                        <span class="material-icons">arrow_back</span>
                        戻る
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <span class="material-icons">save</span>
                        保存する
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script>
        // ラジオボタンの選択状態を反映
        document.querySelectorAll('.radio-option input').forEach(radio => {
            radio.addEventListener('change', function () {
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

        // オーバーレイ設定のリアルタイムプレビュー
        const colorInput = document.getElementById('video_overlay_color');
        const colorText = document.getElementById('video_overlay_color_text');
        const opacityInput = document.getElementById('video_overlay_opacity');
        const opacityValue = document.getElementById('opacity-value');
        const previewColor = document.getElementById('overlay-preview-color');

        function updateOverlayPreview() {
            const color = colorInput.value;
            const opacity = opacityInput.value;

            previewColor.style.backgroundColor = color;
            previewColor.style.opacity = opacity;
            opacityValue.textContent = Math.round(opacity * 100) + '%';
            colorText.value = color;

            // スライダーの背景グラデーションを更新
            opacityInput.style.background = `linear-gradient(to right, transparent, ${color})`;
        }

        colorInput.addEventListener('input', updateOverlayPreview);
        opacityInput.addEventListener('input', updateOverlayPreview);

        colorText.addEventListener('input', function () {
            if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
                colorInput.value = this.value;
                updateOverlayPreview();
            }
        });

        // カラープリセットボタン
        document.querySelectorAll('.color-preset').forEach(btn => {
            btn.addEventListener('click', function () {
                colorInput.value = this.dataset.color;
                updateOverlayPreview();
            });
        });

        // 画像用オーバーレイのリアルタイムプレビュー
        const imageColorInput = document.getElementById('image_overlay_color');
        const imageColorText = document.getElementById('image_overlay_color_text');
        const imageOpacityInput = document.getElementById('image_overlay_opacity');
        const imageOpacityValue = document.getElementById('image-opacity-value');
        const imagePreviewColor = document.getElementById('image-overlay-preview-color');

        function updateImageOverlayPreview() {
            const color = imageColorInput.value;
            const opacity = imageOpacityInput.value;

            imagePreviewColor.style.backgroundColor = color;
            imagePreviewColor.style.opacity = opacity;
            imageOpacityValue.textContent = Math.round(opacity * 100) + '%';
            imageColorText.value = color;

            // スライダーの背景グラデーションを更新
            imageOpacityInput.style.background = `linear-gradient(to right, transparent, ${color})`;
        }

        imageColorInput.addEventListener('input', updateImageOverlayPreview);
        imageOpacityInput.addEventListener('input', updateImageOverlayPreview);

        imageColorText.addEventListener('input', function () {
            if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
                imageColorInput.value = this.value;
                updateImageOverlayPreview();
            }
        });

        // 画像用カラープリセットボタン
        document.querySelectorAll('.color-preset-image').forEach(btn => {
            btn.addEventListener('click', function () {
                imageColorInput.value = this.dataset.color;
                updateImageOverlayPreview();
            });
        });

        // 画像ファイル選択時の即座プレビュー
        const imageInput = document.getElementById('background_image_input');
        const imagePreview = document.getElementById('image-preview');
        const imagePreviewBox = document.getElementById('image-preview-box');

        if (imageInput) {
            imageInput.addEventListener('change', function (e) {
                const file = e.target.files[0];
                if (file && file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        imagePreview.src = e.target.result;
                        imagePreviewBox.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                }
            });
        }

        // ファイル名表示
        function updateFileName(input, targetId) {
            const target = document.getElementById(targetId);
            if (input.files && input.files.length > 0) {
                target.textContent = input.files[0].name;
                target.style.display = 'block';
            } else {
                target.textContent = '';
            }
        }
    </script>
</body>

</html>