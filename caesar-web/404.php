<?php
// caesar-web/404.php - Official 404 Error Page for Caesar of Arabs
http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0a0806">
    <title>404 - الصفحة غير موجودة | قيصر العرب</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        :root {
            --bg-base: #0a0806;
            --gold-bright: #ffea9f;
            --gold-main: #dfba6b;
            --gold-dark: #8c6721;
            --ruby-bright: #ff2b4e;
            --ruby-main: #a3102c;
            --border-gold: #57462a;
            --text-primary: #fffaee;
            --text-secondary: #d1c1a5;
            --text-muted: #96866e;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Cairo', Tahoma, Arial, sans-serif;
        }

        body {
            background-color: var(--bg-base);
            background-image: url("/assets/brand/caesar-hero.jpg");
            background-position: center center;
            background-size: cover;
            background-repeat: no-repeat;
            background-attachment: fixed;
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            position: relative;
            overflow-x: hidden;
        }

        /* الطبقة السينمائية المظلمة */
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            background: radial-gradient(circle at 50% 45%, rgba(10, 8, 6, 0.82) 0%, rgba(5, 4, 3, 0.96) 100%);
            pointer-events: none;
            z-index: 0;
        }

        .error-card {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 620px;
            background: rgba(20, 23, 28, 0.92);
            border: 2px solid var(--border-gold);
            border-radius: 14px;
            padding: 44px 36px;
            text-align: center;
            box-shadow: 0 16px 45px rgba(0, 0, 0, 0.8), 0 0 30px rgba(223, 186, 107, 0.12);
            backdrop-filter: blur(8px);
            animation: fadeIn 0.4s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(18px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .icon-badge {
            width: 88px;
            height: 88px;
            margin: 0 auto 20px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(163, 16, 44, 0.35) 0%, rgba(10, 8, 6, 0.85) 100%);
            border: 2px solid var(--ruby-main);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 38px;
            color: var(--ruby-bright);
            box-shadow: 0 0 20px rgba(255, 43, 78, 0.3);
        }

        .error-code {
            font-size: 80px;
            font-weight: 900;
            line-height: 1;
            background: linear-gradient(180deg, #fffaee 20%, var(--gold-main) 70%, var(--gold-dark) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 12px;
            letter-spacing: 2px;
            text-shadow: 0 4px 18px rgba(223, 186, 107, 0.25);
        }

        .error-title {
            font-size: 22px;
            font-weight: 800;
            color: var(--gold-bright);
            margin-bottom: 12px;
        }

        .error-desc {
            font-size: 14.5px;
            color: var(--text-secondary);
            line-height: 1.7;
            margin-bottom: 30px;
            max-width: 480px;
            margin-left: auto;
            margin-right: auto;
        }

        .requested-path {
            direction: ltr;
            display: inline-block;
            background: rgba(0, 0, 0, 0.5);
            border: 1px dashed var(--border-gold);
            color: var(--gold-bright);
            padding: 4px 12px;
            border-radius: 4px;
            font-family: Consolas, monospace;
            font-size: 13px;
            margin-top: 6px;
            word-break: break-all;
        }

        .actions-group {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 22px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: linear-gradient(180deg, #a3102c 0%, #580413 100%);
            border: 1.5px solid #ff2b4e;
            color: #fffaee;
            box-shadow: 0 4px 15px rgba(163, 16, 44, 0.4);
        }

        .btn-primary:hover {
            background: linear-gradient(180deg, #c41838 0%, #750519 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 43, 78, 0.5);
        }

        .btn-secondary {
            background: rgba(0, 0, 0, 0.45);
            border: 1.5px solid var(--border-gold);
            color: var(--gold-bright);
        }

        .btn-secondary:hover {
            border-color: var(--gold-main);
            background: rgba(223, 186, 107, 0.12);
            color: #fff;
            transform: translateY(-2px);
        }

        .footer-note {
            margin-top: 28px;
            font-size: 12px;
            color: var(--text-muted);
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            padding-top: 16px;
        }

        @media (max-width: 600px) {
            .error-card { padding: 32px 20px; }
            .error-code { font-size: 64px; }
            .error-title { font-size: 19px; }
            .actions-group { flex-direction: column; }
            .btn { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>

    <div class="error-card">
        <div class="icon-badge">
            <i class="fa-solid fa-compass-slash"></i>
        </div>

        <div class="error-code">404</div>
        <h1 class="error-title">عفواً أيها القائد.. لقد ضللت الطريق!</h1>

        <p class="error-desc">
            الصفحة أو المورد الذي تحاول الوصول إليه غير موجود في الإمبراطورية أو ربما تم نقله.
            <br>
            <span class="requested-path"><?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
        </p>

        <div class="actions-group">
            <a href="/server.php" class="btn btn-primary">
                <i class="fa-solid fa-gamepad"></i> العودة إلى اللعبة
            </a>
            <a href="/index.php" class="btn btn-secondary">
                <i class="fa-solid fa-landmark"></i> الصفحة الرئيسية
            </a>
            <a href="/profile.php" class="btn btn-secondary">
                <i class="fa-solid fa-id-card"></i> الملف الشخصي
            </a>
        </div>

        <div class="footer-note">
            قيصر العرب &copy; <?= date('Y') ?> - جميع الحقوق محفوظة
        </div>
    </div>

</body>
</html>
