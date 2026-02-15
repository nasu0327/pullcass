<?php
/**
 * 写メ日記スクレイパークラス
 * 参考サイトのCityHeavenScraperを忠実に移植
 */

class DiaryScraper {
    private $tenantId;
    private $settings;
    private $platformPdo;
    private $curl;
    private $cookieFile;
    private $isLoggedIn = false;
    private $logId;
    private $logFile;
    
    // XPath設定（参考サイトから移植）
    private $xpathConfig = [
        'article_nodes' => '//div[contains(@class,"diarylist")]//article | //div[contains(@class,"diary-list")]//article | //article[contains(@class,"diary")] | //div[contains(@class,"diary-item")] | //div[contains(@class,"post")] | //div[contains(@class,"entry")] | //article[not(contains(@class,"menu") or contains(@class,"nav") or contains(@id,"menu"))]',
        'article_container' => '//div[contains(@class,"diarylist") or contains(@class,"diary-list")]//article | //div[contains(@class,"diary")]//div | //ul[contains(@class,"diary")]//li | //div[contains(@class,"post")]',
        'title' => './/h3/a | .//div[2]/div[1]/h3/a | .//h3//a',
        'title_link' => './/h3//a/@href | .//div[2]/div[1]/h3/a/@href',
        'post_time' => './/span[contains(@class,"diary_time")] | .//span[contains(@class,"diary-time")]',
        'writer_name' => './/a[contains(@class,"writer") or contains(@class,"cast")]//text() | .//div[2]/div[1]/div/a/text()',
        'thumbnail' => './/div[1]/div//img/@src | .//div[1]/div//video/@poster',
        'thumbnail_original' => './/img[contains(@class,"thumb") or contains(@class,"image")]/@src | .//div[1]/div/a/img/@src',
        'content_thumbnail' => './/div[1]/div',
        'pd_id_link' => './/a[contains(@href,"pd")]/@href | .//div[2]/div[1]/h3/a/@href',
        'content' => './/div[contains(@class,"content") or contains(@class,"detail")] | .//div[2]/div[2]',
        'videos' => './/div[1]/div//video | .//video | ./descendant::video',
    ];
    
    // 統計
    private $stats = [
        'pages_processed' => 0,
        'posts_found' => 0,
        'posts_saved' => 0,
        'posts_skipped' => 0,
        'errors_count' => 0,
    ];
    
    public function __construct($tenantId, $settings, $platformPdo, $logId = null) {
        $this->tenantId = $tenantId;
        $this->settings = $settings;
        $this->platformPdo = $platformPdo;
        $this->logId = $logId;
        
        // Cookie保存先
        $this->cookieFile = sys_get_temp_dir() . "/cityheaven_cookies_{$tenantId}.txt";
        
        // ログファイル
        $logDir = dirname(dirname(__DIR__)) . '/../../logs/diary_scrape/';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        $this->logFile = $logDir . "tenant_{$tenantId}_" . date('Ymd') . '.log';
        
        $this->initCurl();
    }
    
    /**
     * cURL初期化
     */
    private function initCurl() {
        $this->curl = curl_init();
        curl_setopt_array($this->curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $this->settings['timeout'] ?? 30,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_ENCODING => '',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language: ja,en-US;q=0.9,en;q=0.8',
                'Accept-Encoding: gzip, deflate, br',
                'Connection: keep-alive',
                'Upgrade-Insecure-Requests: 1',
            ],
        ]);
    }
    
    /**
     * 実行
     */
    public function execute() {
        try {
            $this->log('=== 写メ日記スクレイピング開始 ===');
            $this->log("テナントID: {$this->tenantId}");
            $this->log("店舗URL: {$this->settings['shop_url']}");
            
            // ログイン
            if (!$this->login()) {
                throw new Exception('ログインに失敗しました');
            }
            
            // スクレイピング実行
            $this->scrape();
            
            // 古いデータ削除
            $this->cleanupOldPosts();
            
            $this->log('=== スクレイピング完了 ===');
            $this->log("取得件数: {$this->stats['posts_saved']}件");
            
            return array_merge($this->stats, ['status' => 'success']);
            
        } catch (Exception $e) {
            $this->log('エラー: ' . $e->getMessage());
            $this->stats['errors_count']++;
            
            return array_merge($this->stats, [
                'status' => 'error',
                'error_message' => $e->getMessage()
            ]);
            
        } finally {
            if ($this->curl) {
                curl_close($this->curl);
            }
        }
    }
    
    /**
     * ログイン（参考サイトの真のAjaxログインを忠実に移植）
     */
    private function login() {
        $this->log('=== ログイン処理開始 ===');
        
        try {
            // URLからパス情報を抽出
            $shopUrl = $this->settings['shop_url'];
            $parsedUrl = parse_url($shopUrl);
            $baseUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
            $path = $parsedUrl['path'] ?? '';
            
            // /diarylist/を除いたパスからloginajax URLを構築
            // 例: /fukuoka/A4001/A400101/houmantengoku/diarylist/ → /fukuoka/A4001/A400101/loginajax/
            $diaryListPath = $path;
            $pathParts = explode('/', trim($path, '/'));
            // 最後の2要素（店舗名/diarylist）を除去し、loginajax/を追加
            if (count($pathParts) >= 4) {
                $regionPath = implode('/', array_slice($pathParts, 0, 3));
                $ajaxLoginUrl = $baseUrl . '/' . $regionPath . '/loginajax/';
            } else {
                $ajaxLoginUrl = $baseUrl . '/loginajax/';
            }
            
            $this->log("ベースURL: {$baseUrl}");
            $this->log("日記ページパス: {$diaryListPath}");
            $this->log("AjaxログインURL: {$ajaxLoginUrl}");
            
            // Step 1: 日記リストページでセッション確立
            $this->log('Step 1: 日記リストページでセッション確立');
            curl_setopt_array($this->curl, [
                CURLOPT_URL => $shopUrl,
                CURLOPT_POST => false,
                CURLOPT_HTTPHEADER => [
                    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language: ja,en-US;q=0.9,en;q=0.8',
                    'Accept-Encoding: gzip, deflate, br',
                    'Connection: keep-alive',
                    'Upgrade-Insecure-Requests: 1',
                ],
            ]);
            
            $sessionPage = curl_exec($this->curl);
            if (curl_errno($this->curl)) {
                throw new Exception('セッション確立失敗: ' . curl_error($this->curl));
            }
            
            $httpCode = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
            $this->log("セッション確立: HTTPコード={$httpCode}, サイズ=" . strlen($sessionPage) . " bytes");
            
            sleep(1);
            
            // Step 2: 真のAjaxログイン実行（参考サイトと同じパラメータ名）
            $this->log('Step 2: Ajaxログイン実行');
            
            $loginData = [
                'user' => $this->settings['cityheaven_login_id'],
                'pass' => $this->settings['cityheaven_password'],
            ];
            
            $this->log("ログインデータ: user={$loginData['user']}, pass=****");
            
            curl_setopt_array($this->curl, [
                CURLOPT_URL => $ajaxLoginUrl,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($loginData),
                CURLOPT_REFERER => $shopUrl,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With: XMLHttpRequest',
                    'Accept: application/json, text/javascript, */*; q=0.01',
                    'Accept-Language: ja,en-US;q=0.9,en;q=0.8',
                    'Accept-Encoding: gzip, deflate, br',
                    'Origin: ' . $baseUrl,
                    'Referer: ' . $shopUrl,
                    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Sec-Fetch-Dest: empty',
                    'Sec-Fetch-Mode: cors',
                    'Sec-Fetch-Site: same-origin',
                ],
            ]);
            
            $loginResult = curl_exec($this->curl);
            
            if (curl_errno($this->curl)) {
                throw new Exception('Ajaxログインエラー: ' . curl_error($this->curl));
            }
            
            $httpCode = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
            $this->log("ログインレスポンス: HTTPコード={$httpCode}, サイズ=" . strlen($loginResult) . " bytes");
            $this->log("レスポンス内容: " . substr($loginResult, 0, 300));
            
            if ($httpCode === 200) {
                $jsonData = json_decode($loginResult, true);
                
                if ($jsonData !== null) {
                    $this->log("JSONレスポンス: " . json_encode($jsonData, JSON_UNESCAPED_UNICODE));
                    
                    if (isset($jsonData['isLogin']) && $jsonData['isLogin'] === true) {
                        $this->log('✅ ログイン成功！');
                        
                        if (isset($jsonData['nickname'])) {
                            $this->log("ニックネーム: " . $jsonData['nickname']);
                        }
                        
                        // Step 3: Cookie同期
                        $this->log('Step 3: Cookie同期');
                        sleep(2);
                        
                        curl_setopt_array($this->curl, [
                            CURLOPT_URL => $shopUrl,
                            CURLOPT_POST => false,
                            CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_MAXREDIRS => 10,
                            CURLOPT_REFERER => $ajaxLoginUrl,
                            CURLOPT_HTTPHEADER => [
                                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                                'Accept-Language: ja,en-US;q=0.9,en;q=0.8',
                                'Cache-Control: no-cache',
                                'Pragma: no-cache',
                            ]
                        ]);
                        
                        $syncResponse = curl_exec($this->curl);
                        $syncHttpCode = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
                        $this->log("Cookie同期: HTTPコード={$syncHttpCode}, サイズ=" . strlen($syncResponse) . " bytes");
                        
                        sleep(2);
                        
                        $this->isLoggedIn = true;
                        return true;
                    } else {
                        $this->log("❌ ログイン失敗: isLogin = " . var_export($jsonData['isLogin'] ?? 'undefined', true));
                        if (isset($jsonData['error'])) {
                            $this->log("エラー詳細: " . $jsonData['error']);
                        }
                    }
                } else {
                    $this->log("❌ レスポンスが有効なJSONではありません");
                    $this->log("レスポンス内容: " . substr($loginResult, 0, 500));
                }
            } else {
                $this->log("❌ HTTPエラー: {$httpCode}");
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->log('ログインエラー: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * pd_idが既にDBに存在するかチェック（上積み式の核心ロジック）
     */
    private function existsPdId($pdId) {
        try {
            $stmt = $this->platformPdo->prepare("
                SELECT 1 FROM diary_posts 
                WHERE tenant_id = ? AND pd_id = ? LIMIT 1
            ");
            $stmt->execute([$this->tenantId, $pdId]);
            return (bool)$stmt->fetch();
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * スクレイピング（上積み式：既存投稿に到達したら早期終了）
     * 参考サイトのlogin_and_scrape.phpに準拠
     */
    private function scrape() {
        $this->log('=== スクレイピング開始（上積み式） ===');
        
        $page = 1;
        $maxPages = $this->settings['max_pages'] ?? 50;
        $shopUrl = $this->settings['shop_url'];
        $shouldStop = false;
        $consecutiveDuplicates = 0;
        $duplicateThreshold = 3; // 連続3件の既存投稿で停止
        
        while ($page <= $maxPages && !$shouldStop) {
            $this->log("--- ページ {$page} 処理中 ---");
            
            // ページURL（参考サイトと同じパス形式）
            $pageUrl = rtrim($shopUrl, '/') . '/';
            if ($page > 1) {
                $pageUrl .= $page . '/';
            }
            
            // ページ取得
            curl_setopt_array($this->curl, [
                CURLOPT_URL => $pageUrl,
                CURLOPT_POST => false,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_HTTPHEADER => [
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language: ja,en-US;q=0.7,en;q=0.3',
                    'Accept-Encoding: gzip, deflate',
                    'Cache-Control: no-cache',
                ],
            ]);
            
            $html = curl_exec($this->curl);
            
            if (curl_errno($this->curl)) {
                $this->log('ページ取得エラー: ' . curl_error($this->curl));
                $this->stats['errors_count']++;
                break;
            }
            
            $httpCode = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
            $this->log("ページ取得完了: HTTPコード={$httpCode}, サイズ=" . strlen($html) . " bytes");
            
            if ($httpCode !== 200 || empty($html)) {
                $this->log('ページ取得失敗。終了します。');
                break;
            }
            
            $this->stats['pages_processed']++;
            
            // ページ処理開始時に進捗を更新
            $this->updateProgress();
            
            // HTML解析
            $posts = $this->parseHtml($html);
            
            if (empty($posts)) {
                $this->log('投稿が見つかりませんでした。終了します。');
                break;
            }
            
            $this->log(count($posts) . '件の投稿を発見');
            $this->stats['posts_found'] += count($posts);
            
            // 投稿を処理（上積み式：既存投稿に到達したら停止）
            foreach ($posts as $post) {
                // 既存チェック（メディアダウンロード前に判定して効率化）
                if ($this->existsPdId($post['pd_id'])) {
                    $consecutiveDuplicates++;
                    $this->stats['posts_skipped']++;
                    $this->log("既存投稿スキップ: pd_id={$post['pd_id']} ({$post['cast_name']}) [連続{$consecutiveDuplicates}件]");
                    
                    // スキップ時も進捗を更新
                    $this->updateProgress();
                    
                    if ($consecutiveDuplicates >= $duplicateThreshold) {
                        $this->log("連続{$duplicateThreshold}件の既存投稿を検出 → 取得済み領域に到達。停止します。");
                        $shouldStop = true;
                        break;
                    }
                    continue;
                }
                
                // 新規投稿 → 連続重複カウンタをリセット
                $consecutiveDuplicates = 0;
                
                // 弱本文の場合のみ詳細ページにフォールバック
                if ($this->isWeakHtmlBody($post['html_body']) && !empty($post['detail_url'])) {
                    $this->log("弱本文検出（pd_id={$post['pd_id']}）→ 詳細ページから本文取得");
                    $detailHtml = $this->fetchDiaryDetailHtml($post['detail_url']);
                    if (!empty($detailHtml)) {
                        $post['html_body'] = $this->cleanHtmlBody($detailHtml);
                        $this->log("詳細本文取得成功: " . mb_strlen($post['html_body']) . " 文字");
                    }
                    $delay = $this->settings['request_delay'] ?? 0.5;
                    usleep($delay * 1000000);
                }
                
                // 画像・動画をローカルにダウンロード（新規投稿のみ実行）
                $post = $this->downloadPostMedia($post);
                
                if ($this->savePost($post)) {
                    $this->stats['posts_saved']++;
                } else {
                    $this->stats['posts_skipped']++;
                }
                
                // 進捗をDBに保存
                $this->updateProgress();
            }
            
            // 次のページへ
            $page++;
            
            // 遅延
            $delay = $this->settings['request_delay'] ?? 0.5;
            usleep($delay * 1000000);
        }
        
        if ($shouldStop) {
            $this->log("=== スクレイピング完了（差分取得: 既存データに到達） ===");
        } else {
            $this->log("=== スクレイピング完了（全ページ処理済み） ===");
        }
    }
    
    /**
     * 進捗をDBに更新
     */
    private function updateProgress() {
        if (!$this->logId) return;
        
        try {
            $stmt = $this->platformPdo->prepare("
                UPDATE diary_scrape_logs SET
                    pages_processed = ?,
                    posts_found = ?,
                    posts_saved = ?,
                    posts_skipped = ?,
                    errors_count = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $this->stats['pages_processed'],
                $this->stats['posts_found'],
                $this->stats['posts_saved'],
                $this->stats['posts_skipped'],
                $this->stats['errors_count'],
                $this->logId
            ]);
        } catch (Exception $e) {
            // 進捗更新失敗は無視
        }
    }
    
    /**
     * HTML解析（参考サイトのXPath設定を使用）
     */
    private function parseHtml($html) {
        $posts = [];
        
        try {
            $doc = new DOMDocument();
            @$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
            $xpath = new DOMXPath($doc);
            
            // 参考サイトのXPathで記事ノード取得
            $articles = $xpath->query($this->xpathConfig['article_nodes']);
            $this->log("メインXPath結果: {$articles->length}件");
            
            if ($articles->length === 0) {
                // 代替XPath
                $altXpaths = [
                    $this->xpathConfig['article_container'],
                    '//div[contains(@class,"diary")]',
                    '//div[contains(@class,"post")]',
                    '//*[contains(@href,"diarydetail")]/..',
                ];
                
                foreach ($altXpaths as $altXpath) {
                    if (empty($altXpath)) continue;
                    $altArticles = $xpath->query($altXpath);
                    $this->log("代替XPath試行: {$altXpath} → {$altArticles->length}件");
                    if ($altArticles->length > 0 && $altArticles->length < 100) {
                        $articles = $altArticles;
                        break;
                    }
                }
            }
            
            $this->log("解析対象記事数: {$articles->length}件");
            
            foreach ($articles as $index => $article) {
                try {
                    $post = $this->parseArticle($xpath, $article);
                    if ($post) {
                        $posts[] = $post;
                    }
                } catch (Exception $e) {
                    $this->log("記事{$index}解析エラー: " . $e->getMessage());
                    $this->stats['errors_count']++;
                }
            }
            
        } catch (Exception $e) {
            $this->log('HTML解析エラー: ' . $e->getMessage());
            $this->stats['errors_count']++;
        }
        
        return $posts;
    }
    
    /**
     * 記事解析（参考サイトのparseArticleDataを移植）
     */
    private function parseArticle($xpath, $article) {
        // タイトルリンク取得
        $titleLinkNodes = $xpath->query($this->xpathConfig['title_link'], $article);
        $titleLink = $titleLinkNodes->length > 0 ? trim($titleLinkNodes->item(0)->value ?? $titleLinkNodes->item(0)->nodeValue) : '';
        
        // タイトル取得
        $titleNodes = $xpath->query($this->xpathConfig['title'], $article);
        $title = $titleNodes->length > 0 ? trim($titleNodes->item(0)->nodeValue) : '';
        
        // 投稿者名取得
        $writerNodes = $xpath->query($this->xpathConfig['writer_name'], $article);
        $writerName = $writerNodes->length > 0 ? trim($writerNodes->item(0)->nodeValue) : '';
        
        // 投稿時刻取得（<span class="diary_time">2/15 11:31</span>）
        $timeStr = '';
        $timeNodes = $xpath->query($this->xpathConfig['post_time'], $article);
        if ($timeNodes->length > 0) {
            $timeStr = trim($timeNodes->item(0)->nodeValue);
        }
        
        $postedAt = $this->parsePostTime($timeStr);
        if (empty($postedAt)) {
            $postedAt = date('Y-m-d H:i:s');
        }
        
        // pd_id取得
        $pdIdNodes = $xpath->query($this->xpathConfig['pd_id_link'], $article);
        $pdId = null;
        $detailUrl = '';
        if ($pdIdNodes->length > 0) {
            $href = $pdIdNodes->item(0)->value ?? $pdIdNodes->item(0)->nodeValue;
            if (preg_match('/pd[-\/](\d+)/', $href, $matches)) {
                $pdId = (int)$matches[1];
            }
            $detailUrl = (strpos($href, 'http') === 0) ? $href : ('https://www.cityheaven.net' . $href);
        }
        
        // フォールバック: タイトルリンクからpd_id取得
        if (!$pdId && !empty($titleLink)) {
            if (preg_match('/pd[-\/](\d+)/', $titleLink, $matches)) {
                $pdId = (int)$matches[1];
            }
            if (empty($detailUrl)) {
                $detailUrl = (strpos($titleLink, 'http') === 0) ? $titleLink : ('https://www.cityheaven.net' . $titleLink);
            }
        }
        
        // pd_idが取得できない場合はスキップ
        if (!$pdId) {
            return null;
        }
        
        // 投稿者名が空の場合もスキップ
        if (empty($writerName)) {
            return null;
        }
        
        // マイガール限定投稿判定（サムネイル取得前に判定）
        $isMyGirlLimited = (!empty($detailUrl) && strpos($detailUrl, '?lo=1') !== false) ? 1 : 0;
        
        // サムネイル取得（参考サイトの強化版ロジック移植）
        $thumbUrl = '';
        
        // 1. メインXPathでサムネイル検索
        $thumbNodes = $xpath->query($this->xpathConfig['thumbnail'], $article);
        if ($thumbNodes->length > 0) {
            $thumbUrl = $thumbNodes->item(0)->value ?? $thumbNodes->item(0)->nodeValue;
        }
        
        // 2. フォールバック: content_thumbnail エリア内で詳細検索（マイガール限定対応強化）
        if (empty($thumbUrl)) {
            $thumbnailAreaNodes = $xpath->query($this->xpathConfig['content_thumbnail'], $article);
            if ($thumbnailAreaNodes->length > 0) {
                $thumbnailArea = $thumbnailAreaNodes->item(0);
                
                // サムネイルエリア内の画像を優先順位で検索
                $imgSearchPaths = [
                    './/a/img/@src',
                    './/img[not(contains(@class,"deco")) and not(contains(@src,"logo")) and not(contains(@src,"banner"))]/@src',
                    './/video/@poster',
                    './/div/img/@src',
                    './/img/@src',
                ];
                
                foreach ($imgSearchPaths as $imgPath) {
                    $imgNodes = $xpath->query($imgPath, $thumbnailArea);
                    if ($imgNodes->length > 0) {
                        $thumbUrl = $imgNodes->item(0)->value;
                        break;
                    }
                }
            }
        }
        
        // 3. フォールバック: 従来XPath
        if (empty($thumbUrl)) {
            $oldThumbNodes = $xpath->query($this->xpathConfig['thumbnail_original'], $article);
            if ($oldThumbNodes->length > 0) {
                $thumbUrl = $oldThumbNodes->item(0)->value ?? $oldThumbNodes->item(0)->nodeValue;
            }
        }
        
        // 動画チェック（参考サイトの改良版）
        $hasVideo = 0;
        $videoUrl = '';
        $posterUrl = '';
        $videoUrls = [];
        $videoNodes = $xpath->query($this->xpathConfig['videos'], $article);
        
        if ($videoNodes->length > 0) {
            $hasVideo = 1;
            
            for ($i = 0; $i < $videoNodes->length; $i++) {
                $video = $videoNodes->item($i);
                $srcNodes = $xpath->query('./@src', $video);
                if ($srcNodes->length > 0) {
                    $videoUrls[] = $srcNodes->item(0)->value;
                }
                if (empty($thumbUrl)) {
                    $posterNodes = $xpath->query('./@poster', $video);
                    if ($posterNodes->length > 0) {
                        $thumbUrl = $posterNodes->item(0)->value;
                    }
                }
            }
            
            // 最初の動画URLを使用
            if (!empty($videoUrls)) {
                $videoUrl = $videoUrls[0];
            }
            
            // ポスター画像取得
            $posterUrl = $thumbUrl;
        } else {
            // サムネイルエリア内の動画も確認
            $thumbnailAreaNodes = $xpath->query($this->xpathConfig['content_thumbnail'], $article);
            if ($thumbnailAreaNodes->length > 0) {
                $thumbnailArea = $thumbnailAreaNodes->item(0);
                $videoInThumbNodes = $xpath->query('.//video', $thumbnailArea);
                
                if ($videoInThumbNodes->length > 0) {
                    $hasVideo = 1;
                    for ($i = 0; $i < $videoInThumbNodes->length; $i++) {
                        $video = $videoInThumbNodes->item($i);
                        $srcNodes = $xpath->query('./@src', $video);
                        if ($srcNodes->length > 0) {
                            $videoUrls[] = $srcNodes->item(0)->value;
                        }
                        if (empty($thumbUrl)) {
                            $posterNodes = $xpath->query('./@poster', $video);
                            if ($posterNodes->length > 0) {
                                $thumbUrl = $posterNodes->item(0)->value;
                            }
                        }
                    }
                    if (!empty($videoUrls)) {
                        $videoUrl = $videoUrls[0];
                    }
                    $posterUrl = $thumbUrl;
                }
            }
        }
        
        // マイガール限定コンテンツ対応: クラス指定で画像・動画を取得
        // diary_movieframe から動画を取得
        $movieFrameNodes = $xpath->query('.//div[contains(@class,"diary_movieframe")]//video', $article);
        if ($movieFrameNodes->length > 0) {
            $hasVideo = 1;
            $mfVideo = $movieFrameNodes->item(0);
            $mfSrc = $xpath->query('./@src', $mfVideo);
            if ($mfSrc->length > 0) {
                $mfVideoUrl = $mfSrc->item(0)->value;
                if (empty($videoUrl)) {
                    $videoUrl = $mfVideoUrl;
                }
                if (!in_array($mfVideoUrl, $videoUrls)) {
                    $videoUrls[] = $mfVideoUrl;
                }
            }
            $mfPoster = $xpath->query('./@poster', $mfVideo);
            if ($mfPoster->length > 0) {
                $posterUrl = $mfPoster->item(0)->value;
                $thumbUrl = $posterUrl; // 動画のポスター画像をサムネイルに
            }
        }
        
        // diary_img_contents / diary_photoframe から画像を取得
        $imgContentsNodes = $xpath->query(
            './/div[contains(@class,"diary_img_contents")]//img/@src | .//div[contains(@class,"diary_photoframe")]//img/@src',
            $article
        );
        if ($imgContentsNodes->length > 0) {
            $fullImgUrl = $imgContentsNodes->item(0)->value;
            // デコメ画像でなければサムネイルとして使用（完全なURLを取得）
            if (strpos($fullImgUrl, 'deco') === false && strpos($fullImgUrl, 'girls-deco-image') === false) {
                $thumbUrl = $fullImgUrl;
            }
        }
        
        // 本文取得（diary_detailクラスを優先 → ログイン状態ではテキスト本文が含まれる）
        $htmlBody = '';
        
        // 1. diary_detail クラスから本文を取得（最優先）
        $detailNodes = $xpath->query('.//div[contains(@class,"diary_detail")]', $article);
        if ($detailNodes->length > 0) {
            $htmlBody = $this->getInnerHTML($detailNodes->item(0));
        }
        
        // 2. フォールバック: 既存XPath（diary_detailがない場合）
        if (empty($htmlBody)) {
            $contentNodes = $xpath->query($this->xpathConfig['content'], $article);
            if ($contentNodes->length > 0) {
                $htmlBody = $this->getInnerHTML($contentNodes->item(0));
            }
        }
        
        // 本文クリーンアップ（CityHeaven固有の広告・リクルートリンク除去）
        if (!empty($htmlBody)) {
            $htmlBody = $this->cleanHtmlBody($htmlBody);
        }
        
        // thumb_urlがまだ短縮URL（拡張子なし）の場合、html_bodyからフルURLを取得
        $hasValidThumb = !empty($thumbUrl) && (
            strpos($thumbUrl, '.jpg') !== false || 
            strpos($thumbUrl, '.png') !== false || 
            strpos($thumbUrl, '.gif') !== false || 
            strpos($thumbUrl, '.webp') !== false
        );
        if (!$hasValidThumb && !empty($htmlBody)) {
            if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $htmlBody, $imgMatches)) {
                foreach ($imgMatches[1] as $imgSrc) {
                    if (strpos($imgSrc, 'deco') === false && strpos($imgSrc, 'girls-deco-image') === false) {
                        $thumbUrl = $imgSrc;
                        break;
                    }
                }
            }
            if (empty($thumbUrl)) {
                if (preg_match('/<video[^>]+poster=["\']([^"\']+)["\']/i', $htmlBody, $posterMatch)) {
                    $thumbUrl = $posterMatch[1];
                }
            }
        }
        
        // 動画URLをhtmlBodyに埋め込み（参考サイトと同様）
        if (!empty($videoUrls)) {
            $videoTags = '';
            foreach ($videoUrls as $vUrl) {
                $fullVideoUrl = $this->normalizeUrl($vUrl);
                $videoTags .= '<video class="diary-video" src="' . htmlspecialchars($fullVideoUrl) . '"';
                if (!empty($thumbUrl)) {
                    $fullPosterUrl = $this->normalizeUrl($thumbUrl);
                    $videoTags .= ' poster="' . htmlspecialchars($fullPosterUrl) . '"';
                }
                $videoTags .= ' controls muted></video>' . PHP_EOL;
            }
            $htmlBody = $videoTags . $htmlBody;
        }
        
        return [
            'pd_id' => $pdId,
            'cast_name' => $writerName,
            'title' => $title,
            'posted_at' => $postedAt,
            'thumb_url' => $this->normalizeUrl($thumbUrl),
            'video_url' => $this->normalizeUrl($videoUrl),
            'poster_url' => $this->normalizeUrl($posterUrl),
            'has_video' => $hasVideo,
            'html_body' => $htmlBody,
            'detail_url' => $detailUrl,
            'is_my_girl_limited' => $isMyGirlLimited,
        ];
    }
    
    /**
     * DOM要素の内部HTMLを取得
     */
    private function getInnerHTML($node) {
        $innerHTML = '';
        $children = $node->childNodes;
        foreach ($children as $child) {
            $innerHTML .= $node->ownerDocument->saveHTML($child);
        }
        return trim($innerHTML);
    }
    
    /**
     * 投稿時刻のパース
     */
    private function parsePostTime($timeStr) {
        // 空白・改行を正規化
        $timeStr = preg_replace('/\s+/', ' ', trim($timeStr));
        if (empty($timeStr)) return '';
        
        // 「2024/12/25 14:30」形式
        if (preg_match('/(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})\s*(\d{1,2}):(\d{2})/', $timeStr, $matches)) {
            return sprintf('%04d-%02d-%02d %02d:%02d:00', $matches[1], $matches[2], $matches[3], $matches[4], $matches[5]);
        }
        
        // 「2024-12-25T14:30:00」ISO形式
        if (preg_match('/(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/', $timeStr, $matches)) {
            return sprintf('%04d-%02d-%02d %02d:%02d:00', $matches[1], $matches[2], $matches[3], $matches[4], $matches[5]);
        }
        
        // 「12/25 14:30」形式（スペースなしも対応：「12/2514:30」）
        if (preg_match('/(\d{1,2})\/(\d{1,2})\s*(\d{1,2}):(\d{2})/', $timeStr, $matches)) {
            return sprintf('%04d-%02d-%02d %02d:%02d:00', date('Y'), $matches[1], $matches[2], $matches[3], $matches[4]);
        }
        
        // 「2024年12月25日 14:30」形式
        if (preg_match('/(\d{4})年(\d{1,2})月(\d{1,2})日\s*(\d{1,2}):(\d{2})/', $timeStr, $matches)) {
            return sprintf('%04d-%02d-%02d %02d:%02d:00', $matches[1], $matches[2], $matches[3], $matches[4], $matches[5]);
        }
        
        // 「12月25日 14:30」形式
        if (preg_match('/(\d{1,2})月(\d{1,2})日\s*(\d{1,2}):(\d{2})/', $timeStr, $matches)) {
            return sprintf('%04d-%02d-%02d %02d:%02d:00', date('Y'), $matches[1], $matches[2], $matches[3], $matches[4]);
        }
        
        // 「○時間前」形式
        if (preg_match('/(\d+)\s*時間前/', $timeStr, $matches)) {
            return date('Y-m-d H:i:s', strtotime("-{$matches[1]} hours"));
        }
        
        // 「○分前」形式
        if (preg_match('/(\d+)\s*分前/', $timeStr, $matches)) {
            return date('Y-m-d H:i:s', strtotime("-{$matches[1]} minutes"));
        }
        
        // 「○日前」形式
        if (preg_match('/(\d+)\s*日前/', $timeStr, $matches)) {
            return date('Y-m-d H:i:s', strtotime("-{$matches[1]} days"));
        }
        
        // 時刻だけ「14:30」の場合（今日の日付を付与）
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $timeStr, $matches)) {
            return sprintf('%s %02d:%02d:00', date('Y-m-d'), $matches[1], $matches[2]);
        }
        
        return '';
    }
    
    /**
     * 投稿保存
     */
    private function savePost($post) {
        try {
            // キャストIDを取得（テナントDBから）
            $castId = $this->getCastIdByName($post['cast_name']);
            
            if (!$castId) {
                $this->log("キャスト未登録（スキップ）: {$post['cast_name']}");
                return false;
            }
            
            // ON DUPLICATE KEY UPDATEで重複処理
            $stmt = $this->platformPdo->prepare("
                INSERT INTO diary_posts (
                    tenant_id, pd_id, cast_id, cast_name, title, posted_at,
                    thumb_url, video_url, poster_url, has_video,
                    html_body, content_hash, detail_url, is_my_girl_limited
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    cast_id = VALUES(cast_id),
                    cast_name = VALUES(cast_name),
                    title = VALUES(title),
                    posted_at = VALUES(posted_at),
                    thumb_url = VALUES(thumb_url),
                    video_url = VALUES(video_url),
                    poster_url = VALUES(poster_url),
                    has_video = VALUES(has_video),
                    html_body = CASE WHEN VALUES(html_body) != '' AND VALUES(html_body) IS NOT NULL 
                                THEN VALUES(html_body) ELSE html_body END,
                    detail_url = VALUES(detail_url),
                    is_my_girl_limited = VALUES(is_my_girl_limited),
                    updated_at = NOW()
            ");
            
            $contentHash = !empty($post['html_body']) ? md5($post['html_body']) : null;
            
            $stmt->execute([
                $this->tenantId,
                $post['pd_id'],
                $castId,
                $post['cast_name'],
                $post['title'],
                $post['posted_at'],
                $post['thumb_url'],
                $post['video_url'] ?? '',
                $post['poster_url'] ?? '',
                $post['has_video'],
                $post['html_body'] ?? '',
                $contentHash,
                $post['detail_url'],
                $post['is_my_girl_limited'] ?? 0,
            ]);
            
            $affected = $stmt->rowCount();
            
            if ($affected === 1) {
                // 新規挿入
                $this->log("新規保存: {$post['title']} ({$post['cast_name']})");
                return true;
            } elseif ($affected === 2) {
                // 更新
                $this->log("更新: {$post['title']} ({$post['cast_name']})");
                return false;
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->log("保存エラー: " . $e->getMessage());
            $this->stats['errors_count']++;
            return false;
        }
    }
    
    /**
     * キャスト名からキャストIDを取得
     * ※ キャストデータはプラットフォームDB内のtenant_castsテーブルに格納
     */
    private function getCastIdByName($castName) {
        try {
            // プラットフォームDBのtenant_castsテーブルから検索（checked=1がアクティブ）
            $stmt = $this->platformPdo->prepare("
                SELECT id FROM tenant_casts 
                WHERE tenant_id = ? AND name = ? AND checked = 1
                LIMIT 1
            ");
            $stmt->execute([$this->tenantId, $castName]);
            $result = $stmt->fetch();
            
            return $result ? $result['id'] : null;
            
        } catch (Exception $e) {
            $this->log("キャストID取得エラー: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 古いデータ削除（キャスト単位: 1キャストあたり最大500件）
     * 超過分は古い順に削除し、関連するローカルメディアファイルも削除
     */
    private function cleanupOldPosts() {
        $maxPostsPerCast = 500;
        $totalDeleted = 0;
        
        try {
            // このテナントのキャスト一覧を取得
            $stmt = $this->platformPdo->prepare("
                SELECT DISTINCT cast_id FROM diary_posts WHERE tenant_id = ?
            ");
            $stmt->execute([$this->tenantId]);
            $castIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($castIds as $castId) {
                // このキャストの投稿数を確認
                $countStmt = $this->platformPdo->prepare("
                    SELECT COUNT(*) FROM diary_posts WHERE tenant_id = ? AND cast_id = ?
                ");
                $countStmt->execute([$this->tenantId, $castId]);
                $postCount = (int)$countStmt->fetchColumn();
                
                if ($postCount <= $maxPostsPerCast) {
                    continue;
                }
                
                // 超過分の投稿を取得（メディアファイル削除のため先にデータ取得）
                $excessStmt = $this->platformPdo->prepare("
                    SELECT id, thumb_url, video_url, poster_url, html_body
                    FROM diary_posts
                    WHERE tenant_id = ? AND cast_id = ?
                    ORDER BY posted_at DESC, created_at DESC
                    LIMIT 999999 OFFSET ?
                ");
                $excessStmt->execute([$this->tenantId, $castId, $maxPostsPerCast]);
                $excessPosts = $excessStmt->fetchAll();
                
                if (empty($excessPosts)) continue;
                
                $deleteIds = [];
                foreach ($excessPosts as $post) {
                    $deleteIds[] = $post['id'];
                    // ローカルメディアファイルを削除
                    $this->deletePostMediaFiles($post);
                }
                
                // DBから削除
                $placeholders = implode(',', array_fill(0, count($deleteIds), '?'));
                $delStmt = $this->platformPdo->prepare("
                    DELETE FROM diary_posts WHERE id IN ({$placeholders})
                ");
                $delStmt->execute($deleteIds);
                
                $deleted = $delStmt->rowCount();
                $totalDeleted += $deleted;
                $this->log("キャストID={$castId}: 古い投稿{$deleted}件削除（{$postCount}件 → {$maxPostsPerCast}件）");
            }
            
            if ($totalDeleted > 0) {
                $this->log("合計{$totalDeleted}件の古い投稿を削除しました");
            }
            
        } catch (Exception $e) {
            $this->log("データ削除エラー: " . $e->getMessage());
        }
    }
    
    /**
     * 投稿に関連するローカルメディアファイルを削除
     */
    private function deletePostMediaFiles($post) {
        // scraper.phpの場所: www/app/manage/diary_scrape/includes/
        // uploads/の場所: www/uploads/
        $uploadBase = dirname(dirname(dirname(dirname(__DIR__))));
        
        // thumb_url, video_url, poster_url のローカルファイルを削除
        $urlFields = ['thumb_url', 'video_url', 'poster_url'];
        foreach ($urlFields as $field) {
            if (!empty($post[$field]) && strpos($post[$field], '/uploads/diary/') === 0) {
                $filePath = $uploadBase . $post[$field];
                if (file_exists($filePath)) {
                    @unlink($filePath);
                }
            }
        }
        
        // html_body内のローカル画像・動画ファイルも削除
        if (!empty($post['html_body'])) {
            if (preg_match_all('/(?:src|poster)=["\'](\\/uploads\\/diary\\/[^"\']+)["\']/i', $post['html_body'], $matches)) {
                foreach ($matches[1] as $localPath) {
                    $filePath = $uploadBase . $localPath;
                    if (file_exists($filePath)) {
                        @unlink($filePath);
                    }
                }
            }
        }
    }
    
    /**
     * 投稿のメディア（画像・動画）をローカルにダウンロード（参考サイト移植）
     * CityHeavenの_limit付きURLは認証が必要なため、スクレイピング中にダウンロードして
     * ローカルパスに置き換える
     */
    private function downloadPostMedia($post) {
        // サムネイル画像ダウンロード
        if (!empty($post['thumb_url']) && $this->isExternalUrl($post['thumb_url'])) {
            $localPath = $this->downloadFile($post['thumb_url'], 'thumbs', $post['pd_id']);
            if ($localPath) {
                $this->log("サムネイル保存成功: pd_id={$post['pd_id']}");
                $post['thumb_url'] = $localPath;
            }
        }
        
        // 動画ダウンロード
        if (!empty($post['video_url']) && $this->isExternalUrl($post['video_url'])) {
            $localPath = $this->downloadFile($post['video_url'], 'videos', $post['pd_id']);
            if ($localPath) {
                $this->log("動画保存成功: pd_id={$post['pd_id']}");
                $post['video_url'] = $localPath;
            }
        }
        
        // ポスター画像ダウンロード
        if (!empty($post['poster_url']) && $this->isExternalUrl($post['poster_url'])) {
            $localPath = $this->downloadFile($post['poster_url'], 'images', $post['pd_id']);
            if ($localPath) {
                $post['poster_url'] = $localPath;
            }
        }
        
        // 本文内の外部画像もダウンロード
        if (!empty($post['html_body'])) {
            $post['html_body'] = $this->downloadContentMedia($post['html_body'], $post['pd_id']);
        }
        
        return $post;
    }
    
    /**
     * 外部URLかどうかチェック
     */
    private function isExternalUrl($url) {
        return !empty($url) && (strpos($url, 'http') === 0 || strpos($url, '//') === 0);
    }
    
    /**
     * ファイルをダウンロードしてローカルに保存（参考サイト移植）
     */
    private function downloadFile($url, $type, $pdId, $index = 1) {
        if (empty($url)) return null;
        
        // URL正規化
        $url = $this->normalizeUrl($url);
        if (empty($url)) return null;
        
        // 拡張子取得
        $urlPathOnly = parse_url($url, PHP_URL_PATH) ?: $url;
        $pathInfo = pathinfo($urlPathOnly);
        $ext = isset($pathInfo['extension']) ? strtolower($pathInfo['extension']) : 'jpg';
        
        // ファイル名生成
        $timestamp = date('YmdHis');
        $fileName = "pd{$pdId}_{$type}_{$index}_{$timestamp}.{$ext}";
        
        // 保存先ディレクトリ（テナント別）
        $uploadBase = dirname(dirname(dirname(dirname(__DIR__)))) . '/uploads/diary/' . $this->tenantId;
        $monthDir = date('Ym');
        $saveDir = $uploadBase . "/{$type}/{$monthDir}/";
        
        if (!is_dir($saveDir)) {
            @mkdir($saveDir, 0755, true);
        }
        
        $filePath = $saveDir . $fileName;
        
        // cURLでダウンロード（認証Cookie付き）
        curl_setopt_array($this->curl, [
            CURLOPT_URL => $url,
            CURLOPT_POST => false,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'Accept: image/*, video/*, */*',
                'Accept-Language: ja,en-US;q=0.7,en;q=0.3',
            ],
        ]);
        
        $fileData = curl_exec($this->curl);
        $httpCode = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
        
        if (curl_errno($this->curl) || empty($fileData) || $httpCode !== 200) {
            $this->log("ダウンロード失敗: {$url} (HTTP {$httpCode})");
            return null;
        }
        
        // ファイル保存
        if (file_put_contents($filePath, $fileData)) {
            // Webアクセス用の相対パスを返す
            $webPath = '/uploads/diary/' . $this->tenantId . "/{$type}/{$monthDir}/{$fileName}";
            return $webPath;
        }
        
        $this->log("ファイル保存失敗: {$filePath}");
        return null;
    }
    
    /**
     * 本文内の外部画像をダウンロードしてローカルパスに置換
     */
    private function downloadContentMedia($html, $pdId) {
        if (empty($html)) return $html;
        
        $imgIndex = 0;
        // 本文内の外部画像URLをローカルに置換
        $html = preg_replace_callback('/<img([^>]*)src=["\']([^"\']+)["\']/i', function($matches) use ($pdId, &$imgIndex) {
            $src = $matches[2];
            if ($this->isExternalUrl($src)) {
                $imgIndex++;
                $localPath = $this->downloadFile($src, 'images', $pdId, $imgIndex);
                if ($localPath) {
                    return '<img' . $matches[1] . 'src="' . $localPath . '"';
                }
            }
            return $matches[0];
        }, $html);
        
        // 本文内の外部動画URLもローカルに置換
        $vidIndex = 0;
        $html = preg_replace_callback('/<video([^>]*)src=["\']([^"\']+)["\']/i', function($matches) use ($pdId, &$vidIndex) {
            $src = $matches[2];
            if ($this->isExternalUrl($src)) {
                $vidIndex++;
                $localPath = $this->downloadFile($src, 'videos', $pdId, $vidIndex);
                if ($localPath) {
                    return '<video' . $matches[1] . 'src="' . $localPath . '"';
                }
            }
            return $matches[0];
        }, $html);
        
        // posterのURLも置換
        $html = preg_replace_callback('/poster=["\']([^"\']+)["\']/i', function($matches) use ($pdId) {
            $src = $matches[1];
            if ($this->isExternalUrl($src)) {
                $localPath = $this->downloadFile($src, 'images', $pdId, 99);
                if ($localPath) {
                    return 'poster="' . $localPath . '"';
                }
            }
            return $matches[0];
        }, $html);
        
        return $html;
    }
    
    /**
     * 本文HTMLのクリーンアップ（CityHeaven固有の広告・リクルートリンク除去）
     */
    private function cleanHtmlBody($html) {
        if (empty($html)) return '';
        
        // girlsheaven-job.net リクルートリンクのdivブロックを除去
        $html = preg_replace('/<div[^>]*>\s*<a[^>]*href=["\'][^"\']*girlsheaven[^"\']*["\'][^>]*>[\s\S]*?<\/a>\s*<\/div>/i', '', $html);
        
        // girlsheaven関連のscriptタグを除去
        $html = preg_replace('/<script[^>]*girlsheaven[^>]*>[\s\S]*?<\/script>/i', '', $html);
        $html = preg_replace('/<script[^>]*cityheaven[^>]*recruit[^>]*>[\s\S]*?<\/script>/i', '', $html);
        $html = preg_replace('/<script[^>]*recruit[^>]*cityheaven[^>]*>[\s\S]*?<\/script>/i', '', $html);
        
        // recruit_blog.png を含む要素を除去
        $html = preg_replace('/<[^>]*recruit_blog[^>]*>[\s\S]*?<\/[^>]+>/i', '', $html);
        
        // diary_photoframe を除去（サムネイル画像はthumb_urlで別管理）
        $html = preg_replace('/<div[^>]*class=["\'][^"\']*diary_photoframe[^"\']*["\'][^>]*>[\s\S]*?<\/div>/i', '', $html);
        
        // CityHeavenのタイトル・ヘッダー要素を除去
        $html = preg_replace('/<div[^>]*class=["\'][^"\']*diary_title[^"\']*["\'][^>]*>[\s\S]*?<\/div>/i', '', $html);
        $html = preg_replace('/<h3[^>]*class=["\'][^"\']*diary_title[^"\']*["\'][^>]*>[\s\S]*?<\/h3>/i', '', $html);
        $html = preg_replace('/<div[^>]*class=["\'][^"\']*diary_headding[^"\']*["\'][^>]*>[\s\S]*?<\/div>/i', '', $html);
        
        // 空のpタグを除去
        $html = preg_replace('/<p>\s*<\/p>/i', '', $html);
        
        // 先頭・末尾の余分なbrタグを除去
        $html = preg_replace('/^\s*(<br\s*\/?>[\s]*)+/i', '', $html);
        $html = preg_replace('/(<br\s*\/?>[\s]*)+\s*$/i', '', $html);
        
        return trim($html);
    }
    
    /**
     * 本文が「実質的に空（デコ枠や画像タグのみ）」かを判定（参考サイト移植）
     */
    private function isWeakHtmlBody($html) {
        if (empty($html)) return true;
        if (stripos($html, 'diary_photoframe') !== false) return true;
        // 画像とリンクを除去してテキストだけ評価
        $plain = preg_replace('/<img[^>]*>/i', '', $html ?? '');
        $plain = preg_replace('/<a[^>]*>|<\/a>/', '', $plain);
        $plain = str_ireplace(['<br>', '<br/>', '<br />'], '', $plain);
        $text = trim(strip_tags($plain));
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/u', '', $text);
        return mb_strlen($text ?? '', 'UTF-8') < 5;
    }
    
    /**
     * 詳細ページから本文HTMLを抽出（参考サイト移植）
     */
    private function fetchDiaryDetailHtml($detailUrl) {
        if (empty($detailUrl)) return '';
        
        $this->log("詳細ページ取得開始: {$detailUrl}");
        
        curl_setopt_array($this->curl, [
            CURLOPT_URL => $detailUrl,
            CURLOPT_POST => false,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: ja,en-US;q=0.7,en;q=0.3',
                'Accept-Encoding: gzip, deflate',
                'Cache-Control: no-cache',
            ],
        ]);
        
        $html = curl_exec($this->curl);
        if (curl_errno($this->curl) || empty($html)) {
            $error = curl_error($this->curl);
            $httpCode = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
            $this->log("詳細ページ取得失敗: HTTPコード={$httpCode}, エラー={$error}");
            return '';
        }
        
        $doc = new DOMDocument();
        
        // 文字コード検出と変換
        $encoding = mb_detect_encoding($html, ['UTF-8', 'Shift_JIS', 'EUC-JP', 'ISO-2022-JP'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $html = mb_convert_encoding($html, 'UTF-8', $encoding);
        }
        
        $htmlForDom = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        @$doc->loadHTML($htmlForDom, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $xp = new DOMXPath($doc);
        
        // デコ画像コンテナ（photoframe）を除去
        foreach ($xp->query("//*[contains(@class,'photoframe') or contains(@class,'photo-frame')]") as $node) {
            if ($node && $node->parentNode) {
                $node->parentNode->removeChild($node);
            }
        }
        
        // 本文抽出候補（参考サイト準拠）
        $candidates = [
            // 実際の記事内のテキストコンテンツ
            "//article//p[string-length(normalize-space(.)) > 10]",
            "//article//div[string-length(normalize-space(.)) > 10]",
            
            // より具体的なarticle内検索
            "//article/div//p[string-length(normalize-space(.)) > 10]",
            "//article/div//div[string-length(normalize-space(.)) > 10]",
            
            // リンクを含む告知テキストを除外
            "//article//p[string-length(normalize-space(.)) > 15 and not(.//a) and not(contains(text(),'18歳未満') or contains(text(),'風俗コンテンツ') or contains(text(),'EXIT'))]",
            "//article//div[string-length(normalize-space(.)) > 15 and not(.//a) and not(contains(text(),'18歳未満') or contains(text(),'風俗コンテンツ') or contains(text(),'EXIT'))]",
            
            // フォールバック: 一般的なパターン
            "//div[contains(@class,'diary_comment')]",
            "//div[contains(@class,'diary_content')]",
            "//*[contains(@class,'diary_text')]",
            "//p[string-length(normalize-space(.)) > 20]",
        ];
        
        foreach ($candidates as $sel) {
            $nodes = $xp->query($sel);
            if ($nodes && $nodes->length > 0) {
                $inner = '';
                
                if (strpos($sel, '//text()') !== false || strpos($sel, '[text()]') !== false) {
                    foreach ($nodes as $textNode) {
                        $text = trim($textNode->nodeValue);
                        if (strlen($text) > 5) {
                            $inner .= '<p>' . htmlspecialchars($text) . '</p>';
                        }
                    }
                } else {
                    $node = $nodes->item(0);
                    foreach ($node->childNodes as $child) {
                        $inner .= $doc->saveHTML($child);
                    }
                }
                
                if (trim($inner) !== '' && strlen(strip_tags($inner)) > 10) {
                    $this->log("詳細本文抽出成功: {$sel} - " . strlen($inner) . ' bytes');
                    return $inner;
                }
            }
        }
        
        $this->log('詳細本文が見つからない');
        return '';
    }
    
    /**
     * URL正規化（参考サイトのnormalizeUrlを移植）
     * - //で始まるスキーマレスURL → https:を付与
     * - 相対パス → CityHeavenベースURLを付与
     */
    private function normalizeUrl($url) {
        if (empty($url)) return '';
        
        $url = ltrim($url, '@');
        
        if (strpos($url, 'http') === 0) {
            return $url;
        } elseif (strpos($url, '//') === 0) {
            return 'https:' . $url;
        } elseif (strpos($url, '/') === 0) {
            return 'https://www.cityheaven.net' . $url;
        }
        
        return $url;
    }
    
    /**
     * ログ出力
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] {$message}\n";
        
        // ファイルに書き込み
        if ($this->logFile) {
            @file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
        }
        
        // CLIの場合はecho
        if (php_sapi_name() === 'cli') {
            echo $logEntry;
        }
    }
}
