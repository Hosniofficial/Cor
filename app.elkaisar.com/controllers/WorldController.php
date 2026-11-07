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
            case '/api/ABattelRunning/getUnitBattel':
                sendJson([]);
                return true;
            case '/api/ADominant/getCityColonized':
            case '/api/ADominant/getCityColonizer':
                sendJson([]);
                return true;
        }
        return false;
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

    public static function buildNewCity($db) {
        $idCity   = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 1;
        $xCoord   = isset($_REQUEST['xCoord']) ? (int)$_REQUEST['xCoord'] : 0;
        $yCoord   = isset($_REQUEST['yCoord']) ? (int)$_REQUEST['yCoord'] : 0;
        $cityName = isset($_REQUEST['CityName']) ? trim($_REQUEST['CityName']) : 'مدينة جديدة';

        // 1. Get originating city & player
        $stmt = $db->prepare("SELECT * FROM city WHERE id_city = ?");
        $stmt->execute([$idCity]);
        $city = $stmt->fetch();
        if (!$city) {
            sendJson(['state' => 'error_0', 'msg' => 'City not found']);
            return;
        }

        $idPlayer = (int)$city['id_player'];
        $pStmt = $db->prepare("SELECT * FROM player WHERE id_player = ?");
        $pStmt->execute([$idPlayer]);
        $player = $pStmt->fetch();

        // 2. Count current cities
        $cCountStmt = $db->prepare("SELECT COUNT(*) FROM city WHERE id_player = ?");
        $cCountStmt->execute([$idPlayer]);
        $cityCount = (int)$cCountStmt->fetchColumn();

        // 3. Check promotion requirement: porm >= city_counts * 2
        $porm = (int)($player['porm'] ?? 0);
        if ($porm < $cityCount * 2) {
            sendJson(['state' => 'error_1', 'msg' => 'Required noble rank not met']);
            return;
        }

        // 4. Check resource requirement: 10^(cityCount + 3)
        $reqRes = pow(10, $cityCount + 3);
        if ($city['food'] < $reqRes || $city['wood'] < $reqRes || $city['stone'] < $reqRes || $city['metal'] < $reqRes || $city['coin'] < $reqRes) {
            sendJson(['state' => 'error_2', 'msg' => 'Not enough resources']);
            return;
        }

        // 5. Check if target coordinates are occupied
        $checkStmt = $db->prepare("SELECT id_city FROM city WHERE x = ? AND y = ?");
        $checkStmt->execute([$xCoord, $yCoord]);
        if ($checkStmt->fetch()) {
            sendJson(['state' => 'error_3', 'msg' => 'Tile is already occupied by a city']);
            return;
        }

        // 6. Deduct resources from originating city
        $deductStmt = $db->prepare("UPDATE city SET food = GREATEST(0, food - ?), wood = GREATEST(0, wood - ?), stone = GREATEST(0, stone - ?), metal = GREATEST(0, metal - ?), coin = GREATEST(0, coin - ?) WHERE id_city = ?");
        $deductStmt->execute([$reqRes, $reqRes, $reqRes, $reqRes, $reqRes, $idCity]);

        // 7. Insert new city
        $insStmt = $db->prepare("INSERT INTO city (id_player, name, x, y, lvl, food, wood, stone, metal, coin, food_cap, wood_cap, stone_cap, metal_cap, coin_cap, pop, pop_max, helper) 
                                 VALUES (?, ?, ?, ?, 1, 10000, 10000, 10000, 10000, 10000, 100000, 100000, 100000, 100000, 100000, 100, 500, 0)");
        $insStmt->execute([$idPlayer, $cityName, $xCoord, $yCoord]);
        $newCityId = (int)$db->lastInsertId();

        // 8. Insert starter buildings for the new city
        $placesStmt = $db->prepare("SELECT place FROM city_building WHERE id_city = 1");
        $placesStmt->execute();
        $places = $placesStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        
        $bIns = $db->prepare("INSERT INTO city_building (id_city, place, type, lvl) VALUES (?, ?, ?, ?)");
        foreach ($places as $place) {
            $type = 0;
            $lvl = 0;
            if ($place === 'palace') { $type = 12; $lvl = 1; }
            elseif ($place === 'farm') { $type = 16; $lvl = 1; }
            elseif ($place === 'wood') { $type = 15; $lvl = 1; }
            elseif ($place === 'stone') { $type = 18; $lvl = 1; }
            elseif ($place === 'mine') { $type = 17; $lvl = 1; }
            elseif ($place === 'wall') { $type = 13; $lvl = 1; }
            elseif ($place === 'market') { $type = 14; $lvl = 1; }
            $bIns->execute([$newCityId, $place, $type, $lvl]);
        }

        // 9. Initialize city_jop
        $db->prepare("INSERT INTO city_jop (id_city) VALUES (?) ON DUPLICATE KEY UPDATE id_city=id_city")->execute([$newCityId]);

        // 10. Fetch new city data
        $getNewStmt = $db->prepare("SELECT * FROM city WHERE id_city = ?");
        $getNewStmt->execute([$newCityId]);
        $newCity = $getNewStmt->fetch();

        // Format integer fields
        foreach (['id_city', 'id_player', 'x', 'y', 'lvl', 'food', 'wood', 'stone', 'metal', 'coin', 'pop', 'pop_max', 'food_cap', 'wood_cap', 'stone_cap', 'metal_cap', 'coin_cap'] as $f) {
            if (isset($newCity[$f])) $newCity[$f] = (int)$newCity[$f];
        }

        sendJson(['state' => 'ok', 'City' => $newCity]);
    }
}
