<?php
/**
 * メニュー背景設定管理
 * hero_edit.phpを参考にした背景カスタマイズUI
 */
require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/includes/background_functions.php';

// ログイン認証
requireTenantAdminLogin();

$pdo = getPlatformDb();

// 現在の設定を取得
$settings = getMenuBackground($pdo, $tenantId);

$message = '';
$error = '';

// POST処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $backgroundType = $_POST['background_type'] ?? 'theme';
        
        // アップロードディレクトリ
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/tenants/' . $tenantSlug . '/menu/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // 背景画像のアップロード
        if (!empty($_FILES['background_image']['name'])) {
            $ext = strtolower(pathinfo($_FILES['background_image']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $filename = 'menu_bg_' . time() . '.' . $ext;
                $filepath = $uploadDir . $filename;
                
                // 古い画像を削除
                if (!empty($settings['background_image'])) {
                    $oldFile = $_SERVER['DOCUMENT_ROOT'] . $settings['background_image'];
                    if (file_exists($oldFile)) {
                        unlink($oldFile);
                    }
                }
                
                if (move_uploaded_file($_FILES['background_image']['tmp_name'], $filepath)) {
                    $settings['background_image'] = '/uploads/tenants/' . $tenantSlug . '/menu/' . $filename;
                }
            }
        }
        
        // 画像削除
        if (isset($_POST['delete_image']) && $_POST['delete_image'] === '1') {
            if (!empty($settings['background_image'])) {
                $oldFile = $_SERVER['DOCUMENT_ROOT'] . $settings['background_image'];
                if (file_exists($oldFile)) {
                    unlink($oldFile);
                }
                $settings['background_image'] = null;
            }
        }
        
        // データを準備
        $data = [
            'background_type' => $backgroundType,
            'background_color' => $_POST['background_color'] ?? null,
            'gradient_start' => $_POST['gradient_start'] ?? null,
            'gradient_end' => $_POST['gradient_end'] ?? null,
            'background_image' => $settings['background_image'] ?? null,
            'overlay_color' => $_POST['overlay_color'] ?? '#000000',
            'overlay_opacity' => isset($_POST['overlay_opacity']) ? floatval($_POST['overlay_opacity']) : 0.5
        ];
        
        // 保存
        $result = saveMenuBackground($pdo, $tenantId, $data);
        
        if ($result['success']) {
            // 設定を再取得
            $settings = getMenuBackground($pdo, $tenantId);
        } else {
            $error = $result['message'];
        }
        
    } catch (Exception $e) {
        $error = 'エラー: ' . $e->getMessage();
    }
}

$pageTitle = 'メニュー背景設定';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($tenant['name']); ?> <?php echo $pageTitle; ?></title>
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

        .color-input-group {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .color-input-group input[type="color"] {
            width: 60px;
            height: 40px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            background: transparent;
        }

        .color-input-group input[type="text"] {
            width: 100px;
            padding: 10px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 8px;
            color: #fff;
            font-family: monospace;
        }

        .color-presets {
            display: flex;
            gap: 8px;
        }

        .color-preset {
            width: 30px;
            height: 30px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 5px;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .color-preset:hover {
            transform: scale(1.1);
        }

        .preview-box {
            margin-top: 15px;
            padding: 15px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 10px;
            text-align: center;
        }

        .preview-box img {
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

        .hint {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.5);
            margin-top: 5px;
        }

        .preview-overlay {
            margin-top: 20px;
        }

        #overlay-preview {
            position: relative;
            width: 100%;
            height: 150px;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 10px;
        }

        .slider-input {
            flex: 1;
            height: 8px;
            -webkit-appearance: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .slider-input::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #27a3eb;
            cursor: pointer;
        }

        .slider-input::-moz-range-thumb {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #27a3eb;
            cursor: pointer;
            border: none;
        }
    </style>
</head>

<body class="admin-body">
    <?php include __DIR__ . '/../includes/header.php'; ?>
    
    <div class="container">
        <?php
        require_once __DIR__ . '/../includes/breadcrumb.php';
        $breadcrumbs = [
            ['label' => 'ダッシュボード', 'url' => '/app/manage/?tenant=' . $tenantSlug, 'icon' => 'fas fa-chart-pie'],
            ['label' => 'メニュー管理', 'url' => '/app/manage/menu_management/?tenant=' . $tenantSlug],
            ['label' => '背景設定']
        ];
        renderBreadcrumb($breadcrumbs);
        ?>
        
        <div class="header">
            <h1>メニュー背景設定</h1>
            <p>ハンバーガーメニューの背景をカスタマイズ</p>
        </div>

        <?php if ($error): ?>
            <div class="message error"><?php echo h($error); ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="buttons" style="margin-top: 0; margin-bottom: 20px;">
                <button type="button" class="btn btn-secondary"
                    onclick="window.location.href='index.php?tenant=<?php echo urlencode($tenantSlug); ?>'">
                    <span class="material-icons">arrow_back</span>
                    戻る
                </button>
                <button type="submit" class="btn btn-primary">
                    <span class="material-icons">save</span>
                    更新
                </button>
            </div>

            <!-- 背景タイプ選択 -->
            <div class="form-container">
                <h2>
                    <span class="material-icons">palette</span>
                    背景タイプ
                </h2>

                <div class="form-group">
                    <div class="radio-group">
                        <label class="radio-option <?php echo ($settings['background_type'] ?? 'theme') === 'theme' ? 'selected' : ''; ?>">
                            <input type="radio" name="background_type" value="theme" <?php echo ($settings['background_type'] ?? 'theme') === 'theme' ? 'checked' : ''; ?>>
                            <span class="material-icons">color_lens</span>
                            テーマカラー（デフォルト）
                        </label>
                        <label class="radio-option <?php echo ($settings['background_type'] ?? 'theme') === 'solid' ? 'selected' : ''; ?>">
                            <input type="radio" name="background_type" value="solid" <?php echo ($settings['background_type'] ?? 'theme') === 'solid' ? 'checked' : ''; ?>>
                            <span class="material-icons">circle</span>
                            単色
                        </label>
                        <label class="radio-option <?php echo ($settings['background_type'] ?? 'theme') === 'gradient' ? 'selected' : ''; ?>">
                            <input type="radio" name="background_type" value="gradient" <?php echo ($settings['background_type'] ?? 'theme') === 'gradient' ? 'checked' : ''; ?>>
                            <span class="material-icons">gradient</span>
                            グラデーション
                        </label>
                        <label class="radio-option <?php echo ($settings['background_type'] ?? 'theme') === 'image' ? 'selected' : ''; ?>">
                            <input type="radio" name="background_type" value="image" <?php echo ($settings['background_type'] ?? 'theme') === 'image' ? 'checked' : ''; ?>>
                            <span class="material-icons">image</span>
                            背景画像
                        </label>
                    </div>
                </div>
            </div>

            <!-- 単色設定 -->
            <div id="solid-section" class="form-container" style="<?php echo ($settings['background_type'] ?? 'theme') !== 'solid' ? 'display:none;' : ''; ?>">
                <h2>
                    <span class="material-icons">circle</span>
                    単色設定
                </h2>
                <div class="form-group">
                    <label>背景色</label>
                    <div class="color-input-group">
                        <input type="color" name="background_color" id="solid_color" value="<?php echo h($settings['background_color'] ?? '#f568df'); ?>">
                        <input type="text" id="solid_color_text" value="<?php echo h($settings['background_color'] ?? '#f568df'); ?>">
                        <div class="color-presets">
                            <button type="button" class="color-preset solid-preset" data-color="#f568df" style="background: #f568df;" title="ピンク"></button>
                            <button type="button" class="color-preset solid-preset" data-color="#ffa0f8" style="background: #ffa0f8;" title="ライトピンク"></button>
                            <button type="button" class="color-preset solid-preset" data-color="#7c4dff" style="background: #7c4dff;" title="パープル"></button>
                            <button type="button" class="color-preset solid-preset" data-color="#00bcd4" style="background: #00bcd4;" title="シアン"></button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- グラデーション設定 -->
            <div id="gradient-section" class="form-container" style="<?php echo ($settings['background_type'] ?? 'theme') !== 'gradient' ? 'display:none;' : ''; ?>">
                <h2>
                    <span class="material-icons">gradient</span>
                    グラデーション設定
                </h2>
                <div class="form-group">
                    <label>開始色</label>
                    <div class="color-input-group">
                        <input type="color" name="gradient_start" id="gradient_start" value="<?php echo h($settings['gradient_start'] ?? '#f568df'); ?>">
                        <input type="text" id="gradient_start_text" value="<?php echo h($settings['gradient_start'] ?? '#f568df'); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>終了色</label>
                    <div class="color-input-group">
                        <input type="color" name="gradient_end" id="gradient_end" value="<?php echo h($settings['gradient_end'] ?? '#ffa0f8'); ?>">
                        <input type="text" id="gradient_end_text" value="<?php echo h($settings['gradient_end'] ?? '#ffa0f8'); ?>">
                    </div>
                </div>
            </div>

            <!-- 画像設定 -->
            <div id="image-section" class="form-container" style="<?php echo ($settings['background_type'] ?? 'theme') !== 'image' ? 'display:none;' : ''; ?>">
                <h2>
                    <span class="material-icons">image</span>
                    背景画像
                </h2>
                <div class="form-group">
                    <label>画像をアップロード</label>
                    <div class="banner-upload-area" onclick="document.getElementById('background_image_input').click()">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <div class="banner-upload-text">クリックして画像を選択</div>
                        <div class="banner-upload-subtext">推奨: 400x800px以上</div>
                        <div id="background_image_name" style="margin-top: 10px; color: var(--accent); font-weight: bold;"></div>
                    </div>
                    <input type="file" name="background_image" id="background_image_input" accept="image/*" style="display: none;" onchange="updateFileName(this, 'background_image_name')">
                    <p class="hint">推奨サイズ: 400x800px以上、JPG/PNG/WebP形式</p>
                </div>

                <div class="preview-box" id="image-preview-box" style="<?php echo empty($settings['background_image']) ? 'display:none;' : ''; ?>">
                    <p style="color: rgba(255,255,255,0.7); margin-bottom: 10px;">現在の画像:</p>
                    <img src="<?php echo h($settings['background_image']); ?>" alt="背景画像" id="image-preview" style="display: block; margin: 0 auto;">
                    <br>
                    <button type="button" class="delete-btn" onclick="deleteImage()" style="display: inline-block;">
                        <span class="material-icons" style="font-size: 14px; vertical-align: middle;">delete</span>
                        画像を削除
                    </button>
                    <input type="hidden" name="delete_image" id="delete_image" value="0">
                </div>

                <!-- オーバーレイ設定 -->
                <div class="overlay-settings" style="margin-top: 25px; padding-top: 25px; border-top: 1px solid rgba(255,255,255,0.1);">
                    <h3 style="color: #27a3eb; font-size: 1.1rem; margin-bottom: 15px;">
                        <span class="material-icons" style="vertical-align: middle; margin-right: 5px;">layers</span>
                        オーバーレイ設定
                        <span style="font-size: 0.75rem; color: rgba(255,255,255,0.5); font-weight: normal; margin-left: 10px;">※任意</span>
                    </h3>
                    <p class="hint" style="margin-bottom: 15px;">画像の上に重ねる色と透明度を設定できます。文字を読みやすくするために使用します。</p>

                    <div class="form-group">
                        <label>オーバーレイカラー</label>
                        <div class="color-input-group">
                            <input type="color" name="overlay_color" id="overlay_color" value="<?php echo h($settings['overlay_color'] ?? '#000000'); ?>">
                            <input type="text" id="overlay_color_text" value="<?php echo h($settings['overlay_color'] ?? '#000000'); ?>">
                            <div class="color-presets">
                                <button type="button" class="color-preset overlay-preset" data-color="#000000" style="background: #000000;" title="黒"></button>
                                <button type="button" class="color-preset overlay-preset" data-color="#1a1a2e" style="background: #1a1a2e;" title="ダークネイビー"></button>
                                <button type="button" class="color-preset overlay-preset" data-color="#4a0e4e" style="background: #4a0e4e;" title="ダークパープル"></button>
                                <button type="button" class="color-preset overlay-preset" data-color="#f568df" style="background: #f568df;" title="ピンク"></button>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>透明度: <span id="opacity-value"><?php echo round(($settings['overlay_opacity'] ?? 0.5) * 100); ?>%</span></label>
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <input type="range" name="overlay_opacity" id="overlay_opacity" min="0" max="1" step="0.05" value="<?php echo h($settings['overlay_opacity'] ?? 0.5); ?>" class="slider-input" style="background: linear-gradient(to right, transparent, <?php echo h($settings['overlay_color'] ?? '#000000'); ?>);">
                            <span style="color: rgba(255,255,255,0.5); font-size: 0.85rem; min-width: 80px;">0% ～ 100%</span>
                        </div>
                        <p class="hint">0%: 完全に透明（オーバーレイなし） / 100%: 完全に不透明（画像が見えない）</p>
                    </div>

                    <div class="preview-overlay">
                        <label>プレビュー</label>
                        <div id="overlay-preview">
                            <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(45deg, #333 25%, #444 25%, #444 50%, #333 50%, #333 75%, #444 75%); background-size: 20px 20px;"></div>
                            <div id="overlay-preview-color" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-color: <?php echo h($settings['overlay_color'] ?? '#000000'); ?>; opacity: <?php echo h($settings['overlay_opacity'] ?? 0.5); ?>;"></div>
                            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: #fff; text-shadow: 0 2px 10px rgba(0,0,0,0.5); font-size: 1.2rem; font-weight: bold; z-index: 10;">サンプルテキスト</div>
                        </div>
                    </div>
                </div>
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
                document.getElementById('solid-section').style.display = this.value === 'solid' ? 'block' : 'none';
                document.getElementById('gradient-section').style.display = this.value === 'gradient' ? 'block' : 'none';
                document.getElementById('image-section').style.display = this.value === 'image' ? 'block' : 'none';
            });
        });

        function deleteImage() {
            if (confirm('画像を削除しますか？')) {
                document.getElementById('delete_image').value = '1';
                document.querySelector('form').submit();
            }
        }

        function updateFileName(input, targetId) {
            const target = document.getElementById(targetId);
            if (input.files && input.files.length > 0) {
                target.textContent = input.files[0].name;
            } else {
                target.textContent = '';
            }
        }

        // オーバーレイ設定のリアルタイムプレビュー
        const colorInput = document.getElementById('overlay_color');
        const colorText = document.getElementById('overlay_color_text');
        const opacityInput = document.getElementById('overlay_opacity');
        const opacityValue = document.getElementById('opacity-value');
        const previewColor = document.getElementById('overlay-preview-color');

        function updateOverlayPreview() {
            const color = colorInput.value;
            const opacity = opacityInput.value;

            previewColor.style.backgroundColor = color;
            previewColor.style.opacity = opacity;
            opacityValue.textContent = Math.round(opacity * 100) + '%';
            colorText.value = color;

            opacityInput.style.background = `linear-gradient(to right, transparent, ${color})`;
        }

        colorInput.addEventListener('input', updateOverlayPreview);
        opacityInput.addEventListener('input', updateOverlayPreview);

        colorText.addEventListener('input', function() {
            if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
                colorInput.value = this.value;
                updateOverlayPreview();
            }
        });

        // カラープリセットボタン（オーバーレイ用）
        document.querySelectorAll('.overlay-preset').forEach(btn => {
            btn.addEventListener('click', function() {
                colorInput.value = this.dataset.color;
                updateOverlayPreview();
            });
        });

        // 単色カラープリセット
        document.querySelectorAll('.solid-preset').forEach(btn => {
            btn.addEventListener('click', function() {
                document.getElementById('solid_color').value = this.dataset.color;
                document.getElementById('solid_color_text').value = this.dataset.color;
            });
        });

        // カラーピッカーとテキスト入力の同期（単色）
        const solidColor = document.getElementById('solid_color');
        const solidColorText = document.getElementById('solid_color_text');

        solidColor.addEventListener('input', function() {
            solidColorText.value = this.value;
        });

        solidColorText.addEventListener('input', function() {
            if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
                solidColor.value = this.value;
            }
        });

        // カラーピッカーとテキスト入力の同期（グラデーション）
        const gradientStart = document.getElementById('gradient_start');
        const gradientStartText = document.getElementById('gradient_start_text');
        const gradientEnd = document.getElementById('gradient_end');
        const gradientEndText = document.getElementById('gradient_end_text');

        gradientStart.addEventListener('input', function() {
            gradientStartText.value = this.value;
        });

        gradientStartText.addEventListener('input', function() {
            if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
                gradientStart.value = this.value;
            }
        });

        gradientEnd.addEventListener('input', function() {
            gradientEndText.value = this.value;
        });

        gradientEndText.addEventListener('input', function() {
            if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
                gradientEnd.value = this.value;
            }
        });

        // 画像ファイル選択時の即座プレビュー
        const imageInput = document.getElementById('background_image_input');
        const imagePreview = document.getElementById('image-preview');
        const imagePreviewBox = document.getElementById('image-preview-box');

        if (imageInput) {
            imageInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file && file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        if (imagePreview) {
                            imagePreview.src = e.target.result;
                        }
                        if (imagePreviewBox) {
                            imagePreviewBox.style.display = 'block';
                        }
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
    </script>
</body>
</html>
