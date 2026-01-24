# pullcass（プルキャス）

デリヘル向けマルチテナントシステム

## 📋 概要

pullcassは、複数のデリヘル店舗を1つのシステムで管理できるマルチテナントWebアプリケーションです。

## 🚀 クイックスタート

### 1. 環境変数の設定

```bash
# .envファイルを作成
cp .env.example .env

# 必要に応じて.envを編集
```

**.env ファイルの内容（例）:**

```env
# アプリケーション設定
APP_ENV=development
APP_DEBUG=true
APP_NAME=pullcass

# Nginxポート
NGINX_PORT=8080

# MySQL設定
MYSQL_PORT=3306
MYSQL_ROOT_PASSWORD=root_secret

# データベース設定
DB_DATABASE=pullcass
DB_USERNAME=pullcass
DB_PASSWORD=pullcass_secret

# phpMyAdminポート
PMA_PORT=8081
```

### 2. Dockerコンテナの起動

```bash
docker-compose up -d
```

### 3. アクセス

| URL | 説明 |
|-----|------|
| http://localhost:8080 | プラットフォームトップ |
| http://localhost:8080/admin/ | スーパー管理画面 |
| http://localhost:8081 | phpMyAdmin |

### デフォルトログイン情報

- **ユーザー名:** admin
- **パスワード:** admin123

## 📁 ディレクトリ構成

```
pullcass/
├── docker/                 # Docker設定
│   ├── mysql/init/        # MySQL初期化SQL
│   ├── nginx/             # Nginx設定
│   └── php/               # PHP設定
├── docs/                   # ドキュメント
├── reference/              # 参考ファイル（現行システム）
├── www/                    # アプリケーション本体
│   ├── admin/             # スーパー管理画面
│   ├── app/
│   │   ├── front/         # フロントページ（テナント別）
│   │   └── manage/        # 店舗管理画面（テナント別）
│   └── includes/          # 共通機能
├── docker-compose.yml
└── README.md
```

## 🔑 3つの画面

### 1. スーパー管理画面 (`/admin/`)
- 新規店舗の登録・削除
- 店舗一覧・ステータス管理
- システム全体の設定

### 2. 店舗管理画面 (`/app/manage/`)
- キャスト管理
- スケジュール管理
- 料金管理
- テーマ設定

### 3. フロントページ (`/app/front/` または店舗ドメイン)
- トップページ
- キャスト一覧・詳細
- スケジュール表示
- 料金表

## 🏪 マルチテナント仕組み

テナント（店舗）の判別は以下の優先順位で行われます：

1. **サブドメイン**: `houman.pullcass.com` → houman
2. **カスタムドメイン**: `club-houman.com` → houman
3. **URLパス**: `/houman/` → houman（開発用）

## 🛠 開発コマンド

```bash
# コンテナ起動
docker-compose up -d

# コンテナ停止
docker-compose down

# ログ確認
docker-compose logs -f

# PHPコンテナに入る
docker-compose exec php bash

# MySQLに接続
docker-compose exec mysql mysql -u pullcass -p pullcass
```

## 📌 バージョン

### v1.1.0 (Refactoring & Features)
- [x] キャスト管理システムの統合 (`tenant_casts` テーブルへの一本化)
- [x] ランキング機能の実装 (リピート率・注目度集計)
- [x] トップページデザインのリファレンス準拠改修
- [x] スクレイピング処理の改善 (ID固定化・排他制御)

## 🔄 キャストデータ管理の仕組み

本システムでは、外部サイトからのスクレイピングデータを `tenant_casts` テーブルに統合して管理しています。

1. **スクレイピング**: 各種ソース（駅チカ・Heaven等）からデータを取得
2. **データ同期**: 取得したデータを `tenant_casts` に同期（IDを維持しつつ情報を更新）
3. **フロントエンド表示**: 常に `tenant_casts` を参照するため、ソースを切り替えてもURLが変わらない

## 📄 ライセンス

Proprietary - All Rights Reserved
