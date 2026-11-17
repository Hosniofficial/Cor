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
$db->exec('INSERT INTO hero_army (id_hero, id_player, f_1_type, f_1_num) VALUES (1, 7, 1, 50)');
$db->exec('INSERT INTO hero_army (id_hero, id_player) VALUES (2, 7)');
$db->exec('INSERT INTO player_edu VALUES (7, 0)');
$db->exec('INSERT INTO hero_medal (id_hero) VALUES (1), (2)');

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

$db->exec('UPDATE hero_army SET b_2_num = 0, b_1_type = 1, b_1_num = 40000 WHERE id_hero = 2');
$db->exec('UPDATE hero_army SET f_1_type = 1, f_1_num = 10 WHERE id_hero = 1');
$result = moveHeroArmy($db, 1, 2, 'f_1', 'b_3', 1);
if (($result['state'] ?? null) !== 'error_4') {
    throw new RuntimeException('A transfer exceeding the destination hero capacity should be rejected');
}

echo "Hero army transfer tests passed\n";
