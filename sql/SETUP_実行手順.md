# 写メ日記スクレイピング機能 - 実行手順（まとめ）

**実行日**: 2026-02-14

---

## 🚀 実行するコマンド（この順番で実行してください）

### ステップ1: プラットフォームDBにテーブル作成

ターミナルで以下を実行:

```bash
cd /Users/nasumac_mini/Desktop/pullcass

mysql -u root -p pullcass_platform < sql/diary_scrape_tables.sql
```

**パスワードを聞かれたら入力してください。**

**作成されるもの**:
- `diary_scrape_settings` テーブル
- `diary_scrape_logs` テーブル
- `tenant_features` に `diary_scrape` 機能フラグ追加

---

### ステップ2: 全テナントDBにdiary_postsテーブルを作成

ターミナルで以下を実行:

```bash
cd /Users/nasumac_mini/Desktop/pullcass

php sql/migrate_diary_posts_all_tenants.php
```

**実行結果例**:
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

===========================================
マイグレーション完了
===========================================
✅ 成功: 3件
⏭️  スキップ: 0件
❌ エラー: 0件
```

---

### ステップ3: アップロードディレクトリ作成

ターミナルで以下を実行:

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

### ステップ4: 暗号化キーの生成と設定

#### 4-1. 暗号化キーを生成

ターミナルで以下を実行:

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

```bash
cd /Users/nasumac_mini/Desktop/pullcass/www

# .envファイルを編集
nano .env
```

ファイルの最後に以下を追加（上で生成した値を使用）:

```env
# 写メ日記スクレイピング - 暗号化設定
DIARY_ENCRYPTION_KEY=a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6
DIARY_ENCRYPTION_IV=q1w2e3r4t5y6u7i8
```

保存して終了（Ctrl+O → Enter → Ctrl+X）

---

### ステップ5: 確認

#### 5-1. テーブル作成確認

```bash
mysql -u root -p
```

MySQLにログイン後:

```sql
-- プラットフォームDBのテーブル確認
USE pullcass_platform;
SHOW TABLES LIKE 'diary_scrape%';

-- 結果（2つ表示されればOK）:
-- diary_scrape_settings
-- diary_scrape_logs

-- テナントDBのテーブル確認（例: tenant_houman）
USE tenant_houman;
SHOW TABLES LIKE 'diary_posts';

-- 結果（1つ表示されればOK）:
-- diary_posts

-- 終了
exit;
```

#### 5-2. ディレクトリ確認

```bash
ls -la /Users/nasumac_mini/Desktop/pullcass/www/uploads/diary/
```

**結果例**:
```
drwxr-xr-x  12 user  staff   384  2 14 10:00 1
drwxr-xr-x  12 user  staff   384  2 14 10:00 2
drwxr-xr-x  12 user  staff   384  2 14 10:00 3
...
```

---

## ✅ 完了チェックリスト

- [ ] ステップ1: プラットフォームDBにテーブル作成完了
- [ ] ステップ2: 全テナントDBにdiary_postsテーブル作成完了
- [ ] ステップ3: アップロードディレクトリ作成完了
- [ ] ステップ4: 暗号化キー設定完了
- [ ] ステップ5: 確認完了

---

## ❌ エラーが出た場合

### エラー1: `mysql: command not found`

**原因**: MySQLのパスが通っていない

**解決方法**:
```bash
# MySQLのパスを確認
which mysql

# パスが表示されない場合は、フルパスで実行
/usr/local/mysql/bin/mysql -u root -p pullcass_platform < sql/diary_scrape_tables.sql
```

### エラー2: `Access denied for user 'root'@'localhost'`

**原因**: パスワードが間違っている

**解決方法**:
- 正しいMySQLのrootパスワードを入力してください

### エラー3: `Unknown database 'pullcass_platform'`

**原因**: プラットフォームDBが存在しない

**解決方法**:
```bash
mysql -u root -p

# MySQL内で実行
CREATE DATABASE pullcass_platform;
exit;

# 再度実行
mysql -u root -p pullcass_platform < sql/diary_scrape_tables.sql
```

### エラー4: `Cannot add foreign key constraint`

**原因**: cast_dataテーブルが存在しない

**解決方法**:
- 各テナントDBにcast_dataテーブルを先に作成してください
- または、外部キー制約を一時的に削除してテーブル作成

### エラー5: PHPスクリプトでエラー

**原因**: bootstrap.phpが見つからない

**解決方法**:
```bash
# パスを確認
ls -la /Users/nasumac_mini/Desktop/pullcass/www/includes/bootstrap.php

# 存在しない場合は、スクリプトのパスを修正
```

---

## 📞 サポート

エラーが解決しない場合は、以下の情報を教えてください:

1. どのステップでエラーが出たか
2. エラーメッセージの全文
3. 実行したコマンド

---

**セットアップ完了後、次は管理画面の実装に進みます！**
