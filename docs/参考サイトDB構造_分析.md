# 参考サイト - データベース構造分析

**分析日**: 2026-02-14  
**参照元**: `reference/public_html/admin/diary_scrape/`

---

## 📊 テーブル構造

### 1. diary_posts テーブル

**用途**: 写メ日記投稿データを保存

#### カラム構成（コードから逆算）

```sql
CREATE TABLE diary_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pd_id BIGINT NOT NULL UNIQUE COMMENT 'CityHeavenの投稿ID',
    cast_id INT COMMENT 'キャストID（cast_dataテーブルへの外部キー）',
    
    -- 投稿情報
    title VARCHAR(500) COMMENT 'タイトル',
    writer_name VARCHAR(100) NOT NULL COMMENT '投稿者名（キャスト名）',
    posted_at DATETIME NOT NULL COMMENT '投稿日時',
    
    -- メディア情報
    thumb_url VARCHAR(500) COMMENT 'サムネイル画像URL',
    video_url VARCHAR(500) COMMENT '動画URL',
    poster_url VARCHAR(500) COMMENT '動画ポスター画像URL',
    has_video TINYINT(1) DEFAULT 0 COMMENT '動画有無フラグ',
    
    -- 本文
    html_body TEXT COMMENT '本文HTML',
    content_hash VARCHAR(64) COMMENT '本文ハッシュ値（重複チェック用）',
    
    -- タイムスタンプ
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
    
    UNIQUE KEY unique_pd_id (pd_id),
    INDEX idx_cast_id (cast_id),
    INDEX idx_posted_at (posted_at),
    INDEX idx_writer_name (writer_name),
    FOREIGN KEY (cast_id) REFERENCES cast_data(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### 使用箇所

**INSERT/UPDATE**:
```php
// scraper_functions.php:1375-1391
INSERT INTO diary_posts (
    pd_id, cast_id, title, posted_at, writer_name,
    thumb_url, video_url, poster_url, html_body, has_video, content_hash
) VALUES (...)
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    thumb_url = VALUES(thumb_url),
    video_url = VALUES(video_url),
    poster_url = VALUES(poster_url),
    html_body = VALUES(html_body),
    has_video = VALUES(has_video),
    content_hash = VALUES(content_hash),
    updated_at = CURRENT_TIMESTAMP
```

**SELECT**:
```php
// 重複チェック
SELECT id FROM diary_posts WHERE pd_id = ? LIMIT 1

// 投稿取得
SELECT * FROM diary_posts WHERE pd_id = ? LIMIT 1

// 統計情報
SELECT COUNT(*) as total FROM diary_posts
SELECT COUNT(*) as today FROM diary_posts WHERE DATE(created_at) = CURDATE()

// 最新投稿
SELECT dp.title, dp.writer_name, dp.posted_at, dp.created_at
FROM diary_posts dp
ORDER BY dp.posted_at DESC, dp.created_at DESC
LIMIT 10
```

**JOIN**:
```php
// キャスト別投稿数
SELECT cd.name, COUNT(*) as count 
FROM diary_posts dp 
JOIN cast_data cd ON dp.cast_id = cd.id 
GROUP BY cd.id, cd.name 
ORDER BY count DESC 
LIMIT 10

// 削除対象投稿（キャスト不在）
SELECT dp.id, dp.pd_id, dp.thumb_url, dp.html_body
FROM diary_posts dp
LEFT JOIN cast_data cd ON dp.cast_id = cd.id
WHERE cd.id IS NULL
```

---

### 2. cast_data テーブル（既存）

**用途**: キャスト情報管理

#### 使用箇所

```php
// キャスト名からID取得
SELECT id FROM cast_data WHERE name = ? LIMIT 1
```

#### リレーション
- `diary_posts.cast_id` → `cast_data.id`
- 外部キー制約: `ON DELETE SET NULL`（キャスト削除時は投稿のcast_idをNULLに）

---

## 🔍 重要な仕様

### 1. pd_id（投稿ID）
- **型**: BIGINT
- **制約**: UNIQUE
- **用途**: CityHeavenの投稿IDを保存
- **重複チェック**: このカラムで既存投稿を判定
- **例**: `735666819`

### 2. キャスト紐付け
```php
// scraper_functions.php:1342-1347
public function getCastIdByName($writerName) {
    $stmt = $this->pdo->prepare("SELECT id FROM cast_data WHERE name = ? LIMIT 1");
    $stmt->execute([$writerName]);
    $result = $stmt->fetch();
    return $result ? $result['id'] : null;
}
```

**紐付けロジック**:
1. 投稿の`writer_name`から`cast_data`テーブルを検索
2. 一致するキャストがいれば`cast_id`を設定
3. いなければ`NULL`（マイガール限定投稿の場合は保存）

### 3. ON DUPLICATE KEY UPDATE
```php
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    thumb_url = VALUES(thumb_url),
    // ... 他のカラム
    updated_at = CURRENT_TIMESTAMP
```

**動作**:
- `pd_id`が既に存在する場合は更新
- 新規の場合は挿入
- `created_at`は初回のみ、`updated_at`は毎回更新

### 4. メディアURL管理

**保存形式**:
```
/admin/diary_scrape/uploads/diary/images/202501/pd735666819_thumb_20250113123456.jpg
/admin/diary_scrape/uploads/diary/videos/202501/pd735666819_video_1_20250113123456.mp4
```

**ディレクトリ構造**:
```
uploads/diary/
├── thumbs/202501/     # サムネイル（非推奨、imagesに統合中）
├── images/202501/     # 画像
├── deco/202501/       # デコ画像
└── videos/202501/     # 動画
```

---

## 📝 プルキャスへの適用

### 変更が必要な点

#### 1. テナント分離
**参考サイト**: 単一テナント（シングルDB）
```sql
diary_posts (全店舗共通)
```

**プルキャス**: マルチテナント（テナント別DB）
```sql
-- テナント1のDB
tenant_1.diary_posts

-- テナント2のDB
tenant_2.diary_posts
```

#### 2. 設定管理
**参考サイト**: ハードコード
```php
// scrape_config.php
define('CITYHEAVEN_LOGIN_ID', 'nasu.o.0327@gmail.com');
define('CITYHEAVEN_PASSWORD', 'nasu0903');
define('DIARY_LIST_BASE_URL', '/fukuoka/A4001/A400101/houmantengoku/diarylist/');
```

**プルキャス**: DB管理（プラットフォームDB）
```sql
-- diary_scrape_settings テーブル
tenant_id | cityheaven_login_id | cityheaven_password | shop_url
----------|---------------------|---------------------|----------
1         | tenant1@example.com | encrypted_pass      | /fukuoka/.../shop1/
2         | tenant2@example.com | encrypted_pass      | /fukuoka/.../shop2/
```

#### 3. ファイル保存パス
**参考サイト**:
```
/admin/diary_scrape/uploads/diary/images/202501/
```

**プルキャス**:
```
/uploads/diary/{tenant_id}/images/202501/
```

#### 4. 実行管理
**参考サイト**: 単一プロセス
- ロックファイル: `logs/scraping.lock`

**プルキャス**: テナント別プロセス
- ロックファイル: `logs/scraping_{tenant_id}.lock`
- 並列実行制御が必要

---

## ✅ そのまま使える点

### 1. テーブル構造
`diary_posts`テーブルの構造はほぼそのまま使用可能
- カラム定義
- インデックス
- 外部キー制約

### 2. スクレイピングロジック
- ログイン処理
- HTML解析（XPath）
- メディアダウンロード
- 重複チェック

### 3. データ処理
- キャスト紐付け
- ON DUPLICATE KEY UPDATE
- コンテンツハッシュ

---

## 🎯 実装時の注意点

### 1. テナント情報の受け渡し
```php
// 全てのクラスでテナントIDを管理
class DiaryScraper {
    private $tenantId;
    
    public function __construct($tenantId) {
        $this->tenantId = $tenantId;
        // テナント別の設定を読み込み
        $this->loadConfig();
    }
}
```

### 2. DB接続の切り替え
```php
// プラットフォームDB（設定取得）
$platformPdo = getPlatformDb();

// テナントDB（投稿保存）
$tenantPdo = getTenantDb($tenantId);
```

### 3. ファイルパスの動的生成
```php
// テナント別のアップロードディレクトリ
define('UPLOAD_BASE_DIR', __DIR__ . "/../../uploads/diary/{$tenantId}/");
define('UPLOAD_WEB_PATH', "/uploads/diary/{$tenantId}/");
```

### 4. ログファイルの分離
```php
// テナント別ログファイル
$logFile = LOG_DIR . "diary_scrape_{$tenantId}_" . date('Ymd') . '.log';
```

---

## 📊 データ移行（将来的に必要な場合）

参考サイトから既存データを移行する場合:

```sql
-- 参考サイトのdiary_postsをプルキャスのテナントDBへコピー
INSERT INTO tenant_1.diary_posts 
SELECT * FROM reference_db.diary_posts;

-- キャストIDの再マッピングが必要な場合
UPDATE tenant_1.diary_posts dp
JOIN tenant_1.cast_data cd ON dp.writer_name = cd.name
SET dp.cast_id = cd.id;
```

---

## 🔗 参考ファイル

- `reference/public_html/admin/diary_scrape/includes/scraper_functions.php`
  - 行1333-1461: DiaryDatabaseクラス
  - 行1375-1391: INSERT/UPDATE SQL
  
- `reference/public_html/admin/diary_scrape/index.php`
  - 行106-149: 統計情報取得SQL

- `reference/public_html/admin/diary_scrape/config/scrape_config.php`
  - 設定定義

---

**分析完了**: 2026-02-14  
**次のステップ**: プルキャス用のテーブル作成SQLを作成
