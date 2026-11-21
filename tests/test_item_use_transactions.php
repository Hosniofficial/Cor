<?php

class ItemTransactionJsonResponse extends RuntimeException {
    public $payload;

    public function __construct($payload) {
        $this->payload = $payload;
    }
}

function sendJson($data): void {
    throw new ItemTransactionJsonResponse($data);
}

require_once __DIR__ . '/../caesar-api/PlayerAuth.php';
require_once __DIR__ . '/../caesar-api/controllers/ItemController.php';

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

function invokeItemAction(string $action, string $item): void {
    $_GET = [];
    $_POST = ['token' => 'transaction_test_token'];
    $_REQUEST = [
        'token' => 'transaction_test_token',
        'Item' => $item,
        'amount' => 1,
        'idCity' => 10,
    ];
    if (strpos($action, 'useArmyPack') === 0) {
        ItemController::handleArmyPack($action, $GLOBALS['db']);
        return;
    }
    ItemController::handleItemUse($action, $GLOBALS['db']);
}

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->sqliteCreateFunction('GREATEST', static function ($left, $right) {
    return max($left, $right);
});
$db->sqliteCreateFunction('LEAST', static function ($left, $right) {
    return min($left, $right);
});
$db->exec("
    CREATE TABLE player (
        id_player INTEGER PRIMARY KEY,
        name TEXT NOT NULL,
        p_token TEXT NOT NULL,
        gold INTEGER NOT NULL DEFAULT 0
    );
    CREATE TABLE player_state (
        id_player INTEGER PRIMARY KEY,
        peace INTEGER NOT NULL DEFAULT 0,
        attack_10 INTEGER NOT NULL DEFAULT 0,
        defence_10 INTEGER NOT NULL DEFAULT 0,
        motiv INTEGER NOT NULL DEFAULT 0,
        prot_pop INTEGER NOT NULL DEFAULT 0,
        medical INTEGER NOT NULL DEFAULT 0
    );
    CREATE TABLE city (
        id_city INTEGER PRIMARY KEY,
        id_player INTEGER NOT NULL,
        pop INTEGER NOT NULL DEFAULT 100,
        pop_max INTEGER NOT NULL DEFAULT 1000,
        loy INTEGER NOT NULL DEFAULT 50,
        dis_loy INTEGER NOT NULL DEFAULT 0,
        helper INTEGER NOT NULL DEFAULT 1,
        army_a INTEGER NOT NULL DEFAULT 0,
        army_b INTEGER NOT NULL DEFAULT 0,
        army_c INTEGER NOT NULL DEFAULT 0,
        army_d INTEGER NOT NULL DEFAULT 0,
        army_e INTEGER NOT NULL DEFAULT 0,
        army_f INTEGER NOT NULL DEFAULT 0
    );
    CREATE TABLE city_hero (id_hero INTEGER PRIMARY KEY, id_city INTEGER, in_city INTEGER);
    CREATE TABLE world_battel (state TEXT, id_city INTEGER, x_city INTEGER, y_city INTEGER);
    CREATE TABLE player_item (
        id_player INTEGER NOT NULL,
        id_item TEXT NOT NULL,
        amount INTEGER NOT NULL,
        PRIMARY KEY (id_player, id_item)
    );
");
$db->exec("INSERT INTO player VALUES (1, 'TestPlayer', 'transaction_test_token', 0)");
$db->exec("INSERT INTO player_state (id_player) VALUES (1)");
$db->exec("INSERT INTO city (id_city, id_player) VALUES (10, 1)");

$cases = [
    [
        'action' => 'useGoldPack',
        'item' => 'gold_pack',
        'trigger' => "CREATE TRIGGER fail_gold_update BEFORE UPDATE OF gold ON player BEGIN SELECT RAISE(ABORT, 'gold update failed'); END",
        'drop' => 'fail_gold_update',
        'effectMessage' => 'Gold-pack effect failure is surfaced',
        'itemMessage' => 'Gold pack is restored when its effect fails',
    ],
    [
        'action' => 'useAttackAdvancer',
        'item' => 'attack_boost',
        'trigger' => "CREATE TRIGGER fail_attack_update BEFORE UPDATE OF attack_10 ON player_state BEGIN SELECT RAISE(ABORT, 'attack update failed'); END",
        'drop' => 'fail_attack_update',
        'effectMessage' => 'Attack-boost effect failure is surfaced',
        'itemMessage' => 'Attack-boost item is restored when its effect fails',
    ],
    [
        'action' => 'useCeaseFire',
        'item' => 'peace_shield',
        'trigger' => "CREATE TRIGGER fail_peace_update BEFORE UPDATE OF peace ON player_state BEGIN SELECT RAISE(ABORT, 'peace update failed'); END",
        'drop' => 'fail_peace_update',
        'effectMessage' => 'Peace-shield effect failure is surfaced',
        'itemMessage' => 'Peace-shield item is restored when its effect fails',
    ],
    [
        'action' => 'useDefenceAdvancer',
        'item' => 'defence_boost',
        'trigger' => "CREATE TRIGGER fail_defence_update BEFORE UPDATE OF defence_10 ON player_state BEGIN SELECT RAISE(ABORT, 'defence update failed'); END",
        'drop' => 'fail_defence_update',
        'effectMessage' => 'Defence-boost effect failure is surfaced',
        'itemMessage' => 'Defence-boost item is restored when its effect fails',
    ],
    [
        'action' => 'useMotivSpeech',
        'item' => 'motiv_speech',
        'trigger' => "CREATE TRIGGER fail_motiv_update BEFORE UPDATE OF motiv ON player_state BEGIN SELECT RAISE(ABORT, 'motivation update failed'); END",
        'drop' => 'fail_motiv_update',
        'effectMessage' => 'Motivational-speech effect failure is surfaced',
        'itemMessage' => 'Motivational-speech item is restored when its effect fails',
    ],
    [
        'action' => 'useProtPop',
        'item' => 'population_boost',
        'trigger' => "CREATE TRIGGER fail_population_update BEFORE UPDATE OF pop ON city BEGIN SELECT RAISE(ABORT, 'population update failed'); END",
        'drop' => 'fail_population_update',
        'effectMessage' => 'Population effect failure is surfaced',
        'itemMessage' => 'Population item is restored when its effect fails',
    ],
    [
        'action' => 'useMedicalStatue',
        'item' => 'medical_statue',
        'trigger' => "CREATE TRIGGER fail_medical_update BEFORE UPDATE OF medical ON player_state BEGIN SELECT RAISE(ABORT, 'medical update failed'); END",
        'drop' => 'fail_medical_update',
        'effectMessage' => 'Medical-statue effect failure is surfaced',
        'itemMessage' => 'Medical-statue item is restored when its effect fails',
    ],
    [
        'action' => 'useTheatrics',
        'item' => 'theatrics',
        'trigger' => "CREATE TRIGGER fail_theatrics_update BEFORE UPDATE OF loy ON city BEGIN SELECT RAISE(ABORT, 'loyalty update failed'); END",
        'drop' => 'fail_theatrics_update',
        'effectMessage' => 'Theatrics effect failure is surfaced',
        'itemMessage' => 'Theatrics item is restored when its effect fails',
    ],
    [
        'action' => 'useFreedomHelp',
        'item' => 'freedom_help',
        'trigger' => "CREATE TRIGGER fail_freedom_update BEFORE UPDATE OF helper ON city BEGIN SELECT RAISE(ABORT, 'helper update failed'); END",
        'drop' => 'fail_freedom_update',
        'effectMessage' => 'Freedom-help effect failure is surfaced',
        'itemMessage' => 'Freedom-help item is restored when its effect fails',
    ],
    [
        'action' => 'useArmyPackA100',
        'item' => 'army_a',
        'trigger' => "CREATE TRIGGER fail_army_update BEFORE UPDATE OF army_a ON city BEGIN SELECT RAISE(ABORT, 'army update failed'); END",
        'drop' => 'fail_army_update',
        'effectMessage' => 'Army-pack effect failure is surfaced',
        'itemMessage' => 'Army pack is restored when its effect fails',
    ],
];

foreach ($cases as $case) {
    $stmt = $db->prepare('INSERT INTO player_item (id_player, id_item, amount) VALUES (1, ?, 1)');
    $stmt->execute([$case['item']]);
    $db->exec($case['trigger']);

    $failedAsExpected = false;
    try {
        invokeItemAction($case['action'], $case['item']);
    } catch (PDOException $error) {
        $failedAsExpected = true;
    }

    $remainingItem = (int)$db->query(
        'SELECT amount FROM player_item WHERE id_player = 1 AND id_item = ' . $db->quote($case['item'])
    )->fetchColumn();
    assertCondition($failedAsExpected, $case['effectMessage']);
    assertCondition($remainingItem === 1, $case['itemMessage']);
    $db->exec('DROP TRIGGER ' . $case['drop']);
}

$db->exec("INSERT INTO player_item (id_player, id_item, amount) VALUES (1, 'unhandled_item', 1)");
$unknownActionResponse = null;
try {
    invokeItemAction('useUnknownAction', 'unhandled_item');
} catch (ItemTransactionJsonResponse $error) {
    $unknownActionResponse = $error->payload;
}
$unhandledItemAmount = (int)$db->query(
    "SELECT amount FROM player_item WHERE id_player = 1 AND id_item = 'unhandled_item'"
)->fetchColumn();
assertCondition(
    $unknownActionResponse !== null && $unknownActionResponse['state'] === 'error_invalid_item',
    'Unknown item-use actions are rejected instead of reported as successful'
);
assertCondition($unhandledItemAmount === 1, 'Unknown item-use actions do not consume inventory');

echo "\nITEM TRANSACTION RESULTS: {$passed} / " . ($passed + $failed) . " tests passed.\n";
if ($failed > 0) {
    exit(1);
}
