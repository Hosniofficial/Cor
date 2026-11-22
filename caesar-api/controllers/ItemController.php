<?php
// ItemController.php - Complete Item Buying, Inventory Consumption, Buffs, Relocation, Army Packs, and Mystery Boxes

require_once __DIR__ . '/../services/HeroPowerService.php';
require_once __DIR__ . '/../services/ResourceService.php';

class ItemController {
    public static function handle($uri, $db) {
        switch ($uri) {
            case '/api/AItem/getEquipmentPower':
                self::getEquipmentPower();
                return true;
            case '/api/AItem/getAllItemPrice':
                self::getAllItemPrice();
                return true;
            case '/api/AItem/buyItem':
                self::buyItem($db);
                return true;
            case '/api/AItem/openItemBox':
                self::handleBoxUse('useGiftBox', $db);
                return true;
            case '/api/AExchange/buyExchange':
                self::buyExchange($db);
                return true;
            case '/api/AExchange/getExchangeItem':
                self::getExchangeItem($db);
                return true;
            case '/api/AContribute/contribute':
                sendJson(['state' => 'ok']);
                return true;
            case '/api/AContribute/upgradeEquip':
                self::upgradeEquip($db);
                return true;

            // AItemUse
            case '/api/AItemUse/useWheat':
            case '/api/AItemUse/useWood':
            case '/api/AItemUse/useStone':
            case '/api/AItemUse/useMetal':
            case '/api/AItemUse/useCoin':
            case '/api/AItemUse/useGoldPack':
            case '/api/AItemUse/useCeaseFire':
            case '/api/AItemUse/useItemProtect':
            case '/api/AItemUse/useAttackAdvancer':
            case '/api/AItemUse/useDefenceAdvancer':
            case '/api/AItemUse/useMotivSpeech':
            case '/api/AItemUse/useProtPop':
            case '/api/AItemUse/useMedicalStatue':
            case '/api/AItemUse/useTheatrics':
            case '/api/AItemUse/useFreedomHelp':
            case '/api/AItemUse/useRandomMove':
            case '/api/AItemUse/useCertainMove':
            case '/api/AItemUse/useArenaAttempt':
            case '/api/AItemUse/useArenaExpPack':
            case '/api/AItemUse/useFlag':
                $action = substr($uri, strlen('/api/AItemUse/'));
                self::handleItemUse($action, $db);
                return true;

            // AItemArmyPack
            case '/api/AItemArmyPack/useArmyPackA100':
            case '/api/AItemArmyPack/useArmyPackA1000':
            case '/api/AItemArmyPack/useArmyPackB100':
            case '/api/AItemArmyPack/useArmyPackB1000':
            case '/api/AItemArmyPack/useArmyPackC100':
            case '/api/AItemArmyPack/useArmyPackC1000':
            case '/api/AItemArmyPack/useArmyPackD100':
            case '/api/AItemArmyPack/useArmyPackD1000':
            case '/api/AItemArmyPack/useArmyPackE100':
            case '/api/AItemArmyPack/useArmyPackE1000':
            case '/api/AItemArmyPack/useArmyPackF100':
            case '/api/AItemArmyPack/useArmyPackF1000':
            case '/api/AItemArmyPack/useArmyPackMini':
            case '/api/AItemArmyPack/useArmyPackMedium':
            case '/api/AItemArmyPack/useArmyPackLarge':
                $action = substr($uri, strlen('/api/AItemArmyPack/'));
                self::handleArmyPack($action, $db);
                return true;

            // AItemBoxUse
            case '/api/AItemBoxUse/useBeginnerPack':
            case '/api/AItemBoxUse/useGiftBox':
            case '/api/AItemBoxUse/useGoldenBox':
            case '/api/AItemBoxUse/useWoodBox':
            case '/api/AItemBoxUse/useArmyBox':
            case '/api/AItemBoxUse/useResourcePacks':
            case '/api/AItemBoxUse/useHeroPacks':
                $action = substr($uri, strlen('/api/AItemBoxUse/'));
                self::handleBoxUse($action, $db);
                return true;
        }

        // Fallbacks for any prefix
        if (strpos($uri, '/api/AItemUse/') === 0) {
            $action = substr($uri, strlen('/api/AItemUse/'));
            self::handleItemUse($action, $db);
            return true;
        }
        if (strpos($uri, '/api/AItemArmyPack/') === 0) {
            $action = substr($uri, strlen('/api/AItemArmyPack/'));
            self::handleArmyPack($action, $db);
            return true;
        }
        if (strpos($uri, '/api/AItemBoxUse/') === 0) {
            $action = substr($uri, strlen('/api/AItemBoxUse/'));
            self::handleBoxUse($action, $db);
            return true;
        }
        return false;
    }

    private static function getPlayer($db) {
        return requireAuthenticatedPlayer($db);
    }

    public static function upgradeEquip($db) {
        $player = self::getPlayer($db);
        $idEquip = isset($_REQUEST['idEquip']) ? (int)$_REQUEST['idEquip'] : 0;
        if ($idEquip <= 0) {
            sendJson(['state' => 'error_no_equip']);
            return;
        }

        $equip = requirePlayerOwnedEquip($db, $player, $idEquip);
        if (!$equip) {
            sendJson(['state' => 'error_not_found']);
            return;
        }

        $secondaryParts = ['belt', 'necklace', 'pendant', 'ring', 'steed'];
        if (!in_array($equip['part'], $secondaryParts)) {
            // Primary equipments cannot be upgraded by level
            sendJson(['state' => 'error_not_upgradable', 'message' => 'المعدات الأساسية لا تتطور بالمستوى']);
            return;
        }

        $currLvl = (int)$equip['lvl'];
        if ($currLvl >= 9) {
            sendJson(['state' => 'error_max_lvl', 'message' => 'المعدة وصلت لأقصى مستوى تطوير (م9)']);
            return;
        }

        $newLvl = $currLvl + 1;
        $db->prepare("UPDATE player_equip SET lvl = ? WHERE id_equip = ?")->execute([$newLvl, $idEquip]);

        $equip['lvl'] = $newLvl;
        foreach ($equip as $k => $v) {
            if (is_numeric($v)) $equip[$k] = (int)$v;
        }

        sendJson([
            'state' => 'ok',
            'Equip' => $equip
        ]);
    }

    public static function deductPlayerItem($db, $playerId, $itemKey, $amount = 1) {
        $amount = max(1, (int)$amount);
        $upd = $db->prepare(
            'UPDATE `player_item` SET `amount` = `amount` - ?'
            . ' WHERE `id_player` = ? AND `id_item` = ? AND `amount` >= ?'
        );
        $upd->execute([$amount, $playerId, $itemKey, $amount]);
        if ($upd->rowCount() !== 1) {
            sendJson(['state' => 'error_no_item']);
        }

        $db->prepare('DELETE FROM `player_item` WHERE `id_player` = ? AND `id_item` = ? AND `amount` <= 0')
           ->execute([$playerId, $itemKey]);
        return true;
    }

    public static function runItemTransaction($db, callable $operation): void {
        $db->beginTransaction();
        try {
            $operation();
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    private static function addPlayerItem($db, $playerId, $itemKey, $amount = 1) {
        $stmt = $db->prepare("INSERT INTO player_item (id_player, id_item, amount) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE amount = amount + VALUES(amount)");
        $stmt->execute([$playerId, $itemKey, $amount]);
    }

    private static function loadItemPrices(): array {
        $file = dirname(__DIR__, 2) . '/caesar-private/data/item-prices.json';
        $raw = is_file($file) && is_readable($file) ? file_get_contents($file) : false;
        $prices = $raw === false ? null : json_decode($raw, true);
        if (!is_array($prices) || !$prices) {
            throw new RuntimeException('Item price data is unavailable or invalid');
        }

        $indexed = [];
        foreach ($prices as $price) {
            if (!is_array($price) || empty($price['id_item']) || !isset($price['gold'])) {
                throw new RuntimeException('Item price data contains an invalid entry');
            }
            $indexed[$price['id_item']] = $price;
        }
        return $indexed;
    }

    private static function effectiveItemLimits(array $prices, array $offers): array {
        $limits = [];
        foreach ($prices as $item => $definition) {
            $limit = max(0, (int)($definition['prizeLimit'] ?? 0));
            if ($limit > 0) {
                $limits[$item] = $limit;
            }
        }

        // Keep one inventory cap regardless of whether an item is bought or exchanged.
        foreach ($offers as $offer) {
            $reward = json_decode((string)($offer['reword'] ?? ''), true);
            if (!is_array($reward) || ($reward['type'] ?? '') !== 'matrial') {
                continue;
            }

            $item = (string)($reward['matrial'] ?? '');
            $limit = max(0, (int)($offer['max_to_have'] ?? 0));
            if ($item === '' || $limit === 0) {
                continue;
            }
            $limits[$item] = isset($limits[$item]) ? min($limits[$item], $limit) : $limit;
        }
        return $limits;
    }

    private static function applyItemLimitsToExchangeOffers(array $offers, array $limits): array {
        foreach ($offers as &$offer) {
            $reward = json_decode((string)($offer['reword'] ?? ''), true);
            if (is_array($reward) && ($reward['type'] ?? '') === 'matrial') {
                $item = (string)($reward['matrial'] ?? '');
                $offer['max_to_have'] = (int)($limits[$item] ?? 0);
            }
        }
        unset($offer);
        return $offers;
    }

    public static function getAllItemPrice() {
        try {
            $prices = self::loadItemPrices();
            $offers = self::loadExchangeOffers();
            $limits = self::effectiveItemLimits($prices, $offers);
        } catch (RuntimeException $error) {
            error_log('[ItemController] ' . $error->getMessage());
            http_response_code(500);
            sendJson(['state' => 'error', 'msg' => 'Item limit data is unavailable']);
        }

        foreach ($prices as $item => &$definition) {
            $definition['prizeLimit'] = (int)($limits[$item] ?? 0);
        }
        unset($definition);

        header('Content-Type: text/plain; charset=utf-8');
        echo json_encode(array_values($prices), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    public static function getEquipmentPower(): void {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            http_response_code(405);
            sendJson(['state' => 'error_method_not_allowed']);
        }

        $equip = $_GET['equip'] ?? '';
        $part = $_GET['part'] ?? '';
        $rawLevel = $_GET['lvl'] ?? null;
        $level = is_string($rawLevel) ? filter_var($rawLevel, FILTER_VALIDATE_INT) : false;
        $validParts = ['sword', 'helmet', 'shield', 'armor', 'boot', 'belt', 'necklace', 'pendant', 'ring', 'steed'];
        if (!is_string($equip)
            || preg_match('/\A[a-z0-9]+\z/i', $equip) !== 1
            || !is_string($part)
            || !in_array($part, $validParts, true)
            || $level === false
            || $level < 1
            || $level > 9) {
            http_response_code(400);
            sendJson(['state' => 'error_invalid_equipment']);
        }

        $file = dirname(__DIR__, 2) . '/caesar-private/data/equipment-power.json';
        $raw = is_file($file) && is_readable($file) ? file_get_contents($file) : false;
        $entries = $raw === false ? null : json_decode($raw, true);
        if (!is_array($entries)) {
            error_log('[ItemController] Equipment power data is unavailable or invalid');
            http_response_code(500);
            sendJson(['state' => 'error_equipment_power_unavailable']);
        }

        foreach ($entries as $entry) {
            if (!is_array($entry)
                || ($entry['equip'] ?? null) !== $equip
                || ($entry['part'] ?? null) !== $part
                || (int)($entry['lvl'] ?? 0) !== $level) {
                continue;
            }

            $power = [];
            foreach (['attack', 'defence', 'vitality', 'damage', 'break', 'anti_break', 'strike', 'immunity', 'sp_attr'] as $field) {
                if (!isset($entry[$field]) || !is_numeric($entry[$field])) {
                    error_log('[ItemController] Equipment power entry is invalid for ' . $equip . '_' . $part . '_' . $level);
                    http_response_code(500);
                    sendJson(['state' => 'error_equipment_power_invalid']);
                }
                $power[$field] = (int)$entry[$field];
            }

            sendJson(['state' => 'ok', 'Power' => $power]);
        }

        http_response_code(404);
        sendJson(['state' => 'error_equipment_not_found']);
    }

    private static function loadExchangeOffers(): array {
        $file = dirname(__DIR__, 2) . '/caesar-private/data/exchange-offers.json';
        $raw = is_file($file) && is_readable($file) ? file_get_contents($file) : false;
        $offers = $raw === false ? null : json_decode($raw, true);
        if (!is_array($offers) || !$offers) {
            throw new RuntimeException('Exchange offer data is unavailable or invalid');
        }

        $indexed = [];
        $resourceTypes = ['food', 'wood', 'stone', 'metal', 'coin'];
        foreach ($offers as $offer) {
            if (!is_array($offer)) {
                throw new RuntimeException('Exchange offer data contains an invalid entry');
            }
            $id = filter_var($offer['id_ex'] ?? null, FILTER_VALIDATE_INT);
            $playerMax = filter_var($offer['player_max'] ?? null, FILTER_VALIDATE_INT);
            $maxToHave = filter_var($offer['max_to_have'] ?? null, FILTER_VALIDATE_INT);
            $playerPorm = filter_var($offer['player_porm'] ?? null, FILTER_VALIDATE_INT);
            $serverMax = ($offer['server_max'] ?? null) === null
                ? null
                : filter_var($offer['server_max'], FILTER_VALIDATE_INT);
            $requirements = json_decode((string)($offer['req'] ?? ''), true);
            $reward = json_decode((string)($offer['reword'] ?? ''), true);

            if ($id === false || $id <= 0 || isset($indexed[$id])
                || $playerMax === false || $playerMax <= 0
                || $maxToHave === false || $maxToHave < 0
                || $playerPorm === false || $playerPorm < 0
                || ($serverMax !== null && ($serverMax === false || $serverMax < 0))
                || !is_array($requirements) || !$requirements
                || !is_array($reward)) {
                throw new RuntimeException('Exchange offer data contains invalid limits or payload');
            }

            foreach ($requirements as $requirement) {
                $cost = is_array($requirement)
                    ? filter_var($requirement['amount'] ?? null, FILTER_VALIDATE_INT)
                    : false;
                $type = $requirement['type'] ?? '';
                if ($cost === false || $cost <= 0 || $cost > intdiv(PHP_INT_MAX, 1000)
                    || !in_array($type, ['resource', 'matrial', 'equip'], true)
                    || ($type === 'resource' && !in_array($requirement['resource_type'] ?? '', $resourceTypes, true))
                    || ($type === 'matrial' && empty($requirement['matrial']))
                    || ($type === 'equip' && (empty($requirement['Equip']) || empty($requirement['Part'])
                        || filter_var($requirement['lvl'] ?? null, FILTER_VALIDATE_INT) === false))) {
                    throw new RuntimeException('Exchange offer contains an invalid requirement');
                }
            }

            $rewardAmount = filter_var($reward['amount'] ?? null, FILTER_VALIDATE_INT);
            if ($rewardAmount === false || $rewardAmount <= 0 || $rewardAmount > intdiv(PHP_INT_MAX, 1000)
                || !in_array($reward['type'] ?? '', ['matrial', 'equip'], true)
                || (($reward['type'] ?? '') === 'matrial' && empty($reward['matrial']))
                || (($reward['type'] ?? '') === 'equip' && (empty($reward['Equip']) || empty($reward['Part'])
                    || filter_var($reward['lvl'] ?? null, FILTER_VALIDATE_INT) === false))) {
                throw new RuntimeException('Exchange offer contains an invalid reward');
            }

            $indexed[$id] = $offer;
        }

        return $indexed;
    }

    public static function getExchangeItem($db): void {
        $player = self::getPlayer($db);
        try {
            $offers = self::loadExchangeOffers();
            $prices = self::loadItemPrices();
            $limits = self::effectiveItemLimits($prices, $offers);
            $offers = self::applyItemLimitsToExchangeOffers($offers, $limits);
        } catch (RuntimeException $error) {
            error_log('[ItemController] ' . $error->getMessage());
            http_response_code(500);
            sendJson(['state' => 'error', 'msg' => 'Item limit data is unavailable']);
        }

        $usageStmt = $db->prepare(
            'SELECT `id_exchange`, `trade_count`
             FROM `player_exchange_usage`
             WHERE `id_player` = ? AND `usage_date` = UTC_DATE()'
        );
        $usageStmt->execute([(int)$player['id_player']]);
        $usage = [];
        foreach ($usageStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $usage[(int)$row['id_exchange']] = (int)$row['trade_count'];
        }

        $limitedOffers = array_filter(
            $offers,
            static function ($offer) {
                return $offer['server_max'] !== null;
            }
        );
        if ($limitedOffers) {
            $placeholders = implode(',', array_fill(0, count($limitedOffers), '?'));
            $serverUsageStmt = $db->prepare(
                'SELECT `id_exchange`, `trade_count`
                 FROM `exchange_server_usage`
                 WHERE `id_exchange` IN (' . $placeholders . ')'
            );
            $serverUsageStmt->execute(array_keys($limitedOffers));
            foreach ($serverUsageStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                $offers[(int)$row['id_exchange']]['server_take'] = (int)$row['trade_count'];
            }
        }

        foreach ($offers as $id => &$offer) {
            $offer['take_times'] = $usage[$id] ?? 0;
            unset($offer['server_max'], $offer['server_take']);
        }
        unset($offer);

        header('Content-Type: text/plain; charset=utf-8');
        echo json_encode(array_values($offers), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    public static function buyItem($db) {
        $player = self::getPlayer($db);
        if (!$player) {
            sendJson(['state' => 'error', 'msg' => 'Player not found']);
        }
        $playerId = (int)$player['id_player'];
        $item = isset($_REQUEST['item']) ? trim($_REQUEST['item']) : '';
        $amount = filter_var($_REQUEST['amount'] ?? 1, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if ($item === '' || $amount === false) {
            sendJson(['state' => 'error_invalid_amount']);
        }

        try {
            $prices = self::loadItemPrices();
            $offers = self::loadExchangeOffers();
            $limits = self::effectiveItemLimits($prices, $offers);
        } catch (RuntimeException $error) {
            error_log('[ItemController] ' . $error->getMessage());
            http_response_code(500);
            sendJson(['state' => 'error', 'msg' => 'Item limit data is unavailable']);
        }
        $itemDefinition = $prices[$item] ?? null;
        if ($itemDefinition === null) {
            sendJson(['state' => 'error', 'msg' => 'invalid_item']);
        }

        $itemPrice = (int)($itemDefinition['gold'] ?? 0);
        if ($itemPrice <= 0) {
            sendJson(['state' => 'item_not_for_sale']);
        }
        $prizeLimit = (int)($limits[$item] ?? 0);
        if ($itemPrice > 0 && $amount > intdiv(PHP_INT_MAX, $itemPrice)) {
            sendJson(['state' => 'error_invalid_amount']);
        }
        $totalCost = $itemPrice * $amount;

        $rejection = null;
        $ownedAmount = 0;
        $gold = 0;
        try {
            $db->beginTransaction();
            $playerStmt = $db->prepare(
                'SELECT `gold` FROM `player` WHERE `id_player` = ? FOR UPDATE'
            );
            $playerStmt->execute([$playerId]);
            $lockedPlayer = $playerStmt->fetch(PDO::FETCH_ASSOC);
            if (!$lockedPlayer) {
                $rejection = ['state' => 'error_auth'];
            } else {
                $gold = (int)$lockedPlayer['gold'];
                $itemStmt = $db->prepare(
                    'SELECT `amount` FROM `player_item` WHERE `id_player` = ? AND `id_item` = ? FOR UPDATE'
                );
                $itemStmt->execute([$playerId, $item]);
                $ownedAmount = (int)($itemStmt->fetchColumn() ?: 0);

                if ($prizeLimit > 0 && ($ownedAmount > $prizeLimit - $amount)) {
                    $rejection = [
                        'state' => 'error_prize_limit',
                        'remaining' => max(0, $prizeLimit - $ownedAmount),
                    ];
                } elseif ($gold < $totalCost) {
                    $rejection = ['state' => 'not_enough_gold'];
                } else {
                    if ($totalCost > 0) {
                        $deduct = $db->prepare(
                            'UPDATE `player` SET `gold` = `gold` - ?'
                            . ' WHERE `id_player` = ? AND `gold` >= ?'
                        );
                        $deduct->execute([$totalCost, $playerId, $totalCost]);
                        if ($deduct->rowCount() !== 1) {
                            $rejection = ['state' => 'not_enough_gold'];
                        }
                    }

                    if ($rejection === null) {
                        self::addPlayerItem($db, $playerId, $item, $amount);
                    }
                }
            }

            if ($rejection !== null) {
                $db->rollBack();
            } else {
                $db->commit();
            }
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[ItemController] Item purchase failed: ' . $error->getMessage());
            http_response_code(500);
            sendJson(['state' => 'error', 'msg' => 'Item purchase could not be completed']);
        }

        if ($rejection !== null) {
            sendJson($rejection);
        }

        sendJson(['state' => 'ok', 'gold' => $gold - $totalCost]);
    }

    public static function handleItemUse($action, $db) {
        $player = self::getPlayer($db);
        $playerId = (int)$player['id_player'];
        $item = isset($_REQUEST['Item']) ? trim($_REQUEST['Item']) : '';
        $amount = ResourceService::parseAmount($_REQUEST['amount'] ?? '1');
        if ($amount === null || $amount <= 0 || $amount > 1000000) {
            sendJson(['state' => 'error_invalid_amount']);
        }
        $idCity = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 0;
        if ($idCity <= 0) {
            $cStmt = $db->prepare("SELECT * FROM city WHERE id_player = ? ORDER BY id_city ASC LIMIT 1");
            $cStmt->execute([$playerId]);
            $city = $cStmt->fetch();
            $idCity = (int)($city['id_city'] ?? 0);
        }
        $city = requirePlayerOwnedCity($db, $player, $idCity);

        $now = time();

        switch ($action) {
            case 'useFlag':
                $validFlags = [
                    'flag_england', 'flag_france', 'flag_germany', 'flag_magul',
                    'flag_macdoni', 'flag_roma', 'flag_spain', 'flag_cartaga',
                    'flag_egypt', 'flag_greek'
                ];
                if (!in_array($item, $validFlags, true)) {
                    sendJson(['state' => 'error_invalid_item']);
                    return;
                }
                if (!isset($_REQUEST['amount']) || filter_var($_REQUEST['amount'], FILTER_VALIDATE_INT, [
                    'options' => ['min_range' => 1, 'max_range' => 1],
                ]) === false) {
                    sendJson(['state' => 'error_invalid_amount']);
                    return;
                }

                $db->beginTransaction();
                try {
                    $cityStmt = $db->prepare(
                        'SELECT `id_city` FROM `city` WHERE `id_city` = ? AND `id_player` = ? FOR UPDATE'
                    );
                    $cityStmt->execute([$idCity, $playerId]);
                    if ($cityStmt->fetchColumn() === false) {
                        $db->rollBack();
                        sendJson(['state' => 'error_auth']);
                        return;
                    }

                    $deduct = $db->prepare(
                        'UPDATE `player_item`
                         SET `amount` = `amount` - 1
                         WHERE `id_player` = ? AND `id_item` = ? AND `amount` >= 1'
                    );
                    $deduct->execute([$playerId, $item]);
                    if ($deduct->rowCount() !== 1) {
                        $db->rollBack();
                        sendJson(['state' => 'error_no_item']);
                        return;
                    }

                    $db->prepare(
                        'DELETE FROM `player_item`
                         WHERE `id_player` = ? AND `id_item` = ? AND `amount` <= 0'
                    )->execute([$playerId, $item]);
                    $db->prepare(
                        'UPDATE `city`
                         SET `flag` = ?, `flag_expires_at` = ?, `flag_tag` = \'\'
                         WHERE `id_city` = ? AND `id_player` = ?'
                    )->execute([$item, $now + (7 * 86400), $idCity, $playerId]);
                    $db->commit();

                    $freshCityStmt = $db->prepare('SELECT * FROM `city` WHERE `id_city` = ?');
                    $freshCityStmt->execute([$idCity]);
                    $freshCity = $freshCityStmt->fetch(PDO::FETCH_ASSOC);
                    foreach ($freshCity as $key => $value) {
                        if (is_numeric($value)) {
                            $freshCity[$key] = (int)$value;
                        }
                    }
                    sendJson([
                        'state' => 'ok',
                        'City' => $freshCity,
                        'FlagExpiresAt' => (int)$freshCity['flag_expires_at'],
                        'FlagTag' => (string)$freshCity['flag_tag'],
                    ]);
                    return;
                } catch (Throwable $error) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    throw $error;
                }

            case 'useWheat':
            case 'useWood':
            case 'useStone':
            case 'useMetal':
            case 'useCoin':
                $col = strtolower(str_replace('use', '', $action));
                if ($col === 'wheat') $rField = 'food';
                elseif ($col === 'coin') $rField = 'coin';
                else $rField = $col;

                // Check if this is an instant resource tier pack (e.g. food_a..e, wood_a..e, stone_a..e, metal_a..e, coin_a..e)
                if (preg_match('/^(food|wood|stone|metal|coin)_([a-d])$/', $item, $m)) {
                    $tierVals = ['a' => 1500, 'b' => 5000, 'c' => 100000, 'd' => 350000];
                    $resType = $m[1];
                    $addVal = $tierVals[$m[2]] * $amount;
                } else {
                    // Statue item (wheat_1/7, wood_1/7, stone_1/7, metal_1/7, coin_1/7)
                    $duration = (strpos($item, '_7') !== false) ? 86400 * 7 * $amount : 86400 * $amount;
                    $resBoost = (strpos($item, '_7') !== false ? 100000 : 10000) * $amount;
                    $addVal = $resBoost;
                    $resType = $rField;
                }

                $db->beginTransaction();
                try {
                    self::deductPlayerItem($db, $playerId, $item, $amount);
                    if (!ResourceService::creditCity($db, $idCity, $playerId, [$resType => $addVal])) {
                        throw new ResourceLimitException('Resource item would exceed the city balance limit');
                    }
                    if (!preg_match('/^(food|wood|stone|metal|coin)_([a-d])$/', $item)) {
                        $stmt = $db->prepare("UPDATE player_state SET `$col` = GREATEST(`$col`, ?) + ? WHERE id_player = ?");
                        $stmt->execute([$now, $duration, $playerId]);
                    }
                    $db->commit();
                } catch (ResourceLimitException $error) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    sendJson(['state' => 'error_resource_limit', 'msg' => 'ستتجاوز مكافأة العنصر الحد الأقصى لموارد المدينة']);
                } catch (Throwable $error) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    throw $error;
                }

                $freshCity = $db->query("SELECT * FROM city WHERE id_city = $idCity")->fetch();
                foreach ($freshCity as $k => $v) { if (is_numeric($v)) $freshCity[$k] = (int)$v; }
                sendJson(['state' => 'ok', 'City' => $freshCity, 'CityRes' => $freshCity]);
                break;

            case 'useGoldPack':
                $goldVal = 5;
                if (preg_match('/gold_(\d+)/', $item, $m)) {
                    $goldVal = (int)$m[1];
                }
                $totalGold = $goldVal * $amount;
                self::runItemTransaction($db, function () use ($db, $playerId, $item, $amount, $totalGold) {
                    self::deductPlayerItem($db, $playerId, $item, $amount);
                    $goldStmt = $db->prepare("UPDATE player SET gold = gold + ? WHERE id_player = ?");
                    $goldStmt->execute([$totalGold, $playerId]);
                    if ($goldStmt->rowCount() !== 1) {
                        throw new RuntimeException('Gold pack could not update the player balance');
                    }
                });
                sendJson(['state' => 'ok', 'gold' => (int)$player['gold'] + $totalGold]);
                break;

            case 'useCeaseFire':
            case 'useItemProtect':
                self::runItemTransaction($db, function () use ($db, $playerId, $item, $amount, $now) {
                    self::deductPlayerItem($db, $playerId, $item, $amount);
                    $stmt = $db->prepare("UPDATE player_state SET `peace` = GREATEST(`peace`, ?) + ? WHERE id_player = ?");
                    $stmt->execute([$now, 43200 * $amount, $playerId]);
                    if ($stmt->rowCount() !== 1) {
                        throw new RuntimeException('Peace shield item could not update player state');
                    }
                });
                sendJson(['state' => 'ok']);
                break;

            case 'useAttackAdvancer':
                self::runItemTransaction($db, function () use ($db, $playerId, $item, $amount, $now) {
                    self::deductPlayerItem($db, $playerId, $item, $amount);
                    $stmt = $db->prepare("UPDATE player_state SET `attack_10` = GREATEST(`attack_10`, ?) + ? WHERE id_player = ?");
                    $stmt->execute([$now, 86400 * $amount, $playerId]);
                    if ($stmt->rowCount() !== 1) {
                        throw new RuntimeException('Attack boost item could not update player state');
                    }
                });
                sendJson(['state' => 'ok']);
                break;

            case 'useDefenceAdvancer':
                self::runItemTransaction($db, function () use ($db, $playerId, $item, $amount, $now) {
                    self::deductPlayerItem($db, $playerId, $item, $amount);
                    $stmt = $db->prepare("UPDATE player_state SET `defence_10` = GREATEST(`defence_10`, ?) + ? WHERE id_player = ?");
                    $stmt->execute([$now, 86400 * $amount, $playerId]);
                    if ($stmt->rowCount() !== 1) {
                        throw new RuntimeException('Defence boost item could not update player state');
                    }
                });
                sendJson(['state' => 'ok']);
                break;

            case 'useMotivSpeech':
                $dur = (strpos($item, '_7') !== false) ? 86400 * 7 * $amount : 216000 * $amount;
                self::runItemTransaction($db, function () use ($db, $playerId, $item, $amount, $now, $dur) {
                    self::deductPlayerItem($db, $playerId, $item, $amount);
                    $stmt = $db->prepare("UPDATE player_state SET `motiv` = GREATEST(`motiv`, ?) + ? WHERE id_player = ?");
                    $stmt->execute([$now, $dur, $playerId]);
                    if ($stmt->rowCount() !== 1) {
                        throw new RuntimeException('Motivational speech item could not update player state');
                    }
                });
                sendJson(['state' => 'ok']);
                break;

            case 'useProtPop':
                $popBonus = max(100, round((int)$city['pop_max'] * 0.20 * $amount));
                self::runItemTransaction($db, function () use ($db, $playerId, $item, $amount, $idCity, $popBonus, $now) {
                    self::deductPlayerItem($db, $playerId, $item, $amount);
                    $cityStmt = $db->prepare('SELECT 1 FROM city WHERE id_city = ? AND id_player = ?');
                    $cityStmt->execute([$idCity, $playerId]);
                    if ($cityStmt->fetchColumn() === false) {
                        throw new RuntimeException('Population item city is no longer owned by the player');
                    }
                    $db->prepare("UPDATE city SET `pop` = LEAST(`pop_max`, `pop` + ?) WHERE id_city = ? AND id_player = ?")
                        ->execute([$popBonus, $idCity, $playerId]);
                    $stateStmt = $db->prepare("UPDATE player_state SET `prot_pop` = GREATEST(`prot_pop`, ?) + ? WHERE id_player = ?");
                    $stateStmt->execute([$now, 86400 * $amount, $playerId]);
                    if ($stateStmt->rowCount() !== 1) {
                        throw new RuntimeException('Population protection item could not update player state');
                    }
                });

                $freshCity = $db->query("SELECT * FROM city WHERE id_city = $idCity")->fetch();
                foreach ($freshCity as $k => $v) { if (is_numeric($v)) $freshCity[$k] = (int)$v; }
                sendJson(['state' => 'ok', 'City' => $freshCity, 'CityRes' => $freshCity]);
                break;

            case 'useMedicalStatue':
                self::runItemTransaction($db, function () use ($db, $playerId, $item, $amount, $now) {
                    self::deductPlayerItem($db, $playerId, $item, $amount);
                    $stmt = $db->prepare("UPDATE player_state SET `medical` = GREATEST(`medical`, ?) + ? WHERE id_player = ?");
                    $stmt->execute([$now, 86400 * $amount, $playerId]);
                    if ($stmt->rowCount() !== 1) {
                        throw new RuntimeException('Medical item could not update player state');
                    }
                });
                sendJson(['state' => 'ok']);
                break;

            case 'useTheatrics':
                self::runItemTransaction($db, function () use ($db, $playerId, $item, $amount, $idCity) {
                    self::deductPlayerItem($db, $playerId, $item, $amount);
                    $cityStmt = $db->prepare('SELECT 1 FROM city WHERE id_city = ? AND id_player = ?');
                    $cityStmt->execute([$idCity, $playerId]);
                    if ($cityStmt->fetchColumn() === false) {
                        throw new RuntimeException('Theatrics item city is no longer owned by the player');
                    }
                    $db->prepare("UPDATE city SET `loy` = 100, `dis_loy` = 0 WHERE id_city = ? AND id_player = ?")
                        ->execute([$idCity, $playerId]);
                });
                $freshCity = $db->query("SELECT * FROM city WHERE id_city = $idCity")->fetch();
                foreach ($freshCity as $k => $v) { if (is_numeric($v)) $freshCity[$k] = (int)$v; }
                sendJson(['state' => 'ok', 'City' => $freshCity, 'CityRes' => $freshCity]);
                break;

            case 'useFreedomHelp':
                self::runItemTransaction($db, function () use ($db, $playerId, $item, $amount, $idCity) {
                    self::deductPlayerItem($db, $playerId, $item, $amount);
                    $cityStmt = $db->prepare('SELECT 1 FROM city WHERE id_city = ? AND id_player = ?');
                    $cityStmt->execute([$idCity, $playerId]);
                    if ($cityStmt->fetchColumn() === false) {
                        throw new RuntimeException('Freedom item city is no longer owned by the player');
                    }
                    $db->prepare("UPDATE city SET `helper` = 0 WHERE id_city = ? AND id_player = ?")
                        ->execute([$idCity, $playerId]);
                });
                $freshCity = $db->query("SELECT * FROM city WHERE id_city = $idCity")->fetch();
                foreach ($freshCity as $k => $v) { if (is_numeric($v)) $freshCity[$k] = (int)$v; }
                sendJson(['state' => 'ok', 'City' => $freshCity, 'CityRes' => $freshCity]);
                break;

            case 'useRandomMove':
                // 1. Verify player owns the city
                $cityStmt = $db->prepare('SELECT * FROM city WHERE id_city = ? AND id_player = ?');
                $cityStmt->execute([$idCity, $playerId]);
                $city = $cityStmt->fetch(PDO::FETCH_ASSOC);
                if (!$city) {
                    sendJson(['state' => 'error_auth']);
                    return;
                }

                // 2. Prerequisite check: ensure no heroes from this city are outside (in_city = 0)
                $heroOutsideStmt = $db->prepare('SELECT 1 FROM city_hero WHERE id_city = ? AND in_city = 0 LIMIT 1');
                $heroOutsideStmt->execute([$idCity]);
                if ($heroOutsideStmt->fetchColumn() !== false) {
                    sendJson([
                        'state' => 'error_army_outside',
                        'msg' => 'لا يمكن نقل المدينة أثناء وجود قوات أو أبطال في مهمات خارج المدينة'
                    ]);
                    return;
                }

                // Ensure no active marching/battle expeditions from this city
                $activeBattleStmt = $db->prepare(
                    "SELECT 1 FROM world_battel WHERE state = 'marching' AND (id_city = ? OR (x_city = ? AND y_city = ?)) LIMIT 1"
                );
                $activeBattleStmt->execute([$idCity, (int)$city['x'], (int)$city['y']]);
                if ($activeBattleStmt->fetchColumn() !== false) {
                    sendJson([
                        'state' => 'error_army_outside',
                        'msg' => 'لا يمكن نقل المدينة أثناء وجود قوات أو أبطال في مهمات خارج المدينة'
                    ]);
                    return;
                }

                // Find an empty tile before charging the player.
                $foundX = null;
                $foundY = null;
                for ($attempt = 0; $attempt < 100; $attempt++) {
                    $candX = rand(50, 450);
                    $candY = rand(50, 450);
                    $occCity = $db->prepare('SELECT 1 FROM city WHERE x = ? AND y = ? LIMIT 1');
                    $occCity->execute([$candX, $candY]);
                    if ($occCity->fetchColumn() !== false) continue;

                    $occBar = $db->prepare('SELECT 1 FROM city_barray WHERE x_coord = ? AND y_coord = ? LIMIT 1');
                    $occBar->execute([$candX, $candY]);
                    if ($occBar->fetchColumn() !== false) continue;

                    $foundX = $candX;
                    $foundY = $candY;
                    break;
                }
                if ($foundX === null) {
                    sendJson([
                        'state' => 'error_no_place_empty',
                        'msg' => 'لم يتم العثور على موقع فارغ لنقل المدينة'
                    ]);
                    return;
                }

                $db->beginTransaction();
                try {
                    $occCity = $db->prepare('SELECT 1 FROM city WHERE x = ? AND y = ? LIMIT 1');
                    $occCity->execute([$foundX, $foundY]);
                    $occBar = $db->prepare('SELECT 1 FROM city_barray WHERE x_coord = ? AND y_coord = ? LIMIT 1');
                    $occBar->execute([$foundX, $foundY]);
                    if ($occCity->fetchColumn() !== false || $occBar->fetchColumn() !== false) {
                        $db->rollBack();
                        sendJson(['state' => 'error_no_place_empty', 'msg' => 'الموقع المختار لم يعد فارغاً']);
                        return;
                    }

                    self::deductPlayerItem($db, $playerId, 'random_move', 1);
                    $moveStmt = $db->prepare(
                        'UPDATE city SET `x` = ?, `y` = ? WHERE `id_city` = ? AND `id_player` = ?'
                    );
                    $moveStmt->execute([$foundX, $foundY, $idCity, $playerId]);
                    if ($moveStmt->rowCount() !== 1) {
                        throw new RuntimeException('Random city move did not update the owned city');
                    }

                    $freshStmt = $db->prepare('SELECT * FROM city WHERE id_city = ? AND id_player = ?');
                    $freshStmt->execute([$idCity, $playerId]);
                    $freshCity = $freshStmt->fetch(PDO::FETCH_ASSOC);
                    if (!$freshCity) {
                        throw new RuntimeException('Moved city was not found after relocation');
                    }
                    foreach ($freshCity as $k => $v) { if (is_numeric($v)) $freshCity[$k] = (int)$v; }
                    $db->commit();
                } catch (Throwable $error) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    throw $error;
                }

                sendJson(['state' => 'ok', 'City' => $freshCity, 'CityRes' => $freshCity]);
                break;

            case 'useCertainMove':
                // 1. Verify player owns the city
                $cityStmt = $db->prepare('SELECT * FROM city WHERE id_city = ? AND id_player = ?');
                $cityStmt->execute([$idCity, $playerId]);
                $city = $cityStmt->fetch(PDO::FETCH_ASSOC);
                if (!$city) {
                    sendJson(['state' => 'error_auth']);
                    return;
                }

                // 2. Prerequisite check: ensure no heroes from this city are outside (in_city = 0)
                $heroOutsideStmt = $db->prepare('SELECT 1 FROM city_hero WHERE id_city = ? AND in_city = 0 LIMIT 1');
                $heroOutsideStmt->execute([$idCity]);
                if ($heroOutsideStmt->fetchColumn() !== false) {
                    sendJson([
                        'state' => 'error_army_outside',
                        'msg' => 'لا يمكن نقل المدينة أثناء وجود قوات أو أبطال في مهمات خارج المدينة'
                    ]);
                    return;
                }

                // Ensure no active marching/battle expeditions from this city
                $activeBattleStmt = $db->prepare(
                    "SELECT 1 FROM world_battel WHERE state = 'marching' AND (id_city = ? OR (x_city = ? AND y_city = ?)) LIMIT 1"
                );
                $activeBattleStmt->execute([$idCity, (int)$city['x'], (int)$city['y']]);
                if ($activeBattleStmt->fetchColumn() !== false) {
                    sendJson([
                        'state' => 'error_army_outside',
                        'msg' => 'لا يمكن نقل المدينة أثناء وجود قوات أو أبطال في مهمات خارج المدينة'
                    ]);
                    return;
                }

                // 3. Target coordinate validation
                $newX = isset($_REQUEST['newX']) ? (int)$_REQUEST['newX'] : (isset($_REQUEST['xCoord']) ? (int)$_REQUEST['xCoord'] : (isset($_REQUEST['x']) ? (int)$_REQUEST['x'] : -1));
                $newY = isset($_REQUEST['newY']) ? (int)$_REQUEST['newY'] : (isset($_REQUEST['yCoord']) ? (int)$_REQUEST['yCoord'] : (isset($_REQUEST['y']) ? (int)$_REQUEST['y'] : -1));
                if ($newX < 0 || $newX >= 500 || $newY < 0 || $newY >= 500) {
                    sendJson(['state' => 'error_coord', 'msg' => 'الإحداثيات غير صحيحة']);
                    return;
                }

                // 4. Ensure target spot is empty
                $occCity = $db->prepare('SELECT 1 FROM city WHERE x = ? AND y = ? LIMIT 1');
                $occCity->execute([$newX, $newY]);
                if ($occCity->fetchColumn() !== false) {
                    sendJson(['state' => 'error_no_place_empty']);
                    return;
                }
                $occBar = $db->prepare('SELECT 1 FROM city_barray WHERE x_coord = ? AND y_coord = ? LIMIT 1');
                $occBar->execute([$newX, $newY]);
                if ($occBar->fetchColumn() !== false) {
                    sendJson(['state' => 'error_no_place_empty']);
                    return;
                }

                $wbPath = __DIR__ . '/../json/WorldBarrary.json';
                if (!file_exists($wbPath)) {
                    $wbPath = __DIR__ . '/../../caesar-assets/api/js-0.0.24/json/WorldBarrary.json';
                }
                if (file_exists($wbPath)) {
                    $wbContent = file_get_contents($wbPath);
                    if (preg_match('/(^|[,"]\s*)' . $newX . '_' . $newY . '_([1-9][0-9]*)/', $wbContent)) {
                        sendJson(['state' => 'error_no_place_empty']);
                        return;
                    }
                }

                $db->beginTransaction();
                try {
                    $occCity->execute([$newX, $newY]);
                    $occBar->execute([$newX, $newY]);
                    if ($occCity->fetchColumn() !== false || $occBar->fetchColumn() !== false) {
                        $db->rollBack();
                        sendJson(['state' => 'error_no_place_empty']);
                        return;
                    }

                    self::deductPlayerItem($db, $playerId, 'certain_move', 1);

                    // Targeted move imposes 24-hour attack/expedition cooldown.
                    $cooldownUntil = time() + 86400;
                    $moveStmt = $db->prepare(
                        'UPDATE city SET `x` = ?, `y` = ?, `attack_cooldown_until` = ?'
                        . ' WHERE `id_city` = ? AND `id_player` = ?'
                    );
                    $moveStmt->execute([$newX, $newY, $cooldownUntil, $idCity, $playerId]);
                    if ($moveStmt->rowCount() !== 1) {
                        throw new RuntimeException('Certain city move did not update the owned city');
                    }

                    $freshStmt = $db->prepare('SELECT * FROM city WHERE id_city = ? AND id_player = ?');
                    $freshStmt->execute([$idCity, $playerId]);
                    $freshCity = $freshStmt->fetch(PDO::FETCH_ASSOC);
                    if (!$freshCity) {
                        throw new RuntimeException('Moved city was not found after relocation');
                    }
                    foreach ($freshCity as $k => $v) { if (is_numeric($v)) $freshCity[$k] = (int)$v; }
                    $db->commit();
                } catch (Throwable $error) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    throw $error;
                }

                sendJson(['state' => 'ok', 'City' => $freshCity, 'CityRes' => $freshCity, 'attack_cooldown_until' => $cooldownUntil]);
                break;

            case 'useArenaAttempt':
                $attemptsToAdd = 1;
                if ($item === 'arena_attempt_5') $attemptsToAdd = 5;
                elseif ($item === 'arena_attempt_10') $attemptsToAdd = 10;
                $totalAttempts = $attemptsToAdd * $amount;

                self::runItemTransaction($db, function () use ($db, $playerId, $item, $amount, $totalAttempts) {
                    self::deductPlayerItem($db, $playerId, $item, $amount);
                    $db->prepare("INSERT INTO `arena_player` (`id_player`, `rank`, `points`, `attempts`, `wins`, `losses`, `hero_list`)
                                  VALUES (?, 1000, 100, ?, 0, 0, '[]')
                                  ON DUPLICATE KEY UPDATE `attempts` = `attempts` + ?")
                       ->execute([$playerId, $totalAttempts, $totalAttempts]);
                });
                sendJson(['state' => 'ok', 'attempts' => $totalAttempts]);
                break;

            case 'useArenaExpPack':
                $expToAdd = 1;
                if ($item === 'arena_exp_5') $expToAdd = 5;
                elseif ($item === 'arena_exp_10') $expToAdd = 10;
                elseif ($item === 'arena_exp_25') $expToAdd = 25;
                $totalExp = $expToAdd * $amount;

                self::runItemTransaction($db, function () use ($db, $playerId, $item, $amount, $totalExp) {
                    self::deductPlayerItem($db, $playerId, $item, $amount);
                    $db->prepare("INSERT INTO `arena_player` (`id_player`, `rank`, `points`, `attempts`, `wins`, `losses`, `hero_list`)
                                  VALUES (?, 1000, ?, 10, 0, 0, '[]')
                                  ON DUPLICATE KEY UPDATE `points` = `points` + ?")
                       ->execute([$playerId, $totalExp, $totalExp]);
                });
                sendJson(['state' => 'ok', 'exp' => $totalExp]);
                break;

            default:
                // Instant Resource Packs (food_a..e, wood_a..e, stone_a..e, metal_a..e, coin_a..e)
                if (preg_match('/^(food|wood|stone|metal|coin)_([a-d])$/', $item, $m)) {
                    $resType = $m[1];
                    $tier = $m[2];
                    $tierValues = ['a' => 1500, 'b' => 5000, 'c' => 100000, 'd' => 350000];
                    $gain = $tierValues[$tier] * $amount;
                    $db->beginTransaction();
                    try {
                        self::deductPlayerItem($db, $playerId, $item, $amount);
                        if (!ResourceService::creditCity($db, $idCity, $playerId, [$resType => $gain])) {
                            throw new ResourceLimitException('Resource pack would exceed the city balance limit');
                        }
                        $db->commit();
                    } catch (ResourceLimitException $error) {
                        if ($db->inTransaction()) {
                            $db->rollBack();
                        }
                        sendJson(['state' => 'error_resource_limit', 'msg' => 'ستتجاوز الحزمة الحد الأقصى لموارد المدينة']);
                    } catch (Throwable $error) {
                        if ($db->inTransaction()) {
                            $db->rollBack();
                        }
                        throw $error;
                    }
                    $freshCity = $db->query("SELECT * FROM city WHERE id_city = $idCity")->fetch(PDO::FETCH_ASSOC);
                    foreach ($freshCity as $k => $v) { if (is_numeric($v)) $freshCity[$k] = (int)$v; }
                    sendJson(['state' => 'ok', 'City' => $freshCity, 'CityRes' => $freshCity]);
                    return;
                }
                sendJson(['state' => 'error_invalid_item']);
                return;
        }
    }

    public static function handleArmyPack($action, $db) {
        $player = self::getPlayer($db);
        $playerId = (int)$player['id_player'];
        $item = isset($_REQUEST['Item']) ? trim($_REQUEST['Item']) : '';
        $amount = isset($_REQUEST['amount']) ? max(1, (int)$_REQUEST['amount']) : 1;
        $idCity = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 0;
        if ($idCity <= 0) {
            $cityStmt = $db->prepare('SELECT `id_city` FROM `city` WHERE `id_player` = ? ORDER BY `id_city` ASC LIMIT 1');
            $cityStmt->execute([$playerId]);
            $idCity = (int)$cityStmt->fetchColumn();
        }
        $city = requirePlayerOwnedCity($db, $player, $idCity);

        // Army pack rules
        $packs = [
            'useArmyPackA100'  => ['army_a' => 100],
            'useArmyPackA1000' => ['army_a' => 1000],
            'useArmyPackB100'  => ['army_b' => 100],
            'useArmyPackB1000' => ['army_b' => 1000],
            'useArmyPackC100'  => ['army_c' => 100],
            'useArmyPackC1000' => ['army_c' => 1000],
            'useArmyPackD100'  => ['army_d' => 100],
            'useArmyPackD1000' => ['army_d' => 1000],
            'useArmyPackE100'  => ['army_e' => 100],
            'useArmyPackE1000' => ['army_e' => 1000],
            'useArmyPackF100'  => ['army_f' => 100],
            'useArmyPackF1000' => ['army_f' => 1000],
            'useArmyPackMini'  => ['army_a' => 50, 'army_b' => 50, 'army_c' => 50, 'army_d' => 50, 'army_e' => 50, 'army_f' => 50],
            'useArmyPackMedium'=> ['army_a' => 200, 'army_b' => 200, 'army_c' => 200, 'army_d' => 200, 'army_e' => 200, 'army_f' => 200],
            'useArmyPackLarge' => ['army_a' => 1000, 'army_b' => 1000, 'army_c' => 1000, 'army_d' => 1000, 'army_e' => 1000, 'army_f' => 1000]
        ];

        $armies = isset($packs[$action]) ? $packs[$action] : ['army_a' => 50 * $amount];

        $setClauses = [];
        $params = [];
        foreach ($armies as $col => $num) {
            $setClauses[] = "`$col` = `$col` + ?";
            $params[] = $num * $amount;
        }
        $params[] = $idCity;

        self::runItemTransaction($db, function () use ($db, $playerId, $item, $amount, $idCity, $setClauses, $params) {
            self::deductPlayerItem($db, $playerId, $item, $amount);
            $updateParams = $params;
            $updateParams[] = $playerId;
            $update = $db->prepare(
                "UPDATE city SET " . implode(', ', $setClauses) . " WHERE id_city = ? AND id_player = ?"
            );
            $update->execute($updateParams);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('Army pack could not update the owned city');
            }
        });

        $freshCity = $db->query("SELECT * FROM city WHERE id_city = $idCity")->fetch();
        foreach ($freshCity as $k => $v) { if (is_numeric($v)) $freshCity[$k] = (int)$v; }
        sendJson(['state' => 'ok', 'City' => $freshCity, 'CityRes' => $freshCity]);
    }

    public static function handleBoxUse($action, $db) {
        $player = self::getPlayer($db);
        $playerId = (int)$player['id_player'];
        $item = isset($_REQUEST['Item'])
            ? trim($_REQUEST['Item'])
            : (isset($_REQUEST['idItem']) ? trim($_REQUEST['idItem']) : '');
        $amount = ResourceService::parseAmount($_REQUEST['amount'] ?? '1');
        if ($amount === null || $amount <= 0 || $amount > 1000000) {
            sendJson(['state' => 'error_invalid_amount']);
        }
        $idCity = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 0;
        if ($idCity <= 0) {
            $cStmt = $db->prepare("SELECT * FROM city WHERE id_player = ? ORDER BY id_city ASC LIMIT 1");
            $cStmt->execute([$playerId]);
            $idCity = (int)$cStmt->fetchColumn();
        }
        $city = requirePlayerOwnedCity($db, $player, $idCity);

        if ($action === 'useResourcePacks') {
            if (!preg_match('/^(food|wood|stone|metal|coin)_([a-d])$/', $item, $matches)) {
                sendJson(['state' => 'error_invalid_item']);
            }
            $amount = ResourceService::parseAmount($_REQUEST['amount'] ?? null);
            if ($amount === null || $amount <= 0) {
                sendJson(['state' => 'error_invalid_amount']);
            }

            $resource = $matches[1];
            $tierValues = ['a' => 1500, 'b' => 5000, 'c' => 100000, 'd' => 350000];
            $gainPerPack = $tierValues[$matches[2]];
            $maxResource = ResourceService::MAX_CITY_BALANCE;

            $db->beginTransaction();
            try {
                $cityStmt = $db->prepare(
                    "SELECT `$resource` FROM `city` WHERE `id_city` = ? AND `id_player` = ? FOR UPDATE"
                );
                $cityStmt->execute([$idCity, $playerId]);
                $currentResource = $cityStmt->fetchColumn();
                if ($currentResource === false) {
                    $db->rollBack();
                    sendJson(['state' => 'error_auth']);
                }
                if ($amount > intdiv($maxResource - (int)$currentResource, $gainPerPack)) {
                    $db->rollBack();
                    sendJson(['state' => 'error_resource_limit']);
                }
                $gain = $gainPerPack * $amount;

                $deduct = $db->prepare(
                    'UPDATE `player_item`
                     SET `amount` = `amount` - ?
                     WHERE `id_player` = ? AND `id_item` = ? AND `amount` >= ?'
                );
                $deduct->execute([$amount, $playerId, $item, $amount]);
                if ($deduct->rowCount() !== 1) {
                    $db->rollBack();
                    sendJson(['state' => 'error_no_item']);
                }
                $db->prepare(
                    'DELETE FROM `player_item`
                     WHERE `id_player` = ? AND `id_item` = ? AND `amount` <= 0'
                )->execute([$playerId, $item]);

                if (!ResourceService::creditCity($db, $idCity, $playerId, [$resource => $gain])) {
                    $db->rollBack();
                    sendJson(['state' => 'error_resource_limit']);
                }
                $db->commit();
            } catch (Throwable $error) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $error;
            }

            $freshCityStmt = $db->prepare('SELECT * FROM `city` WHERE `id_city` = ? AND `id_player` = ?');
            $freshCityStmt->execute([$idCity, $playerId]);
            $freshCity = $freshCityStmt->fetch(PDO::FETCH_ASSOC);
            foreach ($freshCity as $key => $value) {
                if (is_numeric($value)) {
                    $freshCity[$key] = (int)$value;
                }
            }
            sendJson(['state' => 'ok', 'City' => $freshCity, 'CityRes' => $freshCity]);
            return;
        }

        // 1. Hero Recruitment Packs (tagned_3p to tagned_8p)
        if ($action === 'useHeroPacks' || strpos($item, 'tagned_') === 0) {
            $potential = 3;
            if (preg_match('/tagned_(\d+)p/', $item, $m)) {
                $potential = (int)$m[1];
            }

            // Check Palace Capacity
            $bStmt = $db->prepare("SELECT lvl FROM city_building WHERE id_city = ? AND type = 1");
            $bStmt->execute([$idCity]);
            $palaceLvl = (int)$bStmt->fetchColumn() ?: 1;
            $maxHeroes = max(3, $palaceLvl * 2);

            $hCountStmt = $db->prepare("SELECT COUNT(*) FROM city_hero WHERE id_city = ?");
            $hCountStmt->execute([$idCity]);
            $curHeroes = (int)$hCountStmt->fetchColumn();

            if ($curHeroes >= $maxHeroes) {
                sendJson(['state' => 'error_1', 'msg' => 'قصر المدينة ممتلئ، طور القصر لتجنيد المزيد من الأبطال']);
                return;
            }

            $heroNames = ['أورليوس', 'ماكسيموس', 'سكيبيو', 'أغسطس', 'سبارتاكوس', 'تيطس', 'تراجان', 'ألكسندر', 'أنطونيوس', 'سيزر'];
            $hName = $heroNames[array_rand($heroNames)];

            $pA = 35 + ($potential * 5);
            $pB = 40 + ($potential * 6);
            $pC = 30 + ($potential * 5);
            $pExtra = 25 + ($potential * 5);
            $powerMax = 100;

            $db->exec("CREATE TABLE IF NOT EXISTS `hero_medal` (
                `id_hero` int(11) NOT NULL,
                `id_player` int(11) NOT NULL,
                `medal_ceasro` int(11) NOT NULL DEFAULT 0,
                `medal_den` int(11) NOT NULL DEFAULT 0,
                `medal_leo` int(11) NOT NULL DEFAULT 0,
                `ceaser_eagle` int(11) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id_hero`),
                KEY `id_player` (`id_player`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            $db->beginTransaction();
            try {
                self::deductPlayerItem($db, $playerId, $item, $amount);
                $insHero = $db->prepare("INSERT INTO city_hero (id_city, id_player, name, lvl, ultra_p, exp, loyal, power, power_max, avatar, in_city, point_a, point_b, point_c, points)
                                         VALUES (?, ?, ?, 10, ?, 0, 95, ?, ?, ?, 1, ?, ?, ?, ?)");
                $insHero->execute([$idCity, $playerId, $hName, $potential, $powerMax, $powerMax, rand(1, 10), $pA, $pB, $pC, $pExtra]);
                $newHeroId = (int)$db->lastInsertId();
                $db->prepare("INSERT IGNORE INTO `hero_medal` (`id_hero`, `id_player`) VALUES (?, ?)")->execute([$newHeroId, $playerId]);
                $db->commit();
            } catch (Throwable $error) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $error;
            }

            sendJson(['state' => 'ok', 'msg' => "تم تجنيد البطل $hName بنجاح برتبة $potential نجوم"]);
            return;
        }

        // 2. Army Box (useArmyBox)
        if ($action === 'useArmyBox' || $item === 'army_box') {
            $db->beginTransaction();
            try {
                self::deductPlayerItem($db, $playerId, $item, $amount);
                $db->prepare("UPDATE city SET
                    `army_a` = `army_a` + ?,
                    `army_b` = `army_b` + ?,
                    `army_c` = `army_c` + ?,
                    `army_d` = `army_d` + ?,
                    `army_e` = `army_e` + ?,
                    `army_f` = `army_f` + ?
                    WHERE id_city = ? AND id_player = ?")
                   ->execute([2000 * $amount, 1000 * $amount, 1500 * $amount, 800 * $amount, 200 * $amount, 100 * $amount, $idCity, $playerId]);
                $db->commit();
            } catch (Throwable $error) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $error;
            }

            $freshCity = $db->query("SELECT * FROM city WHERE id_city = $idCity")->fetch(PDO::FETCH_ASSOC);
            foreach ($freshCity as $k => $v) { if (is_numeric($v)) $freshCity[$k] = (int)$v; }
            sendJson(['state' => 'ok', 'City' => $freshCity, 'CityRes' => $freshCity]);
            return;
        }

        // 3. Gift Boxes, Wood Boxes, Golden Boxes, & Beginner Progression Packs
        $giftItems = [];

        if ($action === 'useWoodBox' || $item === 'wood_box') {
            $giftItems = [
                ['Item' => 'wood_c',   'amount' => 2 * $amount],
                ['Item' => 'archit_a', 'amount' => 2 * $amount],
                ['Item' => 'wood_1',   'amount' => 1 * $amount],
                ['Item' => 'wheat_1',  'amount' => 1 * $amount]
            ];
        } elseif ($action === 'useGoldenBox' || $item === 'golden_box') {
            $giftItems = [
                ['Item' => 'gold_25',  'amount' => 1 * $amount],
                ['Item' => 'food_d',   'amount' => 1 * $amount],
                ['Item' => 'wood_d',   'amount' => 1 * $amount],
                ['Item' => 'archit_b', 'amount' => 3 * $amount],
                ['Item' => 'peace',    'amount' => 1 * $amount]
            ];
        } elseif ($action === 'useBeginnerPack' || strpos($item, 'beginner_back_') === 0) {
            $bLvl = 1;
            if (preg_match('/beginner_back_(\d+)/', $item, $m)) {
                $bLvl = (int)$m[1];
            }

            $packLoot = [
                1 => [
                    ['Item' => 'food_b', 'amount' => 2], ['Item' => 'wood_b', 'amount' => 2],
                    ['Item' => 'stone_b', 'amount' => 2], ['Item' => 'metal_b', 'amount' => 2],
                    ['Item' => 'coin_b', 'amount' => 2], ['Item' => 'archit_a', 'amount' => 2],
                    ['Item' => 'wheat_1', 'amount' => 1], ['Item' => 'beginner_back_2', 'amount' => 1]
                ],
                2 => [
                    ['Item' => 'food_c', 'amount' => 1], ['Item' => 'wood_c', 'amount' => 1],
                    ['Item' => 'archit_a', 'amount' => 3], ['Item' => 'peace', 'amount' => 1],
                    ['Item' => 'rec_letter', 'amount' => 1], ['Item' => 'beginner_back_3', 'amount' => 1]
                ],
                3 => [
                    ['Item' => 'food_c', 'amount' => 2], ['Item' => 'stone_c', 'amount' => 2],
                    ['Item' => 'archit_b', 'amount' => 2], ['Item' => 'train_acce_30', 'amount' => 2],
                    ['Item' => 'luxury_1', 'amount' => 3], ['Item' => 'beginner_back_4', 'amount' => 1]
                ],
                4 => [
                    ['Item' => 'food_c', 'amount' => 3], ['Item' => 'wood_c', 'amount' => 3],
                    ['Item' => 'metal_c', 'amount' => 3], ['Item' => 'archit_b', 'amount' => 3],
                    ['Item' => 'gold_10', 'amount' => 1], ['Item' => 'beginner_back_5', 'amount' => 1]
                ],
                5 => [
                    ['Item' => 'food_d', 'amount' => 1], ['Item' => 'wood_d', 'amount' => 1],
                    ['Item' => 'archit_c', 'amount' => 2], ['Item' => 'archim_b', 'amount' => 2],
                    ['Item' => 'tagned_3p', 'amount' => 1], ['Item' => 'beginner_back_6', 'amount' => 1]
                ],
                6 => [
                    ['Item' => 'food_d', 'amount' => 2], ['Item' => 'stone_d', 'amount' => 2],
                    ['Item' => 'metal_d', 'amount' => 2], ['Item' => 'archit_c', 'amount' => 3],
                    ['Item' => 'gold_25', 'amount' => 1], ['Item' => 'beginner_back_7', 'amount' => 1]
                ],
                7 => [
                    ['Item' => 'food_d', 'amount' => 3], ['Item' => 'wood_d', 'amount' => 3],
                    ['Item' => 'metal_d', 'amount' => 3], ['Item' => 'archit_c', 'amount' => 5],
                    ['Item' => 'sparta_stab', 'amount' => 2], ['Item' => 'marmlo_helmet', 'amount' => 2],
                    ['Item' => 'beginner_back_8', 'amount' => 1]
                ],
                8 => [
                    ['Item' => 'archit_d', 'amount' => 2], ['Item' => 'archim_d', 'amount' => 2],
                    ['Item' => 'tagned_4p', 'amount' => 1],
                    ['Item' => 'beginner_back_9', 'amount' => 1]
                ],
                9 => [
                    ['Item' => 'gold_75', 'amount' => 1], ['Item' => 'train_acce_50', 'amount' => 3],
                    ['Item' => 'beginner_back_10', 'amount' => 1]
                ],
                10 => [
                    ['Item' => 'gold_100', 'amount' => 1],
                    ['Item' => 'tagned_5p', 'amount' => 1], ['Item' => 'sparta_stab', 'amount' => 5],
                    ['Item' => 'marmlo_helmet', 'amount' => 5], ['Item' => 'peace', 'amount' => 3]
                ]
            ];

            $baseList = $packLoot[$bLvl] ?? $packLoot[1];
            foreach ($baseList as $entry) {
                $giftItems[] = [
                    'Item'   => $entry['Item'],
                    'amount' => $entry['amount'] * $amount
                ];
            }
        } else {
            // Default Gift Box (useGiftBox / gift_box)
            $giftItems = [
                ['Item' => 'food_c',   'amount' => 2 * $amount],
                ['Item' => 'wood_c',   'amount' => 2 * $amount],
                ['Item' => 'stone_c',  'amount' => 2 * $amount],
                ['Item' => 'metal_c',  'amount' => 2 * $amount],
                ['Item' => 'coin_c',   'amount' => 2 * $amount],
                ['Item' => 'archit_a', 'amount' => 3 * $amount],
                ['Item' => 'peace',    'amount' => 1 * $amount],
                ['Item' => 'luxury_1', 'amount' => 2 * $amount]
            ];
        }

        $db->beginTransaction();
        try {
            self::deductPlayerItem($db, $playerId, $item, $amount);

            foreach ($giftItems as $g) {
                self::addPlayerItem($db, $playerId, $g['Item'], $g['amount']);
            }

            $resAmt = 100000 * $amount;
            if (!ResourceService::creditCity($db, $idCity, $playerId, [
                'food' => $resAmt,
                'wood' => $resAmt,
                'stone' => $resAmt,
                'metal' => $resAmt,
                'coin' => $resAmt
            ])) {
                $db->rollBack();
                sendJson(['state' => 'error_resource_limit', 'msg' => 'ستتجاوز مكافأة الصندوق الحد الأقصى للموارد']);
            }
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }

        $freshCity = $db->query("SELECT * FROM city WHERE id_city = $idCity")->fetch(PDO::FETCH_ASSOC);
        foreach ($freshCity as $k => $v) { if (is_numeric($v)) $freshCity[$k] = (int)$v; }

        sendJson([
            'state'   => 'ok',
            'Item'    => $giftItems,
            'City'    => $freshCity,
            'CityRes' => $freshCity
        ]);
    }

    public static function buyExchange($db) {
        $player = self::getPlayer($db);
        $playerId = (int)$player['id_player'];
        $idExchange = filter_var($_REQUEST['idExchange'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $amount = filter_var($_REQUEST['amountToTrade'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 1000],
        ]);
        $idCity = filter_var($_REQUEST['idCity'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($idExchange === false || $amount === false || $idCity === false) {
            sendJson(['state' => 'error_invalid_trade']);
        }

        try {
            $offers = self::loadExchangeOffers();
            $prices = self::loadItemPrices();
            $limits = self::effectiveItemLimits($prices, $offers);
            $offers = self::applyItemLimitsToExchangeOffers($offers, $limits);
        } catch (RuntimeException $error) {
            error_log('[ItemController] ' . $error->getMessage());
            http_response_code(500);
            sendJson(['state' => 'error', 'msg' => 'Item limit data is unavailable']);
        }
        if (!isset($offers[$idExchange])) {
            sendJson(['state' => 'error_invalid_exchange']);
        }
        $offer = $offers[$idExchange];
        if ($amount > (int)$offer['player_max']) {
            sendJson(['state' => 'error_1']);
        }
        foreach (json_decode($offer['req'], true) as $requirement) {
            if ((int)$requirement['amount'] > intdiv(PHP_INT_MAX, $amount)) {
                http_response_code(500);
                error_log('[ItemController] Exchange requirement amount exceeds supported range');
                sendJson(['state' => 'error', 'msg' => 'Exchange offer data is invalid']);
            }
        }
        $rewardDefinition = json_decode($offer['reword'], true);
        if ((int)$rewardDefinition['amount'] > intdiv(PHP_INT_MAX, $amount)) {
            http_response_code(500);
            error_log('[ItemController] Exchange reward amount exceeds supported range');
            sendJson(['state' => 'error', 'msg' => 'Exchange offer data is invalid']);
        }

        try {
            $db->beginTransaction();
            $playerStmt = $db->prepare(
                'SELECT `id_player`, `porm`
                 FROM `player`
                 WHERE `id_player` = ?
                 FOR UPDATE'
            );
            $playerStmt->execute([$playerId]);
            $lockedPlayer = $playerStmt->fetch(PDO::FETCH_ASSOC);
            if (!$lockedPlayer) {
                self::rejectExchange($db, 'error_auth', 401);
            }
            if ((int)$lockedPlayer['porm'] < (int)$offer['player_porm']) {
                self::rejectExchange($db, 'error_porm', 403);
            }

            $cityStmt = $db->prepare(
                'SELECT *
                 FROM `city`
                 WHERE `id_city` = ? AND `id_player` = ?
                 FOR UPDATE'
            );
            $cityStmt->execute([$idCity, $playerId]);
            $city = $cityStmt->fetch(PDO::FETCH_ASSOC);
            if (!$city) {
                self::rejectExchange($db, 'error_auth', 403);
            }

            $usageStmt = $db->prepare(
                'INSERT INTO `player_exchange_usage` (`id_player`, `id_exchange`, `usage_date`, `trade_count`)
                 VALUES (?, ?, UTC_DATE(), 0)
                 ON DUPLICATE KEY UPDATE `trade_count` = `trade_count`'
            );
            $usageStmt->execute([$playerId, $idExchange]);
            $usageLock = $db->prepare(
                'SELECT `trade_count`
                 FROM `player_exchange_usage`
                 WHERE `id_player` = ? AND `id_exchange` = ? AND `usage_date` = UTC_DATE()
                 FOR UPDATE'
            );
            $usageLock->execute([$playerId, $idExchange]);
            $tradeCount = (int)$usageLock->fetchColumn();
            if ($tradeCount + $amount > (int)$offer['player_max']) {
                self::rejectExchange($db, 'error_1');
            }

            $serverMaximum = $offer['server_max'] === null ? null : (int)$offer['server_max'];
            if ($serverMaximum !== null) {
                $serverTake = max(0, (int)($offer['server_take'] ?? 0));
                $serverUsage = $db->prepare(
                    'INSERT INTO `exchange_server_usage` (`id_exchange`, `trade_count`)
                     VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE `id_exchange` = `id_exchange`'
                );
                $serverUsage->execute([$idExchange, $serverTake]);
                $serverLock = $db->prepare(
                    'SELECT `trade_count`
                     FROM `exchange_server_usage`
                     WHERE `id_exchange` = ?
                     FOR UPDATE'
                );
                $serverLock->execute([$idExchange]);
                $serverCount = (int)$serverLock->fetchColumn();
                if ($serverCount + $amount > $serverMaximum) {
                    self::rejectExchange($db, 'error_3');
                }
                $db->prepare(
                    'UPDATE `exchange_server_usage`
                     SET `trade_count` = `trade_count` + ?
                     WHERE `id_exchange` = ?'
                )->execute([$amount, $idExchange]);
            }

            foreach (json_decode($offer['req'], true) as $requirement) {
                $quantity = (int)$requirement['amount'] * $amount;
                if ($requirement['type'] === 'resource') {
                    $resource = $requirement['resource_type'];
                    $deduct = $db->prepare(
                        "UPDATE `city`
                         SET `$resource` = `$resource` - ?
                         WHERE `id_city` = ? AND `id_player` = ? AND `$resource` >= ?"
                    );
                    $deduct->execute([$quantity, $idCity, $playerId, $quantity]);
                    if ($deduct->rowCount() !== 1) {
                        self::rejectExchange($db, 'error_2');
                    }
                } elseif ($requirement['type'] === 'matrial') {
                    $deduct = $db->prepare(
                        'UPDATE `player_item`
                         SET `amount` = `amount` - ?
                         WHERE `id_player` = ? AND `id_item` = ? AND `amount` >= ?'
                    );
                    $deduct->execute([$quantity, $playerId, $requirement['matrial'], $quantity]);
                    if ($deduct->rowCount() !== 1) {
                        self::rejectExchange($db, 'error_4');
                    }
                    $db->prepare(
                        'DELETE FROM `player_item`
                         WHERE `id_player` = ? AND `id_item` = ? AND `amount` <= 0'
                    )->execute([$playerId, $requirement['matrial']]);
                } elseif ($requirement['type'] === 'equip') {
                    $equipType = (string)$requirement['Equip'];
                    $equipPart = (string)$requirement['Part'];
                    $equipLevel = (int)$requirement['lvl'];
                    $equipIds = $db->prepare(
                        'SELECT `id_equip`
                         FROM `player_equip`
                         WHERE `id_player` = ? AND `type` = ? AND `part` = ? AND `lvl` = ? AND `on_hero` = 0
                         ORDER BY `id_equip`
                         LIMIT ' . $quantity . '
                         FOR UPDATE'
                    );
                    $equipIds->execute([$playerId, $equipType, $equipPart, $equipLevel]);
                    $ids = $equipIds->fetchAll(PDO::FETCH_COLUMN);
                    if (count($ids) < $quantity) {
                        self::rejectExchange($db, 'error_5');
                    }
                    $delete = $db->prepare(
                        'DELETE FROM `player_equip` WHERE `id_player` = ? AND `id_equip` = ?'
                    );
                    foreach ($ids as $equipId) {
                        $delete->execute([$playerId, (int)$equipId]);
                    }
                }
            }

            $reward = $rewardDefinition;
            $rewardAmount = (int)$reward['amount'] * $amount;
            if ($reward['type'] === 'matrial') {
                $rewardLock = $db->prepare(
                    'SELECT `amount`
                     FROM `player_item`
                     WHERE `id_player` = ? AND `id_item` = ?
                     FOR UPDATE'
                );
                $rewardLock->execute([$playerId, $reward['matrial']]);
                $ownedAmount = (int)($rewardLock->fetchColumn() ?: 0);
                $maxToHave = (int)$offer['max_to_have'];
                if ($maxToHave > 0 && $ownedAmount + $rewardAmount > $maxToHave) {
                    self::rejectExchange($db, 'error_over_max');
                }
                self::addPlayerItem($db, $playerId, $reward['matrial'], $rewardAmount);
            } else {
                $rewardEquipStmt = $db->prepare(
                    'SELECT COUNT(*)
                     FROM `player_equip`
                     WHERE `id_player` = ? AND `type` = ? AND `part` = ? AND `lvl` = ?'
                );
                $rewardEquipStmt->execute([
                    $playerId,
                    $reward['Equip'],
                    $reward['Part'],
                    (int)$reward['lvl'],
                ]);
                $ownedAmount = (int)$rewardEquipStmt->fetchColumn();
                $maxToHave = (int)$offer['max_to_have'];
                if ($maxToHave > 0 && $ownedAmount + $rewardAmount > $maxToHave) {
                    self::rejectExchange($db, 'error_over_max');
                }
                $grantEquip = $db->prepare(
                    'INSERT INTO `player_equip` (`id_player`, `id_hero`, `type`, `part`, `lvl`, `on_hero`)
                     VALUES (?, 0, ?, ?, ?, 0)'
                );
                for ($i = 0; $i < $rewardAmount; $i++) {
                    $grantEquip->execute([
                        $playerId,
                        $reward['Equip'],
                        $reward['Part'],
                        (int)$reward['lvl'],
                    ]);
                }
            }

            $db->prepare(
                'UPDATE `player_exchange_usage`
                 SET `trade_count` = `trade_count` + ?
                 WHERE `id_player` = ? AND `id_exchange` = ? AND `usage_date` = UTC_DATE()'
            )->execute([$amount, $playerId, $idExchange]);
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[ItemController] Exchange purchase failed: ' . $error->getMessage());
            http_response_code(500);
            sendJson(['state' => 'error', 'msg' => 'Exchange could not be completed']);
        }

        $freshCityStmt = $db->prepare('SELECT * FROM `city` WHERE `id_city` = ? AND `id_player` = ?');
        $freshCityStmt->execute([$idCity, $playerId]);
        $freshCity = $freshCityStmt->fetch(PDO::FETCH_ASSOC);
        foreach ($freshCity as $key => $value) {
            if (is_numeric($value)) {
                $freshCity[$key] = (int)$value;
            }
        }

        sendJson([
            'state' => 'ok',
            'City' => $freshCity,
            'CityRes' => $freshCity,
        ]);
    }

    private static function rejectExchange($db, string $state, int $status = 200): void {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        http_response_code($status);
        sendJson(['state' => $state]);
    }
}
