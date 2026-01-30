<?php
/**
 * 派遣状況別のデフォルト表示文言（フロント「派遣方法」等）
 * プレーンテキスト。代入テキスト（プレースホルダー）は廃止。
 */

/**
 * 保存されている文言が旧デフォルト（編集不可の見出し付き）かどうか判定
 * @param string $type full|conditional|limited|none
 * @param string $content 保存されている文言（HTML除去済み可）
 * @return bool 旧デフォルトなら true（新デフォルトに差し替えるべき）
 */
function is_old_dispatch_default_content($type, $content) {
    $c = $content;
    // 旧デフォルト（絵文字・見出し付き）または前バージョン（URL・プレースホルダー付き）なら新デフォルトに差し替える
    if ($type === 'full') {
        return strpos($c, '✅ 派遣可能') !== false || (strpos($c, 'ご利用の流れ') !== false && strpos($c, 'フロントでの待ち合わせは不要') !== false)
            || strpos($c, '(/schedule/day1)') !== false || strpos($c, '(/yoyaku/)') !== false || strpos($c, '{{business_hours}}') !== false;
    }
    if ($type === 'conditional') {
        return strpos($c, 'ℹ️ 入館方法') !== false || strpos($c, 'カードキー式のため') !== false
            || strpos($c, '(/schedule/day1)') !== false || strpos($c, '(/yoyaku/)') !== false || strpos($c, '{{business_hours}}') !== false;
    }
    if ($type === 'limited') {
        return strpos($c, '⚠️ ご注意') !== false || strpos($c, 'ご予約前のご確認') !== false;
    }
    if ($type === 'none') {
        return strpos($c, '❌ 派遣不可') !== false || strpos($c, '派遣できない理由') !== false
            || (strpos($c, '】様は') !== false && strpos($c, 'ホテル側のセキュリティ方針') !== false);
    }
    return false;
}

function get_default_dispatch_content($type) {
    $defaults = [
        'full' => "・ホテルにチェックイン前からご予約は可能です。当店ホームページのスケジュールページから、お目当てのキャストの出勤日時をご確認下さい。
スケジュールに掲載分の予定は事前予約も可能です。
・電話予約は営業時間内で受け付けております。ネット予約は24時間受け付けております。
・チェックイン後はホテルの部屋番号を当店受付まで直接お電話にてお伝え下さい。
・受付完了後はキャストが予定時刻に直接お部屋までお伺いいたします。",

        'conditional' => "・ホテルにチェックイン前からご予約は可能です。当店ホームページのスケジュールページから、お目当てのキャストの出勤日時をご確認下さい。
スケジュールに掲載分の予定は事前予約も可能です。
・電話予約は営業時間内で受け付けております。ネット予約は24時間受け付けております。
・予定時刻に入り口外までキャストのお迎えをお願いします。
・キャスト到着前に当店受付にお迎えの際の服装とお名前をお伝え下さい。
・キャストが予定時刻に到着したらお電話いたしますのでキャストと合流してお部屋までご一緒に入室お願いいたします。",

        'limited' => "ホテル側のセキュリティ状況により、デリヘルの派遣ができない場合がございます。必ずホテルご予約の前に当店受付にてご確認をお願いいたします。",

        'none' => "ホテル側のセキュリティ方針により、
外部からの訪問者をお部屋までご案内することができません。",
    ];
    return $defaults[$type] ?? '';
}
