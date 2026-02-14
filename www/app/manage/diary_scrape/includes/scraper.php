<?php
/**
 * 写メ日記スクレイパークラス
 */

class DiaryScraper {
    private $tenantId;
    private $settings;
    private $platformPdo;
    private $tenantPdo;
    private $curl;
    private $cookieFile;
    private $isLoggedIn = false;
    
    // 統計
    private $stats = [
        'pages_processed' => 0,
        'posts_found' => 0,
        'posts_saved' => 0,
        'posts_skipped' => 0,
        'errors_count' => 0,
    ];
    
    public function __construct($tenantId, $settings, $platformPdo) {
        $this->tenantId = $tenantId;
        $this->settings = $settings;
        $this->platformPdo = $platformPdo;
        
        // Cookie保存先
        $this->cookieFile = sys_get_temp_dir() . "/cityheaven_cookies_{$tenantId}.txt";
        
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
            CURLOPT_TIMEOUT => $this->settings['timeout'],
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
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
     * ログイン
     */
    private function login() {
        $this->log('=== ログイン処理開始 ===');
        
        try {
            // Step 1: 日記ページにアクセス
            $this->log('Step 1: 日記ページにアクセス');
            curl_setopt($this->curl, CURLOPT_URL, $this->settings['shop_url']);
            $html = curl_exec($this->curl);
            
            if (curl_errno($this->curl)) {
                throw new Exception('ページアクセスエラー: ' . curl_error($this->curl));
            }
            
            sleep(1);
            
            // Step 2: Ajaxログイン実行
            $this->log('Step 2: Ajaxログイン実行');
            
            // URLからベースURLを抽出
            $parsedUrl = parse_url($this->settings['shop_url']);
            $baseUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
            $ajaxLoginUrl = $baseUrl . '/api/login.php';
            
            $loginData = [
                'login_id' => $this->settings['cityheaven_login_id'],
                'password' => $this->settings['cityheaven_password'],
            ];
            
            curl_setopt_array($this->curl, [
                CURLOPT_URL => $ajaxLoginUrl,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($loginData),
                CURLOPT_REFERER => $this->settings['shop_url'],
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With: XMLHttpRequest',
                    'Accept: application/json, text/javascript, */*; q=0.01',
                    'Origin: ' . $baseUrl,
                    'Referer: ' . $this->settings['shop_url'],
                ],
            ]);
            
            $loginResult = curl_exec($this->curl);
            $httpCode = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
            
            $this->log("ログインレスポンス: HTTPコード={$httpCode}");
            
            if ($httpCode === 200) {
                $jsonData = json_decode($loginResult, true);
                
                if (isset($jsonData['isLogin']) && $jsonData['isLogin'] === true) {
                    $this->log('✅ ログイン成功');
                    
                    // Cookie同期
                    sleep(2);
                    curl_setopt_array($this->curl, [
                        CURLOPT_URL => $this->settings['shop_url'],
                        CURLOPT_POST => false,
                        CURLOPT_HTTPHEADER => [
                            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                        ],
                    ]);
                    curl_exec($this->curl);
                    
                    $this->isLoggedIn = true;
                    return true;
                }
            }
            
            throw new Exception('ログイン失敗: 認証情報を確認してください');
            
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
        $maxPages = $this->settings['max_pages'];
        
        while ($page <= $maxPages) {
            $this->log("--- ページ {$page} 処理中 ---");
            
            // ページURL
            $pageUrl = $this->settings['shop_url'];
            if ($page > 1) {
                $pageUrl .= '?page=' . $page;
            }
            
            // ページ取得
            curl_setopt($this->curl, CURLOPT_URL, $pageUrl);
            $html = curl_exec($this->curl);
            
            if (curl_errno($this->curl)) {
                $this->log('ページ取得エラー: ' . curl_error($this->curl));
                $this->stats['errors_count']++;
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
            }
            
            // 次のページへ
            $page++;
            
            // 遅延
            usleep($this->settings['request_delay'] * 1000000);
        }
        
        $this->log('=== スクレイピング終了 ===');
    }
    
    /**
     * HTML解析
     */
    private function parseHtml($html) {
        $posts = [];
        
        try {
            $dom = new DOMDocument();
            @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
            $xpath = new DOMXPath($dom);
            
            // 記事ノード取得
            $articles = $xpath->query('//article[contains(@class, "diary")]');
            
            if ($articles->length === 0) {
                // 別パターンを試す
                $articles = $xpath->query('//div[contains(@class, "diary-item")]');
            }
            
            foreach ($articles as $article) {
                try {
                    $post = $this->parseArticle($xpath, $article);
                    if ($post) {
                        $posts[] = $post;
                    }
                } catch (Exception $e) {
                    $this->log('記事解析エラー: ' . $e->getMessage());
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
     * 記事解析
     */
    private function parseArticle($xpath, $article) {
        // タイトル
        $titleNodes = $xpath->query('.//h3//a', $article);
        if ($titleNodes->length === 0) {
            return null;
        }
        $titleNode = $titleNodes->item(0);
        $title = trim($titleNode->textContent);
        $detailUrl = $titleNode->getAttribute('href');
        
        // pd_id抽出
        if (preg_match('/pd(\d+)/', $detailUrl, $matches)) {
            $pdId = $matches[1];
        } else {
            return null;
        }
        
        // キャスト名
        $castNodes = $xpath->query('.//a[contains(@class, "writer") or contains(@class, "cast")]', $article);
        $castName = $castNodes->length > 0 ? trim($castNodes->item(0)->textContent) : '';
        
        // 投稿日時
        $timeNodes = $xpath->query('.//span[contains(@class, "time") or contains(@class, "date")]', $article);
        $postedAt = $timeNodes->length > 0 ? $this->parseDateTime($timeNodes->item(0)->textContent) : date('Y-m-d H:i:s');
        
        // サムネイル
        $imgNodes = $xpath->query('.//img', $article);
        $thumbUrl = $imgNodes->length > 0 ? $imgNodes->item(0)->getAttribute('src') : '';
        
        // 動画チェック
        $videoNodes = $xpath->query('.//video', $article);
        $hasVideo = $videoNodes->length > 0;
        $videoUrl = '';
        $posterUrl = '';
        
        if ($hasVideo) {
            $videoNode = $videoNodes->item(0);
            $videoUrl = $videoNode->getAttribute('src');
            $posterUrl = $videoNode->getAttribute('poster');
        }
        
        return [
            'pd_id' => $pdId,
            'cast_name' => $castName,
            'title' => $title,
            'posted_at' => $postedAt,
            'thumb_url' => $thumbUrl,
            'video_url' => $videoUrl,
            'poster_url' => $posterUrl,
            'has_video' => $hasVideo ? 1 : 0,
            'detail_url' => $detailUrl,
        ];
    }
    
    /**
     * 日時パース
     */
    private function parseDateTime($text) {
        $text = trim($text);
        
        // 「2024/12/25 14:30」形式
        if (preg_match('/(\d{4})\/(\d{1,2})\/(\d{1,2})\s+(\d{1,2}):(\d{2})/', $text, $matches)) {
            return sprintf('%04d-%02d-%02d %02d:%02d:00', $matches[1], $matches[2], $matches[3], $matches[4], $matches[5]);
        }
        
        // 「12/25 14:30」形式（今年）
        if (preg_match('/(\d{1,2})\/(\d{1,2})\s+(\d{1,2}):(\d{2})/', $text, $matches)) {
            return sprintf('%04d-%02d-%02d %02d:%02d:00', date('Y'), $matches[1], $matches[2], $matches[3], $matches[4]);
        }
        
        return date('Y-m-d H:i:s');
    }
    
    /**
     * 投稿保存
     */
    private function savePost($post) {
        try {
            // キャストIDを取得（テナントDBから）
            $castId = $this->getCastIdByName($post['cast_name']);
            
            if (!$castId) {
                $this->log("キャスト未登録: {$post['cast_name']}");
                return false;
            }
            
            // 重複チェック
            $stmt = $this->platformPdo->prepare("
                SELECT id FROM diary_posts 
                WHERE tenant_id = ? AND pd_id = ?
            ");
            $stmt->execute([$this->tenantId, $post['pd_id']]);
            
            if ($stmt->fetch()) {
                // 既存データは更新
                $stmt = $this->platformPdo->prepare("
                    UPDATE diary_posts SET
                        cast_id = ?,
                        cast_name = ?,
                        title = ?,
                        posted_at = ?,
                        thumb_url = ?,
                        video_url = ?,
                        poster_url = ?,
                        has_video = ?,
                        detail_url = ?,
                        updated_at = NOW()
                    WHERE tenant_id = ? AND pd_id = ?
                ");
                $stmt->execute([
                    $castId,
                    $post['cast_name'],
                    $post['title'],
                    $post['posted_at'],
                    $post['thumb_url'],
                    $post['video_url'],
                    $post['poster_url'],
                    $post['has_video'],
                    $post['detail_url'],
                    $this->tenantId,
                    $post['pd_id']
                ]);
                
                return false; // スキップ扱い
            }
            
            // 新規保存
            $stmt = $this->platformPdo->prepare("
                INSERT INTO diary_posts (
                    tenant_id,
                    pd_id,
                    cast_id,
                    cast_name,
                    title,
                    posted_at,
                    thumb_url,
                    video_url,
                    poster_url,
                    has_video,
                    detail_url
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $this->tenantId,
                $post['pd_id'],
                $castId,
                $post['cast_name'],
                $post['title'],
                $post['posted_at'],
                $post['thumb_url'],
                $post['video_url'],
                $post['poster_url'],
                $post['has_video'],
                $post['detail_url']
            ]);
            
            $this->log("保存成功: {$post['title']} ({$post['cast_name']})");
            return true;
            
        } catch (Exception $e) {
            $this->log("保存エラー: " . $e->getMessage());
            $this->stats['errors_count']++;
            return false;
        }
    }
    
    /**
     * キャスト名からキャストIDを取得
     */
    private function getCastIdByName($castName) {
        try {
            // テナントDBに接続
            if (!$this->tenantPdo) {
                // テナント情報取得
                $stmt = $this->platformPdo->prepare("SELECT code FROM tenants WHERE id = ?");
                $stmt->execute([$this->tenantId]);
                $tenant = $stmt->fetch();
                
                if (!$tenant) {
                    throw new Exception("テナント情報が見つかりません: ID={$this->tenantId}");
                }
                
                $tenantDbName = 'pullcass_tenant_' . $tenant['code'];
                $this->tenantPdo = getTenantDb($tenantDbName);
            }
            
            $stmt = $this->tenantPdo->prepare("
                SELECT id FROM cast_data 
                WHERE name = ? AND status = 'active'
                LIMIT 1
            ");
            $stmt->execute([$castName]);
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
            $maxPosts = $this->settings['max_posts_per_tenant'];
            
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
        echo "[{$timestamp}] {$message}\n";
    }
}
