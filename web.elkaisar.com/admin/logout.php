<?php
// web.elkaisar.com/admin/logout.php
require_once __DIR__ . '/auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}
requireValidAdminCsrf();

if (isLoggedIn()) {
    logAdminAction('logout', 'system', $_SESSION['admin_user']['id_admin'], $_SESSION['admin_user']['username'], 'تسجيل خروج من لوحة التحكم');
}
unset($_SESSION['admin_user'], $_SESSION['admin_session_expires_at']);
$_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
session_regenerate_id(true);

header('Location: login.php');
exit;
