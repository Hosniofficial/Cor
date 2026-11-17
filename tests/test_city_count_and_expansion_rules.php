<?php

class CityRulesResponse extends RuntimeException {
    public $data;

    public function __construct(array $data) {
        parent::__construct($data['state'] ?? 'response');
        $this->data = $data;
    }
}

if (!function_exists('sendJson')) {
    function sendJson($data): void {
        throw new CityRulesResponse($data);
    }
}

function requireAuthenticatedPlayer($db): array {
    return ['id_player' => $db->playerId, 'porm' => $db->porm];
}

function requirePlayerOwnedCity($db, array $player, $cityId = null): array {
    return ['id_city' => $db->cityId, 'id_player' => $player['id_player']];
}

function normalizeGameDisplayName($name): ?string {
    return is_string($name) && trim($name) !== '' ? trim($name) : null;
}

require_once __DIR__ . '/../caesar-api/controllers/WorldController.php';
require_once __DIR__ . '/../caesar-api/controllers/CityController.php';

class CityRulesStatement {
    private $db;
    private $sql;

    public function __construct($db, string $sql) {
        $this->db = $db;
        $this->sql = $sql;
    }

    public function execute(array $params = []): bool {
        return true;
    }

    public function fetch($mode = null) {
        return strpos($this->sql, 'SELECT * FROM city WHERE id_city') !== false
            ? $this->db->city
            : false;
    }

    public function fetchColumn($column = 0) {
        if (strpos($this->sql, 'SELECT porm FROM player') !== false) {
            return $this->db->porm;
        }
        if (strpos($this->sql, 'SELECT COUNT(*) FROM city') !== false) {
            return $this->db->cityCount;
        }
        if (strpos($this->sql, 'SELECT amount FROM player_item') !== false) {
            return $this->db->plans;
        }
        return false;
    }

    public function fetchAll($mode = null): array {
        return strpos($this->sql, 'SELECT lvl FROM city_building') !== false
            ? [$this->db->palaceLevel]
            : [];
    }

    public function rowCount(): int {
        return 1;
    }
}

class CityRulesDatabase {
    public $playerId = 12;
    public $cityId = 34;
    public $porm = 0;
    public $cityCount = 1;
    public $resourceAmount = 10000;
    public $palaceLevel = 10;
    public $plans = 0;
    public $city;
    private $transaction = false;

    public function __construct() {
        $this->city = [
            'id_city' => $this->cityId,
            'id_player' => $this->playerId,
            'lvl' => 2,
            'food' => $this->resourceAmount,
            'wood' => $this->resourceAmount,
            'stone' => $this->resourceAmount,
            'metal' => $this->resourceAmount,
            'coin' => $this->resourceAmount
        ];
    }

    public function prepare(string $sql): CityRulesStatement {
        return new CityRulesStatement($this, $sql);
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
        return true;
    }

    public function commit(): bool {
        $this->transaction = false;
        return true;
    }
}

function buildCityState(int $cityCount, int $porm, int $resources): string {
    $db = new CityRulesDatabase();
    $db->cityCount = $cityCount;
    $db->porm = $porm;
    $db->resourceAmount = $resources;
    foreach (['food', 'wood', 'stone', 'metal', 'coin'] as $resource) {
        $db->city[$resource] = $resources;
    }

    $_REQUEST = [
        'idCity' => $db->cityId,
        'xCoord' => 500,
        'yCoord' => 0,
        'CityName' => 'New city'
    ];

    try {
        WorldController::buildNewCity($db);
    } catch (CityRulesResponse $response) {
        return $response->data['state'] ?? '';
    }

    return '';
}

if (buildCityState(9, 9, 10000) !== 'error_3') {
    echo "[FAIL] rank 9 and 10,000 resources should pass requirements for city 10" . PHP_EOL;
    exit(1);
}
if (buildCityState(9, 8, 10000) !== 'error_1') {
    echo "[FAIL] city 10 should require rank 9" . PHP_EOL;
    exit(1);
}
if (buildCityState(9, 9, 9999) !== 'error_2') {
    echo "[FAIL] city construction should require 10,000 of each resource" . PHP_EOL;
    exit(1);
}
if (buildCityState(10, 9, 10000) !== 'error_1') {
    echo "[FAIL] city construction should stop at the 10-city cap" . PHP_EOL;
    exit(1);
}

function expandCityState(int $palaceLevel): string {
    $db = new CityRulesDatabase();
    $db->palaceLevel = $palaceLevel;
    $_REQUEST = ['idCity' => $db->cityId];

    try {
        CityController::expandCity($db);
    } catch (CityRulesResponse $response) {
        return $response->data['state'] ?? '';
    }

    return '';
}

if (expandCityState(10) !== 'error_item_lack') {
    echo "[FAIL] palace level 10 should satisfy the third city expansion requirement" . PHP_EOL;
    exit(1);
}
if (expandCityState(9) !== 'error_palace_lvl') {
    echo "[FAIL] palace level 9 should not satisfy the third city expansion requirement" . PHP_EOL;
    exit(1);
}

echo "[PASS] city rank, resource, cap, and palace expansion requirements match the game rules" . PHP_EOL;
