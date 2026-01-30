<?php
// スマホブラウザ特別対応（強力なキャッシュ無効化）
if (preg_match('/Mobile|Android|iPhone|iPad/', $_SERVER['HTTP_USER_AGENT'])) {
  header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
  header('Pragma: no-cache');
  header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
  header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
}

// フレーム表示許可（管理画面からの表示用）
header('X-Frame-Options: ALLOWALL');
// CSP修正: Google Tag Manager, Google Analytics, YouTube, Font Awesome, Swiperなどを許可
header("Content-Security-Policy: default-src 'self' https: data:; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://fonts.googleapis.com https://www.googletagmanager.com https://www.google-analytics.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com data:; img-src 'self' data: https:; connect-src 'self' https:; frame-src 'self' *;");

// SEO対応：URLパラメータで個別ホテル表示
$hotelId = isset($_GET['hotel_id']) ? (int) $_GET['hotel_id'] : null;
$hotelSlug = isset($_GET['hotel']) ? trim($_GET['hotel']) : null;

// ホテルデータを取得して個別ホテル情報を確認
// DB接続とホテルデータ取得
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/theme_helper.php';
require_once __DIR__ . '/../../includes/dispatch_default_content.php';

// テナント情報を取得・初期化（ヘッダー・フッター用）
$tenantFromRequest = getTenantFromRequest();
$tenantFromSession = getCurrentTenant();

if ($tenantFromRequest) {
  $tenant = $tenantFromRequest;
  if (!$tenantFromSession || $tenantFromSession['id'] !== $tenant['id']) {
    setCurrentTenant($tenant);
  }
} elseif ($tenantFromSession) {
  $tenant = $tenantFromSession;
} else {
  // テナント特定不可時はトップへ
  header('Location: /');
  exit;
}

// 共通変数設定
$shopName = $tenant['name'];
$shopCode = $tenant['code'];
$tenantId = $tenant['id'];
$shopTitle = $tenant['title'] ?? '';
$logoLargeUrl = $tenant['logo_large_url'] ?? '';
$logoSmallUrl = $tenant['logo_small_url'] ?? '';
$phoneNumber = $tenant['phone'] ?? '';
$businessHours = $tenant['business_hours'] ?? '';
$businessHoursNote = $tenant['business_hours_note'] ?? '';

$pdo = getPlatformDb();

// テーマを取得
$currentTheme = getCurrentTheme($tenantId);
$themeData = $currentTheme['theme_data'];

try {
  // 表示順: sort_order昇順, その後ID降順（新しい順）
  $stmt = $pdo->prepare("SELECT * FROM hotels WHERE tenant_id = ? ORDER BY sort_order ASC, id DESC");
  $stmt->execute([$tenantId]);
  $hotels = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  $hotels = [];
  // エラーログ出力など
}

// テナント別「派遣状況テキスト」（管理画面で編集可能）
$tenantDispatchTexts = [];
try {
  $stmtDt = $pdo->prepare("SELECT dispatch_type, content FROM tenant_dispatch_texts WHERE tenant_id = ?");
  $stmtDt->execute([$tenantId]);
  while ($row = $stmtDt->fetch(PDO::FETCH_ASSOC)) {
    $c = trim($row['content'] ?? '');
    if ($c !== '') {
      $tenantDispatchTexts[$row['dispatch_type']] = $c;
    }
  }
} catch (PDOException $e) {
  // テーブル未作成の場合は無視
}

$selectedHotel = null;
if ($hotelId && $hotels) {
  foreach ($hotels as $hotel) {
    if (isset($hotel['id']) && $hotel['id'] == $hotelId) {
      $selectedHotel = $hotel;
      break;
    }
  }
} elseif ($hotelSlug && $hotels) {
  foreach ($hotels as $hotel) {
    if (urlencode($hotel['name']) === $hotelSlug || $hotel['name'] === urldecode($hotelSlug)) {
      $selectedHotel = $hotel;
      break;
    }
  }
}

// ============================================
// 派遣状況の判定ロジック
// ============================================
if ($selectedHotel) {
  // 記号で派遣状況を判定（確実な方法）
  $symbol = $selectedHotel['symbol'] ?? '';
  $dispatchType = 'unknown';

  if (strpos($symbol, '◯') !== false) {
    $dispatchType = 'full'; // 完全OK
  } elseif (strpos($symbol, '※') !== false) {
    $dispatchType = 'conditional'; // 条件付きOK
  } elseif (strpos($symbol, '△') !== false) {
    $dispatchType = 'limited'; // 要確認
  } elseif (strpos($symbol, '×') !== false) {
    $dispatchType = 'none'; // 派遣不可
  } else {
    // 記号がない場合は method テキストで判定（フォールバック）
    $method = $selectedHotel['method'] ?? '';
    if (stripos($method, '派遣できません') !== false) {
      $dispatchType = 'none';
    } elseif (stripos($method, '状況により') !== false) {
      $dispatchType = 'limited';
    } elseif (
      stripos($method, 'カードキー') !== false ||
      stripos($method, '待ち合わせ') !== false
    ) {
      $dispatchType = 'conditional';
    } else {
      $dispatchType = 'full';
    }
  }

  // 派遣可能かどうか（H2タグの表示判定用）
  $canDispatch = in_array($dispatchType, ['full', 'conditional']);

  // 住所から番地以外を抽出（H1タグ用）
  // 半角・全角の数字とハイフン、号を削除
  $addressWithoutNumber = preg_replace('/\s*[0-9０-９\-－ー]+号?$/u', '', $selectedHotel['address']);
  $addressWithoutNumber = trim($addressWithoutNumber);

  // 近隣のホテルを取得（全てのホテルページで表示）
  $nearbyHotels = [];
  $nearbyHotelsTitle = "近くの派遣可能なホテル"; // デフォルトはビジネスホテル用

  if ($selectedHotel) {
    // 現在のホテルがラブホテルかどうか
    $isCurrentLoveHotel = isset($selectedHotel['is_love_hotel']) && $selectedHotel['is_love_hotel'] == 1;

    // ラブホテルの場合はタイトルを変更
    if ($isCurrentLoveHotel) {
      $nearbyHotelsTitle = "近くのラブホテル";
    }

    // 住所の類似度を計算する関数
    $calculateAddressSimilarity = function ($address1, $address2) {
      $score = 0;

      // 区の抽出と比較（同じ区：+100点）
      preg_match('/(博多区|中央区|東区|南区|西区|城南区|早良区|春日市|大野城市|筑紫野市|太宰府市)/', $address1, $matches1);
      preg_match('/(博多区|中央区|東区|南区|西区|城南区|早良区|春日市|大野城市|筑紫野市|太宰府市)/', $address2, $matches2);

      if (!empty($matches1) && !empty($matches2)) {
        if ($matches1[0] == $matches2[0]) {
          $score += 100;

          // 町名の抽出と比較（同じ町名：+50点）
          // 「区」または「市」の後から数字の前まで
          if (
            preg_match('/[区市](.+?)(?=[0-9]|$)/u', $address1, $town1) &&
            preg_match('/[区市](.+?)(?=[0-9]|$)/u', $address2, $town2)
          ) {
            $town1_clean = trim($town1[1]);
            $town2_clean = trim($town2[1]);

            // 完全一致
            if ($town1_clean == $town2_clean) {
              $score += 50;

              // 町名が完全一致した場合、番地の最初の数字を比較
              if (
                preg_match('/[区市][^\d]*(\d+)/u', $address1, $num1) &&
                preg_match('/[区市][^\d]*(\d+)/u', $address2, $num2)
              ) {
                $number1 = (int) $num1[1];
                $number2 = (int) $num2[1];
                $difference = abs($number1 - $number2);

                if ($difference == 0) {
                  $score += 15;
                } elseif ($difference == 1) {
                  $score += 10;
                } elseif ($difference <= 3) {
                  $score += 5;
                }
              }
            }
            // 部分一致
            elseif (strlen($town1_clean) > 3 && strlen($town2_clean) > 3) {
              $common = '';
              for ($i = 0; $i < min(strlen($town1_clean), strlen($town2_clean)); $i++) {
                if (mb_substr($town1_clean, $i, 1) == mb_substr($town2_clean, $i, 1)) {
                  $common .= mb_substr($town1_clean, $i, 1);
                } else {
                  break;
                }
              }
              if (mb_strlen($common) >= 2) {
                $score += 20;
              }
            }
          }
        }
      }

      // 駅名のチェック（同じ駅名：+30点）
      $stations = ['博多駅', '天神', '中洲', '祇園', '呉服町', '西鉄福岡', '薬院', '渡辺通', '赤坂', '大橋'];
      foreach ($stations as $station) {
        if (mb_strpos($address1, $station) !== false && mb_strpos($address2, $station) !== false) {
          $score += 30;
          break;
        }
      }

      return $score;
    };

    // 全ホテルをスコアリング
    $scoredHotels = [];
    foreach ($hotels as $hotel) {
      // 自分自身は除外
      if ($hotel['id'] == $selectedHotel['id'])
        continue;

      $hotelIsLoveHotel = isset($hotel['is_love_hotel']) && $hotel['is_love_hotel'] == 1;

      // ビジネスホテルページの場合
      if (!$isCurrentLoveHotel) {
        // 派遣可能なビジネスホテルのみ（◯または※）
        $symbol = $hotel['symbol'] ?? '';
        $isAvailable = false;
        if (strpos($symbol, '◯') !== false || strpos($symbol, '※') !== false) {
          $isAvailable = true;
        } else {
          // symbolがない場合はmethodで判定
          $method = $hotel['method'] ?? '';
          if (
            stripos($method, '派遣できません') === false &&
            stripos($method, '状況により') === false
          ) {
            $isAvailable = true;
          }
        }

        // ラブホテルは除外 & 派遣可能なホテルのみ
        if ($isAvailable && !$hotelIsLoveHotel) {
          $score = $calculateAddressSimilarity($selectedHotel['address'], $hotel['address']);
          if ($score > 0) {
            $scoredHotels[] = [
              'hotel' => $hotel,
              'score' => $score
            ];
          }
        }
      }
      // ラブホテルページの場合
      else {
        // 近くのラブホテルを全て対象（◯×フラグ関係なく）
        if ($hotelIsLoveHotel) {
          $score = $calculateAddressSimilarity($selectedHotel['address'], $hotel['address']);
          if ($score > 0) {
            $scoredHotels[] = [
              'hotel' => $hotel,
              'score' => $score
            ];
          }
        }
      }
    }

    // スコアでソート（高い順、同点の場合はランダム）
    usort($scoredHotels, function ($a, $b) {
      // スコアが異なる場合はスコアで比較
      if ($b['score'] != $a['score']) {
        return $b['score'] - $a['score'];
      }
      // スコアが同じ場合はランダム
      return rand(-1, 1);
    });

    // スコア100点以上（同じ区）のホテルのみを抽出し、最大5件
    foreach ($scoredHotels as $item) {
      if ($item['score'] >= 100) {
        $nearbyHotels[] = $item['hotel'];
        if (count($nearbyHotels) >= 5) {
          break;
        }
      }
    }
  }
}

// このページ固有の情報を設定（動的メタタグ）
if ($selectedHotel) {
  $pageCanonical = "https://club-houman.com/hotel_list?hotel_id=" . $selectedHotel['id'];

  // ============================================
  // メタタグを派遣状況に応じて設定
  // ============================================
  $hotelType = ($selectedHotel['is_love_hotel'] == 1) ? 'ホテル' : 'ビジネスホテル';

  if ($canDispatch) {
    // 派遣可能（◯※）
    $pageTitle = "【{$selectedHotel['name']}】デリヘルが呼べる{$hotelType}｜福岡市・博多｜豊満倶楽部";
    $pageDescription = "福岡市・博多エリアの【{$selectedHotel['name']}】でデリヘル「豊満倶楽部」をお呼びいただけます。交通費：{$selectedHotel['cost']}。デリヘルが呼べる{$hotelType}の詳細情報。";
  } else {
    // 派遣不可・要確認（△×）
    if ($dispatchType === 'limited') {
      // パターン△：要確認
      $pageTitle = "【{$selectedHotel['name']}】{$hotelType}詳細情報｜福岡市・博多｜豊満倶楽部";
      $pageDescription = "福岡市・博多エリアの【{$selectedHotel['name']}】の詳細情報。交通費：{$selectedHotel['cost']}。デリヘル派遣の可否は事前確認が必要です。";
    } else {
      // パターン×：派遣不可
      $pageTitle = "【{$selectedHotel['name']}】{$hotelType}詳細情報｜福岡市・博多｜豊満倶楽部";
      $pageDescription = "福岡市・博多エリアの【{$selectedHotel['name']}】の詳細情報。こちらのホテルは派遣対象外です。近隣の派遣可能なホテル情報もご確認いただけます。";
    }
  }
} else {
  $pageTitle = "デリヘルが呼べるホテル・ビジネスホテル一覧【福岡市・博多】｜豊満倶楽部";
  $pageDescription = "福岡市・博多エリアでデリヘルが呼べるホテル・ビジネスホテル一覧。博多区・中央区のビジネスホテルやラブホテル別に交通費や入室方法を掲載。中洲、天神、博多駅周辺のホテル情報｜豊満倶楽部";
  $pageCanonical = "https://club-houman.com/hotel_list";
}

// 追加のメタ情報
$pageAuthor = "豊満倶楽部";
$pageRobots = "index, follow";
$pageViewport = "width=device-width, initial-scale=1.0";
$pageCharset = "UTF-8";
$pageLanguage = "ja";
$pageOgTitle = $pageTitle;
$pageOgDescription = $pageDescription;
$pageOgImage = "https://club-houman.com/img/hp/hc_logo.png";
$pageOgUrl = $pageCanonical;
$pageOgType = "website";
$pageOgSiteName = "豊満倶楽部";
$pageTwitterCard = "summary_large_image";
$pageTwitterTitle = $pageTitle;
$pageTwitterDescription = $pageDescription;
$pageTwitterImage = $pageOgImage;

// style2.cssを使用するフラグを設定
$useStyle2 = false;

// 構造化データ（JSON-LD）の設定（個別ホテル対応）
if ($selectedHotel) {
  // 構造化データのdescriptionを派遣状況に応じて設定
  $hotelTypeForSchema = ($selectedHotel['is_love_hotel'] == 1) ? 'ホテル' : 'ビジネスホテル';
  if ($canDispatch) {
    $schemaDescription = "福岡市・博多エリアでデリヘルが呼べる{$hotelTypeForSchema}。デリヘル「豊満倶楽部」がご利用いただけます。";
  } else {
    if ($dispatchType === 'limited') {
      $schemaDescription = "福岡市・博多エリアの{$hotelTypeForSchema}。デリヘル派遣の可否は事前確認が必要です。";
    } else {
      $schemaDescription = "福岡市・博多エリアの{$hotelTypeForSchema}。こちらのホテルは派遣対象外です。";
    }
  }

  // 個別ホテルページ用構造化データ
  $customStructuredData = [
    [
      '@context' => 'https://schema.org',
      '@type' => 'LodgingBusiness',
      '@id' => 'https://club-houman.com/hotel_list?hotel_id=' . $selectedHotel['id'] . '#hotel',
      'name' => $selectedHotel['name'],
      'description' => $schemaDescription,
      'telephone' => $selectedHotel['phone'],
      'address' => [
        '@type' => 'PostalAddress',
        'streetAddress' => $selectedHotel['address'],
        'addressLocality' => $selectedHotel['area'],
        'addressRegion' => '福岡県',
        'addressCountry' => 'JP'
      ],
      'url' => $pageCanonical,
      'hasMap' => 'https://www.google.com/maps/search/?api=1&query=' . urlencode($selectedHotel['name'] . ' ' . $selectedHotel['address']),
      'amenityFeature' => [
        [
          '@type' => 'LocationFeatureSpecification',
          'name' => 'デリヘル利用可能',
          'value' => true
        ]
      ],
      'additionalProperty' => [
        [
          '@type' => 'PropertyValue',
          'name' => '交通費',
          'value' => $selectedHotel['cost']
        ],
        [
          '@type' => 'PropertyValue',
          'name' => 'デリヘル店舗',
          'value' => '豊満倶楽部'
        ]
      ]
    ],
    [
      '@context' => 'https://schema.org',
      '@type' => 'BreadcrumbList',
      'itemListElement' => [
        [
          '@type' => 'ListItem',
          'position' => 1,
          'name' => 'ホーム',
          'item' => 'https://club-houman.com/'
        ],
        [
          '@type' => 'ListItem',
          'position' => 2,
          'name' => 'トップ',
          'item' => 'https://club-houman.com/top'
        ],
        [
          '@type' => 'ListItem',
          'position' => 3,
          'name' => 'ホテルリスト',
          'item' => 'https://club-houman.com/hotel_list'
        ],
        [
          '@type' => 'ListItem',
          'position' => 4,
          'name' => $selectedHotel['name'],
          'item' => $pageCanonical
        ]
      ]
    ],
    [
      '@context' => 'https://schema.org',
      '@type' => 'WebPage',
      '@id' => $pageCanonical . '#webpage',
      'url' => $pageCanonical,
      'name' => $pageTitle,
      'description' => $pageDescription,
      'isPartOf' => [
        '@id' => 'https://club-houman.com/#website'
      ]
    ]
  ];

  // 近くのホテルがある場合、ItemList構造化データを追加
  if (!empty($nearbyHotels)) {
    $nearbyHotelsListItems = [];
    foreach ($nearbyHotels as $index => $hotel) {
      $nearbyHotelsListItems[] = [
        '@type' => 'ListItem',
        'position' => $index + 1,
        'item' => [
          '@type' => 'LodgingBusiness',
          '@id' => 'https://club-houman.com/hotel_list?hotel_id=' . $hotel['id'] . '#hotel',
          'name' => $hotel['name'],
          'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => $hotel['address'],
            'addressLocality' => $hotel['area'],
            'addressRegion' => '福岡県',
            'addressCountry' => 'JP'
          ],
          'url' => 'https://club-houman.com/hotel_list?hotel_id=' . $hotel['id'],
          'additionalProperty' => [
            [
              '@type' => 'PropertyValue',
              'name' => '交通費',
              'value' => $hotel['cost']
            ]
          ]
        ]
      ];
    }

    $customStructuredData[] = [
      '@context' => 'https://schema.org',
      '@type' => 'ItemList',
      '@id' => $pageCanonical . '#nearby-hotels',
      'name' => $nearbyHotelsTitle,
      'description' => $selectedHotel['name'] . 'の近くでデリヘルが利用できるホテル一覧',
      'numberOfItems' => count($nearbyHotels),
      'itemListElement' => $nearbyHotelsListItems
    ];
  }

  // FAQPage構造化データを追加（パターン別）
  $faqItems = [];

  if ($dispatchType === 'full') {
    // パターン◯：完全OK
    $faqItems = [
      [
        '@type' => 'Question',
        'name' => $selectedHotel['name'] . 'でデリヘルの事前予約は可能ですか？',
        'acceptedAnswer' => [
          '@type' => 'Answer',
          'text' => 'はい、可能です。当店ホームページのスケジュールページから、お目当てのキャストの出勤日時をご確認いただけます。電話予約は10:30~2:00の間で受け付けており、ネット予約は24時間受け付けております。'
        ]
      ],
      [
        '@type' => 'Question',
        'name' => 'チェックイン後の流れを教えてください',
        'acceptedAnswer' => [
          '@type' => 'Answer',
          'text' => 'ホテルにチェックイン後、部屋番号を当店受付まで直接お電話にてお伝えください。受付完了後はキャストが予定時刻に直接お部屋までお伺いいたします。フロントでの待ち合わせは不要です。'
        ]
      ],
      [
        '@type' => 'Question',
        'name' => 'キャストはどこに来ますか？',
        'acceptedAnswer' => [
          '@type' => 'Answer',
          'text' => 'キャストが直接お部屋までお伺いします。フロントでの待ち合わせは不要です。'
        ]
      ]
    ];
  } elseif ($dispatchType === 'conditional') {
    // パターン※：条件付きOK
    $faqItems = [
      [
        '@type' => 'Question',
        'name' => $selectedHotel['name'] . 'でデリヘルの事前予約は可能ですか？',
        'acceptedAnswer' => [
          '@type' => 'Answer',
          'text' => 'はい、可能です。当店ホームページのスケジュールページから、お目当てのキャストの出勤日時をご確認いただけます。電話予約は10:30~2:00の間で受け付けており、ネット予約は24時間受け付けております。'
        ]
      ],
      [
        '@type' => 'Question',
        'name' => 'カードキー式ホテルでの利用方法は？',
        'acceptedAnswer' => [
          '@type' => 'Answer',
          'text' => 'カードキー式のため、ホテルの入り口外までキャストのお迎えをお願いします。キャスト到着前に当店受付にお迎えの際の服装とお名前をお伝えください。キャストが予定時刻に到着したらお電話いたしますのでキャストと合流してお部屋までご一緒に入室をお願いいたします。'
        ]
      ],
      [
        '@type' => 'Question',
        'name' => '待ち合わせ場所はどこですか？',
        'acceptedAnswer' => [
          '@type' => 'Answer',
          'text' => 'ホテルの入り口外でお待ちください。キャストが到着したらお電話いたしますので、合流後にご一緒にお部屋まで入室お願いいたします。'
        ]
      ]
    ];
  } elseif ($dispatchType === 'limited') {
    // パターン△：要確認
    $faqItems = [
      [
        '@type' => 'Question',
        'name' => $selectedHotel['name'] . 'でデリヘルを呼べますか？',
        'acceptedAnswer' => [
          '@type' => 'Answer',
          'text' => 'ホテル側のセキュリティ状況により、デリヘルの派遣ができない場合がございます。必ずホテルご予約の前に当店受付（TEL: 092-441-3651）にてご確認をお願いいたします。'
        ]
      ],
      [
        '@type' => 'Question',
        'name' => '派遣できない場合はどうすればいいですか？',
        'acceptedAnswer' => [
          '@type' => 'Answer',
          'text' => 'お近くの派遣可能なホテル一覧をご確認ください。' . $selectedHotel['area'] . 'エリアには派遣可能なビジネスホテルが多数ございます。'
        ]
      ]
    ];
  } else {
    // パターン×：派遣不可
    $faqItems = [
      [
        '@type' => 'Question',
        'name' => $selectedHotel['name'] . 'でデリヘルを呼べますか？',
        'acceptedAnswer' => [
          '@type' => 'Answer',
          'text' => 'いいえ、こちらのホテルにはデリヘルの派遣ができません。' . $selectedHotel['name'] . '様は、ホテル側のセキュリティ方針により、外部からの訪問者をお部屋までご案内することができません。'
        ]
      ],
      [
        '@type' => 'Question',
        'name' => '代わりに利用できるホテルはありますか？',
        'acceptedAnswer' => [
          '@type' => 'Answer',
          'text' => 'はい、' . $selectedHotel['area'] . 'エリアには、デリヘル「豊満倶楽部」をご利用いただけるビジネスホテルが多数ございます。交通費無料のホテル多数、博多駅徒歩圏内のホテル多数、カードキー形式のホテルも多数ございます。派遣可能なホテル一覧をご確認ください。'
        ]
      ],
      [
        '@type' => 'Question',
        'name' => 'ホテルの相談はどこにすればいいですか？',
        'acceptedAnswer' => [
          '@type' => 'Answer',
          'text' => 'ご不明な点やホテルのご相談は、お気軽にお電話（TEL: 080-6316-3545）ください。電話受付時間は10:30～翌2:00です。'
        ]
      ]
    ];
  }

  if (!empty($faqItems)) {
    $customStructuredData[] = [
      '@context' => 'https://schema.org',
      '@type' => 'FAQPage',
      '@id' => $pageCanonical . '#faq',
      'mainEntity' => $faqItems
    ];
  }
} else {
  // 既存のリストページ用構造化データ
  $customStructuredData = [
    [
      '@context' => 'https://schema.org',
      '@type' => 'LocalBusiness',
      '@id' => 'https://club-houman.com/#business',
      'name' => '豊満倶楽部',
      'description' => '福岡市・博多エリアでデリヘルが呼べるホテル・ビジネスホテル情報を提供。博多区・中央区のビジネスホテルやラブホテルに派遣可能な巨乳ぽっちゃり専門デリヘル',
      'url' => 'https://club-houman.com/',
      'telephone' => '080-6316-3545',
      'address' => [
        '@type' => 'PostalAddress',
        'streetAddress' => '福岡県福岡市',
        'addressLocality' => '福岡市',
        'addressRegion' => '福岡県',
        'postalCode' => '810-0011',
        'addressCountry' => 'JP'
      ],
      'geo' => [
        '@type' => 'GeoCoordinates',
        'latitude' => '33.5902',
        'longitude' => '130.4207'
      ],
      'openingHours' => 'Mo-Su 10:30-02:00',
      'priceRange' => '¥¥',
      'image' => 'https://club-houman.com/img/hp/hc_logo.png'
    ],
    [
      '@context' => 'https://schema.org',
      '@type' => 'BreadcrumbList',
      'itemListElement' => [
        [
          '@type' => 'ListItem',
          'position' => 1,
          'name' => 'ホーム',
          'item' => 'https://club-houman.com/'
        ],
        [
          '@type' => 'ListItem',
          'position' => 2,
          'name' => 'トップ',
          'item' => 'https://club-houman.com/top'
        ],
        [
          '@type' => 'ListItem',
          'position' => 3,
          'name' => 'ホテルリスト',
          'item' => 'https://club-houman.com/hotel_list'
        ]
      ]
    ],
    [
      '@context' => 'https://schema.org',
      '@type' => 'WebPage',
      '@id' => 'https://club-houman.com/hotel_list#webpage',
      'url' => 'https://club-houman.com/hotel_list',
      'name' => $pageTitle,
      'description' => $pageDescription,
      'isPartOf' => [
        '@id' => 'https://club-houman.com/#website'
      ],
      'about' => [
        '@id' => 'https://club-houman.com/#business'
      ]
    ]
  ];


  // ホテルデータは上記で既に取得済み（重複回避）

  if ($hotels && is_array($hotels)) {
    $hotelSchemas = [];
    $filteredHotels = [];

    // 「◯」がついているホテルのみを抽出
    foreach ($hotels as $hotel) {
      $symbol = $hotel['symbol'] ?? '';
      // 「◯」が含まれているホテルのみを抽出
      if (strpos($symbol, '◯') !== false) {
        $filteredHotels[] = $hotel;
      }
    }

    foreach ($filteredHotels as $index => $hotel) {
      // GoogleマップURL（既存のリンクと同一形式）
      $mapsUrl = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($hotel['address']);

      $hotelSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'LodgingBusiness',
        '@id' => 'https://club-houman.com/hotel_list#hotel-' . ($index + 1),
        'name' => $hotel['symbol'] ? $hotel['symbol'] . ' ' . $hotel['name'] : $hotel['name'],
        'description' => '福岡市・博多エリアでデリヘルが呼べる' . ($hotel['is_love_hotel'] == 1 ? 'ホテル' : 'ビジネスホテル') . '。豊満倶楽部がご利用いただけます。',
        'telephone' => $hotel['phone'],
        'address' => [
          '@type' => 'PostalAddress',
          'addressLocality' => $hotel['area'],
          'addressRegion' => '福岡県',
          'addressCountry' => 'JP',
          'streetAddress' => $hotel['address']
        ],
        'url' => 'https://club-houman.com/hotel_list',
        'hasMap' => $mapsUrl,
        'priceRange' => '¥¥',
        'amenityFeature' => [
          [
            '@type' => 'LocationFeatureSpecification',
            'name' => 'デリヘルが呼べるホテル',
            'value' => true
          ]
        ],
        'additionalProperty' => array_values(array_filter([
          !empty($hotel['cost']) ? [
            '@type' => 'PropertyValue',
            'name' => '交通費',
            'value' => $hotel['cost']
          ] : null,
          !empty($hotel['method']) ? [
            '@type' => 'PropertyValue',
            'name' => '案内方法',
            'value' => $hotel['method']
          ] : null,
        ]))
      ];

      // ラブホテルの場合は追加情報
      if ($hotel['is_love_hotel'] == 1) {
        $hotelSchema['amenityFeature'][] = [
          '@type' => 'LocationFeatureSpecification',
          'name' => 'ラブホテル',
          'value' => true
        ];
      }

      $hotelSchemas[] = $hotelSchema;
    }

    // 最初の10件のホテルスキーマを追加
    foreach (array_slice($hotelSchemas, 0, 10) as $hotelSchema) {
      $customStructuredData[] = $hotelSchema;
    }

    // ItemListも追加（ListItem 形式）
    $listItems = [];
    foreach (array_slice($hotelSchemas, 0, 10) as $index => $schema) {
      $listItems[] = [
        '@type' => 'ListItem',
        'position' => $index + 1,
        'item' => $schema
      ];
    }
    $customStructuredData[] = [
      '@context' => 'https://schema.org',
      '@type' => 'ItemList',
      '@id' => 'https://club-houman.com/hotel_list#itemlist',
      'name' => 'デリヘルが呼べるホテル・ビジネスホテル一覧【福岡市・博多】- 豊満倶楽部',
      'description' => '福岡市・博多エリアでデリヘルが呼べるホテル・ビジネスホテルの一覧。博多区・中央区のビジネスホテルやラブホテル別に交通費や入室方法を掲載。中洲、天神、博多駅周辺のホテル情報。',
      'url' => 'https://club-houman.com/hotel_list',
      'numberOfItems' => count($filteredHotels),
      'itemListElement' => $listItems
    ];
  }
}

// HTML出力開始
?>
<!DOCTYPE html>
<html lang="ja">

<head>
  <?php include __DIR__ . '/includes/head.php'; ?>
  <?php if (isset($customStructuredData)): ?>
    <script type="application/ld+json">
              <?php echo json_encode($customStructuredData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES); ?>
                </script>
  <?php endif; ?>
  <!-- ページ固有のスタイル（既存のスタイルがある場合はここに移動するか、body内に残す） -->
</head>

<body>
  <?php
  include __DIR__ . '/includes/header.php';
  ?>
  <!-- Google Analytics 4 -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=G-1JRH7FTGL4"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag() { dataLayer.push(arguments); }
    gtag('js', new Date());
    gtag('config', 'G-1JRH7FTGL4', {
      'page_title': '<?php echo addslashes($pageTitle); ?>',
      'page_location': '<?php echo $pageCanonical; ?>',
      'custom_map': {
        'custom_parameter_1': 'page_category',
        'custom_parameter_2': 'user_type',
        'custom_parameter_3': 'content_type'
      },
      'page_category': '<?php echo isset($pageCategory) ? $pageCategory : "hotel_list"; ?>',
      'user_type': '<?php echo isset($userType) ? $userType : "visitor"; ?>',
      'content_type': '<?php echo isset($contentType) ? $contentType : "page"; ?>',
      'anonymize_ip': true,
      'allow_google_signals': false,
      'allow_ad_personalization_signals': false
    });

    // カスタムイベントトラッキング
    document.addEventListener('DOMContentLoaded', function () {
      // 電話番号クリック追跡
      const phoneLinks = document.querySelectorAll('a[href^="tel:"]');
      phoneLinks.forEach(link => {
        link.addEventListener('click', function () {
          gtag('event', 'phone_click', {
            'event_category': 'engagement',
            'event_label': this.href,
            'page_location': window.location.href
          });
        });
      });

      // 外部リンククリック追跡
      const externalLinks = document.querySelectorAll('a[target="_blank"]');
      externalLinks.forEach(link => {
        link.addEventListener('click', function () {
          gtag('event', 'external_link_click', {
            'event_category': 'engagement',
            'event_label': this.href,
            'page_location': window.location.href
          });
        });
      });

      // フォーム送信追跡
      const forms = document.querySelectorAll('form');
      forms.forEach(form => {
        form.addEventListener('submit', function () {
          gtag('event', 'form_submit', {
            'event_category': 'engagement',
            'event_label': form.action || 'unknown_form',
            'page_location': window.location.href
          });
        });
      });

      // スクロール深度追跡
      let maxScroll = 0;
      window.addEventListener('scroll', function () {
        const scrollPercent = Math.round((window.scrollY / (document.body.scrollHeight - window.innerHeight)) * 100);
        if (scrollPercent > maxScroll && scrollPercent % 25 === 0) {
          maxScroll = scrollPercent;
          gtag('event', 'scroll_depth', {
            'event_category': 'engagement',
            'event_label': scrollPercent + '%',
            'page_location': window.location.href
          });
        }
      });
    });
  </script>
  <?php
  ?>
  <main class="main-content">

    <?php if ($selectedHotel) { ?>
      <!-- 個別ホテル表示 -->
      <!-- パンくず -->
      <nav class="breadcrumb">
        <a href="/">ホーム</a><span>»</span>
        <a href="/top">トップ</a><span>»</span>
        <a href="/hotel_list">ホテルリスト</a><span>»</span>
        <?php echo htmlspecialchars($selectedHotel['name']); ?> |
      </nav>

      <!-- タイトルセクション -->
      <section class="title-section" style="padding-top: 24px;">
        <h1 style="font-size: 28px; line-height: 1.3;">
          【<?php echo htmlspecialchars($selectedHotel['name']); ?>】<?php echo htmlspecialchars($addressWithoutNumber); ?>
        </h1>
        <!-- H2タグの条件分岐（ラブホテル/ビジネスホテル） -->
        <h2 style="font-size: 16px; line-height: 1.4; letter-spacing: -0.4px; margin-top: 8px;">
          <?php
          if ($isCurrentLoveHotel) {
            echo 'デリヘルが呼べる！ラブホテル詳細情報';
          } elseif ($canDispatch) {
            echo 'デリヘルが呼べる！ホテル詳細情報';
          } else {
            echo 'ホテル詳細情報';
          }
          ?>
        </h2>
        <div class="dot-line"
          style="height: 3px; width: 100%; margin: 12px 0; background-image: radial-gradient(var(--color-primary) 3px, transparent 3px); background-size: 12px 7px; background-repeat: repeat-x; background-position: center;">
        </div>
      </section>

      <!-- 個別ホテル詳細 -->
      <section class="hotel-detail" style="max-width: 800px; margin: 0 auto; padding: 24px 16px; text-align: left;">
        <div style="background: white; padding: 24px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
          <div style="display: grid; gap: 16px;">
            <!-- 1. 基本情報 -->
            <div>
              <h3 style="margin: 0 0 8px; color: var(--color-primary); font-size: 18px; text-align: left;">基本情報</h3>
              <p style="margin: 0; font-size: 16px; text-align: left;">
                <strong>ホテル名：</strong><?php echo htmlspecialchars($selectedHotel['name']); ?>
              </p>
              <p style="margin: 8px 0 0; font-size: 16px; text-align: left;">
                <strong>エリア：</strong><?php echo htmlspecialchars($selectedHotel['area']); ?>
              </p>
            </div>

            <!-- 2. 派遣情報 -->
            <div>
              <h3 style="margin: 0 0 8px; color: var(--color-primary); font-size: 18px; text-align: left;">派遣情報</h3>
              <p style="margin: 0; font-size: 16px; text-align: left;">
                <strong>交通費：</strong><?php echo htmlspecialchars($selectedHotel['cost']); ?>
              </p>
              <?php if (!empty($selectedHotel['method'])): ?>
                <p style="margin: 8px 0 0; font-size: 16px; text-align: left;">
                  <strong>案内方法：</strong><?php echo htmlspecialchars($selectedHotel['method']); ?>
                </p>
              <?php endif; ?>
            </div>

            <!-- 3. 電話番号 -->
            <div>
              <h3 style="margin: 0 0 8px; color: var(--color-primary); font-size: 18px; text-align: left;">電話番号</h3>
              <p style="display: flex; align-items: center; margin: 0; font-size: 16px; text-align: left;">
                <span class="material-icons" style="margin-right: 8px;">smartphone</span>
                <a href="tel:092-441-3651" style="color: var(--color-primary); text-decoration: none;">
                  092-441-3651
                </a>
              </p>
            </div>

            <!-- 4. 所在地 -->
            <div>
              <h3 style="margin: 0 0 8px; color: var(--color-primary); font-size: 18px; text-align: left;">所在地</h3>
              <p style="display: flex; align-items: center; margin: 0; font-size: 16px; text-align: left;">
                <span class="material-icons" style="margin-right: 8px;">map</span>
                <a href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($selectedHotel['name'] . ' ' . $selectedHotel['address']); ?>"
                  target="_blank" style="color: var(--color-primary); text-decoration: none;">
                  <?php echo htmlspecialchars($selectedHotel['address']); ?>
                </a>
              </p>
            </div>
          </div>

          <!-- ============================================ -->
          <!-- ホテルについて（オリジナルコンテンツ） -->
          <!-- ============================================ -->
          <?php if (!empty($selectedHotel['hotel_description'])): ?>
            <section style="margin-top: 24px; padding: 16px; background: #f9f9f9; border-radius: 8px;">
              <h3 style="font-size: 18px; color: var(--color-primary); margin-bottom: 12px;">
                ホテルについて
              </h3>
              <div style="line-height: 1.8; font-size: 14px; color: #333;">
                <?php
                $text = htmlspecialchars($selectedHotel['hotel_description']);
                $text = nl2br($text);

                // [URL:リンク先|表示テキスト] を <a>タグに変換
                $text = preg_replace(
                  '/\[URL:(https?:\/\/[^\|]+)\|([^\]]+)\]/',
                  '<a href="$1" target="_blank" rel="noopener" style="color: var(--color-primary); text-decoration: underline; font-weight: bold;">$2 →</a>',
                  $text
                );

                echo $text;
                ?>
              </div>
            </section>
          <?php endif; ?>

          <!-- ============================================ -->
          <!-- 派遣状況別の警告ボックスとコンテンツ -->
          <!-- ============================================ -->

          <?php if ($isCurrentLoveHotel): ?>
            <!-- ラブホテル専用コンテンツ -->
            <!-- ご利用の流れ（ラブホテル） -->
            <h3 style="margin: 20px 0 12px; color: var(--color-primary); font-size: 18px;">
              ご利用の流れ
            </h3>
            <ul style="margin: 0; padding-left: 20px; font-size: 14px; line-height: 1.8;">
              <li>ホテルにチェックイン前からご予約は可能です。当店ホームページの<strong><a href="/schedule/day1"
                    style="color: var(--color-primary); text-decoration: underline;">スケジュールページ</a></strong>から、お目当てのキャストの出勤日時をご確認下さい。<br>
                スケジュールに掲載分の予定は事前予約も可能です。<br>
                電話予約は10:30~2:00の間で受け付けております。<strong><a href="/yoyaku/"
                    style="color: var(--color-primary); text-decoration: underline;">ネット予約</a></strong>は24時間受け付けております。</li>
              <li>ホテル入室前に入室予定のホテルのエリアとホテル名を当店受付に伝えていただけると案内はスムーズです。その際にキャストの待ち時間などもお伝えいたします。</li>
              <li>キャストの到着時間前にホテルに入室して入室後は速やかにホテル名と部屋番号を当店受付にお伝え下さい。</li>
              <li>受付完了後はキャストが予定時刻に直接お部屋までお伺いいたします。</li>
            </ul>

            <!-- 近くのラブホテル -->
            <?php if (!empty($nearbyHotels)): ?>
              <h4 style="margin: 20px 0 12px; color: var(--color-primary); font-size: 18px; font-weight: bold;">
                <?php echo htmlspecialchars($nearbyHotelsTitle); ?>
              </h4>
              <?php foreach ($nearbyHotels as $hotel): ?>
                <a href="/app/front/hotel_list.php?hotel_id=<?php echo $hotel['id']; ?>"
                  style="display: block; padding: 10px; background: white; border-radius: 6px; text-decoration: none; border: 1px solid #ddd; margin-top: 8px;">
                  <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 18px; flex-shrink: 0;"><?php echo htmlspecialchars($hotel['symbol']); ?></span>
                    <div style="flex: 1; min-width: 0; overflow: hidden;">
                      <div
                        style="font-size: 14px; font-weight: bold; color: #333; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        <?php echo htmlspecialchars($hotel['name']); ?>
                      </div>
                      <div style="font-size: 12px; color: #666; margin-top: 2px;">
                        交通費：<?php echo htmlspecialchars($hotel['cost']); ?>
                      </div>
                    </div>
                    <span style="color: var(--color-primary); font-size: 16px; flex-shrink: 0;">→</span>
                  </div>
                </a>
              <?php endforeach; ?>
            <?php endif; ?>

          <?php elseif ($dispatchType === 'full'): ?>
            <?php
            $dispatchContent = !empty($tenantDispatchTexts['full']) ? $tenantDispatchTexts['full'] : get_default_dispatch_content('full');
            $dispatchContent = str_replace(['{{hotel_name}}', '{{area}}'], [h($selectedHotel['name'] ?? ''), h($selectedHotel['area'] ?? '')], $dispatchContent);
            echo $dispatchContent;
            ?>
            <!-- 近くのホテル（ビジネスホテルまたはラブホテル） -->
            <?php if (!empty($nearbyHotels)): ?>
              <h4 style="margin: 20px 0 12px; color: var(--color-primary); font-size: 18px; font-weight: bold;">
                <?php echo htmlspecialchars($nearbyHotelsTitle); ?>
              </h4>
              <?php foreach ($nearbyHotels as $hotel): ?>
                <a href="/app/front/hotel_list.php?hotel_id=<?php echo $hotel['id']; ?>"
                  style="display: block; padding: 10px; background: white; border-radius: 6px; text-decoration: none; border: 1px solid #ddd; margin-top: 8px;">
                  <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 18px; flex-shrink: 0;"><?php echo htmlspecialchars($hotel['symbol']); ?></span>
                    <div style="flex: 1; min-width: 0; overflow: hidden;">
                      <div
                        style="font-size: 14px; font-weight: bold; color: #333; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        <?php echo htmlspecialchars($hotel['name']); ?>
                      </div>
                      <div style="font-size: 12px; color: #666; margin-top: 2px;">
                        交通費：<?php echo htmlspecialchars($hotel['cost']); ?>
                      </div>
                    </div>
                    <span style="color: var(--color-primary); font-size: 16px; flex-shrink: 0;">→</span>
                  </div>
                </a>
              <?php endforeach; ?>
            <?php endif; ?>

          <?php elseif ($dispatchType === 'conditional'): ?>
            <?php
            $dispatchContent = !empty($tenantDispatchTexts['conditional']) ? $tenantDispatchTexts['conditional'] : get_default_dispatch_content('conditional');
            $dispatchContent = str_replace(['{{hotel_name}}', '{{area}}'], [h($selectedHotel['name'] ?? ''), h($selectedHotel['area'] ?? '')], $dispatchContent);
            echo $dispatchContent;
            ?>
            <!-- 近くのホテル（ビジネスホテルまたはラブホテル） -->
            <?php if (!empty($nearbyHotels)): ?>
              <h4 style="margin: 20px 0 12px; color: var(--color-primary); font-size: 18px; font-weight: bold;">
                <?php echo htmlspecialchars($nearbyHotelsTitle); ?>
              </h4>
              <?php foreach ($nearbyHotels as $hotel): ?>
                <a href="/app/front/hotel_list.php?hotel_id=<?php echo $hotel['id']; ?>"
                  style="display: block; padding: 10px; background: white; border-radius: 6px; text-decoration: none; border: 1px solid #ddd; margin-top: 8px;">
                  <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 18px; flex-shrink: 0;"><?php echo htmlspecialchars($hotel['symbol']); ?></span>
                    <div style="flex: 1; min-width: 0; overflow: hidden;">
                      <div
                        style="font-size: 14px; font-weight: bold; color: #333; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        <?php echo htmlspecialchars($hotel['name']); ?>
                      </div>
                      <div style="font-size: 12px; color: #666; margin-top: 2px;">
                        交通費：<?php echo htmlspecialchars($hotel['cost']); ?>
                      </div>
                    </div>
                    <span style="color: var(--color-primary); font-size: 16px; flex-shrink: 0;">→</span>
                  </div>
                </a>
              <?php endforeach; ?>
            <?php endif; ?>

          <?php elseif ($dispatchType === 'limited'): ?>
            <?php
            $dispatchContent = !empty($tenantDispatchTexts['limited']) ? $tenantDispatchTexts['limited'] : get_default_dispatch_content('limited');
            $dispatchContent = str_replace(['{{hotel_name}}', '{{area}}'], [h($selectedHotel['name'] ?? ''), h($selectedHotel['area'] ?? '')], $dispatchContent);
            echo $dispatchContent;
            ?>
            <!-- 近くのホテル（ビジネスホテルまたはラブホテル） -->
            <?php if (!empty($nearbyHotels)): ?>
              <h4 style="margin: 20px 0 12px; color: var(--color-primary); font-size: 18px; font-weight: bold;">
                <?php echo htmlspecialchars($nearbyHotelsTitle); ?>
              </h4>
              <?php foreach ($nearbyHotels as $hotel): ?>
                <a href="/app/front/hotel_list.php?hotel_id=<?php echo $hotel['id']; ?>"
                  style="display: block; padding: 10px; background: white; border-radius: 6px; text-decoration: none; border: 1px solid #ddd; margin-top: 8px;">
                  <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 18px; flex-shrink: 0;"><?php echo htmlspecialchars($hotel['symbol']); ?></span>
                    <div style="flex: 1; min-width: 0; overflow: hidden;">
                      <div
                        style="font-size: 14px; font-weight: bold; color: #333; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        <?php echo htmlspecialchars($hotel['name']); ?>
                      </div>
                      <div style="font-size: 12px; color: #666; margin-top: 2px;">
                        交通費：<?php echo htmlspecialchars($hotel['cost']); ?>
                      </div>
                    </div>
                    <span style="color: var(--color-primary); font-size: 16px; flex-shrink: 0;">→</span>
                  </div>
                </a>
              <?php endforeach; ?>
            <?php endif; ?>

          <?php else: ?>
            <?php
            $dispatchContent = !empty($tenantDispatchTexts['none']) ? $tenantDispatchTexts['none'] : get_default_dispatch_content('none');
            $dispatchContent = str_replace(['{{hotel_name}}', '{{area}}'], [h($selectedHotel['name'] ?? ''), h($selectedHotel['area'] ?? '')], $dispatchContent);
            echo $dispatchContent;
            ?>

            <!-- 近くのホテル（ビジネスホテルまたはラブホテル） -->
            <?php if (!empty($nearbyHotels)): ?>
              <h4 style="margin: 20px 0 12px; color: var(--color-primary); font-size: 18px; font-weight: bold;">
                <?php echo htmlspecialchars($nearbyHotelsTitle); ?>
              </h4>
              <?php foreach ($nearbyHotels as $hotel): ?>
                <a href="/app/front/hotel_list.php?hotel_id=<?php echo $hotel['id']; ?>"
                  style="display: block; padding: 10px; background: white; border-radius: 6px; text-decoration: none; border: 1px solid #ddd; margin-top: 8px;">
                  <div style="display: flex; align-items: center; gap: 8px;">
                    <span style="font-size: 18px; flex-shrink: 0;"><?php echo htmlspecialchars($hotel['symbol']); ?></span>
                    <div style="flex: 1; min-width: 0; overflow: hidden;">
                      <div
                        style="font-size: 14px; font-weight: bold; color: #333; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                        <?php echo htmlspecialchars($hotel['name']); ?>
                      </div>
                      <div style="font-size: 12px; color: #666; margin-top: 2px;">
                        交通費：<?php echo htmlspecialchars($hotel['cost']); ?>
                      </div>
                    </div>
                    <span style="color: var(--color-primary); font-size: 16px; flex-shrink: 0;">→</span>
                  </div>
                </a>
              <?php endforeach; ?>
            <?php endif; ?>

          <?php endif; ?>

        </div>
      </section>

    <?php } else { ?>
      <!-- 既存のリスト表示 -->
      <!-- パンくず -->
      <nav class="breadcrumb">
        <a href="/">ホーム</a><span>»</span><a href="/top">トップ</a><span>»</span>ホテルリスト |
      </nav>

      <!-- タイトルセクション -->
      <section class="title-section" style="padding-top: 24px;">
        <h1 style="font-size: 40px;">
          HOTEL LIST
        </h1>
        <h2 style="font-size: 20px;">
          デリヘルが呼べるビジネスホテル
        </h2>
        <div class="dot-line"
          style="height: 3px; width: 100%; margin: 0; background-image: radial-gradient(var(--color-primary) 3px, transparent 3px); background-size: 12px 7px; background-repeat: repeat-x; background-position: center;">
        </div>
      </section>

      <!-- メインコンテンツエリア -->
      <section class="main-content" style="min-height: calc(100vh - 300px);">

        <!-- エリア別説明（SEO強化） -->
        <div style="max-width: 900px; margin: 0 auto; padding: 24px 16px 0; text-align: left;">
          <h2 style="font-size: 20px; font-weight: 700; color: var(--color-text); margin: 0 0 12px 0; text-align: left;">
            福岡市・博多でデリヘルが呼べるビジネスホテル</h2>
          <p style="font-size: 14px; line-height: 1.7; color: var(--color-text); margin: 0 0 24px 0; text-align: left;">
            福岡市内の<strong>博多区</strong>や<strong>中央区</strong>など、各エリアのビジネスホテルでデリヘル「豊満倶楽部」をご利用いただけます。<br>
            <strong>デリヘルが呼べるビジネスホテル</strong>を博多駅周辺、中洲、天神エリア別にご案内。交通費や入室方法も詳しく掲載しています。
          </p>
        </div>

        <!-- フィルターセクション -->
        <div style="max-width: 900px; margin: 0 auto 20px auto; padding: 0 16px; text-align: left;">

          <!-- エリアと派遣状況フィルター（横並び） -->
          <div style="display: flex; gap: 16px; margin-bottom: 12px;">
            <!-- エリアフィルター（プルダウン） -->
            <div style="flex: 1;">
              <label for="areaFilter"
                style="display: block; margin-bottom: 6px; font-weight: bold; color: var(--color-text); font-size: 14px; text-align: left;">エリア</label>
              <select id="areaFilter"
                style="width: 100%; padding: 10px 12px; font-size: 16px; border: 2px solid #ddd; border-radius: 8px; outline: none; background: white; cursor: pointer;">
                <option value="all">すべて</option>
                <option value="博多区のビジネスホテル">博多区のビジネスホテル</option>
                <option value="中央区のビジネスホテル">中央区のビジネスホテル</option>
                <option value="その他エリアのビジネスホテル">その他のエリア</option>
                <option value="ラブホテル一覧">ラブホテル</option>
              </select>
            </div>

            <!-- 派遣状況フィルター（プルダウン・ビジネスホテルのみ） -->
            <div id="symbolFilterWrapper" style="flex: 1;">
              <label for="symbolFilter"
                style="display: block; margin-bottom: 6px; font-weight: bold; color: var(--color-text); font-size: 14px; text-align: left;">派遣状況</label>
              <select id="symbolFilter"
                style="width: 100%; padding: 10px 12px; font-size: 16px; border: 2px solid #ddd; border-radius: 8px; outline: none; background: white; cursor: pointer;">
                <option value="all">すべて</option>
                <option value="available">派遣可能</option>
                <option value="△">要確認</option>
                <option value="×">派遣不可</option>
              </select>
            </div>
          </div>

          <!-- 検索ボックス -->
          <div>
            <label for="hotelSearch"
              style="display: block; margin-bottom: 6px; font-weight: bold; color: var(--color-text); font-size: 14px; text-align: left;">ホテル名で検索</label>
            <input type="text" id="hotelSearch" placeholder="ホテル名を入力"
              style="width: 100%; padding: 10px 12px; font-size: 16px; border: 2px solid #ddd; border-radius: 8px; outline: none; transition: all 0.3s ease; background: white;">
          </div>

        </div>

        <!-- ホテルリスト（FAQ方式） -->
        <div class="faq-list" style="max-width: 900px; margin: 0 auto; padding: 0 16px; padding-bottom: 24px;">
          <?php
          // 全てのホテルを表示（◯、×、△、※全て）
          $filteredHotels = $hotels;

          $grouped = [];
          $circles = ['◯', '○', '〇', '◎', '●'];
          foreach ($filteredHotels as $hotel) {
            $hotel['symbol'] = trim($hotel['symbol'] ?? '');
            // ラブホテルの場合はシンボルを統合
            if ($hotel['is_love_hotel'] == 1 && (empty($hotel['symbol']) || !in_array($hotel['symbol'], array_merge($circles, ['※', '△', '×'])))) {
              $hotel['symbol'] = '◯';
            }
            if (in_array($hotel['symbol'], $circles)) {
              $hotel['symbol'] = '◯';
            }
            $grouped[$hotel['area']][] = $hotel;
          }

          // 各エリアのホテルを個別のfaq-itemとして表示
          foreach ($grouped as $areaName => $list) {
            $isLoveHotel = false;
            foreach ($list as $hotel) {
              if ($hotel['is_love_hotel'] == 1) {
                $isLoveHotel = true;
                break;
              }
            }

            foreach ($list as $hotel) {
              $hotelBgColor = $isLoveHotel ? '#FC41B4' : '#0BACFE';
              $hotelItemBgColor = $isLoveHotel ? '#FF85D1' : '#0050BA';
              $iconColor = $isLoveHotel ? '#F568DF' : '#0BACFE';
              $linkColor = $isLoveHotel ? '#F568DF' : '#0BACFE';
              ?>
              <div class="faq-item hotel-item" data-category="<?php echo htmlspecialchars($areaName); ?>"
                data-hotel-name="<?php echo htmlspecialchars($hotel['name']); ?>"
                data-address="<?php echo htmlspecialchars($hotel['address']); ?>"
                data-symbol="<?php echo htmlspecialchars($hotel['symbol'] ?? ''); ?>"
                data-is-love-hotel="<?php echo $hotel['is_love_hotel']; ?>" style="margin-bottom: 3px;">
                <!-- ホテル見出し -->
                <div class="faq-question hotel-question" onclick="toggleHotelAnswer(this)"
                  style="background: linear-gradient(135deg, <?php echo $hotelBgColor; ?> 0%, <?php echo $hotelItemBgColor; ?> 100%);">
                  <?php echo htmlspecialchars($hotel['symbol'] ? $hotel['symbol'] . ' ' . $hotel['name'] : $hotel['name']); ?>
                </div>

                <!-- ホテル詳細 -->
                <div class="faq-answer hotel-answer">
                  <div class="faq-answer-content hotel-answer-content">
                    <div
                      style="padding: 20px; background-color: rgba(255, 255, 255, 0.4); border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                      <p style="font-size: 16px; font-weight: normal; line-height: 1.4; margin: 0; text-align: left;">
                        <strong>交通費:</strong> <?php echo htmlspecialchars($hotel['cost']); ?>
                      </p>
                      <?php if ($hotel['method']) { ?>
                        <p style="font-size: 16px; font-weight: normal; line-height: 1.4; margin: 8px 0 0 0; text-align: left;">
                          <strong>案内方法:</strong> <?php echo htmlspecialchars($hotel['method']); ?>
                        </p>
                      <?php } ?>
                      <p
                        style="display: flex; align-items: center; margin: 12px 0 0 0; font-size: 16px; font-weight: normal; line-height: 1.4; text-align: left;">
                        <span class="material-icons"
                          style="margin-right: 8px; font-size: 20px; color: <?php echo $iconColor; ?>;">smartphone</span>
                        <a href="tel:<?php echo htmlspecialchars($hotel['phone']); ?>"
                          style="text-decoration: none; color: <?php echo $linkColor; ?>; font-weight: bold;"><?php echo htmlspecialchars($hotel['phone']); ?></a>
                      </p>
                      <p
                        style="display: flex; align-items: flex-start; margin: 12px 0 0 0; font-size: 16px; font-weight: normal; line-height: 1.4; text-align: left;">
                        <span class="material-icons"
                          style="margin-right: 8px; font-size: 20px; color: <?php echo $iconColor; ?>; margin-top: 2px;">map</span>
                        <a href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($hotel['address']); ?>"
                          target="_blank" rel="noreferrer" style="text-decoration: none; color: var(--color-text); flex: 1;">
                          <?php echo htmlspecialchars($hotel['address']); ?>
                        </a>
                      </p>
                      <!-- SEO対応：個別ホテルページへのリンク追加 -->
                      <?php if (isset($hotel['id'])) { ?>
                        <p style="display: flex; align-items: center; margin: 16px 0 0 0; font-size: 14px; text-align: left;">
                          <span class="material-icons"
                            style="margin-right: 8px; font-size: 18px; color: <?php echo $iconColor; ?>;">info</span>
                          <a href="/app/front/hotel_list.php?hotel_id=<?php echo $hotel['id']; ?>"
                            style="color: <?php echo $linkColor; ?>; text-decoration: none; font-weight: bold;">
                            このホテルの詳細ページを見る →
                          </a>
                        </p>
                      <?php } ?>
                    </div>
                  </div>
                </div>
              </div>
              <?php
            }
          }
          ?>
        </div>

        <style>
          /* Material Icons */
          .material-icons {
            font-family: 'Material Icons';
            font-weight: normal;
            font-style: normal;
            font-size: 24px;
            line-height: 1;
            letter-spacing: normal;
            text-transform: none;
            display: inline-block;
            white-space: nowrap;
            word-wrap: normal;
            direction: ltr;
            font-feature-settings: 'liga';
            -webkit-font-feature-settings: 'liga';
            -webkit-font-smoothing: antialiased;
          }

          /* フィルターセクション */
          #hotelSearch:focus,
          #areaFilter:focus,
          #symbolFilter:focus {
            border-color: #999;
          }

          /* FAQ方式のスタイル（完全にfaq.phpと同じ） */
          .faq-list {
            max-width: 900px;
            margin: 0 auto;
          }

          .faq-item {
            margin-bottom: 15px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(245, 104, 223, 0.1);
            overflow: hidden;
            transition: all 0.3s ease;
            border: 2px solid transparent;
          }

          .faq-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(245, 104, 223, 0.15);
            border-color: rgb(185, 234, 255) !important;
          }

          .faq-item.active {
            border-color: rgb(185, 234, 255) !important;
          }

          .faq-question {
            background: linear-gradient(135deg, #F568DF 0%, #ff6b9d 100%);
            color: white;
            padding: 20px 60px 20px 60px;
            cursor: pointer;
            font-weight: 600;
            position: relative;
            transition: all 0.3s ease;
            border: none;
            text-align: left;
          }

          .faq-question::after {
            content: "＋";
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 20px;
            font-weight: 700;
            transition: all 0.3s ease;
            width: 30px;
            height: 30px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
          }

          .faq-question.active::after {
            content: "－";
            background: rgba(255, 255, 255, 0.3);
          }

          .faq-question:hover {
            background: linear-gradient(135deg, #d147c4 0%, #e55a8a 100%);
          }

          .faq-answer {
            padding: 0;
            background: #f8f9fa;
            position: relative;
            overflow: hidden;
            max-height: 0;
            opacity: 0;
            transition: max-height 0.4s ease, opacity 0.3s ease;
          }

          .faq-answer.show {
            max-height: 2000px;
            opacity: 1;
          }

          .faq-answer-content {
            padding: 25px 60px 25px 60px;
            position: relative;
            text-align: left;
          }

          /* ホテル専用スタイル */
          .hotel-item .faq-answer.show {
            max-height: 500px;
          }

          .hotel-item .faq-question {
            padding: 15px 50px 15px 50px;
            font-size: 16px;
          }

          .hotel-item .faq-answer-content {
            padding: 20px 50px 20px 50px;
          }

          /* レスポンシブ対応 */
          @media (max-width: 768px) {

            /* フィルターセクションを縦並びに */
            div[style*="display: flex"] {
              flex-direction: column !important;
              gap: 12px !important;
            }

            #hotelSearch,
            #areaFilter,
            #symbolFilter {
              font-size: 14px;
              padding: 10px 12px;
            }

            label[for="areaFilter"],
            label[for="symbolFilter"],
            label[for="hotelSearch"] {
              font-size: 13px;
            }

            .faq-question {
              padding: 20px 50px 20px 20px;
              font-size: 14px;
            }

            .faq-answer-content {
              padding: 25px 20px 25px 20px;
            }

            .hotel-item .faq-question {
              padding: 15px 40px 15px 20px;
              font-size: 14px;
            }

            .hotel-item .faq-answer-content {
              padding: 20px;
            }

            .faq-question::after {
              right: 10px;
            }
          }
        </style>

        <script>
          // ホテルのアコーディオン開閉（FAQ方式）
          function toggleHotelAnswer(questionElement) {
            const answer = questionElement.nextElementSibling;
            const faqItem = questionElement.closest('.faq-item');
            const isActive = questionElement.classList.contains('active');

            // すべてのホテルを閉じる
            document.querySelectorAll('.hotel-question').forEach(q => q.classList.remove('active'));
            document.querySelectorAll('.hotel-answer').forEach(a => a.classList.remove('show'));
            document.querySelectorAll('.faq-item').forEach(item => item.classList.remove('active'));

            // クリックされたホテルを開く/閉じる
            if (!isActive) {
              questionElement.classList.add('active');
              answer.classList.add('show');
              faqItem.classList.add('active');
            }
          }

          // フィルタリング機能（プルダウン版）
          document.addEventListener('DOMContentLoaded', function () {
            const areaFilter = document.getElementById('areaFilter');
            const symbolFilter = document.getElementById('symbolFilter');
            const symbolFilterWrapper = document.getElementById('symbolFilterWrapper');
            const searchInput = document.getElementById('hotelSearch');
            const hotelItems = document.querySelectorAll('.hotel-item');

            // URLパラメータを読み取ってフィルタを初期化
            const urlParams = new URLSearchParams(window.location.search);
            const symbolParam = urlParams.get('symbolFilter');
            const areaParam = urlParams.get('areaFilter');
            const searchParam = urlParams.get('search');

            // URLパラメータからフィルタの初期値を設定
            if (symbolParam) {
              symbolFilter.value = symbolParam;
            }
            if (areaParam) {
              areaFilter.value = areaParam;
            }
            if (searchParam) {
              searchInput.value = searchParam;
            }

            // ラブホテルが選択された場合は派遣状況フィルターを非表示
            if (areaFilter.value === 'ラブホテル一覧') {
              symbolFilterWrapper.style.display = 'none';
              symbolFilter.value = 'all'; // リセット
            }

            // フィルタリングを実行する関数
            function applyFilters() {
              const selectedArea = areaFilter.value;
              const selectedSymbol = symbolFilter.value;
              const searchTerm = searchInput.value.toLowerCase();

              hotelItems.forEach(item => {
                const itemCategory = item.getAttribute('data-category');
                const itemSymbol = item.getAttribute('data-symbol');
                const itemName = item.getAttribute('data-hotel-name').toLowerCase();

                // エリアフィルター
                const categoryMatch = selectedArea === 'all' || itemCategory === selectedArea;

                // 派遣状況フィルター
                let symbolMatch = true;
                if (selectedSymbol === 'available') {
                  // 「派遣可能」：◯ または ※
                  symbolMatch = itemSymbol.includes('◯') || itemSymbol.includes('※');
                } else if (selectedSymbol !== 'all') {
                  // 「要確認」または「派遣不可」
                  symbolMatch = itemSymbol.includes(selectedSymbol);
                }

                // 検索フィルター（名前または住所に含む場合）
                const itemAddress = item.getAttribute('data-address').toLowerCase();
                const searchMatch = searchTerm === '' || itemName.includes(searchTerm) || itemAddress.includes(searchTerm);

                // すべての条件を満たす場合のみ表示
                if (categoryMatch && symbolMatch && searchMatch) {
                  item.style.display = 'block';
                } else {
                  item.style.display = 'none';
                }
              });

              // フィルター切り替え時にすべてのアコーディオンを閉じる
              document.querySelectorAll('.hotel-question').forEach(q => q.classList.remove('active'));
              document.querySelectorAll('.hotel-answer').forEach(a => a.classList.remove('show'));
            }

            // URLパラメータが設定されている場合、初期フィルタを適用
            if (symbolParam || areaParam || searchParam) {
              applyFilters();
            }

            // エリアフィルター変更時
            areaFilter.addEventListener('change', function () {
              // ラブホテルが選択された場合は派遣状況フィルターを非表示
              if (this.value === 'ラブホテル一覧') {
                symbolFilterWrapper.style.display = 'none';
                symbolFilter.value = 'all'; // リセット
              } else {
                symbolFilterWrapper.style.display = 'block';
              }
              applyFilters();
            });

            // 派遣状況フィルター変更時
            symbolFilter.addEventListener('change', function () {
              applyFilters();
            });

            // 検索入力時
            searchInput.addEventListener('input', function () {
              applyFilters();
            });
          });
        </script>
      </section>
      <!-- セクション下の影 -->
      <div class="w-full h-[15px]"
        style="background-color:transparent; box-shadow:0 -8px 12px -4px rgba(0,0,0,0.2); position:relative;"></div>

    <?php } ?>
  </main>
  <?php include __DIR__ . '/includes/footer_nav.php'; ?>
  <?php include __DIR__ . '/includes/footer.php'; ?>
</body>

</html>