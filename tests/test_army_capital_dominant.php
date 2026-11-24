<?php

class CapitalDominantTestResponse extends RuntimeException {
    public $payload;

    public function __construct($payload) {
        $this->payload = $payload;
    }
}

if (!function_exists('sendJson')) {
    function sendJson($data): void {
        throw new CapitalDominantTestResponse($data);
    }
}

require_once __DIR__ . '/../caesar-api/controllers/WorldController.php';

function expectCondition(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

// 1. Verify isGuildDominanceTarget includes capitals
$capitalTypes = [150, 151, 152, 153, 154, 155, 105];
$ref = new ReflectionClass('WorldController');
$isTargetMethod = $ref->getMethod('isGuildDominanceTarget');
$isTargetMethod->setAccessible(true);

foreach ($capitalTypes as $t) {
    expectCondition($isTargetMethod->invoke(null, $t) === true, "Capital type $t must be a guild dominance target");
}
foreach ([180, 181, 182, 184, 185, 186] as $t) {
    expectCondition($isTargetMethod->invoke(null, $t) === true, "Unit type $t must be a guild dominance target");
}
foreach ([1, 20, 50, 60, 70, 71, 90, 110, 175] as $t) {
    expectCondition($isTargetMethod->invoke(null, $t) === false, "Unit type $t must NOT be a guild dominance target");
}

// 2. Test getArmyCapitalDominant with empty dominance table (no guilds holding capitals)
class MockDominanceStmt {
    private $rows;
    private $index = 0;
    public function __construct(array $rows) {
        $this->rows = $rows;
    }
    public function fetch($mode = PDO::FETCH_ASSOC) {
        if ($this->index < count($this->rows)) {
            return $this->rows[$this->index++];
        }
        return false;
    }
}

class MockDominanceDB extends PDO {
    private $mockRows;
    public function __construct(array $mockRows = []) {
        $this->mockRows = $mockRows;
    }
    public function query($statement, $mode = PDO::ATTR_DEFAULT_FETCH_MODE, ...$fetch_mode_args) {
        return new MockDominanceStmt($this->mockRows);
    }
}

$emptyDb = new MockDominanceDB([]);
try {
    WorldController::getArmyCapitalDominant($emptyDb);
    expectCondition(false, 'Expected CapitalDominantTestResponse');
} catch (CapitalDominantTestResponse $e) {
    $res = $e->payload;
    expectCondition($res['state'] === 'ok', 'State must be ok');
    expectCondition($res['type'] === 'domain', 'Type must be domain');
    expectCondition($res['dominanceDataAvailable'] === true, 'dominanceDataAvailable must be true');
    expectCondition(count($res['unit']) === 7, 'Must have 7 capitals');
    foreach ($res['unit'] as $u) {
        expectCondition(empty($u['rank']), "Rank must be empty for unclaimed capital {$u['title']}");
        expectCondition(in_array($u['type'], $capitalTypes, true), "Capital type must be valid");
        expectCondition($u['x'] > 0 && $u['y'] > 0, "Coordinates must be positive");
    }
}

// 3. Test getArmyCapitalDominant with active dominance records
// Suppose unit 150 (Infantry capital, x=235, y=125) is dominated by guild "Spartans" player "Achilles"
$now = time();
$mockRows = [
    [
        'x_coord' => 235,
        'y_coord' => 125,
        'unit_type' => 150,
        'conquered_at' => $now - 3600,
        'id_guild' => 5,
        'id_player' => 12,
        'GuildName' => 'حلف الفرسان',
        'PlayerName' => 'الاسكندر',
    ],
    [
        'x_coord' => 103,
        'y_coord' => 447,
        'unit_type' => 105,
        'conquered_at' => $now - 7200,
        'id_guild' => 0,
        'id_player' => 99,
        'GuildName' => '',
        'PlayerName' => 'سيزر',
    ]
];

$activeDb = new MockDominanceDB($mockRows);
try {
    WorldController::getArmyCapitalDominant($activeDb);
    expectCondition(false, 'Expected CapitalDominantTestResponse');
} catch (CapitalDominantTestResponse $e) {
    $res = $e->payload;
    expectCondition($res['dominanceDataAvailable'] === true, 'dominanceDataAvailable must be true');

    // Find capital 150
    $cap150 = null;
    $cap105 = null;
    $unclaimed = [];
    foreach ($res['unit'] as $u) {
        if ($u['type'] === 150) $cap150 = $u;
        elseif ($u['type'] === 105) $cap105 = $u;
        else $unclaimed[] = $u;
    }

    expectCondition($cap150 !== null, 'Capital 150 must be found');
    expectCondition(count($cap150['rank']) === 1, 'Capital 150 must have 1 rank entry');
    expectCondition($cap150['rank'][0]['name'] === 'حلف الفرسان (الاسكندر)', 'Display name should combine guild and player');
    expectCondition($cap150['rank'][0]['time_stamp'] === $now - 3600, 'time_stamp must match conquered_at');
    expectCondition($cap150['rank'][0]['d_sum'] >= 3600, 'd_sum must match elapsed time');

    expectCondition($cap105 !== null, 'Capital 105 must be found');
    expectCondition(count($cap105['rank']) === 1, 'Capital 105 must have 1 rank entry');
    expectCondition($cap105['rank'][0]['name'] === 'سيزر', 'Player name only when no guild');

    foreach ($unclaimed as $u) {
        expectCondition(empty($u['rank']), "Unclaimed capital {$u['title']} must have empty rank");
    }
}

echo "All army capital dominance tests passed!\n";
