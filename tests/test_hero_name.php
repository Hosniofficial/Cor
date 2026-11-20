<?php

class HeroNameTestResponse extends RuntimeException {
    public $payload;

    public function __construct($payload) {
        $this->payload = $payload;
    }
}

function sendJson($data): void {
    throw new HeroNameTestResponse($data);
}

require_once __DIR__ . '/../caesar-api/PlayerAuth.php';
require_once __DIR__ . '/../caesar-api/controllers/HeroController.php';

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE player (id_player INTEGER PRIMARY KEY, p_token TEXT)');
$db->exec('CREATE TABLE city_hero (
    id_hero INTEGER PRIMARY KEY,
    id_player INTEGER NOT NULL,
    name TEXT NOT NULL
)');
$db->exec("INSERT INTO player VALUES (7, 'player-token'), (8, 'other-token')");
$db->exec("INSERT INTO city_hero VALUES (101, 7, 'Old Hero')");

$request = function(array $data) use ($db) {
    $_POST = $data;
    $_GET = [];
    $_REQUEST = $data;
    try {
        HeroController::handle('/api/AHero/changeName', $db);
        throw new RuntimeException('Hero name endpoint should return a JSON response');
    } catch (HeroNameTestResponse $response) {
        return $response->payload;
    }
};

$success = $request([
    'token' => 'player-token',
    'idHero' => 101,
    'name' => 'New Hero'
]);
if (($success['state'] ?? null) !== 'ok'
    || ($success['name'] ?? null) !== 'New Hero'
    || $db->query('SELECT name FROM city_hero WHERE id_hero = 101')->fetchColumn() !== 'New Hero') {
    throw new RuntimeException('An owner should be able to change a hero name');
}

$invalidName = $request([
    'token' => 'player-token',
    'idHero' => 101,
    'name' => 'ab'
]);
if (($invalidName['state'] ?? null) !== 'error_name'
    || $db->query('SELECT name FROM city_hero WHERE id_hero = 101')->fetchColumn() !== 'New Hero') {
    throw new RuntimeException('Invalid hero names should not be saved');
}

$sameName = $request([
    'token' => 'player-token',
    'idHero' => 101,
    'name' => 'New Hero'
]);
if (($sameName['state'] ?? null) !== 'error_same_name') {
    throw new RuntimeException('An unchanged hero name should be rejected explicitly');
}

$unauthorized = $request([
    'token' => 'other-token',
    'idHero' => 101,
    'name' => 'Another Name'
]);
if (($unauthorized['state'] ?? null) !== 'error_auth'
    || $db->query('SELECT name FROM city_hero WHERE id_hero = 101')->fetchColumn() !== 'New Hero') {
    throw new RuntimeException('A different player must not be able to rename this hero');
}

echo "Hero name endpoint tests passed\n";
