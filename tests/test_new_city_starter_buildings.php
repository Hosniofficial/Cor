<?php
// tests/test_new_city_starter_buildings.php
// Verifies WorldController::buildNewCity initializes wall (type 13) at level 0
// with wall_cap = 0, matching starter cities and allowing upgrade from level 0 to 1.

putenv('CAESAR_ENV=test');
putenv('CAESAR_DB_NAME=caesar_test');

class NewCityTestResponse extends RuntimeException {
    public $data;

    public function __construct(array $data) {
        parent::__construct($data['state'] ?? 'response');
        $this->data = $data;
    }
}

if (!function_exists('sendJson')) {
    function sendJson($data): void {
        throw new NewCityTestResponse($data);
    }
}

require_once __DIR__ . '/../caesar-api/db.php';
require_once __DIR__ . '/../caesar-api/PlayerAuth.php';
require_once __DIR__ . '/../caesar-api/services/ProductionService.php';
require_once __DIR__ . '/../caesar-api/controllers/WorldController.php';
require_once __DIR__ . '/../caesar-api/controllers/CityController.php';

$db = getDB();
$testPlayerId = 99997;
$testOriginCityId = 99997;
$testToken = 'new_city_wall_test_token_99997';
$createdCityId = 0;

function assertNewCity(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }
    echo "[PASS] {$message}\n";
}

function cleanupNewCityFixtures(PDO $db, int $playerId, int $originCityId, int $createdCityId): void {
    $cityIds = array_values(array_filter([$originCityId, $createdCityId], fn($id) => $id > 0));
    foreach ($cityIds as $cid) {
        $db->prepare('DELETE FROM `timed_task` WHERE `id_city` = ?')->execute([$cid]);
        $db->prepare('DELETE FROM `city_storage` WHERE `id_city` = ?')->execute([$cid]);
        $db->prepare('DELETE FROM `city_jop` WHERE `id_city` = ?')->execute([$cid]);
        $db->prepare('DELETE FROM `city_building` WHERE `id_city` = ?')->execute([$cid]);
        $db->prepare('DELETE FROM `city` WHERE `id_city` = ?')->execute([$cid]);
    }
    $db->prepare('DELETE FROM `player_edu` WHERE `id_player` = ?')->execute([$playerId]);
    $db->prepare('DELETE FROM `player_state` WHERE `id_player` = ?')->execute([$playerId]);
    $db->prepare('DELETE FROM `player` WHERE `id_player` = ?')->execute([$playerId]);
}

try {
    cleanupNewCityFixtures($db, $testPlayerId, $testOriginCityId, 0);

    $db->prepare(
        "INSERT INTO `player` (`id_player`, `id_user`, `name`, `prestige`, `porm`, `gold`, `p_token`) " .
        "VALUES (?, ?, 'NewCityWallTester', 100, 5, 1000, ?)"
    )->execute([$testPlayerId, $testPlayerId, $testToken]);
    $db->prepare("INSERT INTO `player_edu` (`id_player`) VALUES (?)")->execute([$testPlayerId]);
    $db->prepare("INSERT INTO `player_state` (`id_player`) VALUES (?)")->execute([$testPlayerId]);

    $originCoords = findAvailableWorldCoordinates($db);
    $db->prepare(
        "INSERT INTO `city` (`id_city`, `id_player`, `name`, `x`, `y`, `lvl`, `food`, `wood`, `stone`, `metal`, `coin`, `pop`, `pop_cap`, `pop_max`, `wall_cap`) " .
        "VALUES (?, ?, 'OriginCity', ?, ?, 1, 50000, 50000, 50000, 50000, 50000, 500, 1000, 1000, 0)"
    )->execute([$testOriginCityId, $testPlayerId, $originCoords['x'], $originCoords['y']]);
    $db->prepare("INSERT INTO `city_jop` (`id_city`, `id_player`) VALUES (?, ?)")->execute([$testOriginCityId, $testPlayerId]);
    $db->prepare("INSERT INTO `city_storage` (`id_city`) VALUES (?)")->execute([$testOriginCityId]);
    $db->prepare("INSERT INTO `city_building` (`id_city`, `place`, `type`, `lvl`) VALUES (?, 'palace', 12, 1)")->execute([$testOriginCityId]);

    $targetCoords = findAvailableWorldCoordinates($db);
    while ($targetCoords['x'] === $originCoords['x'] && $targetCoords['y'] === $originCoords['y']) {
        $targetCoords = findAvailableWorldCoordinates($db);
    }

    $_REQUEST = [
        'idCity' => $testOriginCityId,
        'xCoord' => $targetCoords['x'],
        'yCoord' => $targetCoords['y'],
        'CityName' => 'NewTestCity',
        'token' => $testToken
    ];
    $_GET = ['token' => $testToken];
    $_POST = [];

    $buildResponse = null;
    try {
        WorldController::buildNewCity($db);
    } catch (NewCityTestResponse $response) {
        $buildResponse = $response->data;
    }

    assertNewCity(($buildResponse['state'] ?? '') === 'ok', 'buildNewCity succeeds with state ok');
    $createdCityId = (int)($buildResponse['City']['id_city'] ?? 0);
    assertNewCity($createdCityId > 0, 'buildNewCity returns created city ID');
    assertNewCity((int)($buildResponse['City']['wall_cap'] ?? -1) === 0, 'New city starts with wall_cap = 0');

    $bStmt = $db->prepare('SELECT `place`, `type`, `lvl` FROM `city_building` WHERE `id_city` = ?');
    $bStmt->execute([$createdCityId]);
    $buildingsByPlace = [];
    foreach ($bStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $buildingsByPlace[$row['place']] = [
            'type' => (int)$row['type'],
            'lvl' => (int)$row['lvl']
        ];
    }

    assertNewCity(($buildingsByPlace['wall']['type'] ?? -1) === 13, 'New city wall slot has type 13');
    assertNewCity(($buildingsByPlace['wall']['lvl'] ?? -1) === 0, 'New city wall starts at level 0 instead of level 1');
    assertNewCity(($buildingsByPlace['palace']['lvl'] ?? -1) === 1, 'New city palace starts at level 1');
    foreach (['farm', 'wood', 'stone', 'mine'] as $resPlace) {
        assertNewCity(($buildingsByPlace[$resPlace]['lvl'] ?? -1) === 1, "New city {$resPlace} starts at level 1");
    }

    // Verify upgrading the wall from level 0 to level 1 works smoothly
    $_REQUEST = [
        'idCity' => $createdCityId,
        'buildingPlace' => 'wall',
        'token' => $testToken
    ];
    $upgradeResponse = null;
    try {
        CityController::upgradeBuilding($db);
    } catch (NewCityTestResponse $response) {
        $upgradeResponse = $response->data;
    }
    assertNewCity(($upgradeResponse['state'] ?? '') === 'ok', 'Upgrading new city wall from level 0 to 1 succeeds');
    assertNewCity((int)($upgradeResponse['list'][0]['lvl_to'] ?? 0) === 1, 'Wall upgrade task targets level 1');
} finally {
    cleanupNewCityFixtures($db, $testPlayerId, $testOriginCityId, $createdCityId);
}
