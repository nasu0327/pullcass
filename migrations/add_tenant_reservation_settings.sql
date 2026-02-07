-- 予約機能設定テーブル
CREATE TABLE IF NOT EXISTS tenant_reservation_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    
    -- 予約機能の有効/無効
    is_enabled TINYINT(1) DEFAULT 1,
    
    -- 通知メールアドレス（複数可、カンマ区切り）
    notification_emails TEXT,
    
    -- 予約受付時間設定
    accept_start_time TIME DEFAULT '10:00:00',
    accept_end_time TIME DEFAULT '02:00:00',
    
    -- 予約可能な日数（何日先まで予約可能か）
    advance_days INT DEFAULT 7,
    
    -- 自動返信メールの件名
    auto_reply_subject VARCHAR(255) DEFAULT 'ネット予約',
    
    -- 自動返信メールの本文（プレースホルダー対応）
    auto_reply_body TEXT,
    
    -- 管理者通知メールの件名
    admin_notify_subject VARCHAR(255) DEFAULT 'ネット予約',
    
    -- 管理者通知メールの本文（プレースホルダー対応）
    admin_notify_body TEXT,
    
    -- 注意事項テキスト
    notice_text TEXT,
    
    -- 予約完了後のメッセージ
    complete_message TEXT,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_tenant (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 既存テナントにデフォルト設定を挿入
INSERT IGNORE INTO tenant_reservation_settings (tenant_id, notification_emails, auto_reply_subject, auto_reply_body, admin_notify_subject, admin_notify_body, notice_text, complete_message)
SELECT 
    id,
    email,
    'ネット予約',
    '{customer_name} 様\n\nこの度は{tenant_name}をご利用いただき、ありがとうございます。\n仮予約を受け付けました。\n※お店より確認の電話が入り次第、ご予約成立とさせていただきます。\n\n※下記の内容をご確認下さい。\nご利用予定日：{date} {time}\n指名キャスト：{cast_name}\n利用形態：{customer_type}\nご利用コース：{course}\n适応したいイベント・キャンペーン：{event}\nお客様の電話番号：{customer_phone}\nお客様のメールアドレス：{customer_email}\nご利用施設：{facility}\n合計金額：{total_amount}\n仮予約送信時刻：{created_at}\n伝達事項：{notes}\n\nご予約確認のため、{confirm_time}の間にお電話させていただきます。\nご都合の悪い場合は、お手数ですがお電話にてご連絡ください。\n\n※別途その他の料金が発生する場合がございます。詳しくは直接お店までご確認ください。\n\n{tenant_name}\nオフィシャルHP\n{tenant_hp}\nTEL: {tenant_tel}\n電話受付{tenant_hours}',
    'ネット予約',
    '予定日：{date} {time}\nコールバック：{confirm_time}\nキャスト名：{cast_name}\nコース：{course}\n有料OP：{option}\nイベント：{event}\n名前：{customer_name}\n電話：{customer_phone}\nMAIL：{customer_email}\n{facility_label_admin}：{facility}\n伝達事項：{notes}\n合計金額：{total_amount}\n受信時刻：{created_at}',
    '・このネット予約は仮予約です。お店からの確認連絡をもって予約確定となります。\n・ご希望の日時・キャストが確保できない場合がございます。\n・キャンセルや変更はお電話にてご連絡ください。',
    'ご予約ありがとうございます。お店からの確認連絡をお待ちください。'
FROM tenants;
