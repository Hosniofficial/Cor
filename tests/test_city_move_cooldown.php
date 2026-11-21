<?php
// tests/test_city_move_cooldown.php
// Full Integration Test Suite for City Relocation & 24h Attack Cooldown
// Verifies:
// 1. Certain Move (نقل المدينة المحدد):
//    - Prerequisite: rejects when heroes are outside city (in_city = 0) with error_army_outside.
//    - Prerequisite: rejects when battle expeditions are marching with error_army_outside.
//    - Out of bounds validation ([0, 499]).
//    - Occupied tile validation (city or barrary).
//    - Deducts certain_move item.
//    - Imposes 24-hour attack/expedition cooldown (attack_cooldown_until = now + 86400).
// 2. Random Move (نقل المدينة العشوائي):
//    - Prerequisite: rejects when heroes are outside city with error_army_outside.
//    - Deducts random_move item.
//    - Updates coordinates to empty tile.
//    - Does NOT impose attack cooldown (attack_cooldown_until remains 0).
// 3. Attack Enforcement:
//    - WorldController / joinBattel rejects attack if city is within 24h cooldown.
//    - joinBattel permits attack once cooldown has elapsed.

class CityMoveJsonResponse extends RuntimeException {
    public $payload;
    public function __construct($payload) {
        $this->payload = $payload;
    }
}

if (!function_exists('sendJson')) {
    function sendJson($data): void {
        throw new CityMoveJsonResponse($data);
    }
}

require_once __DIR__ . '/../caesar-api/PlayerAuth.php';
require_once __DIR__ . '/../caesar-api/controllers/ItemController.php';
require_once __DIR__ . '/../caesar-api/controllers/WorldController.php';

$passed = 0;
$failed = 0;

function assertCondition(bool $condition, string $message): void {
    global $passed, $failed;
    if ($condition) {
        echo "[PASS] {$message}\n";
        $passed++;
    } else {
        echo "[FAIL] {$message}\n";
        $failed++;
    }
}

echo "=== STARTING CITY MOVE & 24H ATTACK COOLDOWN TEST SUITE ===\n\n";

// In-memory SQLite DB
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Setup schema
$db->exec("
    CREATE TABLE player (
        id_player INTEGER PRIMARY KEY,
        name TEXT,
        porm INTEGER DEFAULT 1,
        id_guild INTEGER DEFAULT 0,
        gold INTEGER DEFAULT 0,
        p_token TEXT
    );
    CREATE TABLE city (
        id_city INTEGER PRIMARY KEY AUTOINCREMENT,
        id_player INTEGER,
        name TEXT,
        x INTEGER,
        y INTEGER,
        lvl INTEGER DEFAULT 1,
        helper INTEGER DEFAULT 0,
        attack_cooldown_until INTEGER DEFAULT 0
    );
    CREATE TABLE city_hero (
        id_hero INTEGER PRIMARY KEY AUTOINCREMENT,
        id_player INTEGER,
        id_city INTEGER,
        name TEXT,
        avatar INTEGER DEFAULT 1,
        lvl INTEGER DEFAULT 1,
        console INTEGER DEFAULT 0,
        in_city INTEGER DEFAULT 1,
        loyal INTEGER DEFAULT 100,
        power INTEGER DEFAULT 100,
        power_max INTEGER DEFAULT 100,
        power_ls INTEGER DEFAULT 0
    );
    CREATE TABLE hero_army (
        id_hero INTEGER PRIMARY KEY,
        f_1_type INTEGER DEFAULT 1, f_1_num INTEGER DEFAULT 100,
        f_2_type INTEGER DEFAULT 0, f_2_num INTEGER DEFAULT 0,
        f_3_type INTEGER DEFAULT 0, f_3_num INTEGER DEFAULT 0,
        b_1_type INTEGER DEFAULT 0, b_1_num INTEGER DEFAULT 0,
        b_2_type INTEGER DEFAULT 0, b_2_num INTEGER DEFAULT 0,
        b_3_type INTEGER DEFAULT 0, b_3_num INTEGER DEFAULT 0
    );
    CREATE TABLE player_item (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        id_player INTEGER,
        id_item TEXT,
        amount INTEGER DEFAULT 0
    );
    CREATE TABLE city_barray (
        id_barray INTEGER PRIMARY KEY AUTOINCREMENT,
        id_city INTEGER,
        id_player INTEGER,
        x_coord INTEGER,
        y_coord INTEGER,
        Type INTEGER,
        lvl INTEGER DEFAULT 1
    );
    CREATE TABLE world_battel (
        id_battel INTEGER PRIMARY KEY AUTOINCREMENT,
        id_player INTEGER,
        id_city INTEGER,
        id_hero INTEGER,
        task INTEGER DEFAULT 0,
        x_city INTEGER DEFAULT 0,
        y_city INTEGER DEFAULT 0,
        x_coord INTEGER DEFAULT 0,
        y_coord INTEGER DEFAULT 0,
        time_start INTEGER DEFAULT 0,
        time_arrive INTEGER DEFAULT 0,
        time_back INTEGER DEFAULT 0,
        return_duration INTEGER DEFAULT 0,
        state TEXT DEFAULT 'marching'
    );
    CREATE TABLE world_battel_member (
        id_member INTEGER PRIMARY KEY AUTOINCREMENT,
        id_battel INTEGER,
        id_player INTEGER,
        id_city INTEGER,
        id_hero INTEGER,
        side INTEGER DEFAULT 0,
        task INTEGER DEFAULT 0,
        x_city INTEGER DEFAULT 0,
        y_city INTEGER DEFAULT 0,
        time_start INTEGER DEFAULT 0,
        time_arrive INTEGER DEFAULT 0,
        return_duration INTEGER DEFAULT 0,
        state TEXT DEFAULT 'marching'
    );
");

// Insert seed player & cities
$db->exec("INSERT INTO player (id_player, name, p_token) VALUES (1, 'PlayerOne', 'token_1')");
$db->exec("INSERT INTO player (id_player, name, p_token) VALUES (2, 'PlayerTwo', 'token_2')");
$db->exec("INSERT INTO city (id_city, id_player, name, x, y, attack_cooldown_until) VALUES (10, 1, 'City 1', 100, 100, 0)");
$db->exec("INSERT INTO city (id_city, id_player, name, x, y, attack_cooldown_until) VALUES (20, 2, 'Enemy City', 200, 200, 0)");

// Seed items for player 1
$db->exec("INSERT INTO player_item (id_player, id_item, amount) VALUES (1, 'certain_move', 2)");
$db->exec("INSERT INTO player_item (id_player, id_item, amount) VALUES (1, 'random_move', 2)");

// Seed hero for city 10 (currently in city)
$db->exec("INSERT INTO city_hero (id_hero, id_player, id_city, name, in_city) VALUES (101, 1, 10, 'Hero 1', 1)");
$db->exec("INSERT INTO hero_army (id_hero, f_1_num) VALUES (101, 500)");

$playerAuth = ['id_player' => 1, 'name' => 'PlayerOne'];

// Helper to call ItemController::handleItemUse
function invokeItemUse($action, $db, $player, $request) {
    $request['token'] = 'token_' . $player['id_player'];
    $_REQUEST = $request;
    $_POST = $request;
    $_GET = $request;
    try {
        ItemController::handleItemUse($action, $db);
        return null;
    } catch (CityMoveJsonResponse $e) {
        return $e->payload;
    }
}

// 1. Test Certain Move: Blocked if hero is outside city
echo "--- Testing Prerequisite Checks for Certain Move ---\n";
$db->exec("UPDATE city_hero SET in_city = 0 WHERE id_hero = 101");
$res = invokeItemUse('useCertainMove', $db, $playerAuth, [
    'Item' => 'certain_move',
    'idCity' => 10,
    'newX' => 120,
    'newY' => 130
]);
assertCondition(($res['state'] ?? '') === 'error_army_outside', 'Certain move rejected when hero is outside city (in_city = 0)');

// Reset hero to in_city = 1
$db->exec("UPDATE city_hero SET in_city = 1 WHERE id_hero = 101");

// Test Certain Move: Blocked if active battle expedition is marching from city
$db->exec("INSERT INTO world_battel (id_battel, id_player, id_city, state) VALUES (1, 1, 10, 'marching')");
$res = invokeItemUse('useCertainMove', $db, $playerAuth, [
    'Item' => 'certain_move',
    'idCity' => 10,
    'newX' => 120,
    'newY' => 130
]);
assertCondition(($res['state'] ?? '') === 'error_army_outside', 'Certain move rejected when battle expedition is active');
$db->exec("DELETE FROM world_battel WHERE id_battel = 1");

// 2. Test Certain Move: Invalid coordinates
echo "\n--- Testing Boundary and Tile Validation ---\n";
$res = invokeItemUse('useCertainMove', $db, $playerAuth, [
    'Item' => 'certain_move',
    'idCity' => 10,
    'newX' => 550,
    'newY' => 130
]);
assertCondition(($res['state'] ?? '') === 'error_coord', 'Certain move rejected with out-of-bounds coordinates (550, 130)');

// Test Certain Move: Target tile occupied by enemy city (200, 200)
$res = invokeItemUse('useCertainMove', $db, $playerAuth, [
    'Item' => 'certain_move',
    'idCity' => 10,
    'newX' => 200,
    'newY' => 200
]);
assertCondition(($res['state'] ?? '') === 'error_no_place_empty', 'Certain move rejected when target coordinates are occupied by another city');

// Test Certain Move: Target tile occupied by colonized wild
$db->exec("INSERT INTO city_barray (id_barray, id_city, id_player, x_coord, y_coord, Type) VALUES (1, 20, 2, 210, 210, 1)");
$res = invokeItemUse('useCertainMove', $db, $playerAuth, [
    'Item' => 'certain_move',
    'idCity' => 10,
    'newX' => 210,
    'newY' => 210
]);
assertCondition(($res['state'] ?? '') === 'error_no_place_empty', 'Certain move rejected when target coordinates are occupied by colonized wild');

// 3. Test Certain Move Success: Deducts item, updates coordinates, sets 24h attack cooldown
echo "\n--- Testing Certain Move Success & 24h Cooldown ---\n";
$beforeTime = time();
$res = invokeItemUse('useCertainMove', $db, $playerAuth, [
    'Item' => 'certain_move',
    'idCity' => 10,
    'newX' => 300,
    'newY' => 350
]);
assertCondition(($res['state'] ?? '') === 'ok', 'Certain move succeeded with valid empty coordinates');
assertCondition(($res['City']['x'] ?? 0) === 300 && ($res['City']['y'] ?? 0) === 350, 'City coordinates updated to (300, 350)');

// Verify item deduction
$itemRemaining = $db->query("SELECT amount FROM player_item WHERE id_player = 1 AND id_item = 'certain_move'")->fetchColumn();
assertCondition((int)$itemRemaining === 1, 'Player certain_move item deducted by 1');

// Verify 24h cooldown set on city
$cooldownUntil = (int)$db->query("SELECT attack_cooldown_until FROM city WHERE id_city = 10")->fetchColumn();
assertCondition($cooldownUntil >= $beforeTime + 86390 && $cooldownUntil <= $beforeTime + 86410, 'attack_cooldown_until set to approximately now + 24 hours (86400s)');
assertCondition(($res['attack_cooldown_until'] ?? 0) === $cooldownUntil, 'attack_cooldown_until timestamp returned in API response');

// 4. Test Attack Restriction with Active Cooldown
echo "\n--- Testing Attack Prevention During 24h Cooldown ---\n";
// Create a battle to join as attacker (side = 0)
$now = time();
$db->exec("INSERT INTO world_battel (id_battel, id_player, id_city, id_hero, task, x_city, y_city, x_coord, y_coord, time_start, time_arrive, state)
           VALUES (100, 2, 20, 201, 0, 200, 200, 250, 250, $now, $now + 500, 'marching')");

function invokeJoinBattle($db, $player, $request) {
    $_REQUEST = $request;
    $_SESSION['auth_player'] = $player;
    try {
        WorldController::joinBattel($db);
        return null;
    } catch (CityMoveJsonResponse $e) {
        return $e->payload;
    }
}

$joinRes = invokeJoinBattle($db, $playerAuth, [
    'idBattel' => 100,
    'idHero' => 101,
    'side' => 0
]);
assertCondition(($joinRes['state'] ?? '') === 'error_city_move_cooldown', 'Joining battle as attacker rejected with error_city_move_cooldown');

// Once cooldown expires (set attack_cooldown_until in the past)
$db->exec("UPDATE city SET attack_cooldown_until = " . ($now - 100) . " WHERE id_city = 10");
// Mock hero power and requirements for joinBattel
$joinResExpired = invokeJoinBattle($db, $playerAuth, [
    'idBattel' => 100,
    'idHero' => 101,
    'side' => 0
]);
assertCondition(($joinResExpired['state'] ?? '') !== 'error_city_move_cooldown', 'Joining battle permitted after 24h cooldown expires');

// 5. Test Random Move: Does NOT set cooldown
echo "\n--- Testing Random Move (No 24h Cooldown) ---\n";
// Ensure hero outside check works for random move as well
$db->exec("UPDATE city_hero SET in_city = 0 WHERE id_hero = 101");
$resRand = invokeItemUse('useRandomMove', $db, $playerAuth, [
    'Item' => 'random_move',
    'idCity' => 10
]);
assertCondition(($resRand['state'] ?? '') === 'error_army_outside', 'Random move rejected when hero is outside city');
$db->exec("UPDATE city_hero SET in_city = 1 WHERE id_hero = 101");

// Reset cooldown to 0 on city
$db->exec("UPDATE city SET attack_cooldown_until = 0 WHERE id_city = 10");
$resRand = invokeItemUse('useRandomMove', $db, $playerAuth, [
    'Item' => 'random_move',
    'idCity' => 10
]);
assertCondition(($resRand['state'] ?? '') === 'ok', 'Random move succeeded');
$cooldownAfterRandom = (int)$db->query("SELECT attack_cooldown_until FROM city WHERE id_city = 10")->fetchColumn();
assertCondition($cooldownAfterRandom === 0, 'Random move does NOT impose 24h attack cooldown (attack_cooldown_until remains 0)');

$randItemRemaining = $db->query("SELECT amount FROM player_item WHERE id_player = 1 AND id_item = 'random_move'")->fetchColumn();
assertCondition((int)$randItemRemaining === 1, 'Player random_move item deducted by 1');

// Fill each candidate from the next random search so the no-space path is deterministic.
srand(7321);
$occupiedCoords = $db->prepare(
    'INSERT INTO city_barray (id_city, id_player, x_coord, y_coord, Type) VALUES (10, 1, ?, ?, 1)'
);
for ($attempt = 0; $attempt < 100; $attempt++) {
    $occupiedCoords->execute([rand(50, 450), rand(50, 450)]);
}
srand(7321);
$cityBeforeNoSpaceMove = $db->query("SELECT x, y FROM city WHERE id_city = 10")->fetch(PDO::FETCH_ASSOC);
$resNoSpace = invokeItemUse('useRandomMove', $db, $playerAuth, [
    'Item' => 'random_move',
    'idCity' => 10
]);
$cityAfterNoSpaceMove = $db->query("SELECT x, y FROM city WHERE id_city = 10")->fetch(PDO::FETCH_ASSOC);
$randItemAfterNoSpace = (int)$db->query(
    "SELECT amount FROM player_item WHERE id_player = 1 AND id_item = 'random_move'"
)->fetchColumn();
assertCondition(($resNoSpace['state'] ?? '') === 'error_no_place_empty', 'Random move reports when no empty candidate is found');
assertCondition(
    $cityAfterNoSpaceMove === $cityBeforeNoSpaceMove && $randItemAfterNoSpace === 1,
    'Random move without an empty tile neither moves the city nor consumes the item'
);

$db->exec("
    CREATE TRIGGER reject_certain_city_move
    BEFORE UPDATE OF x, y ON city
    WHEN NEW.id_city = 10 AND (NEW.x != OLD.x OR NEW.y != OLD.y)
    BEGIN
        SELECT RAISE(ABORT, 'simulated city relocation failure');
    END
");
$certainItemBeforeFailedMove = (int)$db->query(
    "SELECT amount FROM player_item WHERE id_player = 1 AND id_item = 'certain_move'"
)->fetchColumn();
$cityBeforeFailedCertainMove = $db->query("SELECT x, y FROM city WHERE id_city = 10")->fetch(PDO::FETCH_ASSOC);
$certainMoveFailed = false;
try {
    invokeItemUse('useCertainMove', $db, $playerAuth, [
        'Item' => 'certain_move',
        'idCity' => 10,
        'newX' => 310,
        'newY' => 360
    ]);
} catch (PDOException $error) {
    $certainMoveFailed = strpos($error->getMessage(), 'simulated city relocation failure') !== false;
}
$certainItemAfterFailedMove = (int)$db->query(
    "SELECT amount FROM player_item WHERE id_player = 1 AND id_item = 'certain_move'"
)->fetchColumn();
$cityAfterFailedCertainMove = $db->query("SELECT x, y FROM city WHERE id_city = 10")->fetch(PDO::FETCH_ASSOC);
assertCondition($certainMoveFailed, 'Certain move reports a database relocation failure');
assertCondition(
    $certainItemAfterFailedMove === $certainItemBeforeFailedMove
        && $cityAfterFailedCertainMove === $cityBeforeFailedCertainMove,
    'Failed certain move rolls back both item deduction and city coordinates'
);
$db->exec("DROP TRIGGER reject_certain_city_move");

echo "\n=======================================================\n";
echo "CITY MOVE RESULTS: {$passed} / " . ($passed + $failed) . " tests passed.\n";
echo "=======================================================\n";

if ($failed > 0) {
    exit(1);
}
