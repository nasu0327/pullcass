# テナント毎のDB作成について

## 🎯 結論：自動作成されます！

**新規テナント作成時に、自動的に`diary_posts`テーブルとアップロードディレクトリが作成されます。**

手動でDBを実行する必要はありません。

---

## 📋 仕組み

### 新規テナント作成時（自動）

`/admin/tenants/create.php` でテナントを作成すると、以下が自動実行されます：

1. ✅ テナントDBに`diary_posts`テーブル作成
2. ✅ `/uploads/diary/{tenant_id}/` ディレクトリ作成
   - `thumbs/`
   - `images/`
   - `videos/`
   - `deco/`

**何もする必要はありません！**

---

## 🔧 既存テナントの場合

既にテナントが存在する場合は、以下の方法で一括作成できます。

### 方法1: ブラウザで一括作成（推奨）

1. ブラウザで以下にアクセス:
   ```
   http://あなたのドメイン/admin/tenants/init_diary_all.php
   ```

2. 自動的に全テナント分が作成されます

3. 実行結果が表示されます:
   ```
   ===========================================
   写メ日記機能一括初期化
   ===========================================
   
   対象テナント数: 3
   
   -------------------------------------------
   テナント: 豊満天国 (ID: 1, Code: houman)
   DB: tenant_houman
   ✅ diary_postsテーブル作成成功
   ✅ アップロードディレクトリ作成成功
   -------------------------------------------
   
   【テーブル作成】
   ✅ 成功: 3件
   ⏭️  スキップ: 0件
   ❌ エラー: 0件
   
   【ディレクトリ作成】
   ✅ 成功: 3件
   ⏭️  スキップ: 0件
   ❌ エラー: 0件
   ```

### 方法2: ターミナルで実行

```bash
cd /Users/nasumac_mini/Desktop/pullcass
php www/admin/tenants/init_diary_all.php
```

### 方法3: phpMyAdminで手動実行

各テナントDBで以下のSQLを実行:

```sql
CREATE TABLE IF NOT EXISTS diary_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pd_id BIGINT NOT NULL,
    cast_id INT,
    title VARCHAR(500),
    writer_name VARCHAR(100) NOT NULL,
    posted_at DATETIME NOT NULL,
    thumb_url VARCHAR(500),
    video_url VARCHAR(500),
    poster_url VARCHAR(500),
    has_video TINYINT(1) DEFAULT 0,
    html_body TEXT,
    content_hash VARCHAR(64),
    detail_url VARCHAR(500),
    is_my_girl_limited TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_pd_id (pd_id),
    INDEX idx_cast_id (cast_id),
    INDEX idx_posted_at (posted_at),
    FOREIGN KEY (cast_id) REFERENCES cast_data(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 💡 実装の詳細

### 自動作成の仕組み

#### 1. 初期化関数（`includes/diary_init.php`）

```php
// テーブル作成
initDiaryPostsTable($tenantId);

// ディレクトリ作成
initDiaryUploadDirectories($tenantId);
```

#### 2. テナント作成時に自動実行（`admin/tenants/create.php`）

```php
// 写メ日記機能の初期化
require_once __DIR__ . '/../../includes/diary_init.php';
$diaryResults = initDiaryScrapeFeature($tenantId);
```

---

## ✅ 確認方法

### phpMyAdminで確認

1. 各テナントDBを開く
2. `diary_posts` テーブルが存在するか確認

### ディレクトリ確認

Finderで以下を確認:
```
/Users/nasumac_mini/Desktop/pullcass/www/uploads/diary/
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
...
```

---

## 🎉 まとめ

- ✅ **新規テナント**: 自動作成されるので何もしなくてOK
- ✅ **既存テナント**: ブラウザで `/admin/tenants/init_diary_all.php` にアクセスするだけ
- ✅ **テナントが増えても**: 自動作成されるので安心

**もう手動でDBを実行する必要はありません！**
