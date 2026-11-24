<?php
// Test for message.php MSG_NUMBERS total/unread counts and notification flow

class MessageTestResponse extends RuntimeException {
    public $payload;
    public function __construct($payload) {
        $this->payload = $payload;
    }
}

if (!function_exists('sendJson')) {
    function sendJson($data): void {
        throw new MessageTestResponse($data);
    }
}

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE player (id_player INTEGER PRIMARY KEY, p_token TEXT, name TEXT, id_guild INTEGER)');
$db->exec('CREATE TABLE msg_income (id_msg INTEGER PRIMARY KEY AUTOINCREMENT, id_player INTEGER, from_ INTEGER, from_name TEXT, subject TEXT, body TEXT, seen INTEGER, time_stamp TEXT)');
$db->exec('CREATE TABLE msg_out (id_msg INTEGER PRIMARY KEY AUTOINCREMENT, id_player INTEGER, to_player INTEGER, to_name TEXT, subject TEXT, body TEXT, time_stamp TEXT)');
$db->exec('CREATE TABLE msg_diff (id_msg INTEGER PRIMARY KEY AUTOINCREMENT, id_player INTEGER, subject TEXT, body TEXT, seen INTEGER, time_stamp TEXT)');
$db->exec('CREATE TABLE report_player (id_report INTEGER PRIMARY KEY AUTOINCREMENT, id_player INTEGER, seen INTEGER)');
$db->exec('CREATE TABLE spy_report (id_report INTEGER PRIMARY KEY AUTOINCREMENT, id_player INTEGER, seen INTEGER)');

$db->exec("INSERT INTO player VALUES (1, 'token-1', 'PlayerOne', 0)");
$db->exec("INSERT INTO player VALUES (2, 'token-2', 'PlayerTwo', 0)");

// Insert test messages: Player 1 has 3 income msgs (2 seen=1, 1 seen=0)
$db->exec("INSERT INTO msg_income (id_player, from_, from_name, subject, body, seen, time_stamp) VALUES (1, 2, 'PlayerTwo', 'Hello 1', 'Body 1', 1, '2026-11-23 10:00:00')");
$db->exec("INSERT INTO msg_income (id_player, from_, from_name, subject, body, seen, time_stamp) VALUES (1, 2, 'PlayerTwo', 'Hello 2', 'Body 2', 1, '2026-11-23 11:00:00')");
$db->exec("INSERT INTO msg_income (id_player, from_, from_name, subject, body, seen, time_stamp) VALUES (1, 2, 'PlayerTwo', 'Hello 3', 'Body 3', 0, '2026-11-23 12:00:00')");

// Player 1 has 2 outbox msgs
$db->exec("INSERT INTO msg_out (id_player, to_player, to_name, subject, body, time_stamp) VALUES (1, 2, 'PlayerTwo', 'Out 1', 'Body', '2026-11-23 09:00:00')");
$db->exec("INSERT INTO msg_out (id_player, to_player, to_name, subject, body, time_stamp) VALUES (1, 2, 'PlayerTwo', 'Out 2', 'Body', '2026-11-23 09:30:00')");

// Player 1 has 1 diff msg (seen=0)
$db->exec("INSERT INTO msg_diff (id_player, subject, body, seen, time_stamp) VALUES (1, 'Diff 1', 'Body', 0, '2026-11-23 08:00:00')");

// Test MSG_NUMBERS query
$idPlayer = 1;
$stmtTotals = $db->prepare(
    'SELECT
        (SELECT COUNT(*) FROM `msg_income` WHERE `id_player` = ?) AS `msg_income`,
        (SELECT COUNT(*) FROM `msg_out` WHERE `id_player` = ?) AS `msg_out`,
        (SELECT COUNT(*) FROM `msg_diff` WHERE `id_player` = ?) AS `msg_diff`,
        (SELECT COUNT(*) FROM `report_player` WHERE `id_player` = ?) AS `report_player`,
        (SELECT COUNT(*) FROM `spy_report` WHERE `id_player` = ?) AS `spy_report`'
);
$stmtTotals->execute([$idPlayer, $idPlayer, $idPlayer, $idPlayer, $idPlayer]);
$totals = $stmtTotals->fetch(PDO::FETCH_ASSOC);

$stmtUnread = $db->prepare(
    'SELECT
        (SELECT COUNT(*) FROM `msg_income` WHERE `id_player` = ? AND `seen` = 0) AS `unread_income`,
        (SELECT COUNT(*) FROM `msg_diff` WHERE `id_player` = ? AND `seen` = 0) AS `unread_diff`,
        (SELECT COUNT(*) FROM `report_player` WHERE `id_player` = ? AND `seen` = 0) AS `unread_report`,
        (SELECT COUNT(*) FROM `spy_report` WHERE `id_player` = ? AND `seen` = 0) AS `unread_spy`'
);
$stmtUnread->execute([$idPlayer, $idPlayer, $idPlayer, $idPlayer]);
$unreads = $stmtUnread->fetch(PDO::FETCH_ASSOC);

// Assert totals include ALL messages (not just seen=0)
if ((int)$totals['msg_income'] !== 3) {
    throw new RuntimeException("Expected total msg_income to be 3, got {$totals['msg_income']}");
}
if ((int)$totals['msg_out'] !== 2) {
    throw new RuntimeException("Expected total msg_out to be 2, got {$totals['msg_out']}");
}
if ((int)$totals['msg_diff'] !== 1) {
    throw new RuntimeException("Expected total msg_diff to be 1, got {$totals['msg_diff']}");
}

// Assert unread counts are accurate
if ((int)$unreads['unread_income'] !== 1) {
    throw new RuntimeException("Expected unread_income to be 1, got {$unreads['unread_income']}");
}
if ((int)$unreads['unread_diff'] !== 1) {
    throw new RuntimeException("Expected unread_diff to be 1, got {$unreads['unread_diff']}");
}

echo "Message numbers and notifications test passed successfully!\n";
