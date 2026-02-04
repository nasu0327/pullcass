# 予約メール・SES 引き継ぎメモ

予約フォームからメールが届くようにする作業の続き用です。**SES の pullcass.com が「検証済み」になってから**このメモに沿って作業を続けてください。

---

## 1. 目的

- 予約フォーム送信時に、管理者・お客様へメールが届くようにする
- 送信は **AWS SES**（SMTP）経由で行う

---

## 2. 実施済みの内容


### コード・設定

- **`www/includes/mail_helper.php`** … `send_reservation_mail()` を実装。`MAIL_HOST` が設定されていれば SMTP（SES）、なければ `mb_send_mail()` で送信
- **`www/app/front/yoyaku/submit.php`** … 予約送信時に `send_reservation_mail()` を利用するように変更済み
- **`.env.example`** … メール送信元（`MAIL_FROM`）と SMTP（`MAIL_HOST` 等）の説明を追記済み

### AWS

- **SES** … 送信ドメインとして **pullcass.com** を追加。DKIM 用 CNAME 3本と DMARC 用 TXT を Route 53 に登録済み
- **Route 53** … DKIM・DMARC を入れたホストゾーンは **ns-348 / ns-1376 / ns-1883 / ns-712** の4つ。登録済みドメインのネームサーバーをこの4つに変更済み

### 現在の状態（2026年2月 更新）

- **SES** … pullcass.com の ID ステータスは **「検証済み」**（ap-southeast-2 シドニー）
- **DMARC** … SES から「DMARC 設定が見つかりませんでした」の推奨あり。Route 53 には `_dmarc.pullcass.com` TXT（`v=DMARC1; p=none;`）登録済み。必要に応じてレコード確認やポリシー強化を検討

---

## 3. 検証済みになったらやること

### 3-1. .env に SES 用 SMTP を設定する

1. **SES コンソール**で「SMTP 設定」から **SMTP 認証情報**（ユーザー名・パスワード）を発行する
2. **リージョン**は **ap-northeast-1（東京）** または **ap-southeast-2（シドニー）** など、SES で pullcass.com を登録したリージョンに合わせる
3. プロジェクトの **`.env`** に以下を設定（値は環境に合わせて書き換え）

```env
# 送信元（任意。未設定なら noreply@pullcass.com 等が使われる）
MAIL_FROM=店名 <noreply@pullcass.com>

# SES SMTP（MAIL_HOST を設定すると SMTP で送信される）
MAIL_HOST=email-smtp.ap-southeast-2.amazonaws.com
MAIL_PORT=587
MAIL_USERNAME=（SES の SMTP ユーザー名）
MAIL_PASSWORD=（SES の SMTP パスワード）
MAIL_ENCRYPTION=tls
```

- **MAIL_HOST** … SES のリージョンに合わせる（例: シドニーなら `email-smtp.ap-southeast-2.amazonaws.com`）。SES コンソールの「SMTP 設定」に表示されるエンドポイントを参照

### 3-2. 動作確認

1. 予約フォームからテスト送信する
2. 管理者用・お客様用の両方のメールが届くか確認する
3. 届かない場合はサーバーログや `mail_helper.php` のエラーハンドリングを確認する

---

## 4. 参照ファイル

| 用途           | パス |
|----------------|------|
| メール送信処理 | `www/includes/mail_helper.php` |
| 予約送信処理   | `www/app/front/yoyaku/submit.php` |
| 環境変数例     | `.env.example` |

---

## 5. 続きの依頼の仕方（AI に依頼する場合）

新しいチャットで次のように書くと、続きの作業をスムーズに進められます。

- 「SES の pullcass.com が検証済みになりました。予約メールの設定を続けてください」
- 「`docs/予約メール_SES_引き継ぎ.md` を読んで、.env の SMTP 設定とテスト送信まで進めてください」

---

*最終更新: 2026年2月（SES 検証保留中・DNS 反映待ちの時点で作成）*
