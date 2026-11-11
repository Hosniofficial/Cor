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
                sendJson(['state' => 'error_8_1']);
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
        $stmt = $db->query("SELECT b.*, p.name as PlayerName, c.name as CityName 
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
            $r['attackNum'] = 1;
            $r['defenceNum'] = 0;
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

        $stmt = $db->prepare("SELECT b.*, p.name AS PlayerName, c.name AS CityName
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
            $row['attackNum'] = 1;
            $row['defenceNum'] = 0;
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

        $stmt = $db->prepare("SELECT id_battel FROM world_battel
                              WHERE id_battel = ? AND state = 'marching' AND time_arrive > UNIX_TIMESTAMP()
                              LIMIT 1");
        $stmt->execute([$idBattle]);
        if (!$stmt->fetch()) {
            http_response_code(404);
            sendJson(['state' => 'error_battle_not_found']);
            return;
        }
        sendJson(['state' => 'ok', 'attackNum' => 1, 'defenceNum' => 0]);
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
        $stmt = $db->prepare("SELECT b.id_battel, b.id_hero, b.task, b.x_city, b.y_city,
                                     b.x_coord, b.y_coord, b.time_arrive AS time_end
                              FROM world_battel b
                              WHERE b.id_player = ? AND b.state = 'marching'
                                AND b.time_arrive > UNIX_TIMESTAMP()
                              ORDER BY b.time_arrive ASC");
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
        $stmt = $db->prepare("SELECT b.id_hero, b.task AS Task, b.x_coord AS xFrom,
                                     b.y_coord AS yFrom, b.x_city AS xTo, b.y_city AS yTo,
                                     b.time_back
                              FROM world_battel b
                              WHERE b.id_player = ? AND b.state IN ('won', 'lost')
                                AND b.time_back > UNIX_TIMESTAMP()
                              ORDER BY b.time_back ASC");
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

        // Check city_barray first to see if this tile is colonized/occupied
        $barStmt = $db->prepare("SELECT Type, lvl FROM city_barray WHERE x_coord = ? AND y_coord = ? LIMIT 1");
        $barStmt->execute([$x, $y]);
        $barRow = $barStmt->fetch(PDO::FETCH_ASSOC);

        if ($barRow) {
            $unitType = (int)$barRow['Type'];
            $reqLvl = (int)$barRow['lvl'];
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
        if (isset($unitTypeData['maxLvl']) && $reqLvl > (int)$unitTypeData['maxLvl']) {
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
