<?php
/**
 * DB修正スクリプト for duplicates
 * 1. テーブルを空にする (TRUNCATE)
 * 2. nameカラムにユニークインデックスを追加する
 */
// bootstrapのパスは環境に合わせて調整 (migrate.phpと同じ階層を想定して書くか、debug_db.phpの知見を活かす)
// ここは app/manage/fix_db.php として配置するので、 ../../includes/bootstrap.php
require_once __DIR__ . '/../../includes/bootstrap.php';

$pdo = getPlatformDb();

echo "Starting DB Fix...\n";

try {
    // 1. Truncate
    echo "Truncating 'hotels' table...\n";
    $pdo->exec("TRUNCATE TABLE hotels");
    echo "Done.\n";

    // 2. Add Unique Index
    echo "Adding UNIQUE index to 'name' column...\n";
    // インデックスが存在するか確認してから追加するのが丁寧だが、エラーになっても"Duplicate"ならOK
    // ただしTRUNCATEしてるのでデータ競合はない。
    // 名前が既にインデックスあるかもしれないので、IGNORE的なことはできないが、try-catchで囲む
    $pdo->exec("ALTER TABLE hotels ADD UNIQUE INDEX unique_name (name)");
    echo "Done.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "DB Fix Completed.\n";
