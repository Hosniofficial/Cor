<?php

require_once __DIR__ . '/../caesar-api/services/HeroDeploymentService.php';

class HeroDeploymentTestStatement extends PDOStatement {
    private $db;
    private $sql;
    private $value;

    public function __construct($db, string $sql) {
        $this->db = $db;
        $this->sql = $sql;
    }

    public function execute(?array $params = null): bool {
        if (strpos($this->sql, 'SELECT id_city FROM city WHERE id_city = ? FOR UPDATE') === 0) {
            $cityId = (int)$params[0];
            $this->db->lockedCities[] = $cityId;
            $this->value = in_array($cityId, $this->db->cityIds, true) ? $cityId : false;
        } elseif (strpos($this->sql, 'FROM city_building') !== false) {
            $this->value = $this->db->plazaLevels[(int)$params[0]] ?? 0;
        } elseif (strpos($this->sql, 'SELECT COUNT(*) FROM city_hero') === 0) {
            $this->db->outsideCountQuery = $this->sql;
            $this->value = $this->db->outsideCounts[(int)$params[0]] ?? 0;
        }
        return true;
    }

    public function fetchColumn(int $column = 0): mixed {
        return $this->value;
    }
}

class HeroDeploymentTestDatabase extends PDO {
    public $cityIds = [3, 9];
    public $plazaLevels = [3 => 5];
    public $outsideCounts = [3 => 4];
    public $lockedCities = [];
    public $outsideCountQuery = '';

    public function __construct() {}

    public function prepare(string $query, array $options = []): PDOStatement|false {
        return new HeroDeploymentTestStatement($this, $query);
    }
}

$cases = [
    [0, 0, false],
    [5, 4, true],
    [5, 5, false],
    [10, 9, true],
    [10, 10, false],
    [30, 9, true],
    [30, 10, false],
    [30, 15, false]
];

foreach ($cases as [$plazaLevel, $outsideCount, $expected]) {
    if (HeroDeploymentService::hasAvailableSlot($plazaLevel, $outsideCount) !== $expected) {
        throw new RuntimeException(
            "Unexpected outside-hero capacity for Plaza {$plazaLevel} with {$outsideCount} heroes out"
        );
    }
}

$db = new HeroDeploymentTestDatabase();
if (!HeroDeploymentService::hasAvailableOutsideSlot($db, 3, 7, [9])
    || $db->lockedCities !== [3, 9]
    || strpos($db->outsideCountQuery, "g.status = 'engaged'") === false
    || strpos($db->outsideCountQuery, 'h.loyal > 0') === false) {
    throw new RuntimeException('The city Plaza capacity check did not lock cities or count outside heroes correctly');
}

$db->outsideCounts[3] = 5;
if (HeroDeploymentService::hasAvailableOutsideSlot($db, 3, 7)) {
    throw new RuntimeException('A city must not dispatch another hero once its Plaza capacity is reached');
}

echo "Hero deployment capacity tests passed\n";
