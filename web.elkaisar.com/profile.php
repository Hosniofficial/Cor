<?php
require_once __DIR__ . '/session.php';

$playerId = (int)($_SESSION['player_id'] ?? 0);
if ($playerId <= 0) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../app.elkaisar.com/db.php';
$db = getDB();
$servers = require __DIR__ . '/game-servers.php';
$notice = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (!is_string($submittedToken) || !is_string($sessionToken)
        || $sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
        http_response_code(419);
        $notice = 'انتهت صلاحية الصفحة. حدّثها ثم حاول مجدداً.';
    } else {
        $serverValue = $_POST['server_id'] ?? '';
        $serverId = is_string($serverValue)
            ? filter_var($serverValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : false;
        if ($serverId === false || empty($servers[$serverId]['available'])) {
            http_response_code(422);
            $notice = 'الخادم المحدد غير متاح حالياً.';
        } else {
            $stmt = $db->prepare('SELECT 1 FROM `user_auth` WHERE `id_player` = ? LIMIT 1');
            $stmt->execute([$playerId]);
            if (!$stmt->fetchColumn()) {
                http_response_code(409);
                $notice = 'تعذر حفظ الخادم لهذا الحساب.';
            } else {
                $db->beginTransaction();
                $db->prepare(
                    'UPDATE `user_auth`
                     SET `last_server_id` = ?, `last_server_entered_at` = CURRENT_TIMESTAMP
                     WHERE `id_player` = ?'
                )->execute([$serverId, $playerId]);
                $db->prepare('UPDATE `player` SET `online` = 1, `last_seen` = ? WHERE `id_player` = ?')
                    ->execute([time(), $playerId]);
                $db->commit();
                $_SESSION['selected_server_id'] = $serverId;
                header('Location: server.php');
                exit;
            }
        }
    }
}

$stmt = $db->prepare(
    'SELECT ua.username, ua.email, ua.last_server_id, ua.last_server_entered_at,
            p.name AS player_name, p.p_token
     FROM `user_auth` ua
     JOIN `player` p ON p.id_player = ua.id_player
     WHERE ua.id_player = ?
     ORDER BY ua.id_user DESC
     LIMIT 1'
);
$stmt->execute([$playerId]);
$account = $stmt->fetch();
if (!$account || !is_string($_SESSION['player_token'] ?? null)
    || !hash_equals((string)$account['p_token'], $_SESSION['player_token'])) {
    $_SESSION = [];
    header('Location: login.php?reason=account_replaced');
    exit;
}

$lastServerId = $account['last_server_id'] === null ? null : (int)$account['last_server_id'];
$lastServerName = $lastServerId !== null
    ? ($servers[$lastServerId]['name'] ?? 'خادم غير متاح')
    : 'لم تدخل أي خادم بعد';
$username = $account['username'] ?: $account['player_name'];
$csrfToken = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8');
$escapedUsername = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
$escapedEmail = htmlspecialchars($account['email'], ENT_QUOTES, 'UTF-8');
$escapedLastServer = htmlspecialchars($lastServerName, ENT_QUOTES, 'UTF-8');
$lastEnteredAt = $account['last_server_entered_at']
    ? htmlspecialchars($account['last_server_entered_at'], ENT_QUOTES, 'UTF-8')
    : '';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#120c08">
    <title>مقر القيادة | القيصر العربي – Caesar of Arabs</title>
    
    <!-- الخط الموحد للواجهة -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        :root {
            --gold-bright: #ffea9f;
            --gold-main: #dfba6b;
            --gold-dark: #8c6721;
            --ruby-bright: #ff2b4e;
            --ruby-main: #a3102c;
            --ruby-dark: #580413;
            --emerald-glow: #00ff73;
            --text-gold: #dfba6b;
            --text-muted: #c7b596;
            --stone-border: #4a4d53;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            width: 100vw;
            overflow-x: hidden;
            color: #fffaee;
            font-family: 'Cairo', Tahoma, Arial, sans-serif;
            background-color: #0d0a08;
            background-image: url("assets/brand/caesar-profile.jpg");
            background-position: center center;
            background-size: cover;
            background-repeat: no-repeat;
            background-attachment: fixed;
            position: relative;
        }

        /* تظليل سينمائي لإبراز الواجهة */
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            background: radial-gradient(circle at center, rgba(12, 10, 8, 0.72) 0%, rgba(6, 4, 3, 0.88) 100%);
            pointer-events: none;
            z-index: 1;
        }

        main {
            position: relative;
            z-index: 2;
            width: min(1040px, calc(100% - 32px));
            margin: 0 auto;
            padding: 40px 0 60px;
        }

        /* شريط العنوان العلوي الفخم */
        .page-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 30px;
            padding-bottom: 16px;
            border-bottom: 2px solid rgba(223, 186, 107, 0.25);
        }

        .header-title-box h1 {
            font-family: 'Cairo', Tahoma, Arial, sans-serif;
            font-size: clamp(26px, 4vw, 36px);
            font-weight: 700;
            background: linear-gradient(180deg, #fff7d6 0%, #ffd875 35%, #dfa030 70%, #875508 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.9));
            margin-bottom: 2px;
        }

        .header-title-box .english-sub {
            font-family: 'Cairo', Tahoma, Arial, sans-serif;
            font-size: 13px;
            letter-spacing: 0.12em;
            color: var(--gold-main);
            direction: ltr;
            text-align: right;
            opacity: 0.85;
        }

        /* زر تسجيل الخروج الروماني */
        .logout-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 20px;
            background: linear-gradient(180deg, #24282f 0%, #15171b 100%);
            border: 1.5px solid #8c6721;
            border-radius: 4px;
            color: var(--gold-bright);
            font-family: 'Cairo', sans-serif;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            box-shadow: 0 4px 10px rgba(0,0,0,0.6);
            transition: all 0.2s ease;
        }

        .logout-btn:hover {
            border-color: var(--gold-bright);
            background: linear-gradient(180deg, #323740 0%, #1e2126 100%);
            box-shadow: 0 0 14px rgba(223, 186, 107, 0.35);
            transform: translateY(-1px);
        }

        /* اللوحة الحجرية الكبرى للمحتوى */
        .roman-tablet-card {
            position: relative;
            background: radial-gradient(ellipse at 50% 20%, #2b2f37 0%, #1c1f24 55%, #131518 100%);
            border: 4px solid #4a4d53;
            border-radius: 8px;
            box-shadow: 
                inset 0 0 0 2px #26272b,
                inset 0 0 30px rgba(0,0,0,0.95),
                0 20px 50px rgba(0, 0, 0, 0.9);
            padding: clamp(24px, 4vw, 40px);
        }

        .tablet-outer-border {
            position: absolute;
            inset: -9px;
            border: 3px solid #36393f;
            border-radius: 12px;
            pointer-events: none;
            box-shadow: inset 0 0 8px rgba(0,0,0,0.85), 0 0 0 1px #111214;
        }

        /* براغي الزوايا */
        .tablet-rivet {
            position: absolute;
            width: 14px;
            height: 14px;
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

        /* عناوين الأقسام الذهبية المنقوشة */
        .section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .section-header::before {
            content: "◆";
            color: var(--gold-main);
            font-size: 14px;
            filter: drop-shadow(0 0 5px var(--gold-bright));
        }

        .section-title {
            font-family: 'Cairo', Tahoma, Arial, sans-serif;
            font-size: 22px;
            font-weight: 700;
            color: var(--gold-bright);
            text-shadow: 0 2px 4px rgba(0,0,0,0.8);
        }

        /* صندوق الإشعارات ورسائل الخطأ */
        .notice-alert {
            margin-bottom: 24px;
            padding: 10px 16px;
            background: rgba(140, 16, 32, 0.75);
            border: 1.5px solid #ff4762;
            border-radius: 5px;
            color: #ffeaeb;
            font-size: 14px;
            font-weight: 700;
            text-align: center;
            text-shadow: 0 1px 2px #000;
            box-shadow: 0 4px 12px rgba(255, 43, 78, 0.25);
        }
        .notice-alert:empty { display: none; }

        /* شبكة بيانات الحساب كأقواس حجرية */
        .profile-data-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 16px;
            margin-bottom: 40px;
        }

        .data-cartouche {
            position: relative;
            background: linear-gradient(180deg, #101216 0%, #171a20 50%, #101216 100%);
            border: 1.5px solid #6c552e;
            border-radius: 6px;
            padding: 14px 18px;
            box-shadow: inset 0 2px 8px rgba(0,0,0,0.85);
            transition: all 0.25s ease;
        }

        .data-cartouche::before,
        .data-cartouche::after {
            content: "";
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 8px;
            height: 24px;
            border: 2px solid #caa055;
            pointer-events: none;
            opacity: 0.85;
        }
        .data-cartouche::before { right: 4px; border-left: none; border-radius: 0 4px 4px 0; }
        .data-cartouche::after { left: 4px; border-right: none; border-radius: 4px 0 0 4px; }

        .data-cartouche:hover {
            border-color: var(--gold-main);
            box-shadow: inset 0 2px 8px rgba(0,0,0,0.85), 0 0 12px rgba(223, 186, 107, 0.25);
        }

        .data-label {
            display: block;
            margin-bottom: 6px;
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 600;
        }

        .data-value {
            color: #fffaee;
            font-size: 16px;
            font-weight: 800;
            overflow-wrap: anywhere;
        }

        .last-entered-date {
            margin-top: 6px;
            color: #a49479;
            font-size: 12px;
            font-weight: 500;
        }

        /* شبكة الخوادم المتاحة */
        .server-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        /* بطاقة الخادم الحجرية المستقلة */
        .server-fortress-card {
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 200px;
            background: linear-gradient(160deg, #22262d 0%, #15181c 60%, #0d0e11 100%);
            border: 2px solid #57462a;
            border-radius: 6px;
            padding: 22px 20px 18px;
            box-shadow: 
                inset 0 0 12px rgba(0,0,0,0.9),
                0 6px 18px rgba(0,0,0,0.7);
            transition: all 0.3s ease;
        }

        .server-fortress-card:hover {
            border-color: var(--gold-main);
            transform: translateY(-3px);
            box-shadow: 
                inset 0 0 12px rgba(0,0,0,0.9),
                0 10px 25px rgba(0,0,0,0.85),
                0 0 16px rgba(223, 186, 107, 0.3);
        }

        .server-name {
            font-family: 'Cairo', Tahoma, Arial, sans-serif;
            font-size: 22px;
            font-weight: 700;
            color: var(--gold-bright);
            margin-bottom: 8px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.8);
        }

        .server-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #9fe8b0;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            background-color: var(--emerald-glow);
            border-radius: 50%;
            box-shadow: 0 0 8px var(--emerald-glow);
        }

        /* الزر القرمزي الإمبراطوري المتطابق مع شاشة الدخول */
        .btn-ruby-royal {
            position: relative;
            width: 100%;
            height: 48px;
            background: linear-gradient(180deg, #b81734 0%, #870b22 50%, #520412 100%);
            border: 1.5px solid #e5ba63;
            border-radius: 5px;
            color: #fff6e0;
            font-family: 'Cairo', sans-serif;
            font-size: 16px;
            font-weight: 800;
            letter-spacing: 0.5px;
            cursor: pointer;
            outline: none;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 
                0 4px 12px rgba(0, 0, 0, 0.75),
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
            box-shadow: 
                0 6px 18px rgba(184, 23, 52, 0.55),
                inset 0 1px 2px rgba(255, 255, 255, 0.5);
            transform: translateY(-1px);
        }

        .btn-ruby-royal:active {
            transform: translateY(1px);
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.9);
        }

        /* التجاوب مع شاشات الهواتف والتابلت */
        @media (max-width: 650px) {
            main { padding: 25px 0 40px; }
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 14px;
            }
            .logout-btn { align-self: flex-start; }
            .profile-data-grid { grid-template-columns: 1fr; }
            .server-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<main>
    <!-- الترويسة الرئيسية -->
    <header class="page-header">
        <div class="header-title-box">
            <h1>مقر القيادة العامة</h1>
            <p class="english-sub">CAESAR OF ARABS – COMMAND CENTER</p>
        </div>
        <a class="logout-btn" href="logout.php">
            <span>تسجيل الخروج</span>
            <span aria-hidden="true">⮌</span>
        </a>
    </header>

    <!-- اللوح الروماني العظيم الحاوي للبيانات والخوادم -->
    <section class="roman-tablet-card" aria-labelledby="account-title">
        <div class="tablet-outer-border" aria-hidden="true"></div>
        
        <!-- مسامير التثبيت الزاوية الأربعة -->
        <div class="tablet-rivet rivet-tl" aria-hidden="true"></div>
        <div class="tablet-rivet rivet-tr" aria-hidden="true"></div>
        <div class="tablet-rivet rivet-bl" aria-hidden="true"></div>
        <div class="tablet-rivet rivet-br" aria-hidden="true"></div>

        <!-- رسالة التنبيه إن وُجدت -->
        <?php if ($notice !== ''): ?>
            <div class="notice-alert" role="alert"><?= htmlspecialchars($notice, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <!-- القسم الأول: سجل القائد وبيانات الحساب -->
        <div class="section-header">
            <h2 class="section-title" id="account-title">سجل القائد</h2>
        </div>

        <div class="profile-data-grid">
            <div class="data-cartouche">
                <span class="data-label">اسم المستخدم والقائد</span>
                <div class="data-value"><?= $escapedUsername ?></div>
            </div>

            <div class="data-cartouche">
                <span class="data-label">البريد الإلكتروني</span>
                <div class="data-value" dir="ltr"><?= $escapedEmail ?></div>
            </div>

            <div class="data-cartouche">
                <span class="data-label">آخر خادم دخلت إليه</span>
                <div class="data-value"><?= $escapedLastServer ?></div>
                <?php if ($lastEnteredAt !== ''): ?>
                    <p class="last-entered-date">تاريخ آخر دخول: <?= $lastEnteredAt ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- القسم الثاني: الخوادم المتاحة للحرب -->
        <div class="section-header">
            <h2 class="section-title">اختر خادم المعركة</h2>
        </div>

        <div class="server-grid">
            <?php foreach ($servers as $serverId => $server): ?>
                <?php if (empty($server['available'])) { continue; } ?>
                <form class="server-fortress-card" method="post" action="">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <input type="hidden" name="server_id" value="<?= (int)$serverId ?>">
                    
                    <div>
                        <h3 class="server-name"><?= htmlspecialchars($server['name'], ENT_QUOTES, 'UTF-8') ?></h3>
                        <div class="server-status-pill">
                            <span class="status-dot"></span>
                            <span>متاح للدخول الفوري</span>
                        </div>
                    </div>

                    <button class="btn-ruby-royal" type="submit">
                        دخول إلى هذا الخادم
                    </button>
                </form>
            <?php endforeach; ?>
        </div>
    </section>
</main>

<script>
    const playerSessionCheck = async () => {
        try {
            const response = await fetch('auth.php?action=check', {
                cache: 'no-store',
                credentials: 'same-origin'
            });
            if (!response.ok) {
                throw new Error(`Session check failed: ${response.status}`);
            }
            const session = await response.json();
            if (!session.logged_in && session.reason === 'account_replaced') {
                window.location.replace('login.php?reason=account_replaced');
            }
        } catch (error) {
            console.error('Unable to verify the account session:', error);
        }
    };

    window.setInterval(playerSessionCheck, 5000);
</script>

</body>
</html>