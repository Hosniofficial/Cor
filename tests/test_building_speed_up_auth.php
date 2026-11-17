<?php

class BuildingSpeedUpJsonResponse extends RuntimeException {
    public $payload;

    public function __construct($payload) {
        $this->payload = $payload;
    }
}

function sendJson($data): void {
    throw new BuildingSpeedUpJsonResponse($data);
}

require_once __DIR__ . '/../caesar-api/PlayerAuth.php';
require_once __DIR__ . '/../caesar-api/controllers/CityController.php';

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->sqliteCreateFunction('GREATEST', static function (...$values) {
    return max($values);
}, -1);
$db->exec('CREATE TABLE player (id_player INTEGER PRIMARY KEY, p_token TEXT)');
$db->exec('CREATE TABLE city (id_city INTEGER PRIMARY KEY, id_player INTEGER NOT NULL)');
$db->exec('CREATE TABLE timed_task (
    id_task INTEGER PRIMARY KEY,
    id_city INTEGER NOT NULL,
    id_player INTEGER NOT NULL,
    task_type TEXT NOT NULL,
    item_id TEXT NOT NULL,
    lvl_to INTEGER NOT NULL,
    start_time INTEGER NOT NULL,
    time_arrive INTEGER NOT NULL,
    details TEXT
)');
$db->exec('CREATE TABLE player_item (
    id_player INTEGER NOT NULL,
    id_item TEXT NOT NULL,
    amount INTEGER NOT NULL,
    PRIMARY KEY (id_player, id_item)
)');
$db->exec('CREATE TABLE city_building (id_city INTEGER, place TEXT, type INTEGER)');
$db->exec("INSERT INTO player VALUES (7, 'player-token'), (8, 'other-token')");
$db->exec('INSERT INTO city VALUES (3, 7), (4, 8)');
$arrival = time() + 3600;
$stmt = $db->prepare(
    "INSERT INTO timed_task VALUES (19, 3, 8, 'building', 'palace', 2, ?, ?, ?)"
);
$stmt->execute([time(), $arrival, '{"type":12}']);
$stmt = $db->prepare('INSERT INTO player_item VALUES (7, ?, 2)');
$stmt->execute(['archit_a']);

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['token' => 'player-token'];
$_REQUEST = [
    'token' => 'player-token',
    'idCity' => 999,
    'idWorking' => 19,
    'itemToUse' => 'archit_a'
];

try {
    CityController::handle('/api/ACityBuilding/speedUp', $db);
    throw new RuntimeException('Speed-up should return a JSON response');
} catch (BuildingSpeedUpJsonResponse $response) {
    if (($response->payload['state'] ?? null) !== 'ok') {
        throw new RuntimeException('A player-owned task should be speeded up using its actual city');
    }
}

$task = $db->query('SELECT id_city, time_arrive FROM timed_task WHERE id_task = 19')->fetch(PDO::FETCH_ASSOC);
$itemAmount = (int)$db->query("SELECT amount FROM player_item WHERE id_player = 7 AND id_item = 'archit_a'")->fetchColumn();
if ((int)$task['id_city'] !== 3
    || (int)$task['time_arrive'] > $arrival - 900
    || $itemAmount !== 1) {
    throw new RuntimeException('Speed-up did not update the owned task and deduct the authenticated player item');
}

$architecturalBookDuration = 3600;
$architecturalBookArrival = time() + $architecturalBookDuration;
$stmt = $db->prepare(
    "INSERT INTO timed_task VALUES (21, 3, 7, 'building', 'palace', 2, ?, ?, ?)"
);
$stmt->execute([time(), $architecturalBookArrival, '{"type":12}']);
$db->prepare('INSERT INTO player_item VALUES (7, ?, 1)')->execute(['archit_d']);
$_REQUEST = [
    'token' => 'player-token',
    'idCity' => 3,
    'idWorking' => 21,
    'itemToUse' => 'archit_d'
];

try {
    CityController::handle('/api/ACityBuilding/speedUp', $db);
    throw new RuntimeException('Architectural books should return a JSON response');
} catch (BuildingSpeedUpJsonResponse $response) {
    if (($response->payload['state'] ?? null) !== 'ok') {
        throw new RuntimeException('Architectural books should speed up an owned building task');
    }
}

$task = $db->query('SELECT time_arrive FROM timed_task WHERE id_task = 21')->fetch(PDO::FETCH_ASSOC);
$itemAmount = (int)$db->query("SELECT amount FROM player_item WHERE id_player = 7 AND id_item = 'archit_d'")->fetchColumn();
$remainingDuration = (int)$task['time_arrive'] - time();
if ($remainingDuration < 350 || $remainingDuration > 365 || $itemAmount !== 0) {
    throw new RuntimeException('An architectural book should reduce the remaining building time by 90%');
}

$_REQUEST = [
    'token' => 'player-token',
    'idCity' => 3,
    'idWorking' => 20,
    'itemToUse' => 'archit_a'
];
$stmt = $db->prepare(
    "INSERT INTO timed_task VALUES (20, 4, 8, 'building', 'palace', 2, ?, ?, ?)"
);
$stmt->execute([time(), time() + 3600, '{"type":12}']);

try {
    CityController::handle('/api/ACityBuilding/speedUp', $db);
    throw new RuntimeException('A task in another player city should not be accessible');
} catch (BuildingSpeedUpJsonResponse $response) {
    if (($response->payload['state'] ?? null) !== 'error_auth') {
        throw new RuntimeException('An unowned building task should be rejected');
    }
}

echo "Building speed-up ownership tests passed\n";
