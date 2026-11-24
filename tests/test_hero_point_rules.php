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

// Test resetHeroPoints with retreat_point validation
$db->exec('ALTER TABLE city_hero ADD COLUMN lvl INTEGER NOT NULL DEFAULT 1');
$db->exec('ALTER TABLE city_hero ADD COLUMN p_b_a INTEGER NOT NULL DEFAULT 20');
$db->exec('ALTER TABLE city_hero ADD COLUMN p_b_b INTEGER NOT NULL DEFAULT 20');
$db->exec('ALTER TABLE city_hero ADD COLUMN p_b_c INTEGER NOT NULL DEFAULT 20');
$db->exec('CREATE TABLE player_item (id_player INTEGER NOT NULL, id_item TEXT NOT NULL, amount INTEGER NOT NULL, PRIMARY KEY (id_player, id_item))');

// Hero 101 now has point_b = 21, p_b_b = 20, points = 19, lvl = 1
// Should require floor(1/10) + 1 = 1 retreat_point
$_REQUEST = ['idHero' => 101];

// 1. Without item -> should fail with error_1
try {
    HeroController::resetHeroPoints($db);
    throw new RuntimeException('resetHeroPoints should fail without retreat_point');
} catch (HeroPointRulesTestResponse $e) {
    if (($e->payload['state'] ?? '') !== 'error_1') {
        throw new RuntimeException('Expected error_1 when player has no retreat_point, got: ' . json_encode($e->payload));
    }
}

// 2. Add 2 retreat_point items and reset
$db->exec("INSERT INTO player_item VALUES (7, 'retreat_point', 2)");
try {
    HeroController::resetHeroPoints($db);
    throw new RuntimeException('resetHeroPoints should return response');
} catch (HeroPointRulesTestResponse $e) {
    $res = $e->payload;
    if (($res['state'] ?? '') !== 'ok') {
        throw new RuntimeException('Expected state ok for resetHeroPoints, got: ' . json_encode($res));
    }
    if ((int)$res['Hero']['point_b'] !== 20 || (int)$res['Hero']['points'] !== 20) {
        throw new RuntimeException('Points must be refunded: ' . json_encode($res['Hero']));
    }
}

// Check item deduction
$remItem = (int)$db->query("SELECT amount FROM player_item WHERE id_player = 7 AND id_item = 'retreat_point'")->fetchColumn();
if ($remItem !== 1) {
    throw new RuntimeException("Expected 1 retreat_point remaining, got $remItem");
}

echo "Hero point reset with retreat_point tests passed\n";
