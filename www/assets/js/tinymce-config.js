/**
 * TinyMCE共通設定ファイル
 * プロジェクト全体でTinyMCEエディタの統一設定を管理
 */

/**
 * 基本設定 - シンプルな編集機能
 * 用途: ポップアップ、シンプルなコンテンツ編集
 */
const TinyMCEConfig = {
    basic: {
        height: 300,
        menubar: false,
        language: 'ja',
        branding: false,
        promotion: false,
        license_key: 'gpl',
        plugins: [
            'autolink', 'lists', 'link', 'image', 'code', 'table'
        ],
        toolbar: 'undo redo | blocks | bold italic | forecolor backcolor | ' +
            'alignleft aligncenter alignright | ' +
            'bullist numlist | link image | code',
        content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, sans-serif; font-size: 14px; line-height: 1.6; }',
        automatic_uploads: true,
        file_picker_types: 'image',
        images_reuse_filename: true
    },

    /**
     * 標準設定 - 中程度の編集機能
     * 用途: 一般的なコンテンツ管理
     */
    standard: {
        height: 400,
        menubar: false,
        language: 'ja',
        branding: false,
        promotion: false,
        license_key: 'gpl',
        plugins: [
            'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
            'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
            'insertdatetime', 'media', 'table', 'help', 'wordcount'
        ],
        toolbar: 'undo redo | blocks | bold italic underline | forecolor backcolor | ' +
            'alignleft aligncenter alignright alignjustify | ' +
            'bullist numlist outdent indent | ' +
            'link image media table | searchreplace | code fullscreen | help',
        quickbars_selection_toolbar: 'bold italic | quicklink h2 h3 blockquote',
        contextmenu: 'link image table',
        content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, sans-serif; font-size: 14px; line-height: 1.6; }',
        automatic_uploads: true,
        file_picker_types: 'image',
        images_reuse_filename: true
    },

    /**
     * フル設定 - 完全な編集機能
     * 用途: ブログ記事、詳細なコンテンツ編集
     */
    full: {
        height: 500,
        menubar: 'file edit view insert format tools table help',
        language: 'ja',
        branding: false,
        promotion: false,
        license_key: 'gpl',
        plugins: [
            'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
            'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
            'insertdatetime', 'media', 'table', 'help', 'wordcount', 'emoticons',
            'nonbreaking', 'pagebreak', 'save', 'directionality', 'visualchars',
            'quickbars', 'codesample'
        ],
        toolbar: 'undo redo | blocks fontfamily fontsize | ' +
            'bold italic underline strikethrough | forecolor backcolor | ' +
            'alignleft aligncenter alignright alignjustify | ' +
            'bullist numlist outdent indent | ' +
            'link image media table | ' +
            'anchor charmap emoticons | ' +
            'searchreplace visualblocks code | ' +
            'insertdatetime | fullscreen preview | help',
        font_size_formats: '8px 10px 12px 14px 16px 18px 20px 24px 28px 32px 36px 48px 60px 72px 96px',
        font_family_formats: 'M PLUS 1p=M PLUS 1p,sans-serif; 游ゴシック=Yu Gothic,YuGothic,sans-serif; メイリオ=Meiryo,sans-serif; ヒラギノ角ゴ=Hiragino Sans,Hiragino Kaku Gothic ProN,sans-serif; MS Pゴシック=MS PGothic,sans-serif; Noto Sans JP=Noto Sans JP,sans-serif; 游明朝=Yu Mincho,YuMincho,serif; ヒラギノ明朝=Hiragino Mincho ProN,serif; MS P明朝=MS PMincho,serif; Noto Serif JP=Noto Serif JP,serif; 丸ゴシック=Rounded Mplus 1c,Kosugi Maru,Maru Gothic,sans-serif; M PLUS Rounded 1c=M PLUS Rounded 1c,sans-serif; Arial=arial,helvetica,sans-serif; Georgia=georgia,palatino,serif; Times New Roman=times new roman,times,serif',
        quickbars_selection_toolbar: 'bold italic | quicklink h2 h3 blockquote quickimage quicktable',
        quickbars_insert_toolbar: 'quickimage quicktable',
        quickbars_image_toolbar: 'alignleft aligncenter alignright | rotateleft rotateright | imageoptions',
        image_caption: true,
        image_advtab: true,
        image_class_list: [
            {title: 'なし', value: ''},
            {title: '左揃え', value: 'img-align-left'},
            {title: '中央揃え', value: 'img-align-center'},
            {title: '右揃え', value: 'img-align-right'}
        ],
        contextmenu: 'link image table',
        content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, sans-serif; font-size: 16px; line-height: 1.4; margin: 0; padding: 20px; box-sizing: border-box; } .page-background { padding: 20px; margin: -20px; } p { margin: 0 0 0.5em 0; padding: 0; font-size: 16px; } h1, h2, h3, h4, h5, h6 { margin: 0 0 0.5em 0; padding: 0; font-weight: bold; display: block; } ul, ol { margin: 0 0 0.5em 0; padding: 0 0 0 1.5em; } li { margin: 0; padding: 0; font-size: 16px; } img { max-width: 100%; height: auto; } img.img-align-left { float: left !important; margin: 0 15px 10px 0 !important; } img.img-align-center { display: block !important; margin: 10px auto !important; float: none !important; } img.img-align-right { float: right !important; margin: 0 0 10px 15px !important; }',
        automatic_uploads: true,
        file_picker_types: 'image',
        images_reuse_filename: true,
        relative_urls: false,
        remove_script_host: false,
        convert_urls: false
    }
};

/**
 * TinyMCE初期化ヘルパー関数
 * @param {string} selector - エディタのセレクタ
 * @param {string} configType - 設定タイプ ('basic', 'standard', 'full')
 * @param {Object} customOptions - カスタムオプション（任意）
 */
function initTinyMCE(selector, configType = 'standard', customOptions = {}) {
    if (!TinyMCEConfig[configType]) {
        console.error(`TinyMCE Config: 不正な設定タイプ "${configType}"`);
        return;
    }

    const baseConfig = { ...TinyMCEConfig[configType] };
    const finalConfig = {
        selector: selector,
        ...baseConfig,
        ...customOptions
    };

    return tinymce.init(finalConfig);
}

/**
 * 画像アップロード設定を追加する関数
 * @param {Object} config - TinyMCE設定オブジェクト
 * @param {string} uploadUrl - アップロード先URL
 * @param {string} basePath - ベースパス（任意）
 */
function addImageUploadConfig(config, uploadUrl, basePath = '/') {
    return {
        ...config,
        images_upload_url: uploadUrl,
        images_upload_base_path: basePath,
        images_upload_credentials: false,
        file_picker_callback: function (callback, value, meta) {
            if (meta.filetype === 'image') {
                const input = document.createElement('input');
                input.setAttribute('type', 'file');
                input.setAttribute('accept', 'image/*');
                input.style.display = 'none';
                document.body.appendChild(input);
                input.click();
                
                input.onchange = function () {
                    const file = this.files[0];
                    if (file) {
                        // ファイルサイズチェック（5MB）
                        if (file.size > 5 * 1024 * 1024) {
                            alert('ファイルサイズが大きすぎます（5MB以下）');
                            return;
                        }
                        
                        // ファイルタイプチェック
                        if (!file.type.startsWith('image/')) {
                            alert('画像ファイルを選択してください');
                            return;
                        }
                        
                        // サーバーにアップロード
                        const formData = new FormData();
                        formData.append('file', file);
                        
                        fetch(uploadUrl, {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(result => {
                            if (result.success || result.url) {
                                callback(result.url || result.location, { alt: file.name });
                            } else {
                                console.error('Upload error:', result);
                                alert('画像のアップロードに失敗しました');
                            }
                        })
                        .catch(error => {
                            console.error('Upload error:', error);
                            alert('画像のアップロードに失敗しました');
                        });
                    }
                    document.body.removeChild(input);
                };
            }
        },
        images_upload_handler: function (blobInfo, progress) {
            return new Promise((resolve, reject) => {
                const formData = new FormData();
                formData.append('file', blobInfo.blob(), blobInfo.filename());
                
                fetch(uploadUrl, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success || result.url) {
                        resolve(result.url || result.location);
                    } else {
                        reject(result.error || 'アップロードに失敗しました');
                    }
                })
                .catch(error => {
                    console.error('Upload error:', error);
                    reject('アップロードに失敗しました');
                });
            });
        }
    };
}
