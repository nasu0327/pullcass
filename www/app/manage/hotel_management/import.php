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
    $sheets = $xlsx->sheetNames(); // [ インデックス => シート名 ]
    $count = 0;

    // テナントIDの確認
    if (!$tenantId) {
        $tenantId = $_SESSION['manage_tenant']['id'] ?? null;
    }
    if (!$tenantId) {
        header("Location: index.php?tenant={$tenantSlug}&error=テナントIDが取得できませんでした");
        exit;
    }

    // シートはファイルのタブ順のまま処理。エリアはシート名をそのまま使用（テナントごとのタブ名に対応）。

    // インポート前に現在のテナントのデータを完全に削除する
    // ※シートごとのループの外で一度だけ実行する必要があります
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("DELETE FROM hotels WHERE tenant_id = ?");
        $stmt->execute([$tenantId]);

        // 削除後にループ処理開始（タブ順のまま）
        foreach ($sheets as $index => $sheetName) {
            $areaName = trim($sheetName);
            if ($areaName === '') {
                continue;
            }
            // ラブホテル判定: シート名に「ラブホテル」が含まれるか（テナントごとのタブ名に対応）
            $isLoveHotel = (mb_strpos($areaName, 'ラブホテル') !== false) ? 1 : 0;

            // シート内の行を取得
            $rows = $xlsx->rows($index);

            // ヘッダー行を取得
            $header = array_shift($rows);

            // ヘッダーのマッピング（列番号の検出）
            $map = [
                'symbol' => -1,
                'name' => -1,
                'cost' => -1,
                'phone' => -1,
                'address' => -1,
                'method' => -1,
                'desc' => -1
            ];

            // ヘッダー名から列位置を特定
            foreach ($header as $idx => $colName) {
                $colName = trim($colName ?? '');
                if (empty($colName))
                    continue;

                if (preg_match('/(案内)?種別|マーク|記号/', $colName))
                    $map['symbol'] = $idx;
                elseif (preg_match('/ホテル名|名前|名称/', $colName))
                    $map['name'] = $idx;
                elseif (preg_match('/交通費|料金|コスト/', $colName))
                    $map['cost'] = $idx;
                elseif (preg_match('/電話番号|TEL|連絡先/i', $colName))
                    $map['phone'] = $idx;
                elseif (preg_match('/住所|場所|所在地/', $colName))
                    $map['address'] = $idx;
                elseif (preg_match('/案内方法|利用方法|入室方法/', $colName))
                    $map['method'] = $idx;
                elseif (preg_match('/詳細|備考|説明/', $colName))
                    $map['desc'] = $idx;
            }

            // 必須カラム（ホテル名）が見つからない場合は、デフォルトの列順と仮定する（後方互換性）
            if ($map['name'] === -1) {
                // デフォルト: 0=案内種別, 1=ホテル名, 2=交通費, 3=電話番号, 4=住所, 5=案内方法, 6=ホテル詳細
                $map = [
                    'symbol' => 0,
                    'name' => 1,
                    'cost' => 2,
                    'phone' => 3,
                    'address' => 4,
                    'method' => 5,
                    'desc' => 6
                ];
            }

            // シート内の行順を保持するためのカウンター
            $rowOrder = 0;

            foreach ($rows as $r) {
                // ホテル名を取得
                $nameIdx = $map['name'];
                $nameVal = isset($r[$nameIdx]) ? trim($r[$nameIdx]) : '';

                // 名前が空またはヘッダー行のような値の場合はスキップ
                if (empty($nameVal) || $nameVal === 'ホテル名')
                    continue;

                // 行順をインクリメント（Excelの上から順に1, 2, 3...）
                $rowOrder++;

                // 各カラムの値を取得
                $symbol = ($map['symbol'] !== -1 && isset($r[$map['symbol']])) ? trim($r[$map['symbol']]) : '';
                $cost = ($map['cost'] !== -1 && isset($r[$map['cost']])) ? trim($r[$map['cost']]) : '';
                $phone = ($map['phone'] !== -1 && isset($r[$map['phone']])) ? trim($r[$map['phone']]) : '';
                $address = ($map['address'] !== -1 && isset($r[$map['address']])) ? trim($r[$map['address']]) : '';
                $method = ($map['method'] !== -1 && isset($r[$map['method']])) ? trim($r[$map['method']]) : '';
                $desc = ($map['desc'] !== -1 && isset($r[$map['desc']])) ? trim($r[$map['desc']]) : '';

                // シンボル（案内種別）が空の場合はデフォルト値を設定（ラブホテルは♡、それ以外は◯）
                if ($symbol === '') {
                    $symbol = $isLoveHotel ? '♡' : '◯';
                }
                // ラブホテルでハート系の表記（♥❤等）は♡に統一
                if ($isLoveHotel && (mb_strpos($symbol, '♡') !== false || mb_strpos($symbol, '♥') !== false || mb_strpos($symbol, '❤') !== false)) {
                    $symbol = '♡';
                }

                // 重要：既存データを削除済みなので、ここでは単純にINSERTのみ行う
                // ただし、同じファイル内での重複を防ぐために一応チェックする
                $stmt = $pdo->prepare("SELECT id FROM hotels WHERE name = ? AND tenant_id = ? LIMIT 1");
                $stmt->execute([$nameVal, $tenantId]);
                $exists = $stmt->fetch();

                if ($exists) {
                    // 更新（同じファイル内で名前重複があった場合、後勝ちで更新）
                    $stmt = $pdo->prepare("UPDATE hotels SET 
                    symbol = ?, area = ?, address = ?, phone = ?, cost = ?, 
                    method = ?, is_love_hotel = ?, hotel_description = ?, sort_order = ?
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
                        $rowOrder,
                        $exists['id']
                    ]);
                } else {
                    // 新規挿入（sort_orderにExcelの行順を設定）
                    $stmt = $pdo->prepare("INSERT INTO hotels (
                    tenant_id, name, symbol, area, address, phone, cost, 
                    method, is_love_hotel, hotel_description, sort_order
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $tenantId,
                        $nameVal,
                        $symbol,
                        $areaName,
                        $address,
                        $phone,
                        $cost,
                        $method,
                        $isLoveHotel,
                        $desc,
                        $rowOrder
                    ]);
                }
                $count++;
            }
        }

        $pdo->commit();
        header("Location: index.php?tenant={$tenantSlug}&success={$count}件のデータをインポートしました");

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        header("Location: index.php?tenant={$tenantSlug}&error=インポート処理エラー: " . $e->getMessage());
        exit;
    }
} else {
    $error = SimpleXLSX::parseError();
    header("Location: index.php?tenant={$tenantSlug}&error=Excel解析エラー: {$error}");
}
exit;
