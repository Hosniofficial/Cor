<?php
require_once __DIR__ . '/../caesar-api/services/ProductionService.php';

class UnownedCityStatement {
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

    public function fetchColumn($column = 0) {
        return false;
    }
}

class UnownedCityDatabase {
    public $queries = [];

    public function prepare(string $sql): UnownedCityStatement {
        return new UnownedCityStatement($this, $sql);
    }
}

$db = new UnownedCityDatabase();
$recalculated = ProductionService::recalculateCityProductionForPlayer($db, 123, 456);
$ownershipQuery = $db->queries[0] ?? null;

if ($recalculated
    || count($db->queries) !== 1
    || strpos($ownershipQuery[0] ?? '', 'id_player` = ? FOR UPDATE') === false
    || ($ownershipQuery[1] ?? []) !== [123, 456]) {
    fwrite(STDERR, "[FAIL] production recalculation was not blocked for an unowned city\n");
    exit(1);
}

echo "[PASS] production recalculation is blocked unless the caller owns the locked city\n";
