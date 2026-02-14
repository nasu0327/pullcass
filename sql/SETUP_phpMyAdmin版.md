# 写メ日記スクレイピング機能 - 実行手順（phpMyAdmin版）

**実行日**: 2026-02-14

---

## 🚀 phpMyAdminでの実行手順

### ステップ1: プラットフォームDBにテーブル作成

#### 1-1. phpMyAdminにログイン

ブラウザで phpMyAdmin を開く

#### 1-2. データベース選択

左側のデータベース一覧から **`pullcass_platform`** をクリック

#### 1-3. SQLタブを開く

上部メニューの **「SQL」** タブをクリック

#### 1-4. SQLを実行

以下のSQLをコピーして、SQLエディタに貼り付けて **「実行」** をクリック:

```sql
-- ============================================
-- 1. 写メ日記スクレイピング設定テーブル
-- ============================================
CREATE TABLE IF NOT EXISTS diary_scrape_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL COMMENT 'テナントID',
    
    -- CityHeavenログイン情報
    cityheaven_login_id VARCHAR(255) NOT NULL COMMENT 'ログインID（メールアドレス）',
    cityheaven_password VARCHAR(500) NOT NULL COMMENT 'パスワード（暗号化）',
    
    -- スクレイピング対象URL
    shop_url VARCHAR(500) NOT NULL COMMENT '店舗URL（例: /fukuoka/A4001/A400101/houmantengoku/）',
    
    -- 実行設定
    is_enabled TINYINT(1) DEFAULT 0 COMMENT '自動取得ON/OFF',
    scrape_interval INT DEFAULT 10 COMMENT '取得間隔（分）',
    request_delay DECIMAL(3,1) DEFAULT 0.5 COMMENT 'リクエスト間隔（秒）',
    max_pages INT DEFAULT 50 COMMENT '最大ページ数',
    timeout INT DEFAULT 30 COMMENT 'タイムアウト（秒）',
    
    -- Cookie管理
    cookie_data TEXT COMMENT 'Cookie情報（JSON）',
    cookie_updated_at DATETIME COMMENT 'Cookie更新日時',
    
    -- 実行状態
    last_executed_at DATETIME COMMENT '最終実行日時',
    last_execution_status ENUM('success', 'error', 'running') COMMENT '最終実行状態',
    last_error_message TEXT COMMENT '最終エラーメッセージ',
    
    -- 統計情報
    total_posts_scraped INT DEFAULT 0 COMMENT '累計取得投稿数',
    last_posts_count INT DEFAULT 0 COMMENT '最終取得投稿数',
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_tenant (tenant_id),
    INDEX idx_enabled (is_enabled),
    INDEX idx_last_executed (last_executed_at),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='写メ日記スクレイピング設定';

-- ============================================
-- 2. スクレイピング実行ログテーブル
-- ============================================
CREATE TABLE IF NOT EXISTS diary_scrape_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL COMMENT 'テナントID',
    
    -- 実行情報
    execution_type ENUM('manual', 'cron') NOT NULL COMMENT '実行タイプ',
    started_at DATETIME NOT NULL COMMENT '開始日時',
    finished_at DATETIME COMMENT '終了日時',
    execution_time DECIMAL(10,2) COMMENT '実行時間（秒）',
    
    -- 実行結果
    status ENUM('success', 'error', 'timeout', 'running') NOT NULL COMMENT '実行結果',
    pages_processed INT DEFAULT 0 COMMENT '処理ページ数',
    posts_found INT DEFAULT 0 COMMENT '検出投稿数',
    posts_saved INT DEFAULT 0 COMMENT '保存投稿数',
    posts_skipped INT DEFAULT 0 COMMENT 'スキップ投稿数',
    errors_count INT DEFAULT 0 COMMENT 'エラー数',
    
    -- エラー情報
    error_message TEXT COMMENT 'エラーメッセージ',
    
    -- メタ情報
    memory_usage DECIMAL(10,2) COMMENT 'メモリ使用量（MB）',
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_started_at (started_at),
    INDEX idx_status (status),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='スクレイピング実行ログ';

-- ============================================
-- 3. 機能フラグ追加
-- ============================================
INSERT INTO tenant_features (tenant_id, feature_code, is_enabled, created_at)
SELECT id, 'diary_scrape', 0, NOW()
FROM tenants
WHERE NOT EXISTS (
    SELECT 1 FROM tenant_features 
    WHERE tenant_id = tenants.id 
    AND feature_code = 'diary_scrape'
);
```

**✅ 成功メッセージが表示されればOK**

---

### ステップ2: 各テナントDBにdiary_postsテーブルを作成

**✨ 新規テナント作成時は自動作成されるので、このステップは不要です！**

既存テナントの場合のみ、以下の方法で作成してください。

#### 方法A: ブラウザで一括作成（推奨・簡単）

1. ブラウザで以下のURLにアクセス:
   ```
   http://あなたのドメイン/admin/tenants/init_diary_all.php
   ```

2. 自動的に全テナントのテーブルとディレクトリが作成されます

3. 完了メッセージが表示されればOK

#### 方法B: 各テナントDBで手動実行

**テナントDB毎に以下を繰り返してください:**

1. 左側から **テナントDB（例: `tenant_houman`）** をクリック
2. 上部メニューの **「SQL」** タブをクリック
3. 以下のSQLを貼り付けて **「実行」** をクリック

```sql
CREATE TABLE IF NOT EXISTS diary_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pd_id BIGINT NOT NULL COMMENT 'CityHeavenの投稿ID',
    cast_id INT COMMENT 'キャストID（cast_dataテーブル）',
    
    -- 投稿情報
    title VARCHAR(500) COMMENT 'タイトル',
    writer_name VARCHAR(100) NOT NULL COMMENT '投稿者名',
    posted_at DATETIME NOT NULL COMMENT '投稿日時',
    
    -- メディア情報
    thumb_url VARCHAR(500) COMMENT 'サムネイル画像URL',
    video_url VARCHAR(500) COMMENT '動画URL',
    poster_url VARCHAR(500) COMMENT '動画ポスター画像URL',
    has_video TINYINT(1) DEFAULT 0 COMMENT '動画有無',
    
    -- 本文
    html_body TEXT COMMENT '本文HTML',
    content_hash VARCHAR(64) COMMENT '本文ハッシュ値（重複チェック用）',
    
    -- メタ情報
    detail_url VARCHAR(500) COMMENT '詳細ページURL',
    is_my_girl_limited TINYINT(1) DEFAULT 0 COMMENT 'マイガール限定投稿',
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_pd_id (pd_id),
    INDEX idx_cast_id (cast_id),
    INDEX idx_posted_at (posted_at),
    INDEX idx_writer_name (writer_name),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (cast_id) REFERENCES cast_data(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='写メ日記投稿データ';
```

**✅ 各テナントDBで成功メッセージが表示されればOK**

**例**: 
- `tenant_houman` で実行 → OK
- `tenant_jyukujyo` で実行 → OK
- `tenant_xxx` で実行 → OK

---

### ステップ3: アップロードディレクトリ作成

#### 方法A: Finderで作成（Mac）

1. Finderを開く
2. `/Users/nasumac_mini/Desktop/pullcass/www/uploads/` に移動
3. 新規フォルダ作成: `diary`
4. `diary` フォルダ内に以下の構造を作成:

```
diary/
├── 1/
│   ├── thumbs/
│   ├── images/
│   ├── videos/
│   └── deco/
├── 2/
│   ├── thumbs/
│   ├── images/
│   ├── videos/
│   └── deco/
├── 3/
│   ├── thumbs/
│   ├── images/
│   ├── videos/
│   └── deco/
...（テナント数分）
```

#### 方法B: ターミナルで一括作成（推奨）

ターミナル.appを開いて以下を実行:

```bash
cd /Users/nasumac_mini/Desktop/pullcass/www

# テナントID 1-10 のディレクトリを作成
for i in {1..10}; do
    mkdir -p uploads/diary/$i/thumbs
    mkdir -p uploads/diary/$i/images
    mkdir -p uploads/diary/$i/videos
    mkdir -p uploads/diary/$i/deco
done

# 権限設定
chmod -R 755 uploads/diary
```

---

### ステップ4: 暗号化キーの設定

#### 4-1. 暗号化キーを生成

ターミナル.appを開いて以下を実行:

```bash
# 32文字のキー生成
php -r "echo bin2hex(random_bytes(16)) . PHP_EOL;"

# 16文字のIV生成
php -r "echo bin2hex(random_bytes(8)) . PHP_EOL;"
```

**出力例**:
```
a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6  ← これをコピー（KEYとして使用）
q1w2e3r4t5y6u7i8                  ← これをコピー（IVとして使用）
```

#### 4-2. .envファイルに追加

1. テキストエディタで `/Users/nasumac_mini/Desktop/pullcass/www/.env` を開く
2. ファイルの最後に以下を追加（上で生成した値を使用）:

```env
# 写メ日記スクレイピング - 暗号化設定
DIARY_ENCRYPTION_KEY=a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6
DIARY_ENCRYPTION_IV=q1w2e3r4t5y6u7i8
```

3. 保存

---

### ステップ5: 確認

#### 5-1. phpMyAdminでテーブル確認

**プラットフォームDB確認**:
1. 左側から `pullcass_platform` をクリック
2. 以下のテーブルが表示されていればOK:
   - ✅ `diary_scrape_settings`
   - ✅ `diary_scrape_logs`

**テナントDB確認**:
1. 左側から各テナントDB（例: `tenant_houman`）をクリック
2. 以下のテーブルが表示されていればOK:
   - ✅ `diary_posts`

#### 5-2. ディレクトリ確認

Finderで `/Users/nasumac_mini/Desktop/pullcass/www/uploads/diary/` を開いて、以下のフォルダがあればOK:

```
diary/
├── 1/
├── 2/
├── 3/
...
```

---

## ✅ 完了チェックリスト

- [ ] ステップ1: プラットフォームDBにテーブル作成完了
  - [ ] `diary_scrape_settings` テーブル作成
  - [ ] `diary_scrape_logs` テーブル作成
  - [ ] `tenant_features` に機能フラグ追加

- [ ] ステップ2: 各テナントDBにdiary_postsテーブル作成完了
  - [ ] `tenant_houman` に作成
  - [ ] `tenant_jyukujyo` に作成
  - [ ] その他のテナントDBに作成

- [ ] ステップ3: アップロードディレクトリ作成完了

- [ ] ステップ4: 暗号化キー設定完了

- [ ] ステップ5: 確認完了

---

## ❌ エラーが出た場合

### エラー1: `Foreign key constraint fails`

**原因**: `tenants` テーブルまたは `cast_data` テーブルが存在しない

**解決方法**:
- `tenants` テーブルがない場合: プラットフォームDBのセットアップが必要
- `cast_data` テーブルがない場合: 外部キー制約の行を削除してテーブル作成

```sql
-- 外部キー制約なしバージョン
CREATE TABLE IF NOT EXISTS diary_posts (
    -- ... 他のカラムは同じ ...
    
    -- FOREIGN KEY行を削除またはコメントアウト
    -- FOREIGN KEY (cast_id) REFERENCES cast_data(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### エラー2: `Table already exists`

**原因**: テーブルが既に存在する

**解決方法**:
- 問題ありません。`CREATE TABLE IF NOT EXISTS` なので既存テーブルはスキップされます

### エラー3: `.env` ファイルが見つからない

**原因**: .envファイルが存在しない

**解決方法**:
1. `/Users/nasumac_mini/Desktop/pullcass/www/` に `.env` ファイルを新規作成
2. 必要な設定を記述

---

## 📞 次のステップ

セットアップ完了後、以下を確認してください:

### phpMyAdminで確認

```sql
-- プラットフォームDB
USE pullcass_platform;

-- テーブル一覧
SHOW TABLES;

-- 機能フラグ確認
SELECT t.id, t.name, tf.feature_code, tf.is_enabled
FROM tenants t
LEFT JOIN tenant_features tf ON t.id = tf.tenant_id
WHERE tf.feature_code = 'diary_scrape';
```

---

**セットアップ完了後、管理画面の実装に進みます！**
