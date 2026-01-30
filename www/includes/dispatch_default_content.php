<?php
/**
 * 派遣状況別のデフォルト表示文言（フロント「ご利用の流れ」等）
 * プレースホルダー: {{hotel_name}}, {{area}} は表示時に置換
 */
function get_default_dispatch_content($type) {
    $defaults = [
        'full' => '<div style="margin-top: 16px; padding: 16px; background: #d4edda; border-left: 4px solid #28a745; border-radius: 6px;">
              <p style="margin: 0; font-size: 14px; color: #155724; line-height: 1.6;">
                ✅ <strong>派遣可能：</strong>キャストが直接お部屋までお伺いします。フロントでの待ち合わせは不要です。
              </p>
            </div>

            <h3 style="margin: 20px 0 12px; color: var(--color-primary); font-size: 18px;">
              ご利用の流れ
            </h3>
            <ul style="margin: 0; padding-left: 20px; font-size: 14px; line-height: 1.8;">
              <li>ホテルにチェックイン前からご予約は可能です。当店ホームページの<strong><a href="/schedule/day1" style="color: var(--color-primary); text-decoration: underline;">スケジュールページ</a></strong>から、お目当てのキャストの出勤日時をご確認下さい。<br>
                スケジュールに掲載分の予定は事前予約も可能です。<br>
                電話予約は{{business_hours}}の間で受け付けております。<strong><a href="/yoyaku/" style="color: var(--color-primary); text-decoration: underline;">ネット予約</a></strong>は24時間受け付けております。</li>
              <li>チェックイン後はホテルの部屋番号を当店受付まで直接お電話にてお伝え下さい。</li>
              <li>受付完了後はキャストが予定時刻に直接お部屋までお伺いいたします。</li>
            </ul>',

        'conditional' => '<div style="margin-top: 16px; padding: 16px; background: #d1ecf1; border-left: 4px solid #17a2b8; border-radius: 6px;">
              <p style="margin: 0; font-size: 14px; color: #0c5460; line-height: 1.6;">
                ℹ️ <strong>入館方法：</strong>カードキー式のため、ホテルの入り口で待ち合わせとなります。
              </p>
            </div>

            <h3 style="margin: 20px 0 12px; color: var(--color-primary); font-size: 18px;">
              ご利用の流れ
            </h3>
            <ul style="margin: 0; padding-left: 20px; font-size: 14px; line-height: 1.8;">
              <li>ホテルにチェックイン前からご予約は可能です。当店ホームページの<strong><a href="/schedule/day1" style="color: var(--color-primary); text-decoration: underline;">スケジュールページ</a></strong>から、お目当てのキャストの出勤日時をご確認下さい。<br>
                スケジュールに掲載分の予定は事前予約も可能です。<br>
                電話予約は{{business_hours}}の間で受け付けております。<strong><a href="/yoyaku/" style="color: var(--color-primary); text-decoration: underline;">ネット予約</a></strong>は24時間受け付けております。</li>
              <li>予定時刻に入り口外までキャストのお迎えをお願いします。</li>
              <li>キャスト到着前に当店受付にお迎えの際の服装とお名前をお伝え下さい。</li>
              <li>キャストが予定時刻に到着したらお電話いたしますのでキャストと合流してお部屋までご一緒に入室お願いいたします。</li>
            </ul>',

        'limited' => '<div style="margin-top: 16px; padding: 16px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 6px;">
              <p style="margin: 0; font-size: 14px; color: #856404; line-height: 1.6;">
                ⚠️ <strong>ご注意：</strong>状況により派遣できない場合がございます。ご予約前に必ずお電話でご確認ください。
              </p>
            </div>

            <h3 style="margin: 20px 0 12px; color: var(--color-primary); font-size: 18px;">
              ご予約前のご確認
            </h3>
            <p style="margin: 0; font-size: 14px; line-height: 1.7;">
              ホテル側のセキュリティ状況により、デリヘルの派遣ができない場合がございます。必ずホテルご予約の前に当店受付にてご確認をお願いいたします。
            </p>',

        'none' => '<div style="margin-top: 16px; padding: 16px; background: #f8d7da; border-left: 4px solid #dc3545; border-radius: 6px;">
              <p style="margin: 0; font-size: 14px; color: #721c24; line-height: 1.6;">
                ❌ <strong>派遣不可：</strong>こちらのホテルにはデリヘルの派遣ができません。
              </p>
            </div>

            <h3 style="margin: 20px 0 12px; color: var(--color-primary); font-size: 18px;">
              派遣できない理由
            </h3>
            <p style="margin: 0; font-size: 14px; line-height: 1.7;">
              【{{hotel_name}}】様は、ホテル側のセキュリティ方針により、
              外部からの訪問者をお部屋までご案内することができません。
            </p>

            <p style="margin: 16px 0 0; font-size: 13px; color: #666; line-height: 1.7;">
              ご不明な点やホテルのご相談は、お気軽にお電話下さい。<br>
              TEL：<a href="tel:{{phone_raw}}" style="color: var(--color-primary); text-decoration: none; font-weight: bold;">{{phone}}</a><br>
              電話受付：{{business_hours}}
            </p>',
    ];
    return $defaults[$type] ?? '';
}
