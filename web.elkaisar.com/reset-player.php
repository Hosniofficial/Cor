<?php
// reset-player.php - Reset the game to fresh state from scratch
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if (getenv('ELKAISAR_CONFIRM_PLAYER_RESET') !== '1') {
    fwrite(STDERR, "Set ELKAISAR_CONFIRM_PLAYER_RESET=1 to confirm deleting all player data.\n");
    exit(1);
}

require_once __DIR__ . '/../app.elkaisar.com/db.php';

$db = getDB();

// Delete current player and all cascades
$db->exec("DELETE FROM `player`");

// Create fresh player
$playerName = isset($_GET['name']) && !empty(trim($_GET['name'])) ? trim($_GET['name']) : 'البطل سيزر';
$fresh = createFreshPlayer($playerName);

echo "Reset complete. Created player: {$playerName}\n";
