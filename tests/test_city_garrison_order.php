<?php

class CityGarrisonResponse extends RuntimeException {
    public $data;

    public function __construct($data) {
        parent::__construct($data['state'] ?? 'response');
        $this->data = $data;
    }
}

if (!function_exists('sendJson')) {
    function sendJson($data): void {
        throw new CityGarrisonResponse($data);
    }
}

if (!function_exists('requireAuthenticatedPlayer')) {
    function requireAuthenticatedPlayer($db): array {
        return ['id_player' => 7];
    }
}

if (!function_exists('requirePlayerOwnedCity')) {
    function requirePlayerOwnedCity($db, array $player, $cityId = null): array {
        return ['id_city' => (int)($_POST['idCity'] ?? 3), 'id_player' => $player['id_player']];
    }
}

require_once __DIR__ . '/../caesar-api/controllers/CityController.php';

class CityGarrisonOrderStatement {
    private $db;
    private $sql;
    private $rows = [];

    public function __construct($db, string $sql) {
        $this->db = $db;
        $this->sql = $sql;
    }

    public function execute(array $params = []): bool {
        if (strpos($this->sql, 'SELECT id_hero, ord, status, id_player FROM city_garrison') === 0) {
            $this->rows = $this->db->garrisons;
            usort($this->rows, function($left, $right) {
                return ($left['ord'] <=> $right['ord']) ?: ($left['id_hero'] <=> $right['id_hero']);
            });
        } elseif (strpos($this->sql, 'UPDATE city_garrison SET ord = ?') === 0) {
            foreach ($this->db->garrisons as &$hero) {
                if ((int)$hero['id_city'] === (int)$params[1] &&
                    (int)$hero['id_hero'] === (int)$params[2]) {
                    $hero['ord'] = (int)$params[0];
                }
            }
            unset($hero);
        }
        return true;
    }

    public function fetchColumn($column = 0) {
        return 3;
    }

    public function fetchAll($mode = null): array {
        return $this->rows;
    }
}

class CityGarrisonOrderDatabase {
    public $garrisons = [
        ['id_hero' => 10, 'id_city' => 3, 'id_player' => 7, 'ord' => 0, 'status' => 'stationed'],
        ['id_hero' => 13, 'id_city' => 3, 'id_player' => 8, 'ord' => 1, 'status' => 'stationed'],
        ['id_hero' => 11, 'id_city' => 3, 'id_player' => 7, 'ord' => 2, 'status' => 'stationed'],
        ['id_hero' => 12, 'id_city' => 3, 'id_player' => 7, 'ord' => 3, 'status' => 'stationed']
    ];
    private $transaction = false;

    public function prepare(string $sql): CityGarrisonOrderStatement {
        return new CityGarrisonOrderStatement($this, $sql);
    }

    public function beginTransaction(): bool {
        $this->transaction = true;
        return true;
    }

    public function inTransaction(): bool {
        return $this->transaction;
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

$db = new CityGarrisonOrderDatabase();
$_POST = ['idHero' => 12, 'idCity' => 3, 'Direction' => 'up'];
try {
    CityController::handle('/api/ACityPalace/reordCityGarrison', $db);
    throw new RuntimeException('Expected JSON response');
} catch (CityGarrisonResponse $response) {
    if (($response->data['state'] ?? '') !== 'ok') {
        throw new RuntimeException('Reordering should succeed for an eligible neighboring hero');
    }
}

$orderedHeroes = $db->garrisons;
usort($orderedHeroes, function($left, $right) {
    return ($left['ord'] <=> $right['ord']) ?: ($left['id_hero'] <=> $right['id_hero']);
});
$garrisonIds = array_map('intval', array_column($orderedHeroes, 'id_hero'));
if ($garrisonIds !== [10, 13, 12, 11]) {
    throw new RuntimeException('Reordering owner heroes must preserve the relative position of allied garrisons');
}

$_POST = ['idHero' => 13, 'idCity' => 3, 'Direction' => 'up'];
try {
    CityController::handle('/api/ACityPalace/reordCityGarrison', $db);
    throw new RuntimeException('Expected foreign-hero response');
} catch (CityGarrisonResponse $response) {
    if (($response->data['state'] ?? '') !== 'error_hero_not_in_garrison') {
        throw new RuntimeException('The city owner must not reorder a visiting hero');
    }
}

$_POST = ['idHero' => 10, 'idCity' => 3, 'Direction' => 'up'];
try {
    CityController::handle('/api/ACityPalace/reordCityGarrison', $db);
    throw new RuntimeException('Expected boundary response');
} catch (CityGarrisonResponse $response) {
    if (($response->data['state'] ?? '') !== 'error_no_neighbor') {
        throw new RuntimeException('The first hero cannot move farther up');
    }
}

$db->garrisons[3]['status'] = 'engaged';
$_POST = ['idHero' => 12, 'idCity' => 3, 'Direction' => 'up'];
try {
    CityController::handle('/api/ACityPalace/reordCityGarrison', $db);
    throw new RuntimeException('Expected engaged-hero response');
} catch (CityGarrisonResponse $response) {
    if (($response->data['state'] ?? '') !== 'error_garrison_busy') {
        throw new RuntimeException('Engaged heroes cannot be reordered');
    }
}

echo "City garrison order tests passed\n";
