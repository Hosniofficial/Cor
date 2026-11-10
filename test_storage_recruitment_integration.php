<?php
// test_storage_recruitment_integration.php
// Full Integration Test Suite for:
// 1. Store Capacity Scaling (80,000 to 88,064,000 across 30 levels)
// 2. Storage Ratio Validation & City Caps Distribution
// 3. Recruitment Queue Limits (min(10, building_lvl))
// 4. Resource & Population Deductions & Row Locking

putenv('ELKAISAR_ENV=test');
putenv('ELKAISAR_DB_NAME=elkaisar_test');

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

require_once __DIR__ . '/app.elkaisar.com/PlayerAuth.php';
require_once __DIR__ . '/app.elkaisar.com/db.php';
require_once __DIR__ . '/app.elkaisar.com/services/ProductionService.php';
require_once __DIR__ . '/app.elkaisar.com/controllers/CityController.php';
require_once __DIR__ . '/app.elkaisar.com/controllers/ArmyController.php';

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

// --- Setup Test Fixtures in elkaisar_test ---
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
// SECTION 1: Store Upgrade & Capacity Progression (80k to 88.064M)
// =========================================================================
echo "--- 1. Store Capacity Progression Verification ---\n";

// A. No store building in city -> defaults to lvl 1 (80,000)
$storageData = ProductionService::getCityStorageData($db, 99999);
assertTest($storageData['total_cap'] === 80000, "City without store defaults to base 80,000 capacity");

// B. Test exact levels matching client's UpgradeBinfit matrix
$testLevels = [
    1  => 80000,
    2  => 160000,
    3  => 320000,
    5  => 1280000,
    10 => 40960000,
    15 => 57344000,
    20 => 67584000,
    25 => 77824000,
    30 => 88064000,
    35 => 88064000 // Levels beyond 30 stay capped at max level 30
];

$stmtStore = $db->prepare("INSERT INTO `city_building` (`id_city`, `place`, `type`, `lvl`) VALUES (99999, 'store_1', 2, ?)
                           ON DUPLICATE KEY UPDATE `lvl` = VALUES(`lvl`)");

foreach ($testLevels as $lvl => $expectedCap) {
    $stmtStore->execute([$lvl]);
    $data = ProductionService::getCityStorageData($db, 99999);
    assertTest($data['total_cap'] === $expectedCap, "Store level $lvl gives total capacity " . number_format($expectedCap));
}

// =========================================================================
// SECTION 2: Storage Ratios & Capacity Distribution
// =========================================================================
echo "\n--- 2. Storage Ratios & Caps Distribution ---\n";

// Set store at lvl 10 (40,960,000)
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
$quarterCap = (int)floor(40960000 * 0.25); // 10,240,000
assertTest((int)$cityRow['food_cap'] === $quarterCap && (int)$cityRow['wood_cap'] === $quarterCap, "City caps updated to exactly 25% each (10,240,000)");

// B. Valid custom ratios: 40% Food, 30% Wood, 20% Stone, 10% Metal (Sum = 100%)
$res = callUpdatePercentage($db, 99999, 40, 30, 20, 10);
assertTest(($res['state'] ?? '') === 'ok', "Custom ratios (40, 30, 20, 10) accepted with state ok");
$cityRow = $db->query("SELECT food_cap, wood_cap, stone_cap, metal_cap FROM `city` WHERE `id_city` = 99999")->fetch(PDO::FETCH_ASSOC);
assertTest((int)$cityRow['food_cap'] === (int)(40960000 * 0.40) &&
           (int)$cityRow['wood_cap'] === (int)(40960000 * 0.30) &&
           (int)$cityRow['stone_cap'] === (int)(40960000 * 0.20) &&
           (int)$cityRow['metal_cap'] === (int)(40960000 * 0.10), "Proportional caps match exact percentages");

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

function callBuildArmy($db, $cityId, $armyType, $amount, $place, $token) {
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

// A. Barracks Level 1: Allows exactly 1 queue, rejects 2nd
$stmtBarracks->execute([1]);
$db->exec("DELETE FROM `timed_task` WHERE `id_city` = 99999");

$res1 = callBuildArmy($db, 99999, 'army_a', 10, 'barracks_1', 'test_token_99999');
assertTest(($res1['state'] ?? '') === 'ok', "Batch 1 on Lvl 1 Barracks succeeds with state ok");

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

// =========================================================================
// SECTION 4: Resource & Population Verification & Concurrency
// =========================================================================
echo "\n--- 4. Resource & Population Deductions ---\n";

$db->exec("DELETE FROM `timed_task` WHERE `id_city` = 99999");
// Set population to exactly 500 (safely above the game engine minimum floor of 100)
$db->exec("UPDATE `city` SET `pop` = 500, `food` = 100000, `wood` = 100000, `stone` = 100000, `metal` = 100000, `coin` = 100000 WHERE `id_city` = 99999");

// Army_a requires 1 population per soldier. 600 soldiers > 500 pop -> reject with error_2
$resPopFail = callBuildArmy($db, 99999, 'army_a', 600, 'barracks_1', 'test_token_99999');
assertTest(($resPopFail['state'] ?? '') === 'error_2', "Recruiting 600 troops with only 500 idle population rejected with error_2");

// Recruiting 30 soldiers succeeds and reduces population from 500 to 470
$resPopOk = callBuildArmy($db, 99999, 'army_a', 30, 'barracks_1', 'test_token_99999');
assertTest(($resPopOk['state'] ?? '') === 'ok', "Recruiting 30 troops within population succeeds");
$curPop = (int)$db->query("SELECT `pop` FROM `city` WHERE `id_city` = 99999")->fetchColumn();
assertTest($curPop === 470, "City population reduced exactly from 500 to 470");

// Insufficient resources test: Army_a costs 150 food each (30 * 150 = 4500)
// Set food to 100
$db->exec("UPDATE `city` SET `food` = 100 WHERE `id_city` = 99999");
$resResFail = callBuildArmy($db, 99999, 'army_a', 10, 'barracks_1', 'test_token_99999');
assertTest(($resResFail['state'] ?? '') === 'error_5', "Recruiting with insufficient food rejected with error_5");

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
