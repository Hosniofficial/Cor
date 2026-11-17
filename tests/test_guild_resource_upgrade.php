<?php

class GuildUpgradeTestResponse extends RuntimeException {
    public $payload;

    public function __construct($payload) {
        $this->payload = $payload;
    }
}

function sendJson($data): void {
    throw new GuildUpgradeTestResponse($data);
}

require_once __DIR__ . '/../caesar-api/PlayerAuth.php';
require_once __DIR__ . '/../caesar-api/controllers/GuildController.php';

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE player (id_player INTEGER PRIMARY KEY, p_token TEXT, id_guild INTEGER)');
$db->exec('CREATE TABLE guild_member (id_guild INTEGER, id_player INTEGER, rank INTEGER)');
$db->exec('CREATE TABLE guild (
    id_guild INTEGER PRIMARY KEY,
    lvl INTEGER NOT NULL,
    food INTEGER NOT NULL,
    wood INTEGER NOT NULL,
    stone INTEGER NOT NULL,
    metal INTEGER NOT NULL,
    coin INTEGER NOT NULL
)');
$db->exec("INSERT INTO player VALUES (7, 'guild-token', 3)");
$db->exec('INSERT INTO guild_member VALUES (3, 7, 4)');
$db->exec('INSERT INTO guild VALUES (3, 1, 1250000, 1250000, 1250000, 1250000, 1000000)');

function upgradeGuildForTest(PDO $db): array {
    $_POST = $_REQUEST = ['token' => 'guild-token'];
    try {
        GuildController::handle('/api/AGuild/upgradeUsingRes', $db);
    } catch (GuildUpgradeTestResponse $response) {
        return $response->payload;
    }
    throw new RuntimeException('Guild upgrade should return JSON');
}

$result = upgradeGuildForTest($db);
$guild = $db->query('SELECT * FROM guild WHERE id_guild = 3')->fetch(PDO::FETCH_ASSOC);
if (($result['state'] ?? '') !== 'ok'
    || (int)$guild['lvl'] !== 2
    || array_map('intval', [
        $guild['food'], $guild['wood'], $guild['stone'], $guild['metal'], $guild['coin']
    ]) !== [0, 0, 0, 0, 0]) {
    throw new RuntimeException('Guild upgrade should charge the displayed resource costs and increase its level');
}

$db->exec('UPDATE guild SET lvl = 1, food = 0, wood = 1250000, stone = 1250000, metal = 1250000, coin = 1000000 WHERE id_guild = 3');
$result = upgradeGuildForTest($db);
$level = (int)$db->query('SELECT lvl FROM guild WHERE id_guild = 3')->fetchColumn();
if (($result['state'] ?? '') !== 'error_not_enough_resources' || $level !== 1) {
    throw new RuntimeException('Guild upgrade should not proceed when any required resource is missing');
}

$db->exec('UPDATE guild SET lvl = 10, food = 99999999, wood = 99999999, stone = 99999999, metal = 99999999, coin = 99999999 WHERE id_guild = 3');
$result = upgradeGuildForTest($db);
if (($result['state'] ?? '') !== 'error_max_level') {
    throw new RuntimeException('Guilds should not upgrade beyond the displayed maximum level');
}

echo "Guild resource upgrade tests passed\n";
