<?php
// CityController.php - Handles city resources, buildings, palace, jobs, and city actions

require_once __DIR__ . '/../services/ProductionService.php';
require_once __DIR__ . '/../services/TaskService.php';

class CityController {
    public static function handle($uri, $db) {
        switch ($uri) {
            case '/api/ACity/refreshCityBase':
            case '/api/ACity/getCityBase':
                self::getCityBase($db);
                return true;
            case '/api/ACityBuilding/getCityBuilding':
                self::getCityBuilding($db);
                return true;
            case '/api/ACityBuilding/constructNewBuilding':
                self::constructNewBuilding($db);
                return true;
            case '/api/ACityBuilding/upgrade':
                self::upgradeBuilding($db);
                return true;
            case '/api/ACityBuilding/downgrade':
                self::downgradeBuilding($db);
                return true;
            case '/api/ACityBuilding/explodeBuilding':
                self::explodeBuilding($db);
                return true;
            case '/api/ACityBuilding/speedUp':
                self::speedUpBuilding($db);
                return true;
            case '/api/ACityStudy/speedUpStudy':
                self::speedUpStudy($db);
                return true;
            case '/api/ACityPalace/updateProductionRate':
                self::updateProductionRate($db);
                return true;
            case '/api/ACityPalace/updateTaxs':
                self::updateTaxs($db);
                return true;
            case '/api/ACityPalace/updateName':
                self::updateName($db);
                return true;
            case '/api/ACityPalace/expandCity':
                self::expandCity($db);
                return true;
            case '/api/ACityBarrary/getCityBarray':
                self::getCityBarray($db);
                return true;
            case '/api/ACityPalace/barryAbandon':
                self::barryAbandon($db);
                return true;
            case '/api/ACityPalace/getCityGarrison':
            case '/api/ACityWounded/getCityWounded':
                sendJson([]);
                return true;
            case '/api/ACity/fireArmy':
                self::fireArmy($db);
                return true;
            case '/api/ACity/changeCityHelper':
                self::changeCityHelper($db);
                return true;
            case '/api/ACityJop/getCityJop':
                self::getCityJop($db);
                return true;
            case '/api/ACityJop/hire':
                self::hireLabor($db);
                return true;
            case '/api/ACityJop/fireLabor':
                self::fireLabor($db);
                return true;
            case '/api/ACityJop/speedUpHiring':
                self::speedUpHiring($db);
                return true;
        }
        return false;
    }

    public static function getCityBase($db) {
        $cityId = isset($_GET['idCity']) ? (int)$_GET['idCity'] : (isset($_POST['idCity']) ? (int)$_POST['idCity'] : 0);
        if ($cityId > 0) {
            $stmt = $db->prepare("SELECT * FROM `city` WHERE `id_city` = ?");
            $stmt->execute([$cityId]);
        } else {
            $stmt = $db->query("SELECT * FROM `city` ORDER BY `id_city` ASC LIMIT 1");
        }
        $city = $stmt->fetch();

        if ($city) {
            $cityId = (int)$city['id_city'];
            ProductionService::recalculateCityProduction($db, $cityId);
            $stmt = $db->prepare("SELECT * FROM `city` WHERE `id_city` = ?");
            $stmt->execute([$cityId]);
            $city = $stmt->fetch();

            $now = time();
            $elapsed = max(0, $now - (int)$city['LS']);
            if ($elapsed > 0) {
                $foodGain  = (int)round(($city['food_in']  - $city['food_out'])  * ($elapsed / 3600));
                $woodGain  = (int)round(($city['wood_in']  - $city['wood_out'])  * ($elapsed / 3600));
                $stoneGain = (int)round(($city['stone_in'] - $city['stone_out']) * ($elapsed / 3600));
                $metalGain = (int)round(($city['metal_in'] - $city['metal_out']) * ($elapsed / 3600));
                $coinGain  = (int)round(($city['coin_in']  - $city['coin_out'])  * ($elapsed / 3600));

                // Production only increments if below storage cap, but NEVER cuts down existing resources (gifts/loot/injection)
                if ((int)$city['food'] < (int)$city['food_cap']) {
                    $city['food'] = min((int)$city['food_cap'], max(0, (int)$city['food'] + $foodGain));
                }
                if ((int)$city['wood'] < (int)$city['wood_cap']) {
                    $city['wood'] = min((int)$city['wood_cap'], max(0, (int)$city['wood'] + $woodGain));
                }
                if ((int)$city['stone'] < (int)$city['stone_cap']) {
                    $city['stone'] = min((int)$city['stone_cap'], max(0, (int)$city['stone'] + $stoneGain));
                }
                if ((int)$city['metal'] < (int)$city['metal_cap']) {
                    $city['metal'] = min((int)$city['metal_cap'], max(0, (int)$city['metal'] + $metalGain));
                }
                if ((int)$city['coin'] < (int)$city['coin_cap']) {
                    $city['coin'] = min((int)$city['coin_cap'], max(0, (int)$city['coin'] + $coinGain));
                }
                $city['LS'] = $now;

                $uStmt = $db->prepare("UPDATE `city` SET `food` = ?, `wood` = ?, `stone` = ?, `metal` = ?, `coin` = ?, `LS` = ? WHERE `id_city` = ?");
                $uStmt->execute([$city['food'], $city['wood'], $city['stone'], $city['metal'], $city['coin'], $now, $city['id_city']]);
            }

            foreach ($city as $k => $v) {
                if (is_numeric($v)) $city[$k] = (int)$v;
            }
            sendJson($city);
        }
        sendJson(['state' => 'error']);
    }

    public static function getCityBuilding($db) {
        $cityId = isset($_GET['idCity']) ? (int)$_GET['idCity'] : 1;
        $stmt = $db->prepare("SELECT `place`, `type`, `lvl` FROM `city_building` WHERE `id_city` = ?");
        $stmt->execute([$cityId]);
        $rows = $stmt->fetchAll() ?: [];

        $lvl  = [];
        $type = [];
        foreach ($rows as $row) {
            $lvl[$row['place']]  = (int)$row['lvl'];
            $type[$row['place']] = (int)$row['type'];
        }
        sendJson(['lvl' => $lvl, 'type' => $type]);
    }

    public static function constructNewBuilding($db) {
        $cityId = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 1;
        $place  = isset($_REQUEST['buildingPlace']) ? trim($_REQUEST['buildingPlace']) : '';
        $type   = isset($_REQUEST['buildingType']) ? (int)$_REQUEST['buildingType'] : 0;

        if ($cityId <= 0) {
            $cStmt = $db->query("SELECT id_city FROM city ORDER BY id_city ASC LIMIT 1");
            $cityId = (int)$cStmt->fetchColumn();
        }

        if (!empty($place) && $type > 0) {
            $stmt = $db->prepare("SELECT id_player FROM city WHERE id_city = ?");
            $stmt->execute([$cityId]);
            $playerId = (int)$stmt->fetchColumn() ?: 1;

            $duration = 30; // 30 seconds for construction
            $now = time();
            $arrive = $now + $duration;

            $stmt = $db->prepare("INSERT INTO `timed_task` (`id_city`, `id_player`, `task_type`, `item_id`, `lvl_to`, `start_time`, `time_arrive`, `details`) VALUES (?, ?, 'building', ?, 1, ?, ?, ?)");
            $stmt->execute([$cityId, $playerId, $place, $now, $arrive, json_encode(['type' => $type])]);
            $taskId = (int)$db->lastInsertId();

            // Mark in city_building with type
            $stmt = $db->prepare("INSERT INTO `city_building` (`id_city`, `place`, `type`, `lvl`) VALUES (?, ?, ?, 0) ON DUPLICATE KEY UPDATE `type` = VALUES(`type`)");
            $stmt->execute([$cityId, $place, $type]);

            // Deduct starter cost
            $stmt = $db->prepare("UPDATE `city` SET `food` = GREATEST(0, `food` - 100), `wood` = GREATEST(0, `wood` - 100), `stone` = GREATEST(0, `stone` - 100) WHERE `id_city` = ?");
            $stmt->execute([$cityId]);

            sendJson([
                'state' => 'ok',
                'list'  => [
                    [
                        'id'           => $taskId,
                        'id_city'      => $cityId,
                        'type'         => $type,
                        'place'        => $place,
                        'lvl_to'       => 1,
                        'time_start'   => $now,
                        'time_end'     => $arrive,
                        'time_end_org' => $arrive
                    ]
                ]
            ]);
        }
        sendJson(['state' => 'error', 'msg' => 'Invalid parameters']);
    }

    public static function upgradeBuilding($db) {
        $cityId = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 1;
        $place  = isset($_REQUEST['buildingPlace']) ? trim($_REQUEST['buildingPlace']) : '';

        if ($cityId <= 0) {
            $cStmt = $db->query("SELECT id_city FROM city ORDER BY id_city ASC LIMIT 1");
            $cityId = (int)$cStmt->fetchColumn();
        }

        if (!empty($place)) {
            $stmt = $db->prepare("SELECT b.type, b.lvl, c.id_player FROM city_building b JOIN city c ON b.id_city = c.id_city WHERE b.id_city = ? AND b.place = ?");
            $stmt->execute([$cityId, $place]);
            $curr = $stmt->fetch();
            $type = (int)($curr['type'] ?? 0);
            $currLvl = (int)($curr['lvl'] ?? 0);
            $playerId = (int)($curr['id_player'] ?? 1);
            $nextLvl = $currLvl + 1;

            $duration = max(30, min(600, $nextLvl * 45)); // 45s per level
            $now = time();
            $arrive = $now + $duration;

            $stmt = $db->prepare("INSERT INTO `timed_task` (`id_city`, `id_player`, `task_type`, `item_id`, `lvl_to`, `start_time`, `time_arrive`, `details`) VALUES (?, ?, 'building', ?, ?, ?, ?, ?)");
            $stmt->execute([$cityId, $playerId, $place, $nextLvl, $now, $arrive, json_encode(['type' => $type])]);
            $taskId = (int)$db->lastInsertId();

            // Deduct upgrade cost
            $stmt = $db->prepare("UPDATE `city` SET `wood` = GREATEST(0, `wood` - 200), `stone` = GREATEST(0, `stone` - 200), `metal` = GREATEST(0, `metal` - 100) WHERE `id_city` = ?");
            $stmt->execute([$cityId]);

            sendJson([
                'state' => 'ok',
                'list'  => [
                    [
                        'id'           => $taskId,
                        'id_city'      => $cityId,
                        'type'         => $type,
                        'place'        => $place,
                        'lvl_to'       => $nextLvl,
                        'time_start'   => $now,
                        'time_end'     => $arrive,
                        'time_end_org' => $arrive
                    ]
                ]
            ]);
        }
        sendJson(['state' => 'error', 'msg' => 'Invalid parameters']);
    }

    public static function downgradeBuilding($db) {
        $cityId = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 1;
        $place  = isset($_REQUEST['buildingPlace']) ? trim($_REQUEST['buildingPlace']) : '';
        if (!empty($place)) {
            $stmt = $db->prepare("UPDATE `city_building` SET `lvl` = GREATEST(0, `lvl` - 1) WHERE `id_city` = ? AND `place` = ?");
            $stmt->execute([$cityId, $place]);
            sendJson(['state' => 'ok', 'list' => []]);
        }
        sendJson(['state' => 'error', 'msg' => 'Invalid parameters']);
    }

    public static function explodeBuilding($db) {
        $cityId = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 1;
        $place  = isset($_REQUEST['buildingPlace']) ? trim($_REQUEST['buildingPlace']) : '';
        if (!empty($place)) {
            $stmt = $db->prepare("UPDATE `city_building` SET `type` = 0, `lvl` = 0 WHERE `id_city` = ? AND `place` = ?");
            $stmt->execute([$cityId, $place]);
            sendJson(['state' => 'ok', 'list' => []]);
        }
        sendJson(['state' => 'error', 'msg' => 'Invalid parameters']);
    }

    public static function speedUpBuilding($db) {
        $taskId = isset($_REQUEST['idWorking']) ? (int)$_REQUEST['idWorking'] : (int)($_REQUEST['idTask'] ?? 0);
        if ($taskId > 0) {
            $stmt = $db->prepare("UPDATE `timed_task` SET `time_arrive` = UNIX_TIMESTAMP() WHERE `id_task` = ?");
            $stmt->execute([$taskId]);
            TaskService::processCompletedTasks($db);
        }
        sendJson(['state' => 'ok', 'list' => []]);
    }

    public static function speedUpStudy($db) {
        $taskId = isset($_REQUEST['idTask']) ? (int)$_REQUEST['idTask'] : 0;
        if ($taskId > 0) {
            $stmt = $db->prepare("UPDATE `timed_task` SET `time_arrive` = UNIX_TIMESTAMP() WHERE `id_task` = ?");
            $stmt->execute([$taskId]);
            TaskService::processCompletedTasks($db);
        }
        sendJson(['state' => 'ok', 'list' => []]);
    }

    public static function updateProductionRate($db) {
        $cityId    = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 1;
        $foodRate  = isset($_REQUEST['foodRate']) ? max(0, min(100, (int)$_REQUEST['foodRate'])) : 100;
        $woodRate  = isset($_REQUEST['woodRate']) ? max(0, min(100, (int)$_REQUEST['woodRate'])) : 100;
        $stoneRate = isset($_REQUEST['stoneRate']) ? max(0, min(100, (int)$_REQUEST['stoneRate'])) : 100;
        $metalRate = isset($_REQUEST['metalRate']) ? max(0, min(100, (int)$_REQUEST['metalRate'])) : 100;

        $up = $db->prepare("UPDATE `city_jop` SET `food_rate` = ?, `wood_rate` = ?, `stone_rate` = ?, `metal_rate` = ? WHERE `id_city` = ?");
        $up->execute([$foodRate, $woodRate, $stoneRate, $metalRate, $cityId]);

        ProductionService::recalculateCityProduction($db, $cityId);

        $cStmt = $db->prepare("SELECT * FROM city WHERE id_city = ?");
        $cStmt->execute([$cityId]);
        $city = $cStmt->fetch() ?: [];
        foreach ($city as $k => $v) { if (is_numeric($v)) $city[$k] = (int)$v; }

        $jStmt = $db->prepare("SELECT * FROM city_jop WHERE id_city = ?");
        $jStmt->execute([$cityId]);
        $jop = $jStmt->fetch() ?: [];
        foreach ($jop as $k => $v) { if (is_numeric($v)) $jop[$k] = (int)$v; }

        sendJson([
            'state'   => 'ok',
            'City'    => $city,
            'CityJop' => $jop
        ]);
    }

    public static function updateTaxs($db) {
        $cityId = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 1;
        $newTax = isset($_REQUEST['newTaxRate']) ? max(0, min(100, (int)$_REQUEST['newTaxRate'])) : 10;

        $stmt = $db->prepare("UPDATE `city` SET `taxs` = ? WHERE `id_city` = ?");
        $stmt->execute([$newTax, $cityId]);

        ProductionService::recalculateCityProduction($db, $cityId);

        $cStmt = $db->prepare("SELECT * FROM city WHERE id_city = ?");
        $cStmt->execute([$cityId]);
        $city = $cStmt->fetch() ?: [];
        foreach ($city as $k => $v) { if (is_numeric($v)) $city[$k] = (int)$v; }

        sendJson([
            'state' => 'ok',
            'City'  => $city
        ]);
    }

    public static function updateName($db) {
        $cityId = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 1;
        $newName = isset($_REQUEST['NewName']) ? trim($_REQUEST['NewName']) : 'المدينة 1';

        $stmt = $db->prepare("UPDATE `city` SET `name` = ? WHERE `id_city` = ?");
        $stmt->execute([$newName, $cityId]);

        $cStmt = $db->prepare("SELECT * FROM city WHERE id_city = ?");
        $cStmt->execute([$cityId]);
        $city = $cStmt->fetch() ?: [];
        foreach ($city as $k => $v) { if (is_numeric($v)) $city[$k] = (int)$v; }

        sendJson([
            'state' => 'ok',
            'City'  => $city
        ]);
    }

    public static function expandCity($db) {
        $cityId = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 1;
        $stmt = $db->prepare("UPDATE `city` SET `lvl` = `lvl` + 1 WHERE `id_city` = ?");
        $stmt->execute([$cityId]);

        $cStmt = $db->prepare("SELECT * FROM city WHERE id_city = ?");
        $cStmt->execute([$cityId]);
        $city = $cStmt->fetch() ?: [];
        foreach ($city as $k => $v) { if (is_numeric($v)) $city[$k] = (int)$v; }

        sendJson([
            'state' => 'ok',
            'City'  => $city
        ]);
    }

    public static function fireArmy($db) {
        $cityId    = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 1;
        $armyType  = isset($_REQUEST['armyType']) ? trim($_REQUEST['armyType']) : '';
        $amount    = isset($_REQUEST['amount']) ? (int)$_REQUEST['amount'] : 0;

        $validArmies = ['army_a', 'army_b', 'army_c', 'army_d', 'army_e', 'army_f'];
        if (in_array($armyType, $validArmies) && $amount > 0) {
            $stmt = $db->prepare("UPDATE `city` SET `{$armyType}` = GREATEST(0, `{$armyType}` - ?), `pop` = LEAST(`pop_max`, `pop` + ?) WHERE `id_city` = ?");
            $stmt->execute([$amount, $amount, $cityId]);
        }

        $cStmt = $db->prepare("SELECT * FROM city WHERE id_city = ?");
        $cStmt->execute([$cityId]);
        $city = $cStmt->fetch() ?: [];
        foreach ($city as $k => $v) { if (is_numeric($v)) $city[$k] = (int)$v; }

        sendJson([
            'state' => 'ok',
            'City'  => $city
        ]);
    }

    public static function changeCityHelper($db) {
        $cityId = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 1;
        $helper = isset($_REQUEST['newHelper']) ? (int)$_REQUEST['newHelper'] : 0;

        $stmt = $db->prepare("UPDATE `city` SET `helper` = ? WHERE `id_city` = ?");
        $stmt->execute([$helper, $cityId]);

        $cStmt = $db->prepare("SELECT * FROM city WHERE id_city = ?");
        $cStmt->execute([$cityId]);
        $city = $cStmt->fetch() ?: [];
        foreach ($city as $k => $v) { if (is_numeric($v)) $city[$k] = (int)$v; }

        sendJson([
            'state' => 'ok',
            'City'  => $city
        ]);
    }

    public static function getCityJop($db) {
        $cityId = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 1;
        if ($cityId <= 0) {
            $cStmt = $db->query("SELECT id_city FROM city ORDER BY id_city ASC LIMIT 1");
            $cityId = (int)$cStmt->fetchColumn() ?: 1;
        }

        ProductionService::recalculateCityProduction($db, $cityId);

        $stmt = $db->prepare("SELECT * FROM city_jop WHERE id_city = ?");
        $stmt->execute([$cityId]);
        $jop = $stmt->fetch();

        if ($jop) {
            foreach ($jop as $k => $v) { if (is_numeric($v)) $jop[$k] = (int)$v; }
            sendJson($jop);
        } else {
            sendJson([
                'id_city'    => $cityId,
                'id_player'  => 1,
                'food'       => 10,
                'wood'       => 10,
                'stone'      => 10,
                'metal'      => 10,
                'food_rate'  => 100,
                'wood_rate'  => 100,
                'stone_rate' => 100,
                'metal_rate' => 100
            ]);
        }
    }

    public static function hireLabor($db) {
        $cityId    = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 1;
        $place     = isset($_REQUEST['buildingPlace']) ? trim($_REQUEST['buildingPlace']) : 'farm';
        $amount    = isset($_REQUEST['amountToHire']) ? (int)$_REQUEST['amountToHire'] : 0;

        $stmt = $db->prepare("SELECT id_player FROM city WHERE id_city = ?");
        $stmt->execute([$cityId]);
        $playerId = (int)$stmt->fetchColumn() ?: 1;

        $duration = max(15, min(600, (int)ceil($amount * 2)));
        $now = time();
        $arrive = $now + $duration;

        $stmt = $db->prepare("UPDATE `city` SET `food` = GREATEST(0, `food` - ?), `pop` = GREATEST(0, `pop` - ?) WHERE `id_city` = ?");
        $stmt->execute([$amount * 5, $amount, $cityId]);

        $stmt = $db->prepare("INSERT INTO `timed_task` (`id_city`, `id_player`, `task_type`, `item_id`, `lvl_to`, `start_time`, `time_arrive`, `details`) VALUES (?, ?, 'jop', ?, ?, ?, ?, ?)");
        $stmt->execute([$cityId, $playerId, $place, $amount, $now, $arrive, json_encode(['jop_place' => $place])]);
        $taskId = (int)$db->lastInsertId();

        $cStmt = $db->prepare("SELECT * FROM city WHERE id_city = ?");
        $cStmt->execute([$cityId]);
        $updatedCity = $cStmt->fetch();
        foreach ($updatedCity as $k => $v) { if (is_numeric($v)) $updatedCity[$k] = (int)$v; }

        $taskItem = [
            'id'           => $taskId,
            'id_city'      => $cityId,
            'jop_place'    => $place,
            'lvl_to'       => $amount,
            'time_start'   => $now,
            'time_end'     => $arrive,
            'time_end_org' => $arrive
        ];

        sendJson([
            'state'       => 'ok',
            'cityRes'     => $updatedCity,
            'list'        => [$taskItem],
            'JopTaskList' => [$taskItem]
        ]);
    }

    public static function fireLabor($db) {
        $cityId = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 1;
        $amount = isset($_REQUEST['amountToFire']) ? (int)$_REQUEST['amountToFire'] : 0;
        $place  = isset($_REQUEST['buildingPlace']) ? trim($_REQUEST['buildingPlace']) : 'farm';

        $validJops = ['farm' => 'food', 'wood' => 'wood', 'stone' => 'stone', 'mine' => 'metal'];
        $col = $validJops[$place] ?? $place;

        if (in_array($col, ['food', 'wood', 'stone', 'metal']) && $amount > 0) {
            $up = $db->prepare("UPDATE `city_jop` SET `{$col}` = GREATEST(0, `{$col}` - ?) WHERE `id_city` = ?");
            $up->execute([$amount, $cityId]);
            $db->prepare("UPDATE `city` SET `pop` = LEAST(`pop_max`, `pop` + ?) WHERE `id_city` = ?")->execute([$amount, $cityId]);
            ProductionService::recalculateCityProduction($db, $cityId);
        }

        $jStmt = $db->prepare("SELECT * FROM city_jop WHERE id_city = ?");
        $jStmt->execute([$cityId]);
        $cityJop = $jStmt->fetch() ?: [];
        foreach ($cityJop as $k => $v) { if (is_numeric($v)) $cityJop[$k] = (int)$v; }

        $cStmt = $db->prepare("SELECT * FROM city WHERE id_city = ?");
        $cStmt->execute([$cityId]);
        $cityRes = $cStmt->fetch() ?: [];
        foreach ($cityRes as $k => $v) { if (is_numeric($v)) $cityRes[$k] = (int)$v; }

        sendJson([
            'state'   => 'ok',
            'cityJop' => $cityJop,
            'cityRes' => $cityRes
        ]);
    }

    public static function speedUpHiring($db) {
        $taskId = isset($_REQUEST['idTask']) ? (int)$_REQUEST['idTask'] : 0;
        if ($taskId > 0) {
            $stmt = $db->prepare("UPDATE `timed_task` SET `time_arrive` = UNIX_TIMESTAMP() WHERE `id_task` = ?");
            $stmt->execute([$taskId]);
            TaskService::processCompletedTasks($db);
        }
        sendJson(['state' => 'ok', 'list' => [], 'JopTaskList' => []]);
    }

    public static function getCityBarray($db) {
        $cityId = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 1;
        $stmt = $db->prepare("SELECT `x_coord`, `y_coord`, `Type`, `lvl` FROM `city_barray` WHERE `id_city` = ?");
        $stmt->execute([$cityId]);
        $rows = $stmt->fetchAll() ?: [];
        foreach ($rows as &$r) {
            $r['x_coord'] = (int)$r['x_coord'];
            $r['y_coord'] = (int)$r['y_coord'];
            $r['Type']    = (int)$r['Type'];
            $r['lvl']     = (int)$r['lvl'];
        }
        sendJson($rows);
    }

    public static function barryAbandon($db) {
        $x = isset($_REQUEST['xCoord']) ? (int)$_REQUEST['xCoord'] : 0;
        $y = isset($_REQUEST['yCoord']) ? (int)$_REQUEST['yCoord'] : 0;
        $stmt = $db->prepare("SELECT id_city FROM city_barray WHERE x_coord = ? AND y_coord = ?");
        $stmt->execute([$x, $y]);
        $cityId = (int)$stmt->fetchColumn();

        $del = $db->prepare("DELETE FROM city_barray WHERE x_coord = ? AND y_coord = ?");
        $del->execute([$x, $y]);

        if ($cityId > 0) {
            ProductionService::recalculateCityProduction($db, $cityId);
        }
        sendJson(['state' => 'ok']);
    }
}
