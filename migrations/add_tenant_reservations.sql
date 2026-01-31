-- 予約テーブルの作成
-- 実行日: 2025-XX-XX
-- 目的: ネット予約機能のための予約データ保存テーブル

CREATE TABLE IF NOT EXISTS tenant_reservations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL COMMENT 'テナントID',
    
    -- キャスト情報
    cast_id INT DEFAULT NULL COMMENT 'キャストID（指名ありの場合）',
    cast_name VARCHAR(100) DEFAULT NULL COMMENT 'キャスト名（指名ありの場合）',
    nomination_type ENUM('shimei', 'free') NOT NULL DEFAULT 'free' COMMENT '指名形態（shimei:指名あり, free:フリー）',
    
    -- 予約日時
    reservation_date DATE NOT NULL COMMENT '利用予定日',
    reservation_time VARCHAR(10) NOT NULL COMMENT '希望時刻（例: 14:00, 25:30）',
    
    -- 確認連絡
    contact_available_time VARCHAR(255) DEFAULT NULL COMMENT '確認電話可能日時',
    
    -- 利用形態
    customer_type ENUM('new', 'member') NOT NULL DEFAULT 'new' COMMENT '利用形態（new:初めて, member:会員）',
    
    -- コース
    course VARCHAR(100) NOT NULL COMMENT 'コース名',
    course_price INT DEFAULT NULL COMMENT 'コース料金',
    
    -- オプション（将来拡張用）
    options TEXT DEFAULT NULL COMMENT '有料オプション（JSON形式）',
    options_price INT DEFAULT 0 COMMENT 'オプション合計料金',
    
    -- 利用施設
    facility_type ENUM('home', 'hotel') NOT NULL DEFAULT 'home' COMMENT '利用施設（home:自宅, hotel:ホテル）',
    facility_detail VARCHAR(500) DEFAULT NULL COMMENT '住所・ホテル名',
    
    -- お客様情報
    customer_name VARCHAR(100) NOT NULL COMMENT 'お客様名',
    customer_phone VARCHAR(20) NOT NULL COMMENT '電話番号',
    customer_email VARCHAR(255) DEFAULT NULL COMMENT 'メールアドレス',
    
    -- 伝達事項
    message TEXT DEFAULT NULL COMMENT '伝達事項・ご要望',
    
    -- 合計金額
    total_price INT DEFAULT NULL COMMENT '合計金額',
    
    -- ステータス
    status ENUM('new', 'confirmed', 'completed', 'cancelled') NOT NULL DEFAULT 'new' COMMENT 'ステータス（new:新規, confirmed:確認済み, completed:完了, cancelled:キャンセル）',
    
    -- 管理用メモ
    admin_memo TEXT DEFAULT NULL COMMENT '管理者メモ',
    
    -- タイムスタンプ
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
    
    -- インデックス
    INDEX idx_tenant_id (tenant_id),
    INDEX idx_cast_id (cast_id),
    INDEX idx_reservation_date (reservation_date),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    
    -- 外部キー
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (cast_id) REFERENCES tenant_casts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='ネット予約データ';

-- 確認用
-- SELECT * FROM tenant_reservations;
