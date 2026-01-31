# 実装プラン：確認電話可能日の開始・終了時刻の設定連動

## 概要
確認電話可能日の開始時刻と終了時刻を、店舗管理画面の「予約機能管理」で設定した「受付開始時刻」と「受付終了時刻」と連動させる。

## 変更対象ファイル
- `www/app/front/yoyaku.php`

## 実装ステップ

### ステップ1: PHP側でデータベースから設定を取得
- `tenant_reservation_settings`テーブルから`accept_start_time`と`accept_end_time`を取得
- テナント情報を取得する既存のコードの後に追加

### ステップ2: JavaScriptに変数を渡す
- PHPで取得した値をJavaScriptのグローバル変数として定義
```javascript
const acceptStartTime = '<?php echo h($acceptStartTime); ?>';
const acceptEndTime = '<?php echo h($acceptEndTime); ?>';
```

### ステップ3: JavaScript関数を修正
- `populateConfirmTimeOptions`関数のハードコード値(10:30, 24:30)を削除
- データベースから取得した値を使用するように変更

### 変更例

```javascript
// 変更前（ハードコード）
function populateConfirmTimeOptions(startHour = 10, startMinute = 30, endHour = 24, endMinute = 0) {

// 変更後（DBの値を使用）
function populateConfirmTimeOptions(startHour = acceptStartHour, startMinute = acceptStartMinute, endHour = acceptEndHour, endMinute = acceptEndMinute) {
```

## 完了チェックリスト
- [x] PHP側でデータベースから設定を取得するコードを追加
- [x] JavaScriptに変数を渡すコードを追加
- [x] `populateConfirmTimeOptions`関数を修正
- [ ] 動作確認

