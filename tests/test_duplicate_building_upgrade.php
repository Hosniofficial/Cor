<?php

class BuildingUpgradeResponse extends Exception {
    public $data;

    public function __construct($data) {
        $this->data = $data;
    }
}

if (!function_exists('sendJson')) {
    function sendJson($data): void {
        throw new BuildingUpgradeResponse($data);
    }
}

require_once __DIR__ . '/../caesar-api/controllers/CityController.php';

class DuplicateUpgradeStatement {
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
        if (strpos($this->sql, 'FROM `city`') !== false) {
            return [
                'id_city' => 3,
                'id_player' => 9,
                'food' => 100000,
                'wood' => 100000,
                'stone' => 100000,
                'metal' => 100000,
                'coin' => 100000
            ];
        }

        if (strpos($this->sql, 'FROM `city_building`') !== false) {
            return ['type' => 1, 'lvl' => 4];
        }

        return false;
    }

    public function fetchColumn($column = 0) {
        return strpos($this->sql, 'FROM `timed_task`') !== false ? 42 : false;
    }
}

class DuplicateUpgradeDatabase {
    public $queries = [];
    public $rollbacks = 0;
    private $transaction = false;

    public function prepare(string $sql): DuplicateUpgradeStatement {
        return new DuplicateUpgradeStatement($this, $sql);
    }

    public function beginTransaction(): bool {
        $this->transaction = true;
        return true;
    }

    public function inTransaction(): bool {
        return $this->transaction;
    }

    public function rollBack(): bool {
        $this->transaction = false;
        $this->rollbacks++;
        return true;
    }

    public function commit(): bool {
        $this->transaction = false;
        return true;
    }
}

$db = new DuplicateUpgradeDatabase();
$_REQUEST = ['idCity' => 3, 'buildingPlace' => 'farm_1'];

try {
    CityController::upgradeBuilding($db);
    echo "[FAIL] duplicate building upgrade was accepted" . PHP_EOL;
    exit(1);
} catch (BuildingUpgradeResponse $response) {
    $rejected = ($response->data['state'] ?? '') === 'error_in_progress';
}

$mutatedResourcesOrTasks = count(array_filter($db->queries, function($query) {
    return strpos($query[0], 'UPDATE `city` SET') !== false
        || strpos($query[0], "INSERT INTO `timed_task`") !== false
        || strpos($query[0], 'UPDATE `player_item`') !== false;
})) > 0;
$cityRowLocked = count(array_filter($db->queries, function($query) {
    return strpos($query[0], 'FROM `city`') !== false
        && strpos($query[0], 'FOR UPDATE') !== false;
})) === 1;

if (!$rejected || !$cityRowLocked || $db->inTransaction() || $db->rollbacks !== 1 || $mutatedResourcesOrTasks) {
    echo "[FAIL] duplicate upgrade was not rejected atomically" . PHP_EOL;
    exit(1);
}

echo "[PASS] duplicate building upgrade is rejected without charging resources or items" . PHP_EOL;
