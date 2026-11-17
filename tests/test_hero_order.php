<?php

class HeroOrderTestResponse extends RuntimeException {
    public $payload;

    public function __construct($payload) {
        $this->payload = $payload;
    }
}

function sendJson($data): void {
    throw new HeroOrderTestResponse($data);
}

require_once __DIR__ . '/../caesar-api/PlayerAuth.php';
require_once __DIR__ . '/../caesar-api/controllers/HeroController.php';

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE player (id_player INTEGER PRIMARY KEY, p_token TEXT)');
$db->exec('CREATE TABLE city (id_city INTEGER PRIMARY KEY, id_player INTEGER)');
$db->exec('CREATE TABLE city_hero (
    id_hero INTEGER PRIMARY KEY,
    id_city INTEGER NOT NULL,
    id_player INTEGER NOT NULL,
    ord INTEGER NOT NULL DEFAULT 0
)');
$db->exec("INSERT INTO player VALUES (7, 'player-token')");
$db->exec('INSERT INTO city VALUES (3, 7)');
$db->exec('INSERT INTO city_hero VALUES (101, 3, 7, 0), (102, 3, 7, 1), (103, 3, 7, 2)');

function reorderHero(PDO $db, int $heroId, string $direction): array {
    $_POST = $_REQUEST = [
        'token' => 'player-token',
        'idCity' => 3,
        'idHero' => $heroId,
        'MoveDir' => $direction
    ];
    try {
        HeroController::handle('/api/AHero/reOrderHero', $db);
    } catch (HeroOrderTestResponse $response) {
        return $response->payload;
    }
    throw new RuntimeException('Hero reorder should return JSON');
}

$result = reorderHero($db, 102, 'down');
if (($result['state'] ?? '') !== 'ok'
    || $result['HeroList'] !== [
        ['id_hero' => 101, 'ord' => 0],
        ['id_hero' => 103, 'ord' => 1],
        ['id_hero' => 102, 'ord' => 2]
    ]) {
    throw new RuntimeException('Moving a hero down should swap it with the next hero');
}

$result = reorderHero($db, 102, 'up');
if (($result['state'] ?? '') !== 'ok'
    || array_column($result['HeroList'], 'id_hero') !== [101, 102, 103]) {
    throw new RuntimeException('Moving a hero up should restore its previous position');
}

$result = reorderHero($db, 101, 'up');
if (($result['state'] ?? '') !== 'ok'
    || array_column($result['HeroList'], 'id_hero') !== [101, 102, 103]) {
    throw new RuntimeException('Moving the first hero up should preserve the order');
}

echo "Hero ordering tests passed\n";
