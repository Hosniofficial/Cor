<?php
// web.elkaisar.com/admin/index.php
// Professional Human-Crafted Admin Console for Elkaisar
require_once __DIR__ . '/auth.php';
requireLogin();
$adminCsrfToken = htmlspecialchars($_SESSION['admin_csrf_token'], ENT_QUOTES, 'UTF-8');

$currentUser = getAdminUser();
$roleBadges = [
    'super_admin' => ['label' => 'مدير تنفيذي', 'class' => 'role-super'],
    'admin'       => ['label' => 'مدير عام', 'class' => 'role-admin'],
    'supervisor'  => ['label' => 'مشرف عام', 'class' => 'role-supervisor'],
    'moderator'   => ['label' => 'مراقب عام', 'class' => 'role-mod']
];
$userRoleInfo = $roleBadges[$currentUser['role']] ?? ['label' => $currentUser['role'], 'class' => 'role-mod'];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#120c08">
    <meta name="csrf-token" content="<?= $adminCsrfToken ?>">
    <title>لوحة التحكم الإدارية | قيصر العرب</title>
    
    <!-- الخط الموحد وأيقونات FontAwesome -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        :root {
            --bg-base: #0a0806;
            --bg-surface: #14171c;
            --bg-card: #191c22;
            --bg-card-hover: #22262e;
            --bg-input: #0d0f12;
            --border: #4d3d24;
            --border-hover: #dfba6b;
            
            --gold-bright: #ffea9f;
            --gold-main: #dfba6b;
            --gold-dark: #8c6721;
            --gold-subtle: rgba(223, 186, 107, 0.12);
            
            --ruby-bright: #ff2b4e;
            --ruby-main: #a3102c;
            --ruby-dark: #580413;
            --rose-subtle: rgba(163, 16, 44, 0.15);
            
            --emerald: #00ff73;
            --emerald-subtle: rgba(0, 255, 115, 0.12);
            
            --sky: #38bdf8;
            --sky-subtle: rgba(56, 189, 248, 0.12);
            
            --text-primary: #fffaee;
            --text-secondary: #d1c1a5;
            --text-muted: #96866e;
            
            --sidebar-w: 260px;
            --header-h: 68px;
            --radius-sm: 5px;
            --radius-md: 8px;
            --radius-lg: 12px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Cairo', Tahoma, Arial, sans-serif; }
        
        body { 
            background-color: var(--bg-base);
            /* الخلفية الملحمية الموحدة */
            background-image: url("../assets/brand/caesar-hero.jpg");
            background-position: center center;
            background-size: cover;
            background-repeat: no-repeat;
            background-attachment: fixed;
            color: var(--text-primary); 
            min-height: 100vh; 
            display: flex; 
            font-size: 13.5px;
            position: relative;
        }

        /* طبقة تظليل سينمائية ناعمة لضمان راحة العين وقراءة البيانات */
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            background: radial-gradient(circle at 60% 40%, rgba(10, 8, 6, 0.78) 0%, rgba(5, 4, 3, 0.92) 100%);
            pointer-events: none;
            z-index: 0;
        }

        /* Scrollbars الفخمة */
        ::-webkit-scrollbar { width: 7px; height: 7px; }
        ::-webkit-scrollbar-track { background: rgba(0,0,0,0.3); }
        ::-webkit-scrollbar-thumb { background: #57462a; border-radius: 4px; border: 1px solid #1c1811; }
        ::-webkit-scrollbar-thumb:hover { background: var(--gold-main); }

        /* الشريط الجانبي الروماني المذهب (Sidebar) */
        aside.sidebar {
            width: var(--sidebar-w);
            background: radial-gradient(ellipse at 50% 10%, #20242b 0%, #131518 70%, #0a0b0d 100%);
            border-left: 2px solid var(--border);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            bottom: 0;
            right: 0;
            z-index: 50;
            box-shadow: -8px 0 25px rgba(0,0,0,0.85);
        }

        .brand {
            height: var(--header-h);
            padding: 0 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 2px solid rgba(223, 186, 107, 0.25);
            background: rgba(0, 0, 0, 0.35);
        }
        .brand-icon {
            width: 38px;
            height: 38px;
            background: linear-gradient(135deg, #ffd875, #b88220);
            color: #171107;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            box-shadow: 0 0 12px rgba(223, 186, 107, 0.45);
            border: 1px solid #ffea9f;
        }
        .brand-info h2 { 
            font-family: 'Cairo', Tahoma, Arial, sans-serif;
            font-size: 20px; 
            font-weight: 700; 
            background: linear-gradient(180deg, #fff7d6 0%, #ffd875 40%, #dfa030 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.9));
            line-height: 1.1;
        }
        .brand-info span { 
            font-size: 11px; 
            color: var(--text-muted); 
            display: block; 
            margin-top: 2px;
            font-weight: 600;
        }

        .user-widget {
            padding: 14px 18px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid rgba(223, 186, 107, 0.15);
            background: rgba(0, 0, 0, 0.2);
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: radial-gradient(circle, #382c16 0%, #15181d 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gold-bright);
            font-size: 16px;
            border: 1.5px solid var(--gold-main);
            box-shadow: 0 0 8px rgba(223, 186, 107, 0.3);
        }
        .user-meta { flex: 1; min-width: 0; }
        .user-meta .name { font-size: 13.5px; font-weight: 700; color: #fffaee; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .role-tag {
            display: inline-block;
            font-size: 10.5px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 4px;
            margin-top: 3px;
            border: 1px solid transparent;
        }
        .role-super { background: var(--ruby-dark); color: #ff9ea8; border-color: var(--ruby-main); }
        .role-admin { background: var(--gold-subtle); color: var(--gold-bright); border-color: var(--gold-dark); }
        .role-supervisor { background: var(--emerald-subtle); color: #8affbc; border-color: #0e8544; }
        .role-mod { background: var(--sky-subtle); color: var(--sky); border-color: #19698c; }

        nav.menu {
            flex: 1;
            padding: 16px 12px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .menu-label {
            font-size: 11px;
            font-weight: 800;
            color: var(--gold-main);
            letter-spacing: 0.5px;
            padding: 12px 12px 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .menu-label::after {
            content: "";
            flex: 1;
            height: 1px;
            background: rgba(223, 186, 107, 0.15);
        }
        .menu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            border-radius: var(--radius-sm);
            color: var(--text-secondary);
            font-size: 13.5px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            border: 1px solid transparent;
            transition: all 0.2s ease;
        }
        .menu-item i { width: 18px; text-align: center; font-size: 14px; color: var(--text-muted); transition: color 0.2s; }
        .menu-item:hover { 
            color: var(--gold-bright); 
            background: rgba(223, 186, 107, 0.08); 
            border-color: rgba(223, 186, 107, 0.25);
        }
        .menu-item:hover i { color: var(--gold-bright); }
        .menu-item.active {
            color: #fff;
            background: linear-gradient(90deg, rgba(163, 16, 44, 0.35) 0%, rgba(20, 23, 28, 0.7) 100%);
            border: 1px solid var(--ruby-main);
            box-shadow: inset 0 0 10px rgba(163, 16, 44, 0.35);
            font-weight: 700;
        }
        .menu-item.active i { color: var(--ruby-bright); filter: drop-shadow(0 0 5px var(--ruby-bright)); }

        .sidebar-bottom {
            padding: 14px 16px;
            border-top: 1px solid rgba(223, 186, 107, 0.2);
            display: flex;
            flex-direction: column;
            gap: 8px;
            background: rgba(0, 0, 0, 0.25);
        }
        .btn-side {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 9px 12px;
            font-size: 12.5px;
            font-weight: 700;
            border-radius: var(--radius-sm);
            text-decoration: none;
            transition: all 0.2s;
            font-family: inherit;
            cursor: pointer;
        }
        .btn-side-game { 
            background: linear-gradient(180deg, #252a33, #15181e); 
            color: var(--gold-bright); 
            border: 1.5px solid var(--border); 
        }
        .btn-side-game:hover { 
            border-color: var(--gold-main); 
            box-shadow: 0 0 10px rgba(223, 186, 107, 0.3);
        }
        .btn-side-logout { 
            color: #ff8c9b; 
            background: rgba(163, 16, 44, 0.15); 
            border: 1px solid rgba(163, 16, 44, 0.4); 
        }
        .btn-side-logout:hover { 
            background: var(--ruby-main); 
            color: #fff; 
            box-shadow: 0 0 12px rgba(255, 43, 78, 0.4);
        }

        /* مساحة العمل المركزية (Main Workspace) */
        main.workspace {
            margin-right: var(--sidebar-w);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            position: relative;
            z-index: 1;
        }

        header.topbar {
            height: var(--header-h);
            background: radial-gradient(ellipse at 50% 0%, #1e2229 0%, #121417 100%);
            border-bottom: 2px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            position: sticky;
            top: 0;
            z-index: 40;
            box-shadow: 0 6px 18px rgba(0,0,0,0.6);
        }
        .topbar-left { display: flex; align-items: center; gap: 16px; }
        .topbar-left h1 { 
            font-family: 'Cairo', Tahoma, Arial, sans-serif;
            font-size: 24px; 
            font-weight: 700; 
            color: var(--gold-bright); 
            text-shadow: 0 2px 4px rgba(0,0,0,0.8);
        }
        .server-status {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 12px;
            color: #8effba;
            background: rgba(0, 255, 115, 0.08);
            border: 1px solid rgba(0, 255, 115, 0.25);
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 700;
        }
        .server-status .dot { 
            width: 7px; 
            height: 7px; 
            background: var(--emerald); 
            border-radius: 50%; 
            box-shadow: 0 0 8px var(--emerald);
        }

        .content-area {
            padding: 28px 32px 50px;
            flex: 1;
            max-width: 1440px;
            width: 100%;
            margin: 0 auto;
        }

        .tab-pane { display: none; }
        .tab-pane.active { display: block; animation: fadeIn 0.25s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

        /* بطاقات المقاييس والإحصائيات (KPI Metric Cards) */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 28px;
        }
        @media (max-width: 1100px) { .metrics-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 640px) { .metrics-grid { grid-template-columns: 1fr; } }

        .metric-card {
            position: relative;
            background: radial-gradient(ellipse at 50% 20%, #22262d 0%, #15181d 70%, #0e1013 100%);
            border: 1.5px solid var(--border);
            border-radius: var(--radius-md);
            padding: 18px 22px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            box-shadow: inset 0 0 15px rgba(0,0,0,0.85), 0 6px 16px rgba(0,0,0,0.6);
            transition: all 0.2s ease;
        }
        .metric-card:hover { 
            border-color: var(--gold-main); 
            transform: translateY(-2px);
            box-shadow: inset 0 0 15px rgba(0,0,0,0.85), 0 8px 20px rgba(0,0,0,0.8), 0 0 12px rgba(223, 186, 107, 0.2);
        }
        .metric-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .metric-header span { font-size: 13px; font-weight: 700; color: var(--text-muted); }
        .metric-header i { font-size: 16px; color: var(--gold-main); }
        .metric-value { 
            font-family: 'Cairo', Tahoma, Arial, sans-serif;
            font-size: 30px; 
            font-weight: 700; 
            color: var(--gold-bright); 
            text-shadow: 0 2px 4px rgba(0,0,0,0.9);
            line-height: 1;
        }

        /* اللوحة الحجرية الرومانية الكبرى (Card Container) */
        .card {
            position: relative;
            background: radial-gradient(ellipse at 50% 15%, #252a32 0%, #17191d 60%, #101215 100%);
            border: 2px solid #57462a;
            border-radius: var(--radius-md);
            margin-bottom: 26px;
            overflow: hidden;
            box-shadow: inset 0 0 25px rgba(0,0,0,0.9), 0 10px 30px rgba(0,0,0,0.7);
        }
        .card-header {
            padding: 16px 24px;
            border-bottom: 1.5px solid rgba(223, 186, 107, 0.25);
            background: rgba(0, 0, 0, 0.3);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .card-title { 
            font-family: 'Cairo', Tahoma, Arial, sans-serif;
            font-size: 18px; 
            font-weight: 700; 
            color: var(--gold-bright); 
            display: flex; 
            align-items: center; 
            gap: 10px; 
            text-shadow: 0 2px 4px #000;
        }
        .card-title i { color: var(--gold-main); filter: drop-shadow(0 0 5px var(--gold-bright)); }
        .card-body { padding: 22px; }

        /* الجداول الحربية (Data Tables) */
        .table-responsive { width: 100%; overflow-x: auto; }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13.5px;
            text-align: right;
        }
        table.data-table th {
            background: rgba(43, 33, 19, 0.45);
            color: var(--gold-bright);
            font-weight: 700;
            font-size: 12.5px;
            padding: 12px 18px;
            border-bottom: 2px solid #57462a;
            white-space: nowrap;
        }
        table.data-table td {
            padding: 13px 18px;
            border-bottom: 1px solid rgba(223, 186, 107, 0.08);
            color: var(--text-secondary);
            vertical-align: middle;
        }
        table.data-table tbody tr:hover td {
            background: rgba(223, 186, 107, 0.06);
            color: #fff;
        }

        /* الأزرار الملكية (Buttons) */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 700;
            border-radius: var(--radius-sm);
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            white-space: nowrap;
            outline: none;
        }
        .btn-sm { padding: 5px 12px; font-size: 12px; }
        
        /* الزر القرمزي الأساسي المطابق لواجهات اللعبة */
        .btn-primary { 
            background: linear-gradient(180deg, #b81734 0%, #870b22 50%, #520412 100%);
            border: 1.5px solid #e5ba63;
            color: #fff6e0;
            box-shadow: 0 4px 10px rgba(0,0,0,0.7), inset 0 1px 1px rgba(255, 255, 255, 0.35);
            text-shadow: 0 1px 3px #000;
        }
        .btn-primary:hover { 
            background: linear-gradient(180deg, #cc1c3b 0%, #9e0e29 50%, #610717 100%);
            border-color: #ffea9f;
            box-shadow: 0 4px 15px rgba(184, 23, 52, 0.5);
            transform: translateY(-1px);
        }
        
        .btn-secondary { 
            background: linear-gradient(180deg, #252a33 0%, #171a20 100%);
            color: var(--gold-bright);
            border: 1.5px solid #57462a;
            box-shadow: 0 3px 8px rgba(0,0,0,0.6);
        }
        .btn-secondary:hover { 
            border-color: var(--gold-main); 
            background: linear-gradient(180deg, #323844 0%, #1f232b 100%);
            color: #fff;
        }
        
        .btn-danger { 
            background: rgba(163, 16, 44, 0.2); 
            color: #ff8c9b; 
            border: 1px solid var(--ruby-main); 
        }
        .btn-danger:hover { 
            background: var(--ruby-main); 
            color: #fff; 
            box-shadow: 0 0 10px rgba(255, 43, 78, 0.4);
        }
        
        .btn-success { 
            background: rgba(0, 255, 115, 0.12); 
            color: #7affb5; 
            border: 1px solid #168c4e; 
        }
        .btn-success:hover { 
            background: #147a44; 
            color: #fff; 
            box-shadow: 0 0 10px rgba(0, 255, 115, 0.35);
        }

        /* حقول الإدخال والكبسولات (Form Controls) */
        .form-group { margin-bottom: 18px; }
        .form-label { display: block; font-size: 12.5px; font-weight: 700; color: var(--text-secondary); margin-bottom: 6px; }
        .form-input, .form-select {
            width: 100%;
            background: linear-gradient(180deg, #0d0f12 0%, #171a1f 50%, #0d0f12 100%);
            border: 1.5px solid #57462a;
            border-radius: var(--radius-sm);
            color: #fffaee;
            color-scheme: dark;
            padding: 9px 14px;
            font-size: 13.5px;
            font-weight: 600;
            outline: none;
            box-shadow: inset 0 2px 6px rgba(0,0,0,0.85);
            transition: all 0.2s ease;
        }
        select,
        select option,
        select optgroup {
            color-scheme: dark !important;
        }
        select option,
        select optgroup {
            background-color: #14171c !important;
            color: #fffaee !important;
        }
        select option:checked {
            background-color: #57462a !important;
            color: #ffea9f !important;
        }
        .form-input:focus, .form-select:focus { 
            border-color: var(--gold-main); 
            box-shadow: inset 0 2px 6px rgba(0,0,0,0.85), 0 0 12px rgba(223, 186, 107, 0.3);
        }

        .search-bar {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 18px;
        }
        .search-input-wrapper { position: relative; flex: 1; }
        .search-input-wrapper i {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 14px;
        }
        .search-input-wrapper input { padding-right: 38px; }

        /* شارات الحالة الملونة (Status Pills) */
        .pill {
            display: inline-block;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 4px;
            border: 1px solid transparent;
        }
        .pill-emerald { background: var(--emerald-subtle); color: #8effba; border-color: rgba(0, 255, 115, 0.25); }
        .pill-rose { background: var(--rose-subtle); color: #ff9da9; border-color: rgba(255, 43, 78, 0.3); }
        .pill-amber { background: var(--gold-subtle); color: var(--gold-bright); border-color: rgba(223, 186, 107, 0.3); }
        .pill-gray { background: rgba(255, 255, 255, 0.06); color: var(--text-muted); }

        /* هيكلية النوافذ المنبثقة الإمبراطورية (Modal Architecture) */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(4, 5, 8, 0.82);
            backdrop-filter: blur(5px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 100;
            padding: 20px;
        }
        .modal-overlay.active { display: flex; animation: fadeIn 0.2s ease; }
        .modal-box {
            position: relative;
            background: radial-gradient(ellipse at 50% 15%, #252a32 0%, #17191d 60%, #101215 100%);
            border: 2px solid #dfba6b;
            border-radius: var(--radius-lg);
            width: 100%;
            max-width: 820px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.95), 0 0 25px rgba(223, 186, 107, 0.25);
        }
        .modal-box.modal-lg { max-width: 940px; }
        .modal-box.modal-sm { max-width: 500px; }
        .modal-head {
            padding: 16px 24px;
            border-bottom: 1.5px solid rgba(223, 186, 107, 0.3);
            background: rgba(0, 0, 0, 0.35);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .modal-head h3 { 
            font-family: 'Cairo', Tahoma, Arial, sans-serif;
            font-size: 20px; 
            font-weight: 700; 
            color: var(--gold-bright); 
            display: flex; 
            align-items: center; 
            gap: 10px; 
            text-shadow: 0 2px 4px #000;
        }
        .modal-close-btn {
            background: none;
            border: none;
            color: var(--gold-main);
            font-size: 22px;
            cursor: pointer;
            padding: 4px;
            transition: color 0.15s;
        }
        .modal-close-btn:hover { color: #fff; }
        .modal-body {
            padding: 24px;
            overflow-y: auto;
            flex: 1;
        }
        .modal-foot {
            padding: 14px 24px;
            border-top: 1.5px solid rgba(223, 186, 107, 0.2);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            background: rgba(0, 0, 0, 0.35);
        }

        /* تبويبات التنقل الداخلية داخل نافذة اللاعب (Drawer Tabs) */
        .drawer-nav {
            display: flex;
            border-bottom: 1.5px solid rgba(223, 186, 107, 0.25);
            margin: -24px -24px 22px -24px;
            background: rgba(0, 0, 0, 0.3);
            padding: 0 16px;
            gap: 4px;
            overflow-x: auto;
        }
        .drawer-tab {
            padding: 12px 16px;
            font-size: 13px;
            font-weight: 700;
            color: var(--text-muted);
            cursor: pointer;
            border-bottom: 3px solid transparent;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
            transition: all 0.2s ease;
        }
        .drawer-tab:hover { color: var(--gold-bright); }
        .drawer-tab.active { 
            color: var(--gold-bright); 
            border-bottom-color: var(--gold-main); 
            background: rgba(223, 186, 107, 0.08);
        }

        .drawer-pane { display: none; }
        .drawer-pane.active { display: block; animation: fadeIn 0.2s ease; }

        /* شبكة عتاد ومواد اللاعب (Item Grid) */
        .item-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(115px, 1fr));
            gap: 12px;
        }
        .item-card {
            background: linear-gradient(180deg, #1d2127 0%, #111317 100%);
            border: 1.5px solid #57462a;
            border-radius: var(--radius-sm);
            padding: 10px;
            text-align: center;
            position: relative;
            box-shadow: inset 0 0 10px rgba(0,0,0,0.85);
            transition: border-color 0.2s;
        }
        .item-card:hover { border-color: var(--gold-main); }
        .item-card img { width: 44px; height: 44px; object-fit: contain; margin-bottom: 6px; border-radius: 4px; }
        .item-card .name { font-size: 11.5px; font-weight: 700; color: #fffaee; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .item-card .count { font-size: 11px; color: var(--gold-main); font-weight: 700; margin-top: 3px; }
        .item-card .del-btn {
            position: absolute;
            top: 5px;
            left: 5px;
            color: var(--text-muted);
            font-size: 11px;
            cursor: pointer;
            padding: 3px;
        }
        .item-card .del-btn:hover { color: var(--ruby-bright); }

        /* رسائل الإشعارات العائمة (Toast Notifications) */
        #toast-shelf {
            position: fixed;
            bottom: 24px;
            left: 24px;
            z-index: 200;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .toast-msg {
            background: #171a20;
            border: 1.5px solid var(--border);
            padding: 12px 18px;
            border-radius: var(--radius-sm);
            color: #fffaee;
            font-size: 13px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.7), 0 0 10px rgba(223, 186, 107, 0.2);
            animation: slideIn 0.25s ease;
        }
        .toast-msg.success { border-color: #168c4e; }
        .toast-msg.success i { color: var(--emerald); }
        .toast-msg.error { border-color: var(--ruby-main); }
        .toast-msg.error i { color: var(--ruby-bright); }
        @keyframes slideIn { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }

        /* الشبكات والتنسيق المتجاوب */
        .two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 850px) { .two-col { grid-template-columns: 1fr; } }
        .three-col { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
        @media (max-width: 850px) { .three-col { grid-template-columns: 1fr; } }

        .mobile-nav-toggle { display: none; }

        @media (max-width: 900px) {
            aside.sidebar { transform: translateX(100%); transition: transform 0.3s ease; }
            aside.sidebar.mobile-open { transform: translateX(0); }
            main.workspace { margin-right: 0; }
            .mobile-nav-toggle { display: inline-flex; }
        }
    </style>
</head>
<body>

<!-- الشريط الجانبي الإمبراطوري (Imperial Sidebar) -->
<aside class="sidebar">
    <div class="brand">
        <div class="brand-icon"><i class="fa-solid fa-chess-rook"></i></div>
        <div class="brand-info">
            <h2>قيصر العرب</h2>
            <span>لوحة التحكم الإدارية</span>
        </div>
    </div>

    <div class="user-widget">
        <div class="user-avatar"><i class="fa-solid fa-user-shield"></i></div>
        <div class="user-meta">
            <div class="name"><?= htmlspecialchars($currentUser['name'] ?: $currentUser['username']) ?></div>
            <span class="role-tag <?= $userRoleInfo['class'] ?>"><?= $userRoleInfo['label'] ?></span>
        </div>
    </div>

    <nav class="menu">
        <div class="menu-label">الرئيسية</div>
        <a class="menu-item active" data-tab="tab-overview"><i class="fa-solid fa-gauge"></i> نظرة عامة</a>
        <a class="menu-item" data-tab="tab-players"><i class="fa-solid fa-users"></i> دليل اللاعبين</a>

        <div class="menu-label">العمليات السريعة</div>
        <a class="menu-item" data-tab="tab-quick-grant"><i class="fa-solid fa-boxes-stacked"></i> منح الموارد والعتاد</a>
        <a class="menu-item" data-tab="tab-sanctions"><i class="fa-solid fa-gavel"></i> العقوبات والحظر</a>

        <?php if ($currentUser['role'] === 'super_admin' || $currentUser['role'] === 'admin'): ?>
        <div class="menu-label">الإدارة والرقابة</div>
        <a class="menu-item" data-tab="tab-staff"><i class="fa-solid fa-user-gear"></i> المشرفين والصلاحيات</a>
        <?php endif; ?>
        <a class="menu-item" data-tab="tab-logs"><i class="fa-solid fa-clock-rotate-left"></i> سجل العمليات</a>
    </nav>

    <div class="sidebar-bottom">
        <a href="../server.php" class="btn-side btn-side-game"><i class="fa-solid fa-gamepad"></i> العودة للعبة</a>
        <form method="POST" action="logout.php">
            <input type="hidden" name="csrf_token" value="<?= $adminCsrfToken ?>">
            <button type="submit" class="btn-side btn-side-logout" style="width: 100%;"><i class="fa-solid fa-arrow-right-from-bracket"></i> خروج</button>
        </form>
    </div>
</aside>

<!-- مساحة العمل المركزية (Main Workspace) -->
<main class="workspace">
    <header class="topbar">
        <div class="topbar-left">
            <button type="button" class="btn btn-secondary btn-sm mobile-nav-toggle" aria-label="فتح القائمة" aria-expanded="false" onclick="toggleMobileNav(this)"><i class="fa-solid fa-bars"></i></button>
            <h1 id="topbar-title">نظرة عامة على السيرفر</h1>
            <div class="server-status"><span class="dot"></span> السيرفر يعمل بشكل طبيعي</div>
        </div>
        <div>
            <button class="btn btn-secondary btn-sm" onclick="refreshCurrentTab()"><i class="fa-solid fa-rotate"></i> تحديث البيانات</button>
        </div>
    </header>

    <div class="content-area">

        <!-- ========================================== -->
        <!-- TAB 1: OVERVIEW -->
        <!-- ========================================== -->
        <div class="tab-pane active" id="tab-overview">
            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-header">
                        <span>إجمالي اللاعبين</span>
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div class="metric-value" id="kpi-players">-</div>
                </div>
                <div class="metric-card">
                    <div class="metric-header">
                        <span>المتصلين الآن</span>
                        <i class="fa-solid fa-wifi" style="color: var(--emerald);"></i>
                    </div>
                    <div class="metric-value" id="kpi-online" style="color: var(--emerald);">-</div>
                </div>
                <div class="metric-card">
                    <div class="metric-header">
                        <span>الذهب المتداول</span>
                        <i class="fa-solid fa-coins" style="color: var(--gold-main);"></i>
                    </div>
                    <div class="metric-value" id="kpi-gold">-</div>
                </div>
                <div class="metric-card">
                    <div class="metric-header">
                        <span>حسابات معاقبة</span>
                        <i class="fa-solid fa-ban" style="color: var(--ruby-bright);"></i>
                    </div>
                    <div class="metric-value" id="kpi-banned" style="color: var(--ruby-bright);">-</div>
                </div>
            </div>

            <!-- Quick Jump Search -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-bolt"></i> وصول سريع لإدارة أي لاعب</div>
                </div>
                <div class="card-body">
                    <div style="display: flex; gap: 12px; max-width: 620px;">
                        <input type="text" id="quick-player-query" class="form-input" placeholder="اكتب اسم اللاعب أو رقم المعرف ID للفتح الفوري...">
                        <button class="btn btn-primary" onclick="quickOpenPlayer()"><i class="fa-solid fa-arrow-left"></i> فتح الملف</button>
                    </div>
                </div>
            </div>

            <!-- Recent Logs Table -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-clock-rotate-left"></i> آخر العمليات الإدارية المنفذة</div>
                    <button class="btn btn-secondary btn-sm" onclick="switchNav('tab-logs')">عرض السجل كاملاً</button>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>الوقت</th>
                                <th>الإداري</th>
                                <th>الإجراء</th>
                                <th>الهدف</th>
                                <th>التفاصيل</th>
                            </tr>
                        </thead>
                        <tbody id="overview-logs-tbody">
                            <tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 22px;">جارِ تحميل السجلات...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- TAB 2: PLAYERS DIRECTORY -->
        <!-- ========================================== -->
        <div class="tab-pane" id="tab-players">
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-users"></i> قاعدة بيانات اللاعبين</div>
                    <div style="font-size: 12px; color: var(--gold-main); font-weight: 700;" id="players-count-label"></div>
                </div>
                <div class="card-body" style="padding-bottom: 0;">
                    <div class="search-bar">
                        <div class="search-input-wrapper">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input type="text" id="player-filter-query" class="form-input" placeholder="ابحث باسم اللاعب أو اسم الدخول أو ID أو الحلف..." oninput="debouncePlayerSearch()">
                        </div>
                        <select id="player-filter-status" class="form-select" style="width: 180px;" onchange="fetchPlayers(1)">
                            <option value="all">كافة الأعضاء</option>
                            <option value="online">المتصلين فقط</option>
                            <option value="banned">المحظورين</option>
                            <option value="muted">المكتومين من الشات</option>
                        </select>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>اسم اللاعب</th>
                                <th>الذهب</th>
                                <th>البرستيج</th>
                                <th>الشرف</th>
                                <th>الحلف</th>
                                <th>الحالة</th>
                                <th style="text-align: left; padding-left: 20px;">إدارة الحساب</th>
                            </tr>
                        </thead>
                        <tbody id="players-tbody">
                            <!-- Injected dynamically -->
                        </tbody>
                    </table>
                </div>

                <div style="padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; border-top: 1.5px solid rgba(223, 186, 107, 0.2);">
                    <div id="players-pagination-info" style="font-size: 12.5px; color: var(--text-secondary); font-weight: 600;"></div>
                    <div id="players-pagination-btns" style="display: flex; gap: 6px;"></div>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- TAB 3: QUICK GRANT (EQUIP & ITEMS) -->
        <!-- ========================================== -->
        <div class="tab-pane" id="tab-quick-grant">
            <div class="two-col">
                <!-- Equipment Grant -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title"><i class="fa-solid fa-shield"></i> منح قطعة عتاد أو سلاح</div>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="form-label">اللاعب المستلم</label>
                            <select id="grant-equip-player" class="form-select"></select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">القطعة أو السلاح</label>
                            <select id="grant-equip-type" class="form-select" onchange="onGrantEquipTypeChange()"></select>
                        </div>
                        <div class="form-group">
                            <label class="form-label" id="grant-equip-lvl-label">المستوى (م1 - م9 للثانوية | م1 للأساسية)</label>
                            <input type="number" id="grant-equip-lvl" class="form-input" min="1" max="9" value="1">
                        </div>
                        <button class="btn btn-primary" style="width: 100%; height: 44px;" onclick="executeGrantEquip()"><i class="fa-solid fa-plus"></i> إيداع العتاد بحساب اللاعب</button>
                    </div>
                </div>

                <!-- Items & VIP Grant -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title"><i class="fa-solid fa-box-open"></i> شحن المواد والرفاهيات</div>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="form-label">اللاعب المستلم</label>
                            <select id="grant-item-player" class="form-select"></select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">المادة أو الحزمة</label>
                            <select id="grant-item-id" class="form-select"></select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">الكمية</label>
                            <input type="number" id="grant-item-amount" class="form-input" min="1" value="50">
                        </div>
                        <button class="btn btn-primary" style="width: 100%; height: 44px;" onclick="executeGrantItem()"><i class="fa-solid fa-plus"></i> إيداع المواد بالحقيبة</button>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-gift"></i> تجهيز مكافأة وإرسالها لجميع اللاعبين</div>
                </div>
                <div class="card-body">
                    <p style="color: var(--text-secondary); margin-bottom: 16px;">
                        أضف الذهب أو العناصر أو العتاد أو موارد المدن. ستصل المكافأة نفسها لكل لاعب، وتضاف الموارد إلى كل مدينة لديه.
                    </p>
                    <div class="form-group" style="max-width: 320px;">
                        <label class="form-label" for="bulk-reward-gold">ذهب يضاف لكل لاعب (اختياري)</label>
                        <input type="number" id="bulk-reward-gold" class="form-input" min="0" max="2147483647" value="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">عناصر الحقيبة</label>
                        <div id="bulk-item-rewards"></div>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="addBulkRewardRow('item')">
                            <i class="fa-solid fa-plus"></i> إضافة عنصر
                        </button>
                    </div>
                    <div class="form-group">
                        <label class="form-label">قطع العتاد</label>
                        <div id="bulk-equip-rewards"></div>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="addBulkRewardRow('equip')">
                            <i class="fa-solid fa-plus"></i> إضافة قطعة عتاد
                        </button>
                    </div>
                    <div class="form-group">
                        <label class="form-label">موارد تضاف لكل مدينة (اختياري)</label>
                        <div class="three-col">
                            <div>
                                <label class="form-label" for="grant-all-food">الغذاء</label>
                                <input type="number" id="grant-all-food" class="form-input" min="0" max="2147483647" value="0">
                            </div>
                            <div>
                                <label class="form-label" for="grant-all-wood">الأخشاب</label>
                                <input type="number" id="grant-all-wood" class="form-input" min="0" max="2147483647" value="0">
                            </div>
                            <div>
                                <label class="form-label" for="grant-all-stone">الصخور</label>
                                <input type="number" id="grant-all-stone" class="form-input" min="0" max="2147483647" value="0">
                            </div>
                            <div>
                                <label class="form-label" for="grant-all-metal">الحديد</label>
                                <input type="number" id="grant-all-metal" class="form-input" min="0" max="2147483647" value="0">
                            </div>
                            <div>
                                <label class="form-label" for="grant-all-coin">العملات</label>
                                <input type="number" id="grant-all-coin" class="form-input" min="0" max="2147483647" value="0">
                            </div>
                        </div>
                    </div>
                    <button id="grant-all-submit" class="btn btn-primary" onclick="executeGrantEventRewards()">
                        <i class="fa-solid fa-gift"></i> إرسال المكافأة للجميع
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- TAB 4: SANCTIONS & BANS -->
        <!-- ========================================== -->
        <div class="tab-pane" id="tab-sanctions">
            <div class="two-col" style="margin-bottom: 26px;">
                <div class="card" style="margin-bottom: 0;">
                    <div class="card-header">
                        <div class="card-title"><i class="fa-solid fa-gavel"></i> إصدار عقوبة على حساب</div>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="form-label">اللاعب المخالف</label>
                            <select id="sanction-player" class="form-select"></select>
                        </div>
                        <div class="two-col">
                            <div class="form-group">
                                <label class="form-label">نوع العقوبة</label>
                                <select id="sanction-type" class="form-select">
                                    <option value="account_ban">🚫 حظر كامل (منع الدخول)</option>
                                    <option value="chat_mute">🔇 كتم الشات (Mute)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">المدة</label>
                                <select id="sanction-duration" class="form-select">
                                    <option value="3600">ساعة واحدة</option>
                                    <option value="86400" selected>يوم واحد (24 ساعة)</option>
                                    <option value="259200">3 أيام</option>
                                    <option value="604800">أسبوع كامل</option>
                                    <option value="2592000">شهر كامل</option>
                                    <option value="999999999">حظر دائم نهائي</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">سبب المخالفة</label>
                            <input type="text" id="sanction-reason" class="form-input" placeholder="اكتب سبب العقوبة للأرشفة...">
                        </div>
                        <button class="btn btn-danger" style="width: 100%; height: 44px;" onclick="executeSanction()"><i class="fa-solid fa-lock"></i> تطبيق العقوبة فوراً</button>
                    </div>
                </div>

                <div class="card" style="margin-bottom: 0;">
                    <div class="card-header">
                        <div class="card-title"><i class="fa-solid fa-shield-halved"></i> معايير العقوبات والرقابة</div>
                    </div>
                    <div class="card-body" style="font-size: 13.5px; color: var(--text-secondary); line-height: 1.9;">
                        <p style="margin-bottom: 12px;">• <strong style="color: var(--gold-bright);">كتم الشات:</strong> يمنع اللاعب من إرسال الرسائل في القنوات العامة والخاصة، مع إمكانية استمرار لعبه داخل مدينته.</p>
                        <p style="margin-bottom: 12px;">• <strong style="color: #ff9ea8;">الحظر الكامل:</strong> يقطع اتصال اللاعب فوراً ويمنعه من تسجيل الدخول إلى السيرفر طوال مدة الحظر.</p>
                        <p>• <strong style="color: var(--emerald);">الشفافية:</strong> كل عقوبة تصدر تُسجل في سجل العمليات باسم الإداري والتاريخ وسبب العقوبة تلقائياً.</p>
                    </div>
                </div>
            </div>

            <!-- Active Sanctions Table -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-list-check"></i> قائمة الحسابات المعاقبة حالياً</div>
                    <button class="btn btn-secondary btn-sm" onclick="fetchActiveSanctions()"><i class="fa-solid fa-rotate"></i> تحديث</button>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>اسم اللاعب</th>
                                <th>نوع العقوبة</th>
                                <th>الحالة</th>
                                <th style="text-align: left; padding-left: 20px;">إلغاء العقوبة</th>
                            </tr>
                        </thead>
                        <tbody id="active-sanctions-tbody">
                            <!-- Injected dynamically -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- TAB 5: STAFF & ACCESS CONTROL -->
        <!-- ========================================== -->
        <?php if ($currentUser['role'] === 'super_admin' || $currentUser['role'] === 'admin'): ?>
        <div class="tab-pane" id="tab-staff">
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-user-gear"></i> المشرفين والمراقبين</div>
                    <button class="btn btn-primary btn-sm" onclick="openStaffModal()"><i class="fa-solid fa-user-plus"></i> إضافة مشرف جديد</button>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>اسم المستخدم</th>
                                <th>الاسم الظاهر</th>
                                <th>الرتبة</th>
                                <th>الحالة</th>
                                <th>آخر دخول</th>
                                <th style="text-align: left; padding-left: 20px;">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody id="staff-tbody">
                            <!-- Injected dynamically -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ========================================== -->
        <!-- TAB 6: AUDIT LOGS -->
        <!-- ========================================== -->
        <div class="tab-pane" id="tab-logs">
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-clock-rotate-left"></i> سجل العمليات الإدارية الكامل (Audit Logs)</div>
                    <button class="btn btn-secondary btn-sm" onclick="fetchAuditLogs(1)"><i class="fa-solid fa-rotate"></i> تحديث</button>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>التاريخ والوقت</th>
                                <th>المشرف</th>
                                <th>نوع الإجراء</th>
                                <th>الهدف</th>
                                <th>التفاصيل والملاحظات</th>
                                <th>عنوان IP</th>
                            </tr>
                        </thead>
                        <tbody id="logs-tbody">
                            <!-- Injected dynamically -->
                        </tbody>
                    </table>
                </div>
                <div style="padding: 16px 24px; display: flex; justify-content: flex-end; gap: 6px;" id="logs-pagination-btns"></div>
            </div>
        </div>

    </div>
</main>

<!-- ========================================================== -->
<!-- UNIFIED PLAYER DRAWER MODAL (CENTRALIZED PLAYER MANAGEMENT) -->
<!-- ========================================================== -->
<div class="modal-overlay" id="player-modal">
    <div class="modal-box modal-lg">
        <div class="modal-head">
            <h3 id="player-modal-title"><i class="fa-solid fa-user"></i> ملف اللاعب الإداري</h3>
            <button class="modal-close-btn" onclick="closeModal('player-modal')">&times;</button>
        </div>

        <div class="modal-body">
            <!-- Modal Internal Tabs -->
            <div class="drawer-nav">
                <div class="drawer-tab active" data-pane="pane-profile"><i class="fa-solid fa-id-card"></i> البيانات والترقية</div>
                <div class="drawer-tab" data-pane="pane-cities"><i class="fa-solid fa-city"></i> المدن والموارد</div>
                <div class="drawer-tab" data-pane="pane-equips"><i class="fa-solid fa-shield"></i> العتاد والأسلحة</div>
                <div class="drawer-tab" data-pane="pane-items"><i class="fa-solid fa-gem"></i> الحقيبة والـ VIP</div>
                <div class="drawer-tab" data-pane="pane-sanctions"><i class="fa-solid fa-gavel"></i> العقوبات والحظر</div>
            </div>

            <!-- Pane 1: Profile & Basic Data -->
            <div class="drawer-pane active" id="pane-profile">
                <input type="hidden" id="p-id">
                <div class="two-col">
                    <div class="form-group">
                        <label class="form-label">اسم اللاعب</label>
                        <input type="text" id="p-name" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">الذهب (Gold)</label>
                        <input type="number" id="p-gold" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">البرستيج (Prestige)</label>
                        <input type="number" id="p-prestige" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">الشرف (Honor)</label>
                        <input type="number" id="p-honor" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">الرتبة العسكرية</label>
                        <input type="number" id="p-rank" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">الصلاحية داخل اللعبة</label>
                        <select id="p-group" class="form-select">
                            <option value="0">لاعب عادي</option>
                            <option value="1">مراقب شات داخل اللعبة (Moderator)</option>
                            <option value="2">مشرف داخل اللعبة (Supervisor)</option>
                            <option value="3">مدير عام داخل اللعبة (Administrator)</option>
                        </select>
                    </div>
                </div>
                <button class="btn btn-primary" onclick="savePlayerProfile()"><i class="fa-solid fa-check"></i> حفظ التعديلات الأساسية</button>
            </div>

            <!-- Pane 2: Cities & Resources -->
            <div class="drawer-pane" id="pane-cities">
                <div class="form-group">
                    <label class="form-label">اختر المدينة التابعة للاعب</label>
                    <select id="p-city-select" class="form-select" onchange="onCitySelected()"></select>
                </div>

                <div id="p-city-data-box" style="display: none; background: rgba(0,0,0,0.3); border: 1.5px solid #57462a; border-radius: var(--radius-sm); padding: 18px; margin-bottom: 18px;">
                    <div class="three-col" style="margin-bottom: 16px;">
                        <div class="form-group" style="margin-bottom: 8px;">
                            <label class="form-label">🌾 الغذاء</label>
                            <input type="number" id="c-food" class="form-input">
                        </div>
                        <div class="form-group" style="margin-bottom: 8px;">
                            <label class="form-label">🪵 الأخشاب</label>
                            <input type="number" id="c-wood" class="form-input">
                        </div>
                        <div class="form-group" style="margin-bottom: 8px;">
                            <label class="form-label">🪨 الصخور</label>
                            <input type="number" id="c-stone" class="form-input">
                        </div>
                        <div class="form-group" style="margin-bottom: 8px;">
                            <label class="form-label">⛏️ الحديد</label>
                            <input type="number" id="c-metal" class="form-input">
                        </div>
                        <div class="form-group" style="margin-bottom: 8px;">
                            <label class="form-label">🪙 العملات</label>
                            <input type="number" id="c-coin" class="form-input">
                        </div>
                        <div class="form-group" style="margin-bottom: 8px;">
                            <label class="form-label">❤️ الولاء (1-100)</label>
                            <input type="number" id="c-loy" class="form-input" min="0" max="100">
                        </div>
                    </div>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <button class="btn btn-primary btn-sm" onclick="saveCityResources()"><i class="fa-solid fa-floppy-disk"></i> حفظ الموارد</button>
                        <button class="btn btn-secondary btn-sm" onclick="quickBoostCity('max_res')"><i class="fa-solid fa-bolt"></i> تعبئة 100 مليون من كل مورد</button>
                        <button class="btn btn-secondary btn-sm" onclick="quickBoostCity('max_loy_pop')"><i class="fa-solid fa-users"></i> رفع الولاء والسكان</button>
                        <button class="btn btn-secondary btn-sm" onclick="quickBoostCity('max_army')"><i class="fa-solid fa-shield"></i> حقن 50,000 مقاتل</button>
                    </div>
                </div>
            </div>

            <!-- Pane 3: Equipment -->
            <div class="drawer-pane" id="pane-equips">
                <!-- Inline Quick Grant -->
                <div style="background: rgba(0,0,0,0.3); border: 1.5px solid #57462a; border-radius: var(--radius-sm); padding: 16px; margin-bottom: 18px;">
                    <div style="font-size: 13px; font-weight: 700; color: var(--gold-bright); margin-bottom: 12px;">إضافة قطعة عتاد جديدة لهذا اللاعب:</div>
                    <div style="display: flex; gap: 12px; align-items: flex-end;">
                        <div style="flex: 2;">
                            <label class="form-label">اختر المعدة</label>
                            <select id="p-grant-equip-catalog" class="form-select"></select>
                        </div>
                        <div style="width: 140px;">
                            <label class="form-label">المستوى (1-9)</label>
                            <input type="number" id="p-grant-equip-lvl" class="form-input" value="1" min="1" max="9">
                        </div>
                        <button class="btn btn-primary" onclick="playerModalGrantEquip()"><i class="fa-solid fa-plus"></i> منح</button>
                    </div>
                </div>

                <div style="font-size: 13px; font-weight: 700; color: var(--text-muted); margin-bottom: 12px;">العتاد الحالي في حقيبة وأبطال اللاعب:</div>
                <div class="item-grid" id="p-equips-grid"></div>
            </div>

            <!-- Pane 4: Items & VIP -->
            <div class="drawer-pane" id="pane-items">
                <!-- VIP Royal Boost Card -->
                <div style="background: linear-gradient(135deg, rgba(88, 67, 16, 0.4), rgba(20, 23, 28, 0.8)); border: 1.5px solid var(--gold-main); border-radius: var(--radius-sm); padding: 18px; margin-bottom: 18px; display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap;">
                    <div>
                        <div style="font-size: 14px; font-weight: 700; color: var(--gold-bright);"><i class="fa-solid fa-crown"></i> تفعيل باقة الرفاهيات الملكية (VIP Boost)</div>
                        <div style="font-size: 12px; color: var(--text-secondary); margin-top: 4px;">تفعيل الهدنة الشاملة، تعزيز الإنتاج +25%، ومعززات الهجوم والدفاع.</div>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <select id="p-vip-duration" class="form-select" style="width: 130px;">
                            <option value="7">7 أيام</option>
                            <option value="30" selected>30 يوماً</option>
                            <option value="90">90 يوماً</option>
                            <option value="365">سنة كاملة</option>
                        </select>
                        <button class="btn btn-primary" onclick="playerModalApplyVip()"><i class="fa-solid fa-wand-magic-sparkles"></i> تفعيل</button>
                    </div>
                </div>

                <!-- Inline Quick Item Grant -->
                <div style="background: rgba(0,0,0,0.3); border: 1.5px solid #57462a; border-radius: var(--radius-sm); padding: 16px; margin-bottom: 18px;">
                    <div style="font-size: 13px; font-weight: 700; color: var(--gold-bright); margin-bottom: 12px;">إيداع مواد بالحقيبة:</div>
                    <div style="display: flex; gap: 12px; align-items: flex-end;">
                        <div style="flex: 2;">
                            <label class="form-label">المادة</label>
                            <select id="p-grant-item-catalog" class="form-select"></select>
                        </div>
                        <div style="width: 130px;">
                            <label class="form-label">الكمية</label>
                            <input type="number" id="p-grant-item-amount" class="form-input" value="50" min="1">
                        </div>
                        <button class="btn btn-primary" onclick="playerModalGrantItem()"><i class="fa-solid fa-plus"></i> إيداع</button>
                    </div>
                </div>

                <div style="font-size: 13px; font-weight: 700; color: var(--text-muted); margin-bottom: 12px;">المواد المتوفرة بالحقيبة حالياً:</div>
                <div class="item-grid" id="p-items-grid"></div>
            </div>

            <!-- Pane 5: Sanctions -->
            <div class="drawer-pane" id="pane-sanctions">
                <div style="max-width: 520px;">
                    <div class="form-group">
                        <label class="form-label">نوع العقوبة</label>
                        <select id="p-sanction-type" class="form-select">
                            <option value="account_ban">🚫 حظر الحساب كلياً من تسجيل الدخول</option>
                            <option value="chat_mute">🔇 كتم الشات والمحادثات (Mute)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">المدة</label>
                        <select id="p-sanction-duration" class="form-select">
                            <option value="3600">ساعة واحدة</option>
                            <option value="86400" selected>يوم واحد (24 ساعة)</option>
                            <option value="259200">3 أيام</option>
                            <option value="604800">أسبوع كامل</option>
                            <option value="2592000">شهر كامل</option>
                            <option value="999999999">حظر دائم نهائي</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">السبب</label>
                        <input type="text" id="p-sanction-reason" class="form-input" placeholder="اكتب سبب العقوبة...">
                    </div>
                    <button class="btn btn-danger" onclick="playerModalExecuteSanction()"><i class="fa-solid fa-gavel"></i> تطبيق العقوبة على هذا اللاعب</button>
                </div>
            </div>

        </div>
        <div class="modal-foot">
            <button class="btn btn-secondary" onclick="closeModal('player-modal')">إغلاق</button>
        </div>
    </div>
</div>

<!-- ========================================================== -->
<!-- STAFF MODAL (ADD / EDIT ADMIN USER) -->
<!-- ========================================================== -->
<div class="modal-overlay" id="staff-modal">
    <div class="modal-box modal-sm">
        <div class="modal-head">
            <h3 id="staff-modal-title"><i class="fa-solid fa-user-plus"></i> إضافة مشرف جديد</h3>
            <button class="modal-close-btn" onclick="closeModal('staff-modal')">&times;</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="staff-id">
            <div class="form-group">
                <label class="form-label">اسم المستخدم للدخول</label>
                <input type="text" id="staff-username" class="form-input">
            </div>
            <div class="form-group">
                <label class="form-label">كلمة المرور (اتركه فارغاً عند التعديل للإبقاء عليها)</label>
                <input type="password" id="staff-password" class="form-input">
            </div>
            <div class="form-group">
                <label class="form-label">الاسم الظاهر</label>
                <input type="text" id="staff-name" class="form-input">
            </div>
            <div class="form-group">
                <label class="form-label">الرتبة الإدارية</label>
                <select id="staff-role" class="form-select">
                    <option value="moderator">مراقب عام (Moderator) - رقابة وشات</option>
                    <option value="supervisor">مشرف عام (Supervisor) - شحن وموارد</option>
                    <option value="admin">مدير عام (Admin) - كافة الصلاحيات</option>
                    <?php if ($currentUser['role'] === 'super_admin'): ?>
                    <option value="super_admin">مدير تنفيذي (Super Admin) - صلاحيات مطلقة</option>
                    <?php endif; ?>
                </select>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-secondary" onclick="closeModal('staff-modal')">إلغاء</button>
            <button class="btn btn-primary" onclick="saveStaffUser()"><i class="fa-solid fa-check"></i> حفظ</button>
        </div>
    </div>
</div>

<!-- حاوية التنبيهات العائمة (Toast Shelf) -->
<div id="toast-shelf"></div>

<!-- محرك الجافاسكريبت والربط المنطقي الكامل (Preserved 100%) -->
<script>
const adminCsrfToken = document.querySelector('meta[name="csrf-token"]').content;
const nativeFetch = window.fetch.bind(window);
window.fetch = function(input, options = {}) {
    const requestUrl = typeof input === 'string' ? input : input.url;
    const requestMethod = (options.method || (input instanceof Request ? input.method : 'GET')).toUpperCase();
    if (requestMethod === 'POST' && /(^|\/)api\.php(?:[?#]|$)/.test(requestUrl)) {
        const headers = new Headers(options.headers || (input instanceof Request ? input.headers : undefined));
        headers.set('X-CSRF-Token', adminCsrfToken);
        options = Object.assign({}, options, { headers });
    }
    return nativeFetch(input, options);
};

// State
let appCatalog = { items: [], equips: [] };
let cachedPlayers = [];
let activePlayerDetails = null;

// Notification Toast
function notify(text, type = 'success') {
    const shelf = document.getElementById('toast-shelf');
    const msg = document.createElement('div');
    msg.className = `toast-msg ${type}`;
    msg.innerHTML = `<i class="fa-solid ${type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation'}"></i> <span>${text}</span>`;
    shelf.appendChild(msg);
    setTimeout(() => { msg.remove(); }, 3500);
}

// Modal Helpers
function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }

function toggleMobileNav(button) {
    const sidebar = document.querySelector('.sidebar');
    const isOpen = sidebar.classList.toggle('mobile-open');
    button.setAttribute('aria-expanded', String(isOpen));
}

// Navigation Switcher
function switchNav(tabId) {
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.menu-item').forEach(m => m.classList.remove('active'));

    const pane = document.getElementById(tabId);
    if (pane) pane.classList.add('active');

    const navItem = document.querySelector(`.menu-item[data-tab="${tabId}"]`);
    if (navItem) navItem.classList.add('active');

    const titleMap = {
        'tab-overview': 'نظرة عامة على السيرفر',
        'tab-players': 'دليل إدارة اللاعبين',
        'tab-quick-grant': 'منح الموارد والمعدات',
        'tab-sanctions': 'العقوبات والحظر والرقابة',
        'tab-staff': 'المشرفين والصلاحيات الإدارية',
        'tab-logs': 'سجل العمليات الإدارية (Audit Logs)'
    };
    document.getElementById('topbar-title').innerText = titleMap[tabId] || 'لوحة التحكم';

    if (window.matchMedia('(max-width: 900px)').matches) {
        document.querySelector('.sidebar').classList.remove('mobile-open');
        document.querySelector('.mobile-nav-toggle').setAttribute('aria-expanded', 'false');
    }

    // Lazy loads
    if (tabId === 'tab-players') fetchPlayers(1);
    if (tabId === 'tab-sanctions') fetchActiveSanctions();
    if (tabId === 'tab-staff') fetchStaffList();
    if (tabId === 'tab-logs') fetchAuditLogs(1);
}

document.querySelectorAll('.menu-item').forEach(item => {
    item.addEventListener('click', function() {
        switchNav(this.getAttribute('data-tab'));
    });
});

function refreshCurrentTab() {
    const active = document.querySelector('.menu-item.active');
    if (active) switchNav(active.getAttribute('data-tab'));
    fetchStats();
}

// Drawer Tabs inside Player Modal
document.querySelectorAll('.drawer-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.drawer-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.drawer-pane').forEach(p => p.classList.remove('active'));
        this.classList.add('active');
        const paneId = this.getAttribute('data-pane');
        const targetPane = document.getElementById(paneId);
        if (targetPane) targetPane.classList.add('active');
    });
});

// 1. Initial Overview & Stats
async function fetchStats() {
    try {
        const res = await fetch('api.php?action=get_stats');
        const json = await res.json();
        if (json.state === 'ok') {
            const d = json.data;
            document.getElementById('kpi-players').innerText = Number(d.total_players).toLocaleString();
            document.getElementById('kpi-online').innerText = Number(d.online_players).toLocaleString();
            document.getElementById('kpi-gold').innerText = Number(d.total_gold).toLocaleString();
            document.getElementById('kpi-banned').innerText = (Number(d.banned_players) + Number(d.muted_players)).toLocaleString();

            const tbody = document.getElementById('overview-logs-tbody');
            if (d.recent_logs && d.recent_logs.length > 0) {
                tbody.innerHTML = d.recent_logs.map(l => `
                    <tr>
                        <td>${l.created_at}</td>
                        <td><span class="pill pill-amber">${l.admin_name}</span></td>
                        <td><span class="pill pill-emerald">${l.action}</span></td>
                        <td><strong>${l.target_name || l.target_id || '-'}</strong></td>
                        <td>${l.details || '-'}</td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 20px;">لا توجد أي عمليات مسجلة حديثاً</td></tr>';
            }
        }
    } catch (e) {
        console.error(e);
    }
}

// 2. Catalog & Selectors
async function fetchCatalogAndPlayers() {
    try {
        const [catRes, pRes] = await Promise.all([
            fetch('api.php?action=get_catalog'),
            fetch('api.php?action=get_players&limit=250')
        ]);
        const catJson = await catRes.json();
        const pJson = await pRes.json();

        if (catJson.state === 'ok') {
            appCatalog = catJson.data;
            renderCatalogDropdowns();
        }
        if (pJson.state === 'ok') {
            cachedPlayers = pJson.data.players;
            renderPlayerDropdowns();
        }
    } catch(e) {
        console.error(e);
    }
}

function renderCatalogDropdowns() {
    // Equipment select
    const equipOptions = appCatalog.equips.map(eq => `<option value="${eq.id}">[${eq.category}] ${eq.name}</option>`).join('');
    const grantEquipSel = document.getElementById('grant-equip-type');
    const modalEquipSel = document.getElementById('p-grant-equip-catalog');
    if (grantEquipSel) grantEquipSel.innerHTML = equipOptions;
    if (modalEquipSel) modalEquipSel.innerHTML = equipOptions;

    // Items select
    const itemOptions = appCatalog.items.map(it => `<option value="${it.id}">${it.name}</option>`).join('');
    const grantItemSel = document.getElementById('grant-item-id');
    const modalItemSel = document.getElementById('p-grant-item-catalog');
    if (grantItemSel) grantItemSel.innerHTML = itemOptions;
    if (modalItemSel) modalItemSel.innerHTML = itemOptions;

    if (document.getElementById('bulk-item-rewards')?.children.length === 0) {
        addBulkRewardRow('item');
    }
    if (document.getElementById('bulk-equip-rewards')?.children.length === 0) {
        addBulkRewardRow('equip');
    }
}

function renderPlayerDropdowns() {
    const opts = '<option value="">-- اختر اللاعب --</option>' + cachedPlayers.map(p => `
        <option value="${p.id_player}">#${p.id_player} - ${p.name} (ذهب: ${Number(p.gold).toLocaleString()})</option>
    `).join('');

    ['grant-equip-player', 'grant-item-player', 'sanction-player'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.innerHTML = opts;
    });
}

function onGrantEquipTypeChange() {
    const val = document.getElementById('grant-equip-type').value;
    const isPrimary = val.includes('_sword') || val.includes('_helmet') || val.includes('_shield') || val.includes('_armor') || val.includes('_boot');
    const lvlInput = document.getElementById('grant-equip-lvl');
    const lvlLabel = document.getElementById('grant-equip-lvl-label');
    if (isPrimary) {
        lvlInput.value = 1;
        lvlInput.disabled = true;
        lvlLabel.innerText = 'المستوى (م1 ثابت للأساسية)';
    } else {
        lvlInput.disabled = false;
        lvlLabel.innerText = 'المستوى (م1 - م9 للثانوية)';
    }
}

// 3. Players Directory Table
let searchTimer = null;
function debouncePlayerSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { fetchPlayers(1); }, 350);
}

async function fetchPlayers(page = 1) {
    const query = document.getElementById('player-filter-query').value;
    const status = document.getElementById('player-filter-status').value;
    const tbody = document.getElementById('players-tbody');
    tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; color: var(--text-muted); padding: 24px;">جارِ جلب اللاعبين...</td></tr>';

    try {
        const res = await fetch(`api.php?action=get_players&page=${page}&search=${encodeURIComponent(query)}&status=${status}`);
        const json = await res.json();
        if (!res.ok || json.state !== 'ok') {
            const message = json.message || 'تعذر جلب بيانات اللاعبين';
            tbody.innerHTML = `<tr><td colspan="8" style="text-align: center; color: var(--text-muted); padding: 24px;">${message}</td></tr>`;
            document.getElementById('players-count-label').innerText = '';
            document.getElementById('players-pagination-btns').innerHTML = '';
            document.getElementById('players-pagination-info').innerText = '';
            notify(message, 'error');
            return;
        }

        const players = json.data.players;
        document.getElementById('players-count-label').innerText = `إجمالي النتائج: ${json.data.total.toLocaleString()} لاعب`;

        if (players.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; color: var(--text-muted); padding: 30px;">لم يتم العثور على أي لاعبين يطابقون البحث</td></tr>';
            document.getElementById('players-pagination-btns').innerHTML = '';
            document.getElementById('players-pagination-info').innerText = '';
            return;
        }

        tbody.innerHTML = players.map(p => {
            let statusPill = p.online == 1 ? '<span class="pill pill-emerald">متصل</span>' : '<span class="pill pill-gray">غير متصل</span>';
            if (p.is_banned) statusPill = '<span class="pill pill-rose">محظور كلياً</span>';
            else if (p.is_muted) statusPill = '<span class="pill pill-amber">مكتوم شات</span>';

            return `
                    <tr>
                        <td><strong>#${p.id_player}</strong></td>
                        <td><span style="font-weight: 700; color: #fff;">${p.name}</span></td>
                        <td>${Number(p.gold).toLocaleString()}</td>
                        <td>${Number(p.prestige).toLocaleString()}</td>
                        <td>${Number(p.honor).toLocaleString()}</td>
                        <td>${p.guild || '<span style="color: var(--text-muted);">-</span>'}</td>
                        <td>${statusPill}</td>
                        <td style="text-align: left; padding-left: 20px;">
                            <div style="display: flex; gap: 6px; justify-content: flex-end;">
                                <button class="btn btn-secondary btn-sm" onclick="openPlayerHub(${p.id_player})"><i class="fa-solid fa-sliders"></i> إدارة الحساب</button>
                                ${p.is_banned 
                                    ? `<button class="btn btn-success btn-sm" onclick="unbanPlayerQuick(${p.id_player})"><i class="fa-solid fa-unlock"></i> فك الحظر</button>` 
                                    : `<button class="btn btn-danger btn-sm" onclick="quickSanctionPrompt(${p.id_player}, '${p.name}')"><i class="fa-solid fa-ban"></i> حظر</button>`
                                }
                            </div>
                        </td>
                    </tr>
                `;
        }).join('');

        // Pagination info & buttons
        document.getElementById('players-pagination-info').innerText = `الصفحة ${json.data.page} من ${json.data.total_pages}`;
        let pagHtml = '';
        for (let i = 1; i <= json.data.total_pages; i++) {
            if (i <= 7 || i === json.data.total_pages) {
                pagHtml += `<button class="btn ${i === json.data.page ? 'btn-primary' : 'btn-secondary'} btn-sm" onclick="fetchPlayers(${i})">${i}</button>`;
            }
        }
        document.getElementById('players-pagination-btns').innerHTML = pagHtml;
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; color: var(--text-muted); padding: 24px;">تعذر الاتصال بالخادم لجلب اللاعبين</td></tr>';
        document.getElementById('players-count-label').innerText = '';
        document.getElementById('players-pagination-btns').innerHTML = '';
        document.getElementById('players-pagination-info').innerText = '';
        notify('تعذر جلب بيانات اللاعبين', 'error');
    }
}

// 4. Centralized Player Hub (The Unified Modal)
async function openPlayerHub(idPlayer) {
    try {
        const res = await fetch(`api.php?action=get_player_details&id_player=${idPlayer}`);
        const json = await res.json();
        if (json.state === 'ok') {
            activePlayerDetails = json.data;
            const p = activePlayerDetails.player;

            document.getElementById('player-modal-title').innerHTML = `<i class="fa-solid fa-user"></i> إدارة حساب: ${p.name} (#${p.id_player})`;

            // Profile Tab
            document.getElementById('p-id').value = p.id_player;
            document.getElementById('p-name').value = p.name;
            document.getElementById('p-gold').value = p.gold;
            document.getElementById('p-prestige').value = p.prestige;
            document.getElementById('p-honor').value = p.honor;
            document.getElementById('p-rank').value = p.rank;
            document.getElementById('p-group').value = p.user_group || 0;

            // Cities Tab
            const citySel = document.getElementById('p-city-select');
            if (activePlayerDetails.cities.length === 0) {
                citySel.innerHTML = '<option value="">لا توجد مدن لهذا اللاعب</option>';
                document.getElementById('p-city-data-box').style.display = 'none';
            } else {
                citySel.innerHTML = activePlayerDetails.cities.map(c => `
                    <option value="${c.id_city}">مدينة: ${c.name} [${c.x}, ${c.y}] - مستوى ${c.lvl}</option>
                `).join('');
                onCitySelected();
            }

            // Equips Tab
            renderPlayerHubEquips();

            // Items Tab
            renderPlayerHubItems();

            // Reset Tab to Profile
            document.querySelector('.drawer-tab[data-pane="pane-profile"]').click();

            openModal('player-modal');
        } else {
            notify(json.message, 'error');
        }
    } catch(e) {
        notify('خطأ في جلب ملف اللاعب', 'error');
    }
}

function onCitySelected() {
    const cityId = document.getElementById('p-city-select').value;
    const city = activePlayerDetails.cities.find(c => c.id_city == cityId);
    const box = document.getElementById('p-city-data-box');
    if (city) {
        box.style.display = 'block';
        document.getElementById('c-food').value = city.food;
        document.getElementById('c-wood').value = city.wood;
        document.getElementById('c-stone').value = city.stone;
        document.getElementById('c-metal').value = city.metal;
        document.getElementById('c-coin').value = city.coin;
        document.getElementById('c-loy').value = city.loy;
    } else {
        box.style.display = 'none';
    }
}

async function savePlayerProfile() {
    const fd = new FormData();
    fd.append('action', 'save_player');
    fd.append('id_player', document.getElementById('p-id').value);
    fd.append('name', document.getElementById('p-name').value);
    fd.append('gold', document.getElementById('p-gold').value);
    fd.append('prestige', document.getElementById('p-prestige').value);
    fd.append('honor', document.getElementById('p-honor').value);
    fd.append('rank', document.getElementById('p-rank').value);
    fd.append('user_group', document.getElementById('p-group').value);

    const res = await fetch('api.php', { method: 'POST', body: fd });
    const json = await res.json();
    if (json.state === 'ok') {
        notify(json.message);
        fetchPlayers(1);
    } else {
        notify(json.message, 'error');
    }
}

async function saveCityResources() {
    const cityId = document.getElementById('p-city-select').value;
    const fd = new FormData();
    fd.append('action', 'update_city_resources');
    fd.append('id_city', cityId);
    fd.append('food', document.getElementById('c-food').value);
    fd.append('wood', document.getElementById('c-wood').value);
    fd.append('stone', document.getElementById('c-stone').value);
    fd.append('metal', document.getElementById('c-metal').value);
    fd.append('coin', document.getElementById('c-coin').value);
    fd.append('loy', document.getElementById('c-loy').value);

    const res = await fetch('api.php', { method: 'POST', body: fd });
    const json = await res.json();
    if (json.state === 'ok') {
        notify(json.message);
    } else {
        notify(json.message, 'error');
    }
}

async function quickBoostCity(type) {
    const cityId = document.getElementById('p-city-select').value;
    const fd = new FormData();
    fd.append('action', 'quick_boost_city');
    fd.append('id_city', cityId);
    fd.append('type', type);

    const res = await fetch('api.php', { method: 'POST', body: fd });
    const json = await res.json();
    if (json.state === 'ok') {
        notify(json.message);
        openPlayerHub(activePlayerDetails.player.id_player);
    } else {
        notify(json.message, 'error');
    }
}

function renderPlayerHubEquips() {
    const grid = document.getElementById('p-equips-grid');
    const equips = activePlayerDetails.equips || [];
    if (equips.length === 0) {
        grid.innerHTML = '<div style="grid-column: 1/-1; color: var(--text-muted); padding: 14px; text-align: center;">لا يمتلك هذا اللاعب أي عتاد حالياً</div>';
        return;
    }
    grid.innerHTML = equips.map(eq => `
        <div class="item-card">
            <i class="fa-solid fa-trash del-btn" onclick="playerModalDeleteEquip(${eq.id_equip})" title="حذف"></i>
            <img src="../${eq.image}" onerror="this.src='images/equip/equip0.jpg'">
            <div class="name">${eq.name}</div>
            <div class="count">+${eq.lvl}</div>
        </div>
    `).join('');
}

async function playerModalGrantEquip() {
    const id = activePlayerDetails.player.id_player;
    const type = document.getElementById('p-grant-equip-catalog').value;
    const lvl = document.getElementById('p-grant-equip-lvl').value;

    const fd = new FormData();
    fd.append('action', 'grant_equip');
    fd.append('id_player', id);
    fd.append('type', type);
    fd.append('lvl', lvl);

    const res = await fetch('api.php', { method: 'POST', body: fd });
    const json = await res.json();
    if (json.state === 'ok') {
        notify(json.message);
        openPlayerHub(id);
    } else {
        notify(json.message, 'error');
    }
}

async function playerModalDeleteEquip(idEquip) {
    if (!confirm('هل أنت متأكد من حذف هذه القطعة؟')) return;
    const fd = new FormData();
    fd.append('action', 'delete_equip');
    fd.append('id_equip', idEquip);
    const res = await fetch('api.php', { method: 'POST', body: fd });
    const json = await res.json();
    if (json.state === 'ok') {
        notify(json.message);
        openPlayerHub(activePlayerDetails.player.id_player);
    }
}

function renderPlayerHubItems() {
    const grid = document.getElementById('p-items-grid');
    const items = activePlayerDetails.items || [];
    if (items.length === 0) {
        grid.innerHTML = '<div style="grid-column: 1/-1; color: var(--text-muted); padding: 14px; text-align: center;">حقيبة اللاعب فارغة تماماً</div>';
        return;
    }
    grid.innerHTML = items.map(it => `
        <div class="item-card">
            <i class="fa-solid fa-trash del-btn" onclick="playerModalDeleteItem('${it.id_item}')" title="حذف"></i>
            <img src="../${it.image}" onerror="this.src='images/items/item000.jpg'">
            <div class="name">${it.name}</div>
            <div class="count">العدد: ${Number(it.amount).toLocaleString()}</div>
        </div>
    `).join('');
}

async function playerModalGrantItem() {
    const id = activePlayerDetails.player.id_player;
    const idItem = document.getElementById('p-grant-item-catalog').value;
    const amount = document.getElementById('p-grant-item-amount').value;

    const fd = new FormData();
    fd.append('action', 'grant_item');
    fd.append('id_player', id);
    fd.append('id_item', idItem);
    fd.append('amount', amount);

    const res = await fetch('api.php', { method: 'POST', body: fd });
    const json = await res.json();
    if (json.state === 'ok') {
        notify(json.message);
        openPlayerHub(id);
    } else {
        notify(json.message, 'error');
    }
}

async function playerModalDeleteItem(idItem) {
    if (!confirm('حذف هذه المادة من حقيبة اللاعب؟')) return;
    const fd = new FormData();
    fd.append('action', 'delete_item');
    fd.append('id_player', activePlayerDetails.player.id_player);
    fd.append('id_item', idItem);
    const res = await fetch('api.php', { method: 'POST', body: fd });
    const json = await res.json();
    if (json.state === 'ok') {
        notify(json.message);
        openPlayerHub(activePlayerDetails.player.id_player);
    }
}

async function playerModalApplyVip() {
    const id = activePlayerDetails.player.id_player;
    const days = document.getElementById('p-vip-duration').value;
    const fd = new FormData();
    fd.append('action', 'vip_boost_luxuries');
    fd.append('id_player', id);
    fd.append('days', days);

    const res = await fetch('api.php', { method: 'POST', body: fd });
    const json = await res.json();
    if (json.state === 'ok') {
        notify(json.message);
    } else {
        notify(json.message, 'error');
    }
}

async function playerModalExecuteSanction() {
    const id = activePlayerDetails.player.id_player;
    const type = document.getElementById('p-sanction-type').value;
    const duration = document.getElementById('p-sanction-duration').value;
    const reason = document.getElementById('p-sanction-reason').value;

    const fd = new FormData();
    fd.append('action', type === 'account_ban' ? 'ban_player' : 'mute_player');
    fd.append('id_player', id);
    fd.append('duration', duration);
    fd.append('reason', reason);

    const res = await fetch('api.php', { method: 'POST', body: fd });
    const json = await res.json();
    if (json.state === 'ok') {
        notify(json.message);
        closeModal('player-modal');
        fetchPlayers(1);
    } else {
        notify(json.message, 'error');
    }
}

// 5. Standalone Tools Operations (Quick Grant & Sanctions)
async function executeGrantEquip() {
    const id = document.getElementById('grant-equip-player').value;
    if (!id) { notify('يرجى اختيار اللاعب', 'error'); return; }

    const type = document.getElementById('grant-equip-type').value;
    const lvl = document.getElementById('grant-equip-lvl').value;

    const fd = new FormData();
    fd.append('action', 'grant_equip');
    fd.append('id_player', id);
    fd.append('type', type);
    fd.append('lvl', lvl);

    const res = await fetch('api.php', { method: 'POST', body: fd });
    const json = await res.json();
    if (json.state === 'ok') {
        notify(json.message);
    } else {
        notify(json.message, 'error');
    }
}

async function executeGrantItem() {
    const id = document.getElementById('grant-item-player').value;
    if (!id) { notify('يرجى اختيار اللاعب', 'error'); return; }

    const idItem = document.getElementById('grant-item-id').value;
    const amount = document.getElementById('grant-item-amount').value;

    const fd = new FormData();
    fd.append('action', 'grant_item');
    fd.append('id_player', id);
    fd.append('id_item', idItem);
    fd.append('amount', amount);

    const res = await fetch('api.php', { method: 'POST', body: fd });
    const json = await res.json();
    if (json.state === 'ok') {
        notify(json.message);
    } else {
        notify(json.message, 'error');
    }
}

function addBulkRewardRow(type) {
    const container = document.getElementById(type === 'item' ? 'bulk-item-rewards' : 'bulk-equip-rewards');
    if (!container) return;

    const row = document.createElement('div');
    row.className = 'bulk-reward-row';
    row.style.cssText = 'display:grid;grid-template-columns:minmax(180px,1fr) 120px 110px auto;gap:8px;align-items:center;margin-bottom:8px;';

    const select = document.createElement('select');
    select.className = 'form-select bulk-reward-catalog';
    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = type === 'item' ? '-- اختر العنصر --' : '-- اختر العتاد --';
    select.appendChild(placeholder);

    const catalog = type === 'item' ? appCatalog.items : appCatalog.equips;
    catalog.forEach(entry => {
        const option = document.createElement('option');
        option.value = entry.id;
        option.textContent = type === 'equip' ? `[${entry.category}] ${entry.name}` : entry.name;
        if (type === 'equip') {
            option.dataset.maxLevel = String(entry.max_lvl);
            option.dataset.upgradeable = String(entry.can_upgrade);
        }
        select.appendChild(option);
    });

    const amount = document.createElement('input');
    amount.type = 'number';
    amount.className = 'form-input bulk-reward-amount';
    amount.min = '1';
    amount.max = type === 'item' ? '1000000' : '100';
    amount.value = '1';
    amount.setAttribute('aria-label', 'الكمية لكل لاعب');

    row.appendChild(select);
    if (type === 'equip') {
        const level = document.createElement('input');
        level.type = 'number';
        level.className = 'form-input bulk-reward-level';
        level.min = '1';
        level.max = '9';
        level.value = '1';
        level.setAttribute('aria-label', 'مستوى العتاد');
        select.addEventListener('change', () => {
            const option = select.selectedOptions[0];
            level.max = option.dataset.maxLevel || '9';
            level.disabled = option.dataset.upgradeable === 'false';
            if (level.disabled) level.value = '1';
        });
        row.appendChild(level);
    }
    row.appendChild(amount);

    const remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'btn btn-danger btn-sm';
    remove.textContent = 'إزالة';
    remove.addEventListener('click', () => row.remove());
    row.appendChild(remove);
    container.appendChild(row);
}

async function executeGrantEventRewards() {
    const submitButton = document.getElementById('grant-all-submit');
    const resourceNames = ['food', 'wood', 'stone', 'metal', 'coin'];
    const resources = Object.fromEntries(resourceNames.map(resource => [
        resource,
        document.getElementById(`grant-all-${resource}`).value
    ]));

    const rewards = {
        gold: document.getElementById('bulk-reward-gold').value,
        items: [...document.querySelectorAll('#bulk-item-rewards .bulk-reward-row')].map(row => ({
            id: row.querySelector('.bulk-reward-catalog').value,
            amount: row.querySelector('.bulk-reward-amount').value
        })).filter(item => item.id),
        equips: [...document.querySelectorAll('#bulk-equip-rewards .bulk-reward-row')].map(row => ({
            id: row.querySelector('.bulk-reward-catalog').value,
            level: row.querySelector('.bulk-reward-level').value,
            amount: row.querySelector('.bulk-reward-amount').value
        })).filter(equip => equip.id),
        city_resources: resources
    };

    const numericValues = [
        rewards.gold,
        ...resourceNames.map(resource => resources[resource]),
        ...rewards.items.map(item => item.amount),
        ...rewards.equips.flatMap(equip => [equip.level, equip.amount])
    ];
    if (numericValues.some(value => !Number.isSafeInteger(Number(value)) || Number(value) < 0)) {
        notify('تحقق من أن كل الكميات والمستويات أعداد صحيحة غير سالبة', 'error');
        return;
    }

    const hasRewards = Number(rewards.gold) > 0
        || resourceNames.some(resource => Number(resources[resource]) > 0)
        || rewards.items.length > 0
        || rewards.equips.length > 0;
    if (!hasRewards) {
        notify('اختر مكافأة واحدة على الأقل قبل الإرسال', 'error');
        return;
    }
    if (rewards.items.length > 20 || rewards.equips.length > 20) {
        notify('الحد الأقصى 20 نوعاً من العناصر و20 قطعة عتاد في الإرسال الواحد', 'error');
        return;
    }

    const summary = [
        Number(rewards.gold) > 0 ? `ذهب: ${Number(rewards.gold).toLocaleString()} لكل لاعب` : '',
        rewards.items.length ? `${rewards.items.length} عنصر` : '',
        rewards.equips.length ? `${rewards.equips.length} قطعة عتاد` : '',
        resourceNames.some(resource => Number(resources[resource]) > 0) ? 'موارد لكل مدينة' : ''
    ].filter(Boolean).join('، ');
    if (!confirm(`سيتم إرسال المكافأة التالية إلى جميع اللاعبين: ${summary}. هل تريد المتابعة؟`)) {
        return;
    }

    const fd = new FormData();
    fd.append('action', 'bulk_event_rewards');
    fd.append('rewards', JSON.stringify(rewards));

    submitButton.disabled = true;
    try {
        const res = await fetch('api.php', { method: 'POST', body: fd });
        const json = await res.json();
        if (res.ok && json.state === 'ok') {
            notify(json.message);
        } else {
            notify(json.message || 'تعذر إرسال الموارد', 'error');
        }
    } catch (e) {
        notify('تعذر الاتصال بالخادم لإرسال الموارد', 'error');
    } finally {
        submitButton.disabled = false;
    }
}

async function executeSanction() {
    const id = document.getElementById('sanction-player').value;
    if (!id) { notify('يرجى اختيار اللاعب', 'error'); return; }

    const type = document.getElementById('sanction-type').value;
    const duration = document.getElementById('sanction-duration').value;
    const reason = document.getElementById('sanction-reason').value;

    const fd = new FormData();
    fd.append('action', type === 'account_ban' ? 'ban_player' : 'mute_player');
    fd.append('id_player', id);
    fd.append('duration', duration);
    fd.append('reason', reason);

    const res = await fetch('api.php', { method: 'POST', body: fd });
    const json = await res.json();
    if (json.state === 'ok') {
        notify(json.message);
        fetchActiveSanctions();
    } else {
        notify(json.message, 'error');
    }
}

async function fetchActiveSanctions() {
    const tbody = document.getElementById('active-sanctions-tbody');
    tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 20px;">جارِ جلب قائمة العقوبات...</td></tr>';

    try {
        const [bRes, mRes] = await Promise.all([
            fetch('api.php?action=get_players&status=banned'),
            fetch('api.php?action=get_players&status=muted')
        ]);
        const bJson = await bRes.json();
        const mJson = await mRes.json();

        const list = [
            ...bJson.data.players.map(p => ({ ...p, penalty: 'حظر كامل' })),
            ...mJson.data.players.map(p => ({ ...p, penalty: 'كتم شات' }))
        ];

        if (list.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 20px;">لا توجد أي عقوبات نشطة حالياً بالسيرفر</td></tr>';
            return;
        }

        tbody.innerHTML = list.map(p => `
            <tr>
                <td><strong>#${p.id_player}</strong></td>
                <td><span style="font-weight: 700; color: #fff;">${p.name}</span></td>
                <td><span class="pill pill-rose">${p.penalty}</span></td>
                <td><span class="pill pill-amber">سارية</span></td>
                <td style="text-align: left; padding-left: 20px;">
                    ${p.penalty === 'حظر كامل'
                        ? `<button class="btn btn-success btn-sm" onclick="unbanPlayerQuick(${p.id_player})"><i class="fa-solid fa-unlock"></i> رفع الحظر</button>`
                        : `<button class="btn btn-success btn-sm" onclick="unmutePlayerQuick(${p.id_player})"><i class="fa-solid fa-volume-high"></i> رفع الكتم</button>`
                    }
                </td>
            </tr>
        `).join('');
    } catch(e) {
        tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: var(--ruby-bright);">خطأ في جلب بيانات العقوبات</td></tr>';
    }
}

async function unbanPlayerQuick(idPlayer) {
    const fd = new FormData();
    fd.append('action', 'unban_player');
    fd.append('id_player', idPlayer);
    const res = await fetch('api.php', { method: 'POST', body: fd });
    const json = await res.json();
    if (json.state === 'ok') {
        notify(json.message);
        fetchActiveSanctions();
        fetchPlayers(1);
    }
}

async function unmutePlayerQuick(idPlayer) {
    const fd = new FormData();
    fd.append('action', 'unmute_player');
    fd.append('id_player', idPlayer);
    const res = await fetch('api.php', { method: 'POST', body: fd });
    const json = await res.json();
    if (json.state === 'ok') {
        notify(json.message);
        fetchActiveSanctions();
        fetchPlayers(1);
    }
}

function quickSanctionPrompt(idPlayer, name) {
    switchNav('tab-sanctions');
    document.getElementById('sanction-player').value = idPlayer;
}

function quickOpenPlayer() {
    const q = document.getElementById('quick-player-query').value.trim();
    if (!q) return;
    const p = cachedPlayers.find(x => x.name.toLowerCase() === q.toLowerCase() || x.id_player == q);
    if (p) {
        openPlayerHub(p.id_player);
    } else {
        switchNav('tab-players');
        document.getElementById('player-filter-query').value = q;
        fetchPlayers(1);
    }
}

// 6. Staff & Permissions
async function fetchStaffList() {
    const tbody = document.getElementById('staff-tbody');
    if (!tbody) return;
    tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: var(--text-muted); padding: 20px;">جارِ جلب المشرفين...</td></tr>';

    try {
        const res = await fetch('api.php?action=get_admins');
        const json = await res.json();
        if (json.state === 'ok') {
            tbody.innerHTML = json.data.map(a => `
                <tr>
                    <td><strong>#${a.id_admin}</strong></td>
                    <td><strong style="color: var(--gold-bright);">${a.username}</strong></td>
                    <td>${a.name}</td>
                    <td><span class="pill pill-amber">${a.role}</span></td>
                    <td><span class="pill ${a.status == 1 ? 'pill-emerald' : 'pill-rose'}">${a.status == 1 ? 'نشط' : 'معطل'}</span></td>
                    <td>${a.last_login || '-'}</td>
                    <td style="text-align: left; padding-left: 20px;">
                        <button class="btn btn-secondary btn-sm" onclick='editStaffModal(${JSON.stringify(a)})'><i class="fa-solid fa-pen"></i></button>
                        <button class="btn btn-danger btn-sm" onclick="deleteStaff(${a.id_admin})"><i class="fa-solid fa-trash"></i></button>
                    </td>
                </tr>
            `).join('');
        }
    } catch(e) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: var(--ruby-bright);">خطأ أثناء تحميل المشرفين</td></tr>';
    }
}

function openStaffModal() {
    document.getElementById('staff-modal-title').innerHTML = '<i class="fa-solid fa-user-plus"></i> إضافة مشرف جديد';
    document.getElementById('staff-id').value = '';
    document.getElementById('staff-username').value = '';
    document.getElementById('staff-password').value = '';
    document.getElementById('staff-name').value = '';
    document.getElementById('staff-role').value = 'moderator';
    openModal('staff-modal');
}

function editStaffModal(admin) {
    document.getElementById('staff-modal-title').innerHTML = '<i class="fa-solid fa-user-pen"></i> تعديل حساب مشرف';
    document.getElementById('staff-id').value = admin.id_admin;
    document.getElementById('staff-username').value = admin.username;
    document.getElementById('staff-password').value = '';
    document.getElementById('staff-name').value = admin.name;
    document.getElementById('staff-role').value = admin.role;
    openModal('staff-modal');
}

async function saveStaffUser() {
    const fd = new FormData();
    fd.append('action', 'save_admin');
    fd.append('id_admin', document.getElementById('staff-id').value);
    fd.append('username', document.getElementById('staff-username').value);
    fd.append('password', document.getElementById('staff-password').value);
    fd.append('name', document.getElementById('staff-name').value);
    fd.append('role', document.getElementById('staff-role').value);

    const res = await fetch('api.php', { method: 'POST', body: fd });
    const json = await res.json();
    if (json.state === 'ok') {
        notify(json.message);
        closeModal('staff-modal');
        fetchStaffList();
    } else {
        notify(json.message, 'error');
    }
}

async function deleteStaff(idAdmin) {
    if (!confirm('هل أنت متأكد من حذف هذا الحساب الإداري؟')) return;
    const fd = new FormData();
    fd.append('action', 'delete_admin');
    fd.append('id_admin', idAdmin);
    const res = await fetch('api.php', { method: 'POST', body: fd });
    const json = await res.json();
    if (json.state === 'ok') {
        notify(json.message);
        fetchStaffList();
    } else {
        notify(json.message, 'error');
    }
}

// 7. Audit Logs
async function fetchAuditLogs(page = 1) {
    const tbody = document.getElementById('logs-tbody');
    tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: var(--text-muted); padding: 24px;">جارِ تحميل السجل...</td></tr>';

    try {
        const res = await fetch(`api.php?action=get_logs&page=${page}`);
        const json = await res.json();
        if (json.state === 'ok') {
            const logs = json.data.logs;
            if (logs.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: var(--text-muted); padding: 30px;">لا توجد أي سجلات مسجلة</td></tr>';
                document.getElementById('logs-pagination-btns').innerHTML = '';
                return;
            }

            tbody.innerHTML = logs.map(l => `
                <tr>
                    <td><strong>#${l.id_log}</strong></td>
                    <td>${l.created_at}</td>
                    <td><span class="pill pill-amber">${l.admin_name}</span></td>
                    <td><span class="pill pill-emerald">${l.action}</span></td>
                    <td><strong style="color: #fff;">${l.target_name || l.target_id || '-'}</strong></td>
                    <td>${l.details || '-'}</td>
                    <td><code style="color: var(--gold-main);">${l.ip_address}</code></td>
                </tr>
            `).join('');

            let pagHtml = '';
            for (let i = 1; i <= json.data.total_pages; i++) {
                if (i <= 6 || i === json.data.total_pages) {
                    pagHtml += `<button class="btn ${i === json.data.page ? 'btn-primary' : 'btn-secondary'} btn-sm" onclick="fetchAuditLogs(${i})">${i}</button>`;
                }
            }
            document.getElementById('logs-pagination-btns').innerHTML = pagHtml;
        }
    } catch(e) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: var(--ruby-bright);">خطأ أثناء تحميل السجلات</td></tr>';
    }
}

// بدء التشغيل التلقائي
document.addEventListener('DOMContentLoaded', () => {
    fetchStats();
    fetchCatalogAndPlayers();
});
</script>

</body>
</html>