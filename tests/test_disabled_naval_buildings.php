<?php

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

require_once __DIR__ . '/../caesar-api/controllers/CityController.php';

class NavalBuildingTestStatement {
    private $db;
    private $sql;

    public function __construct($db, string $sql) {
        $this->db = $db;
        $this->sql = $sql;
    }

    public function execute(array $params = []): bool {
        $this->db->queries[] = [$this->sql, $params];
        return true;
    }

    public function fetch($mode = null) {
        return [
            'type' => 19,
            'lvl' => 0,
            'id_player' => 1,
            'id_city' => 1,
            'id_task' => 5,
            'task_type' => 'building',
            'item_id' => 'seaport',
            'details' => '{"type":19}'
        ];
    }

    public function fetchColumn($column = 0) {
        return 20;
    }

    public function fetchAll($mode = null): array {
        if (strpos($this->sql, 'FROM `timed_task`') !== false) {
            return [[
                'id_task' => 5,
                'id_city' => 1,
                'id_player' => 1,
                'task_type' => 'building',
                'item_id' => 'seaport',
                'lvl_to' => 1,
                'details' => '{"type":19}'
            ]];
        }
        return [];
    }
}

class NavalBuildingTestDatabase {
    public $queries = [];
    private $transaction = false;

    public function prepare(string $sql): NavalBuildingTestStatement {
        return new NavalBuildingTestStatement($this, $sql);
    }

    public function inTransaction(): bool {
        return $this->transaction;
    }

    public function beginTransaction(): bool {
        $this->transaction = true;
        return true;
    }

    public function commit(): bool {
        $this->transaction = false;
        return true;
    }

    public function rollBack(): bool {
        $this->transaction = false;
        return true;
    }
}

function expectDisabledBuilding(callable $action): bool {
    try {
        $action();
    } catch (SendJsonException $error) {
        return ($error->data['state'] ?? '') === 'error_building_disabled';
    }
    return false;
}

$db = new NavalBuildingTestDatabase();
$cases = [
    'construction of seaport is rejected' => function() use ($db) {
        $_REQUEST = ['idCity' => 1, 'buildingPlace' => 'free', 'buildingType' => 19];
        CityController::constructNewBuilding($db);
    },
    'construction of lighthouse is rejected' => function() use ($db) {
        $_REQUEST = ['idCity' => 1, 'buildingPlace' => 'free', 'buildingType' => 20];
        CityController::constructNewBuilding($db);
    },
    'seaport upgrade is rejected' => function() use ($db) {
        $_REQUEST = ['idCity' => 1, 'buildingPlace' => 'seaport'];
        CityController::upgradeBuilding($db);
    },
    'lighthouse downgrade is rejected' => function() use ($db) {
        $_REQUEST = ['idCity' => 1, 'buildingPlace' => 'lighthouse'];
        CityController::downgradeBuilding($db);
    },
    'seaport task cannot be sped up' => function() use ($db) {
        $_REQUEST = ['idTask' => 5, 'itemToUse' => 'speed_15m'];
        CityController::speedUpBuilding($db);
    }
];

$failed = 0;
foreach ($cases as $name => $case) {
    $passed = expectDisabledBuilding($case);
    echo ($passed ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$passed) {
        $failed++;
    }
}

TaskService::processCompletedTasks($db);
$taskMutations = array_filter($db->queries, function($query) {
    return strpos($query[0], 'DELETE FROM `timed_task`') !== false
        || strpos($query[0], 'INSERT INTO `city_building`') !== false
        || strpos($query[0], 'UPDATE `player` SET `prestige`') !== false;
});
$canceledTask = count(array_filter($taskMutations, function($query) {
    return strpos($query[0], 'DELETE FROM `timed_task`') !== false;
})) === 1;
$noBuildingOrPrestigeAward = count(array_filter($taskMutations, function($query) {
    return strpos($query[0], 'INSERT INTO `city_building`') !== false
        || strpos($query[0], 'UPDATE `player` SET `prestige`') !== false;
})) === 0;
echo ($canceledTask && $noBuildingOrPrestigeAward ? '[PASS] ' : '[FAIL] ')
    . 'completed unsupported naval-building task is canceled without rewards' . PHP_EOL;
if (!$canceledTask || !$noBuildingOrPrestigeAward) {
    $failed++;
}

exit($failed > 0 ? 1 : 0);
