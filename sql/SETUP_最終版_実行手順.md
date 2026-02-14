# 写メ日記スクレイピング機能 - セットアップ手順（最終版）

**作成日**: 2026-02-14  
**DB設計**: プラットフォームDB一元管理 + cast_id管理

---

## 🎯 重要な変更点

### ✅ テナントDB不要！

**diary_postsテーブルはプラットフォームDBで一元管理されます。**

- ❌ 各テナントDBにテーブル作成 **不要**
- ✅ プラットフォームDBのみで完結
- ✅ テナント毎のDB実行 **不要**

---

## 📋 実行手順

### ステップ1: プラットフォームDBにテーブル作成

#### phpMyAdminで実行

1. phpMyAdminにログイン

2. 左側のデータベース一覧から **`pullcass_platform`** をクリック

3. 上部メニューの **「SQL」** タブをクリック

4. 以下のファイルの内容をコピーして貼り付け:
   ```
   sql/diary_scrape_tables_FINAL.sql
   ```

5. **「実行」** をクリック

6. ✅ 成功メッセージが表示されればOK

**作成されるテーブル**:
- ✅ `diary_posts` - 写メ日記投稿データ（全テナント共通）
- ✅ `diary_scrape_settings` - スクレイピング設定
- ✅ `diary_scrape_logs` - 実行ログ
- ✅ `tenant_features` - 機能フラグ（diary_scrape追加）

---

### ステップ2: アップロードディレクトリ作成

#### Finderで作成（Mac）

1. Finderを開く

2. `/Users/nasumac_mini/Desktop/pullcass/www/uploads/` に移動

3. 新規フォルダ作成: `diary`

4. `diary` フォルダ内に以下の構造を作成:

```
diary/
├── 1/          ← テナントID
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

#### ターミナルで一括作成（推奨）

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

### ステップ3: 暗号化キーの設定

#### 3-1. 暗号化キーを生成

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

#### 3-2. .envファイルに追加

1. テキストエディタで `/Users/nasumac_mini/Desktop/pullcass/www/.env` を開く

2. ファイルの最後に以下を追加（上で生成した値を使用）:

```env
# 写メ日記スクレイピング - 暗号化設定
DIARY_ENCRYPTION_KEY=a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6
DIARY_ENCRYPTION_IV=q1w2e3r4t5y6u7i8
```

3. 保存

---

### ステップ4: 確認

#### phpMyAdminで確認

1. 左側から `pullcass_platform` をクリック

2. 以下のテーブルが表示されていればOK:
   - ✅ `diary_posts`
   - ✅ `diary_scrape_settings`
   - ✅ `diary_scrape_logs`

3. SQLタブで以下を実行して機能フラグを確認:

```sql
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

#### ディレクトリ確認

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
  - [ ] `diary_posts` テーブル作成
  - [ ] `diary_scrape_settings` テーブル作成
  - [ ] `diary_scrape_logs` テーブル作成
  - [ ] `tenant_features` に機能フラグ追加

- [ ] ステップ2: アップロードディレクトリ作成完了

- [ ] ステップ3: 暗号化キー設定完了

- [ ] ステップ4: 確認完了

---

## 🎉 セットアップ完了！

これで写メ日記スクレイピング機能のデータベース準備が完了しました。

### 次のステップ

1. **管理画面の実装**
   - スクレイピング設定画面
   - 手動実行機能
   - ログ表示

2. **スクレイピング機能の実装**
   - ログイン処理
   - データ取得
   - DB保存

3. **フロント表示の実装**
   - 写メ日記一覧表示
   - キャスト連動

---

## 📊 DB設計の特徴

### プラットフォームDB一元管理

```
pullcass_platform
└── diary_posts（全テナント共通）
    ├── tenant_id（テナント識別）
    ├── cast_id（キャスト識別）
    └── cast_name（表示用スナップショット）
```

### メリット

1. ✅ **テナント削除時に自動削除**
   - `ON DELETE CASCADE`で自動対応

2. ✅ **キャスト連動**
   - キャスト削除/非表示 → 写メ日記も自動非表示
   - キャスト再表示 → 写メ日記も自動再表示

3. ✅ **メンテナンス性**
   - スキーマ変更が1回で済む
   - 横断検索・統計が容易

4. ✅ **データ量制限**
   - テナント毎に最大保持件数を設定可能

---

## ❌ エラーが出た場合

### エラー1: `Foreign key constraint fails`

**原因**: `tenants` テーブルが存在しない

**解決方法**:
- プラットフォームDBのセットアップを先に完了してください

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

**セットアップ完了後、管理画面とスクレイピング機能の実装に進みます！**
