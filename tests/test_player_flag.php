<?php

class PlayerFlagTestResponse extends RuntimeException {
    public $payload;

    public function __construct($payload) {
        $this->payload = $payload;
    }
}

function sendJson($data): void {
    throw new PlayerFlagTestResponse($data);
}

require_once __DIR__ . '/../caesar-api/PlayerAuth.php';
require_once __DIR__ . '/../caesar-api/controllers/PlayerController.php';

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE player (id_player INTEGER PRIMARY KEY, p_token TEXT NOT NULL, city_flag TEXT NOT NULL)');
$db->exec('CREATE TABLE player_item (id_player INTEGER NOT NULL, id_item TEXT NOT NULL, amount INTEGER NOT NULL)');
$db->exec("INSERT INTO player VALUES (7, 'player-token', '')");
$db->exec("INSERT INTO player_item VALUES (7, 'flag', 2)");

function changePlayerFlagForTest(PDO $db, string $flag): array {
    $_POST = ['token' => 'player-token'];
    $_REQUEST = ['flag' => $flag];

    try {
        PlayerController::changePlayerFlag($db);
    } catch (PlayerFlagTestResponse $response) {
        return $response->payload;
    }

    throw new RuntimeException('Changing the player flag did not return JSON');
}

foreach (['AB', 'AB1', 'A!C', '123', '🌟BC'] as $invalidFlag) {
    $result = changePlayerFlagForTest($db, $invalidFlag);
    if (($result['state'] ?? null) !== 'error_flag_length') {
        throw new RuntimeException('Player flags must contain exactly three letters');
    }
}

$valid = changePlayerFlagForTest($db, 'ABC');
$storedFlag = $db->query('SELECT city_flag FROM player WHERE id_player = 7')->fetchColumn();
$remainingFlags = (int)$db->query("SELECT amount FROM player_item WHERE id_player = 7 AND id_item = 'flag'")->fetchColumn();
if (($valid['state'] ?? null) !== 'ok'
    || ($valid['city_flag'] ?? null) !== 'ABC'
    || $storedFlag !== 'ABC'
    || $remainingFlags !== 1) {
    throw new RuntimeException('A valid player flag should persist and consume exactly one item');
}

echo "Player flag validation and transaction tests passed\n";
