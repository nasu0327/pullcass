-- ========================================
-- tenant_castsテーブルのnewカラム型修正
-- ========================================
-- 作成日: 2026-01-24
-- 目的: '新人'という文字列を保存できるようにVARCHARに変更
-- ========================================

-- tenant_casts
ALTER TABLE tenant_casts MODIFY COLUMN `new` VARCHAR(50) DEFAULT NULL COMMENT '新人フラグ';

-- 各ソーステーブルも念のため確認・修正（既にVARCHARの可能性高いが統一）
ALTER TABLE tenant_cast_data_ekichika MODIFY COLUMN `new` VARCHAR(50) DEFAULT NULL COMMENT '新人フラグ';
ALTER TABLE tenant_cast_data_heaven MODIFY COLUMN `new` VARCHAR(50) DEFAULT NULL COMMENT '新人フラグ';
ALTER TABLE tenant_cast_data_dto MODIFY COLUMN `new` VARCHAR(50) DEFAULT NULL COMMENT '新人フラグ';
