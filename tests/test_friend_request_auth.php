<?php

class FriendAuthJsonResponse extends RuntimeException {
    public $payload;

    public function __construct($payload) {
        $this->payload = $payload;
    }
}

function sendJson($data): void {
    throw new FriendAuthJsonResponse($data);
}

require_once __DIR__ . '/../caesar-api/PlayerAuth.php';

function assertCondition(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, "[FAIL] {$message}\n");
        exit(1);
    }
    echo "[PASS] {$message}\n";
}

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE player (id_player INTEGER PRIMARY KEY, name TEXT, p_token TEXT)");
$db->exec("INSERT INTO player VALUES (1, 'PlayerOne', 'valid_token')");

$_GET = [];
$_POST = ['id_player' => 1];
$missingTokenResponse = null;
try {
    requireAuthenticatedPlayer($db);
} catch (FriendAuthJsonResponse $error) {
    $missingTokenResponse = $error->payload;
}
assertCondition(
    $missingTokenResponse !== null && $missingTokenResponse['state'] === 'error_auth',
    'A caller-supplied player ID cannot authenticate a friend request'
);

$_POST = ['token' => 'invalid_token', 'id_player' => 1];
$invalidTokenResponse = null;
try {
    requireAuthenticatedPlayer($db);
} catch (FriendAuthJsonResponse $error) {
    $invalidTokenResponse = $error->payload;
}
assertCondition(
    $invalidTokenResponse !== null && $invalidTokenResponse['state'] === 'error_auth',
    'An invalid token cannot be overridden by a caller-supplied player ID'
);

$_POST = ['token' => 'valid_token'];
$player = requireAuthenticatedPlayer($db);
assertCondition((int)$player['id_player'] === 1, 'A valid token resolves the authenticated player');

$endpoint = file_get_contents(__DIR__ . '/../caesar-web/api/player.php');
$friendHandler = substr($endpoint, strpos($endpoint, "if (isset(\$_POST['add_friend'])"));
$serverDataHandler = strpos($friendHandler, '// 4. Server Data');
$friendHandler = substr($friendHandler, 0, $serverDataHandler);
assertCondition(
    strpos($friendHandler, 'requireAuthenticatedPlayer($db)') !== false
        && strpos($friendHandler, "\$_POST['id_player']") === false
        && strpos($friendHandler, "\$_GET['id_player']") === false,
    'The add-friend endpoint uses token authentication instead of the supplied player ID'
);
