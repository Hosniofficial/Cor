<?php
const ELKAISAR_SESSION_TTL = 1209600;

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', (string)ELKAISAR_SESSION_TTL);
    session_set_cookie_params([
        'lifetime' => ELKAISAR_SESSION_TTL,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

$now = time();
if (!empty($_SESSION['player_id'])
    && (!isset($_SESSION['player_session_expires_at'])
        || (int)$_SESSION['player_session_expires_at'] <= $now)) {
    unset(
        $_SESSION['player_id'],
        $_SESSION['player_name'],
        $_SESSION['user_group'],
        $_SESSION['selected_server_id'],
        $_SESSION['player_session_expires_at']
    );
}

if (!empty($_SESSION['admin_user'])
    && (!isset($_SESSION['admin_session_expires_at'])
        || (int)$_SESSION['admin_session_expires_at'] <= $now)) {
    unset($_SESSION['admin_user'], $_SESSION['admin_session_expires_at']);
}
