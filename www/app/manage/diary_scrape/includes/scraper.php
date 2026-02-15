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
        'post_time' => './/span[contains(@class,"time") or contains(@class,"date")]//text() | .//div[2]/div[1]/div/span/text()',
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
     * スクレイピング
     */
    private function scrape() {
        $this->log('=== スクレイピング開始 ===');
        
        $page = 1;
        $maxPages = $this->settings['max_pages'] ?? 50;
        $shopUrl = $this->settings['shop_url'];
        
        while ($page <= $maxPages) {
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
            
            // HTML解析
            $posts = $this->parseHtml($html);
            
            if (empty($posts)) {
                $this->log('投稿が見つかりませんでした。終了します。');
                break;
            }
            
            $this->log(count($posts) . '件の投稿を発見');
            $this->stats['posts_found'] += count($posts);
            
            // 投稿を保存
            foreach ($posts as $post) {
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
        
        $this->log('=== スクレイピング終了 ===');
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
        
        // 投稿時刻取得
        $timeNodes = $xpath->query($this->xpathConfig['post_time'], $article);
        $timeStr = $timeNodes->length > 0 ? trim($timeNodes->item(0)->nodeValue) : '';
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
        
        // サムネイル取得
        $thumbUrl = '';
        $thumbNodes = $xpath->query($this->xpathConfig['thumbnail'], $article);
        if ($thumbNodes->length > 0) {
            $thumbUrl = $thumbNodes->item(0)->value ?? $thumbNodes->item(0)->nodeValue;
        }
        if (empty($thumbUrl)) {
            $oldThumbNodes = $xpath->query($this->xpathConfig['thumbnail_original'], $article);
            if ($oldThumbNodes->length > 0) {
                $thumbUrl = $oldThumbNodes->item(0)->value ?? $oldThumbNodes->item(0)->nodeValue;
            }
        }
        
        // 動画チェック
        $hasVideo = 0;
        $videoUrl = '';
        $posterUrl = '';
        $videoNodes = $xpath->query($this->xpathConfig['videos'], $article);
        if ($videoNodes->length > 0) {
            $hasVideo = 1;
            $video = $videoNodes->item(0);
            $srcNodes = $xpath->query('./@src', $video);
            if ($srcNodes->length > 0) {
                $videoUrl = $srcNodes->item(0)->value;
            }
            $posterNodes = $xpath->query('./@poster', $video);
            if ($posterNodes->length > 0) {
                $posterUrl = $posterNodes->item(0)->value;
                if (empty($thumbUrl)) {
                    $thumbUrl = $posterUrl;
                }
            }
        }
        
        // 本文取得
        $htmlBody = '';
        $contentNodes = $xpath->query($this->xpathConfig['content'], $article);
        if ($contentNodes->length > 0) {
            $htmlBody = $this->getInnerHTML($contentNodes->item(0));
        }
        
        // マイガール限定投稿判定
        $isMyGirlLimited = (!empty($detailUrl) && strpos($detailUrl, '?lo=1') !== false) ? 1 : 0;
        
        return [
            'pd_id' => $pdId,
            'cast_name' => $writerName,
            'title' => $title,
            'posted_at' => $postedAt,
            'thumb_url' => $thumbUrl,
            'video_url' => $videoUrl,
            'poster_url' => $posterUrl,
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
     * 投稿時刻のパース（参考サイトから移植）
     */
    private function parsePostTime($timeStr) {
        $timeStr = trim($timeStr);
        if (empty($timeStr)) return '';
        
        // 「2024/12/25 14:30」形式
        if (preg_match('/(\d{4})\/(\d{1,2})\/(\d{1,2})\s+(\d{1,2}):(\d{2})/', $timeStr, $matches)) {
            return sprintf('%04d-%02d-%02d %02d:%02d:00', $matches[1], $matches[2], $matches[3], $matches[4], $matches[5]);
        }
        
        // 「12/25 14:30」形式
        if (preg_match('/(\d{1,2})\/(\d{1,2})\s+(\d{1,2}):(\d{2})/', $timeStr, $matches)) {
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
     * 古いデータ削除
     */
    private function cleanupOldPosts() {
        try {
            $maxPosts = $this->settings['max_posts_per_tenant'] ?? 1000;
            
            $stmt = $this->platformPdo->prepare("
                DELETE FROM diary_posts
                WHERE tenant_id = ?
                AND id NOT IN (
                    SELECT id FROM (
                        SELECT id FROM diary_posts
                        WHERE tenant_id = ?
                        ORDER BY posted_at DESC, created_at DESC
                        LIMIT ?
                    ) AS keep_posts
                )
            ");
            $stmt->execute([$this->tenantId, $this->tenantId, $maxPosts]);
            
            $deleted = $stmt->rowCount();
            if ($deleted > 0) {
                $this->log("古いデータ削除: {$deleted}件");
            }
            
        } catch (Exception $e) {
            $this->log("データ削除エラー: " . $e->getMessage());
        }
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
