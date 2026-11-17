<?php

class GuildCapacityTestResponse extends RuntimeException {
    public $payload;

    public function __construct($payload) {
        $this->payload = $payload;
    }
}

function sendJson($data): void {
    throw new GuildCapacityTestResponse($data);
}

require_once __DIR__ . '/../caesar-api/PlayerAuth.php';
require_once __DIR__ . '/../caesar-api/controllers/GuildController.php';

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE player (id_player INTEGER PRIMARY KEY, p_token TEXT, id_guild INTEGER, name TEXT)');
$db->exec('CREATE TABLE guild_member (id_guild INTEGER, id_player INTEGER)');
$db->exec('CREATE TABLE city (
    id_city INTEGER PRIMARY KEY,
    id_player INTEGER NOT NULL,
    food INTEGER NOT NULL,
    wood INTEGER NOT NULL,
    stone INTEGER NOT NULL,
    metal INTEGER NOT NULL,
    coin INTEGER NOT NULL
)');
$db->exec('CREATE TABLE guild (
    id_guild INTEGER PRIMARY KEY,
    name TEXT,
    id_leader INTEGER,
    lvl INTEGER,
    prestige INTEGER,
    honor INTEGER,
    mem_num INTEGER,
    mem_max INTEGER,
    slog_top TEXT,
    slog_cnt TEXT,
    slog_btm TEXT,
    word TEXT,
    food INTEGER,
    wood INTEGER,
    stone INTEGER,
    metal INTEGER,
    coin INTEGER,
    total_prize_share INTEGER
)');
$db->exec("INSERT INTO player VALUES (7, 'guild-token', 3, 'Tester')");
$db->exec('INSERT INTO guild_member VALUES (3, 7)');
$db->exec('INSERT INTO city VALUES (5, 7, 100000, 5000, 100000, 100000, 100000)');
$db->exec("INSERT INTO guild VALUES (
    3, 'Guild', 7, 1, 0, 0, 1, 50, '1', '1', '1', '', 1240000, 0, 0, 0, 0, 10
)");

function donateToGuildForTest(PDO $db, array $donation): array {
    $_POST = $_REQUEST = array_merge([
        'token' => 'guild-token',
        'idCity' => 5,
        'food' => 0,
        'wood' => 0,
        'stone' => 0,
        'metal' => 0,
        'coin' => 0
    ], $donation);
    try {
        GuildController::handle('/api/AGuild/donateRes', $db);
    } catch (GuildCapacityTestResponse $response) {
        return $response->payload;
    }
    throw new RuntimeException('Guild donation should return JSON');
}

$result = donateToGuildForTest($db, ['food' => 20000]);
if (($result['state'] ?? '') !== 'error_guild_capacity'
    || (int)$db->query('SELECT food FROM city WHERE id_city = 5')->fetchColumn() !== 100000
    || (int)$db->query('SELECT food FROM guild WHERE id_guild = 3')->fetchColumn() !== 1240000) {
    throw new RuntimeException('An over-capacity donation should be rejected without charging the city');
}

$result = donateToGuildForTest($db, ['food' => 10000]);
if (($result['state'] ?? '') !== 'ok'
    || (int)$db->query('SELECT food FROM city WHERE id_city = 5')->fetchColumn() !== 90000
    || (int)$db->query('SELECT food FROM guild WHERE id_guild = 3')->fetchColumn() !== 1250000) {
    throw new RuntimeException('A donation that exactly fills the resource capacity should succeed');
}

$result = donateToGuildForTest($db, ['wood' => 10000]);
if (($result['state'] ?? '') !== 'error_not_enough_resources'
    || (int)$db->query('SELECT wood FROM guild WHERE id_guild = 3')->fetchColumn() !== 0) {
    throw new RuntimeException('A donation should not credit guild resources the city cannot pay');
}

$result = donateToGuildForTest($db, ['coin' => -1]);
if (($result['state'] ?? '') !== 'error_invalid_amount') {
    throw new RuntimeException('Negative donation amounts should be rejected');
}

$result = donateToGuildForTest($db, ['stone' => 1]);
if (($result['state'] ?? '') !== 'ok'
    || (int)$db->query('SELECT stone FROM city WHERE id_city = 5')->fetchColumn() !== 99999
    || (int)$db->query('SELECT stone FROM guild WHERE id_guild = 3')->fetchColumn() !== 1) {
    throw new RuntimeException('Any positive donation within capacity and available resources should succeed');
}

echo "Guild resource capacity tests passed\n";
