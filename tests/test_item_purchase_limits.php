<?php

class ItemPurchaseTestResponse extends RuntimeException {
    public $data;

    public function __construct($data) {
        $this->data = $data;
    }
}

function sendJson($data): void {
    throw new ItemPurchaseTestResponse($data);
}

function requireAuthenticatedPlayer($db): array {
    return ['id_player' => 7, 'gold' => $db->gold];
}

class ItemPurchaseTestStatement {
    private $db;
    private $sql;
    private $result = null;
    private $column = null;
    private $affectedRows = 0;

    public function __construct($db, string $sql) {
        $this->db = $db;
        $this->sql = $sql;
    }

    public function execute(array $params = []): bool {
        $this->affectedRows = 0;
        if (strpos($this->sql, 'SELECT `gold` FROM `player`') !== false) {
            $this->result = ['gold' => $this->db->gold];
        } elseif (strpos($this->sql, 'SELECT `amount` FROM `player_item`') !== false) {
            $this->column = $this->db->items[$params[1]] ?? 0;
        } elseif (strpos($this->sql, 'UPDATE `player` SET `gold`') !== false) {
            $amount = (int)$params[0];
            if ($this->db->gold >= $amount) {
                $this->db->gold -= $amount;
                $this->affectedRows = 1;
            }
        } elseif (strpos($this->sql, 'INSERT INTO player_item') !== false) {
            $item = $params[1];
            $this->db->items[$item] = ($this->db->items[$item] ?? 0) + (int)$params[2];
            $this->affectedRows = 1;
        } else {
            throw new RuntimeException('Unexpected SQL in item purchase test: ' . $this->sql);
        }
        return true;
    }

    public function fetch($mode = null) {
        return $this->result;
    }

    public function fetchColumn($column = 0) {
        return $this->column;
    }

    public function rowCount(): int {
        return $this->affectedRows;
    }
}

class ItemPurchaseTestDatabase {
    public $gold = 1000;
    public $items = [];
    private $snapshot = null;

    public function prepare(string $sql): ItemPurchaseTestStatement {
        return new ItemPurchaseTestStatement($this, $sql);
    }

    public function beginTransaction(): bool {
        $this->snapshot = [$this->gold, $this->items];
        return true;
    }

    public function commit(): bool {
        $this->snapshot = null;
        return true;
    }

    public function rollBack(): bool {
        if ($this->snapshot !== null) {
            [$this->gold, $this->items] = $this->snapshot;
            $this->snapshot = null;
        }
        return true;
    }

    public function inTransaction(): bool {
        return $this->snapshot !== null;
    }
}

require_once __DIR__ . '/../caesar-api/controllers/ItemController.php';

function buyTestItem(ItemPurchaseTestDatabase $db, string $item, int $amount): array {
    $_REQUEST = ['item' => $item, 'amount' => $amount];
    try {
        ItemController::buyItem($db);
    } catch (ItemPurchaseTestResponse $response) {
        return $response->data;
    }
    throw new RuntimeException('Item purchase did not return a response');
}

$db = new ItemPurchaseTestDatabase();
$db->items['food_pack'] = 3;
$response = buyTestItem($db, 'food_pack', 2);
$failed = 0;
if (($response['state'] ?? '') !== 'error_prize_limit' || $db->items['food_pack'] !== 3 || $db->gold !== 1000) {
    echo "[FAIL] purchase over the configured limit is rejected without charging\n";
    $failed++;
} else {
    echo "[PASS] purchase over the configured limit is rejected without charging\n";
}

$response = buyTestItem($db, 'food_pack', 1);
if (($response['state'] ?? '') !== 'ok' || $db->items['food_pack'] !== 4 || $db->gold !== 650) {
    echo "[FAIL] purchase up to the configured limit succeeds\n";
    $failed++;
} else {
    echo "[PASS] purchase up to the configured limit succeeds\n";
}

$response = buyTestItem($db, 'bronze_horn', 2);
if (($response['state'] ?? '') !== 'error_prize_limit'
    || isset($db->items['bronze_horn'])
    || $db->gold !== 650) {
    echo "[FAIL] mall purchases respect exchange item limits\n";
    $failed++;
} else {
    echo "[PASS] mall purchases respect exchange item limits\n";
}

$loadPrices = new ReflectionMethod(ItemController::class, 'loadItemPrices');
$loadPrices->setAccessible(true);
$prices = $loadPrices->invoke(null);
$loadOffers = new ReflectionMethod(ItemController::class, 'loadExchangeOffers');
$loadOffers->setAccessible(true);
$offers = $loadOffers->invoke(null);
$limitMethod = new ReflectionMethod(ItemController::class, 'effectiveItemLimits');
$limitMethod->setAccessible(true);
$limits = $limitMethod->invoke(null, $prices, $offers);
$applyLimits = new ReflectionMethod(ItemController::class, 'applyItemLimitsToExchangeOffers');
$applyLimits->setAccessible(true);
$limitedOffers = $applyLimits->invoke(null, $offers, $limits);
if (($limits['festival_blue'] ?? 0) !== 2
    || ($limits['bronze_horn'] ?? 0) !== 1
    || (int)$limitedOffers[23]['max_to_have'] !== 2) {
    echo "[FAIL] overlapping item limits use the strictest configured maximum\n";
    $failed++;
} else {
    echo "[PASS] overlapping item limits use the strictest configured maximum\n";
}

exit($failed > 0 ? 1 : 0);
