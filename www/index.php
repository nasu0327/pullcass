<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>pullcass（プルキャス）- デリヘル向けホームページ作成サービス</title>
    <meta name="description" content="pullcassは、デリヘル店舗向けのホームページ作成・運用サービスです。キャスト管理、スケジュール、料金表示などすべてお任せください。">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Zen+Kaku+Gothic+New:wght@400;500;700;900&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #ff6b9d;
            --primary-dark: #e91e63;
            --secondary: #7c4dff;
            --dark: #1a1a2e;
            --darker: #0f0f1a;
            --light: #ffffff;
            --gray: #a0a0b0;
        }

        body {
            font-family: 'Zen Kaku Gothic New', sans-serif;
            background: var(--darker);
            color: var(--light);
            line-height: 1.8;
            overflow-x: hidden;
        }

        /* ヒーローセクション */
        .hero {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 40px 20px;
            position: relative;
            background: 
                radial-gradient(ellipse at 20% 80%, rgba(255, 107, 157, 0.15) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 20%, rgba(124, 77, 255, 0.15) 0%, transparent 50%),
                var(--darker);
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="1" fill="rgba(255,255,255,0.03)"/></svg>');
            background-size: 50px 50px;
            pointer-events: none;
        }

        .logo {
            font-size: clamp(3rem, 10vw, 6rem);
            font-weight: 900;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
            letter-spacing: -2px;
            animation: fadeInUp 1s ease;
        }

        .tagline {
            font-size: clamp(1rem, 3vw, 1.5rem);
            color: var(--gray);
            margin-bottom: 40px;
            animation: fadeInUp 1s ease 0.2s both;
        }

        .hero-description {
            max-width: 700px;
            font-size: 1.1rem;
            color: var(--gray);
            margin-bottom: 50px;
            animation: fadeInUp 1s ease 0.4s both;
        }

        .cta-buttons {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            justify-content: center;
            animation: fadeInUp 1s ease 0.6s both;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 18px 40px;
            font-size: 1.1rem;
            font-weight: 700;
            text-decoration: none;
            border-radius: 50px;
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: var(--light);
            box-shadow: 0 10px 40px rgba(255, 107, 157, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 50px rgba(255, 107, 157, 0.4);
        }

        .btn-outline {
            background: transparent;
            color: var(--light);
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        /* 特徴セクション */
        .features {
            padding: 120px 20px;
            background: var(--dark);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .section-title {
            text-align: center;
            font-size: clamp(2rem, 5vw, 3rem);
            font-weight: 900;
            margin-bottom: 20px;
        }

        .section-subtitle {
            text-align: center;
            color: var(--gray);
            margin-bottom: 60px;
            font-size: 1.1rem;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }

        .feature-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 20px;
            padding: 40px;
            transition: all 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            border-color: rgba(255, 107, 157, 0.3);
            background: rgba(255, 255, 255, 0.05);
        }

        .feature-icon {
            font-size: 3rem;
            margin-bottom: 20px;
        }

        .feature-title {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 15px;
        }

        .feature-description {
            color: var(--gray);
            font-size: 1rem;
        }

        /* 料金セクション */
        .pricing {
            padding: 120px 20px;
            background: var(--darker);
        }

        .pricing-card {
            max-width: 500px;
            margin: 0 auto;
            background: linear-gradient(135deg, rgba(255, 107, 157, 0.1), rgba(124, 77, 255, 0.1));
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 30px;
            padding: 50px 40px;
            text-align: center;
        }

        .pricing-label {
            display: inline-block;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: var(--light);
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 700;
            margin-bottom: 30px;
        }

        .pricing-price {
            font-size: 4rem;
            font-weight: 900;
            margin-bottom: 10px;
        }

        .pricing-price span {
            font-size: 1.5rem;
            font-weight: 500;
        }

        .pricing-note {
            color: var(--gray);
            margin-bottom: 30px;
        }

        .pricing-features {
            text-align: left;
            margin-bottom: 40px;
        }

        .pricing-features li {
            list-style: none;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .pricing-features li::before {
            content: '✓';
            color: var(--primary);
            font-weight: bold;
        }

        /* お問い合わせセクション */
        .contact {
            padding: 120px 20px;
            background: var(--dark);
            text-align: center;
        }

        .contact-description {
            color: var(--gray);
            margin-bottom: 40px;
            font-size: 1.1rem;
        }

        /* フッター */
        .footer {
            padding: 40px 20px;
            background: var(--darker);
            text-align: center;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }

        .footer-logo {
            font-size: 1.5rem;
            font-weight: 900;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 15px;
        }

        .footer-text {
            color: var(--gray);
            font-size: 0.9rem;
        }

        /* アニメーション */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* レスポンシブ */
        @media (max-width: 768px) {
            .hero {
                padding: 60px 20px;
            }

            .cta-buttons {
                flex-direction: column;
                width: 100%;
                max-width: 300px;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .features, .pricing, .contact {
                padding: 80px 20px;
            }

            .pricing-card {
                padding: 40px 25px;
            }

            .pricing-price {
                font-size: 3rem;
            }
        }
    </style>
</head>
<body>
    <!-- ヒーローセクション -->
    <section class="hero">
        <h1 class="logo">pullcass</h1>
        <p class="tagline">プルキャス - デリヘル向けHP作成サービス</p>
        <p class="hero-description">
            キャスト管理、スケジュール、料金表示、写メ日記まで。<br>
            デリヘル店舗に必要なすべてを、かんたんに。
        </p>
        <div class="cta-buttons">
            <a href="#contact" class="btn btn-primary">
                🚀 お問い合わせ
            </a>
            <a href="#features" class="btn btn-outline">
                詳しく見る
            </a>
        </div>
    </section>

    <!-- 特徴セクション -->
    <section class="features" id="features">
        <div class="container">
            <h2 class="section-title">pullcassの特徴</h2>
            <p class="section-subtitle">店舗運営をもっとシンプルに</p>
            
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">👩‍💼</div>
                    <h3 class="feature-title">キャスト管理</h3>
                    <p class="feature-description">
                        プロフィール、写真、スケジュールをかんたんに登録・更新。キャスト自身がスマホから操作も可能です。
                    </p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">📅</div>
                    <h3 class="feature-title">出勤スケジュール</h3>
                    <p class="feature-description">
                        リアルタイムで更新される出勤表。お客様はいつでも最新の情報を確認できます。
                    </p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">💰</div>
                    <h3 class="feature-title">料金システム</h3>
                    <p class="feature-description">
                        コース料金、オプション、指名料など、複雑な料金体系もわかりやすく表示します。
                    </p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">📝</div>
                    <h3 class="feature-title">写メ日記</h3>
                    <p class="feature-description">
                        キャストが日記を投稿。お客様とのコミュニケーションを促進し、リピーターを増やします。
                    </p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">🎨</div>
                    <h3 class="feature-title">デザインカスタマイズ</h3>
                    <p class="feature-description">
                        店舗のイメージに合わせてカラーやレイアウトを自由にカスタマイズできます。
                    </p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">📱</div>
                    <h3 class="feature-title">スマホ対応</h3>
                    <p class="feature-description">
                        スマートフォンに最適化されたデザイン。どのデバイスからも美しく表示されます。
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- 料金セクション -->
    <section class="pricing" id="pricing">
        <div class="container">
            <h2 class="section-title">料金プラン</h2>
            <p class="section-subtitle">シンプルでわかりやすい料金体系</p>
            
            <div class="pricing-card">
                <div class="pricing-label">スタンダードプラン</div>
                <div class="pricing-price">お問い合わせ<span></span></div>
                <p class="pricing-note">※初期費用・サポート費用込み</p>
                
                <ul class="pricing-features">
                    <li>独自ドメイン対応</li>
                    <li>キャスト管理（無制限）</li>
                    <li>出勤スケジュール</li>
                    <li>料金システム</li>
                    <li>写メ日記機能</li>
                    <li>デザインカスタマイズ</li>
                    <li>SSL証明書（HTTPS）</li>
                    <li>メール・LINEサポート</li>
                </ul>
                
                <a href="#contact" class="btn btn-primary" style="width: 100%;">
                    お問い合わせ
                </a>
            </div>
        </div>
    </section>

    <!-- お問い合わせセクション -->
    <section class="contact" id="contact">
        <div class="container">
            <h2 class="section-title">お問い合わせ</h2>
            <p class="contact-description">
                導入のご相談、お見積もりなど、お気軽にお問い合わせください。
            </p>
            
            <a href="mailto:info@pullcass.com" class="btn btn-primary">
                ✉️ info@pullcass.com
            </a>
        </div>
    </section>

    <!-- フッター -->
    <footer class="footer">
        <div class="footer-logo">pullcass</div>
        <p class="footer-text">&copy; 2026 pullcass. All rights reserved.</p>
    </footer>
</body>
</html>
