<?php

class TaskSpeedUpJsonResponse extends RuntimeException {
    public $payload;

    public function __construct($payload) {
        $this->payload = $payload;
    }
}

function sendJson($data): void {
    throw new TaskSpeedUpJsonResponse($data);
}

require_once __DIR__ . '/../caesar-api/PlayerAuth.php';
require_once __DIR__ . '/../caesar-api/controllers/CityController.php';
require_once __DIR__ . '/../caesar-api/controllers/ArmyController.php';
require_once __DIR__ . '/../caesar-api/controllers/TimedTaskController.php';

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->sqliteCreateFunction('GREATEST', static function (...$values) {
    return max($values);
}, -1);
$db->exec('CREATE TABLE player (id_player INTEGER PRIMARY KEY, p_token TEXT)');
$db->exec('CREATE TABLE city (id_city INTEGER PRIMARY KEY, id_player INTEGER NOT NULL)');
$db->exec('CREATE TABLE city_building (
    id_city INTEGER NOT NULL,
    place TEXT NOT NULL,
    type INTEGER NOT NULL,
    PRIMARY KEY (id_city, place)
)');
$db->exec('CREATE TABLE player_item (
    id_player INTEGER NOT NULL,
    id_item TEXT NOT NULL,
    amount INTEGER NOT NULL,
    PRIMARY KEY (id_player, id_item)
)');
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
$db->exec('CREATE TABLE army_training (
    id_city INTEGER NOT NULL,
    id_player INTEGER NOT NULL,
    army_type TEXT NOT NULL,
    amount INTEGER NOT NULL,
    start_time INTEGER NOT NULL,
    end_time INTEGER NOT NULL,
    done INTEGER NOT NULL
)');
$db->exec("INSERT INTO player VALUES (7, 'player-token')");
$db->exec('INSERT INTO city VALUES (3, 7), (4, 7)');
$db->exec("INSERT INTO city_building VALUES
    (3, 'barracks_1', 3),
    (3, 'barracks_2', 3),
    (3, 'stable_1', 4),
    (3, 'workshop_1', 5),
    (3, 'wall', 13),
    (4, 'wall', 13),
    (4, 'barracks_1', 3)");

$now = time();
$taskInsert = $db->prepare(
    'INSERT INTO timed_task VALUES (?, 3, 7, ?, ?, 1, ?, ?, NULL)'
);
$taskInsert->execute([31, 'study', 'infantry', $now, $now + 1000]);
$db->exec("INSERT INTO player_item VALUES (7, 'archim_d', 2)");

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['token' => 'player-token', 'idTask' => 31, 'itemToUse' => 'archim_d'];
$_REQUEST = $_POST;

try {
    ArmyController::handle('/api/APlayerEdu/speedUpStudyTask', $db);
    throw new RuntimeException('Study speed-up should return a JSON response');
} catch (TaskSpeedUpJsonResponse $response) {
    if (($response->payload['state'] ?? null) !== 'ok') {
        throw new RuntimeException('A valid owned study task should not require an idCity parameter');
    }
    if (count($response->payload['list'] ?? []) !== 1
        || (int)$response->payload['list'][0]['id'] !== 31) {
        throw new RuntimeException('Study speed-up should return the refreshed active study queue');
    }
}

$studyTask = $db->query('SELECT time_arrive FROM timed_task WHERE id_task = 31')->fetch(PDO::FETCH_ASSOC);
$studyItemAmount = (int)$db->query(
    "SELECT amount FROM player_item WHERE id_player = 7 AND id_item = 'archim_d'"
)->fetchColumn();
$studyRemaining = (int)$studyTask['time_arrive'] - time();
if ($studyRemaining < 680 || $studyRemaining > 700 || $studyItemAmount !== 1) {
    throw new RuntimeException('The study speed-up should reduce the remaining time by 30% and consume one item');
}

$taskInsert = $db->prepare(
    'INSERT INTO timed_task VALUES (?, 3, 7, ?, ?, 1, ?, ?, ?)'
);
$taskInsert->execute([32, 'army', 'army_a', $now, $now + 1000, '{"place":"barracks_1"}']);
$taskInsert->execute([33, 'army', 'army_d', $now, $now + 2000, '{"place":"barracks_2"}']);
$taskInsert->execute([34, 'army', 'army_b', $now, $now + 1800, '{"place":"stable_1"}']);
$taskInsert->execute([35, 'army', 'army_e', $now, $now + 2500, '{"place":"workshop_1"}']);
$taskInsert->execute([36, 'army', 'army_a', $now + 500, $now + 3000, '{"place":"barracks_1"}']);
$otherCityTaskInsert = $db->prepare(
    'INSERT INTO timed_task VALUES (?, 4, 7, ?, ?, 1, ?, ?, ?)'
);
$otherCityTaskInsert->execute([37, 'army', 'army_a', $now, $now + 4000, '{"place":"barracks_1"}']);
$db->exec("INSERT INTO player_item VALUES (7, 'train_acce_30', 1), (7, 'train_acce_50', 1)");
$_POST = ['token' => 'player-token', 'idBatch' => 32, 'itemToUse' => 'train_acce_30'];
$_REQUEST = $_POST;

try {
    ArmyController::handle('/api/AArmyBatch/speedUpBatches', $db);
    throw new RuntimeException('Army speed-up should return a JSON response');
} catch (TaskSpeedUpJsonResponse $response) {
    if (($response->payload['state'] ?? null) !== 'ok') {
        throw new RuntimeException('Army speed-up should accept an owned barracks task');
    }
    if ((int)($response->payload['affectedBatches'] ?? 0) !== 3) {
        throw new RuntimeException('A barracks speed-up should affect all queued batches in every barracks in the city');
    }
}

$barracksTimes = $db->query(
    'SELECT id_task, time_arrive, details FROM timed_task WHERE id_task IN (32, 33, 36) ORDER BY id_task'
)->fetchAll(PDO::FETCH_ASSOC);
$expectedRemaining = [32 => 700, 33 => 1400, 36 => 2100];
foreach ($barracksTimes as $batch) {
    $remaining = (int)$batch['time_arrive'] - time();
    $batchDetails = json_decode($batch['details'], true);
    if (abs($remaining - $expectedRemaining[(int)$batch['id_task']]) > 2 || empty($batchDetails['acce'])) {
        throw new RuntimeException('A barracks book should reduce and mark each matching army batch once');
    }
}
$stableEnd = (int)$db->query('SELECT time_arrive FROM timed_task WHERE id_task = 34')->fetchColumn();
$workshopEnd = (int)$db->query('SELECT time_arrive FROM timed_task WHERE id_task = 35')->fetchColumn();
if ($stableEnd !== $now + 1800 || $workshopEnd !== $now + 2500) {
    throw new RuntimeException('Barracks speed-up should not affect stable or workshop queues');
}
$otherCityBarracksEnd = (int)$db->query('SELECT time_arrive FROM timed_task WHERE id_task = 37')->fetchColumn();
if ($otherCityBarracksEnd !== $now + 4000) {
    throw new RuntimeException('Barracks speed-up should not affect matching buildings in another city');
}
$trainingBookAmount = (int)$db->query(
    "SELECT amount FROM player_item WHERE id_player = 7 AND id_item = 'train_acce_30'"
)->fetchColumn();
if ($trainingBookAmount !== 0) {
    throw new RuntimeException('One training book should be consumed for the whole matching queue group');
}

$_POST = ['token' => 'player-token', 'idBatch' => 33, 'itemToUse' => 'train_acce_50'];
$_REQUEST = $_POST;
try {
    ArmyController::handle('/api/AArmyBatch/speedUpBatches', $db);
    throw new RuntimeException('A batch should not accept a second speed-up');
} catch (TaskSpeedUpJsonResponse $response) {
    if (($response->payload['state'] ?? null) !== 'error_already_sped_up') {
        throw new RuntimeException('A second normal or advanced speed-up should be rejected for the same batch');
    }
}
$advancedBookAmount = (int)$db->query(
    "SELECT amount FROM player_item WHERE id_player = 7 AND id_item = 'train_acce_50'"
)->fetchColumn();
if ($advancedBookAmount !== 1) {
    throw new RuntimeException('A rejected second speed-up must not consume the advanced book');
}

$taskInsert->execute([40, 'wall', 'wall_a', $now, $now + 3600, '{"place":"wall"}']);
$taskInsert->execute([41, 'wall', 'wall_b', $now, $now + 7200, '{"place":"wall"}']);
$otherCityTaskInsert = $db->prepare(
    'INSERT INTO timed_task VALUES (?, 4, 7, ?, ?, 1, ?, ?, ?)'
);
$otherCityTaskInsert->execute([42, 'wall', 'wall_a', $now, $now + 5000, '{"place":"wall"}']);
$db->exec("INSERT INTO player_item VALUES (7, 'wall_acce', 1)");
$_POST = ['token' => 'player-token', 'idBatch' => 40, 'itemToUse' => 'wall_acce'];
$_REQUEST = $_POST;

try {
    ArmyController::handle('/api/AArmyBatch/speedUpBatches', $db);
    throw new RuntimeException('Pulley speed-up should return a JSON response');
} catch (TaskSpeedUpJsonResponse $response) {
    if (($response->payload['state'] ?? null) !== 'ok'
        || (int)($response->payload['affectedBatches'] ?? 0) !== 2) {
        throw new RuntimeException('The pulley should affect all wall queues in the current city only');
    }
}

$wallTimes = $db->query(
    'SELECT id_task, time_arrive, details FROM timed_task WHERE id_task IN (40, 41) ORDER BY id_task'
)->fetchAll(PDO::FETCH_ASSOC);
$expectedWallRemaining = [40 => 1800, 41 => 3600];
foreach ($wallTimes as $batch) {
    $remaining = (int)$batch['time_arrive'] - time();
    $batchDetails = json_decode($batch['details'], true);
    if (abs($remaining - $expectedWallRemaining[(int)$batch['id_task']]) > 2 || empty($batchDetails['acce'])) {
        throw new RuntimeException('The pulley should reduce every wall batch by 50% and mark it accelerated');
    }
}
$otherCityWallEnd = (int)$db->query('SELECT time_arrive FROM timed_task WHERE id_task = 42')->fetchColumn();
$wallItemAmount = (int)$db->query(
    "SELECT amount FROM player_item WHERE id_player = 7 AND id_item = 'wall_acce'"
)->fetchColumn();
if ($otherCityWallEnd !== $now + 5000 || $wallItemAmount !== 0) {
    throw new RuntimeException('The pulley must be consumed once and leave other cities unchanged');
}

$_REQUEST = ['token' => 'player-token', 'idCity' => 3];
try {
    TimedTaskController::getCityArmyTasks($db);
    throw new RuntimeException('Army task list should return JSON');
} catch (TaskSpeedUpJsonResponse $response) {
    $wallBatch = null;
    foreach ($response->payload as $batch) {
        if ((int)$batch['id'] === 40) {
            $wallBatch = $batch;
            break;
        }
    }
    if (!$wallBatch || (int)$wallBatch['acce'] !== 1) {
        throw new RuntimeException('Refreshed wall queue should include the persisted one-use speed-up state');
    }
}

echo "Army, wall, and study speed-up tests passed\n";
