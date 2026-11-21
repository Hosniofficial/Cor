<?php

class GuildChangeNameTestResponse extends RuntimeException {
    public $payload;

    public function __construct($payload) {
        $this->payload = $payload;
    }
}

function sendJson($data): void {
    throw new GuildChangeNameTestResponse($data);
}

require_once __DIR__ . '/../caesar-api/PlayerAuth.php';
require_once __DIR__ . '/../caesar-api/controllers/ItemController.php';
require_once __DIR__ . '/../caesar-api/controllers/GuildController.php';

function changeGuildForTest(PDO $db, array $changes): array {
    $_POST = $_REQUEST = array_merge([
        'token' => 'guild-change-token',
        'newGuildName' => 'Original Guild',
        'slog_top' => '1',
        'slog_cnt' => '1',
        'slog_btm' => '1',
    ], $changes);

    try {
        GuildController::handle('/api/AGuild/changeGuildName', $db);
    } catch (GuildChangeNameTestResponse $response) {
        return $response->payload;
    }

    throw new RuntimeException('Guild changes should return a JSON response');
}

function assertGuildChange(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "[PASS] {$message}\n";
}

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec("
    CREATE TABLE player (
        id_player INTEGER PRIMARY KEY,
        p_token TEXT NOT NULL,
        id_guild INTEGER NOT NULL,
        name TEXT NOT NULL,
        guild TEXT
    );
    CREATE TABLE guild_member (
        id_guild INTEGER NOT NULL,
        id_player INTEGER NOT NULL,
        rank INTEGER NOT NULL
    );
    CREATE TABLE guild (
        id_guild INTEGER PRIMARY KEY,
        name TEXT NOT NULL,
        slog_top TEXT NOT NULL,
        slog_cnt TEXT NOT NULL,
        slog_btm TEXT NOT NULL
    );
    CREATE TABLE player_item (
        id_player INTEGER NOT NULL,
        id_item TEXT NOT NULL,
        amount INTEGER NOT NULL,
        PRIMARY KEY (id_player, id_item)
    );
");
$db->exec("INSERT INTO player VALUES (7, 'guild-change-token', 3, 'Tester', 'Original Guild')");
$db->exec("INSERT INTO guild_member VALUES (3, 7, 6)");
$db->exec("INSERT INTO guild VALUES (3, 'Original Guild', '1', '1', '1')");
$db->exec("INSERT INTO player_item VALUES (7, 'family_slogan', 2)");

$result = changeGuildForTest($db, [
    'newGuildName' => 'Renamed Guild',
    'slog_top' => '2',
    'slog_cnt' => '3',
    'slog_btm' => '4',
]);
$guild = $db->query('SELECT * FROM guild WHERE id_guild = 3')->fetch(PDO::FETCH_ASSOC);
$playerGuildName = $db->query('SELECT guild FROM player WHERE id_player = 7')->fetchColumn();
$itemAmount = (int)$db->query(
    "SELECT amount FROM player_item WHERE id_player = 7 AND id_item = 'family_slogan'"
)->fetchColumn();
assertGuildChange(
    ($result['state'] ?? null) === 'ok'
        && $guild['name'] === 'Renamed Guild'
        && $playerGuildName === 'Renamed Guild',
    'Guild name and member name cache update together'
);
assertGuildChange(
    [$guild['slog_top'], $guild['slog_cnt'], $guild['slog_btm']] === ['2', '3', '4'],
    'Guild emblem layers update from the validated request'
);
assertGuildChange(
    $itemAmount === 1 && (int)$result['familySloganAmount'] === 1,
    'A successful change deducts one family-slogan item and returns its balance'
);

$db->exec("DELETE FROM player_item WHERE id_player = 7 AND id_item = 'family_slogan'");
$result = changeGuildForTest($db, [
    'newGuildName' => 'No Item Change',
    'slog_top' => '5',
]);
$guild = $db->query('SELECT name, slog_top FROM guild WHERE id_guild = 3')->fetch(PDO::FETCH_ASSOC);
assertGuildChange(
    ($result['state'] ?? null) === 'error_3'
        && $guild['name'] === 'Renamed Guild'
        && $guild['slog_top'] === '2',
    'Missing family-slogan item rejects changes without modifying the guild'
);

$db->exec("INSERT INTO player_item VALUES (7, 'family_slogan', 1)");
$db->exec("
    CREATE TRIGGER fail_member_guild_name
    BEFORE UPDATE OF guild ON player
    BEGIN SELECT RAISE(ABORT, 'member guild-name update failed'); END
");
$failedUpdate = false;
try {
    changeGuildForTest($db, [
        'newGuildName' => 'Rollback Guild',
        'slog_top' => '5',
    ]);
} catch (PDOException $error) {
    $failedUpdate = true;
}
$guild = $db->query('SELECT name, slog_top FROM guild WHERE id_guild = 3')->fetch(PDO::FETCH_ASSOC);
$playerGuildName = $db->query('SELECT guild FROM player WHERE id_player = 7')->fetchColumn();
$itemAmount = (int)$db->query(
    "SELECT amount FROM player_item WHERE id_player = 7 AND id_item = 'family_slogan'"
)->fetchColumn();
assertGuildChange($failedUpdate, 'Member-name update failure is surfaced');
assertGuildChange(
    $guild['name'] === 'Renamed Guild'
        && $guild['slog_top'] === '2'
        && $playerGuildName === 'Renamed Guild'
        && $itemAmount === 1,
    'Guild changes and item deduction roll back together on database failure'
);

$db->exec('DROP TRIGGER fail_member_guild_name');
$result = changeGuildForTest($db, ['slog_top' => '22']);
assertGuildChange(
    ($result['state'] ?? null) === 'error_slogan'
        && (int)$db->query(
            "SELECT amount FROM player_item WHERE id_player = 7 AND id_item = 'family_slogan'"
        )->fetchColumn() === 1,
    'Out-of-range emblem selections are rejected without consuming the item'
);

echo "Guild name and family-slogan transaction tests passed\n";
