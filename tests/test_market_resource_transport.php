<?php
// tests/test_market_resource_transport.php
// Comprehensive Integration Tests for Market Resource Transport Lifecycle:
// 1. Distance-based travel time (D * 20s) and resource deductions
// 2. 20 concurrent active porters limit (error_1)
// 3. Arrival delivery to destination city & transition to returning porters
// 4. Return trip completion freeing transport slot
// 5. Transport speed-up using shopping_car (halves remaining time, sets acce=1)

class MarketTransportJsonResponse extends RuntimeException {
    public $payload;
    public function __construct($payload) {
        $this->payload = $payload;
    }
}

if (!function_exists('sendJson')) {
    function sendJson($data): void {
        throw new MarketTransportJsonResponse($data);
    }
}

require_once __DIR__ . '/../caesar-api/PlayerAuth.php';
require_once __DIR__ . '/../caesar-api/services/ResourceService.php';
require_once __DIR__ . '/../caesar-api/services/ProductionService.php';
require_once __DIR__ . '/../caesar-api/controllers/MarketController.php';
require_once __DIR__ . '/../caesar-api/controllers/CityController.php';

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

// Setup in-memory SQLite database
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->sqliteCreateFunction('GREATEST', static function (...$values) {
    return max($values);
}, -1);
$db->sqliteCreateFunction('LEAST', static function (...$values) {
    return min($values);
}, -1);

$db->exec("
    CREATE TABLE `player` (
        `id_player` INTEGER PRIMARY KEY,
        `name` TEXT NOT NULL,
        `p_token` TEXT NOT NULL
    );

    CREATE TABLE `city` (
        `id_city` INTEGER PRIMARY KEY,
        `id_player` INTEGER NOT NULL,
        `name` TEXT NOT NULL,
        `x` INTEGER NOT NULL,
        `y` INTEGER NOT NULL,
        `food` INTEGER NOT NULL DEFAULT 0,
        `wood` INTEGER NOT NULL DEFAULT 0,
        `stone` INTEGER NOT NULL DEFAULT 0,
        `metal` INTEGER NOT NULL DEFAULT 0,
        `coin` INTEGER NOT NULL DEFAULT 0,
        `pop` INTEGER NOT NULL DEFAULT 100,
        `pop_max` INTEGER NOT NULL DEFAULT 1000
    );

    CREATE TABLE `city_building` (
        `id_city` INTEGER NOT NULL,
        `place` TEXT NOT NULL,
        `type` INTEGER NOT NULL,
        `lvl` INTEGER NOT NULL DEFAULT 1,
        PRIMARY KEY (`id_city`, `place`)
    );

    CREATE TABLE `city_market_trans` (
        `id_trans` INTEGER PRIMARY KEY AUTOINCREMENT,
        `id_player` INTEGER NOT NULL,
        `id_city_from` INTEGER NOT NULL,
        `id_city_to` INTEGER NOT NULL,
        `resource` TEXT NOT NULL DEFAULT 'mixed',
        `amount` INTEGER NOT NULL DEFAULT 0,
        `food` INTEGER NOT NULL DEFAULT 0,
        `wood` INTEGER NOT NULL DEFAULT 0,
        `stone` INTEGER NOT NULL DEFAULT 0,
        `metal` INTEGER NOT NULL DEFAULT 0,
        `coin` INTEGER NOT NULL DEFAULT 0,
        `time_start` INTEGER NOT NULL DEFAULT 0,
        `time_arrive` INTEGER NOT NULL DEFAULT 0,
        `status` TEXT NOT NULL DEFAULT 'marching',
        `acce` INTEGER NOT NULL DEFAULT 0
    );

    CREATE TABLE `player_item` (
        `id_player` INTEGER NOT NULL,
        `id_item` TEXT NOT NULL,
        `amount` INTEGER NOT NULL DEFAULT 0,
        PRIMARY KEY (`id_player`, `id_item`)
    );
");

// Seed test fixtures:
// Player 1, City 10 at (100, 100), City 20 at (103, 104) -> distance = sqrt(3^2 + 4^2) = 5
$db->exec("INSERT INTO `player` VALUES (1, 'CaesarCommander', 'test_auth_token')");
$db->exec("INSERT INTO `player` VALUES (2, 'OtherPlayer', 'other_auth_token')");
$db->exec("INSERT INTO `city` VALUES (10, 1, 'OriginCity', 100, 100, 500000, 500000, 500000, 500000, 500000, 500, 1000)");
$db->exec("INSERT INTO `city` VALUES (20, 1, 'DestCity', 103, 104, 0, 0, 0, 0, 0, 500, 1000)");
$db->exec("INSERT INTO `city` VALUES (30, 2, 'OtherPlayerCity', 110, 110, 0, 0, 0, 0, 0, 500, 1000)");
// Market level 10 in OriginCity (capacity = 10 * 200,000 = 2,000,000)
$db->exec("INSERT INTO `city_building` VALUES (10, 'market_1', 11, 10)");
// Player has 2 shopping_car items
$db->exec("INSERT INTO `player_item` VALUES (1, 'shopping_car', 2)");

$_GET['token'] = 'test_auth_token';
$_POST['token'] = 'test_auth_token';
$_REQUEST['token'] = 'test_auth_token';

echo "=== STARTING MARKET RESOURCE TRANSPORT TEST SUITE ===\n\n";

// --- 1. Test Dispatching Transport with Euclidean Distance ---
$_REQUEST['idCityFrom'] = 10;
$_REQUEST['idCityTo']   = 20;
$_REQUEST['food']       = 10000;
$_REQUEST['wood']       = 20000;
$_REQUEST['stone']      = 5000;
$_REQUEST['metal']      = 2000;
$_REQUEST['coin']       = 1000;

$dispatchPayload = null;
try {
    MarketController::transportResource($db);
} catch (MarketTransportJsonResponse $e) {
    $dispatchPayload = $e->payload;
} catch (Throwable $t) {
    echo "ERROR: " . $t->getMessage() . "\n" . $t->getTraceAsString() . "\n";
}

assertCondition($dispatchPayload !== null && $dispatchPayload['state'] === 'ok', 'Transport dispatched successfully');
$transId = (int)($dispatchPayload['id_trans'] ?? 0);
assertCondition($transId > 0, "Transport assigned ID {$transId}");

// Verify origin city resources deducted
$origin = $db->query("SELECT food, wood, stone, metal, coin FROM city WHERE id_city = 10")->fetch(PDO::FETCH_ASSOC);
assertCondition((int)$origin['food'] === 490000, 'Origin city food deducted by 10,000');
assertCondition((int)$origin['wood'] === 480000, 'Origin city wood deducted by 20,000');
assertCondition((int)$origin['stone'] === 495000, 'Origin city stone deducted by 5,000');
assertCondition((int)$origin['metal'] === 498000, 'Origin city metal deducted by 2,000');
assertCondition((int)$origin['coin'] === 499000, 'Origin city coin deducted by 1,000');

// Verify city_market_trans record
$transRow = $db->query("SELECT * FROM city_market_trans WHERE id_trans = {$transId}")->fetch(PDO::FETCH_ASSOC);
assertCondition($transRow['status'] === 'marching', 'Transport status is marching');
assertCondition((int)$transRow['food'] === 10000 && (int)$transRow['wood'] === 20000, 'Transport cargo recorded correctly');
$expectedDuration = 5 * 20; // 100s
$actualDuration = (int)$transRow['time_arrive'] - (int)$transRow['time_start'];
assertCondition($actualDuration === $expectedDuration, "Distance formula applied: duration is {$actualDuration}s (expected {$expectedDuration}s for dist 5)");

// --- 2. Test getCityTransportResource ---
$_REQUEST['idCity'] = 10;
$originList = null;
try {
    MarketController::getCityTransportResource($db);
} catch (MarketTransportJsonResponse $e) {
    $originList = $e->payload;
}
assertCondition(is_array($originList) && count($originList) === 1, 'Origin city has 1 active outgoing transport');
assertCondition($originList[0]['CityNameFrom'] === 'OriginCity' && $originList[0]['CityNameTo'] === 'DestCity', 'City names joined in transport list');

$_REQUEST['idCity'] = 20;
$destList = null;
try {
    MarketController::getCityTransportResource($db);
} catch (MarketTransportJsonResponse $e) {
    $destList = $e->payload;
}
assertCondition(is_array($destList) && count($destList) === 1, 'Destination city has 1 incoming marching transport');

// --- 3. Test Speed Up Transport ---
$_REQUEST['idTrans'] = $transId;
$_REQUEST['idCity']  = 10;
$speedPayload = null;
try {
    MarketController::speedUpTransport($db);
} catch (MarketTransportJsonResponse $e) {
    $speedPayload = $e->payload;
}
assertCondition($speedPayload !== null && $speedPayload['state'] === 'ok', 'Speed up transport succeeded');

// Verify shopping_car deducted
$carCount = (int)$db->query("SELECT amount FROM player_item WHERE id_player = 1 AND id_item = 'shopping_car'")->fetchColumn();
assertCondition($carCount === 1, '1 shopping_car consumed (remaining: 1)');

// Verify acce set to 1 and remaining time halved
$speedTrans = $db->query("SELECT time_arrive, acce FROM city_market_trans WHERE id_trans = {$transId}")->fetch(PDO::FETCH_ASSOC);
assertCondition((int)$speedTrans['acce'] === 1, 'Transport acce set to 1');
$newRemaining = (int)$speedTrans['time_arrive'] - time();
assertCondition($newRemaining <= 51 && $newRemaining >= 45, "Remaining time halved to {$newRemaining}s");

// Re-speed up should fail with error_1
$reSpeedPayload = null;
try {
    MarketController::speedUpTransport($db);
} catch (MarketTransportJsonResponse $e) {
    $reSpeedPayload = $e->payload;
}
assertCondition($reSpeedPayload !== null && $reSpeedPayload['state'] === 'error_1', 'Re-accelerating same transport rejected with error_1');

// --- 4. Test 20 Active Porters Limit ---
for ($i = 0; $i < 19; $i++) {
    $db->exec("
        INSERT INTO city_market_trans (id_player, id_city_from, id_city_to, food, time_start, time_arrive, status, acce)
        VALUES (1, 10, 20, 100, " . time() . ", " . (time() + 100) . ", 'marching', 0)
    ");
}
$activeCount = (int)$db->query("SELECT COUNT(*) FROM city_market_trans WHERE id_city_from = 10 AND status IN ('marching', 'returning')")->fetchColumn();
assertCondition($activeCount === 20, "Total active transports now at maximum capacity ({$activeCount}/20)");

// Attempt 21st transport
$_REQUEST['idCityFrom'] = 10;
$_REQUEST['idCityTo']   = 20;
$_REQUEST['food']       = 1000;
$_REQUEST['wood']       = 0;
$_REQUEST['stone']      = 0;
$_REQUEST['metal']      = 0;
$_REQUEST['coin']       = 0;
$blockedPayload = null;
try {
    MarketController::transportResource($db);
} catch (MarketTransportJsonResponse $e) {
    $blockedPayload = $e->payload;
}
assertCondition($blockedPayload !== null && $blockedPayload['state'] === 'error_1', '21st transport rejected with error_1 (all porters busy)');

// Clean up the 19 extra dummy transports
$db->exec("DELETE FROM city_market_trans WHERE id_trans != {$transId}");

// A player must not transport resources to another player's city.
$_REQUEST['idCityTo'] = 30;
$_REQUEST['food'] = 1000;
$foreignDestinationPayload = null;
try {
    MarketController::transportResource($db);
} catch (MarketTransportJsonResponse $e) {
    $foreignDestinationPayload = $e->payload;
}
$originAfterRejectedTransport = (int)$db->query("SELECT food FROM city WHERE id_city = 10")->fetchColumn();
assertCondition(
    $foreignDestinationPayload !== null && $foreignDestinationPayload['state'] === 'error_invalid_input',
    'Transport to another player city is rejected'
);
assertCondition($originAfterRejectedTransport === 490000, 'Rejected foreign destination does not deduct resources');
$_REQUEST['idCityTo'] = 20;

// --- 5. Test Arrival Resolution & Delivery to Destination ---
// Fast forward arrival time into the past
$pastArrival = time() - 10;
$originalDuration = $expectedDuration; // 100s
$db->exec("UPDATE city_market_trans SET time_start = " . ($pastArrival - $originalDuration) . ", time_arrive = {$pastArrival} WHERE id_trans = {$transId}");

// Resolve arrived transports
MarketController::resolveArrivedTransports($db, 10);

// Verify destination city received the cargo
$destCity = $db->query("SELECT food, wood, stone, metal, coin FROM city WHERE id_city = 20")->fetch(PDO::FETCH_ASSOC);
assertCondition((int)$destCity['food'] === 10000, 'Destination city credited with 10,000 food');
assertCondition((int)$destCity['wood'] === 20000, 'Destination city credited with 20,000 wood');
assertCondition((int)$destCity['stone'] === 5000, 'Destination city credited with 5,000 stone');
assertCondition((int)$destCity['metal'] === 2000, 'Destination city credited with 2,000 metal');
assertCondition((int)$destCity['coin'] === 1000, 'Destination city credited with 1,000 coin');

MarketController::resolveArrivedTransports($db, 10);
$destFoodAfterRepeatedResolution = (int)$db->query("SELECT food FROM city WHERE id_city = 20")->fetchColumn();
assertCondition($destFoodAfterRepeatedResolution === 10000, 'Repeated arrival resolution does not credit cargo twice');

// Verify transport transitioned to returning
$retTrans = $db->query("SELECT * FROM city_market_trans WHERE id_trans = {$transId}")->fetch(PDO::FETCH_ASSOC);
assertCondition($retTrans['status'] === 'returning', 'Transport transitioned to returning status');
assertCondition((int)$retTrans['acce'] === 0, 'Transport acce reset for return journey');
$expectedReturnTime = $pastArrival + $originalDuration;
assertCondition((int)$retTrans['time_arrive'] === $expectedReturnTime, "Return journey arrival time set to {$expectedReturnTime}");

// Destination city should no longer show returning porters in its incoming list
$_REQUEST['idCity'] = 20;
$destReturningList = null;
try {
    MarketController::getCityTransportResource($db);
} catch (MarketTransportJsonResponse $e) {
    $destReturningList = $e->payload;
}
assertCondition(empty($destReturningList), 'Destination city incoming list excludes returning porters');

// Origin city outgoing list still shows returning porters
$_REQUEST['idCity'] = 10;
$originReturningList = null;
try {
    MarketController::getCityTransportResource($db);
} catch (MarketTransportJsonResponse $e) {
    $originReturningList = $e->payload;
}
assertCondition(count($originReturningList) === 1 && $originReturningList[0]['status'] === 'returning', 'Origin city shows returning porters in outgoing list');

// --- 6. Test Return Journey Completion & Freeing Transport Slot ---
// Fast forward return arrival time into past
$pastReturn = time() - 10;
$db->exec("UPDATE city_market_trans SET time_arrive = {$pastReturn} WHERE id_trans = {$transId}");

MarketController::resolveArrivedTransports($db, 10);

$completedTrans = $db->query("SELECT status FROM city_market_trans WHERE id_trans = {$transId}")->fetch(PDO::FETCH_ASSOC);
assertCondition($completedTrans['status'] === 'completed', 'Transport marked completed upon return');

// Origin city active list is now empty
$originFinalList = null;
try {
    MarketController::getCityTransportResource($db);
} catch (MarketTransportJsonResponse $e) {
    $originFinalList = $e->payload;
}
assertCondition(empty($originFinalList), 'Origin city active transport list is empty after return');

// Active porters count drops to 0
$finalActiveCount = (int)$db->query("SELECT COUNT(*) FROM city_market_trans WHERE id_city_from = 10 AND status IN ('marching', 'returning')")->fetchColumn();
assertCondition($finalActiveCount === 0, 'Active transport slot freed: 0 active outgoing transports');

echo "\n=======================================================\n";
echo "MARKET RESOURCE TRANSPORT RESULTS: {$passed} / " . ($passed + $failed) . " tests passed.\n";
echo "=======================================================\n";

if ($failed > 0) {
    exit(1);
}
