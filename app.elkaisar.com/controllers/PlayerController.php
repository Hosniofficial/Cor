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
        }
        return false;
    }

    public static function playerEnterServerWeb($db) {
        $stmt = $db->query("SELECT * FROM `player` ORDER BY `id_player` ASC LIMIT 1");
        $player = $stmt->fetch();
        if (!$player) {
            $fresh = createFreshPlayer('البطل سيزر');
            $stmt = $db->prepare("SELECT * FROM `player` WHERE `id_player` = ?");
            $stmt->execute([$fresh['playerId']]);
            $player = $stmt->fetch();
        }

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
        $stmt = $db->query("SELECT * FROM `player_state` ORDER BY `id_player` ASC LIMIT 1");
        $state = $stmt->fetch();
        if ($state) {
            foreach ($state as $k => $v) { $state[$k] = (int)$v; }
            sendJson($state);
        }
        sendJson(['state' => 'ok']);
    }

    public static function refreshPlayerData($db) {
        $stmt = $db->query("SELECT * FROM `player` ORDER BY `id_player` ASC LIMIT 1");
        $p = $stmt->fetch();
        if ($p) {
            $p['id_player'] = (int)$p['id_player'];
            $p['prestige']  = (int)$p['prestige'];
            $p['gold']      = (int)$p['gold'];
            $p['honor']     = (int)$p['honor'];
            sendJson($p);
        }
        sendJson(['state' => 'error']);
    }

    public static function playLuckWheel($db) {
        $prizes = [
            ['Prize' => 'speeder_15', 'amount' => 2],
            ['Prize' => 'food', 'amount' => 50000],
            ['Prize' => 'speeder_60', 'amount' => 1],
            ['Prize' => 'wood', 'amount' => 50000],
            ['Prize' => 'gold_10', 'amount' => 1],
            ['Prize' => 'stone', 'amount' => 50000],
            ['Prize' => 'coin', 'amount' => 100000],
            ['Prize' => 'metal', 'amount' => 50000],
            ['Prize' => 'speeder_15', 'amount' => 1],
            ['Prize' => 'food', 'amount' => 100000],
            ['Prize' => 'trade_carvan', 'amount' => 1],
            ['Prize' => 'wood', 'amount' => 100000],
            ['Prize' => 'gold_50', 'amount' => 1],
            ['Prize' => 'stone', 'amount' => 100000],
            ['Prize' => 'coin', 'amount' => 200000],
            ['Prize' => 'metal', 'amount' => 100000],
            ['Prize' => 'speaker', 'amount' => 5],
            ['Prize' => 'necklace_1', 'amount' => 1],
            ['Prize' => 'necklace_2', 'amount' => 1],
            ['Prize' => 'necklace_3', 'amount' => 1]
        ];
        $winIndex = rand(0, 19);
        sendJson([
            'state'    => 'ok',
            'winIndex' => $winIndex,
            'Prize'    => $prizes
        ]);
    }

    public static function getPlayerEduLvl($db) {
        $stmt = $db->query("SELECT * FROM `player_edu` ORDER BY `id_player` ASC LIMIT 1");
        $edu = $stmt->fetch();
        if ($edu) {
            foreach ($edu as $k => $v) { $edu[$k] = (int)$v; }
            sendJson($edu);
        }
        sendJson(['state' => 'ok']);
    }

    public static function upgradeStudyLvl($db) {
        $cityId  = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 1;
        $idStudy = isset($_REQUEST['idStudy']) ? trim($_REQUEST['idStudy']) : '';

        $stmt = $db->prepare("SELECT c.id_player FROM city c WHERE c.id_city = ?");
        $stmt->execute([$cityId]);
        $playerId = (int)$stmt->fetchColumn() ?: 1;

        $stmt = $db->prepare("SELECT * FROM player_edu WHERE id_player = ?");
        $stmt->execute([$playerId]);
        $edu = $stmt->fetch();
        $currLvl = isset($edu[$idStudy]) ? (int)$edu[$idStudy] : 0;
        $nextLvl = $currLvl + 1;

        $duration = max(30, min(600, $nextLvl * 60));
        $now = time();
        $arrive = $now + $duration;

        $stmt = $db->prepare("UPDATE `city` SET `coin` = GREATEST(0, `coin` - 500), `food` = GREATEST(0, `food` - 300) WHERE `id_city` = ?");
        $stmt->execute([$cityId]);

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
        $stmt = $db->query("SELECT * FROM `player_item` ORDER BY `id_item` ASC");
        $items = $stmt->fetchAll() ?: [];
        foreach ($items as &$it) {
            $it['id_player'] = (int)$it['id_player'];
            $it['amount']    = (int)$it['amount'];
        }
        sendJson($items);
    }

    public static function getPlayerEquip($db) {
        $stmt = $db->query("SELECT id_player FROM player ORDER BY id_player ASC LIMIT 1");
        $idPlayer = (int)$stmt->fetchColumn() ?: 1;
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
        $file = __DIR__ . '/../api/APlayerEquip/getEquipPower.html';
        if (file_exists($file)) {
            header('Content-Type: text/plain; charset=utf-8');
            echo file_get_contents($file);
            exit;
        }
        sendJson([]);
    }
}
