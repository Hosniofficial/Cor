<?php

class HeroArmyTransferTestResponse extends RuntimeException {
    public $payload;

    public function __construct($payload) {
        $this->payload = $payload;
    }
}

function sendJson($data): void {
    throw new HeroArmyTransferTestResponse($data);
}

require_once __DIR__ . '/../caesar-api/PlayerAuth.php';
require_once __DIR__ . '/../caesar-api/controllers/HeroController.php';

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE city_hero (
    id_hero INTEGER PRIMARY KEY, id_city INTEGER NOT NULL, id_player INTEGER NOT NULL,
    in_city INTEGER NOT NULL, console INTEGER NOT NULL, point_a INTEGER NOT NULL,
    point_a_plus INTEGER NOT NULL DEFAULT 0
)');
$db->exec('CREATE TABLE hero_army (
    id_hero INTEGER PRIMARY KEY, id_player INTEGER NOT NULL,
    f_1_type INTEGER NOT NULL DEFAULT 0, f_1_num INTEGER NOT NULL DEFAULT 0,
    f_2_type INTEGER NOT NULL DEFAULT 0, f_2_num INTEGER NOT NULL DEFAULT 0,
    f_3_type INTEGER NOT NULL DEFAULT 0, f_3_num INTEGER NOT NULL DEFAULT 0,
    b_1_type INTEGER NOT NULL DEFAULT 0, b_1_num INTEGER NOT NULL DEFAULT 0,
    b_2_type INTEGER NOT NULL DEFAULT 0, b_2_num INTEGER NOT NULL DEFAULT 0,
    b_3_type INTEGER NOT NULL DEFAULT 0, b_3_num INTEGER NOT NULL DEFAULT 0
)');
$db->exec('CREATE TABLE player_edu (id_player INTEGER PRIMARY KEY, leader INTEGER NOT NULL DEFAULT 0)');
$db->exec('CREATE TABLE hero_medal (
    id_hero INTEGER PRIMARY KEY, medal_ceasro INTEGER NOT NULL DEFAULT 0,
    ceaser_eagle INTEGER NOT NULL DEFAULT 0
)');
$db->exec("INSERT INTO city_hero VALUES
    (1, 3, 7, 1, 0, 20, 0),
    (2, 3, 7, 1, 1, 20, 0)");
$db->exec('INSERT INTO hero_army (id_hero, id_player, f_1_type, f_1_num) VALUES (1, 7, 3, 50)');
$db->exec('INSERT INTO hero_army (id_hero, id_player) VALUES (2, 7)');
$db->exec('INSERT INTO player_edu VALUES (7, 0)');
$db->exec('INSERT INTO hero_medal (id_hero) VALUES (1), (2)');

$db->exec('UPDATE player_edu SET leader = 1 WHERE id_player = 7');
$futureMedalExpiry = time() + 3600;
$db->prepare('UPDATE hero_medal SET medal_ceasro = ?, ceaser_eagle = ? WHERE id_hero = 1')
    ->execute([$futureMedalExpiry, $futureMedalExpiry]);
$heroCapacity = HeroController::calculateHeroMaxCap($db, [
    'id_hero' => 1,
    'id_player' => 7,
    'point_a' => 22,
    'point_a_plus' => 0
]);
if ($heroCapacity !== 56320) {
    throw new RuntimeException('Leadership capacity should apply the rounded politics buff, study, and full-cap eagle bonus');
}
$db->exec('UPDATE player_edu SET leader = 0 WHERE id_player = 7');
$db->exec('UPDATE hero_medal SET medal_ceasro = 0, ceaser_eagle = 0 WHERE id_hero = 1');

function moveHeroArmy(PDO $db, int $fromHero, int $toHero, string $fromPlace, string $toPlace, int $amount): array {
    $_REQUEST = [
        'idCity' => 3,
        'idHeroFrom' => $fromHero,
        'idHeroTo' => $toHero,
        'ArmyPlaceFrom' => $fromPlace,
        'ArmyPlaceTo' => $toPlace,
        'amount' => $amount
    ];

    try {
        HeroController::transArmyFromHeroToHero($db);
    } catch (HeroArmyTransferTestResponse $response) {
        return $response->payload;
    }

    throw new RuntimeException('Army transfer did not return JSON');
}

function moveArmyFromCity(PDO $db, string $armyType, string $heroPlace): array {
    $_REQUEST = [
        'idCity' => 3,
        'idHero' => 1,
        'amount' => 1,
        'ArmyPlace' => $heroPlace,
        'ArmyType' => $armyType
    ];

    try {
        HeroController::transArmyFromCityToHero($db);
    } catch (HeroArmyTransferTestResponse $response) {
        return $response->payload;
    }

    throw new RuntimeException('City-to-hero army transfer did not return JSON');
}

$result = moveHeroArmy($db, 1, 2, 'f_1', 'b_2', 20);
if (($result['state'] ?? null) !== 'ok'
    || (int)$result['HeroArmyFrom']['f_1_num'] !== 30
    || (int)$result['HeroArmyTo']['b_2_num'] !== 20) {
    throw new RuntimeException('A consul should receive troops in the requested back-row slot');
}

$result = moveHeroArmy($db, 1, 1, 'f_1', 'b_1', 30);
if (($result['state'] ?? null) !== 'ok'
    || (int)$result['HeroArmyFrom']['f_1_num'] !== 0
    || (int)$result['HeroArmyTo']['b_1_num'] !== 30) {
    throw new RuntimeException('A full-capacity hero should be able to rearrange troops between rows');
}

$db->exec('UPDATE hero_army SET b_2_num = 0, b_1_type = 3, b_1_num = 40000 WHERE id_hero = 2');
$db->exec('UPDATE hero_army SET f_1_type = 3, f_1_num = 10 WHERE id_hero = 1');
$result = moveHeroArmy($db, 1, 2, 'f_1', 'b_3', 1);
if (($result['state'] ?? null) !== 'error_4') {
    throw new RuntimeException('A transfer exceeding the destination hero capacity should be rejected');
}

// Verify slot restrictions: Archers cannot move to front row, Infantry cannot move to back row
$db->exec('UPDATE hero_army SET b_1_type = 4, b_1_num = 100 WHERE id_hero = 1');
$result = moveHeroArmy($db, 1, 1, 'b_1', 'f_2', 10);
if (($result['state'] ?? null) !== 'error_slot_restricted') {
    throw new RuntimeException('Archers must not be allowed in front slots f_1..f_3');
}

$db->exec('UPDATE hero_army SET f_1_type = 1, f_1_num = 100 WHERE id_hero = 1');
$result = moveHeroArmy($db, 1, 1, 'f_1', 'b_2', 10);
if (($result['state'] ?? null) !== 'error_slot_restricted') {
    throw new RuntimeException('Infantry must not be allowed in back slots b_1..b_3');
}

$result = moveArmyFromCity($db, 'army_d', 'f_1');
if (($result['state'] ?? null) !== 'error_slot_restricted') {
    throw new RuntimeException('City archers must not be allowed in front slots f_1..f_3');
}

$result = moveArmyFromCity($db, 'army_a', 'b_1');
if (($result['state'] ?? null) !== 'error_slot_restricted') {
    throw new RuntimeException('City infantry must not be allowed in back slots b_1..b_3');
}

echo "Hero army transfer tests passed\n";
