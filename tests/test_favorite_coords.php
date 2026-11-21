<?php
// tests/test_favorite_coords.php
// Full Integration Test Suite for Favorite Coordinates (مفضلة الإحداثيات):
// 1. Fetching favorite coordinates list
// 2. Adding favorite coordinate with boundary & own-city checks
// 3. Modifying favorite coordinate title
// 4. Deleting favorite coordinate
// 5. 50 entries limit and duplicate coordinate prevention

class FavoriteCoordsJsonResponse extends RuntimeException {
    public $payload;
    public function __construct($payload) {
        $this->payload = $payload;
    }
}

if (!function_exists('sendJson')) {
    function sendJson($data): void {
        throw new FavoriteCoordsJsonResponse($data);
    }
}

require_once __DIR__ . '/../caesar-api/PlayerAuth.php';
require_once __DIR__ . '/../caesar-api/controllers/PlayerController.php';

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
        `y` INTEGER NOT NULL
    );

    CREATE TABLE `player_favorite_coord` (
        `id_fav` INTEGER PRIMARY KEY AUTOINCREMENT,
        `id_player` INTEGER NOT NULL,
        `title` TEXT NOT NULL,
        `x` INTEGER NOT NULL,
        `y` INTEGER NOT NULL,
        `created_at` TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );
");

// Seed test fixtures:
// Player 1 with City at (100, 100)
$db->exec("INSERT INTO `player` VALUES (1, 'CaesarTestPlayer', 'test_fav_token')");
$db->exec("INSERT INTO `player` VALUES (2, 'OtherTestPlayer', 'other_fav_token')");
$db->exec("INSERT INTO `city` VALUES (10, 1, 'CapitalCity', 100, 100)");
$db->exec("INSERT INTO `city` VALUES (11, 1, 'SecondCity', 110, 110)");
$db->exec("INSERT INTO `city` VALUES (20, 2, 'OtherPlayerCity', 120, 120)");

$_GET['token'] = 'test_fav_token';
$_POST['token'] = 'test_fav_token';
$_REQUEST['id_city'] = 10;

echo "=== STARTING FAVORITE COORDINATES TEST SUITE ===\n\n";

// --- 1. Initially Empty List ---
$initialList = null;
try {
    PlayerController::getFavoriteCoords($db);
} catch (FavoriteCoordsJsonResponse $e) {
    $initialList = $e->payload;
}
assertCondition($initialList !== null && $initialList['state'] === 'ok', 'getFavoriteCoords succeeded');
assertCondition(empty($initialList['list']), 'Initial favorites list is empty');

// --- 2. Add Favorite Coordinate ---
// Attempt to add own city coordinate -> should be rejected with error_own_city
$_REQUEST['x'] = 100;
$_REQUEST['y'] = 100;
$_REQUEST['title'] = 'My Own City';
$ownCityPayload = null;
try {
    PlayerController::addFavoriteCoord($db);
} catch (FavoriteCoordsJsonResponse $e) {
    $ownCityPayload = $e->payload;
}
assertCondition($ownCityPayload !== null && $ownCityPayload['state'] === 'error_own_city', 'Adding own city coordinate rejected with error_own_city');

$_REQUEST['id_city'] = 20;
$_REQUEST['x'] = 130;
$_REQUEST['y'] = 130;
$foreignCurrentCityPayload = null;
try {
    PlayerController::addFavoriteCoord($db);
} catch (FavoriteCoordsJsonResponse $e) {
    $foreignCurrentCityPayload = $e->payload;
}
assertCondition(
    $foreignCurrentCityPayload !== null && $foreignCurrentCityPayload['state'] === 'error_auth',
    'A city owned by another player cannot be claimed as the current city'
);
$_REQUEST['id_city'] = 10;

// A city other than the currently selected city can be saved as a favorite.
$_REQUEST['x'] = 110;
$_REQUEST['y'] = 110;
$_REQUEST['title'] = 'Second City';
$otherOwnedCityPayload = null;
try {
    PlayerController::addFavoriteCoord($db);
} catch (FavoriteCoordsJsonResponse $e) {
    $otherOwnedCityPayload = $e->payload;
}
assertCondition(
    $otherOwnedCityPayload !== null && $otherOwnedCityPayload['state'] === 'ok',
    'A non-current city owned by the player can be saved as a favorite'
);
$otherOwnedFavId = (int)$otherOwnedCityPayload['id_fav'];
$_REQUEST['id_fav'] = $otherOwnedFavId;
$removeOtherOwnedCityPayload = null;
try {
    PlayerController::deleteFavoriteCoord($db);
} catch (FavoriteCoordsJsonResponse $e) {
    $removeOtherOwnedCityPayload = $e->payload;
}
assertCondition(
    $removeOtherOwnedCityPayload !== null && $removeOtherOwnedCityPayload['state'] === 'ok',
    'Temporary non-current city favorite can be removed'
);

// Attempt to add invalid coordinate (< 0 or > 499)
$_REQUEST['x'] = 550;
$_REQUEST['y'] = 200;
$_REQUEST['title'] = 'Out of Bounds';
$invalidCoordPayload = null;
try {
    PlayerController::addFavoriteCoord($db);
} catch (FavoriteCoordsJsonResponse $e) {
    $invalidCoordPayload = $e->payload;
}
assertCondition($invalidCoordPayload !== null && $invalidCoordPayload['state'] === 'error_coord', 'Out of bounds coordinate rejected with error_coord');

// Add a valid coordinate (Rebel Camp at 136, 160)
$_REQUEST['x'] = 136;
$_REQUEST['y'] = 160;
$_REQUEST['title'] = 'المعسكر الفرنسي المتمرد';
$addPayload = null;
try {
    PlayerController::addFavoriteCoord($db);
} catch (FavoriteCoordsJsonResponse $e) {
    $addPayload = $e->payload;
}
assertCondition($addPayload !== null && $addPayload['state'] === 'ok', 'Valid coordinate added to favorites successfully');
$favId = (int)($addPayload['id_fav'] ?? 0);
assertCondition($favId > 0, "Favorite coordinate assigned ID {$favId}");
assertCondition(count($addPayload['list']) === 1, 'Favorites list contains 1 item');
assertCondition($addPayload['list'][0]['title'] === 'المعسكر الفرنسي المتمرد', 'Title stored accurately');
assertCondition($addPayload['list'][0]['x'] === 136 && $addPayload['list'][0]['y'] === 160, 'Coordinates stored accurately (136, 160)');

// Attempt duplicate addition of same coordinate -> should fail with error_exists
$dupPayload = null;
try {
    PlayerController::addFavoriteCoord($db);
} catch (FavoriteCoordsJsonResponse $e) {
    $dupPayload = $e->payload;
}
assertCondition($dupPayload !== null && $dupPayload['state'] === 'error_exists', 'Duplicate coordinate addition rejected with error_exists');

// --- 3. Modify Favorite Coordinate Title ---
$_REQUEST['id_fav'] = $favId;
$_REQUEST['title'] = 'المعسكر الفرنسي - تم الاستطلاع';
$modifyPayload = null;
try {
    PlayerController::modifyFavoriteCoordTitle($db);
} catch (FavoriteCoordsJsonResponse $e) {
    $modifyPayload = $e->payload;
}
assertCondition($modifyPayload !== null && $modifyPayload['state'] === 'ok', 'modifyFavoriteCoordTitle succeeded');
assertCondition($modifyPayload['title'] === 'المعسكر الفرنسي - تم الاستطلاع', 'Updated title returned correctly');

$updatedTitleInDb = $db->query("SELECT title FROM player_favorite_coord WHERE id_fav = {$favId}")->fetchColumn();
assertCondition($updatedTitleInDb === 'المعسكر الفرنسي - تم الاستطلاع', 'Title updated in database');

// --- 4. Limit Test (Max 50 Favorites) ---
// Insert 49 more favorites to reach 50
for ($i = 1; $i <= 49; $i++) {
    $db->exec("INSERT INTO player_favorite_coord (id_player, title, x, y) VALUES (1, 'Camp {$i}', {$i}, {$i})");
}
$countBefore = (int)$db->query("SELECT COUNT(*) FROM player_favorite_coord WHERE id_player = 1")->fetchColumn();
assertCondition($countBefore === 50, "Player now has exactly {$countBefore} favorites (maximum capacity)");

// Attempt 51st favorite
$_REQUEST['x'] = 300;
$_REQUEST['y'] = 300;
$_REQUEST['title'] = '51st Favorite';
$limitPayload = null;
try {
    PlayerController::addFavoriteCoord($db);
} catch (FavoriteCoordsJsonResponse $e) {
    $limitPayload = $e->payload;
}
assertCondition($limitPayload !== null && $limitPayload['state'] === 'error_limit', '51st favorite rejected with error_limit');

// --- 5. Delete Favorite Coordinate ---
$_REQUEST['id_fav'] = $favId;
$delPayload = null;
try {
    PlayerController::deleteFavoriteCoord($db);
} catch (FavoriteCoordsJsonResponse $e) {
    $delPayload = $e->payload;
}
assertCondition($delPayload !== null && $delPayload['state'] === 'ok', 'deleteFavoriteCoord succeeded');
$countAfterDel = (int)$db->query("SELECT COUNT(*) FROM player_favorite_coord WHERE id_player = 1")->fetchColumn();
assertCondition($countAfterDel === 49, 'Favorites count decreased to 49 after deletion');

// Now adding a new favorite should succeed since count < 50
$_REQUEST['x'] = 300;
$_REQUEST['y'] = 300;
$_REQUEST['title'] = 'Newly Allowed Favorite';
$allowedPayload = null;
try {
    PlayerController::addFavoriteCoord($db);
} catch (FavoriteCoordsJsonResponse $e) {
    $allowedPayload = $e->payload;
}
assertCondition($allowedPayload !== null && $allowedPayload['state'] === 'ok', 'Adding favorite succeeds once capacity freed');

echo "\n=======================================================\n";
echo "FAVORITE COORDINATES RESULTS: {$passed} / " . ($passed + $failed) . " tests passed.\n";
echo "=======================================================\n";

if ($failed > 0) {
    exit(1);
}
