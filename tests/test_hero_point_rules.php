<?php

class HeroPointRulesTestResponse extends RuntimeException {
    public $payload;

    public function __construct($payload) {
        $this->payload = $payload;
    }
}

function sendJson($data): void {
    throw new HeroPointRulesTestResponse($data);
}

require_once __DIR__ . '/../caesar-api/controllers/HeroController.php';

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE city_hero (
    id_hero INTEGER PRIMARY KEY,
    id_city INTEGER NOT NULL,
    id_player INTEGER NOT NULL,
    point_a INTEGER NOT NULL,
    point_b INTEGER NOT NULL,
    point_c INTEGER NOT NULL,
    point_a_plus INTEGER NOT NULL DEFAULT 0,
    point_b_plus INTEGER NOT NULL DEFAULT 0,
    point_c_plus INTEGER NOT NULL DEFAULT 0,
    points INTEGER NOT NULL
)');
$db->exec('CREATE TABLE hero_medal (
    id_hero INTEGER PRIMARY KEY,
    medal_ceasro INTEGER NOT NULL DEFAULT 0,
    medal_den INTEGER NOT NULL DEFAULT 0,
    medal_leo INTEGER NOT NULL DEFAULT 0
)');
$db->exec('CREATE TABLE city (id_city INTEGER PRIMARY KEY, id_player INTEGER NOT NULL, food INTEGER NOT NULL DEFAULT 0)');
$db->exec("INSERT INTO city_hero VALUES
    (101, 3, 7, 20, 20, 20, 0, 0, 0, 20),
    (102, 3, 7, 22, 20, 20, 0, 0, 0, 20)");
$futureExpiry = time() + 3600;
$db->exec("INSERT INTO hero_medal VALUES (101, 0, 0, 0), (102, {$futureExpiry}, 0, 0)");
$db->exec('INSERT INTO city VALUES (3, 7, 1000)');

function saveHeroPointRules(PDO $db, int $heroId, int $pointA, int $pointB, int $pointC): array {
    $_REQUEST = [
        'idHero' => $heroId,
        'pointA' => $pointA,
        'pointB' => $pointB,
        'pointC' => $pointC
    ];

    try {
        HeroController::setHeroPoints($db);
    } catch (HeroPointRulesTestResponse $response) {
        return $response->payload;
    }

    throw new RuntimeException('Hero point update did not return JSON');
}

$valid = saveHeroPointRules($db, 101, 0, 1, 0);
if (($valid['state'] ?? null) !== 'ok'
    || (int)$valid['Hero']['point_b'] !== 21
    || (int)$valid['Hero']['points'] !== 19) {
    throw new RuntimeException('A balanced point allocation should be accepted and deducted');
}

$invalid = saveHeroPointRules($db, 102, 13, 0, 0);
$unchanged = $db->query('SELECT point_a, points FROM city_hero WHERE id_hero = 102')->fetch(PDO::FETCH_ASSOC);
if (($invalid['state'] ?? null) !== 'error_hero_point_limit'
    || (int)$unchanged['point_a'] !== 22
    || (int)$unchanged['points'] !== 20) {
    throw new RuntimeException('The balance rule must include an active politics medal and reject invalid allocation');
}

echo "Hero point balance tests passed\n";
