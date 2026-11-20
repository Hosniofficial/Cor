<?php

class GuildHarvestTestResponse extends RuntimeException {
    public $payload;

    public function __construct($payload) {
        $this->payload = $payload;
    }
}

if (!function_exists('sendJson')) {
    function sendJson($data): void {
        throw new GuildHarvestTestResponse($data);
    }
}

require_once __DIR__ . '/../caesar-api/controllers/WorldController.php';

class GuildHarvestTestDatabase {
    public $calls = [];
    public $dominanceRow = [
        'id_guild' => 3,
        'player_guild' => 3,
        'last_harvest_at' => 0,
        'daily_food' => 100,
        'daily_wood' => 200,
        'daily_stone' => 300,
        'daily_metal' => 400,
        'daily_coin' => 500
    ];
    public $cityId = 12;
    private $transaction = false;

    public function beginTransaction(): bool {
        $this->transaction = true;
        $this->calls[] = ['begin'];
        return true;
    }

    public function commit(): bool {
        $this->calls[] = ['commit'];
        $this->transaction = false;
        return true;
    }

    public function rollBack(): bool {
        $this->calls[] = ['rollback'];
        $this->transaction = false;
        return true;
    }

    public function inTransaction(): bool {
        return $this->transaction;
    }

    public function prepare(string $sql): GuildHarvestTestStatement {
        return new GuildHarvestTestStatement($this, $sql);
    }
}

class GuildHarvestTestStatement {
    private $db;
    private $sql;
    private $row = false;

    public function __construct(GuildHarvestTestDatabase $db, string $sql) {
        $this->db = $db;
        $this->sql = $sql;
    }

    public function execute(array $params = []): bool {
        if (strpos($this->sql, 'FOR UPDATE') !== false && !$this->db->inTransaction()) {
            throw new RuntimeException('FOR UPDATE must run inside a transaction');
        }

        $this->db->calls[] = ['query', $this->sql, $params];
        if (strpos($this->sql, 'FROM guild_dominance') !== false) {
            $this->row = $this->db->dominanceRow;
        } elseif (strpos($this->sql, 'SELECT id_city FROM city') !== false) {
            $this->row = ['id_city' => $this->db->cityId];
        }
        return true;
    }

    public function fetch($mode = null) {
        return $this->row;
    }

    public function fetchColumn($column = 0) {
        return $this->row === false ? false : reset($this->row);
    }
}

function expectGuildHarvest(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$_SESSION['id_player'] = 7;
$_REQUEST['xCoord'] = 10;
$_REQUEST['yCoord'] = 20;

$db = new GuildHarvestTestDatabase();
$response = null;
try {
    WorldController::harvestDominance($db);
} catch (GuildHarvestTestResponse $result) {
    $response = $result->payload;
}

expectGuildHarvest(($response['state'] ?? null) === 'ok', 'Eligible guild member should harvest');
expectGuildHarvest(!$db->inTransaction(), 'Successful harvest must commit its transaction');
$updates = array_values(array_filter($db->calls, function ($call) {
    return isset($call[1]) && strpos($call[1], 'UPDATE ') === 0;
}));
expectGuildHarvest(count($updates) === 2, 'Harvest resources and cooldown timestamp must both update');
$lockingRead = array_values(array_filter($db->calls, function ($call) {
    return isset($call[1]) && strpos($call[1], 'FOR UPDATE') !== false;
}));
expectGuildHarvest(count($lockingRead) === 2, 'Dominance and destination city rows must be locked');

$cooldownDb = new GuildHarvestTestDatabase();
$cooldownDb->dominanceRow['last_harvest_at'] = time() - 86399;
$cooldownResponse = null;
try {
    WorldController::harvestDominance($cooldownDb);
} catch (GuildHarvestTestResponse $result) {
    $cooldownResponse = $result->payload;
}

expectGuildHarvest(
    ($cooldownResponse['state'] ?? null) === 'harvest_cooldown',
    'A second harvest inside 24 hours must be rejected'
);
expectGuildHarvest(!$cooldownDb->inTransaction(), 'Cooldown rejection must roll back its transaction');
expectGuildHarvest(
    count(array_filter($cooldownDb->calls, function ($call) {
        return isset($call[1]) && strpos($call[1], 'UPDATE ') === 0;
    })) === 0,
    'Cooldown rejection must not award resources'
);

echo "Guild dominance harvest tests passed\n";
