<?php
require_once __DIR__ . '/session.php';

if (!empty($_SESSION['player_id'])) {
    header('Location: profile.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8');
$initialMode = ($_GET['mode'] ?? '') === 'register' ? 'register' : 'login';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#120c08">
    <title>القيصر العربي – تسجيل الدخول</title>
    
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
            justify-content: flex-start;
            font-family: 'Cairo', Tahoma, Arial, sans-serif;
            background-color: #0d0a08;
            /* الخلفية المعتمدة بالمسار الدقيق لمشروعك */
            background-image: url("assets/brand/caesar-hero.jpg");
            background-position: center center;
            background-size: cover;
            background-repeat: no-repeat;
            background-attachment: fixed;
            position: relative;
        }

        /* تدرج تظليل جانبي لضمان وضوح نصوص اللوحة الحجرية في اليسار دون حجب القيصر على اليمين */
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            background: linear-gradient(90deg, rgba(0, 0, 0, 0.45) 0%, rgba(0, 0, 0, 0.15) 45%, rgba(0, 0, 0, 0.05) 100%);
            pointer-events: none;
            z-index: 1;
        }

        /* مسرح الصفحة وموقع البطاقة في يسار المشهد */
        .stage-wrapper {
            position: relative;
            z-index: 2;
            width: 100%;
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 40px clamp(16px, 5vw, 90px);
            direction: ltr;
        }

        /* حاوية اللوحة الحجرية */
        .tablet-container {
            position: relative;
            width: 100%;
            max-width: 440px;
            direction: rtl;
            filter: drop-shadow(0 24px 50px rgba(0, 0, 0, 0.95)) drop-shadow(0 6px 18px rgba(0, 0, 0, 0.8));
            transition: all 0.3s ease;
        }

        /* الصفيحة الحجرية المركزية مع التدرج الغرانيتي */
        .roman-tablet {
            position: relative;
            background: radial-gradient(ellipse at 50% 30%, #2b2f37 0%, #1c1f24 55%, #131518 100%);
            border: 4px solid #4a4d53;
            border-radius: 8px;
            box-shadow: 
                inset 0 0 0 2px #26272b,
                inset 0 0 28px rgba(0,0,0,0.92),
                0 0 0 2px #121315;
            padding: 30px 30px 28px 30px;
            overflow: visible;
        }

        /* الإطار المعدني الخارجي المضلع */
        .tablet-outer-border {
            position: absolute;
            inset: -9px;
            border: 3px solid #36393f;
            border-radius: 12px;
            pointer-events: none;
            box-shadow: 
                inset 0 0 8px rgba(0,0,0,0.85),
                0 0 0 1px #111214;
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

        /* فتحات وجواهر الياقوت الجانبية المشعة */
        .ruby-gem-edge {
            position: absolute;
            width: 5px;
            height: 24px;
            background: linear-gradient(180deg, #ff576f 0%, #ba0a24 50%, #52000c 100%);
            border-radius: 3px;
            box-shadow: 0 0 12px #ff1a3c, 0 0 4px #ff7a8f;
            z-index: 4;
        }
        .gem-edge-left-top { left: -7px; top: 110px; }
        .gem-edge-left-bottom { left: -7px; bottom: 110px; }
        .gem-edge-right-top { right: -7px; top: 110px; }
        .gem-edge-right-bottom { right: -7px; bottom: 110px; }

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
            width: 90px;
            height: 14px;
            background: linear-gradient(180deg, #5a5e66, #2d3036);
            border: 1px solid #737882;
            border-top: none;
            clip-path: polygon(0 0, 100% 0, 80% 100%, 20% 100%);
        }

        .bottom-ruby {
            margin-top: -6px;
            width: 16px;
            height: 22px;
            background: radial-gradient(circle at 40% 30%, #ff6b85 0%, #b50727 60%, #52000d 100%);
            border-radius: 50% 50% 50% 50% / 60% 60% 40% 40%;
            border: 1px solid #ffd1d9;
            box-shadow: 0 0 12px #ff1e43, 0 3px 6px rgba(0,0,0,0.85);
        }

        /* حقول الإدخال الرومانية المقوسة */
        .field-group {
            margin-bottom: 15px;
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
            box-shadow: 
                inset 0 2px 6px rgba(0,0,0,0.9),
                0 0 0 1px #221a0d;
            transition: all 0.25s ease;
        }

        /* أقواس الزوايا الذهبية لحقل الإدخال */
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
        .input-cartouche::before {
            right: 4px;
            border-left: none;
            border-radius: 0 4px 4px 0;
        }
        .input-cartouche::after {
            left: 4px;
            border-right: none;
            border-radius: 4px 0 0 4px;
        }

        .input-cartouche:hover,
        .input-cartouche:focus-within {
            border-color: #dfba6b;
            box-shadow: 
                inset 0 2px 6px rgba(0,0,0,0.9),
                0 0 14px rgba(223, 186, 107, 0.35);
        }

        .input-cartouche:hover::before,
        .input-cartouche:hover::after,
        .input-cartouche:focus-within::before,
        .input-cartouche:focus-within::after {
            border-color: #ffea9f;
            opacity: 1;
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

        /* الزر القرمزي الإمبراطوري المتطابق مع الصورتين */
        .btn-ruby-royal {
            position: relative;
            width: 100%;
            height: 52px;
            margin-top: 18px;
            margin-bottom: 14px;
            background: linear-gradient(180deg, #b81734 0%, #870b22 50%, #520412 100%);
            border: 1.5px solid #e5ba63;
            border-radius: 5px;
            color: #fff6e0;
            font-family: 'Cairo', sans-serif;
            font-size: 19px;
            font-weight: 800;
            letter-spacing: 0.5px;
            cursor: pointer;
            outline: none;
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

        /* الجواهر المعينة على طرفي الزر */
        .btn-ruby-royal::before,
        .btn-ruby-royal::after {
            content: "◆";
            position: absolute;
            font-size: 12px;
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
                0 6px 22px rgba(184, 23, 52, 0.55),
                inset 0 1px 2px rgba(255, 255, 255, 0.5);
            transform: translateY(-1px);
        }

        .btn-ruby-royal:active {
            transform: translateY(1px);
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.9);
        }

        .btn-ruby-royal:disabled {
            filter: grayscale(0.5);
            opacity: 0.75;
            cursor: wait;
        }

        /* الروابط السفلية والتنقل */
        .tablet-links {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            margin-top: 6px;
        }

        .tablet-link-btn {
            background: none;
            border: none;
            color: var(--text-muted);
            font-family: 'Cairo', sans-serif;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: color 0.2s, text-shadow 0.2s;
        }

        .tablet-link-btn:hover {
            color: var(--gold-bright);
            text-shadow: 0 0 8px rgba(223, 186, 107, 0.6);
        }

        /* رسائل الحالة والأخطاء */
        .status-alert {
            min-height: 22px;
            font-size: 13px;
            font-weight: 700;
            text-align: center;
            margin-bottom: 12px;
            padding: 5px 10px;
            border-radius: 4px;
            transition: all 0.3s ease;
            display: none;
        }

        .status-alert:not(:empty) {
            display: block;
        }

        .status-alert[data-state="error"] {
            background: rgba(140, 16, 32, 0.75);
            border: 1px solid #ff4762;
            color: #ffeaeb;
            text-shadow: 0 1px 2px #000;
        }

        .status-alert[data-state="success"] {
            background: rgba(30, 95, 45, 0.75);
            border: 1px solid #55d677;
            color: #eafff0;
            text-shadow: 0 1px 2px #000;
        }

        /* نافذة استرجاع الحساب */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(4px);
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.25s ease;
        }

        .modal-overlay.active {
            opacity: 1;
            pointer-events: auto;
        }

        .modal-card {
            background: #191c21;
            border: 2px solid #dfba6b;
            border-radius: 8px;
            padding: 24px;
            width: 100%;
            max-width: 380px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.9);
        }

        .modal-card h3 {
            color: #ffea9f;
            margin-bottom: 10px;
            font-size: 18px;
        }

        .modal-card p {
            color: #d1c1a5;
            font-size: 13px;
            margin-bottom: 16px;
            line-height: 1.5;
        }

        [hidden] {
            display: none !important;
        }

        /* التجاوب مع شاشات الهواتف والأجهزة اللوحية */
        @media (max-width: 900px) {
            body {
                background-position: 74% center; /* الحفاظ على وجه القيصر واضحاً في الخلفية */
            }
            .stage-wrapper {
                justify-content: center;
                padding: 60px 16px 30px;
            }
            .tablet-container {
                max-width: 380px;
            }
        }

        @media (max-width: 420px) {
            .tablet-container {
                max-width: 100%;
            }
            .roman-tablet {
                padding: 56px 18px 24px 18px;
            }
        }
    </style>
</head>
<body data-mode="<?= htmlspecialchars($initialMode, ENT_QUOTES, 'UTF-8') ?>">

<main class="stage-wrapper">
    <div class="tablet-container">
        
        <!-- الهيكل واللوحة الحجرية -->
        <div class="roman-tablet">
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

            <!-- صندوق التنبيهات ورسائل التحقق -->
            <div class="status-alert" id="authStatus" role="status" aria-live="polite"></div>

            <!-- =========================
                 1. واجهة تسجيل الدخول (الصورة الأولى)
                 ========================= -->
            <section id="loginPanel" <?= $initialMode === 'register' ? 'hidden' : '' ?> aria-label="تسجيل الدخول">
                <form id="loginForm" novalidate autocomplete="on">
                    <input type="hidden" name="action" value="login">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                    <!-- حقل اسم المستخدم -->
                    <div class="field-group">
                        <div class="input-cartouche">
                            <input id="loginName" name="login" type="text" autocomplete="username" maxlength="100" required placeholder="اسم المستخدم">
                        </div>
                    </div>

                    <!-- حقل كلمة المرور -->
                    <div class="field-group">
                        <div class="input-cartouche">
                            <input id="loginPassword" name="password" type="password" autocomplete="current-password" required placeholder="كلمة المرور">
                        </div>
                    </div>

                    <!-- زر تسجيل الدخول القرمزي -->
                    <button class="btn-ruby-royal" id="loginSubmit" type="submit">
                        تسجيل الدخول
                    </button>

                    <!-- الروابط السفلية -->
                    <div class="tablet-links">
                        <button type="button" class="tablet-link-btn" id="forgotPassBtn">هل نسيت كلمة المرور؟</button>
                        <button type="button" class="tablet-link-btn" data-switch="register">إنشاء حساب جديد</button>
                    </div>
                </form>
            </section>

            <!-- =========================
                 2. واجهة إنشاء حساب (الصورة الثانية)
                 ========================= -->
            <section id="registerPanel" <?= $initialMode !== 'register' ? 'hidden' : '' ?> aria-label="إنشاء حساب">
                <form id="registerForm" novalidate autocomplete="off">
                    <input type="hidden" name="action" value="register">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                    <!-- حقول الحفاظ على توافقية السيرفر التامة -->
                    <input type="hidden" id="avatarValue" name="avatar" value="1">
                    <input type="hidden" id="cityName" name="city_name" value="العاصمة 1">

                    <!-- 1. اسم المستخدم -->
                    <div class="field-group">
                        <div class="input-cartouche">
                            <input id="username" name="username" type="text" autocomplete="username" minlength="2" maxlength="25" required placeholder="اسم المستخدم">
                        </div>
                    </div>

                    <!-- 2. البريد الإلكتروني -->
                    <div class="field-group">
                        <div class="input-cartouche">
                            <input id="email" name="email" type="email" autocomplete="email" maxlength="100" required placeholder="البريد الإلكتروني" dir="ltr">
                        </div>
                    </div>

                    <!-- 3. كلمة المرور -->
                    <div class="field-group">
                        <div class="input-cartouche">
                            <input id="registerPassword" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="72" required placeholder="كلمة المرور">
                        </div>
                    </div>

                    <!-- 4. تأكيد كلمة المرور -->
                    <div class="field-group">
                        <div class="input-cartouche">
                            <input id="passwordConfirm" name="password_confirm" type="password" autocomplete="new-password" minlength="8" maxlength="72" required placeholder="تأكيد كلمة المرور">
                        </div>
                    </div>

                    <!-- زر إنشاء حساب القرمزي -->
                    <button class="btn-ruby-royal" id="registerSubmit" type="submit">
                        إنشاء حساب
                    </button>

                    <!-- رابط العودة لتسجيل الدخول -->
                    <div class="tablet-links">
                        <button type="button" class="tablet-link-btn" data-switch="login">لديك حساب بالفعل ؟ تسجيل الدخول</button>
                    </div>
                </form>
            </section>

            <!-- الحلية الياقوتية السفلية -->
            <div class="bottom-ornament" aria-hidden="true">
                <div class="bottom-bracket"></div>
                <div class="bottom-ruby"></div>
            </div>
        </div>
    </div>
</main>

<!-- نافذة استعادة الحساب -->
<div class="modal-overlay" id="forgotModal" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="modal-card">
        <h3>استعادة حساب القائد</h3>
        <p>إذا فقدت كلمة المرور، يرجى كتابة بريدك الإلكتروني أو التواصل مع الدعم الفني لمملكة القيصر العربي لاستعادة حسابك.</p>
        <button type="button" class="btn-ruby-royal" id="closeForgotModal" style="margin: 0; height: 42px; font-size: 15px;">إغلاق</button>
    </div>
</div>

<script>
    const statusBox = document.getElementById('authStatus');
    const loginPanel = document.getElementById('loginPanel');
    const registerPanel = document.getElementById('registerPanel');
    const forgotModal = document.getElementById('forgotModal');
    const forgotPassBtn = document.getElementById('forgotPassBtn');
    const closeForgotModal = document.getElementById('closeForgotModal');

    function showStatus(message, state) {
        if (!message) {
            statusBox.textContent = '';
            statusBox.style.display = 'none';
            return;
        }
        statusBox.textContent = message;
        statusBox.dataset.state = state || 'success';
        statusBox.style.display = 'block';
    }

    function switchMode(mode, message = '', username = '') {
        const registering = mode === 'register';
        document.body.dataset.mode = mode;
        loginPanel.hidden = registering;
        registerPanel.hidden = !registering;
        
        const newUrl = registering ? 'login.php?mode=register' : 'login.php?mode=login';
        window.history.replaceState(null, '', newUrl);
        
        showStatus(message, 'success');
        
        if (username) {
            const loginUserField = document.getElementById('loginName');
            if (loginUserField) {
                loginUserField.value = username;
                document.getElementById('loginPassword').focus();
            }
        }
    }

    document.querySelectorAll('[data-switch]').forEach(btn => {
        btn.addEventListener('click', () => {
            showStatus('', '');
            switchMode(btn.dataset.switch);
        });
    });

    async function submitAuthForm(form, button, onSuccess) {
        showStatus('', '');
        if (!form.reportValidity()) return;
        
        button.disabled = true;
        const originalText = button.innerHTML;
        button.innerHTML = 'جاري المعالجة...';

        try {
            const response = await fetch('auth.php', {
                method: 'POST',
                body: new FormData(form),
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            });

            const payload = await response.json();

            if (!response.ok || payload.state !== 'ok') {
                showStatus(payload.message || 'تعذر إتمام العملية. تأكد من صحة البيانات.', 'error');
                return;
            }

            onSuccess(payload);
        } catch (error) {
            showStatus('تعذر الاتصال بالخادم. تأكد من اتصال الإنترنت ثم حاول مجدداً.', 'error');
        } finally {
            button.disabled = false;
            button.innerHTML = originalText;
        }
    }

    document.getElementById('loginForm').addEventListener('submit', (e) => {
        e.preventDefault();
        submitAuthForm(e.currentTarget, document.getElementById('loginSubmit'), (payload) => {
            window.location.assign(payload.redirect || 'profile.php');
        });
    });

    document.getElementById('registerForm').addEventListener('submit', (e) => {
        e.preventDefault();
        const pwd = document.getElementById('registerPassword').value;
        const confirm = document.getElementById('passwordConfirm').value;

        if (pwd !== confirm) {
            showStatus('تأكيد كلمة المرور غير مطابق.', 'error');
            return;
        }

        submitAuthForm(e.currentTarget, document.getElementById('registerSubmit'), (payload) => {
            switchMode('login', payload.message || 'تم إنشاء الحساب بنجاح! يمكنك الآن تسجيل الدخول.', payload.username);
        });
    });

    if (forgotPassBtn && forgotModal && closeForgotModal) {
        forgotPassBtn.addEventListener('click', () => {
            forgotModal.classList.add('active');
            forgotModal.setAttribute('aria-hidden', 'false');
        });
        closeForgotModal.addEventListener('click', () => {
            forgotModal.classList.remove('active');
            forgotModal.setAttribute('aria-hidden', 'true');
        });
    }
</script>
</body>
</html>