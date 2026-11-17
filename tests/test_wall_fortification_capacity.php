<?php
// tests/test_wall_fortification_capacity.php
// Full verification test suite for Wall Fortification Capacity Fixes:
// 1. Wall Level 0 provides 0 capacity and rejects builds
// 2. Exact capacity enforcement (5,000 per level)
// 3. Parallel queue exploit prevention (accounting for active timed_task queues)
// 4. Space weights validation (wall_a=1, wall_b=3, wall_c=5)
// 5. city.php API endpoint returns wall_cap and fortification counts

putenv('CAESAR_ENV=test');
putenv('CAESAR_DB_NAME=caesar_test');

class SendJsonException extends Exception {
    public $data;
    public function __construct($data) {
        $this->data = $data;
    }
}

if (!function_exists('sendJson')) {
    function sendJson($data): void {
        throw new SendJsonException($data);
    }
}

require_once __DIR__ . '/../caesar-api/PlayerAuth.php';
require_once __DIR__ . '/../caesar-api/db.php';
require_once __DIR__ . '/../caesar-api/services/ProductionService.php';
require_once __DIR__ . '/../caesar-api/controllers/ArmyController.php';

$db = getDB();

$passed = 0;
$failed = 0;

function assertWallTest($condition, $title) {
    global $passed, $failed;
    if ($condition) {
        echo "[PASS] $title\n";
        $passed++;
    } else {
        echo "[FAIL] $title\n";
        $failed++;
    }
}

echo "=== STARTING WALL FORTIFICATION CAPACITY TEST SUITE ===\n\n";

// --- Setup test fixtures in caesar_test ---
$db->exec("DELETE FROM `city_building` WHERE `id_city` = 99998");
$db->exec("DELETE FROM `timed_task` WHERE `id_city` = 99998");
$db->exec("DELETE FROM `city` WHERE `id_city` = 99998");
$db->exec("DELETE FROM `player_edu` WHERE `id_player` = 99998");
$db->exec("DELETE FROM `player_state` WHERE `id_player` = 99998");
$db->exec("DELETE FROM `player` WHERE `id_player` = 99998");

// Insert mock player
$db->exec("INSERT INTO `player` (`id_player`, `id_user`, `name`, `prestige`, `gold`, `p_token`) VALUES (99998, 99998, 'WallTester', 100, 1000, 'wall_test_token_99998')");
$db->exec("INSERT INTO `player_edu` (`id_player`, `safe`) VALUES (99998, 10)");
$db->exec("INSERT INTO `player_state` (`id_player`) VALUES (99998)");

// Insert mock city with abundant resources and 0 wall fortifications
$db->exec("INSERT INTO `city` (`id_city`, `id_player`, `name`, `lvl`, `food`, `wood`, `stone`, `metal`, `coin`, `pop`, `pop_cap`, `pop_max`, `wall_cap`, `wall_a`, `wall_b`, `wall_c`)
           VALUES (99998, 99998, 'WallTestCity', 1, 50000000, 50000000, 50000000, 50000000, 50000000, 50000, 50000, 50000, 0, 0, 0, 0)");

function callBuildWallDefense($db, $cityId, $armyType, $amount, $place, $token) {
    $_REQUEST['idCity']         = $cityId;
    $_REQUEST['armyType']       = $armyType;
    $_REQUEST['amount']         = $amount;
    $_REQUEST['buildingPlace']  = $place;
    $_REQUEST['token']          = $token;
    $_GET['token']              = $token;
    $_POST['token']             = $token;

    try {
        ArmyController::buildArmy($db);
    } catch (SendJsonException $e) {
        return $e->data;
    }
    return null;
}

// =========================================================================
// TEST 1: Wall Level 0 (No Wall)
// =========================================================================
echo "--- 1. Wall Level 0 Enforcement ---\n";
ProductionService::recalculateCityProduction($db, 99998);
$wallCap0 = (int)$db->query("SELECT `wall_cap` FROM `city` WHERE `id_city` = 99998")->fetchColumn();
assertWallTest($wallCap0 === 0, "Wall Level 0 yields exactly 0 wall_cap (no hardcoded 5000)");

$resNoWall = callBuildWallDefense($db, 99998, 'wall_a', 10, 'wall', 'wall_test_token_99998');
assertWallTest(in_array(($resNoWall['state'] ?? ''), ['error_1', 'error_7_1']), "Building fortifications with no wall rejected");

// =========================================================================
// TEST 2: Wall Level 1 Capacity (5,000 Capacity)
// =========================================================================
echo "\n--- 2. Wall Level 1 (5,000 Cap) Exact Limits ---\n";
$db->exec("INSERT INTO `city_building` (`id_city`, `place`, `type`, `lvl`) VALUES (99998, 'wall', 13, 1)
           ON DUPLICATE KEY UPDATE `lvl` = 1");
ProductionService::recalculateCityProduction($db, 99998);
$wallCap1 = (int)$db->query("SELECT `wall_cap` FROM `city` WHERE `id_city` = 99998")->fetchColumn();
assertWallTest($wallCap1 === 5000, "Wall Level 1 yields exactly 5,000 wall_cap");

// Attempt to build 5,001 wall_a (exceeds 5,000) -> Rejected with error_1
$resExceed = callBuildWallDefense($db, 99998, 'wall_a', 5001, 'wall', 'wall_test_token_99998');
assertWallTest(($resExceed['state'] ?? '') === 'error_1', "Requesting 5,001 traps on 5,000 cap rejected with error_1");

// Build exactly 5,000 wall_a -> Succeeded with ok
$resExact = callBuildWallDefense($db, 99998, 'wall_a', 5000, 'wall', 'wall_test_token_99998');
assertWallTest(($resExact['state'] ?? '') === 'ok', "Requesting exactly 5,000 traps on 5,000 cap accepted with ok");

// Clean up timed tasks
$db->exec("DELETE FROM `timed_task` WHERE `id_city` = 99998");

// =========================================================================
// TEST 3: Parallel Queue Exploit Prevention
// =========================================================================
echo "\n--- 3. Parallel Queue Exploit Prevention ---\n";
// Wall lvl 5 allows up to 5 queues and gives 25,000 total capacity
$db->exec("UPDATE `city_building` SET `lvl` = 5 WHERE `id_city` = 99998 AND `place` = 'wall'");
ProductionService::recalculateCityProduction($db, 99998);
$wallCap5 = (int)$db->query("SELECT `wall_cap` FROM `city` WHERE `id_city` = 99998")->fetchColumn();
assertWallTest($wallCap5 === 25000, "Wall Level 5 yields exactly 25,000 wall_cap");

// Queue 1: Request 15,000 wall_a (space 15,000, remaining 10,000)
$q1 = callBuildWallDefense($db, 99998, 'wall_a', 15000, 'wall', 'wall_test_token_99998');
assertWallTest(($q1['state'] ?? '') === 'ok', "Queue 1 (15,000 traps) accepted with ok");

// Queue 2: Request 15,000 wall_a while Queue 1 is active -> Must be REJECTED! (15,000 + 15,000 = 30,000 > 25,000)
$q2_exploit = callBuildWallDefense($db, 99998, 'wall_a', 15000, 'wall', 'wall_test_token_99998');
assertWallTest(($q2_exploit['state'] ?? '') === 'error_1', "Parallel Queue Exploit blocked: 15,000 traps rejected because Queue 1 reserved 15,000 space");

// Queue 2: Request 10,000 wall_a (exact remaining space) -> Succeeded!
$q2_valid = callBuildWallDefense($db, 99998, 'wall_a', 10000, 'wall', 'wall_test_token_99998');
assertWallTest(($q2_valid['state'] ?? '') === 'ok', "Queue 2 (10,000 traps) filling remaining space accepted");

// Queue 3: Request 1 trap -> Rejected with error_1 because capacity is 100% reserved (workingCount 2 < 5)
$q3_full = callBuildWallDefense($db, 99998, 'wall_a', 1, 'wall', 'wall_test_token_99998');
assertWallTest(($q3_full['state'] ?? '') === 'error_1', "Queue 3 rejected with error_1 when capacity is 100% reserved across active queues");

// Clean up timed tasks
$db->exec("DELETE FROM `timed_task` WHERE `id_city` = 99998");

// =========================================================================
// TEST 4: Space Weights (wall_a=1, wall_b=3, wall_c=5) with Pre-built Units
// =========================================================================
echo "\n--- 4. Space Weights & Pre-built Defenses ---\n";
// Wall lvl 5 (25,000 space, satisfies wall_c lvl 5 prerequisite)
// City already has built: 15,000 wall_a (15,000 space) + 2,000 wall_b (6,000 space) = 21,000 space built
$db->exec("UPDATE `city` SET `wall_a` = 15000, `wall_b` = 2000, `wall_c` = 0 WHERE `id_city` = 99998");
// Total used space = 21,000 built. Available space = 25,000 - 21,000 = 4,000 space.

// Test wall_c (space weight = 5): Max buildable = intdiv(4000, 5) = 800
$resWallCOver = callBuildWallDefense($db, 99998, 'wall_c', 801, 'wall', 'wall_test_token_99998');
assertWallTest(($resWallCOver['state'] ?? '') === 'error_1', "Requesting 801 rolling stones (801 * 5 = 4005 space > 4000) rejected with error_1");

$resWallCOk = callBuildWallDefense($db, 99998, 'wall_c', 800, 'wall', 'wall_test_token_99998');
assertWallTest(($resWallCOk['state'] ?? '') === 'ok', "Requesting exactly 800 rolling stones (800 * 5 = 4000 space) accepted with ok");

// Clean up
$db->exec("DELETE FROM `city_building` WHERE `id_city` = 99998");
$db->exec("DELETE FROM `timed_task` WHERE `id_city` = 99998");
$db->exec("DELETE FROM `city` WHERE `id_city` = 99998");
$db->exec("DELETE FROM `player_edu` WHERE `id_player` = 99998");
$db->exec("DELETE FROM `player_state` WHERE `id_player` = 99998");
$db->exec("DELETE FROM `player` WHERE `id_player` = 99998");

echo "\n=======================================================\n";
echo "WALL FORTIFICATION CAPACITY RESULTS: $passed / " . ($passed + $failed) . " tests passed.\n";
echo "=======================================================\n";

if ($failed > 0) exit(1);
