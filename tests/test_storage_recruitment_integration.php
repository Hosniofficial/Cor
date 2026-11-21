<?php
// test_storage_recruitment_integration.php
// Full Integration Test Suite for:
// 1. Shared storage capacity and ratio-based resource caps
// 2. Storage Ratio Validation
// 3. Recruitment Queue Limits (min(10, building_lvl))
// 4. Resource & Population Deductions & Row Locking

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
require_once __DIR__ . '/../caesar-api/controllers/CityController.php';
require_once __DIR__ . '/../caesar-api/controllers/ArmyController.php';

$db = getDB();

$passed = 0;
$failed = 0;

function assertTest($condition, $title) {
    global $passed, $failed;
    if ($condition) {
        echo "[PASS] $title\n";
        $passed++;
    } else {
        echo "[FAIL] $title\n";
        $failed++;
    }
}

echo "=== STARTING STORAGE & RECRUITMENT INTEGRATION TEST SUITE ===\n\n";

// --- Setup Test Fixtures in caesar_test ---
$db->exec("DELETE FROM `city_building` WHERE `id_city` = 99999");
$db->exec("DELETE FROM `timed_task` WHERE `id_city` = 99999");
$db->exec("DELETE FROM `army_training` WHERE `id_city` = 99999");
$db->exec("DELETE FROM `city_storage` WHERE `id_city` = 99999");
$db->exec("DELETE FROM `city_jop` WHERE `id_city` = 99999");
$db->exec("DELETE FROM `city` WHERE `id_city` = 99999");
$db->exec("DELETE FROM `player_edu` WHERE `id_player` = 99999");
$db->exec("DELETE FROM `player_state` WHERE `id_player` = 99999");
$db->exec("DELETE FROM `player` WHERE `id_player` = 99999");

// Insert mock player
$db->exec("INSERT INTO `player` (`id_player`, `id_user`, `name`, `prestige`, `gold`, `p_token`) VALUES (99999, 99999, 'Tester', 100, 1000, 'test_token_99999')");
$db->exec("INSERT INTO `player_edu` (`id_player`, `infantry`, `riding`, `army`, `safe`) VALUES (99999, 10, 10, 10, 10)");
$db->exec("INSERT INTO `player_state` (`id_player`) VALUES (99999)");

// Insert mock city with abundant resources and population
$db->exec("INSERT INTO `city` (`id_city`, `id_player`, `name`, `lvl`, `food`, `wood`, `stone`, `metal`, `coin`, `pop`, `pop_cap`, `pop_max`, `wall_cap`)
           VALUES (99999, 99999, 'TestCity', 1, 10000000, 10000000, 10000000, 10000000, 10000000, 50000, 50000, 50000, 50000)");

// =========================================================================
// SECTION 1: Shared Storage Capacity
// =========================================================================
echo "--- 1. Shared Storage Capacity ---\n";

$storageData = ProductionService::getCityStorageData($db, 99999);
assertTest($storageData['total_cap'] === 0, "City without a store has no warehouse capacity");
assertTest(
    $storageData['food_cap'] === 0
    && $storageData['wood_cap'] === 0
    && $storageData['stone_cap'] === 0
    && $storageData['metal_cap'] === 0,
    "Without warehouse capacity, each resource cap is zero"
);

$stmtStore = $db->prepare("INSERT INTO `city_building` (`id_city`, `place`, `type`, `lvl`) VALUES (99999, 'store_1', 2, ?)
                           ON DUPLICATE KEY UPDATE `lvl` = VALUES(`lvl`)");

$stmtStore->execute([30]);
$storageData = ProductionService::getCityStorageData($db, 99999);
assertTest(
    $storageData['food_cap'] === 22016000 && $storageData['total_cap'] === 88064000,
    "Level 30 warehouse capacity is divided into four equal resource shares"
);

// =========================================================================
// SECTION 2: Storage Ratios & Capacity Distribution
// =========================================================================
echo "\n--- 2. Storage Ratios & Caps Distribution ---\n";

// Set store at lvl 10 (40,960,000 shared capacity).
$stmtStore->execute([10]);

// Helper function to test updatePercentage
function callUpdatePercentage($db, $cityId, $foodP, $woodP, $stoneP, $metalP) {
    $_REQUEST['idCity']    = $cityId;
    $_REQUEST['foodPerc']  = $foodP;
    $_REQUEST['woodPerc']  = $woodP;
    $_REQUEST['stonePerc'] = $stoneP;
    $_REQUEST['metalPerc'] = $metalP;

    try {
        CityController::updatePercentage($db);
    } catch (SendJsonException $e) {
        return $e->data;
    }
    return null;
}

// A. Valid equal ratios (25% each)
$res = callUpdatePercentage($db, 99999, 25, 25, 25, 25);
assertTest(($res['state'] ?? '') === 'ok', "Valid ratios (25, 25, 25, 25) accepted with state ok");
$cityRow = $db->query("SELECT food_cap, wood_cap, stone_cap, metal_cap FROM `city` WHERE `id_city` = 99999")->fetch(PDO::FETCH_ASSOC);
assertTest(
    (int)$cityRow['food_cap'] === 10240000 && (int)$cityRow['wood_cap'] === 10240000,
    "Default 25% ratios allocate 10,240,000 capacity to each resource"
);

// B. Valid custom ratios: 40% Food, 30% Wood, 20% Stone, 10% Metal (Sum = 100%)
$res = callUpdatePercentage($db, 99999, 40, 30, 20, 10);
assertTest(($res['state'] ?? '') === 'ok', "Custom ratios (40, 30, 20, 10) accepted with state ok");
$cityRow = $db->query("SELECT food_cap, wood_cap, stone_cap, metal_cap FROM `city` WHERE `id_city` = 99999")->fetch(PDO::FETCH_ASSOC);
assertTest((int)$cityRow['food_cap'] === 16384000 &&
           (int)$cityRow['wood_cap'] === 12288000 &&
           (int)$cityRow['stone_cap'] === 8192000 &&
           (int)$cityRow['metal_cap'] === 4096000, "Custom ratios divide shared capacity exactly");

// C. Invalid ratios: Sum > 100% -> must reject with error_0
$res = callUpdatePercentage($db, 99999, 50, 50, 10, 0); // 110%
assertTest(($res['state'] ?? '') === 'error_0', "Ratios summing to 110% rejected with error_0");

// D. Invalid ratios: Negative or out of bounds -> must reject with error_1
$res = callUpdatePercentage($db, 99999, -5, 50, 25, 25);
assertTest(($res['state'] ?? '') === 'error_1', "Negative ratio rejected with error_1");

$res = callUpdatePercentage($db, 99999, 105, 0, 0, 0);
assertTest(($res['state'] ?? '') === 'error_1', "Ratio > 100 rejected with error_1");

// =========================================================================
// SECTION 3: Recruitment Queue Limits (min(10, building_lvl))
// =========================================================================
echo "\n--- 3. Recruitment Queue Limits Verification ---\n";

// Set up barracks at place 'barracks_1' (type = 3)
$stmtBarracks = $db->prepare("INSERT INTO `city_building` (`id_city`, `place`, `type`, `lvl`) VALUES (99999, 'barracks_1', 3, ?)
                              ON DUPLICATE KEY UPDATE `lvl` = VALUES(`lvl`)");

function callBuildArmy($db, $cityId, $armyType, $amount, $place, $token, $divideBy = 'none') {
    $_REQUEST['idCity']         = $cityId;
    $_REQUEST['armyType']       = $armyType;
    $_REQUEST['amount']         = $amount;
    $_REQUEST['buildingPlace']  = $place;
    $_REQUEST['divideBy']       = $divideBy;
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

// A. Barracks Level 1: Allows exactly 1 queue, rejects 2nd
$stmtBarracks->execute([1]);
$db->exec("DELETE FROM `timed_task` WHERE `id_city` = 99999");

$res1 = callBuildArmy($db, 99999, 'army_a', 10, 'barracks_1', 'test_token_99999');
assertTest(($res1['state'] ?? '') === 'ok', "Batch 1 on Lvl 1 Barracks succeeds with state ok");
$firstRecruitment = $db->query("SELECT `start_time`, `time_arrive` FROM `timed_task` WHERE `id_city` = 99999 ORDER BY `id_task` DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
assertTest(
    (int)$firstRecruitment['time_arrive'] - (int)$firstRecruitment['start_time'] === 500,
    "Recruitment ETA uses 50 seconds per army_a unit"
);

$res2 = callBuildArmy($db, 99999, 'army_a', 10, 'barracks_1', 'test_token_99999');
assertTest(($res2['state'] ?? '') === 'error_4', "Batch 2 on Lvl 1 Barracks blocked by queue limit with error_4");

// B. Barracks Level 3: Allows 3 queues, rejects 4th
$stmtBarracks->execute([3]);
$res3 = callBuildArmy($db, 99999, 'army_a', 10, 'barracks_1', 'test_token_99999');
assertTest(($res3['state'] ?? '') === 'ok', "Batch 2 on upgraded Lvl 3 Barracks succeeds");
$res4 = callBuildArmy($db, 99999, 'army_a', 10, 'barracks_1', 'test_token_99999');
assertTest(($res4['state'] ?? '') === 'ok', "Batch 3 on upgraded Lvl 3 Barracks succeeds");

$res5 = callBuildArmy($db, 99999, 'army_a', 10, 'barracks_1', 'test_token_99999');
assertTest(($res5['state'] ?? '') === 'error_4', "Batch 4 on Lvl 3 Barracks blocked by queue limit with error_4");

// C. Barracks Level 15: Capped at absolute 10 queues (min(10, 15) = 10)
$stmtBarracks->execute([15]);
$db->exec("DELETE FROM `timed_task` WHERE `id_city` = 99999");
for ($i = 1; $i <= 10; $i++) {
    $r = callBuildArmy($db, 99999, 'army_a', 5, 'barracks_1', 'test_token_99999');
    if (($r['state'] ?? '') !== 'ok') {
        assertTest(false, "Batch $i on Lvl 15 Barracks failed unexpectedly: " . json_encode($r));
        break;
    }
}
assertTest($i === 11, "Successfully queued 10 batches on Lvl 15 Barracks");

$resOver10 = callBuildArmy($db, 99999, 'army_a', 5, 'barracks_1', 'test_token_99999');
assertTest(($resOver10['state'] ?? '') === 'error_4', "Batch 11 on Lvl 15 Barracks blocked by absolute 10 queue limit");

// Recruitment durations match the raw per-unit timing: 1,000 infantry take 50,000 seconds.
$db->exec("DELETE FROM `timed_task` WHERE `id_city` = 99999");
$stmtBarracks->execute([1]);
$db->exec("UPDATE `city` SET `pop` = 50000, `pop_cap` = 1000000, `pop_max` = 1000000 WHERE `id_city` = 99999");
$resLongBatch = callBuildArmy($db, 99999, 'army_a', 1000, 'barracks_1', 'test_token_99999');
$longBatch = $db->query("SELECT `start_time`, `time_arrive` FROM `timed_task` WHERE `id_city` = 99999 ORDER BY `id_task` DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$longBatchDuration = $longBatch ? (int)$longBatch['time_arrive'] - (int)$longBatch['start_time'] : null;
assertTest(
    ($resLongBatch['state'] ?? '') === 'ok'
    && $longBatchDuration === 50000,
    "Recruiting 1,000 infantry keeps the full 50,000-second duration (state: "
        . ($resLongBatch['state'] ?? 'no response') . ', duration: ' . ($longBatchDuration ?? 'n/a') . ')'
);
$db->exec("DELETE FROM `timed_task` WHERE `id_city` = 99999");
$db->exec("DELETE FROM `army_training` WHERE `id_city` = 99999");
$stmtBarracks->execute([15]);
$db->exec("UPDATE `city` SET `pop_cap` = 50000, `pop_max` = 50000 WHERE `id_city` = 99999");

$db->exec("INSERT INTO `city_building` (`id_city`, `place`, `type`, `lvl`) VALUES
           (99999, 'barracks_2', 3, 3),
           (99999, 'barracks_3', 3, 5)
           ON DUPLICATE KEY UPDATE `lvl` = VALUES(`lvl`)");
$amountSplitResponse = callBuildArmy(
    $db,
    99999,
    'army_a',
    10,
    'barracks_1',
    'test_token_99999',
    'amount'
);
$amountSplit = [];
foreach (($amountSplitResponse['armyBatches'] ?? []) as $batch) {
    $amountSplit[$batch['place']] = (int)$batch['amount'];
}
assertTest(
    ($amountSplitResponse['state'] ?? '') === 'ok'
    && $amountSplit === ['barracks_1' => 4, 'barracks_2' => 3, 'barracks_3' => 3],
    "Quantity mode distributes troop batches evenly across eligible barracks"
);
$db->exec("DELETE FROM `timed_task` WHERE `id_city` = 99999");
$db->exec("DELETE FROM `army_training` WHERE `id_city` = 99999");

$now = time();
$db->prepare(
    "INSERT INTO `timed_task` (`id_city`, `id_player`, `task_type`, `item_id`, `lvl_to`, `start_time`, `time_arrive`, `details`)
     VALUES (99999, 99999, 'army', 'army_a', 1, ?, ?, ?)"
)->execute([$now, $now + 3600, json_encode(['place' => 'barracks_2'])]);
$timeSplitResponse = callBuildArmy(
    $db,
    99999,
    'army_a',
    6,
    'barracks_1',
    'test_token_99999',
    'time'
);
$timeSplit = [];
foreach (($timeSplitResponse['armyBatches'] ?? []) as $batch) {
    $timeSplit[$batch['place']] = (int)$batch['amount'];
}
assertTest(
    ($timeSplitResponse['state'] ?? '') === 'ok'
    && $timeSplit === ['barracks_1' => 3, 'barracks_3' => 3],
    "Time mode avoids a busy barracks and distributes work across free barracks"
);
$taskCountBeforeNoFreeBarracks = (int)$db->query(
    "SELECT COUNT(*) FROM `timed_task` WHERE `id_city` = 99999"
)->fetchColumn();
$noFreeBarracksResponse = callBuildArmy(
    $db,
    99999,
    'army_a',
    1,
    'barracks_1',
    'test_token_99999',
    'time'
);
$taskCountAfterNoFreeBarracks = (int)$db->query(
    "SELECT COUNT(*) FROM `timed_task` WHERE `id_city` = 99999"
)->fetchColumn();
assertTest(
    ($noFreeBarracksResponse['state'] ?? '') === 'error_no_available_building'
    && $taskCountAfterNoFreeBarracks === $taskCountBeforeNoFreeBarracks,
    "Time mode rejects production when every eligible barracks is busy"
);
$db->exec("DELETE FROM `timed_task` WHERE `id_city` = 99999");
$db->exec("DELETE FROM `army_training` WHERE `id_city` = 99999");

// Wall fortification durations use their own raw per-unit timings.
$db->exec("INSERT INTO `city_building` (`id_city`, `place`, `type`, `lvl`) VALUES (99999, 'wall_1', 13, 5)
           ON DUPLICATE KEY UPDATE `lvl` = VALUES(`lvl`)");
foreach (['wall_a' => 600, 'wall_b' => 1800, 'wall_c' => 6000] as $wallType => $expectedDuration) {
    $wallRecruitment = callBuildArmy($db, 99999, $wallType, 10, 'wall_1', 'test_token_99999');
    $wallBatch = $db->query(
        "SELECT `start_time`, `time_arrive` FROM `timed_task`
         WHERE `id_city` = 99999 AND `item_id` = " . $db->quote($wallType) . "
         ORDER BY `id_task` DESC LIMIT 1"
    )->fetch(PDO::FETCH_ASSOC);
    $wallDuration = $wallBatch
        ? (int)$wallBatch['time_arrive'] - (int)$wallBatch['start_time']
        : null;
    assertTest(
        ($wallRecruitment['state'] ?? '') === 'ok' && $wallDuration === $expectedDuration,
        "$wallType batches use the full {$expectedDuration}-second raw duration"
    );
}
$db->exec("DELETE FROM `timed_task` WHERE `id_city` = 99999");
$db->exec("DELETE FROM `army_training` WHERE `id_city` = 99999");
$db->exec("DELETE FROM `city_building` WHERE `id_city` = 99999 AND `place` = 'wall_1'");

// =========================================================================
// SECTION 4: Resource & Population Verification & Concurrency
// =========================================================================
echo "\n--- 4. Resource & Population Deductions ---\n";

$db->exec("DELETE FROM `timed_task` WHERE `id_city` = 99999");
$growthStart = time() - 720;
$db->exec("UPDATE `city` SET `loy` = 100, `pop` = 0, `pop_LSS` = $growthStart WHERE `id_city` = 99999");
ProductionService::recalculateCityProduction($db, 99999);
$grownPop = (int)$db->query("SELECT `pop` FROM `city` WHERE `id_city` = 99999")->fetchColumn();
assertTest($grownPop === 98, "Population at zero grows 5% toward its maximum each 360-second tick");

$db->exec("UPDATE `city` SET `pop` = 500 WHERE `id_city` = 99999");
$db->exec("UPDATE `city` SET `pop` = 500, `food` = 100000, `wood` = 100000, `stone` = 100000, `metal` = 100000, `coin` = 100000 WHERE `id_city` = 99999");

// Army_a requires 1 population per soldier. 600 soldiers > 500 pop -> reject with error_2
$resPopFail = callBuildArmy($db, 99999, 'army_a', 600, 'barracks_1', 'test_token_99999');
assertTest(($resPopFail['state'] ?? '') === 'error_2', "Recruiting 600 troops with only 500 idle population rejected with error_2");

// Recruiting 30 soldiers succeeds and deducts exactly 30 population.
$resPopOk = callBuildArmy($db, 99999, 'army_a', 30, 'barracks_1', 'test_token_99999');
assertTest(($resPopOk['state'] ?? '') === 'ok', "Recruiting 30 troops within population succeeds");
$curPop = (int)$db->query("SELECT `pop` FROM `city` WHERE `id_city` = 99999")->fetchColumn();
assertTest($curPop === 470, "Production recalculation preserves the exact remaining population");

// Insufficient resources test: Army_a costs 150 food each (30 * 150 = 4500)
// Set food to 100
$db->exec("UPDATE `city` SET `food` = 100 WHERE `id_city` = 99999");
$resResFail = callBuildArmy($db, 99999, 'army_a', 10, 'barracks_1', 'test_token_99999');
assertTest(($resResFail['state'] ?? '') === 'error_5', "Recruiting with insufficient food rejected with error_5");

// A large stored cap is a test-only opt-in; production must use building-derived capacity.
$db->exec("UPDATE `city` SET `loy` = 100, `pop_cap` = 1000000000, `pop_max` = 1000000000, `pop` = 1000000000 WHERE `id_city` = 99999");
putenv('CAESAR_ENV=production');
ProductionService::recalculateCityProduction($db, 99999);
$productionPopCap = (int)$db->query("SELECT `pop_cap` FROM `city` WHERE `id_city` = 99999")->fetchColumn();
assertTest($productionPopCap === 1000, "Production ignores oversized stored population caps");

putenv('CAESAR_ENV=test');
$db->exec("UPDATE `city` SET `pop_cap` = 1000000000, `pop_max` = 1000000000, `pop` = 1000000000 WHERE `id_city` = 99999");
ProductionService::recalculateCityProduction($db, 99999);
$testPopulation = $db->query("SELECT `pop_cap`, `pop_max`, `pop` FROM `city` WHERE `id_city` = 99999")->fetch(PDO::FETCH_ASSOC);
assertTest(
    (int)$testPopulation['pop_cap'] === 1000000000
    && (int)$testPopulation['pop_max'] === 1000000000
    && (int)$testPopulation['pop'] === 1000000000,
    "Test database honors explicit oversized population caps"
);
putenv('CAESAR_ENV=test');

// Clean up test fixtures
$db->exec("DELETE FROM `city_building` WHERE `id_city` = 99999");
$db->exec("DELETE FROM `timed_task` WHERE `id_city` = 99999");
$db->exec("DELETE FROM `army_training` WHERE `id_city` = 99999");
$db->exec("DELETE FROM `city_storage` WHERE `id_city` = 99999");
$db->exec("DELETE FROM `city` WHERE `id_city` = 99999");
$db->exec("DELETE FROM `player_edu` WHERE `id_player` = 99999");
$db->exec("DELETE FROM `player_state` WHERE `id_player` = 99999");
$db->exec("DELETE FROM `player` WHERE `id_player` = 99999");

echo "\n=======================================================\n";
echo "STORAGE & RECRUITMENT INTEGRATION RESULTS: $passed / " . ($passed + $failed) . " tests passed.\n";
echo "=======================================================\n";

if ($failed > 0) exit(1);
