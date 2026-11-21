<?php

class HeroControllerTestResponse extends RuntimeException {
    public $payload;

    public function __construct($payload) {
        $this->payload = $payload;
    }
}

function sendJson($data): void {
    throw new HeroControllerTestResponse($data);
}

require_once __DIR__ . '/../caesar-api/PlayerAuth.php';
require_once __DIR__ . '/../caesar-api/controllers/HeroController.php';

class HeroTheaterTestStatement {
    private $db;
    private $query;
    private $row = false;
    private $column = false;

    public function __construct($db, string $query) {
        $this->db = $db;
        $this->query = $query;
    }

    public function execute(array $params = []): bool {
        if (strpos($this->query, 'SELECT * FROM `city_theater`') !== false) {
            $this->row = $this->db->cachedPool;
        } elseif (strpos($this->query, 'SELECT `lvl` FROM `city_building`') !== false) {
            $this->column = 10;
        } elseif (strpos($this->query, 'INSERT INTO `city_theater`') !== false) {
            $this->db->savedPool = json_decode($params[3], true);
        }
        return true;
    }

    public function fetch($mode = null) {
        return $this->row;
    }

    public function fetchColumn($column = 0) {
        return $this->column;
    }
}

class HeroTheaterTestDatabase {
    public $cachedPool;
    public $savedPool;

    public function __construct() {
        $this->cachedPool = [
            'last_update' => time(),
            'refresh_time' => 3600,
            'heroes' => json_encode([['id_hero' => 1, 'ultra_p' => 1]])
        ];
    }

    public function exec(string $query): int {
        return 0;
    }

    public function prepare(string $query): HeroTheaterTestStatement {
        return new HeroTheaterTestStatement($this, $query);
    }
}

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE player (id_player INTEGER PRIMARY KEY, p_token TEXT)');
$db->exec('CREATE TABLE city (id_city INTEGER PRIMARY KEY, id_player INTEGER, coin INTEGER)');
$db->exec('CREATE TABLE city_theater (id_city INTEGER PRIMARY KEY, heroes TEXT)');
$db->exec('CREATE TABLE player_edu (id_player INTEGER PRIMARY KEY, scholership INTEGER)');
$db->exec('CREATE TABLE city_hero (
    id_hero INTEGER PRIMARY KEY,
    id_city INTEGER NOT NULL,
    id_player INTEGER NOT NULL,
    lvl INTEGER NOT NULL,
    exp INTEGER NOT NULL,
    ultra_p INTEGER NOT NULL,
    points INTEGER NOT NULL,
    power_max INTEGER NOT NULL,
    power INTEGER NOT NULL
)');
$db->exec("INSERT INTO player VALUES (7, 'player-token'), (8, 'other-token')");
$db->exec('INSERT INTO city VALUES (3, 7, 5000), (4, 8, 5000)');
$db->exec('INSERT INTO player_edu VALUES (7, 0)');
$db->exec('INSERT INTO city_hero VALUES
    (101, 3, 7, 1, 100, 0, 2, 52, 50),
    (102, 3, 7, 1, 125, 3, 6, 52, 50),
    (103, 3, 7, 1, 125, 8, 9, 52, 50),
    (104, 3, 7, 255, 2147483647, 0, 2, 560, 50),
    (105, 3, 7, 256, 2147483647, 0, 2, 562, 50)');

$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = ['token' => 'player-token'];
$_REQUEST = ['token' => 'player-token', 'idHero' => 101];

try {
    HeroController::handle('/api/ACityHero/addFromTheater', $db);
    throw new RuntimeException('Theater recruitment without a city should be rejected');
} catch (HeroControllerTestResponse $response) {
    if (($response->payload['state'] ?? null) !== 'error_auth') {
        throw new RuntimeException('Missing city ID should fail authentication');
    }
}

$_POST = ['token' => 'player-token', 'idCity' => 3];
$_REQUEST = ['token' => 'player-token', 'idCity' => 3, 'idHero' => 1];
try {
    HeroController::handle('/api/ACityHero/addFromTheater', $db);
    throw new RuntimeException('Missing theater hero should return an application error');
} catch (HeroControllerTestResponse $response) {
    if (($response->payload['state'] ?? null) !== 'error_0') {
        throw new RuntimeException('An owned city should pass authorization before theater lookup');
    }
}

$_POST = ['token' => 'player-token', 'idCity' => 4];
$_REQUEST = ['token' => 'player-token', 'idCity' => 4, 'idHero' => 1];
try {
    HeroController::handle('/api/ACityHero/addFromTheater', $db);
    throw new RuntimeException("Recruitment from another player's city should be rejected");
} catch (HeroControllerTestResponse $response) {
    if (($response->payload['state'] ?? null) !== 'error_auth') {
        throw new RuntimeException("Another player's city should fail authorization");
    }
}

$levelUpCases = [
    ['heroId' => 101, 'pointsBefore' => 2, 'pointsGained' => 1],
    ['heroId' => 102, 'pointsBefore' => 6, 'pointsGained' => 3],
    ['heroId' => 103, 'pointsBefore' => 9, 'pointsGained' => 8]
];
foreach ($levelUpCases as $case) {
    $heroId = $case['heroId'];
    $pointsBefore = $case['pointsBefore'];
    $pointsGained = $case['pointsGained'];
    $_POST = ['token' => 'player-token'];
    $_REQUEST = ['token' => 'player-token', 'idHero' => $heroId];
    try {
        HeroController::handle('/api/AHero/upgradeHeroLvl', $db);
        throw new RuntimeException('Hero level-up should return a JSON response');
    } catch (HeroControllerTestResponse $response) {
        if (($response->payload['state'] ?? null) !== 'ok'
            || (int)$response->payload['Hero']['points'] !== $pointsBefore + $pointsGained
            || (int)$response->payload['Hero']['power_max'] !== 54
            || (int)$db
                ->query("SELECT points FROM city_hero WHERE id_hero = {$heroId}")
                ->fetchColumn() !== $pointsBefore + $pointsGained
            || (int)$db
                ->query("SELECT power_max FROM city_hero WHERE id_hero = {$heroId}")
                ->fetchColumn() !== 54) {
            throw new RuntimeException('Hero level-up should add the minimum of one or the hero potential and set the fitness cap');
        }
    }
}

foreach ([104 => 255, 105 => 256] as $heroId => $levelBefore) {
    $_POST = ['token' => 'player-token'];
    $_REQUEST = ['token' => 'player-token', 'idHero' => $heroId];
    try {
        HeroController::handle('/api/AHero/upgradeHeroLvl', $db);
        throw new RuntimeException('A hero at the level cap should not level up');
    } catch (HeroControllerTestResponse $response) {
        $hero = $db->query("SELECT lvl, exp FROM city_hero WHERE id_hero = {$heroId}")->fetch(PDO::FETCH_ASSOC);
        if (($response->payload['state'] ?? null) !== 'error_max_level'
            || (int)$response->payload['maxLevel'] !== 255
            || (int)$hero['lvl'] !== $levelBefore
            || (int)$hero['exp'] !== 2147483647) {
            throw new RuntimeException('The server should preserve heroes at or above level 255');
        }
    }
}

$_REQUEST = ['idHero' => 104, 'itemToUse' => 'exp_hero_8'];
try {
    HeroController::addExp($db);
    throw new RuntimeException('A max-level hero should not consume an experience item');
} catch (HeroControllerTestResponse $response) {
    if (($response->payload['state'] ?? null) !== 'error_max_level') {
        throw new RuntimeException('Max-level heroes should reject experience items');
    }
}

$highPotentialCount = 0;
for ($poolIndex = 0; $poolIndex < 200; $poolIndex++) {
    $theaterDb = new HeroTheaterTestDatabase();
    $_REQUEST = ['idCity' => 3];
    try {
        HeroController::refreshHeroTheater($theaterDb);
        throw new RuntimeException('Theater refresh should return its generated pool');
    } catch (HeroControllerTestResponse $response) {
        $pool = $response->payload['HeroList'] ?? [];
        if (count($pool) !== 10 || count($theaterDb->savedPool) !== 10) {
            throw new RuntimeException('Expired hero pools should regenerate into ten slots');
        }
        foreach ($pool as $hero) {
            $potential = (int)$hero['ultra_p'];
            if ($potential !== 0 && ($potential < 3 || $potential > 8)) {
                throw new RuntimeException('Theater potentials should be zero or a rare value from three through eight');
            }
            if (($hero['_pool_version'] ?? null) !== 2) {
                throw new RuntimeException('Generated theater pools should be marked with the current version');
            }
            if ($potential > 0) $highPotentialCount++;
        }
    }
}

if ($highPotentialCount < 140 || $highPotentialCount > 260) {
    throw new RuntimeException('High-potential heroes should appear at approximately ten percent');
}

echo "Hero theater authorization, potential, and level-up tests passed\n";
