<?php
// WorldController.php - Handles world map tiles, unit data, colonizers, and battles on map units

class WorldController {
    public static function handle($uri, $db) {
        switch ($uri) {
            case '/api/AWorld/getCityData':
                self::getCityData($db);
                return true;
            case '/api/AWorld/BuildNewCity':
                self::buildNewCity($db);
                return true;
            case '/api/AWorld/getBarrayConolizer':
            case '/api/AWorld/getBarrayColonizer':
                self::getBarrayColonizer($db);
                return true;
            case '/api/ABattelRunning/getBattels':
                self::getBattels($db);
                return true;
            case '/api/AWorld/getWorldUnitPrize':
                self::getWorldUnitPrize();
                return true;
            case '/api/ADominant/getArmyCapitalDominant':
            case '/api/dominant.php':
            case '/api/dominant':
                self::getArmyCapitalDominant();
                return true;
            case '/api/ABattelRunning/getUnitBattel':
                self::getUnitBattel($db);
                return true;
            case '/api/ADominant/getCityColonized':
            case '/api/ADominant/getCityColonizer':
                sendJson([]);
                return true;
            case '/api/ABattelRunning/getLeavingHero':
                self::getLeavingHero($db);
                return true;
            case '/api/ABattelRunning/getGarrisonHeros':
            case '/api/ABattelRunning/getSpyRuning':
                sendJson([]);
                return true;
            case '/api/ABattelRunning/getHeroBack':
                self::getHeroBack($db);
                return true;
            case '/api/ASpy/start':
            case '/api/ASpy/cancel':
                sendJson(['state' => 'ok']);
                return true;
            case '/api/AWorldUnit/getWorldUnitDominator':
                self::getBarrayColonizer($db);
                return true;
            case '/api/AWorldUnit/supportByHero':
            case '/api/AWorldUnit/transHero':
            case '/api/AWorldUnit/plundePrize':
                sendJson(['state' => 'ok']);
                return true;
            case '/api/AWorld/refreshWorldUnitLvl':
                self::refreshWorldUnitLvl($db);
                return true;
            case '/api/AWorld/getWorldUnitRank':
            case '/api/AWorld/getGuildAttackQue':
            case '/api/ABattel/applyForRoleInAttQue':
                sendJson(['state' => 'ok']);
                return true;
            case '/api/ABattelRuning/refreshBattelData':
                self::refreshBattelData($db);
                return true;
            case '/api/ABattel/joinBattel':
                self::joinBattel($db);
                return true;
            case '/api/ADominant/abondonColonizedCity':
            case '/api/ADominant/fireColonizer':
                sendJson(['state' => 'ok']);
                return true;
            case '/Battel/abort':
            case '/api/Battel/abort':
                sendJson(['state' => 'ok']);
                return true;
            case '/Battel/getAllWorldBattels':
            case '/api/Battel/getAllWorldBattels':
                self::getBattels($db);
                return true;
            case '/World/getWorldCity':
            case '/api/World/getWorldCity':
                self::getCityData($db);
                return true;
            case '/Chat/sendMsg':
            case '/api/Chat/sendMsg':
            case '/Chat/sendPrivate':
            case '/api/Chat/sendPrivate':
                sendJson(['state' => 'ok']);
                return true;
        }
        return false;
    }

    public static function getWorldUnitPrize() {
        $file = __DIR__ . '/../data/world-unit-prizes.json';
        if (!is_file($file) || !is_readable($file)) {
            error_log('[WorldController] World unit prize data is unavailable');
            http_response_code(500);
            sendJson(['state' => 'error', 'msg' => 'World unit prize data is unavailable']);
        }

        header('Content-Type: text/plain; charset=utf-8');
        readfile($file);
        exit;
    }

    public static function getArmyCapitalDominant() {
        $dataDirectory = __DIR__ . '/../../web.elkaisar.com/js-3.4.113/json/';
        $worldFile = $dataDirectory . 'WorldBarrary.json';
        $unitFile = $dataDirectory . 'worldUnitData.json';

        if (!is_readable($worldFile) || !is_readable($unitFile)) {
            error_log('[WorldController] Army capital source data is unavailable');
            http_response_code(500);
            sendJson(['state' => 'error', 'msg' => 'Army capital source data is unavailable']);
        }

        $worldData = json_decode(file_get_contents($worldFile), true);
        $unitData = json_decode(file_get_contents($unitFile), true);
        if (!is_string($worldData) || !is_array($unitData)) {
            error_log('[WorldController] Army capital source data is invalid');
            http_response_code(500);
            sendJson(['state' => 'error', 'msg' => 'Army capital source data is invalid']);
        }

        $capitalTypes = [150, 151, 152, 153, 154, 155, 105];
        $capitalByType = [];
        foreach (explode(',', $worldData) as $record) {
            $fields = explode('_', $record);
            if (count($fields) !== 6) {
                continue;
            }

            $type = (int)$fields[2];
            if (!in_array($type, $capitalTypes, true)) {
                continue;
            }

            $capitalByType[$type] = [
                'x' => (int)$fields[0],
                'y' => (int)$fields[1],
            ];
        }

        $capitals = [];
        foreach ($capitalTypes as $type) {
            if (!isset($capitalByType[$type], $unitData[$type])
                || !is_array($unitData[$type])
                || !isset($unitData[$type]['Title'])
                || !is_string($unitData[$type]['Title'])) {
                error_log('[WorldController] Army capital data is incomplete for type ' . $type);
                http_response_code(500);
                sendJson(['state' => 'error', 'msg' => 'Army capital data is incomplete']);
            }

            $capitals[] = [
                'title' => $unitData[$type]['Title'],
                'x' => $capitalByType[$type]['x'],
                'y' => $capitalByType[$type]['y'],
                'type' => $type,
                'rank' => [],
            ];
        }

        sendJson([
            'state' => 'ok',
            'type' => 'domain',
            'dominanceDataAvailable' => false,
            'unit' => $capitals,
        ]);
    }

    public static function getCityData($db) {
        $x = isset($_REQUEST['xCoord']) ? (int)$_REQUEST['xCoord'] : 0;
        $y = isset($_REQUEST['yCoord']) ? (int)$_REQUEST['yCoord'] : 0;
        $stmt = $db->prepare("SELECT c.name as CityName, c.id_city, c.id_player, p.name as PlayerName, p.prestige, p.avatar, p.guild as GuildName FROM city c JOIN player p ON c.id_player = p.id_player WHERE c.x = ? AND c.y = ? LIMIT 1");
        $stmt->execute([$x, $y]);
        $cityData = $stmt->fetch();
        if ($cityData) {
            $cityData['id_player'] = (int)$cityData['id_player'];
            $cityData['id_city']   = (int)$cityData['id_city'];
            $cityData['prestige']  = (int)$cityData['prestige'];
            $cityData['avatar']    = (int)$cityData['avatar'];
            $cityData['GuildName'] = $cityData['GuildName'] ?: 'بدون تحالف';
        } else {
            $cityData = [
                'id_player'  => 0,
                'id_city'    => 0,
                'PlayerName' => 'حاكم مجهول',
                'CityName'   => 'مدينة مهجورة',
                'prestige'   => 0,
                'GuildName'  => 'بدون تحالف',
                'avatar'     => 0
            ];
        }
        sendJson($cityData);
    }

    public static function getBarrayColonizer($db) {
        $x = isset($_REQUEST['xCoord']) ? (int)$_REQUEST['xCoord'] : 0;
        $y = isset($_REQUEST['yCoord']) ? (int)$_REQUEST['yCoord'] : 0;
        $stmt = $db->prepare("SELECT c.name as CityName, c.x, c.y, p.name as PlayerName, p.guild as GuildName 
                              FROM city_barray b 
                              JOIN city c ON b.id_city = c.id_city 
                              JOIN player p ON b.id_player = p.id_player 
                              WHERE b.x_coord = ? AND b.y_coord = ? LIMIT 1");
        $stmt->execute([$x, $y]);
        $row = $stmt->fetch();
        if ($row) {
            $row['x'] = (int)$row['x'];
            $row['y'] = (int)$row['y'];
            $row['GuildName'] = $row['GuildName'] ?: 'بدون تحالف';
            sendJson([$row]);
        }
        sendJson([]);
    }

    public static function getBattels($db) {
        $stmt = $db->query("SELECT b.*, p.name as PlayerName, c.name as CityName,
                                   (SELECT COUNT(*) FROM world_battel_member m
                                    WHERE m.id_battel = b.id_battel AND m.side = 1
                                      AND m.state IN ('marching', 'won', 'lost')) AS attackNum,
                                   (SELECT COUNT(*) FROM world_battel_member m
                                    WHERE m.id_battel = b.id_battel AND m.side = 0
                                      AND m.state IN ('marching', 'won', 'lost')) AS defenceNum
                            FROM world_battel b 
                            JOIN player p ON b.id_player = p.id_player 
                            JOIN city c ON b.id_city = c.id_city 
                            WHERE b.state = 'marching' AND b.time_arrive > UNIX_TIMESTAMP()
                            ORDER BY b.id_battel DESC");
        $rows = $stmt->fetchAll() ?: [];
        foreach ($rows as &$r) {
            foreach (['id_battel', 'id_player', 'id_city', 'id_hero', 'task', 'x_city', 'y_city', 'x_coord', 'y_coord', 'time_start', 'time_arrive'] as $col) {
                if (isset($r[$col])) $r[$col] = (int)$r[$col];
            }
            $r['attackNum'] = (int)$r['attackNum'];
            $r['defenceNum'] = (int)$r['defenceNum'];
        }
        sendJson($rows);
    }

    public static function getUnitBattel($db) {
        $x = filter_var($_REQUEST['xCoord'] ?? null, FILTER_VALIDATE_INT);
        $y = filter_var($_REQUEST['yCoord'] ?? null, FILTER_VALIDATE_INT);
        if ($x === false || $y === false || $x === null || $y === null || $x < 0 || $x > 499 || $y < 0 || $y > 499) {
            http_response_code(400);
            sendJson(['state' => 'error_invalid_coordinates']);
            return;
        }

        $stmt = $db->prepare("SELECT b.*, p.name AS PlayerName, c.name AS CityName,
                                     (SELECT COUNT(*) FROM world_battel_member m
                                      WHERE m.id_battel = b.id_battel AND m.side = 1
                                        AND m.state IN ('marching', 'won', 'lost')) AS attackNum,
                                     (SELECT COUNT(*) FROM world_battel_member m
                                      WHERE m.id_battel = b.id_battel AND m.side = 0
                                        AND m.state IN ('marching', 'won', 'lost')) AS defenceNum
                              FROM world_battel b
                              JOIN player p ON p.id_player = b.id_player
                              JOIN city c ON c.id_city = b.id_city
                              WHERE b.x_coord = ? AND b.y_coord = ?
                                AND b.state = 'marching' AND b.time_arrive > UNIX_TIMESTAMP()
                              ORDER BY b.id_battel DESC");
        $stmt->execute([$x, $y]);
        $rows = $stmt->fetchAll() ?: [];
        foreach ($rows as &$row) {
            foreach (['id_battel', 'id_player', 'id_city', 'id_hero', 'task', 'x_city', 'y_city', 'x_coord', 'y_coord', 'time_start', 'time_arrive'] as $column) {
                if (isset($row[$column])) $row[$column] = (int)$row[$column];
            }
            $row['attackNum'] = (int)$row['attackNum'];
            $row['defenceNum'] = (int)$row['defenceNum'];
        }
        sendJson($rows);
    }

    public static function refreshBattelData($db) {
        $idBattle = filter_var($_REQUEST['idBattel'] ?? null, FILTER_VALIDATE_INT);
        if ($idBattle === false || $idBattle === null || $idBattle <= 0) {
            http_response_code(400);
            sendJson(['state' => 'error_invalid_battle']);
            return;
        }

        $stmt = $db->prepare("SELECT b.id_battel,
                                     (SELECT COUNT(*) FROM world_battel_member m
                                      WHERE m.id_battel = b.id_battel AND m.side = 1
                                        AND m.state IN ('marching', 'won', 'lost')) AS attackNum,
                                     (SELECT COUNT(*) FROM world_battel_member m
                                      WHERE m.id_battel = b.id_battel AND m.side = 0
                                        AND m.state IN ('marching', 'won', 'lost')) AS defenceNum
                              FROM world_battel b
                              WHERE b.id_battel = ? AND b.state = 'marching' AND b.time_arrive > UNIX_TIMESTAMP()
                              LIMIT 1");
        $stmt->execute([$idBattle]);
        $battle = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$battle) {
            http_response_code(404);
            sendJson(['state' => 'error_battle_not_found']);
            return;
        }
        sendJson([
            'state' => 'ok',
            'attackNum' => (int)$battle['attackNum'],
            'defenceNum' => (int)$battle['defenceNum']
        ]);
    }

    private static function getStaticWorldUnit($x, $y) {
        static $units = null;
        if ($units === null) {
            $path = __DIR__ . '/../../web.elkaisar.com/js-3.4.113/json/WorldBarrary.json';
            $raw = is_readable($path) ? file_get_contents($path) : false;
            $map = $raw === false ? null : json_decode($raw, true);
            if (!is_string($map)) {
                throw new RuntimeException('World map source data is unavailable or invalid');
            }
            $units = [];
            foreach (explode(',', $map) as $entry) {
                $parts = explode('_', $entry);
                if (count($parts) >= 6) {
                    $units[$parts[0] . '_' . $parts[1]] = [
                        'ut' => (int)$parts[2],
                        'lvl' => (int)$parts[3],
                        'subtype' => (int)$parts[5]
                    ];
                }
            }
        }
        return $units[$x . '_' . $y] ?? null;
    }

    private static function getWorldUnitTypeData() {
        static $unitData = null;
        if ($unitData === null) {
            $path = __DIR__ . '/../js-3.4.113/json/worldUnitData.json';
            $raw = is_readable($path) ? file_get_contents($path) : false;
            $unitData = $raw === false ? null : json_decode($raw, true);
            if (!is_array($unitData)) {
                throw new RuntimeException('World unit requirements are unavailable or invalid');
            }
        }
        return $unitData;
    }

    private static function getJoinMarchDuration($x1, $y1, $x2, $y2, $army, $unitType, $isCity, $config, $applySpeedMultiplier = true) {
        $deltaX = abs((int)$x1 - (int)$x2);
        $deltaY = abs((int)$y1 - (int)$y2);
        if ($deltaX > 249) $deltaX = 500 - $deltaX;
        if ($deltaY > 249) $deltaY = 500 - $deltaY;
        $distance = floor(sqrt($deltaX * $deltaX + $deltaY * $deltaY)) * 6000;

        $speedByType = [1 => 300, 2 => 900, 3 => 600, 4 => 250, 5 => 150, 6 => 100];
        $speeds = [];
        foreach (['f_1', 'f_2', 'f_3', 'b_1', 'b_2', 'b_3'] as $slot) {
            $type = (int)($army[$slot . '_type'] ?? 0);
            $count = (int)($army[$slot . '_num'] ?? 0);
            if ($count > 0 && isset($speedByType[$type])) $speeds[] = $speedByType[$type];
        }
        $speed = $speeds ? min($speeds) : 900;
        $duration = max(1, (int)ceil($distance / $speed));
        if ((int)$unitType === 177) $duration = 300;
        elseif (((int)$unitType >= 70 && (int)$unitType <= 105)
            || ((int)$unitType >= 150 && (int)$unitType <= 155)
            || ((int)$unitType >= 175 && (int)$unitType <= 177)
            || ((int)$unitType >= 200 && (int)$unitType <= 205)) {
            $duration = (int)($config['camp_rally_duration_sec'] ?? 120);
        } elseif ($isCity) {
            $duration = max((int)($config['city_attack_min_duration_sec'] ?? 900), $duration);
        }
        if ($applySpeedMultiplier) {
            $duration = (int)ceil($duration / max(0.1, (float)($config['march_speed_multiplier'] ?? 1)));
        }
        return max(1, $duration);
    }

    public static function joinBattel($db) {
        $player = requireAuthenticatedPlayer($db);
        $idBattle = filter_var($_REQUEST['idBattel'] ?? null, FILTER_VALIDATE_INT);
        $idHero = filter_var($_REQUEST['idHero'] ?? null, FILTER_VALIDATE_INT);
        $side = filter_var($_REQUEST['side'] ?? null, FILTER_VALIDATE_INT);
        if ($idBattle === false || $idBattle === null || $idBattle <= 0
            || $idHero === false || $idHero === null || $idHero <= 0
            || ($side !== 0 && $side !== 1)) {
            sendJson(['state' => 'error_8_1']);
            return;
        }

        $playerId = (int)$player['id_player'];
        $now = time();
        $db->beginTransaction();
        try {
            $battleStmt = $db->prepare(
                "SELECT b.*, p.id_guild, p.name AS PlayerName, c.name AS CityName " .
                "FROM world_battel b JOIN player p ON p.id_player = b.id_player " .
                "JOIN city c ON c.id_city = b.id_city " .
                "WHERE b.id_battel = ? AND b.state = 'marching' AND b.time_arrive > ? FOR UPDATE"
            );
            $battleStmt->execute([$idBattle, $now]);
            $battle = $battleStmt->fetch(PDO::FETCH_ASSOC);
            if (!$battle) {
                $db->rollBack();
                sendJson(['state' => 'error_2']);
                return;
            }

            $heroStmt = $db->prepare(
                "SELECT h.*, c.id_city, c.x AS x_city, c.y AS y_city, p.id_guild " .
                "FROM city_hero h JOIN city c ON c.id_city = h.id_city " .
                "JOIN player p ON p.id_player = h.id_player " .
                "WHERE h.id_hero = ? AND h.id_player = ? AND h.in_city = 1 FOR UPDATE"
            );
            $heroStmt->execute([$idHero, $playerId]);
            $hero = $heroStmt->fetch(PDO::FETCH_ASSOC);
            if (!$hero) {
                $db->rollBack();
                sendJson(['state' => 'error_1']);
                return;
            }

            $armyStmt = $db->prepare('SELECT * FROM hero_army WHERE id_hero = ? FOR UPDATE');
            $armyStmt->execute([$idHero]);
            $army = $armyStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $troops = 0;
            foreach (['f_1', 'f_2', 'f_3', 'b_1', 'b_2', 'b_3'] as $slot) {
                $troops += (int)($army[$slot . '_num'] ?? 0);
            }
            if ($troops <= 0) {
                $db->rollBack();
                sendJson(['state' => 'error_8_1']);
                return;
            }

            $typeData = self::getWorldUnitTypeData();
            $unitType = 0;
            $unitLevel = 1;
            $subtype = 0;
            $targetCityStmt = $db->prepare('SELECT id_city, id_player, lvl FROM city WHERE x = ? AND y = ? LIMIT 1');
            $targetCityStmt->execute([(int)$battle['x_coord'], (int)$battle['y_coord']]);
            $targetCity = $targetCityStmt->fetch(PDO::FETCH_ASSOC);
            if ($targetCity) {
                $unitType = 60;
                $unitLevel = max(1, (int)$targetCity['lvl']);
                if ($side === 0) {
                    $ownerStmt = $db->prepare('SELECT id_guild FROM player WHERE id_player = ? LIMIT 1');
                    $ownerStmt->execute([(int)$targetCity['id_player']]);
                    $ownerGuild = (int)$ownerStmt->fetchColumn();
                    if ($ownerGuild <= 0 || (int)$hero['id_guild'] !== $ownerGuild) {
                        $db->rollBack();
                        sendJson(['state' => 'error_5']);
                        return;
                    }
                }
            } else {
                $barrayStmt = $db->prepare('SELECT Type, lvl, id_player FROM city_barray WHERE x_coord = ? AND y_coord = ? LIMIT 1');
                $barrayStmt->execute([(int)$battle['x_coord'], (int)$battle['y_coord']]);
                $barray = $barrayStmt->fetch(PDO::FETCH_ASSOC);
                if ($barray) {
                    $unitType = (int)$barray['Type'];
                    $unitLevel = max(1, (int)$barray['lvl']);
                } else {
                    $worldUnit = self::getStaticWorldUnit((int)$battle['x_coord'], (int)$battle['y_coord']);
                    if ($worldUnit) {
                        $unitType = (int)$worldUnit['ut'];
                        $unitLevel = max(1, (int)$worldUnit['lvl']);
                        $subtype = (int)$worldUnit['subtype'];
                        $stateStmt = $db->prepare(
                            'SELECT base_lvl, current_lvl, next_reset_at FROM world_unit_state ' .
                            'WHERE x_coord = ? AND y_coord = ? AND unit_type = ? AND unit_subtype = ? LIMIT 1'
                        );
                        $stateStmt->execute([(int)$battle['x_coord'], (int)$battle['y_coord'], $unitType, $subtype]);
                        $worldState = $stateStmt->fetch(PDO::FETCH_ASSOC);
                        if ($worldState) {
                            $unitLevel = (int)$worldState['next_reset_at'] > 0 && (int)$worldState['next_reset_at'] <= $now
                                ? (int)$worldState['base_lvl']
                                : (int)$worldState['current_lvl'];
                        }
                    }
                }
            }
            if (!$unitType) {
                $db->rollBack();
                sendJson(['state' => 'error_8_1']);
                return;
            }

            if ($side === 1) {
                $hostGuild = (int)$battle['id_guild'];
                if (($hostGuild > 0 && (int)$hero['id_guild'] !== $hostGuild)
                    || ($hostGuild <= 0 && (int)$battle['id_player'] !== $playerId)) {
                    $db->rollBack();
                    sendJson(['state' => 'error_5']);
                    return;
                }
            } elseif (!$targetCity) {
                $db->rollBack();
                sendJson(['state' => 'error_5']);
                return;
            }

            $unitTypeData = $typeData[(string)$unitType] ?? [];
            if ((int)($unitTypeData['reqFitness'] ?? 0) > (int)($hero['power'] ?? 0)) {
                $db->rollBack();
                sendJson(['state' => 'error_7']);
                return;
            }
            $configStmt = $db->query(
                "SELECT config_key, config_value FROM server_config " .
                "WHERE config_key IN ('march_speed_multiplier', 'camp_rally_duration_sec', 'city_attack_min_duration_sec')"
            );
            $config = [
                'march_speed_multiplier' => 1,
                'camp_rally_duration_sec' => 120,
                'city_attack_min_duration_sec' => 900
            ];
            foreach ($configStmt->fetchAll(PDO::FETCH_ASSOC) as $configRow) {
                $config[$configRow['config_key']] = (float)$configRow['config_value'];
            }
            $joinDuration = self::getJoinMarchDuration(
                (int)$hero['x_city'],
                (int)$hero['y_city'],
                (int)$battle['x_coord'],
                (int)$battle['y_coord'],
                $army,
                $unitType,
                $unitType === 60,
                $config
            );
            if ($now + $joinDuration > (int)$battle['time_arrive']) {
                $db->rollBack();
                sendJson(['state' => 'error_2']);
                return;
            }

            if ($side === 0) {
                $countStmt = $db->prepare("SELECT COUNT(*) FROM world_battel_member WHERE id_battel = ? AND side = 0 AND state = 'marching'");
                $countStmt->execute([$idBattle]);
                $maxHeroes = max(1, (int)($unitTypeData['heroNum'] ?? 3));
                if ((int)$countStmt->fetchColumn() >= $maxHeroes) {
                    $db->rollBack();
                    sendJson(['state' => 'error_3']);
                    return;
                }
            }

            foreach ($unitTypeData['JoinReq'] ?? [] as $requirement) {
                $item = (string)($requirement['Item'] ?? '');
                $amount = (int)($requirement['amount'] ?? 0);
                if ($item === '' || $amount <= 0) {
                    throw new RuntimeException('Invalid world-unit join requirement');
                }
                $itemStmt = $db->prepare(
                    'UPDATE player_item SET amount = amount - ? WHERE id_player = ? AND id_item = ? AND amount >= ?'
                );
                $itemStmt->execute([$amount, $playerId, $item, $amount]);
                if ($itemStmt->rowCount() !== 1) {
                    $db->rollBack();
                    sendJson(['state' => 'error_6']);
                    return;
                }
            }

            $arriveAt = $now + $joinDuration;
            $returnDuration = self::getJoinMarchDuration(
                (int)$hero['x_city'],
                (int)$hero['y_city'],
                (int)$battle['x_coord'],
                (int)$battle['y_coord'],
                $army,
                0,
                false,
                $config,
                false
            );
            $heroUpdate = $db->prepare('UPDATE city_hero SET in_city = 0 WHERE id_hero = ? AND id_player = ? AND in_city = 1');
            $heroUpdate->execute([$idHero, $playerId]);
            if ($heroUpdate->rowCount() !== 1) {
                $db->rollBack();
                sendJson(['state' => 'error_1']);
                return;
            }
            $insert = $db->prepare(
                "INSERT INTO world_battel_member " .
                "(id_battel, id_player, id_city, id_hero, side, task, x_city, y_city, time_start, time_arrive, return_duration, state) " .
                "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'marching')"
            );
            $insert->execute([
                $idBattle, $playerId, (int)$hero['id_city'], $idHero, $side, (int)$battle['task'],
                (int)$hero['x_city'], (int)$hero['y_city'], $now, $arriveAt, $returnDuration
            ]);

            $countsStmt = $db->prepare(
                "SELECT side, COUNT(*) AS amount FROM world_battel_member WHERE id_battel = ? AND state IN ('marching', 'won', 'lost') GROUP BY side"
            );
            $countsStmt->execute([$idBattle]);
            $counts = [0 => 0, 1 => 0];
            foreach ($countsStmt->fetchAll(PDO::FETCH_ASSOC) as $countRow) {
                $counts[(int)$countRow['side']] = (int)$countRow['amount'];
            }
            $db->commit();

            sendJson([
                'state' => 'ok',
                'unit_type' => $unitType,
                'unit_lvl' => $unitLevel,
                'Battel' => [
                    'id_battel' => $idBattle,
                    'id_player' => (int)$battle['id_player'],
                    'PlayerName' => $battle['PlayerName'],
                    'CityName' => $battle['CityName'],
                    'x_coord' => (int)$battle['x_coord'],
                    'y_coord' => (int)$battle['y_coord'],
                    'x_city' => (int)$battle['x_city'],
                    'y_city' => (int)$battle['y_city'],
                    'task' => (int)$battle['task'],
                    'time_start' => (int)$battle['time_start'],
                    'time_arrive' => (int)$battle['time_arrive'],
                    'attackNum' => $counts[1],
                    'defenceNum' => $counts[0]
                ]
            ]);
        } catch (Throwable $error) {
            if ($db->inTransaction()) $db->rollBack();
            throw $error;
        }
    }

    const MAX_CITIES = 10;

    public static function buildNewCity($db) {
        $player   = requireAuthenticatedPlayer($db);
        $idCity   = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 0;
        $xCoord   = isset($_REQUEST['xCoord']) ? (int)$_REQUEST['xCoord'] : 0;
        $yCoord   = isset($_REQUEST['yCoord']) ? (int)$_REQUEST['yCoord'] : 0;
        $cityName = normalizeGameDisplayName($_REQUEST['CityName'] ?? null);

        if ($cityName === null) {
            sendJson(['state' => 'error_0', 'msg' => 'اسم المدينة غير صالح']);
            return;
        }

        $city = requirePlayerOwnedCity($db, $player, $idCity);
        $idPlayer = (int)$player['id_player'];

        $db->beginTransaction();
        $coordinateLockName = null;
        $coordinateLockAcquired = false;
        try {
            $playerLockStmt = $db->prepare("SELECT porm FROM player WHERE id_player = ? FOR UPDATE");
            $playerLockStmt->execute([$idPlayer]);
            $lockedPorm = $playerLockStmt->fetchColumn();
            if ($lockedPorm === false) {
                http_response_code(403);
                sendJson(['state' => 'error_auth']);
            }
            $player['porm'] = (int)$lockedPorm;

            $cityLockStmt = $db->prepare("SELECT * FROM city WHERE id_city = ? AND id_player = ? FOR UPDATE");
            $cityLockStmt->execute([$idCity, $idPlayer]);
            $city = $cityLockStmt->fetch(PDO::FETCH_ASSOC);
            if (!$city) {
                http_response_code(403);
                sendJson(['state' => 'error_auth']);
            }

            // 2. Count current cities & enforce hard cap (10 cities maximum)
            $cCountStmt = $db->prepare("SELECT COUNT(*) FROM city WHERE id_player = ?");
            $cCountStmt->execute([$idPlayer]);
            $cityCount = (int)$cCountStmt->fetchColumn();

            if ($cityCount >= self::MAX_CITIES) {
                sendJson(['state' => 'error_1', 'msg' => 'لقد بلغت الحد الأقصى للمدن المسموح به (10 مدن)']);
                return;
            }

            // 3. Check promotion requirement: porm >= city_counts * 2
            $porm = (int)($player['porm'] ?? 0);
            $reqPorm = $cityCount * 2;
            if ($porm < $reqPorm) {
                sendJson(['state' => 'error_1', 'msg' => "رتبتك لا تكفى لبناء مدينة جديدة (الرتبة المطلوبة: $reqPorm)"]);
                return;
            }

            // 4. Check resource requirement: 10^(cityCount + 3)
            $reqRes = (int)pow(10, $cityCount + 3);
            if ($city['food'] < $reqRes || $city['wood'] < $reqRes || $city['stone'] < $reqRes || $city['metal'] < $reqRes || $city['coin'] < $reqRes) {
                sendJson(['state' => 'error_2', 'msg' => 'لا توجد موارد كافية لبناء المدينة']);
                return;
            }

            // 5. Validate map coordinates & check if target spot is empty
            if ($xCoord < 0 || $xCoord >= 500 || $yCoord < 0 || $yCoord >= 500) {
                sendJson(['state' => 'error_3', 'msg' => 'الإحداثيات المحددة خارج حدود خريطة العالم']);
                return;
            }

            // The legacy city schema has no unique index for map coordinates.
            $coordinateLockName = "world_city_{$xCoord}_{$yCoord}";
            $coordinateLockStmt = $db->prepare("SELECT GET_LOCK(?, 5)");
            $coordinateLockStmt->execute([$coordinateLockName]);
            $coordinateLockAcquired = (int)$coordinateLockStmt->fetchColumn() === 1;
            if (!$coordinateLockAcquired) {
                http_response_code(503);
                sendJson(['state' => 'error_world_busy']);
            }

            // 5a. Check if occupied by any city
            $checkStmt = $db->prepare("SELECT id_city FROM city WHERE x = ? AND y = ?");
            $checkStmt->execute([$xCoord, $yCoord]);
            if ($checkStmt->fetch()) {
                sendJson(['state' => 'error_3', 'msg' => 'المكان مشغول بالفعل بمدينة أخرى']);
                return;
            }

            // 5b. Check if occupied by colonized wild
            $barStmt = $db->prepare("SELECT id_barray FROM city_barray WHERE x_coord = ? AND y_coord = ?");
            $barStmt->execute([$xCoord, $yCoord]);
            if ($barStmt->fetch()) {
                sendJson(['state' => 'error_3', 'msg' => 'المكان مشغول بالفعل ببرية مستعمرة']);
                return;
            }

            // 5c. Check WorldBarrary.json: ensure tile is empty (ut = 0)
            $wbPath = __DIR__ . '/../../web.elkaisar.com/js-3.4.113/json/WorldBarrary.json';
            if (file_exists($wbPath)) {
                $wbContent = file_get_contents($wbPath);
                if (preg_match('/(^|[,"]\s*)' . $xCoord . '_' . $yCoord . '_([1-9][0-9]*)/', $wbContent)) {
                    sendJson(['state' => 'error_3', 'msg' => 'المكان غير خالٍ (يوجد معلم جغرافي أو برية في هذا الموضع)']);
                    return;
                }
            }

            // 6. Deduct resources from originating city
            $deductStmt = $db->prepare("UPDATE city SET food = food - ?, wood = wood - ?, stone = stone - ?, metal = metal - ?, coin = coin - ? WHERE id_city = ? AND id_player = ? AND food >= ? AND wood >= ? AND stone >= ? AND metal >= ? AND coin >= ?");
            $deductStmt->execute([$reqRes, $reqRes, $reqRes, $reqRes, $reqRes, $idCity, $idPlayer, $reqRes, $reqRes, $reqRes, $reqRes, $reqRes]);
            if ($deductStmt->rowCount() !== 1) {
                $db->rollBack();
                $db->prepare("SELECT RELEASE_LOCK(?)")->execute([$coordinateLockName]);
                $coordinateLockAcquired = false;
                sendJson(['state' => 'error_2', 'msg' => 'لا توجد موارد كافية لبناء المدينة']);
            }

            // 7. Insert new city record with initial expansion lvl = 0
            $insStmt = $db->prepare("INSERT INTO city (id_player, name, x, y, lvl, food, wood, stone, metal, coin, food_cap, wood_cap, stone_cap, metal_cap, coin_cap, pop, pop_max, pop_cap, loy, dis_loy, taxs, helper, pop_LSS)
                                     VALUES (?, ?, ?, ?, 0, 10000, 10000, 10000, 10000, 10000, 100000, 100000, 100000, 100000, 100000, 100, 100, 100, 100, 0, 10, 0, ?)");
            $insStmt->execute([$idPlayer, $cityName, $xCoord, $yCoord, time()]);
            $newCityId = (int)$db->lastInsertId();

            // 8. Insert all standard 65 city building slots with starter buildings
            $standardPlaces = [
                'above_palace_1', 'above_palace_2', 'above_palace_3', 'above_palace_4', 'above_palace_5', 'above_palace_6',
                'around_wood_1', 'around_wood_2', 'around_wood_3',
                'farm', 'wood', 'stone', 'mine', 'palace', 'wall', 'lighthouse', 'seaport',
                'hill_1', 'hill_2', 'hill_3', 'hill_4', 'hill_5', 'hill_6', 'hill_7', 'hill_8', 'hill_9', 'hill_10', 'hill_11', 'hill_12',
                'light_house_1', 'light_house_2', 'light_house_3', 'light_house_4', 'light_house_5', 'light_house_6', 'light_house_7', 'light_house_8', 'light_house_9', 'light_house_10',
                'under_palace_1', 'under_palace_2', 'under_palace_3', 'under_palace_4', 'under_palace_5', 'under_palace_6', 'under_palace_7', 'under_palace_8', 'under_palace_9', 'under_palace_10', 'under_palace_11', 'under_palace_12',
                'under_wall_1', 'under_wall_2', 'under_wall_3', 'under_wall_4', 'under_wall_5', 'under_wall_6', 'under_wall_7', 'under_wall_8', 'under_wall_9', 'under_wall_10', 'under_wall_11', 'under_wall_12'
            ];

            $bIns = $db->prepare("INSERT INTO city_building (id_city, place, type, lvl) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE type = VALUES(type), lvl = VALUES(lvl)");
            foreach ($standardPlaces as $place) {
                $type = 0;
                $lvl = 0;
                if ($place === 'palace')     { $type = 12; $lvl = 1; }
                elseif ($place === 'wall')   { $type = 13; $lvl = 1; }
                elseif ($place === 'farm')   { $type = 16; $lvl = 1; }
                elseif ($place === 'wood')   { $type = 15; $lvl = 1; }
                elseif ($place === 'stone')  { $type = 18; $lvl = 1; }
                elseif ($place === 'mine')   { $type = 17; $lvl = 1; }
                elseif ($place === 'lighthouse') { $type = 20; $lvl = 0; }
                elseif ($place === 'seaport')    { $type = 19; $lvl = 0; }
                $bIns->execute([$newCityId, $place, $type, $lvl]);
            }

            // 9. Initialize city_jop with standard 10 base jobs and 100% rates
            $db->prepare("INSERT INTO city_jop (id_city, id_player, food, wood, stone, metal, food_rate, wood_rate, stone_rate, metal_rate) 
                          VALUES (?, ?, 10, 10, 10, 10, 100, 100, 100, 100) 
                          ON DUPLICATE KEY UPDATE id_city=id_city")
               ->execute([$newCityId, $idPlayer]);

            // 10. Initialize city_storage
            $db->prepare("INSERT INTO city_storage (id_city, food_perc, wood_perc, stone_perc, metal_perc) 
                          VALUES (?, 25, 25, 25, 25) 
                          ON DUPLICATE KEY UPDATE id_city=id_city")
               ->execute([$newCityId]);

            // 11. Recalculate production for both originating city and the new city
            require_once __DIR__ . '/../services/ProductionService.php';
            ProductionService::recalculateCityProduction($db, $newCityId);
            ProductionService::recalculateCityProduction($db, $idCity);

            // 12. Fetch new city data and format integer values
            $getNewStmt = $db->prepare("SELECT * FROM city WHERE id_city = ?");
            $getNewStmt->execute([$newCityId]);
            $newCity = $getNewStmt->fetch();

            foreach ($newCity as $f => $v) {
                if (is_numeric($v)) $newCity[$f] = (int)$v;
            }

            $db->commit();
            $db->prepare("SELECT RELEASE_LOCK(?)")->execute([$coordinateLockName]);
            $coordinateLockAcquired = false;
            sendJson(['state' => 'ok', 'City' => $newCity]);
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if ($coordinateLockAcquired) {
                $db->prepare("SELECT RELEASE_LOCK(?)")->execute([$coordinateLockName]);
            }
            throw $error;
        }
    }

    public static function getLeavingHero($db) {
        $player = requireAuthenticatedPlayer($db);
        $stmt = $db->prepare("SELECT m.id_battel, m.id_hero, m.task, m.x_city, m.y_city,
                                     b.x_coord, b.y_coord, m.time_arrive AS time_end
                              FROM world_battel_member m
                              JOIN world_battel b ON b.id_battel = m.id_battel
                              WHERE m.id_player = ? AND m.state = 'marching'
                                AND m.time_arrive > UNIX_TIMESTAMP()
                              ORDER BY m.time_arrive ASC");
        $stmt->execute([(int)$player['id_player']]);
        $rows = $stmt->fetchAll() ?: [];
        foreach ($rows as &$row) {
            foreach (['id_battel', 'id_hero', 'task', 'x_city', 'y_city', 'x_coord', 'y_coord', 'time_end'] as $column) {
                $row[$column] = (int)$row[$column];
            }
        }
        sendJson($rows);
    }

    public static function getHeroBack($db) {
        $player = requireAuthenticatedPlayer($db);
        $stmt = $db->prepare("SELECT m.id_hero, m.task AS Task, b.x_coord AS xFrom,
                                     b.y_coord AS yFrom, m.x_city AS xTo, m.y_city AS yTo,
                                     m.time_back
                              FROM world_battel_member m
                              JOIN world_battel b ON b.id_battel = m.id_battel
                              WHERE m.id_player = ? AND m.state IN ('won', 'lost')
                                AND m.time_back > UNIX_TIMESTAMP()
                              ORDER BY m.time_back ASC");
        $stmt->execute([(int)$player['id_player']]);
        $rows = $stmt->fetchAll() ?: [];
        foreach ($rows as &$row) {
            foreach (['id_hero', 'Task', 'xFrom', 'yFrom', 'xTo', 'yTo', 'time_back'] as $column) {
                $row[$column] = (int)$row[$column];
            }
        }
        sendJson($rows);
    }

    public static function refreshWorldUnitLvl($db) {
        $x = filter_var($_REQUEST['xCoord'] ?? null, FILTER_VALIDATE_INT);
        $y = filter_var($_REQUEST['yCoord'] ?? null, FILTER_VALIDATE_INT);
        if ($x === false || $y === false || $x === null || $y === null || $x < 0 || $x > 499 || $y < 0 || $y > 499) {
            http_response_code(400);
            sendJson(['state' => 'error_invalid_coordinates']);
            return;
        }

        $unitType = filter_var($_REQUEST['unitType'] ?? null, FILTER_VALIDATE_INT);
        $reqLvl = filter_var($_REQUEST['lvl'] ?? null, FILTER_VALIDATE_INT);
        if ($unitType === false || $reqLvl === false || $unitType === null || $reqLvl === null) {
            http_response_code(400);
            sendJson(['state' => 'error_invalid_world_unit']);
            return;
        }

        // Resolve the requested level from the server-owned map state.
        $barStmt = $db->prepare("SELECT Type, lvl FROM city_barray WHERE x_coord = ? AND y_coord = ? LIMIT 1");
        $barStmt->execute([$x, $y]);
        $barRow = $barStmt->fetch(PDO::FETCH_ASSOC);

        if ($barRow) {
            $unitType = (int)$barRow['Type'];
            $reqLvl = (int)$barRow['lvl'];
        } else {
            $worldUnit = self::getStaticWorldUnit($x, $y);
            if (!$worldUnit) {
                http_response_code(404);
                sendJson(['state' => 'error_world_unit_not_found']);
                return;
            }
            $unitType = (int)$worldUnit['ut'];
            $reqLvl = max(1, (int)$worldUnit['lvl']);
            $stateStmt = $db->prepare(
                'SELECT base_lvl, current_lvl, next_reset_at FROM world_unit_state ' .
                'WHERE x_coord = ? AND y_coord = ? AND unit_type = ? AND unit_subtype = ? LIMIT 1'
            );
            $stateStmt->execute([$x, $y, $unitType, (int)$worldUnit['subtype']]);
            $worldState = $stateStmt->fetch(PDO::FETCH_ASSOC);
            if ($worldState) {
                $reqLvl = (int)$worldState['next_reset_at'] > 0 && (int)$worldState['next_reset_at'] <= time()
                    ? (int)$worldState['base_lvl']
                    : (int)$worldState['current_lvl'];
            }
        }

        $unitDataPath = __DIR__ . '/../js-3.4.113/json/worldUnitData.json';
        $unitArmyPath = __DIR__ . '/../js-3.4.113/json/UnitArmy.json';
        static $cachedUnitData = null;
        static $cachedUnitArmy = null;
        if ($cachedUnitData === null || $cachedUnitArmy === null) {
            $rawUnitData = is_readable($unitDataPath) ? file_get_contents($unitDataPath) : false;
            $rawUnitArmy = is_readable($unitArmyPath) ? file_get_contents($unitArmyPath) : false;
            $cachedUnitData = $rawUnitData === false ? null : json_decode($rawUnitData, true);
            $cachedUnitArmy = $rawUnitArmy === false ? null : json_decode($rawUnitArmy, true);
        }

        if (!is_array($cachedUnitData) || !is_array($cachedUnitArmy)) {
            error_log('[WorldController] World unit army data is unavailable or invalid');
            http_response_code(500);
            sendJson(['state' => 'error_world_unit_data_unavailable']);
            return;
        }

        $unitTypeKey = (string)$unitType;
        $levelKey = (string)$reqLvl;
        if ($unitType <= 0 || $reqLvl <= 0 || !isset($cachedUnitData[$unitTypeKey])) {
            http_response_code(404);
            sendJson(['state' => 'error_world_unit_not_found']);
            return;
        }
        $unitTypeData = $cachedUnitData[$unitTypeKey];
        if (isset($unitTypeData['maxLvl']) && (int)$unitTypeData['maxLvl'] > 0
            && $reqLvl > (int)$unitTypeData['maxLvl']) {
            http_response_code(404);
            sendJson(['state' => 'error_world_unit_level_not_found']);
            return;
        }

        $armyData = $cachedUnitArmy[$unitTypeKey][$levelKey] ?? [];
        $army = [
            'army_a' => (int)($armyData['army_a'] ?? $armyData['1'] ?? 0),
            'army_b' => (int)($armyData['army_b'] ?? $armyData['2'] ?? 0),
            'army_c' => (int)($armyData['army_c'] ?? $armyData['3'] ?? 0),
            'army_d' => (int)($armyData['army_d'] ?? $armyData['4'] ?? 0),
            'army_e' => (int)($armyData['army_e'] ?? $armyData['5'] ?? 0),
            'army_f' => (int)($armyData['army_f'] ?? $armyData['6'] ?? 0),
        ];

        sendJson([
            'state' => 'ok',
            'l' => $reqLvl,
            'ut' => $unitType,
            'army' => $army,
            'equip' => []
        ]);
    }
}
