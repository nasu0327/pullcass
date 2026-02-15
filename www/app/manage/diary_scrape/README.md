# 写メ日記スクレイピング機能

## 概要

CityHeavenから写メ日記を自動取得し、プルキャスのデータベースに保存する機能です。

## ファイル構成

```
diary_scrape/
├── index.php           # メイン管理画面（設定モーダル含む）
├── execute.php         # 手動実行API
├── worker.php          # バックグラウンドワーカー
├── toggle.php          # 定期実行ON/OFF切替API
├── status.php          # 進捗確認API
├── stop.php            # 停止API
├── includes/
│   └── scraper.php     # スクレイパークラス
└── README.md           # このファイル

cron/
└── diary_scrape_cron.php  # 定期実行スクリプト（毎分cron実行）
```

## セットアップ

### 1. データベース

以下のSQLを実行してください（既に実行済みの場合はスキップ）：

```bash
/Users/nasumac_mini/Desktop/pullcass/sql/diary_scrape_tables_FINAL.sql
```

### 2. アップロードディレクトリ

テナントごとのアップロードディレクトリが必要です。
新規テナント作成時は自動で作成されますが、既存テナントは手動作成が必要です。

```bash
# SSH接続
cd /var/www/pullcass/www

# テナント1,2,3のディレクトリ作成
sudo mkdir -p uploads/diary/{1,2,3}/{thumbs,images,videos,deco}
sudo chmod -R 755 uploads/diary
sudo chown -R www-data:www-data uploads/diary
```

### 3. 動作確認

ブラウザで以下にアクセスして動作確認：

```
https://テナントドメイン/app/manage/diary_scrape/test.php?tenant=テナントコード
```

全テストが成功すれば準備完了です。

## 使い方

### 1. 設定

1. 管理画面メニューから「写メ日記スクレイピング」をクリック
2. 「⚙️ 設定」ボタンをクリック
3. 以下を入力：
   - **ログインID**: CityHeavenのメールアドレス
   - **パスワード**: CityHeavenのパスワード
   - **写メ日記ページURL**: 店舗の写メ日記ページURL
     - 例: `https://www.cityheaven.net/fukuoka/A4001/A400101/houmantengoku/diarylist/`
4. 「💾 保存」をクリック

### 2. 手動実行

1. 管理画面で「▶️ 手動実行」ボタンをクリック
2. 進捗が表示されます
3. 完了すると取得件数が表示されます

### 3. 定期実行（自動取得）

1. 「定期実行 開始」ボタンをクリック
2. 10分おきに自動取得されます（各テナントの最終実行時刻基準で分散実行）
3. 「定期実行 停止」ボタンで停止可能

#### crontab設定（サーバー側で1回だけ設定）

```bash
* * * * * /usr/bin/php /var/www/pullcass/www/cron/diary_scrape_cron.php >> /var/log/pullcass_diary_scrape.log 2>&1
```

## 仕様

### データ保存

- **保存先**: `pullcass.diary_posts`テーブル（プラットフォームDB）
- **テナント分離**: `tenant_id`で管理
- **キャスト紐付け**: `cast_id`でテナントDBの`cast_data`と紐付け
- **重複チェック**: `pd_id`（CityHeavenの投稿ID）で重複を防止

### キャスト名マッチング

- テナントDBの`cast_data`テーブルから`name`でマッチング
- ステータスが`active`のキャストのみ対象
- マッチしないキャスト名の投稿はスキップ

### データ削除

- テナント削除時: 自動削除（`ON DELETE CASCADE`）
- 最大件数超過時: 古い投稿から自動削除
- キャスト削除/非表示: アプリケーション側でフィルタリング

## トラブルシューティング

### ログインできない

1. CityHeavenのログイン情報が正しいか確認
2. CityHeavenのサイトで直接ログインできるか確認
3. パスワードを変更した場合は設定を更新

### 投稿が取得されない

1. 店舗URLが正しいか確認
2. キャスト名がテナントDBの`cast_data`と一致しているか確認
3. `test.php`で動作確認を実行

### エラーが発生する

1. 実行履歴でエラーメッセージを確認
2. `test.php`で各項目をチェック
3. PHPのエラーログを確認

## 開発メモ

### 参考実装

`/Users/nasumac_mini/Desktop/pullcass/reference/public_html/admin/diary_scrape/`の実装を参考にしています。

### 主な変更点

1. **マルチテナント対応**
   - 設定をテナントごとに管理
   - データを`tenant_id`で分離

2. **データベース設計**
   - `diary_posts`をプラットフォームDBに集約
   - `cast_id`で直接紐付け

3. **ファイル保存**
   - テナントごとにディレクトリ分離
   - パス: `uploads/diary/{tenant_id}/`

### 今後の拡張

- [x] 本文取得（diary_detailクラスから抽出）
- [x] 画像・動画のローカルダウンロード保存
- [x] フロントエンド表示機能
- [x] 定期実行（cron）— 毎分チェック、10分間隔で分散実行
- [x] キャスト単位のデータ保持制限（500件/キャスト）
- [ ] パスワード暗号化
- [ ] エラー通知機能

## ライセンス

pullcass内部機能
