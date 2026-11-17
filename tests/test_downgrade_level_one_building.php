<?php

class SendJsonException extends Exception {
    public $data;

    public function __construct($data) {
        $this->data = $data;
    }
}

function sendJson($data): void {
    throw new SendJsonException($data);
}

require_once __DIR__ . '/../caesar-api/controllers/CityController.php';

class DowngradeBuildingTestStatement {
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
        if (strpos($this->sql, 'SELECT `type`, `lvl` FROM `city_building`') !== false) {
            return ['type' => 5, 'lvl' => $this->db->buildingLevel];
        }
        return false;
    }

    public function rowCount(): int {
        return 1;
    }
}

class DowngradeBuildingTestDatabase {
    public $buildingLevel = 1;
    public $queries = [];

    public function prepare(string $sql): DowngradeBuildingTestStatement {
        return new DowngradeBuildingTestStatement($this, $sql);
    }
}

$db = new DowngradeBuildingTestDatabase();
$_REQUEST = ['idCity' => 1, 'buildingPlace' => 'barracks'];
try {
    CityController::downgradeBuilding($db);
    echo "[FAIL] downgrade should return its response" . PHP_EOL;
    exit(1);
} catch (SendJsonException $error) {
    $expectedResponse = [
        'state' => 'ok',
        'list' => [],
        'building' => ['place' => 'barracks', 'type' => 0, 'lvl' => 0]
    ];
    $expectedUpdate = [
        'UPDATE `city_building` SET `type` = 0, `lvl` = 0'
        . ' WHERE `id_city` = ? AND `place` = ? AND `type` = ? AND `lvl` = 1',
        [1, 'barracks', 5]
    ];

    if ($error->data !== $expectedResponse || $db->queries[1] !== $expectedUpdate) {
        echo "[FAIL] level-one building was not cleared from its city slot" . PHP_EOL;
        exit(1);
    }
    echo "[PASS] level-one downgrade clears the building type and level" . PHP_EOL;
}

$db = new DowngradeBuildingTestDatabase();
$db->buildingLevel = 2;
try {
    CityController::downgradeBuilding($db);
    echo "[FAIL] downgrade should return its response" . PHP_EOL;
    exit(1);
} catch (SendJsonException $error) {
    $expectedResponse = [
        'state' => 'ok',
        'list' => [],
        'building' => ['place' => 'barracks', 'type' => 5, 'lvl' => 1]
    ];
    $expectedUpdate = [
        'UPDATE `city_building` SET `lvl` = `lvl` - 1'
        . ' WHERE `id_city` = ? AND `place` = ? AND `type` = ? AND `lvl` = ? AND `lvl` > 1',
        [1, 'barracks', 5, 2]
    ];

    if ($error->data !== $expectedResponse || $db->queries[1] !== $expectedUpdate) {
        echo "[FAIL] higher-level downgrade behavior changed unexpectedly" . PHP_EOL;
        exit(1);
    }
    echo "[PASS] higher-level downgrade decrements the level and retains the building" . PHP_EOL;
}
