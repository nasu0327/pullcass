# メニュー管理システム - 実装完了ガイド

## 📋 実装内容

メニュー管理システムの実装が完了しました！以下のファイルが作成されています：

### データベース

- `database/migrations/create_menu_items_table.sql` - テーブル作成とデフォルトデータ挿入SQL

### 管理画面

- `www/app/manage/menu_management/index.php` - メニュー管理画面
- `www/app/manage/menu_management/includes/menu_functions.php` - メニュー操作関数
- `www/app/manage/menu_management/save_order.php` - 並び順保存API
- `www/app/manage/menu_management/save_menu.php` - メニュー保存API
- `www/app/manage/menu_management/delete_menu.php` - メニュー削除API
- `www/app/manage/menu_management/toggle_status.php` - ステータス切り替えAPI
- `www/app/manage/includes/header.php` - サイドメニューに「メニュー管理」を追加済み

### フロントエンド

- `www/app/front/includes/popup_menu.php` - ポップアップメニューコンポーネント
- `www/app/front/includes/popup_menu_styles.php` - ポップアップメニューのCSS

---

## 🚀 セットアップ手順

### ステップ 1: データベースのセットアップ

1. phpMyAdminにログイン
2. pullcassデータベースを選択
3. `database/migrations/create_menu_items_table.sql` の内容をコピー
4. SQLタブで実行

これにより以下が実行されます：

- `menu_items` テーブルの作成
- 既存の全テナントに対してデフォルトメニュー（5件）を自動挿入

### ステップ 2: フロントエンドへの統合

各ページテンプレートファイル（index.php, top.php など）に以下を追加してください：

#### 2-1. ヘッダー内にスタイルを追加

`<head>`セクション内、または既存のCSS読み込み後に：

```php
<!-- ハンバーガーメニューのスタイル -->
<style>
<?php include __DIR__ . '/includes/popup_menu_styles.php'; ?>
</style>
```

#### 2-2. body終了タグ直前にメニューコンポーネントを追加

`</body>`の直前に：

```php
<!-- ハンバーガーメニュー -->
<?php include __DIR__ . '/includes/popup_menu.php'; ?>

</body>
```

### ステップ 3: 既存ページの例

例えば `www/app/front/top.php` の場合：

```php
<?php
// 既存のコード...
?>
<!DOCTYPE html>
<html>
<head>
    <!-- 既存のhead内容 -->
    
    <!-- ハンバーガーメニューのスタイル -->
    <style>
    <?php include __DIR__ . '/includes/popup_menu_styles.php'; ?>
    </style>
</head>
<body>
    <!-- ヘッダー -->
    <?php include __DIR__ . '/includes/header.php'; ?>
    
    <!-- ページコンテンツ -->
    <main>
        <!-- 既存のコンテンツ -->
    </main>
    
    <!-- フッター -->
    <?php include __DIR__ . '/includes/footer.php'; ?>
    
    <!-- ハンバーガーメニュー -->
    <?php include __DIR__ . '/includes/popup_menu.php'; ?>
</body>
</html>
```

---

## 🎨 管理画面での使い方

### メニュー管理画面へのアクセス

1. 管理画面にログイン: `https://pullcass.com/app/manage/?tenant=テナントコード`
2. サイドメニューの「情報更新」セクションにある「メニュー管理」をクリック

### メニュー項目の管理

#### 新しいメニューを追加

1. 「新しいメニューを追加」ボタンをクリック
2. フォームに入力：
   - **コード**（任意）: 英数字の識別子（例: CAST, BLOG）
   - **表示タイトル**（必須）: メニューに表示される名前（例: キャスト一覧）
   - **リンクタイプ**: 内部リンクまたは外部リンクを選択
   - **URL**（必須）:
     - 内部リンク: `/app/front/cast/list.php`
     - 外部リンク: `https://example.com`
   - **新しいタブで開く**: 必要に応じてチェック
   - **有効にする**: チェックを外すと非表示になります
3. 「保存」をクリック

#### メニューの並び替え

- ドラッグ&ドロップで自由に並び替え可能
- 並び替えは自動保存されます

#### メニューの編集

- 「編集」ボタンをクリックして内容を変更

#### メニューの削除

- 「削除」ボタンをクリック（確認ダイアログが表示されます）

#### メニューの表示/非表示切り替え

- 目のアイコンをクリックして有効/無効を切り替え
- 無効にしたメニューはフロントエンドに表示されません

---

## 🎯 主な機能

### ✅ 実装済み機能

1. **管理画面**
   - ドラッグ&ドロップによる並び替え
   - メニュー項目の追加・編集・削除
   - 有効/無効の切り替え
   - リアルタイムプレビュー

2. **フロントエンド**
   - データベースから動的にメニュー読み込み
   - 内部リンク/外部リンクの自動判定
   - テーマカラーの自動反映（CSS変数使用）
   - レスポンシブ対応
   - スムーズなアニメーション
   - キーボードナビゲーション（ESCキーで閉じる）

3. **マルチドメイン対応**
   - サブドメインでも独自ドメインでも動作
   - 相対パスによる柔軟なURL管理

### 🔐 セキュリティ

- テナントIDによるデータ分離
- 外部キー制約によるデータ整合性
- XSS対策（全出力にhtmlspecialchars適用）
- CSRF対策（認証チェック）

---

## 🔧 カスタマイズ

### テーマカラーの変更

メニューは自動的にテーマカラーを反映します。
テーマ設定で以下のCSS変数が使用されます：

- `--color-primary`: メインカラー（ホバー時のボーダーなど）
- `--color-text`: テキストカラー
- `--color-bg`: 背景色

### 背景画像の追加

`www/app/front/includes/popup_menu.php` の以下の部分を編集：

```php
// TODO: 背景画像はテナント設定または管理画面から設定できるようにする
$popupImages = [
    '/path/to/image1.jpg',
    '/path/to/image2.jpg',
    '/path/to/image3.jpg',
];
```

---

## 📝 今後の拡張案

1. **背景画像管理機能**
   - 管理画面からメニュー背景画像をアップロード・設定

2. **階層構造対応**
   - サブメニュー（ドロップダウン）の実装

3. **アイコン設定**
   - メニュー項目ごとにアイコンを設定可能に

4. **プレビュー機能**
   - 管理画面から公開前にメニューをプレビュー

5. **複数メニュー対応**
   - ヘッダー用、フッター用など複数のメニューを管理

---

## ❓ トラブルシューティング

### メニューが表示されない場合

1. データベースを確認

   ```sql
   SELECT * FROM menu_items WHERE tenant_id = テナントID;
   ```

2. ブラウザのコンソールでJavaScriptエラーを確認

3. `popup_menu.php` と `popup_menu_styles.php` が正しくインクルードされているか確認

### メニュー項目が保存されない場合

1. データベース接続を確認
2. ブラウザの開発者ツールでネットワークタブを確認
3. エラーログを確認（`error_log`）

---

## 📚 関連ファイル一覧

```
pullcass/
├── database/
│   └── migrations/
│       └── create_menu_items_table.sql
├── www/
│   ├── app/
│   │   ├── manage/
│   │   │   ├── includes/
│   │   │   │   └── header.php (更新済み)
│   │   │   └── menu_management/
│   │   │       ├── index.php
│   │   │       ├── save_order.php
│   │   │       ├── save_menu.php
│   │   │       ├── delete_menu.php
│   │   │       ├── toggle_status.php
│   │   │       └── includes/
│   │   │           └── menu_functions.php
│   │   └── front/
│   │       └── includes/
│   │           ├── popup_menu.php
│   │           └── popup_menu_styles.php
```

---

## ✅ 完了チェックリスト

- [ ] SQLスクリプトを実行
- [ ] 管理画面で「メニュー管理」が表示されることを確認
- [ ] メニュー項目の追加・編集・削除をテスト
- [ ] ドラッグ&ドロップでの並び替えをテスト
- [ ] フロントエンドページに `popup_menu.php` と `popup_menu_styles.php` をインクルード
- [ ] フロントエンドでメニューが正しく表示されることを確認
- [ ] 内部リンク/外部リンクが正しく動作することを確認
- [ ] レスポンシブデザインを確認（スマホ・タブレット）
- [ ] テーマカラーが反映されることを確認

---

以上でメニュー管理システムの実装は完了です！
ご不明な点がございましたら、お気軽にお問い合わせください。
