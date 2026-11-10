<?php
// PlayerController.php - Handles player auth, profile state, items, education, luck wheel, and equipment

class PlayerController {
    public static function handle($uri, $db) {
        switch ($uri) {
            case '/home/HLogIn/playerEnterServerWeb':
                self::playerEnterServerWeb($db);
                return true;
            case '/api/APlayer/getPlayerState':
                self::getPlayerState($db);
                return true;
            case '/api/APlayer/getPlayerInfo':
            case '/api/APlayer/refreshPlayerData':
                self::refreshPlayerData($db);
                return true;
            case '/api/APlayer/playLuckWheel':
                self::playLuckWheel($db);
                return true;
            case '/api/APlayerEdu/getPlayerEduLvl':
                self::getPlayerEduLvl($db);
                return true;
            case '/api/APlayerEdu/upgradeStudyLvl':
                self::upgradeStudyLvl($db);
                return true;
            case '/api/APlayerItem/getPlayerItems':
                self::getPlayerItems($db);
                return true;
            case '/api/APlayerEquip/getPlayerEquip':
                self::getPlayerEquip($db);
                return true;
            case '/api/APlayerEquip/getEquipPower':
                self::getEquipPower();
                return true;
            case '/api/APlayer/changePlayerName':
                self::changePlayerName($db);
                return true;
            case '/api/APlayer/getAllNotif':
                sendJson([]);
                return true;
            case '/api/APlayer/chatPann':
                sendJson(['state' => 'ok']);
                return true;
        }
        return false;
    }

    public static function resolvePlayer($db) {
        return requireAuthenticatedPlayer($db);
    }

    public static function playerEnterServerWeb($db) {
        $player = self::resolvePlayer($db);

        // Get player's cities
        $cStmt = $db->prepare("SELECT `id_city` FROM `city` WHERE `id_player` = ?");
        $cStmt->execute([$player['id_player']]);
        $cityIds = $cStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $response = [
            'state'     => 'ok',
            'Player'    => [
                'id_user'    => (int)$player['id_user'],
                'id_player'  => (int)$player['id_player'],
                'user_group' => (int)$player['user_group'],
                'prestige'   => (int)$player['prestige'],
                'honor'      => (int)$player['honor'],
                'gold'       => (int)$player['gold'],
                'avatar'     => (int)$player['avatar'],
                'guild'      => $player['guild'],
                'id_guild'   => $player['id_guild'] ? (int)$player['id_guild'] : null,
                'name'       => $player['name'],
                'lang'       => $player['lang'],
                'city_flag'  => $player['city_flag'],
                'porm'       => (int)$player['porm'],
                'chat_panne' => (int)$player['chat_panne'],
                'online'     => 1,
                'last_seen'  => time(),
                'panned'     => (int)$player['panned'],
                'p_token'    => $player['p_token'],
                'rank'       => (int)$player['rank'],
                'guild_data' => false
            ],
            'Server'    => [
                'id'          => 1,
                'player_num'  => 1,
                'guild_num'   => 0,
                'city_num'    => count($cityIds),
                'hero_num'    => 1,
                'open_status' => 1,
                'under_main'  => 0,
                'port'        => '8082'
            ],
            'JsVersion'  => '-0.0.24',
            'WsPort'     => '8081',
            'WsHost'     => 'localhost',
            'OuthToken'  => $player['p_token'],
            'idServer'   => '1',
            'idCities'   => array_map('intval', $cityIds),
            'PayLink'    => '',
            'RechCode'   => 'local'
        ];
        sendJson($response);
    }

    public static function getPlayerState($db) {
        $p = self::resolvePlayer($db);
        $idPlayer = (int)$p['id_player'];
        $stmt = $db->prepare("SELECT * FROM `player_state` WHERE `id_player` = ? LIMIT 1");
        $stmt->execute([$idPlayer]);
        $state = $stmt->fetch();
        if ($state) {
            foreach ($state as $k => $v) { $state[$k] = (int)$v; }
            sendJson($state);
            return;
        }
        sendJson(['state' => 'ok']);
    }

    public static function refreshPlayerData($db) {
        $p = self::resolvePlayer($db);
        if ($p) {
            $p['id_player'] = (int)$p['id_player'];
            $p['prestige']  = (int)$p['prestige'];
            $p['gold']      = (int)$p['gold'];
            $p['honor']     = (int)$p['honor'];
            sendJson($p);
            return;
        }
        sendJson(['state' => 'error']);
    }

    public static function generateLuckWheelPrizes() {
        // Grand Prize Tier (Corner 0 - Top center)
        $corner0Pool = [
            ['Prize' => 'peace',               'amount' => 1, 'weight' => 15,  'rate' => '0.15%'],
            ['Prize' => 'ceaser_eagle',        'amount' => 1, 'weight' => 20,  'rate' => '0.20%'],
            ['Prize' => 'expan_plan',          'amount' => 1, 'weight' => 25,  'rate' => '0.25%'],
            ['Prize' => 'golden_box',          'amount' => 1, 'weight' => 30,  'rate' => '0.30%'],
            ['Prize' => 'golden_supply_crate', 'amount' => 1, 'weight' => 25,  'rate' => '0.25%'],
            ['Prize' => 'gold_bar',            'amount' => 1, 'weight' => 20,  'rate' => '0.20%'],
            ['Prize' => 'archit_a',            'amount' => 3, 'weight' => 40,  'rate' => '0.40%']
        ];

        // Rare Prize Tier (Corner 5 - Right corner)
        $corner5Pool = [
            ['Prize' => 'necklace_3',    'amount' => 1, 'weight' => 50,  'rate' => '0.50%'],
            ['Prize' => 'retreat_point', 'amount' => 1, 'weight' => 100, 'rate' => '1.00%'],
            ['Prize' => 'random_move',   'amount' => 1, 'weight' => 150, 'rate' => '1.50%'],
            ['Prize' => 'rec_letter',    'amount' => 2, 'weight' => 120, 'rate' => '1.20%'],
            ['Prize' => 'motiv_60',      'amount' => 1, 'weight' => 80,  'rate' => '0.80%'],
            ['Prize' => 'gold_bar_piece','amount' => 1, 'weight' => 60,  'rate' => '0.60%']
        ];

        // Rare Prize Tier (Corner 10 - Bottom center)
        $corner10Pool = [
            ['Prize' => 'necklace_2',   'amount' => 1, 'weight' => 100, 'rate' => '1.00%'],
            ['Prize' => 'certain_move', 'amount' => 1, 'weight' => 60,  'rate' => '0.60%'],
            ['Prize' => 'medal_gold',   'amount' => 1, 'weight' => 80,  'rate' => '0.80%'],
            ['Prize' => 'bread',        'amount' => 2, 'weight' => 150, 'rate' => '1.50%'],
            ['Prize' => 'change_name',  'amount' => 1, 'weight' => 70,  'rate' => '0.70%'],
            ['Prize' => 'flag',         'amount' => 1, 'weight' => 100, 'rate' => '1.00%']
        ];

        // Rare Prize Tier (Corner 15 - Left corner)
        $corner15Pool = [
            ['Prize' => 'necklace_1',   'amount' => 1, 'weight' => 150, 'rate' => '1.50%'],
            ['Prize' => 'hero_train',   'amount' => 2, 'weight' => 200, 'rate' => '2.00%'],
            ['Prize' => 'hammer',       'amount' => 2, 'weight' => 250, 'rate' => '2.50%'],
            ['Prize' => 'archit_a',     'amount' => 1, 'weight' => 180, 'rate' => '1.80%'],
            ['Prize' => 'trumpet',      'amount' => 5, 'weight' => 300, 'rate' => '3.00%'],
            ['Prize' => 'wood_box',     'amount' => 2, 'weight' => 220, 'rate' => '2.20%']
        ];

        // Dynamic Pool for Border Cells (16 positions)
        $borderPool = [
            ['Prize' => 'food_c',         'amount' => 1, 'weight' => 1200, 'rate' => '12.0%'],
            ['Prize' => 'wood_c',         'amount' => 1, 'weight' => 1200, 'rate' => '12.0%'],
            ['Prize' => 'stone_c',        'amount' => 1, 'weight' => 1200, 'rate' => '12.0%'],
            ['Prize' => 'metal_c',        'amount' => 1, 'weight' => 1200, 'rate' => '12.0%'],
            ['Prize' => 'food_d',         'amount' => 1, 'weight' => 600,  'rate' => '6.0%'],
            ['Prize' => 'wood_d',         'amount' => 1, 'weight' => 600,  'rate' => '6.0%'],
            ['Prize' => 'stone_d',        'amount' => 1, 'weight' => 600,  'rate' => '6.0%'],
            ['Prize' => 'metal_d',        'amount' => 1, 'weight' => 600,  'rate' => '6.0%'],
            ['Prize' => 'food_b',         'amount' => 1, 'weight' => 300,  'rate' => '3.0%'],
            ['Prize' => 'wood_b',         'amount' => 1, 'weight' => 300,  'rate' => '3.0%'],
            ['Prize' => 'stone_b',        'amount' => 1, 'weight' => 300,  'rate' => '3.0%'],
            ['Prize' => 'metal_b',        'amount' => 1, 'weight' => 300,  'rate' => '3.0%'],
            ['Prize' => 'coin_d',         'amount' => 1, 'weight' => 800,  'rate' => '8.0%'],
            ['Prize' => 'coin_c',         'amount' => 1, 'weight' => 400,  'rate' => '4.0%'],
            ['Prize' => 'archit_b',       'amount' => 1, 'weight' => 250,  'rate' => '2.5%'],
            ['Prize' => 'archit_c',       'amount' => 1, 'weight' => 150,  'rate' => '1.5%'],
            ['Prize' => 'archit_d',       'amount' => 2, 'weight' => 400,  'rate' => '4.0%'],
            ['Prize' => 'shopping_car',   'amount' => 1, 'weight' => 400,  'rate' => '4.0%'],
            ['Prize' => 'trumpet',        'amount' => 2, 'weight' => 350,  'rate' => '3.5%'],
            ['Prize' => 'spies',          'amount' => 2, 'weight' => 300,  'rate' => '3.0%'],
            ['Prize' => 'repel_trumpet_1','amount' => 1, 'weight' => 200,  'rate' => '2.0%'],
            ['Prize' => 'power_bright',   'amount' => 1, 'weight' => 250,  'rate' => '2.5%']
        ];

        $prizes = array_fill(0, 20, null);
        $prizes[0]  = $corner0Pool[array_rand($corner0Pool)];
        $prizes[5]  = $corner5Pool[array_rand($corner5Pool)];
        $prizes[10] = $corner10Pool[array_rand($corner10Pool)];
        $prizes[15] = $corner15Pool[array_rand($corner15Pool)];

        shuffle($borderPool);
        $borderIndices = [1, 2, 3, 4, 6, 7, 8, 9, 11, 12, 13, 14, 16, 17, 18, 19];
        foreach ($borderIndices as $k => $idx) {
            $prizes[$idx] = $borderPool[$k % count($borderPool)];
        }

        return $prizes;
    }

    public static function playLuckWheel($db) {
        $prizes = self::generateLuckWheelPrizes();

        $amount = max(1, min(100, (int)($_POST['amount'] ?? 1)));

        // Resolve player
        $player = self::resolvePlayer($db);
        $idPlayer = (int)$player['id_player'];

        $db->beginTransaction();
        try {
            // Check available tickets
            $itemStmt = $db->prepare("SELECT `amount` FROM `player_item` WHERE `id_player` = ? AND `id_item` = 'luck_play' FOR UPDATE");
            $itemStmt->execute([$idPlayer]);
            $itemRow = $itemStmt->fetch();
            $currentTickets = $itemRow ? (int)$itemRow['amount'] : 0;

            $ticketsToDeduct = min($currentTickets, $amount);
            $remainingSpins = $amount - $ticketsToDeduct;
            $goldNeeded = $remainingSpins * 5;

            // Check gold if tickets aren't enough
            if ($goldNeeded > 0) {
                $pStmt = $db->prepare("SELECT `gold` FROM `player` WHERE `id_player` = ? FOR UPDATE");
                $pStmt->execute([$idPlayer]);
                $pRow = $pStmt->fetch();
                $currentGold = $pRow ? (int)$pRow['gold'] : 0;

                if ($currentGold < $goldNeeded) {
                    $db->rollBack();
                    sendJson(['state' => 'error_no_matrial']);
                    return;
                }

                // Deduct gold
                $db->prepare("UPDATE `player` SET `gold` = `gold` - ? WHERE `id_player` = ?")->execute([$goldNeeded, $idPlayer]);
            }

            // Deduct tickets
            if ($ticketsToDeduct > 0) {
                $db->prepare("UPDATE `player_item` SET `amount` = `amount` - ? WHERE `id_player` = ? AND `id_item` = 'luck_play'")->execute([$ticketsToDeduct, $idPlayer]);
            }

            // Draw prizes
            $totalWeight = array_sum(array_column($prizes, 'weight'));
            $winIndices = [];
            $wonPrizes = [];
            $rewardStmt = $db->prepare(
                'INSERT INTO `player_item` (`id_player`, `id_item`, `amount`) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE `amount` = `amount` + VALUES(`amount`)'
            );

            for ($spin = 0; $spin < $amount; $spin++) {
                $rand = random_int(1, $totalWeight);
                $cumulative = 0;
                $winIndex = 0;
                foreach ($prizes as $idx => $prize) {
                    $cumulative += $prize['weight'];
                    if ($rand <= $cumulative) {
                        $winIndex = $idx;
                        break;
                    }
                }
                $winIndices[] = $winIndex;
                $wonPrize = $prizes[$winIndex];
                $wonPrizes[] = $wonPrize;
                $rewardStmt->execute([$idPlayer, $wonPrize['Prize'], $wonPrize['amount']]);
            }

            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[PlayerController] Luck wheel transaction failed: ' . $error->getMessage());
            throw $error;
        }

        sendJson([
            'state'        => 'ok',
            'winIndex'     => $winIndices[count($winIndices) - 1],
            'winIndices'   => $winIndices,
            'wonPrizes'    => $wonPrizes,
            'amount'       => $amount,
            'usedTickets'  => $ticketsToDeduct,
            'usedGold'     => $goldNeeded,
            'Prize'        => $prizes
        ]);
        return;
    }

    public static function getPlayerEduLvl($db) {
        $p = self::resolvePlayer($db);
        $idPlayer = (int)$p['id_player'];
        $stmt = $db->prepare("SELECT * FROM `player_edu` WHERE `id_player` = ? LIMIT 1");
        $stmt->execute([$idPlayer]);
        $edu = $stmt->fetch();
        if ($edu) {
            foreach ($edu as $k => $v) { $edu[$k] = (int)$v; }
            sendJson($edu);
        }
        sendJson(['state' => 'ok']);
    }

    private static $eduReqs = null;

    public static function getStudyRequirements($studyName, $lvl) {
        if (self::$eduReqs === null) {
            $candidates = [
                __DIR__ . '/../js-3.4.113/json/education.json',
                dirname(__DIR__) . '/js-3.4.113/json/education.json',
                dirname(__DIR__, 2) . '/web.elkaisar.com/js-3.4.113/json/education.json'
            ];
            self::$eduReqs = [];
            foreach ($candidates as $cand) {
                if (file_exists($cand)) {
                    self::$eduReqs = json_decode(file_get_contents($cand), true) ?: [];
                    break;
                }
            }
        }

        if (isset(self::$eduReqs[$studyName])) {
            $s = self::$eduReqs[$studyName];
            $lvlReq = $s['lvl_req'][$lvl - 1] ?? null;
            $timeReq = $s['time_req'][$lvl - 1] ?? ($lvl * 60);
            if ($lvlReq) {
                return [
                    'food'  => (int)($lvlReq['food']  ?? 0),
                    'wood'  => (int)($lvlReq['wood']  ?? 0),
                    'stone' => (int)($lvlReq['stone'] ?? 0),
                    'metal' => (int)($lvlReq['metal'] ?? 0),
                    'coin'  => (int)($lvlReq['coin']  ?? 0),
                    'time'  => max(15, (int)$timeReq)
                ];
            }
        }
        return null;
    }

    public static function upgradeStudyLvl($db) {
        $cityId  = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 0;
        if ($cityId <= 0) {
            sendJson(['state' => 'error_city']);
        }
        $idStudy = isset($_REQUEST['idStudy']) ? trim($_REQUEST['idStudy']) : (isset($_REQUEST['study']) ? trim($_REQUEST['study']) : '');

        $player = self::resolvePlayer($db);
        $stmt = $db->prepare("SELECT c.id_player FROM city c WHERE c.id_city = ?");
        $stmt->execute([$cityId]);
        $cityPlayerId = (int)$stmt->fetchColumn();
        if ($cityPlayerId !== (int)$player['id_player']) {
            http_response_code(403);
            sendJson(['state' => 'error_city_owner']);
        }
        $playerId = $cityPlayerId;

        $stmt = $db->prepare("SELECT * FROM player_edu WHERE id_player = ?");
        $stmt->execute([$playerId]);
        $edu = $stmt->fetch();
        $currLvl = isset($edu[$idStudy]) ? (int)$edu[$idStudy] : 0;
        $nextLvl = $currLvl + 1;

        if ($nextLvl > 30) {
            sendJson(['state' => 'error_max_lvl', 'msg' => 'الدراسة وصلت للحد الأقصى']);
            return;
        }

        $req = self::getStudyRequirements($idStudy, $nextLvl);
        $costFood  = (int)($req['food']  ?? 0);
        $costWood  = (int)($req['wood']  ?? 0);
        $costStone = (int)($req['stone'] ?? 0);
        $costMetal = (int)($req['metal'] ?? 0);
        $costCoin  = (int)($req['coin']  ?? ($nextLvl * 500));
        $duration  = (int)($req['time']  ?? ($nextLvl * 60));

        // 1. Building Prerequisite: Academy (type 9) for military studies, University (type 8) for civil studies
        $academyStudies = ['infantry', 'riding', 'army', 'spying', 'leader', 'safe', 'medicine', 'logistic', 'navigating', 'supplying'];
        $reqBType = in_array($idStudy, $academyStudies, true) ? 9 : 8;

        $bStmt = $db->prepare("SELECT MAX(lvl) FROM city_building WHERE id_city = ? AND type = ?");
        $bStmt->execute([$cityId, $reqBType]);
        $hasBLvl = (int)$bStmt->fetchColumn();
        if ($hasBLvl < $nextLvl) {
            sendJson(['state' => 'error_cond', 'msg' => "تتطلب الدراسة مستوى مبنى التعليم {$nextLvl} على الأقل"]);
            return;
        }

        // 2. Law Items Prerequisite for advanced levels
        if ($nextLvl >= 25) {
            $hasLaw = (int)$db->query("SELECT amount FROM player_item WHERE id_player = $playerId AND id_item = 'law_3'")->fetchColumn();
            if ($hasLaw <= 0) {
                sendJson(['state' => 'error_item', 'msg' => 'تتطلب الدراسة من مستوى 25 وجود قانون روماني (law_3)']);
                return;
            }
            ItemController::deductPlayerItem($db, $playerId, 'law_3', 1);
        } elseif ($nextLvl >= 20) {
            $hasLaw = (int)$db->query("SELECT amount FROM player_item WHERE id_player = $playerId AND id_item = 'law_2'")->fetchColumn();
            if ($hasLaw <= 0) {
                sendJson(['state' => 'error_item', 'msg' => 'تتطلب الدراسة من مستوى 20 وجود جدول الثاني عشر (law_2)']);
                return;
            }
            ItemController::deductPlayerItem($db, $playerId, 'law_2', 1);
        } elseif ($nextLvl >= 10) {
            $hasLaw = (int)$db->query("SELECT amount FROM player_item WHERE id_player = $playerId AND id_item = 'law_1'")->fetchColumn();
            if ($hasLaw <= 0) {
                sendJson(['state' => 'error_item', 'msg' => 'تتطلب الدراسة من مستوى 10 وجود قانون دراكو (law_1)']);
                return;
            }
            ItemController::deductPlayerItem($db, $playerId, 'law_1', 1);
        }

        // 3. Resource Check
        $cStmt = $db->prepare("SELECT * FROM city WHERE id_city = ?");
        $cStmt->execute([$cityId]);
        $city = $cStmt->fetch(PDO::FETCH_ASSOC);

        if ((int)$city['food'] < $costFood || (int)$city['wood'] < $costWood || (int)$city['stone'] < $costStone || (int)$city['metal'] < $costMetal || (int)$city['coin'] < $costCoin) {
            sendJson(['state' => 'error_res', 'msg' => 'الموارد المتوفرة بالمدينة غير كافية للبحث']);
            return;
        }

        // Deduct authentic resources
        $db->prepare("UPDATE `city` SET 
            `food`  = GREATEST(0, `food`  - ?), 
            `wood`  = GREATEST(0, `wood`  - ?), 
            `stone` = GREATEST(0, `stone` - ?), 
            `metal` = GREATEST(0, `metal` - ?), 
            `coin`  = GREATEST(0, `coin`  - ?) 
            WHERE `id_city` = ?")
           ->execute([$costFood, $costWood, $costStone, $costMetal, $costCoin, $cityId]);

        $now = time();
        $arrive = $now + $duration;

        $stmt = $db->prepare("INSERT INTO `timed_task` (`id_city`, `id_player`, `task_type`, `item_id`, `lvl_to`, `start_time`, `time_arrive`, `details`) VALUES (?, ?, 'study', ?, ?, ?, ?, ?)");
        $stmt->execute([$cityId, $playerId, $idStudy, $nextLvl, $now, $arrive, json_encode(['study_in' => 'academy'])]);
        $taskId = (int)$db->lastInsertId();

        $cStmt = $db->prepare("SELECT * FROM city WHERE id_city = ?");
        $cStmt->execute([$cityId]);
        $cityRes = $cStmt->fetch();
        foreach ($cityRes as $k => $v) { if (is_numeric($v)) $cityRes[$k] = (int)$v; }

        sendJson([
            'state'   => 'ok',
            'cityRes' => $cityRes,
            'list'    => [
                [
                    'id'           => $taskId,
                    'id_city'      => $cityId,
                    'study'        => $idStudy,
                    'study_in'     => 'academy',
                    'lvl_to'       => $nextLvl,
                    'time_start'   => $now,
                    'time_end'     => $arrive,
                    'time_end_org' => $arrive
                ]
            ]
        ]);
    }

    public static function getPlayerItems($db) {
        $p = self::resolvePlayer($db);
        $idPlayer = (int)$p['id_player'];
        $stmt = $db->prepare("SELECT * FROM `player_item` WHERE `id_player` = ? ORDER BY `id_item` ASC");
        $stmt->execute([$idPlayer]);
        $items = $stmt->fetchAll() ?: [];
        foreach ($items as &$it) {
            $it['id_player'] = (int)$it['id_player'];
            $it['amount']    = (int)$it['amount'];
        }
        sendJson($items);
    }

    public static function getPlayerEquip($db) {
        $p = self::resolvePlayer($db);
        $idPlayer = (int)$p['id_player'];
        $eqStmt = $db->prepare("SELECT id_equip, id_player, id_hero, type, part, lvl, on_hero FROM player_equip WHERE id_player = ?");
        $eqStmt->execute([$idPlayer]);
        $equips = $eqStmt->fetchAll() ?: [];
        foreach ($equips as &$eq) {
            $eq['id_equip']  = (int)$eq['id_equip'];
            $eq['id_player'] = (int)$eq['id_player'];
            $eq['id_hero']   = (int)$eq['id_hero'];
            $eq['lvl']       = (int)$eq['lvl'];
            $eq['on_hero']   = (int)$eq['on_hero'];
        }
        sendJson($equips);
    }

    public static function getEquipPower() {
        $file = __DIR__ . '/../data/equipment-power.json';
        if (!is_file($file) || !is_readable($file)) {
            error_log('[PlayerController] Equipment power data is unavailable');
            http_response_code(500);
            sendJson(['state' => 'error', 'msg' => 'Equipment power data is unavailable']);
        }

        header('Content-Type: text/plain; charset=utf-8');
        readfile($file);
        exit;
    }

    public static function changePlayerName($db) {
        $newName = normalizeGameDisplayName($_REQUEST['NewName'] ?? null);
        if ($newName === null) {
            sendJson(['state' => 'error_name']);
        }
        $player = self::resolvePlayer($db);
        $idPlayer = (int)$player['id_player'];

        $hasItem = (int)$db->query("SELECT amount FROM player_item WHERE id_player = $idPlayer AND id_item = 'change_player_name'")->fetchColumn();
        if ($hasItem > 0) {
            ItemController::deductPlayerItem($db, $idPlayer, 'change_player_name', 1);
        }

        $db->prepare("UPDATE player SET name = ? WHERE id_player = ?")->execute([$newName, $idPlayer]);
        sendJson(['state' => 'ok', 'name' => $newName]);
    }
}
