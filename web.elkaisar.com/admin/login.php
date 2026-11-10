<?php
// web.elkaisar.com/admin/login.php
require_once __DIR__ . '/auth.php';

$error = '';
$adminCsrfToken = htmlspecialchars($_SESSION['admin_csrf_token'], ENT_QUOTES, 'UTF-8');
if (isLoggedIn()) {
    requireLogin();
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidAdminCsrf();
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!is_string($username) || !is_string($password) || trim($username) === '' || $password === '') {
        $error = 'يرجى إدخال اسم المستخدم وكلمة المرور';
    } else {
        $username = trim($username);
        $db = getDB();
        $rateLimitIdentity = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        if (consumeAuthRateLimit($db, 'admin_login', $rateLimitIdentity, 10)) {
            $error = 'محاولات دخول كثيرة. حاول بعد 15 دقيقة.';
        } else {
            $stmt = $db->prepare("SELECT * FROM `admin_user` WHERE `username` = ? AND `status` = 1 LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            $knownDefaultPassword = hash_equals('admin123', $password);
            $passwordValid = $user && strlen($password) <= 72
                && !$knownDefaultPassword && password_verify($password, $user['password']);
            if ($passwordValid) {
                clearAuthRateLimit($db, 'admin_login', $rateLimitIdentity);
                session_regenerate_id(true);
                $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
                $_SESSION['admin_session_expires_at'] = time() + ELKAISAR_SESSION_TTL;
                $db->prepare("UPDATE `admin_user` SET `last_login` = NOW() WHERE `id_admin` = ?")->execute([$user['id_admin']]);

                $_SESSION['admin_user'] = [
                    'id_admin'    => (int)$user['id_admin'],
                    'username'    => $user['username'],
                    'name'        => $user['name'],
                    'role'        => $user['role'],
                    'permissions' => json_decode($user['permissions'] ?: '{}', true)
                ];

                logAdminAction('login', 'system', $user['id_admin'], $user['username'], 'تسجيل دخول ناجح إلى لوحة التحكم');

                header('Location: index.php');
                exit;
            } else {
                $error = 'بيانات الدخول غير صحيحة أو الحساب معطل';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#120c08">
    <title>تسجيل دخول الإدارة | القيصر العربي</title>
    
    <!-- الخط الموحد وأيقونات FontAwesome -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        :root {
            --gold-bright: #ffea9f;
            --gold-main: #dfba6b;
            --gold-dark: #8c6721;
            --ruby-bright: #ff2b4e;
            --ruby-main: #a3102c;
            --ruby-dark: #580413;
            --text-gold: #dfba6b;
            --text-muted: #c7b596;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            user-select: none;
        }

        body {
            min-height: 100vh;
            width: 100vw;
            overflow-x: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Cairo', Tahoma, Arial, sans-serif;
            background-color: #0d0a08;
            /* المسار النسبي الدقيق من داخل مجلد admin */
            background-image: url("../assets/brand/caesar-hero.jpg");
            background-position: center center;
            background-size: cover;
            background-repeat: no-repeat;
            background-attachment: fixed;
            position: relative;
        }

        /* تظليل سينمائي ناعم */
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            background: radial-gradient(circle at center, rgba(10, 8, 7, 0.65) 0%, rgba(5, 4, 3, 0.85) 100%);
            pointer-events: none;
            z-index: 1;
        }

        .stage-wrapper {
            position: relative;
            z-index: 2;
            width: 100%;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 60px 16px 40px;
        }

        /* حاوية اللوحة الإمبراطورية */
        .tablet-container {
            position: relative;
            width: 100%;
            max-width: 430px;
            filter: drop-shadow(0 25px 50px rgba(0, 0, 0, 0.95)) drop-shadow(0 6px 18px rgba(0, 0, 0, 0.8));
        }

        /* الصفيحة الحجرية */
        .roman-tablet {
            position: relative;
            background: radial-gradient(ellipse at 50% 30%, #2b2f37 0%, #1c1f24 55%, #131518 100%);
            border: 4px solid #4a4d53;
            border-radius: 8px;
            box-shadow: 
                inset 0 0 0 2px #26272b,
                inset 0 0 28px rgba(0,0,0,0.92),
                0 0 0 2px #121315;
            padding: 30px 28px 26px 28px;
        }

        .tablet-outer-border {
            position: absolute;
            inset: -9px;
            border: 3px solid #36393f;
            border-radius: 12px;
            pointer-events: none;
            box-shadow: inset 0 0 8px rgba(0,0,0,0.85), 0 0 0 1px #111214;
        }

        /* براغي التثبيت الزاوية */
        .tablet-rivet {
            position: absolute;
            width: 13px;
            height: 13px;
            background: radial-gradient(circle at 35% 35%, #a6acb4 0%, #464a50 55%, #1f2124 100%);
            border-radius: 50%;
            border: 1px solid #1c1e20;
            box-shadow: 0 2px 4px rgba(0,0,0,0.85);
            z-index: 5;
        }
        .rivet-tl { top: 6px; left: 6px; }
        .rivet-tr { top: 6px; right: 6px; }
        .rivet-bl { bottom: 6px; left: 6px; }
        .rivet-br { bottom: 6px; right: 6px; }

        /* جواهر الياقوت على أطراف الإطار */
        .ruby-gem-edge {
            position: absolute;
            width: 5px;
            height: 22px;
            background: linear-gradient(180deg, #ff576f 0%, #ba0a24 50%, #52000c 100%);
            border-radius: 3px;
            box-shadow: 0 0 12px #ff1a3c, 0 0 4px #ff7a8f;
            z-index: 4;
        }
        .gem-edge-left-top { left: -7px; top: 100px; }
        .gem-edge-left-bottom { left: -7px; bottom: 100px; }
        .gem-edge-right-top { right: -7px; top: 100px; }
        .gem-edge-right-bottom { right: -7px; bottom: 100px; }

        /* صندوق رسائل الخطأ */
        .alert-ruby {
            background: rgba(140, 16, 32, 0.75);
            border: 1px solid #ff4762;
            color: #ffeaeb;
            padding: 8px 12px;
            border-radius: 5px;
            font-size: 13px;
            font-weight: 700;
            text-align: center;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-shadow: 0 1px 2px #000;
            box-shadow: 0 3px 10px rgba(255, 43, 78, 0.3);
        }

        /* حقول الإدخال الرومانية المقوسة */
        .field-group {
            margin-bottom: 16px;
            position: relative;
        }

        .input-cartouche {
            position: relative;
            display: flex;
            align-items: center;
            height: 50px;
            background: linear-gradient(180deg, #0d0f12 0%, #171a1f 50%, #0d0f12 100%);
            border: 1.5px solid #6c552e;
            border-radius: 6px;
            box-shadow: inset 0 2px 6px rgba(0,0,0,0.9), 0 0 0 1px #221a0d;
            transition: all 0.25s ease;
        }

        .input-cartouche::before,
        .input-cartouche::after {
            content: "";
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 10px;
            height: 22px;
            border: 2px solid #caa055;
            pointer-events: none;
            opacity: 0.85;
            transition: all 0.25s ease;
        }
        .input-cartouche::before { right: 4px; border-left: none; border-radius: 0 4px 4px 0; }
        .input-cartouche::after { left: 4px; border-right: none; border-radius: 4px 0 0 4px; }

        .input-cartouche:hover,
        .input-cartouche:focus-within {
            border-color: #dfba6b;
            box-shadow: inset 0 2px 6px rgba(0,0,0,0.9), 0 0 14px rgba(223, 186, 107, 0.35);
        }

        .input-cartouche input {
            width: 100%;
            height: 100%;
            background: transparent;
            border: none;
            outline: none;
            color: #fffaee;
            font-family: 'Cairo', Tahoma, sans-serif;
            font-size: 15px;
            font-weight: 700;
            text-align: center;
            padding: 0 24px;
            user-select: text;
        }

        .input-cartouche input::placeholder {
            color: #9b8a6e;
            font-weight: 500;
            transition: opacity 0.2s;
        }

        .input-cartouche input:focus::placeholder {
            opacity: 0.3;
        }

        /* الزر القرمزي الإمبراطوري */
        .btn-ruby-royal {
            position: relative;
            width: 100%;
            height: 50px;
            margin-top: 14px;
            margin-bottom: 12px;
            background: linear-gradient(180deg, #b81734 0%, #870b22 50%, #520412 100%);
            border: 1.5px solid #e5ba63;
            border-radius: 5px;
            color: #fff6e0;
            font-family: 'Cairo', sans-serif;
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 0.5px;
            cursor: pointer;
            outline: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 
                0 6px 16px rgba(0, 0, 0, 0.75),
                inset 0 1px 1px rgba(255, 255, 255, 0.35),
                inset 0 -2px 6px rgba(0, 0, 0, 0.6);
            transition: all 0.2s ease;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.9), 0 0 10px rgba(255, 120, 140, 0.4);
        }

        .btn-ruby-royal::before,
        .btn-ruby-royal::after {
            content: "◆";
            position: absolute;
            font-size: 11px;
            color: #ffea9f;
            filter: drop-shadow(0 0 3px #ff2a4b);
            top: 50%;
            transform: translateY(-50%);
        }
        .btn-ruby-royal::before { right: 12px; }
        .btn-ruby-royal::after { left: 12px; }

        .btn-ruby-royal:hover {
            background: linear-gradient(180deg, #cc1c3b 0%, #9e0e29 50%, #610717 100%);
            border-color: #ffea9f;
            box-shadow: 0 6px 20px rgba(184, 23, 52, 0.55), inset 0 1px 2px rgba(255, 255, 255, 0.5);
            transform: translateY(-1px);
        }

        .btn-ruby-royal:active {
            transform: translateY(1px);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.9);
        }

        /* رابط العودة للعبة */
        .back-link-box {
            text-align: center;
            margin-top: 10px;
        }

        .back-game-link {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: var(--text-muted);
            font-size: 13.5px;
            font-weight: 600;
            text-decoration: none;
            transition: color 0.2s, text-shadow 0.2s;
        }

        .back-game-link:hover {
            color: var(--gold-bright);
            text-shadow: 0 0 8px rgba(223, 186, 107, 0.6);
        }

        /* الحلية السفلية */
        .bottom-ornament {
            position: absolute;
            bottom: -18px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            flex-direction: column;
            align-items: center;
            z-index: 8;
        }

        .bottom-bracket {
            width: 80px;
            height: 12px;
            background: linear-gradient(180deg, #5a5e66, #2d3036);
            border: 1px solid #737882;
            border-top: none;
            clip-path: polygon(0 0, 100% 0, 80% 100%, 20% 100%);
        }

        .bottom-ruby {
            margin-top: -5px;
            width: 14px;
            height: 20px;
            background: radial-gradient(circle at 40% 30%, #ff6b85 0%, #b50727 60%, #52000d 100%);
            border-radius: 50% 50% 50% 50% / 60% 60% 40% 40%;
            border: 1px solid #ffd1d9;
            box-shadow: 0 0 12px #ff1e43, 0 3px 6px rgba(0,0,0,0.85);
        }

        @media (max-width: 480px) {
            .tablet-container { max-width: 100%; }
            .roman-tablet { padding: 30px 20px 22px 20px; }
        }
    </style>
</head>
<body>

<main class="stage-wrapper">
    <div class="tablet-container">
        
        <!-- اللوحة الحجرية الرومانية -->
        <div class="roman-tablet">
            <div class="tablet-outer-border" aria-hidden="true"></div>
            
            <!-- براغي الزوايا -->
            <div class="tablet-rivet rivet-tl" aria-hidden="true"></div>
            <div class="tablet-rivet rivet-tr" aria-hidden="true"></div>
            <div class="tablet-rivet rivet-bl" aria-hidden="true"></div>
            <div class="tablet-rivet rivet-br" aria-hidden="true"></div>

            <!-- جواهر الياقوت على الأطراف -->
            <div class="ruby-gem-edge gem-edge-left-top" aria-hidden="true"></div>
            <div class="ruby-gem-edge gem-edge-left-bottom" aria-hidden="true"></div>
            <div class="ruby-gem-edge gem-edge-right-top" aria-hidden="true"></div>
            <div class="ruby-gem-edge gem-edge-right-bottom" aria-hidden="true"></div>

            <!-- رسالة الخطأ إن وُجدت -->
            <?php if ($error): ?>
                <div class="alert-ruby" role="alert">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            <?php endif; ?>

            <!-- نموذج الدخول الإداري -->
            <form method="POST" action="login.php" autocomplete="on">
                <input type="hidden" name="csrf_token" value="<?= $adminCsrfToken ?>">
                
                <!-- حقل اسم المستخدم الإداري -->
                <div class="field-group">
                    <div class="input-cartouche">
                        <input type="text" name="username" placeholder="اسم المستخدم الإداري" required autofocus>
                    </div>
                </div>

                <!-- حقل كلمة المرور -->
                <div class="field-group">
                    <div class="input-cartouche">
                        <input type="password" name="password" placeholder="كلمة المرور" required>
                    </div>
                </div>

                <!-- زر تسجيل الدخول الإمبراطوري -->
                <button type="submit" class="btn-ruby-royal">
                    <span>دخول لوحة التحكم</span>
                    <i class="fa-solid fa-arrow-left"></i>
                </button>
            </form>

            <!-- رابط العودة إلى واجهة اللعبة -->
            <div class="back-link-box">
                <a href="../server.php" class="back-game-link">
                    <i class="fa-solid fa-shield-halved"></i>
                    <span>العودة إلى واجهة اللعبة</span>
                </a>
            </div>

            <!-- الجوهرة السفلية المعلقة -->
            <div class="bottom-ornament" aria-hidden="true">
                <div class="bottom-bracket"></div>
                <div class="bottom-ruby"></div>
            </div>
        </div>
    </div>
</main>

</body>
</html>