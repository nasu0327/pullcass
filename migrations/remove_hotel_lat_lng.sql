-- hotels テーブルから緯度・経度カラムを削除
-- 注意: プラットフォームDBの hotels に適用してください。
-- カラムが既に存在しない場合はエラーになります（その場合は無視して問題ありません）。

ALTER TABLE hotels
  DROP COLUMN lat,
  DROP COLUMN lng;
