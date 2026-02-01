<?php
/**
 * 404エラーページ
 */
?>
<!DOCTYPE html>
<html lang="ja">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ページが見つかりません | pullcass</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            text-align: center;
            max-width: 500px;
        }

        .error-code {
            font-size: 120px;
            font-weight: 700;
            background: linear-gradient(135deg, #ff6b9d 0%, #c84b8a 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
            margin-bottom: 20px;
        }

        .error-icon {
            font-size: 60px;
            color: rgba(255, 255, 255, 0.3);
            margin-bottom: 30px;
        }

        h1 {
            color: #fff;
            font-size: 1.5rem;
            margin-bottom: 15px;
        }

        p {
            color: rgba(255, 255, 255, 0.7);
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 28px;
            background: linear-gradient(135deg, #ff6b9d 0%, #c84b8a 100%);
            color: #fff;
            text-decoration: none;
            border-radius: 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(255, 107, 157, 0.3);
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="error-code">404</div>
        <div class="error-icon">
            <i class="fas fa-file-circle-question"></i>
        </div>
        <h1>ページが見つかりません</h1>
        <p>
            お探しのページは存在しないか、削除された可能性があります。<br>
            URLをご確認の上、もう一度お試しください。
        </p>
        <a href="/" class="btn">
            <i class="fas fa-home"></i>
            トップページへ戻る
        </a>
    </div>
</body>

</html>