<?php

class HeroMedalActivationTestResponse extends RuntimeException {
    public $payload;

    public function __construct($payload) {
        $this->payload = $payload;
    }
}

function sendJson($data): void {
    throw new HeroMedalActivationTestResponse($data);
}

require_once __DIR__ . '/../caesar-api/controllers/ItemController.php';
require_once __DIR__ . '/../caesar-api/controllers/HeroController.php';

class HeroMedalActivationTestStatement extends PDOStatement {
    private $db;
    private $sql;
    private $result = false;
    private $affectedRows = 0;

    public function __construct($db, string $sql) {
        $this->db = $db;
        $this->sql = $sql;
    }

    public function execute(?array $params = null): bool {
        if (strpos($this->sql, 'SELECT h.*, c.id_player, c.id_city FROM city_hero') === 0) {
            $this->result = $this->db->hero;
        } elseif (strpos($this->sql, 'INSERT IGNORE INTO `hero_medal`') === 0) {
            $heroId = (int)$params[0];
            if (!isset($this->db->medals[$heroId])) {
                $this->db->medals[$heroId] = [
                    'id_hero' => $heroId,
                    'id_player' => (int)$params[1],
                    'medal_ceasro' => 0,
                    'medal_den' => 0,
                    'medal_leo' => 0,
                    'ceaser_eagle' => 0
                ];
                $this->affectedRows = 1;
            }
        } elseif (strpos($this->sql, 'SELECT amount FROM player_item') === 0) {
            $this->result = $this->db->inventory[$params[1]] ?? 0;
        } elseif (strpos($this->sql, 'UPDATE `player_item` SET `amount` = `amount` - ?') === 0) {
            [$amount, $playerId, $item, $minimum] = $params;
            $available = $this->db->inventory[$item] ?? 0;
            if ($available >= $minimum) {
                $this->db->inventory[$item] = $available - $amount;
                $this->affectedRows = 1;
            }
        } elseif (strpos($this->sql, 'DELETE FROM `player_item`') === 0) {
            $item = $params[1];
            if (($this->db->inventory[$item] ?? 0) <= 0) {
                unset($this->db->inventory[$item]);
                $this->affectedRows = 1;
            }
        } elseif (preg_match('/UPDATE `hero_medal` SET `([a-z_]+)` = GREATEST\\(/', $this->sql, $matches)) {
            [$now, $duration, $heroId] = $params;
            $column = $matches[1];
            $currentExpiry = (int)($this->db->medals[$heroId][$column] ?? 0);
            $this->db->medals[$heroId][$column] = max($currentExpiry, (int)$now) + (int)$duration;
            $this->affectedRows = 1;
        } elseif (strpos($this->sql, 'SELECT * FROM hero_medal WHERE id_hero = ?') === 0) {
            $this->result = $this->db->medals[(int)$params[0]] ?? false;
        } elseif (strpos($this->sql, 'SELECT * FROM city WHERE id_city = ?') === 0) {
            $this->result = $this->db->city;
        } else {
            throw new RuntimeException('Unexpected SQL in hero medal activation test: ' . $this->sql);
        }

        return true;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed {
        return $this->result;
    }

    public function fetchColumn(int $column = 0): mixed {
        return $this->result;
    }

    public function rowCount(): int {
        return $this->affectedRows;
    }
}

class HeroMedalActivationTestDatabase extends PDO {
    public $hero = [
        'id_hero' => 101,
        'id_city' => 3,
        'id_player' => 7
    ];
    public $city = [
        'id_city' => 3,
        'id_player' => 7,
        'food' => 1000
    ];
    public $inventory = [];
    public $medals = [];
    private $transactionSnapshot = null;
    public $commitCount = 0;
    public $rollbackCount = 0;

    public function __construct() {}

    public function prepare(string $query, array $options = []): PDOStatement|false {
        return new HeroMedalActivationTestStatement($this, $query);
    }

    public function exec(string $statement): int|false {
        return 0;
    }

    public function beginTransaction(): bool {
        $this->transactionSnapshot = [$this->inventory, $this->medals];
        return true;
    }

    public function commit(): bool {
        $this->transactionSnapshot = null;
        $this->commitCount++;
        return true;
    }

    public function rollBack(): bool {
        if ($this->transactionSnapshot !== null) {
            [$this->inventory, $this->medals] = $this->transactionSnapshot;
            $this->transactionSnapshot = null;
        }
        $this->rollbackCount++;
        return true;
    }

    public function inTransaction(): bool {
        return $this->transactionSnapshot !== null;
    }
}

$medalRoutes = [
    '/api/AHeroMedal/activateCiceroMedal' => 'medal_ceasro',
    '/api/AHeroMedal/activateDentatusMedal' => 'medal_den',
    '/api/AHeroMedal/activateLeonidasMedal' => 'medal_leo',
    '/api/AHeroMedal/activateCaeserMedal' => 'ceaser_eagle'
];

foreach ($medalRoutes as $route => $item) {
    $db = new HeroMedalActivationTestDatabase();
    $payload = null;
    $db->inventory[$item] = 2;
    $existingExpiry = time() + 3600;
    $db->medals[101] = [
        'id_hero' => 101,
        'id_player' => 7,
        'medal_ceasro' => 0,
        'medal_den' => 0,
        'medal_leo' => 0,
        'ceaser_eagle' => 0
    ];
    $db->medals[101][$item] = $existingExpiry;
    $_REQUEST = ['idHero' => 101];

    try {
        HeroController::activateHeroMedal($route, $db);
    } catch (HeroMedalActivationTestResponse $response) {
        $payload = $response->payload;
    }

    $newExpiry = (int)($payload['HeroMedal'][$item] ?? 0);
    if (($payload['state'] ?? null) !== 'ok'
        || ($db->inventory[$item] ?? 0) !== 1
        || $newExpiry < max($existingExpiry, time()) + 604800
        || $newExpiry > max($existingExpiry, time()) + 604801
        || $db->commitCount !== 1
        || $db->rollbackCount !== 0) {
        throw new RuntimeException("Medal {$item} should be consumed and extend its active effect by seven days");
    }
}

$db = new HeroMedalActivationTestDatabase();
$db->medals[101] = [
    'id_hero' => 101,
    'id_player' => 7,
    'medal_ceasro' => 5000000000,
    'medal_den' => 0,
    'medal_leo' => 0,
    'ceaser_eagle' => 0
];
$payload = null;
$_REQUEST = ['idHero' => 101];
try {
    HeroController::activateHeroMedal('/api/AHeroMedal/activateCiceroMedal', $db);
} catch (HeroMedalActivationTestResponse $response) {
    $payload = $response->payload;
}

if (($payload['state'] ?? null) !== 'error_1'
    || $db->medals[101]['medal_ceasro'] !== 5000000000
    || $db->commitCount !== 0
    || $db->rollbackCount !== 1
    || $db->inTransaction()) {
    throw new RuntimeException('A medal without inventory must not be activated or leave a transaction open');
}

echo "Hero medal activation tests passed\n";
