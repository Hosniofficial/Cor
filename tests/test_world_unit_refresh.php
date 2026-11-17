<?php
require_once __DIR__ . '/../caesar-api/db.php';
require_once __DIR__ . '/../caesar-api/PlayerAuth.php';
require_once __DIR__ . '/../caesar-api/controllers/WorldController.php';

$db = getDB();
$stmt = $db->query('SELECT id_player, p_token FROM player LIMIT 1');
$player = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$player) {
    echo "FAIL: No player found in database\n";
    exit(1);
}

// Test WorldController::refreshWorldUnitLvl
$_REQUEST['server'] = 1;
$_REQUEST['idPlayerV'] = $player['id_player'];
$_REQUEST['token'] = $player['p_token'];
$_REQUEST['xCoord'] = 266;
$_REQUEST['yCoord'] = 245;
$_REQUEST['unitType'] = 77;
$_REQUEST['lvl'] = 1;

ob_start();
WorldController::refreshWorldUnitLvl($db);
$output = ob_get_clean();

$response = json_decode($output, true);
if (!is_array($response) || ($response['state'] ?? '') !== 'ok' || ($response['l'] ?? 0) !== 1 || ($response['ut'] ?? 0) !== 77) {
    echo "FAIL: refreshWorldUnitLvl response unexpected: " . $output . "\n";
    exit(1);
}

if (!isset($response['army']) || !is_array($response['army'])) {
    echo "FAIL: refreshWorldUnitLvl army missing: " . $output . "\n";
    exit(1);
}

echo "PASS: refreshWorldUnitLvl verified successfully with level 1 and army data\n";
