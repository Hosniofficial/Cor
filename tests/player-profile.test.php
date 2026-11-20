<?php

class JsonResponseException extends RuntimeException {
    public $payload;

    public function __construct($payload) {
        $this->payload = $payload;
    }
}

function sendJson($data): void {
    throw new JsonResponseException($data);
}

require_once __DIR__ . '/../caesar-api/PlayerAuth.php';
require_once __DIR__ . '/../caesar-api/controllers/PlayerController.php';

function createPlayerDatabase(): PDO {
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec('CREATE TABLE player (id_player INTEGER PRIMARY KEY, p_token TEXT, name TEXT UNIQUE, avatar INTEGER)');
    $db->exec('CREATE TABLE player_item (id_player INTEGER, id_item TEXT, amount INTEGER, PRIMARY KEY (id_player, id_item))');
    $db->exec("INSERT INTO player (id_player, p_token, name, avatar) VALUES (1, 'test-token', 'Player One', 1)");
    return $db;
}

function callController(callable $action): array {
    try {
        $action();
    } catch (JsonResponseException $response) {
        return $response->payload;
    }
    throw new RuntimeException('Controller did not send a JSON response');
}

$db = createPlayerDatabase();
$_POST = ['token' => 'test-token'];
$_REQUEST = ['token' => 'test-token', 'NewName' => 'Player Two'];
$db->exec("INSERT INTO player_item (id_player, id_item, amount) VALUES (1, 'change_name', 1)");
$response = callController(static function () use ($db): void {
    PlayerController::changePlayerName($db);
});
if (($response['state'] ?? null) !== 'ok'
    || $db->query('SELECT name FROM player WHERE id_player = 1')->fetchColumn() !== 'Player Two'
    || $db->query("SELECT COUNT(*) FROM player_item WHERE id_player = 1 AND id_item = 'change_name'")->fetchColumn() != 0) {
    throw new RuntimeException('Name change must update the name and consume the change_name item');
}

$db = createPlayerDatabase();
$_POST = ['token' => 'test-token'];
$_REQUEST = ['token' => 'test-token', 'NewName' => 'Player Two'];
$response = callController(static function () use ($db): void {
    PlayerController::changePlayerName($db);
});
if (($response['state'] ?? null) !== 'error_no_item'
    || $db->query('SELECT name FROM player WHERE id_player = 1')->fetchColumn() !== 'Player One') {
    throw new RuntimeException('Name changes without the required item must be rejected');
}

$db = createPlayerDatabase();
$_POST = ['token' => 'test-token'];
$_REQUEST = ['token' => 'test-token', 'NewName' => 'Player Two'];
$db->exec("INSERT INTO player (id_player, p_token, name, avatar) VALUES (2, 'other-token', 'Player Two', 1)");
$db->exec("INSERT INTO player_item (id_player, id_item, amount) VALUES (1, 'change_name', 1)");
$response = callController(static function () use ($db): void {
    PlayerController::changePlayerName($db);
});
if (($response['state'] ?? null) !== 'error_2'
    || (int)$db->query("SELECT amount FROM player_item WHERE id_player = 1 AND id_item = 'change_name'")->fetchColumn() !== 1) {
    throw new RuntimeException('Duplicate names must not consume the name-change item');
}

$db = createPlayerDatabase();
$_POST = ['token' => 'test-token'];
$_REQUEST = ['token' => 'test-token', 'image_index' => '19'];
$response = callController(static function () use ($db): void {
    PlayerController::changePlayerAvatar($db);
});
if (($response['state'] ?? null) !== 'ok'
    || (int)$db->query('SELECT avatar FROM player WHERE id_player = 1')->fetchColumn() !== 19) {
    throw new RuntimeException('Valid avatar changes must be saved for the authenticated player');
}

$db = createPlayerDatabase();
$_POST = ['token' => 'test-token'];
$_REQUEST = ['token' => 'test-token', 'image_index' => '20'];
$response = callController(static function () use ($db): void {
    PlayerController::changePlayerAvatar($db);
});
if (($response['state'] ?? null) !== 'error_invalid_avatar'
    || (int)$db->query('SELECT avatar FROM player WHERE id_player = 1')->fetchColumn() !== 1) {
    throw new RuntimeException('Invalid avatar indexes must be rejected without changing the player');
}

echo "Player profile API checks passed\n";
