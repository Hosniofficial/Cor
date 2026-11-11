<?php
// web.elkaisar.com/logout.php - Player Logout
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/../app.elkaisar.com/db.php';

if (!empty($_SESSION['player_id'])) {
    $db = getDB();
    $gameToken = bin2hex(random_bytes(32));
    $uStmt = $db->prepare(
        "UPDATE `player` SET `online` = 0, `p_token` = ? WHERE `id_player` = ? AND `p_token` = ?"
    );
    $uStmt->execute([
        $gameToken,
        (int)$_SESSION['player_id'],
        (string)($_SESSION['player_token'] ?? '')
    ]);
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
