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
            case '/api/ABattelRunning/getUnitBattel':
                sendJson([]);
                return true;
            case '/api/ADominant/getCityColonized':
            case '/api/ADominant/getCityColonizer':
                sendJson([]);
                return true;
            case '/api/ABattelRunning/getGarrisonHeros':
            case '/api/ABattelRunning/getLeavingHero':
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
            case '/api/AWorld/refreshWorldUnitLvl':
            case '/api/AWorld/getWorldUnitRank':
            case '/api/AWorld/getGuildAttackQue':
            case '/api/ABattel/joinBattel':
            case '/api/ABattel/applyForRoleInAttQue':
            case '/api/ABattelRuning/refreshBattelData':
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
                            WHERE b.time_arrive > UNIX_TIMESTAMP() 
                            ORDER BY b.id_battel DESC");
        $rows = $stmt->fetchAll() ?: [];
        foreach ($rows as &$r) {
            foreach (['id_battel', 'id_player', 'id_city', 'id_hero', 'task', 'x_city', 'y_city', 'x_coord', 'y_coord', 'time_start', 'time_arrive'] as $col) {
                if (isset($r[$col])) $r[$col] = (int)$r[$col];
            }
        }
        sendJson($rows);
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

            // 7. Insert new city record
            $insStmt = $db->prepare("INSERT INTO city (id_player, name, x, y, lvl, food, wood, stone, metal, coin, food_cap, wood_cap, stone_cap, metal_cap, coin_cap, pop, pop_max, pop_cap, loy, dis_loy, taxs, helper, pop_LSS)
                                     VALUES (?, ?, ?, ?, 1, 10000, 10000, 10000, 10000, 10000, 100000, 100000, 100000, 100000, 100000, 100, 100, 100, 100, 0, 10, 0, ?)");
            $insStmt->execute([$idPlayer, $cityName, $xCoord, $yCoord, time()]);
            $newCityId = (int)$db->lastInsertId();

            // 8. Insert all standard 65 city building slots with starter buildings
            $standardPlaces = [
                'above_palace_1', 'above_palace_2', 'above_palace_3', 'above_palace_4', 'above_palace_5', 'above_palace_6',
                'around_wood_1', 'around_wood_2', 'around_wood_3',
                'farm', 'wood', 'stone', 'mine', 'palace', 'wall', 'market', 'lighthouse', 'seaport',
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
                elseif ($place === 'market') { $type = 14; $lvl = 1; }
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

    public static function getHeroBack($db) {
        $player = requireAuthenticatedPlayer($db);
        $idHero = isset($_REQUEST['idHero']) ? (int)$_REQUEST['idHero'] : 0;
        requirePlayerOwnedHero($db, $player, $idHero);
        $db->prepare("UPDATE city_hero SET in_city = 1 WHERE id_hero = ? AND id_player = ?")->execute([$idHero, (int)$player['id_player']]);
        $db->prepare("UPDATE world_battel SET state = 'aborted' WHERE id_hero = ? AND id_player = ? AND state = 'marching'")->execute([$idHero, (int)$player['id_player']]);
        sendJson(['state' => 'ok']);
    }
}
