<?php

class QueenRepeatPlunderResponse extends RuntimeException {
    public $payload;

    public function __construct($payload) {
        $this->payload = $payload;
    }
}

if (!function_exists('sendJson')) {
    function sendJson($data): void {
        throw new QueenRepeatPlunderResponse($data);
    }
}

require_once __DIR__ . '/../caesar-api/controllers/WorldController.php';

class QueenRepeatPlunderMockDb {
    public $gold = 100;
    public $guildId = 5;
    public $cityId = 14;
    public $dominanceRow = [
        'id_guild'    => 5,
        'unit_type'   => 180,
        'unit_lvl'    => 10,
        'conquered_at'=> 1,
        'PlayerName'  => 'Leader',
        'GuildName'   => 'Legion',
        'daily_food'  => 500000,
        'daily_wood'  => 500000,
        'daily_stone' => 500000,
        'daily_metal' => 100000,
        'daily_coin'  => 50000,
    ];
    public $harvestRow = false;
    public $items = ['bronze_horn' => 2];
    public $cityCredits = [];
    private $inTx = false;

    public function beginTransaction(): bool { $this->inTx = true; return true; }
    public function commit(): bool { $this->inTx = false; return true; }
    public function rollBack(): bool { $this->inTx = false; return true; }
    public function inTransaction(): bool { return $this->inTx; }

    public function prepare(string $sql): QueenRepeatPlunderStmt {
        return new QueenRepeatPlunderStmt($this, $sql);
    }
}

class QueenRepeatPlunderStmt {
    private $db;
    private $sql;
    private $row = false;
    private $affected = 1;

    public function __construct(QueenRepeatPlunderMockDb $db, string $sql) {
        $this->db = $db;
        $this->sql = $sql;
    }

    public function execute(array $params = []): bool {
        $sql = $this->sql;
        if (strpos($sql, 'SELECT * FROM player WHERE id_player') !== false) {
            $this->row = ['id_player' => 7, 'id_guild' => $this->db->guildId, 'gold' => $this->db->gold, 'name' => 'Leader'];
        } elseif (strpos($sql, 'SELECT id_guild FROM player') !== false) {
            $this->row = ['id_guild' => $this->db->guildId];
        } elseif (strpos($sql, 'SELECT gold FROM player') !== false) {
            $this->row = ['gold' => $this->db->gold];
        } elseif (strpos($sql, 'FROM guild_dominance_harvest') !== false) {
            $this->row = $this->db->harvestRow;
        } elseif (strpos($sql, 'FROM guild_dominance') !== false) {
            $this->row = $this->db->dominanceRow;
        } elseif (strpos($sql, 'SELECT id_city FROM city') !== false) {
            $this->row = ['id_city' => $this->db->cityId];
        } elseif (strpos($sql, 'SELECT amount FROM player_item') !== false) {
            $key = $params[1] ?? '';
            $this->row = ['amount' => $this->db->items[$key] ?? 0];
        } elseif (strpos($sql, 'UPDATE player SET gold = gold - ?') !== false) {
            $cost = (int)$params[0];
            if ($this->db->gold >= $cost) {
                $this->db->gold -= $cost;
                $this->affected = 1;
            } else {
                $this->affected = 0;
            }
        } elseif (strpos($sql, 'UPDATE player_item SET amount = amount - 1') !== false) {
            $key = $params[1] ?? '';
            if (($this->db->items[$key] ?? 0) >= 1) {
                $this->db->items[$key]--;
                $this->affected = 1;
            } else {
                $this->affected = 0;
            }
        } elseif (strpos($sql, 'INSERT INTO player_item') !== false) {
            $key = $params[1];
            $amt = (int)$params[2];
            $this->db->items[$key] = ($this->db->items[$key] ?? 0) + $amt;
        } elseif (strpos($sql, 'UPDATE city SET') !== false) {
            $this->db->cityCredits[] = $params;
        } elseif (strpos($sql, 'INSERT INTO guild_dominance_harvest') !== false) {
            $this->db->harvestRow = [
                'harvested_at'      => $params[4],
                'harvest_count'     => $params[5] ?? 1,
                'window_started_at' => $params[6] ?? $params[4],
            ];
        }
        return true;
    }

    public function fetch($mode = null) { return $this->row; }
    public function fetchColumn($column = 0) { return $this->row === false ? false : reset($this->row); }
    public function rowCount(): int { return $this->affected; }
}

function assertPlunder($cond, $msg): void {
    if (!$cond) {
        fwrite(STDERR, "Assertion failed: $msg\n");
        exit(1);
    }
}

$_SESSION['id_player'] = 7;
$_SESSION['id_guild'] = 5;

// 1. getUnitViewPrizes returns all 4 buckets including Plunder and Sp
$_REQUEST['unitType'] = 180;
unset($_REQUEST['lvl'], $_REQUEST['prizeFor']);
$viewRes = null;
try {
    WorldController::getUnitViewPrizes();
} catch (QueenRepeatPlunderResponse $r) {
    $viewRes = $r->payload;
}
assertPlunder(($viewRes['state'] ?? '') === 'ok', 'getUnitViewPrizes succeeds');
assertPlunder(!empty($viewRes['buckets']['Plunder']), 'Plunder bucket returned');
assertPlunder(!empty($viewRes['buckets']['Sp']), 'Sp bucket returned');
assertPlunder(empty($viewRes['buckets']['Lose']), 'Loser has no prizes (Lose bucket empty)');

// 2. First free plunder on Queen 180 grants BOTH city resources and raw Plunder PrizeList items
$db = new QueenRepeatPlunderMockDb();
$_REQUEST['xCoord'] = 120;
$_REQUEST['yCoord'] = 80;
unset($_REQUEST['payMethod']);
$freeRes = null;
try {
    WorldController::harvestDominance($db);
} catch (QueenRepeatPlunderResponse $r) {
    $freeRes = $r->payload;
}
assertPlunder(($freeRes['state'] ?? '') === 'ok', 'Free Queen plunder succeeds');
assertPlunder(count($db->cityCredits) === 1, 'Free harvest credits daily city resources');
$freeItems = array_column($freeRes['PrizeList'] ?? [], 'amount', 'Item');
assertPlunder(($freeItems['bronze_bar'] ?? 0) >= 1 && ($freeItems['archit_b'] ?? 0) >= 1, 'Queen 180 Plunder grants guaranteed bronze_bar & archit_b');

// 3. checkReapCharge after free plunder reports reapNeedPay=true, coin=10, bronze_horn option, previewItems
$chargeRes = null;
try {
    WorldController::checkReapCharge($db);
} catch (QueenRepeatPlunderResponse $r) {
    $chargeRes = $r->payload;
}
assertPlunder(($chargeRes['reapNeedPay'] ?? false) === true, 'Reap requires payment after free harvest');
assertPlunder(($chargeRes['coin'] ?? 0) === 10, 'First extra plunder costs 10 Gold');
assertPlunder(($chargeRes['requiredItem']['Item'] ?? '') === 'bronze_horn', 'Queen 180 repeat item is bronze_horn');
assertPlunder(($chargeRes['requiredItem']['owned'] ?? 0) === 2, 'Owned horn count reported');
assertPlunder(!empty($chargeRes['previewItems']), 'Plunder preview items returned');

// 4. Repeat plunder #1 via Gold deducts 10 Gold and scales item quantities 2x without duplicating daily city resources
$_REQUEST['payMethod'] = 'gold';
$goldRes = null;
try {
    WorldController::harvestDominance($db);
} catch (QueenRepeatPlunderResponse $r) {
    $goldRes = $r->payload;
}
assertPlunder(($goldRes['state'] ?? '') === 'ok', 'Gold repeat plunder succeeds');
assertPlunder($db->gold === 90 && ($goldRes['goldRemaining'] ?? -1) === 90, '10 Gold deducted cleanly');
assertPlunder(count($db->cityCredits) === 1, 'Repeat plunder does not re-credit free daily city resources');
$goldItems = array_column($goldRes['PrizeList'] ?? [], 'amount', 'Item');
assertPlunder(($goldItems['bronze_bar'] ?? 0) === 2, 'Repeat #1 doubles base guaranteed Plunder quantity');

// 5. Repeat plunder #2 via Item consumes 1 bronze_horn and scales item quantities 3x
$_REQUEST['payMethod'] = 'item';
$itemRes = null;
try {
    WorldController::harvestDominance($db);
} catch (QueenRepeatPlunderResponse $r) {
    $itemRes = $r->payload;
}
assertPlunder(($itemRes['state'] ?? '') === 'ok', 'Item repeat plunder succeeds');
assertPlunder($db->items['bronze_horn'] === 1, 'Consumed 1 bronze_horn cleanly');
$repeatItems = array_column($itemRes['PrizeList'] ?? [], 'amount', 'Item');
assertPlunder(($repeatItems['bronze_bar'] ?? 0) === 3, 'Repeat #2 triples base guaranteed Plunder quantity');

// 6. Insufficient Gold check
$db->gold = 5;
$_REQUEST['payMethod'] = 'gold';
$lowGoldRes = null;
try {
    WorldController::harvestDominance($db);
} catch (QueenRepeatPlunderResponse $r) {
    $lowGoldRes = $r->payload;
}
assertPlunder(($lowGoldRes['state'] ?? '') === 'not_enough_gold', 'Insufficient gold rejected cleanly');

// 7. Daily limit check (6 harvests = 1 free + 5 extra)
$db->harvestRow['harvest_count'] = 6;
$limitRes = null;
try {
    WorldController::harvestDominance($db);
} catch (QueenRepeatPlunderResponse $r) {
    $limitRes = $r->payload;
}
assertPlunder(($limitRes['state'] ?? '') === 'daily_reap_limit', 'Exceeding daily limit rejected');
assertPlunder(($limitRes['reachDailyReapLimit'] ?? false) === true, 'reachDailyReapLimit flag set');

echo "Queen repeat plunder and prize bucket tests passed\n";
