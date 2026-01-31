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
    auto_reply_subject VARCHAR(255) DEFAULT 'ご予約を受け付けました',
    
    -- 自動返信メールの本文（プレースホルダー対応）
    auto_reply_body TEXT,
    
    -- 管理者通知メールの件名
    admin_notify_subject VARCHAR(255) DEFAULT '【新規予約】ネット予約が入りました',
    
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
INSERT IGNORE INTO tenant_reservation_settings (tenant_id, notification_emails, auto_reply_body, admin_notify_body, notice_text, complete_message)
SELECT 
    id,
    email,
    'この度はご予約いただき、誠にありがとうございます。\n\n以下の内容でご予約を受け付けました。\n\n【ご予約内容】\n予約番号: {reservation_id}\nご利用日: {date}\nご希望時刻: {time}\n指名: {cast_name}\nコース: {course}\n\n※このご予約は仮予約です。\nお店からの確認連絡をもって予約確定となります。\n\nご不明な点がございましたら、お気軽にお問い合わせください。',
    '【新規ネット予約】\n\n予約番号: {reservation_id}\n\n【お客様情報】\nお名前: {customer_name}\n電話番号: {customer_phone}\nメール: {customer_email}\n\n【ご予約内容】\nご利用日: {date}\nご希望時刻: {time}\n指名: {cast_name}\nコース: {course}\n施設: {facility}\n\n【備考】\n{notes}',
    '・このネット予約は仮予約です。お店からの確認連絡をもって予約確定となります。\n・ご希望の日時・キャストが確保できない場合がございます。\n・キャンセルや変更はお電話にてご連絡ください。',
    'ご予約ありがとうございます。お店からの確認連絡をお待ちください。'
FROM tenants;
