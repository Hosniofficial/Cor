<?php
require_once __DIR__ . '/session.php';

if (!empty($_SESSION['player_id'])) {
    header('Location: profile.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#120c08">
    <meta name="description" content="Caesar of Arabs – قيصر العرب. ابنِ مملكتك وقد جيوشك في عالم من الاستراتيجية والحروب.">
    <title>Caesar of Arabs – قيصر العرب | الصفحة الرئيسية</title>

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
            --text-gold: #dfba6b;
            --text-muted: #c7b596;
            --border-metal: #57462a;
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
            color: #fffaee;
            font-family: 'Cairo', Tahoma, Arial, sans-serif;
            background-color: #0d0a08;
            /* الخلفية المعتمدة للمشروع */
            background-image: url("assets/brand/caesar-hero.jpg");
            background-position: center center;
            background-size: cover;
            background-repeat: no-repeat;
            background-attachment: fixed;
            position: relative;
        }

        /* تظليل سينمائي خفيف لضمان بروز اللوحة الحجرية في اليسار مع وضوح ملامح القيصر في اليمين */
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            background: linear-gradient(90deg, rgba(8, 6, 5, 0.55) 0%, rgba(8, 6, 5, 0.2) 50%, rgba(8, 6, 5, 0.05) 100%);
            pointer-events: none;
            z-index: 1;
        }

        .site-header, main {
            position: relative;
            z-index: 2;
        }

        /* الشريط العلوي الملكي (Top Navigation Bar) */
        .site-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 82px;
            padding: 12px clamp(20px, 5vw, 84px);
            background: rgba(14, 16, 20, 0.65);
            backdrop-filter: blur(8px);
            border-bottom: 2px solid rgba(223, 186, 107, 0.25);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.7);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .brand-mark {
            display: grid;
            width: 44px;
            height: 44px;
            place-items: center;
            background: radial-gradient(circle, #5e4312 0%, #302008 65%, #140d04 100%);
            border: 2px solid #dfba6b;
            border-radius: 50%;
            color: #ffd875;
            font-size: 20px;
            box-shadow: 0 0 15px rgba(223, 186, 107, 0.45), inset 0 0 8px rgba(0,0,0,0.8);
        }

        .brand-info {
            display: flex;
            flex-direction: column;
        }

        .brand-name {
            font-family: 'Cairo', Tahoma, Arial, sans-serif;
            font-size: 22px;
            font-weight: 700;
            line-height: 1.1;
            background: linear-gradient(180deg, #fff7d6 0%, #ffd875 35%, #dfa030 70%, #875508 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            filter: drop-shadow(0 2px 3px rgba(0,0,0,0.9));
        }

        .brand-name small {
            font-family: 'Cairo', Tahoma, Arial, sans-serif;
            font-size: 11px;
            color: var(--gold-main);
            direction: ltr;
            letter-spacing: 0.12em;
            margin-top: 2px;
            text-align: right;
            opacity: 0.85;
        }

        .header-login {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 20px;
            background: linear-gradient(180deg, #252a33 0%, #15181e 100%);
            border: 1.5px solid var(--border-metal);
            border-radius: 4px;
            color: var(--gold-bright);
            font-family: 'Cairo', sans-serif;
            font-size: 13.5px;
            font-weight: 700;
            text-decoration: none;
            box-shadow: 0 4px 10px rgba(0,0,0,0.6);
            transition: all 0.2s ease;
        }

        .header-login:hover {
            border-color: var(--gold-bright);
            background: linear-gradient(180deg, #323844 0%, #1f232b 100%);
            box-shadow: 0 0 14px rgba(223, 186, 107, 0.35);
            transform: translateY(-1px);
            color: #fff;
        }

        /* مسرح الصفحة واللوحة الرومانية */
        main {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            min-height: calc(100vh - 82px);
            padding: 40px clamp(16px, 5vw, 90px);
        }

        /* حاوية اللوحة الحجرية في يسار المشهد */
        .tablet-container {
            position: relative;
            width: 100%;
            max-width: 480px;
            filter: drop-shadow(0 25px 50px rgba(0, 0, 0, 0.95)) drop-shadow(0 6px 18px rgba(0, 0, 0, 0.8));
        }

        /* اللوح الحجري المصقول */
        .roman-tablet {
            position: relative;
            background: radial-gradient(ellipse at 50% 25%, #2b2f37 0%, #1c1f24 55%, #131518 100%);
            border: 4px solid #4a4d53;
            border-radius: 8px;
            box-shadow: 
                inset 0 0 0 2px #26272b,
                inset 0 0 30px rgba(0,0,0,0.95),
                0 0 0 2px #121315;
            padding: 42px 34px 34px 34px;
            overflow: visible;
        }

        .tablet-outer-border {
            position: absolute;
            inset: -9px;
            border: 3px solid #36393f;
            border-radius: 12px;
            pointer-events: none;
            box-shadow: inset 0 0 8px rgba(0,0,0,0.85), 0 0 0 1px #111214;
        }

        /* براغي الزوايا الأربع */
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
            height: 24px;
            background: linear-gradient(180deg, #ff576f 0%, #ba0a24 50%, #52000c 100%);
            border-radius: 3px;
            box-shadow: 0 0 12px #ff1a3c, 0 0 4px #ff7a8f;
            z-index: 4;
        }
        .gem-edge-left-top { left: -7px; top: 75px; }
        .gem-edge-left-bottom { left: -7px; bottom: 75px; }
        .gem-edge-right-top { right: -7px; top: 75px; }
        .gem-edge-right-bottom { right: -7px; bottom: 75px; }

        /* الشعار النصي العلوي */
        .eyebrow {
            margin: 0 0 10px;
            color: var(--gold-main);
            letter-spacing: 0.16em;
            direction: ltr;
            text-align: right;
            font-family: 'Cairo', Tahoma, Arial, sans-serif;
            font-size: 13px;
            font-weight: 700;
            text-shadow: 0 2px 4px #000;
        }

        /* العنوان الرئيسي الإمبراطوري */
        h1.hero-title {
            margin: 0;
            font-family: 'Cairo', Tahoma, Arial, sans-serif;
            font-size: clamp(34px, 4.5vw, 48px);
            font-weight: 700;
            line-height: 1.25;
            background: linear-gradient(180deg, #fff7d6 0%, #ffd875 35%, #dfa030 65%, #875508 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            filter: drop-shadow(0 2px 0px #5e3b00) drop-shadow(0 4px 6px rgba(0, 0, 0, 0.9));
        }

        /* النص الوصفي */
        .hero-desc {
            margin: 18px 0 0;
            color: var(--text-muted);
            font-size: 15.5px;
            font-weight: 600;
            line-height: 1.85;
            text-shadow: 0 1px 3px #000;
        }

        /* حاوية الأزرار التفاعلية */
        .actions-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 26px;
        }

        /* 1. الزر القرمزي الملكي الأساسي (مطابق لشاشات اللعبة) */
        .btn-ruby-royal {
            position: relative;
            width: 100%;
            height: 52px;
            background: linear-gradient(180deg, #b81734 0%, #870b22 50%, #520412 100%);
            border: 1.5px solid #e5ba63;
            border-radius: 5px;
            color: #fff6e0;
            font-family: 'Cairo', sans-serif;
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 0.5px;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
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
        .btn-ruby-royal::before { right: 14px; }
        .btn-ruby-royal::after { left: 14px; }

        .btn-ruby-royal:hover {
            background: linear-gradient(180deg, #cc1c3b 0%, #9e0e29 50%, #610717 100%);
            border-color: #ffea9f;
            box-shadow: 0 6px 20px rgba(184, 23, 52, 0.55), inset 0 1px 2px rgba(255, 255, 255, 0.5);
            transform: translateY(-1px);
            color: #fff;
        }

        /* 2. الزر البرونزي المذهب الثانوي */
        .btn-bronze-secondary {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 48px;
            background: linear-gradient(180deg, #242932 0%, #15181e 100%);
            border: 1.5px solid var(--border-metal);
            border-radius: 5px;
            color: var(--gold-bright);
            font-family: 'Cairo', sans-serif;
            font-size: 15px;
            font-weight: 700;
            text-decoration: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.7);
            transition: all 0.2s ease;
        }

        .btn-bronze-secondary:hover {
            border-color: var(--gold-main);
            background: linear-gradient(180deg, #303642 0%, #1e222b 100%);
            box-shadow: 0 0 14px rgba(223, 186, 107, 0.35);
            color: #fff;
            transform: translateY(-1px);
        }

        /* الحاشية السفلية */
        .footnote {
            margin-top: 20px;
            color: #a49479;
            font-size: 13px;
            font-weight: 600;
            text-align: center;
            text-shadow: 0 1px 2px #000;
        }

        /* الحلية والجوهرة السفلية المعلقة */
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
            width: 86px;
            height: 12px;
            background: linear-gradient(180deg, #5a5e66, #2d3036);
            border: 1px solid #737882;
            border-top: none;
            clip-path: polygon(0 0, 100% 0, 80% 100%, 20% 100%);
        }

        .bottom-ruby {
            margin-top: -5px;
            width: 15px;
            height: 21px;
            background: radial-gradient(circle at 40% 30%, #ff6b85 0%, #b50727 60%, #52000d 100%);
            border-radius: 50% 50% 50% 50% / 60% 60% 40% 40%;
            border: 1px solid #ffd1d9;
            box-shadow: 0 0 12px #ff1e43, 0 3px 6px rgba(0,0,0,0.85);
        }

        /* التجاوب مع شاشات الهواتف والأجهزة اللوحية */
        @media (max-width: 900px) {
            body {
                background-position: 72% center; /* الحفاظ على هيبة القيصر في الخلفية */
            }
            main {
                justify-content: center;
                padding: 40px 16px;
            }
            .tablet-container {
                max-width: 420px;
            }
            .roman-tablet {
                padding: 34px 22px 28px 22px;
            }
            h1.hero-title {
                font-size: 34px;
            }
        }
    </style>
</head>
<body>

<!-- الشريط العلوي الإمبراطوري -->
<header class="site-header">
    <div class="brand">
        <span class="brand-mark" aria-hidden="true">♜</span>
        <div class="brand-info">
            <span class="brand-name">قيصر العرب</span>
            <small>CAESAR OF ARABS</small>
        </div>
    </div>
    <a class="header-login" href="login.php?mode=login">
        <span>دخول القادة</span>
        <span aria-hidden="true">⮌</span>
    </a>
</header>

<!-- المسرح الرئيسي مع بطاقة البداية الحجرية -->
<main>
    <div class="tablet-container">
        <section class="roman-tablet" aria-labelledby="welcome-title">
            <div class="tablet-outer-border" aria-hidden="true"></div>

            <!-- براغي التثبيت الأربعة في الزوايا -->
            <div class="tablet-rivet rivet-tl" aria-hidden="true"></div>
            <div class="tablet-rivet rivet-tr" aria-hidden="true"></div>
            <div class="tablet-rivet rivet-bl" aria-hidden="true"></div>
            <div class="tablet-rivet rivet-br" aria-hidden="true"></div>

            <!-- جواهر الياقوت على أطراف الإطار -->
            <div class="ruby-gem-edge gem-edge-left-top" aria-hidden="true"></div>
            <div class="ruby-gem-edge gem-edge-left-bottom" aria-hidden="true"></div>
            <div class="ruby-gem-edge gem-edge-right-top" aria-hidden="true"></div>
            <div class="ruby-gem-edge gem-edge-right-bottom" aria-hidden="true"></div>

            <!-- العنوان والوصف -->
            <p class="eyebrow">YOUR EMPIRE BEGINS HERE</p>
            <h1 class="hero-title" id="welcome-title">اكتب تاريخك<br>باسم قيصر العرب</h1>
            <p class="hero-desc">ابدأ بقائد ومدينتك الأولى، واجمع الموارد، وابنِ مملكتك العظمى خطوةً بعد خطوة نحو المجد الإمبراطوري.</p>
            
            <!-- أزرار الإجراءات الملكية -->
            <div class="actions-group">
                <a class="btn-ruby-royal" href="login.php?mode=register">
                    <span>إنشاء حساب جديد</span>
                </a>
                <a class="btn-bronze-secondary" href="login.php?mode=login">
                    <span>لدي حساب — تسجيل الدخول</span>
                </a>
            </div>

            <p class="footnote">سجّل حسابك أولاً، ثم ادخل باسم المستخدم الذي اخترته.</p>

            <!-- الحلية الياقوتية السفلية المعلقة -->
            <div class="bottom-ornament" aria-hidden="true">
                <div class="bottom-bracket"></div>
                <div class="bottom-ruby"></div>
            </div>
        </section>
    </div>
</main>

</body>
</html>