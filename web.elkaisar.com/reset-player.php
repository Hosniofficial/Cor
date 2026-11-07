<?php
// reset-player.php - Reset the game to fresh state from scratch
require_once __DIR__ . '/../app.elkaisar.com/db.php';

$db = getDB();

// Delete current player and all cascades
$db->exec("DELETE FROM `player`");

// Create fresh player
$playerName = isset($_GET['name']) && !empty(trim($_GET['name'])) ? trim($_GET['name']) : 'البطل سيزر';
$fresh = createFreshPlayer($playerName);

// Redirect to game
header('Location: /local-server.php');
exit;
