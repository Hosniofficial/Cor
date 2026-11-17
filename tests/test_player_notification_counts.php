<?php

class PlayerNotificationTestResponse extends RuntimeException {
    public $payload;

    public function __construct($payload) {
        $this->payload = $payload;
    }
}

function sendJson($data): void {
    throw new PlayerNotificationTestResponse($data);
}

require_once __DIR__ . '/../caesar-api/PlayerAuth.php';
require_once __DIR__ . '/../caesar-api/controllers/PlayerController.php';

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE player (id_player INTEGER PRIMARY KEY, p_token TEXT)');
$db->exec('CREATE TABLE msg_income (id_player INTEGER, seen INTEGER)');
$db->exec('CREATE TABLE msg_diff (id_player INTEGER, seen INTEGER)');
$db->exec('CREATE TABLE report_player (id_player INTEGER, seen INTEGER)');
$db->exec('CREATE TABLE spy_report (id_player INTEGER, seen INTEGER)');
$db->exec("INSERT INTO player VALUES (1, 'player-token')");
$db->exec('INSERT INTO msg_income VALUES (1, 0), (1, 0), (1, 1), (2, 0)');
$db->exec('INSERT INTO msg_diff VALUES (1, 0), (1, 1), (2, 0)');
$db->exec('INSERT INTO report_player VALUES (1, 0), (2, 0)');
$db->exec('INSERT INTO spy_report VALUES (1, 0), (1, 0), (2, 0)');
$_GET['token'] = 'player-token';

try {
    PlayerController::handle('/api/APlayer/getAllNotif', $db);
} catch (PlayerNotificationTestResponse $response) {
    $expected = [
        'msg_in' => 2,
        'msg_diff' => 1,
        'msg_report' => 1,
        'spy_report' => 2
    ];
    if ($response->payload !== $expected) {
        throw new RuntimeException('Notification counts should include only unread messages for the authenticated player');
    }
    echo "Player notification count test passed\n";
    exit(0);
}

throw new RuntimeException('Notification endpoint should return its payload');
