<?php
// web.elkaisar.com/logout.php - Player Logout
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../app.elkaisar.com/db.php';

if (!empty($_SESSION['player_id'])) {
    $db = getDB();
    $uStmt = $db->prepare("UPDATE `player` SET `online` = 0 WHERE `id_player` = ?");
    $uStmt->execute([(int)$_SESSION['player_id']]);
}

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'] ?? 'Lax',
    ]);
}
session_destroy();

header('Location: login.php');
exit;
