<?php
require_once __DIR__ . '/session.php';

header('Cache-Control: no-store');
$destination = (int)($_SESSION['player_id'] ?? 0) > 0 ? 'profile.php' : 'login.php';
header('Location: ' . $destination, true, 302);
exit;
