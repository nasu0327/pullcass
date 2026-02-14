# 写メ日記スクレイピング機能 - セットアップ手順

**作成日**: 2026-02-14

---

## 📋 セットアップ手順

### Step 1: プラットフォームDBにテーブル作成

```bash
# MySQLにログイン
mysql -u root -p

# SQLファイルを実行
source /path/to/pullcass/sql/diary_scrape_tables.sql
```

または、直接実行:

```bash
mysql -u root -p pullcass_platform < sql/diary_scrape_tables.sql
```

**作成されるテーブル**:
- `diary_scrape_settings` - スクレイピング設定
- `diary_scrape_logs` - 実行ログ
- `tenant_features` - 機能フラグ（diary_scrape追加）

---

### Step 2: 全テナントDBにdiary_postsテーブルを作成

#### 方法A: PHPスクリプトで一括作成（推奨）

```bash
cd /path/to/pullcass
php sql/migrate_diary_posts_all_tenants.php
```

**出力例**:
```
===========================================
写メ日記テーブル一括マイグレーション
===========================================

対象テナント数: 3

-------------------------------------------
テナント: 豊満天国 (ID: 1, Code: houman)
DB: tenant_houman
✅ diary_postsテーブル作成成功
-------------------------------------------
テナント: 熟女クラブ (ID: 2, Code: jyukujyo)
DB: tenant_jyukujyo
✅ diary_postsテーブル作成成功
-------------------------------------------

===========================================
マイグレーション完了
===========================================
✅ 成功: 3件
⏭️  スキップ: 0件
❌ エラー: 0件
```

#### 方法B: 手動で各テナントDBに作成

各テナントのデータベースで以下を実行:

```sql
USE tenant_houman;  -- テナントのDB名に変更

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

### Step 3: アップロードディレクトリ作成

```bash
cd /path/to/pullcass/www

# テナント別ディレクトリ作成（例: テナントID=1）
mkdir -p uploads/diary/1/thumbs
mkdir -p uploads/diary/1/images
mkdir -p uploads/diary/1/videos
mkdir -p uploads/diary/1/deco

# 権限設定
chmod -R 755 uploads/diary
chown -R www-data:www-data uploads/diary  # Apacheユーザーに変更
```

または、全テナント分を一括作成:

```bash
# テナントID 1-10の場合
for i in {1..10}; do
    mkdir -p uploads/diary/$i/{thumbs,images,videos,deco}
done

chmod -R 755 uploads/diary
chown -R www-data:www-data uploads/diary
```

---

### Step 4: 暗号化キーの設定

`.env`ファイルに暗号化キーを追加:

```bash
# .envファイルを編集
nano /path/to/pullcass/www/.env
```

以下を追加:

```env
# 写メ日記スクレイピング - 暗号化設定
DIARY_ENCRYPTION_KEY=your-32-character-encryption-key-here
DIARY_ENCRYPTION_IV=your-16-character-iv-here
```

暗号化キーの生成:

```bash
# 32文字のキー生成
php -r "echo bin2hex(random_bytes(16)) . PHP_EOL;"

# 16文字のIV生成
php -r "echo bin2hex(random_bytes(8)) . PHP_EOL;"
```

---

### Step 5: 機能の有効化（オプション）

特定のテナントで機能を有効化する場合:

```sql
USE pullcass_platform;

-- テナントID=1で有効化
UPDATE tenant_features 
SET is_enabled = 1 
WHERE tenant_id = 1 AND feature_code = 'diary_scrape';
```

---

## ✅ セットアップ確認

### 1. テーブル作成確認

```sql
-- プラットフォームDB
USE pullcass_platform;
SHOW TABLES LIKE 'diary_scrape%';

-- テナントDB
USE tenant_houman;
SHOW TABLES LIKE 'diary_posts';
```

### 2. 機能フラグ確認

```sql
USE pullcass_platform;

SELECT 
    t.id AS tenant_id,
    t.name AS tenant_name,
    tf.feature_code,
    tf.is_enabled
FROM tenants t
LEFT JOIN tenant_features tf ON t.id = tf.tenant_id 
WHERE tf.feature_code = 'diary_scrape'
ORDER BY t.id;
```

### 3. ディレクトリ確認

```bash
ls -la /path/to/pullcass/www/uploads/diary/
```

---

## 🔧 トラブルシューティング

### エラー: テーブルが作成できない

**原因**: 外部キー制約エラー（cast_dataテーブルが存在しない）

**解決方法**:
```sql
-- cast_dataテーブルの存在確認
USE tenant_houman;
SHOW TABLES LIKE 'cast_data';

-- 存在しない場合は、外部キー制約なしで作成
CREATE TABLE diary_posts (...) -- FOREIGN KEY行を削除
```

### エラー: ディレクトリに書き込めない

**原因**: 権限不足

**解決方法**:
```bash
# 権限確認
ls -la /path/to/pullcass/www/uploads/

# 権限修正
sudo chown -R www-data:www-data /path/to/pullcass/www/uploads/diary
sudo chmod -R 755 /path/to/pullcass/www/uploads/diary
```

### エラー: 暗号化キーが見つからない

**原因**: .envファイルに設定されていない

**解決方法**:
```bash
# .envファイルを確認
cat /path/to/pullcass/www/.env | grep DIARY_ENCRYPTION

# 設定を追加
echo "DIARY_ENCRYPTION_KEY=your-key-here" >> /path/to/pullcass/www/.env
echo "DIARY_ENCRYPTION_IV=your-iv-here" >> /path/to/pullcass/www/.env
```

---

## 📊 次のステップ

セットアップ完了後:

1. **管理画面にアクセス**
   - `/app/manage/diary_scrape/`（実装後）

2. **スクレイピング設定**
   - CityHeavenログイン情報を入力
   - 店舗URLを設定
   - 自動取得をONに設定

3. **テスト実行**
   - 手動実行ボタンでテスト
   - ログを確認

---

## 📝 関連ドキュメント

- [設計書](../docs/写メ日記スクレイピング_設計書.md)
- [DB構造分析](../docs/参考サイトDB構造_分析.md)

---

**セットアップ完了**: 2026-02-14
