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
        'conquered_at' => 1,
        'last_harvest_at' => 1,
        'PlayerName' => 'Test player',
        'GuildName' => 'Test guild',
        'daily_food' => 100,
        'daily_wood' => 200,
        'daily_stone' => 300,
        'daily_metal' => 400,
        'daily_coin' => 500
    ];
    public $playerHarvestRow = false;
    public $playerGuildId = 3;
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
        if (strpos($this->sql, 'SELECT id_guild FROM player') !== false) {
            $this->row = ['id_guild' => $this->db->playerGuildId];
        } elseif (strpos($this->sql, 'FROM guild_dominance_harvest') !== false) {
            $this->row = $this->db->playerHarvestRow;
        } elseif (strpos($this->sql, 'FROM guild_dominance') !== false) {
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
$cityUpdates = array_values(array_filter($db->calls, function ($call) {
    return isset($call[1]) && strpos($call[1], 'UPDATE city') === 0;
}));
expectGuildHarvest(count($cityUpdates) === 1, 'City resources must update');
$harvestInserts = array_values(array_filter($db->calls, function ($call) {
    return isset($call[1]) && strpos($call[1], 'INSERT INTO guild_dominance_harvest') === 0;
}));
expectGuildHarvest(count($harvestInserts) === 1, 'Per-player harvest must be recorded in guild_dominance_harvest');
$lockingRead = array_values(array_filter($db->calls, function ($call) {
    return isset($call[1]) && strpos($call[1], 'FOR UPDATE') !== false;
}));
expectGuildHarvest(count($lockingRead) >= 2, 'Dominance and destination city rows must be locked');

$cooldownDb = new GuildHarvestTestDatabase();
$cooldownDb->playerHarvestRow = ['harvested_at' => time() - 86399];
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
        return isset($call[1]) && strpos($call[1], 'UPDATE city') === 0;
    })) === 0,
    'Cooldown rejection must not award resources'
);

// Verify per-player independence: a second player in the same guild CAN harvest independently
$secondPlayerDb = new GuildHarvestTestDatabase();
$secondPlayerDb->playerHarvestRow = false; // Never harvested
$_SESSION['id_player'] = 15;
$secondPlayerResponse = null;
try {
    WorldController::harvestDominance($secondPlayerDb);
} catch (GuildHarvestTestResponse $result) {
    $secondPlayerResponse = $result->payload;
}
expectGuildHarvest(
    ($secondPlayerResponse['state'] ?? null) === 'ok',
    'Second guild member must be able to harvest independently'
);
$_SESSION['id_player'] = 7; // Reset to 7

$rankDb = new GuildHarvestTestDatabase();
$_GET['xCoord'] = 300;
$_GET['yCoord'] = 100;
$_GET['unitType'] = 181;
$rankResponse = null;
try {
    WorldController::getWorldUnitRank($rankDb);
} catch (GuildHarvestTestResponse $result) {
    $rankResponse = $result->payload;
}

expectGuildHarvest(isset($rankResponse[0]), 'Dominated kingdom should return its controlling guild');
expectGuildHarvest($rankResponse[0]['GuildName'] === 'Test guild', 'Controlling guild name should be returned');
expectGuildHarvest($rankResponse[0]['playerGuildId'] === 3, 'Current player guild should be returned');
expectGuildHarvest($rankResponse[0]['can_harvest'] === true, 'Current controlling guild should be allowed to harvest');
expectGuildHarvest($rankResponse[0]['remaining_sec'] === 0, 'Available harvest should have no remaining cooldown');

$otherGuildDb = new GuildHarvestTestDatabase();
$otherGuildDb->playerGuildId = 4;
$otherGuildResponse = null;
try {
    WorldController::getWorldUnitRank($otherGuildDb);
} catch (GuildHarvestTestResponse $result) {
    $otherGuildResponse = $result->payload;
}
expectGuildHarvest(
    $otherGuildResponse[0]['can_harvest'] === false,
    'A player outside the controlling guild must not be allowed to harvest'
);

$cooldownRankDb = new GuildHarvestTestDatabase();
$cooldownRankDb->playerHarvestRow = ['harvested_at' => time() - 3600];
$cooldownRankResponse = null;
try {
    WorldController::getWorldUnitRank($cooldownRankDb);
} catch (GuildHarvestTestResponse $result) {
    $cooldownRankResponse = $result->payload;
}
expectGuildHarvest(
    $cooldownRankResponse[0]['can_harvest'] === false
        && $cooldownRankResponse[0]['remaining_sec'] > 0
        && $cooldownRankResponse[0]['remaining_sec'] <= 82800,
    'Controlling guild should receive the remaining harvest cooldown'
);

$_REQUEST['xCoord'] = 300;
$_REQUEST['yCoord'] = 100;
$_REQUEST['unitType'] = 180;
$_REQUEST['lvl'] = 11;
$kingdomArmyResponse = null;
try {
    WorldController::refreshWorldUnitLvl($rankDb);
} catch (GuildHarvestTestResponse $result) {
    $kingdomArmyResponse = $result->payload;
}
expectGuildHarvest(
    ($kingdomArmyResponse['state'] ?? null) === 'ok'
        && ($kingdomArmyResponse['ut'] ?? 0) === 180
        && ($kingdomArmyResponse['l'] ?? 0) === 10,
    'Kingdom map level 11 should resolve to supported level 10'
);

echo "Guild dominance harvest tests passed\n";
