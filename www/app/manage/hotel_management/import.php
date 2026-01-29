<?php
require_once __DIR__ . '/../../../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/lib/SimpleXLSX.php';

use Shuchkin\SimpleXLSX;

// ログイン認証
requireTenantAdminLogin();

$pdo = getPlatformDb();
$tenantSlug = $_GET['tenant'] ?? $_SESSION['manage_tenant_slug'] ?? null;
if (!$tenantSlug) {
    header('Location: /');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['excel_file'])) {
    header("Location: index.php?tenant={$tenantSlug}&error=ファイルが選択されていません");
    exit;
}

$file = $_FILES['excel_file']['tmp_name'];

if ($xlsx = SimpleXLSX::parse($file)) {
    $sheets = $xlsx->sheetNames();
    $count = 0;

    foreach ($sheets as $index => $sheetName) {
        $areaName = trim($sheetName);
        $isLoveHotel = ($areaName === 'ラブホテル一覧') ? 1 : 0;

        // シート内の行を取得
        $rows = $xlsx->rows($index);

        // ヘッダー行をスキップ
        $header = array_shift($rows);

        // カラムマッピングの自動検出（簡易）
        // デフォルト: 0=案内種別, 1=ホテル名, 2=交通費, 3=電話番号, 4=住所, 5=案内方法, 6=ホテル詳細
        // ヘッダー名で列位置を特定するのが安全だが、今は列順固定と仮定（ユーザー指定のスプレッドシート構造に従う）

        foreach ($rows as $r) {
            // 空行スキップ
            if (empty($r[1]))
                continue; // ホテル名がない場合はスキップ

            $symbol = trim($r[0] ?? '');
            $name = trim($r[1] ?? '');
            $cost = trim($r[2] ?? '');
            $phone = trim($r[3] ?? '');
            $address = trim($r[4] ?? '');
            $method = trim($r[5] ?? '');
            $desc = trim($r[6] ?? '');

            // 既存データを確認して更新または新規挿入
            // 名前が一致するものを検索
            $stmt = $pdo->prepare("SELECT id FROM hotels WHERE name = ? LIMIT 1");
            $stmt->execute([$name]);
            $exists = $stmt->fetch();

            if ($exists) {
                // 更新
                $stmt = $pdo->prepare("UPDATE hotels SET 
                    symbol = ?, area = ?, address = ?, phone = ?, cost = ?, 
                    method = ?, is_love_hotel = ?, hotel_description = ?
                    WHERE id = ?");
                $stmt->execute([
                    $symbol,
                    $areaName,
                    $address,
                    $phone,
                    $cost,
                    $method,
                    $isLoveHotel,
                    $desc,
                    $exists['id']
                ]);
            } else {
                // 新規挿入
                $stmt = $pdo->prepare("INSERT INTO hotels (
                    name, symbol, area, address, phone, cost, 
                    method, is_love_hotel, hotel_description
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $name,
                    $symbol,
                    $areaName,
                    $address,
                    $phone,
                    $cost,
                    $method,
                    $isLoveHotel,
                    $desc
                ]);
            }
            $count++;
        }
    }

    header("Location: index.php?tenant={$tenantSlug}&success={$count}件のデータをインポートしました");
} else {
    $error = SimpleXLSX::parseError();
    header("Location: index.php?tenant={$tenantSlug}&error=Excel解析エラー: {$error}");
}
exit;
