<?php
// CityController.php - Handles city resources, buildings, palace, jobs, and city actions

require_once __DIR__ . '/../services/ProductionService.php';
require_once __DIR__ . '/../services/TaskService.php';

class CityController {
    public static function handle($uri, $db) {
        $ownedCity = null;
        if (preg_match('~^/api/(?:ACity|ACityBuilding|ACityStudy|ACityPalace|ACityBarrary|ACityWounded|ACityStorage|ACityJop)/~', $uri)) {
            $player = requireAuthenticatedPlayer($db);
            $ownedCity = requirePlayerOwnedCity($db, $player);
        }

        if (in_array($uri, [
            '/api/ACityBuilding/speedUp',
            '/api/ACityStudy/speedUpStudy',
            '/api/ACityJop/speedUpHiring',
            '/api/ACityBuilding/cancelUpgradeing',
        ], true)) {
            $taskId = $_REQUEST['idTask'] ?? $_REQUEST['idWorking'] ?? 0;
            $task = requirePlayerOwnedTask($db, $player, $taskId);
            if ((int)$task['id_city'] !== (int)$ownedCity['id_city']) {
                http_response_code(403);
                sendJson(['state' => 'error_auth']);
            }
        }

        switch ($uri) {
            case '/api/ACity/refreshCityBase':
            case '/api/ACity/getCityBase':
            case '/api/ACity/getCityData':
                self::getCityBase($db);
                return true;
            case '/api/ACityBuilding/getCityBuilding':
                self::getCityBuilding($db);
                return true;
            case '/api/ACityBuilding/constructNewBuilding':
                self::constructNewBuilding($db);
                return true;
            case '/api/ACityBuilding/upgrade':
            case '/api/ACityBuilding/upgradeBuilding':
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
                self::getCityGarrison($db);
                return true;
            case '/api/ACityPalace/addCityGarrison':
            case '/api/ACityPalace/removeHeroFromGarrison':
            case '/api/ACityPalace/reordCityGarrison':
                sendJson(['state' => 'ok']);
                return true;
            case '/api/ACity/resetCityHelper':
                sendJson(['state' => 'ok']);
                return true;
            case '/api/ACityWounded/getCityWounded':
                self::getCityWounded($db);
                return true;
            case '/api/ACity/cureCityWounded':
                self::cureCityWounded($db);
                return true;
            case '/api/ACity/fireCityWounded':
                self::fireCityWounded($db);
                return true;
            case '/api/ACityBuilding/cancelUpgradeing':
                self::cancelUpgradeing($db);
                return true;
            case '/api/ACityStorage/getCityStorage':
                self::getCityStorage($db);
                return true;
            case '/api/ACityStorage/updatePercentage':
                self::updatePercentage($db);
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

                // Handle Resource changes:
                // Negative rates (consumption exceeding production, e.g. army eating food) ALWAYS drain resources down to 0.
                // Positive production increases up to the storage cap (preserving any gifts/loot already above cap).
                if ($foodGain < 0) {
                    $city['food'] = max(0, (int)$city['food'] + $foodGain);
                } elseif ((int)$city['food'] < (int)$city['food_cap']) {
                    $city['food'] = min((int)$city['food_cap'], (int)$city['food'] + $foodGain);
                }

                if ($woodGain < 0) {
                    $city['wood'] = max(0, (int)$city['wood'] + $woodGain);
                } elseif ((int)$city['wood'] < (int)$city['wood_cap']) {
                    $city['wood'] = min((int)$city['wood_cap'], (int)$city['wood'] + $woodGain);
                }

                if ($stoneGain < 0) {
                    $city['stone'] = max(0, (int)$city['stone'] + $stoneGain);
                } elseif ((int)$city['stone'] < (int)$city['stone_cap']) {
                    $city['stone'] = min((int)$city['stone_cap'], (int)$city['stone'] + $stoneGain);
                }

                if ($metalGain < 0) {
                    $city['metal'] = max(0, (int)$city['metal'] + $metalGain);
                } elseif ((int)$city['metal'] < (int)$city['metal_cap']) {
                    $city['metal'] = min((int)$city['metal_cap'], (int)$city['metal'] + $metalGain);
                }

                if ($coinGain < 0) {
                    $city['coin'] = max(0, (int)$city['coin'] + $coinGain);
                } elseif ((int)$city['coin'] < (int)$city['coin_cap']) {
                    $city['coin'] = min((int)$city['coin_cap'], (int)$city['coin'] + $coinGain);
                }
                $city['LS'] = $now;

                $uStmt = $db->prepare("UPDATE `city` SET `food` = ?, `wood` = ?, `stone` = ?, `metal` = ?, `coin` = ?, `LS` = ? WHERE `id_city` = ?");
                $uStmt->execute([$city['food'], $city['wood'], $city['stone'], $city['metal'], $city['coin'], $now, $city['id_city']]);

                // Check for food starvation / hero salary shortage and trigger desertion alerts
                ProductionService::processStarvationAndDesertion($db, $city);
            }

            foreach ($city as $k => $v) {
                if (is_numeric($v)) $city[$k] = (int)$v;
            }
            sendJson($city);
            return;
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

    private static $buildingReqs = null;

    public static function getBuildingRequirements($type, $lvl) {
        if (self::$buildingReqs === null) {
            $candidates = [
                __DIR__ . '/../js-3.4.113/json/buildingReqData.json',
                dirname(__DIR__) . '/js-3.4.113/json/buildingReqData.json',
                dirname(__DIR__, 2) . '/web.elkaisar.com/js-3.4.113/json/buildingReqData.json'
            ];
            self::$buildingReqs = [];
            foreach ($candidates as $cand) {
                if (file_exists($cand)) {
                    self::$buildingReqs = json_decode(file_get_contents($cand), true) ?: [];
                    break;
                }
            }
        }

        foreach (self::$buildingReqs as $b) {
            if (isset($b['BuildingType']) && (int)$b['BuildingType'] === (int)$type) {
                if (isset($b['lvlRequirment'][$lvl - 1])) {
                    return $b['lvlRequirment'][$lvl - 1];
                }
            }
        }
        return null;
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
            $cStmt = $db->prepare("SELECT * FROM city WHERE id_city = ?");
            $cStmt->execute([$cityId]);
            $city = $cStmt->fetch(PDO::FETCH_ASSOC);
            $playerId = (int)($city['id_player'] ?? 1);

            $req = self::getBuildingRequirements($type, 1);
            $costFood  = (int)($req['food']  ?? 61);
            $costWood  = (int)($req['wood']  ?? 276);
            $costStone = (int)($req['stone'] ?? 40);
            $costMetal = (int)($req['metal'] ?? 59);
            $costCoin  = (int)($req['coin']  ?? 0);
            $duration  = max(15, (int)($req['time'] ?? 30));

            // Validate Prerequisites
            if (!empty($req['condetion'])) {
                foreach ($req['condetion'] as $cond) {
                    if (($cond['type'] ?? '') === 'building') {
                        $reqBType = (int)$cond['BuildingType'];
                        $reqBLvl  = (int)$cond['lvl'];
                        $bStmt = $db->prepare("SELECT MAX(lvl) FROM city_building WHERE id_city = ? AND type = ?");
                        $bStmt->execute([$cityId, $reqBType]);
                        $hasLvl = (int)$bStmt->fetchColumn();
                        if ($hasLvl < $reqBLvl) {
                            sendJson(['state' => 'error_cond', 'msg' => "يتطلب مبنى نوع {$reqBType} بمستوى {$reqBLvl}"]);
                            return;
                        }
                    }
                }
            }

            // Validate Resources
            if ((int)$city['food'] < $costFood || (int)$city['wood'] < $costWood || (int)$city['stone'] < $costStone || (int)$city['metal'] < $costMetal || (int)$city['coin'] < $costCoin) {
                sendJson(['state' => 'error_res', 'msg' => 'الموارد المتوفرة بالمدينة غير كافية للبناء']);
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

            $stmt = $db->prepare("INSERT INTO `timed_task` (`id_city`, `id_player`, `task_type`, `item_id`, `lvl_to`, `start_time`, `time_arrive`, `details`) VALUES (?, ?, 'building', ?, 1, ?, ?, ?)");
            $stmt->execute([$cityId, $playerId, $place, $now, $arrive, json_encode(['type' => $type])]);
            $taskId = (int)$db->lastInsertId();

            // Mark in city_building with type
            $stmt = $db->prepare("INSERT INTO `city_building` (`id_city`, `place`, `type`, `lvl`) VALUES (?, ?, ?, 0) ON DUPLICATE KEY UPDATE `type` = VALUES(`type`)");
            $stmt->execute([$cityId, $place, $type]);

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

            if ($nextLvl > 30) {
                sendJson(['state' => 'error_max_lvl', 'msg' => 'المبنى وصل للحد الأقصى للتطوير']);
                return;
            }

            $req = self::getBuildingRequirements($type, $nextLvl);
            $costFood  = (int)($req['food']  ?? ($nextLvl * 100));
            $costWood  = (int)($req['wood']  ?? ($nextLvl * 250));
            $costStone = (int)($req['stone'] ?? ($nextLvl * 150));
            $costMetal = (int)($req['metal'] ?? ($nextLvl * 100));
            $costCoin  = (int)($req['coin']  ?? 0);
            $duration  = max(15, (int)($req['time'] ?? ($nextLvl * 45)));

            // Validate Prerequisites
            if (!empty($req['condetion'])) {
                foreach ($req['condetion'] as $cond) {
                    if (($cond['type'] ?? '') === 'building') {
                        $reqBType = (int)$cond['BuildingType'];
                        $reqBLvl  = (int)$cond['lvl'];
                        $bStmt = $db->prepare("SELECT MAX(lvl) FROM city_building WHERE id_city = ? AND type = ?");
                        $bStmt->execute([$cityId, $reqBType]);
                        $hasLvl = (int)$bStmt->fetchColumn();
                        if ($hasLvl < $reqBLvl) {
                            sendJson(['state' => 'error_cond', 'msg' => "يتطلب مبنى نوع {$reqBType} بمستوى {$reqBLvl}"]);
                            return;
                        }
                    }
                }
            }

            // Level 10+ requires Architectural Drawings (archit_a)
            if ($nextLvl >= 10) {
                $hasArchit = (int)$db->query("SELECT amount FROM player_item WHERE id_player = $playerId AND id_item = 'archit_a'")->fetchColumn();
                if ($hasArchit <= 0) {
                    sendJson(['state' => 'error_item', 'msg' => 'يتطلب تطوير المبنى لمستوى 10 أو أعلى وجود مخطوطات معمارية (archit_a)']);
                    return;
                }
                ItemController::deductPlayerItem($db, $playerId, 'archit_a', 1);
            }

            // Validate Resources
            $cStmt = $db->prepare("SELECT * FROM city WHERE id_city = ?");
            $cStmt->execute([$cityId]);
            $city = $cStmt->fetch(PDO::FETCH_ASSOC);

            if ((int)$city['food'] < $costFood || (int)$city['wood'] < $costWood || (int)$city['stone'] < $costStone || (int)$city['metal'] < $costMetal || (int)$city['coin'] < $costCoin) {
                sendJson(['state' => 'error_res', 'msg' => 'الموارد المتوفرة بالمدينة غير كافية للترقية']);
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

            $stmt = $db->prepare("INSERT INTO `timed_task` (`id_city`, `id_player`, `task_type`, `item_id`, `lvl_to`, `start_time`, `time_arrive`, `details`) VALUES (?, ?, 'building', ?, ?, ?, ?, ?)");
            $stmt->execute([$cityId, $playerId, $place, $nextLvl, $now, $arrive, json_encode(['type' => $type])]);
            $taskId = (int)$db->lastInsertId();

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
            $cStmt = $db->prepare("SELECT id_player FROM city WHERE id_city = ?");
            $cStmt->execute([$cityId]);
            $playerId = (int)$cStmt->fetchColumn() ?: 1;
            $hasKeg = (int)$db->query("SELECT amount FROM player_item WHERE id_player = $playerId AND id_item = 'powder_keg'")->fetchColumn();
            if ($hasKeg > 0) {
                ItemController::deductPlayerItem($db, $playerId, 'powder_keg', 1);
            }

            $stmt = $db->prepare("UPDATE `city_building` SET `type` = 0, `lvl` = 0 WHERE `id_city` = ? AND `place` = ?");
            $stmt->execute([$cityId, $place]);
            sendJson(['state' => 'ok', 'list' => []]);
            return;
        }
        sendJson(['state' => 'error', 'msg' => 'Invalid parameters']);
    }

    private static function getBuildingTaskList($db, $cityId) {
        $stmt = $db->prepare(
            "SELECT * FROM `timed_task` WHERE `task_type` = 'building' AND `id_city` = ? ORDER BY `id_task`"
        );
        $stmt->execute([$cityId]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $list = [];
        foreach ($tasks as $task) {
            $details = !empty($task['details']) ? json_decode($task['details'], true) : [];
            $list[] = [
                'id'           => (int)$task['id_task'],
                'id_city'      => (int)$task['id_city'],
                'type'         => isset($details['type']) ? (int)$details['type'] : 1,
                'place'        => $task['item_id'],
                'lvl_to'       => (int)$task['lvl_to'],
                'time_start'   => (int)$task['start_time'],
                'time_end'     => (int)$task['time_arrive'],
                'time_end_org' => (int)$task['time_arrive']
            ];
        }
        return $list;
    }

    public static function speedUpBuilding($db) {
        $taskId    = isset($_REQUEST['idWorking']) ? (int)$_REQUEST['idWorking'] : (int)($_REQUEST['idTask'] ?? 0);
        $itemToUse = trim($_REQUEST['itemToUse'] ?? '');
        $cityId    = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 0;

        if ($taskId > 0) {
            $tStmt = $db->prepare("SELECT * FROM `timed_task` WHERE `id_task` = ?");
            $tStmt->execute([$taskId]);
            $task = $tStmt->fetch(PDO::FETCH_ASSOC);

            if ($task) {
                $cityId = (int)$task['id_city'];
                $playerId = (int)$task['id_player'];
                $now = time();
                $rem = max(0, (int)$task['time_arrive'] - $now);

                $reduceSec = 0;
                $pct = 0.0;

                // Speedup items definition (Architectural Blueprints & General Time items)
                $speedItems = [
                    'archit_a'   => ['sec' => 900,   'pct' => 0.0],  // 15 minutes
                    'archit_b'   => ['sec' => 3600,  'pct' => 0.0],  // 1 hour
                    'archit_c'   => ['sec' => 10800, 'pct' => 0.0],  // 3 hours
                    'archit_d'   => ['sec' => 0,     'pct' => 0.30], // 30% reduction
                    'speed_15m'  => ['sec' => 900,   'pct' => 0.0],  // 15 minutes
                    'speed_1h'   => ['sec' => 3600,  'pct' => 0.0],  // 1 hour
                    'speed_2_5h' => ['sec' => 9000,  'pct' => 0.0],  // 2.5 hours
                    'speed_8h'   => ['sec' => 28800, 'pct' => 0.0],  // 8 hours
                ];

                if (!empty($itemToUse) && isset($speedItems[$itemToUse])) {
                    // Check and deduct player item
                    $iStmt = $db->prepare("SELECT `amount` FROM `player_item` WHERE `id_player` = ? AND `id_item` = ?");
                    $iStmt->execute([$playerId, $itemToUse]);
                    $hasAmt = (int)$iStmt->fetchColumn();

                    if ($hasAmt > 0) {
                        $db->prepare("UPDATE `player_item` SET `amount` = GREATEST(0, `amount` - 1) WHERE `id_player` = ? AND `id_item` = ?")
                           ->execute([$playerId, $itemToUse]);

                        $def = $speedItems[$itemToUse];
                        if ($def['pct'] > 0) {
                            $reduceSec = (int)ceil($rem * $def['pct']);
                        } else {
                            $reduceSec = $def['sec'];
                        }
                    } else {
                        sendJson(['state' => 'error_no_item', 'msg' => 'لا تمتلك المادة المحددة للتسريع']);
                        return;
                    }
                } else {
                    // Default / instant finish if no item specified
                    $reduceSec = $rem;
                }

                $newArrive = max($now, (int)$task['time_arrive'] - $reduceSec);
                $db->prepare("UPDATE `timed_task` SET `time_arrive` = ? WHERE `id_task` = ?")->execute([$newArrive, $taskId]);

                if ($newArrive <= $now) {
                    TaskService::processCompletedTasks($db);
                }
            }
        }
        sendJson([
            'state' => 'ok',
            'list' => $cityId > 0 ? self::getBuildingTaskList($db, $cityId) : []
        ]);
    }

    public static function speedUpStudy($db) {
        $taskId    = isset($_REQUEST['idTask']) ? (int)$_REQUEST['idTask'] : 0;
        $itemToUse = trim($_REQUEST['itemToUse'] ?? '');

        if ($taskId > 0) {
            $tStmt = $db->prepare("SELECT * FROM `timed_task` WHERE `id_task` = ?");
            $tStmt->execute([$taskId]);
            $task = $tStmt->fetch(PDO::FETCH_ASSOC);

            if ($task) {
                $playerId = (int)$task['id_player'];
                $now = time();
                $rem = max(0, (int)$task['time_arrive'] - $now);

                $reduceSec = 0;

                // Archimedes Blueprints & Scientific Study Speedup Items
                $speedItems = [
                    'archim_a'   => ['sec' => 900,   'pct' => 0.0],  // 15 minutes
                    'archim_b'   => ['sec' => 3600,  'pct' => 0.0],  // 1 hour
                    'archim_c'   => ['sec' => 10800, 'pct' => 0.0],  // 3 hours
                    'archim_d'   => ['sec' => 0,     'pct' => 0.30], // 30% reduction
                    'speed_15m'  => ['sec' => 900,   'pct' => 0.0],  // 15 minutes
                    'speed_1h'   => ['sec' => 3600,  'pct' => 0.0],  // 1 hour
                    'speed_2_5h' => ['sec' => 9000,  'pct' => 0.0],  // 2.5 hours
                    'speed_8h'   => ['sec' => 28800, 'pct' => 0.0],  // 8 hours
                ];

                if (!empty($itemToUse) && isset($speedItems[$itemToUse])) {
                    $iStmt = $db->prepare("SELECT `amount` FROM `player_item` WHERE `id_player` = ? AND `id_item` = ?");
                    $iStmt->execute([$playerId, $itemToUse]);
                    $hasAmt = (int)$iStmt->fetchColumn();

                    if ($hasAmt > 0) {
                        $db->prepare("UPDATE `player_item` SET `amount` = GREATEST(0, `amount` - 1) WHERE `id_player` = ? AND `id_item` = ?")
                           ->execute([$playerId, $itemToUse]);

                        $def = $speedItems[$itemToUse];
                        if ($def['pct'] > 0) {
                            $reduceSec = (int)ceil($rem * $def['pct']);
                        } else {
                            $reduceSec = $def['sec'];
                        }
                    } else {
                        sendJson(['state' => 'error_no_item', 'msg' => 'لا تمتلك المادة المحددة للتسريع']);
                        return;
                    }
                } else {
                    $reduceSec = $rem;
                }

                $newArrive = max($now, (int)$task['time_arrive'] - $reduceSec);
                $db->prepare("UPDATE `timed_task` SET `time_arrive` = ? WHERE `id_task` = ?")->execute([$newArrive, $taskId]);

                if ($newArrive <= $now) {
                    TaskService::processCompletedTasks($db);
                }
            }
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
        $newName = normalizeGameDisplayName($_REQUEST['NewName'] ?? 'المدينة 1');
        if ($newName === null) {
            sendJson(['state' => 'error_name', 'msg' => 'اسم المدينة غير صالح']);
        }

        $stmt = $db->prepare("UPDATE `city` SET `name` = ? WHERE `id_city` = ? AND `id_player` = ?");
        $stmt->execute([$newName, $cityId, (int)requireAuthenticatedPlayer($db)['id_player']]);

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
        $player = requireAuthenticatedPlayer($db);
        $playerId = (int)$player['id_player'];
        $city = requirePlayerOwnedCity($db, $player);
        $cityId = (int)$city['id_city'];

        $currentLvl = (int)($city['lvl'] ?? 0);
        if ($currentLvl > 2) {
            sendJson(['state' => 'error_max_lvl', 'msg' => 'المدينة في أقصى مستوى توسعة']);
        }

        // 1. Required Palace Level: (lvl + 1) * 4
        $reqPalaceLvl = ($currentLvl + 1) * 4;
        $pStmt = $db->prepare("SELECT COALESCE(MAX(lvl), 0) FROM city_building WHERE id_city = ? AND (place = 'palace' OR type = 12)");
        $pStmt->execute([$cityId]);
        $palaceLvl = (int)$pStmt->fetchColumn();
        if ($palaceLvl < $reqPalaceLvl) {
            sendJson(['state' => 'error_palace_lvl', 'msg' => 'مستوى القصر غير كافٍ لتوسيع المدينة']);
        }

        // 2. Required Expansion Plans: 2^lvl
        $reqPlans = (int)pow(2, $currentLvl);
        $itemStmt = $db->prepare("SELECT amount FROM player_item WHERE id_player = ? AND id_item = 'expan_plan'");
        $itemStmt->execute([$playerId]);
        $hasPlans = (int)$itemStmt->fetchColumn();
        if ($hasPlans < $reqPlans) {
            sendJson(['state' => 'error_item_lack', 'msg' => 'لا تمتلك مخططات توسعة كافية']);
        }

        // 3. Required Resources: (lvl + 1) * 3000
        $reqRes = ($currentLvl + 1) * 3000;
        if (
            (float)$city['food'] < $reqRes ||
            (float)$city['wood'] < $reqRes ||
            (float)$city['stone'] < $reqRes ||
            (float)$city['metal'] < $reqRes ||
            (float)$city['coin'] < $reqRes
        ) {
            sendJson(['state' => 'error_resource_lack', 'msg' => 'الموارد غير كافية لتوسيع المدينة']);
        }

        // 4. Deduct plans
        $db->prepare("UPDATE player_item SET amount = amount - ? WHERE id_player = ? AND id_item = 'expan_plan'")->execute([$reqPlans, $playerId]);
        $db->prepare("DELETE FROM player_item WHERE id_player = ? AND id_item = 'expan_plan' AND amount <= 0")->execute([$playerId]);

        // 5. Deduct resources and increment expansion level
        $stmt = $db->prepare("UPDATE city SET lvl = lvl + 1, food = GREATEST(0, food - ?), wood = GREATEST(0, wood - ?), stone = GREATEST(0, stone - ?), metal = GREATEST(0, metal - ?), coin = GREATEST(0, coin - ?) WHERE id_city = ?");
        $stmt->execute([$reqRes, $reqRes, $reqRes, $reqRes, $reqRes, $cityId]);

        // 6. Recalculate production & fetch updated city
        ProductionService::recalculateCityProduction($db, $cityId);
        $cStmt = $db->prepare("SELECT * FROM city WHERE id_city = ?");
        $cStmt->execute([$cityId]);
        $updatedCity = $cStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        foreach ($updatedCity as $k => $v) {
            if (is_numeric($v)) {
                $updatedCity[$k] = (int)$v;
            }
        }

        sendJson([
            'state' => 'ok',
            'City'  => $updatedCity
        ]);
    }

    public static function fireArmy($db) {
        $cityId    = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 1;
        $armyType  = isset($_REQUEST['armyType']) ? trim($_REQUEST['armyType']) : '';
        $amount    = isset($_REQUEST['amount']) ? (int)$_REQUEST['amount'] : 0;

        $validUnits = ['spies', 'army_a', 'army_b', 'army_c', 'army_d', 'army_e', 'army_f', 'wall_a', 'wall_b', 'wall_c'];
        $isWall = in_array($armyType, ['wall_a', 'wall_b', 'wall_c']);

        if (in_array($armyType, $validUnits) && $amount > 0) {
            $popRefund = $isWall ? 0 : $amount;
            $stmt = $db->prepare("UPDATE `city` SET `{$armyType}` = GREATEST(0, `{$armyType}` - ?), `pop` = LEAST(`pop_max`, `pop` + ?) WHERE `id_city` = ?");
            $stmt->execute([$amount, $popRefund, $cityId]);
            ProductionService::recalculateCityProduction($db, $cityId);
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

    public static function getCityGarrison($db) {
        $cityId = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 1;
        $stmt = $db->prepare("SELECT * FROM city_hero WHERE id_city = ? AND console = 0");
        $stmt->execute([$cityId]);
        $heroes = $stmt->fetchAll() ?: [];
        foreach ($heroes as &$h) {
            foreach ($h as $k => $v) { if (is_numeric($v)) $h[$k] = (int)$v; }
        }
        sendJson($heroes);
    }

    public static function getCityWounded($db) {
        $cityId = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 1;
        $stmt = $db->prepare("SELECT * FROM city_wounded WHERE id_city = ?");
        $stmt->execute([$cityId]);
        $w = $stmt->fetch();
        if (!$w) {
            $w = ['army_a' => 0, 'army_b' => 0, 'army_c' => 0, 'army_d' => 0, 'army_e' => 0, 'army_f' => 0];
        } else {
            foreach ($w as $k => $v) { if (is_numeric($v)) $w[$k] = (int)$v; }
        }
        sendJson([$w]);
    }

    public static function cureCityWounded($db) {
        $cityId = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 1;
        $armyType = isset($_REQUEST['armyType']) ? (int)$_REQUEST['armyType'] : 1;
        $typeMap = [1 => 'army_a', 2 => 'army_b', 3 => 'army_c', 4 => 'army_d', 5 => 'army_e', 6 => 'army_f'];
        $col = $typeMap[$armyType] ?? 'army_a';

        $stmt = $db->prepare("SELECT `$col` FROM city_wounded WHERE id_city = ?");
        $stmt->execute([$cityId]);
        $count = (int)$stmt->fetchColumn();

        if ($count > 0) {
            // Restore soldiers to city garrison
            $db->prepare("UPDATE city SET `$col` = `$col` + ? WHERE id_city = ?")->execute([$count, $cityId]);
            $db->prepare("UPDATE city_wounded SET `$col` = 0 WHERE id_city = ?")->execute([$cityId]);
        }

        $freshCity = $db->query("SELECT * FROM city WHERE id_city = $cityId")->fetch();
        foreach ($freshCity as $k => $v) { if (is_numeric($v)) $freshCity[$k] = (int)$v; }

        sendJson(['state' => 'ok', 'City' => $freshCity, 'cityRes' => $freshCity]);
    }

    public static function fireCityWounded($db) {
        $cityId = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 1;
        $db->prepare("UPDATE city_wounded SET army_a = 0, army_b = 0, army_c = 0, army_d = 0, army_e = 0, army_f = 0 WHERE id_city = ?")->execute([$cityId]);
        sendJson(['state' => 'ok']);
    }

    public static function cancelUpgradeing($db) {
        $cityId = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 1;
        $idWorking = isset($_REQUEST['idWorking']) ? (int)$_REQUEST['idWorking'] : 0;

        $db->prepare("DELETE FROM timed_task WHERE id_task = ? AND id_city = ?")->execute([$idWorking, $cityId]);

        // Refund partial resources
        $db->prepare("UPDATE city SET food = LEAST(food_cap, food + 1000), wood = LEAST(wood_cap, wood + 1000), stone = LEAST(stone_cap, stone + 1000), metal = LEAST(metal_cap, metal + 1000) WHERE id_city = ?")->execute([$cityId]);

        $freshCity = $db->query("SELECT * FROM city WHERE id_city = $cityId")->fetch();
        foreach ($freshCity as $k => $v) { if (is_numeric($v)) $freshCity[$k] = (int)$v; }

        sendJson(['state' => 'ok', 'City' => $freshCity, 'cityRes' => $freshCity]);
    }

    public static function getCityStorage($db) {
        $cityId = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 1;
        ProductionService::recalculateCityProduction($db, $cityId);
        sendJson(ProductionService::getCityStorageData($db, $cityId));
    }

    public static function updatePercentage($db) {
        $cityId = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 1;
        $percentages = [];
        foreach (['foodPerc', 'woodPerc', 'stonePerc', 'metalPerc'] as $key) {
            $rawValue = $_REQUEST[$key] ?? null;
            $value = is_scalar($rawValue) ? filter_var($rawValue, FILTER_VALIDATE_INT) : false;
            if ($value === false || $value < 0 || $value > 100) {
                sendJson(['state' => 'error_1']);
            }
            $percentages[] = $value;
        }
        if (array_sum($percentages) > 100) {
            sendJson(['state' => 'error_0']);
        }

        $db->beginTransaction();
        try {
            $stmt = $db->prepare(
                "INSERT INTO `city_storage` (`id_city`, `food_perc`, `wood_perc`, `stone_perc`, `metal_perc`)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE `food_perc` = VALUES(`food_perc`), `wood_perc` = VALUES(`wood_perc`),
                    `stone_perc` = VALUES(`stone_perc`), `metal_perc` = VALUES(`metal_perc`)"
            );
            $stmt->execute(array_merge([$cityId], $percentages));

            ProductionService::recalculateCityProduction($db, $cityId);
            $cityStmt = $db->prepare("SELECT * FROM `city` WHERE `id_city` = ?");
            $cityStmt->execute([$cityId]);
            $city = $cityStmt->fetch(PDO::FETCH_ASSOC);
            foreach ($city as $key => $value) {
                if (is_numeric($value)) {
                    $city[$key] = (int)$value;
                }
            }
            $cityStorage = ProductionService::getCityStorageData($db, $cityId);
            $db->commit();

            sendJson(['state' => 'ok', 'City' => $city, 'cityStorage' => $cityStorage]);
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }
}
