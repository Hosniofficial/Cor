<?php
require_once __DIR__ . '/../services/ServerConfigService.php';
// CityController.php - Handles city resources, buildings, palace, jobs, and city actions

require_once __DIR__ . '/../services/ProductionService.php';
require_once __DIR__ . '/../services/ResourceService.php';
require_once __DIR__ . '/../services/TaskService.php';
require_once __DIR__ . '/../services/TributaryService.php';
require_once __DIR__ . '/../services/CityGarrisonService.php';

class CityController {
    private static function isDecorativeBuildingType(int $type): bool {
        return in_array($type, [19, 20], true);
    }

    private static function rejectDecorativeBuildingType(int $type): bool {
        if (!self::isDecorativeBuildingType($type)) {
            return false;
        }

        sendJson([
            'state' => 'error_building_disabled',
            'msg' => 'الميناء والمنارة مبنيان رمزيان ولا يمكن بناؤهما أو تطويرهما'
        ]);
        return true;
    }

    public static function handle($uri, $db) {
        $ownedCity = null;
        $isBuildingSpeedUp = $uri === '/api/ACityBuilding/speedUp';
        if (preg_match('~^/api/(?:ACity|ACityBuilding|ACityStudy|ACityPalace|ACityBarrary|ACityWounded|ACityStorage|ACityJop)/~', $uri)) {
            $player = requireAuthenticatedPlayer($db);
            if (!$isBuildingSpeedUp) {
                $ownedCity = requirePlayerOwnedCity($db, $player);
            }
        }

        if (in_array($uri, [
            '/api/ACityBuilding/speedUp',
            '/api/ACityStudy/speedUpStudy',
            '/api/ACityJop/speedUpHiring',
            '/api/ACityBuilding/cancelUpgradeing',
        ], true)) {
            $taskId = $_REQUEST['idTask'] ?? $_REQUEST['idWorking'] ?? 0;
            if ($isBuildingSpeedUp) {
                $taskStmt = $db->prepare('SELECT * FROM `timed_task` WHERE `id_task` = ? LIMIT 1');
                $taskStmt->execute([(int)$taskId]);
                $task = $taskStmt->fetch(PDO::FETCH_ASSOC);
                if (!$task || ($task['task_type'] ?? '') !== 'building') {
                    http_response_code(403);
                    sendJson(['state' => 'error_auth']);
                }

                $ownedCity = requirePlayerOwnedCity($db, $player, (int)$task['id_city']);
                $_REQUEST['idCity'] = (int)$ownedCity['id_city'];
            } else {
                $task = requirePlayerOwnedTask($db, $player, $taskId);
                if ((int)$task['id_city'] !== (int)$ownedCity['id_city']) {
                    http_response_code(403);
                    sendJson(['state' => 'error_auth']);
                }
            }
        }

        switch ($uri) {
            case '/api/ACity/refreshCityBase':
            case '/api/ACity/getCityBase':
            case '/api/ACity/getCityData':
                self::getCityBase($db, $ownedCity);
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
                requirePlayerOwnedCity($db, $player, $_REQUEST['idCity'] ?? 0);
                self::downgradeBuilding($db);
                return true;
            case '/api/ACityBuilding/explodeBuilding':
                self::explodeBuilding($db);
                return true;
            case '/api/ACityBuilding/speedUp':
                self::speedUpBuilding($db, $player);
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
            case '/api/ACityPalace/getSovereignty':
                self::getSovereignty($db, $player, $ownedCity);
                return true;
            case '/api/ACityPalace/abandonCity':
                self::abandonCity($db, $player, $ownedCity);
                return true;
            case '/api/ACityBarrary/getCityBarray':
                self::getCityBarray($db);
                return true;
            case '/api/ACityPalace/barryAbandon':
                self::barryAbandon($db, $player);
                return true;
            case '/api/ACityPalace/getCityGarrison':
                self::getCityGarrison($db, $ownedCity);
                return true;
            case '/api/ACityPalace/addCityGarrison':
                self::addCityGarrison($db, $player, $ownedCity);
                return true;
            case '/api/ACityPalace/removeHeroFromGarrison':
                self::removeHeroFromGarrison($db, $player, $ownedCity);
                return true;
            case '/api/ACityPalace/reordCityGarrison':
                self::reorderCityGarrison($db, $player, $ownedCity);
                return true;
            case '/api/ACity/resetCityHelper':
                sendJson(['state' => 'ok']);
                return true;
            case '/api/ACityWounded/getCityWounded':
                self::getCityWounded($db);
                return true;
            case '/api/ACity/cureCityWounded':
                sendJson(self::cureCityWounded($db, $player, $ownedCity));
                return true;
            case '/api/ACity/fireCityWounded':
                self::fireCityWounded($db, $ownedCity);
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
                self::fireArmy($db, $ownedCity);
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

    public static function getCityBase($db, $ownedCity) {
        $cityId = (int)$ownedCity['id_city'];
        $playerId = (int)$ownedCity['id_player'];
        $db->beginTransaction();
        try {
            $lockCity = $db->prepare(
                'SELECT `id_city` FROM `city` WHERE `id_city` = ? AND `id_player` = ? FOR UPDATE'
            );
            $lockCity->execute([$cityId, $playerId]);
            if ($lockCity->fetchColumn() === false) {
                throw new RuntimeException('City resource recipient not found');
            }

            ProductionService::recalculateCityProduction($db, $cityId);
            $stmt = $db->prepare(
                'SELECT * FROM `city` WHERE `id_city` = ? AND `id_player` = ? FOR UPDATE'
            );
            $stmt->execute([$cityId, $playerId]);
            $city = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$city) {
                throw new RuntimeException('City resource recipient not found');
            }
            $now = time();
            $elapsed = max(0, $now - (int)$city['LS']);
            if ($elapsed > 0) {
                $tribute = TributaryService::settleCityTribute($db, $cityId, $now, $city);
                $foodGain  = (int)round(($city['food_in']  - $city['food_out'])  * ($elapsed / 3600)) - $tribute['food'];
                $woodGain  = (int)round(($city['wood_in']  - $city['wood_out'])  * ($elapsed / 3600)) - $tribute['wood'];
                $stoneGain = (int)round(($city['stone_in'] - $city['stone_out']) * ($elapsed / 3600)) - $tribute['stone'];
                $metalGain = (int)round(($city['metal_in'] - $city['metal_out']) * ($elapsed / 3600)) - $tribute['metal'];
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

                $uStmt = $db->prepare(
                    "UPDATE `city`
                     SET `food` = ?, `wood` = ?, `stone` = ?, `metal` = ?, `coin` = ?, `LS` = ?
                     WHERE `id_city` = ? AND `id_player` = ?"
                );
                $uStmt->execute([
                    $city['food'],
                    $city['wood'],
                    $city['stone'],
                    $city['metal'],
                    $city['coin'],
                    $now,
                    $cityId,
                    $playerId
                ]);

                // Check for food starvation / hero salary shortage and trigger desertion alerts
                ProductionService::processStarvationAndDesertion($db, $city);
            }

            $stmt = $db->prepare('SELECT * FROM `city` WHERE `id_city` = ? AND `id_player` = ?');
            $stmt->execute([$cityId, $playerId]);
            $city = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$city) {
                throw new RuntimeException('City resource recipient not found after production update');
            }
            $db->commit();

            foreach ($city as $k => $v) {
                if (is_numeric($v)) $city[$k] = (int)$v;
            }
            sendJson($city);
            return;
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
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

    private static function getBuildingDuration($db, int $cityId, int $baseDuration): int {
        $palaceStmt = $db->prepare(
            "SELECT COALESCE(MAX(`lvl`), 0) FROM `city_building` " .
            "WHERE `id_city` = ? AND (`type` = 12 OR `place` = 'palace')"
        );
        $palaceStmt->execute([$cityId]);
        $palaceLvl = max(0, (int)$palaceStmt->fetchColumn());
        $palaceDurationFactor = $palaceLvl > 0
            ? max(0.1, 1 - (($palaceLvl * 2 + 3) / 100))
            : 1;
        $helperStmt = $db->prepare(
            "SELECT `helper` FROM `city` WHERE `id_city` = ?"
        );
        $helperStmt->execute([$cityId]);
        $helper = (int)$helperStmt->fetchColumn();
        $templeDurationFactor = 1;
        if ($helper === 1) {
            $templeStmt = $db->prepare(
                "SELECT COALESCE(MAX(`lvl`), 0) FROM `city_building` " .
                "WHERE `id_city` = ? AND `type` = 10"
            );
            $templeStmt->execute([$cityId]);
            $templeLvl = max(0, (int)$templeStmt->fetchColumn());
            if ($templeLvl > 0) {
                $templeDurationFactor = max(0.1, 1 - ($templeLvl / 100));
            }
        }
        $serverSpeedMultiplier = max(
            0.1,
            ServerConfigService::getFloat($db, 'building_speed_multiplier', 1.0)
        );
        return max(
            15,
            (int)ceil(
                $baseDuration
                * $palaceDurationFactor
                * $templeDurationFactor
                / $serverSpeedMultiplier
            )
        );
    }

    public static function getBuildingRequirements($type, $lvl) {
        if (self::$buildingReqs === null) {
            $candidates = [
                __DIR__ . '/../js-3.4.113/json/buildingReqData.json',
                dirname(__DIR__) . '/js-3.4.113/json/buildingReqData.json',
                dirname(__DIR__, 2) . '/caesar-assets/web/js-3.4.113/json/buildingReqData.json'
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
        if (self::rejectDecorativeBuildingType($type)) {
            return;
        }

        if ($cityId <= 0) {
            $cStmt = $db->query("SELECT id_city FROM city ORDER BY id_city ASC LIMIT 1");
            $cityId = (int)$cStmt->fetchColumn();
        }

        if (!empty($place) && $type > 0) {
            $cStmt = $db->prepare("SELECT * FROM city WHERE id_city = ?");
            $cStmt->execute([$cityId]);
            $city = $cStmt->fetch(PDO::FETCH_ASSOC);
            $playerId = (int)($city['id_player'] ?? 1);

            // Ensure target place is vacant
            $pStmt = $db->prepare("SELECT `type` FROM `city_building` WHERE `id_city` = ? AND `place` = ?");
            $pStmt->execute([$cityId, $place]);
            $currentPlaceType = (int)$pStmt->fetchColumn();
            if ($currentPlaceType > 0) {
                sendJson(['state' => 'error_occupied', 'msg' => 'هذه القطعة مشغولة بمبنى آخر بالفعل']);
                return;
            }

            // Keep unique city buildings aligned with the construction menu.
            $singlePlotBuildings = [6, 7, 8, 9, 10, 11, 14];
            if (in_array($type, $singlePlotBuildings, true)) {
                $existingStmt = $db->prepare("SELECT COUNT(*) FROM `city_building` WHERE `id_city` = ? AND `type` = ?");
                $existingStmt->execute([$cityId, $type]);
                if ((int)$existingStmt->fetchColumn() > 0) {
                    sendJson(['state' => 'error_max', 'msg' => 'لا يمكنك بناء أكثر من مبنى واحد من هذا النوع في المدينة']);
                    return;
                }
            }

            $req = self::getBuildingRequirements($type, 1);
            $costFood  = (int)($req['food']  ?? 61);
            $costWood  = (int)($req['wood']  ?? 276);
            $costStone = (int)($req['stone'] ?? 40);
            $costMetal = (int)($req['metal'] ?? 59);
            $costCoin  = (int)($req['coin']  ?? 0);
            $duration = self::getBuildingDuration($db, $cityId, (int)($req['time'] ?? 30));

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

        if ($cityId <= 0 || empty($place)) {
            sendJson(['state' => 'error', 'msg' => 'Invalid parameters']);
            return;
        }

        $db->beginTransaction();
        try {
            $cityStmt = $db->prepare("SELECT * FROM `city` WHERE `id_city` = ? FOR UPDATE");
            $cityStmt->execute([$cityId]);
            $city = $cityStmt->fetch(PDO::FETCH_ASSOC);
            if (!$city) {
                $db->rollBack();
                sendJson(['state' => 'error_city']);
                return;
            }

            $playerId = (int)$city['id_player'];
            $buildingStmt = $db->prepare(
                "SELECT `type`, `lvl` FROM `city_building` WHERE `id_city` = ? AND `place` = ?"
            );
            $buildingStmt->execute([$cityId, $place]);
            $building = $buildingStmt->fetch(PDO::FETCH_ASSOC);
            if (!$building) {
                $db->rollBack();
                sendJson(['state' => 'error_building']);
                return;
            }

            $type = (int)$building['type'];
            $currLvl = (int)$building['lvl'];
            if (self::isDecorativeBuildingType($type)) {
                $db->rollBack();
                self::rejectDecorativeBuildingType($type);
                return;
            }

            $pendingStmt = $db->prepare(
                "SELECT `id_task` FROM `timed_task` " .
                "WHERE `id_city` = ? AND `task_type` = 'building' AND `item_id` = ? LIMIT 1"
            );
            $pendingStmt->execute([$cityId, $place]);
            if ($pendingStmt->fetchColumn() !== false) {
                $db->rollBack();
                sendJson(['state' => 'error_in_progress', 'msg' => 'هذا المبنى قيد التطوير بالفعل']);
                return;
            }

            $nextLvl = $currLvl + 1;
            if ($nextLvl > 30) {
                $db->rollBack();
                sendJson(['state' => 'error_max_lvl', 'msg' => 'المبنى وصل للحد الأقصى للتطوير']);
                return;
            }

            $req = self::getBuildingRequirements($type, $nextLvl);
            $costFood  = (int)($req['food']  ?? ($nextLvl * 100));
            $costWood  = (int)($req['wood']  ?? ($nextLvl * 250));
            $costStone = (int)($req['stone'] ?? ($nextLvl * 150));
            $costMetal = (int)($req['metal'] ?? ($nextLvl * 100));
            $costCoin  = (int)($req['coin']  ?? 0);
            $duration = self::getBuildingDuration(
                $db,
                $cityId,
                (int)($req['time'] ?? ($nextLvl * 45))
            );

            if (!empty($req['condetion'])) {
                foreach ($req['condetion'] as $cond) {
                    if (($cond['type'] ?? '') === 'building') {
                        $reqBType = (int)$cond['BuildingType'];
                        $reqBLvl  = (int)$cond['lvl'];
                        $bStmt = $db->prepare("SELECT MAX(lvl) FROM city_building WHERE id_city = ? AND type = ?");
                        $bStmt->execute([$cityId, $reqBType]);
                        $hasLvl = (int)$bStmt->fetchColumn();
                        if ($hasLvl < $reqBLvl) {
                            $db->rollBack();
                            sendJson(['state' => 'error_cond', 'msg' => "يتطلب مبنى نوع {$reqBType} بمستوى {$reqBLvl}"]);
                            return;
                        }
                    }
                }
            }

            if ($nextLvl >= 10) {
                $itemStmt = $db->prepare(
                    "SELECT `amount` FROM `player_item` WHERE `id_player` = ? AND `id_item` = 'archit_a' FOR UPDATE"
                );
                $itemStmt->execute([$playerId]);
                if ((int)$itemStmt->fetchColumn() <= 0) {
                    $db->rollBack();
                    sendJson(['state' => 'error_item', 'msg' => 'يتطلب تطوير المبنى لمستوى 10 أو أعلى وجود مخطوطات معمارية (archit_a)']);
                    return;
                }
            }

            if ((int)$city['food'] < $costFood || (int)$city['wood'] < $costWood || (int)$city['stone'] < $costStone || (int)$city['metal'] < $costMetal || (int)$city['coin'] < $costCoin) {
                $db->rollBack();
                sendJson(['state' => 'error_res', 'msg' => 'الموارد المتوفرة بالمدينة غير كافية للترقية']);
                return;
            }

            if ($nextLvl >= 10)
                ItemController::deductPlayerItem($db, $playerId, 'archit_a', 1);

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
            $taskStmt = $db->prepare(
                "INSERT INTO `timed_task` (`id_city`, `id_player`, `task_type`, `item_id`, `lvl_to`, `start_time`, `time_arrive`, `details`) " .
                "VALUES (?, ?, 'building', ?, ?, ?, ?, ?)"
            );
            $taskStmt->execute([$cityId, $playerId, $place, $nextLvl, $now, $arrive, json_encode(['type' => $type])]);
            $taskId = (int)$db->lastInsertId();
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction())
                $db->rollBack();
            throw $error;
        }

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

    public static function downgradeBuilding($db) {
        $cityId = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 0;
        $place  = isset($_REQUEST['buildingPlace']) ? trim($_REQUEST['buildingPlace']) : '';
        if ($cityId <= 0 || $place === '') {
            sendJson(['state' => 'error', 'msg' => 'Invalid parameters']);
        }

        $buildingStmt = $db->prepare(
            'SELECT `type`, `lvl` FROM `city_building` WHERE `id_city` = ? AND `place` = ?'
        );
        $buildingStmt->execute([$cityId, $place]);
        $building = $buildingStmt->fetch(PDO::FETCH_ASSOC);
        if (!$building) {
            sendJson(['state' => 'error_building_not_found']);
        }

        $type = (int)$building['type'];
        $level = (int)$building['lvl'];
        if (self::rejectDecorativeBuildingType($type)) {
            return;
        }
        if ($level <= 0) {
            sendJson(['state' => 'error_min_level']);
        }

        if ($level === 1) {
            $stmt = $db->prepare(
                'UPDATE `city_building` SET `type` = 0, `lvl` = 0'
                . ' WHERE `id_city` = ? AND `place` = ? AND `type` = ? AND `lvl` = 1'
            );
            $stmt->execute([$cityId, $place, $type]);
            $nextType = 0;
            $nextLevel = 0;
        } else {
            $stmt = $db->prepare(
                'UPDATE `city_building` SET `lvl` = `lvl` - 1'
                . ' WHERE `id_city` = ? AND `place` = ? AND `type` = ? AND `lvl` = ? AND `lvl` > 1'
            );
            $stmt->execute([$cityId, $place, $type, $level]);
            $nextType = $type;
            $nextLevel = $level - 1;
        }
        if ($stmt->rowCount() !== 1) {
            sendJson(['state' => 'error_min_level']);
        }
        ProductionService::recalculateCityProduction($db, $cityId);
        sendJson([
            'state' => 'ok',
            'list' => [],
            'building' => [
                'place' => $place,
                'type' => $nextType,
                'lvl' => $nextLevel,
            ],
        ]);
    }

    public static function explodeBuilding($db) {
        $cityId = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 1;
        $place  = isset($_REQUEST['buildingPlace']) ? trim($_REQUEST['buildingPlace']) : '';
        if (!empty($place)) {
            $db->beginTransaction();
            try {
                $cStmt = $db->prepare("SELECT id_player FROM city WHERE id_city = ? FOR UPDATE");
                $cStmt->execute([$cityId]);
                $playerId = (int)$cStmt->fetchColumn();
                if ($playerId <= 0) {
                    $db->rollBack();
                    sendJson(['state' => 'error_city']);
                }

                $buildingStmt = $db->prepare(
                    "SELECT `type`, `lvl` FROM `city_building` WHERE `id_city` = ? AND `place` = ? FOR UPDATE"
                );
                $buildingStmt->execute([$cityId, $place]);
                $building = $buildingStmt->fetch(PDO::FETCH_ASSOC);
                if (!$building || (int)$building['type'] <= 0) {
                    $db->rollBack();
                    sendJson(['state' => 'error_building']);
                }
                if ((int)$building['type'] > 12) {
                    $db->rollBack();
                    sendJson(['state' => 'error_1', 'msg' => 'لا يمكنك هدم هذا المبنى']);
                }

                $itemStmt = $db->prepare(
                    "SELECT `amount` FROM `player_item` WHERE `id_player` = ? AND `id_item` = 'powder_keg' FOR UPDATE"
                );
                $itemStmt->execute([$playerId]);
                if ((int)$itemStmt->fetchColumn() < 1) {
                    $db->rollBack();
                    sendJson(['state' => 'error_0', 'msg' => 'لا يوجد لديك براميل بارود كافية']);
                }
                ItemController::deductPlayerItem($db, $playerId, 'powder_keg', 1);

                $stmt = $db->prepare(
                    "UPDATE `city_building` SET `type` = 0, `lvl` = 0 WHERE `id_city` = ? AND `place` = ?"
                );
                $stmt->execute([$cityId, $place]);
                ProductionService::recalculateCityProduction($db, $cityId);
                $db->commit();
            } catch (Throwable $error) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $error;
            }
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

    public static function speedUpBuilding($db, array $player) {
        $taskId    = isset($_REQUEST['idWorking']) ? (int)$_REQUEST['idWorking'] : (int)($_REQUEST['idTask'] ?? 0);
        $itemToUse = trim($_REQUEST['itemToUse'] ?? '');
        $cityId    = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 0;

        if ($taskId <= 0) {
            http_response_code(400);
            sendJson(['state' => 'error_task_not_found', 'msg' => 'مهمة التطوير غير صالحة']);
        }

        $tStmt = $db->prepare(
            "SELECT * FROM `timed_task` WHERE `id_task` = ? AND `id_city` = ? AND `task_type` = 'building'"
        );
        $tStmt->execute([$taskId, $cityId]);
        $task = $tStmt->fetch(PDO::FETCH_ASSOC);
        if (!$task) {
            http_response_code(404);
            sendJson(['state' => 'error_task_not_found', 'msg' => 'مهمة تطوير المبنى غير موجودة']);
        }

        $taskDetails = !empty($task['details'])
            ? json_decode($task['details'], true)
            : [];
        $buildingType = is_array($taskDetails)
            ? (int)($taskDetails['type'] ?? 0)
            : 0;
        if ($buildingType <= 0) {
            $buildingStmt = $db->prepare(
                "SELECT `type` FROM `city_building` WHERE `id_city` = ? AND `place` = ?"
            );
            $buildingStmt->execute([(int)$task['id_city'], $task['item_id']]);
            $buildingType = (int)$buildingStmt->fetchColumn();
        }
        if (self::rejectDecorativeBuildingType($buildingType)) {
            return;
        }

        $cityId = (int)$task['id_city'];
        $playerId = (int)$player['id_player'];
        $now = time();
        $rem = max(0, (int)$task['time_arrive'] - $now);

        $reduceSec = 0;
        // Speedup items definition (Architectural Blueprints & General Time items)
        $speedItems = [
            'archit_a'   => ['sec' => 900,   'pct' => 0.0],  // 15 minutes
            'archit_b'   => ['sec' => 3600,  'pct' => 0.0],  // 1 hour
            'archit_c'   => ['sec' => 10800, 'pct' => 0.0],  // 3 hours
            'archit_d'   => ['sec' => 0,     'pct' => 0.90], // 90% reduction
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
            }
        } else {
            $reduceSec = $rem;
        }

        $newArrive = max($now, (int)$task['time_arrive'] - $reduceSec);
        $db->prepare("UPDATE `timed_task` SET `time_arrive` = ? WHERE `id_task` = ? AND `id_city` = ?")
           ->execute([$newArrive, $taskId, $cityId]);

        if ($newArrive <= $now) {
            TaskService::processCompletedTasks($db);
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
        $requestedCity = requirePlayerOwnedCity($db, $player);
        $cityId = (int)$requestedCity['id_city'];

        $db->beginTransaction();
        try {
            $cityStmt = $db->prepare("SELECT * FROM city WHERE id_city = ? AND id_player = ? FOR UPDATE");
            $cityStmt->execute([$cityId, $playerId]);
            $city = $cityStmt->fetch(PDO::FETCH_ASSOC);
            if (!$city) {
                $db->rollBack();
                http_response_code(403);
                sendJson(['state' => 'error_auth']);
            }

            $currentLvl = (int)$city['lvl'];
            if ($currentLvl < 0 || $currentLvl >= 3) {
                $db->rollBack();
                sendJson(['state' => 'error_max_lvl', 'msg' => 'المدينة في أقصى مستوى توسعة']);
            }

            $palaceStmt = $db->prepare(
                "SELECT lvl FROM city_building WHERE id_city = ? AND (place = 'palace' OR type = 12) FOR UPDATE"
            );
            $palaceStmt->execute([$cityId]);
            $palaceLvls = $palaceStmt->fetchAll(PDO::FETCH_COLUMN);
            $palaceLvl = $palaceLvls ? max(array_map('intval', $palaceLvls)) : 0;
            $requiredPalaceLevels = [4, 8, 10];
            $reqPalaceLvl = $requiredPalaceLevels[$currentLvl];
            if ($palaceLvl < $reqPalaceLvl) {
                $db->rollBack();
                sendJson(['state' => 'error_palace_lvl', 'msg' => 'مستوى القصر غير كافٍ لتوسيع المدينة']);
            }

            $itemStmt = $db->prepare(
                "SELECT amount FROM player_item WHERE id_player = ? AND id_item = 'expan_plan' FOR UPDATE"
            );
            $itemStmt->execute([$playerId]);
            $hasPlans = (int)$itemStmt->fetchColumn();
            $reqPlans = (int)pow(2, $currentLvl);
            if ($hasPlans < $reqPlans) {
                $db->rollBack();
                sendJson(['state' => 'error_item_lack', 'msg' => 'لا تمتلك مخططات توسعة كافية']);
            }

            $reqRes = ($currentLvl + 1) * 3000;
            if (
                (float)$city['food'] < $reqRes ||
                (float)$city['wood'] < $reqRes ||
                (float)$city['stone'] < $reqRes ||
                (float)$city['metal'] < $reqRes ||
                (float)$city['coin'] < $reqRes
            ) {
                $db->rollBack();
                sendJson(['state' => 'error_resource_lack', 'msg' => 'الموارد غير كافية لتوسيع المدينة']);
            }

            $deductPlansStmt = $db->prepare(
                "UPDATE player_item SET amount = amount - ? WHERE id_player = ? AND id_item = 'expan_plan' AND amount >= ?"
            );
            $deductPlansStmt->execute([$reqPlans, $playerId, $reqPlans]);
            if ($deductPlansStmt->rowCount() !== 1) {
                $db->rollBack();
                sendJson(['state' => 'error_item_lack', 'msg' => 'لا تمتلك مخططات توسعة كافية']);
            }
            $db->prepare(
                "DELETE FROM player_item WHERE id_player = ? AND id_item = 'expan_plan' AND amount = 0"
            )->execute([$playerId]);

            $updateCityStmt = $db->prepare(
                "UPDATE city
                 SET lvl = lvl + 1,
                     food = food - ?,
                     wood = wood - ?,
                     stone = stone - ?,
                     metal = metal - ?,
                     coin = coin - ?
                 WHERE id_city = ? AND id_player = ? AND lvl = ?
                   AND food >= ? AND wood >= ? AND stone >= ? AND metal >= ? AND coin >= ?"
            );
            $updateCityStmt->execute([
                $reqRes, $reqRes, $reqRes, $reqRes, $reqRes,
                $cityId, $playerId, $currentLvl,
                $reqRes, $reqRes, $reqRes, $reqRes, $reqRes
            ]);
            if ($updateCityStmt->rowCount() !== 1) {
                $db->rollBack();
                sendJson(['state' => 'error_resource_lack', 'msg' => 'الموارد غير كافية لتوسيع المدينة']);
            }

            ProductionService::recalculateCityProduction($db, $cityId);
            $updatedCityStmt = $db->prepare("SELECT * FROM city WHERE id_city = ? AND id_player = ?");
            $updatedCityStmt->execute([$cityId, $playerId]);
            $updatedCity = $updatedCityStmt->fetch(PDO::FETCH_ASSOC);
            if (!$updatedCity) {
                throw new RuntimeException('Expanded city could not be reloaded');
            }
            foreach ($updatedCity as $key => $value) {
                if (is_numeric($value)) {
                    $updatedCity[$key] = (int)$value;
                }
            }

            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[CityController] City expansion failed: ' . $error->getMessage());
            throw $error;
        }

        sendJson([
            'state' => 'ok',
            'City'  => $updatedCity
        ]);
    }

    public static function applyArmyDismissal(PDO $db, int $cityId, string $armyType, int $amount): bool {
        $validUnits = ['spies', 'army_a', 'army_b', 'army_c', 'army_d', 'army_e', 'army_f', 'wall_a', 'wall_b', 'wall_c'];
        if ($cityId <= 0 || !in_array($armyType, $validUnits, true) || $amount <= 0) {
            return false;
        }

        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $lockClause = $driver === 'sqlite' ? '' : ' FOR UPDATE';
        $cityStmt = $db->prepare(
            "SELECT `{$armyType}` AS `unit_count`, `pop`, `pop_max` FROM `city` WHERE `id_city` = ?" . $lockClause
        );
        $cityStmt->execute([$cityId]);
        $city = $cityStmt->fetch(PDO::FETCH_ASSOC);
        if (!$city || (int)$city['unit_count'] < $amount) {
            return false;
        }

        $isWall = in_array($armyType, ['wall_a', 'wall_b', 'wall_c'], true);
        $populationRefund = $isWall ? 0 : $amount;
        $update = $db->prepare(
            "UPDATE `city` SET `{$armyType}` = `{$armyType}` - ?, " .
            "`pop` = CASE WHEN `pop` + ? > `pop_max` THEN `pop_max` ELSE `pop` + ? END " .
            "WHERE `id_city` = ? AND `{$armyType}` >= ?"
        );
        $update->execute([$amount, $populationRefund, $populationRefund, $cityId, $amount]);
        return $update->rowCount() === 1;
    }

    public static function fireArmy($db, array $ownedCity) {
        $cityId    = (int)($ownedCity['id_city'] ?? 0);
        $armyType  = isset($_REQUEST['armyType']) ? trim($_REQUEST['armyType']) : '';
        $amount    = isset($_REQUEST['amount']) ? (int)$_REQUEST['amount'] : 0;
        $validUnits = ['spies', 'army_a', 'army_b', 'army_c', 'army_d', 'army_e', 'army_f', 'wall_a', 'wall_b', 'wall_c'];

        if ($cityId <= 0 || !in_array($armyType, $validUnits, true) || $amount <= 0) {
            sendJson(['state' => 'error_invalid_request', 'msg' => 'بيانات طرد القوات غير صحيحة']);
        }

        $db->beginTransaction();
        try {
            ProductionService::recalculateCityProduction($db, $cityId);
            if (!self::applyArmyDismissal($db, $cityId, $armyType, $amount)) {
                $db->rollBack();
                sendJson(['state' => 'error_army_amount', 'msg' => 'عدد القوات المطلوب طرده أكبر من العدد الموجود']);
            }

            ProductionService::recalculateCityProduction($db, $cityId);
            $cStmt = $db->prepare("SELECT * FROM city WHERE id_city = ?");
            $cStmt->execute([$cityId]);
            $city = $cStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            foreach ($city as $k => $v) {
                if (is_numeric($v)) {
                    $city[$k] = (int)$v;
                }
            }
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[CityController] Army dismissal failed: ' . $error->getMessage());
            throw $error;
        }

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

    public static function barryAbandon($db, $player) {
        $x = isset($_REQUEST['xCoord']) ? (int)$_REQUEST['xCoord'] : 0;
        $y = isset($_REQUEST['yCoord']) ? (int)$_REQUEST['yCoord'] : 0;
        $stmt = $db->prepare(
            "SELECT id_city FROM city_barray WHERE x_coord = ? AND y_coord = ? AND id_player = ?"
        );
        $stmt->execute([$x, $y, (int)$player['id_player']]);
        $cityId = (int)$stmt->fetchColumn();

        $del = $db->prepare(
            "DELETE FROM city_barray WHERE x_coord = ? AND y_coord = ? AND id_player = ?"
        );
        $del->execute([$x, $y, (int)$player['id_player']]);

        if ($cityId > 0) {
            ProductionService::recalculateCityProduction($db, $cityId);
        }
        sendJson(['state' => 'ok']);
    }

    public static function getCityGarrison($db, array $ownedCity) {
        sendJson(CityGarrisonService::getCityGarrison($db, (int)$ownedCity['id_city']));
    }

    public static function addCityGarrison($db, array $player, array $ownedCity): void {
        $heroId = filter_var($_POST['idHero'] ?? null, FILTER_VALIDATE_INT);
        if ($heroId === false || $heroId === null || $heroId <= 0) {
            http_response_code(400);
            sendJson(['state' => 'error_invalid_request']);
        }

        $cityId = (int)$ownedCity['id_city'];
        $playerId = (int)$player['id_player'];
        $db->beginTransaction();
        try {
            $cityLock = $db->prepare(
                'SELECT id_city FROM city WHERE id_city = ? AND id_player = ? FOR UPDATE'
            );
            $cityLock->execute([$cityId, $playerId]);
            if ($cityLock->fetchColumn() === false) {
                $db->rollBack();
                http_response_code(403);
                sendJson(['state' => 'error_auth']);
            }

            $heroStmt = $db->prepare(
                'SELECT id_hero, id_city, id_player, console, in_city FROM city_hero ' .
                'WHERE id_hero = ? AND id_city = ? AND id_player = ? FOR UPDATE'
            );
            $heroStmt->execute([$heroId, $cityId, $playerId]);
            $hero = $heroStmt->fetch(PDO::FETCH_ASSOC);
            if (!$hero || (int)$hero['console'] !== 0 || (int)$hero['in_city'] !== 1) {
                $db->rollBack();
                sendJson(['state' => 'error_hero_unavailable']);
            }

            $countStmt = $db->prepare('SELECT COUNT(*) FROM city_garrison WHERE id_city = ?');
            $countStmt->execute([$cityId]);
            if ((int)$countStmt->fetchColumn() >= CityGarrisonService::MAX_CITY_GARRISON) {
                $db->rollBack();
                sendJson(['state' => 'error_garrison_full']);
            }

            $orderStmt = $db->prepare(
                'SELECT COALESCE(MAX(ord), -1) + 1 FROM city_garrison WHERE id_city = ?'
            );
            $orderStmt->execute([$cityId]);
            $order = (int)$orderStmt->fetchColumn();
            $db->prepare(
                "INSERT INTO city_garrison (id_hero, id_city, id_player, ord, status, time_arrive) " .
                "VALUES (?, ?, ?, ?, 'stationed', ?)"
            )->execute([$heroId, $cityId, $playerId, $order, time()]);

            $heroDataStmt = $db->prepare(
                'SELECT h.*, h.name AS HeroName, p.name AS LordName FROM city_hero h ' .
                'JOIN player p ON p.id_player = h.id_player WHERE h.id_hero = ?'
            );
            $heroDataStmt->execute([$heroId]);
            $heroData = $heroDataStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            foreach ($heroData as $key => $value) {
                if (is_numeric($value)) $heroData[$key] = (int)$value;
            }
            $garrison = CityGarrisonService::getCityGarrison($db, $cityId);
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) $db->rollBack();
            throw $error;
        }

        sendJson(['state' => 'ok', 'Garrison' => $garrison, 'Hero' => $heroData]);
    }

    public static function removeHeroFromGarrison($db, array $player, array $ownedCity): void {
        $heroId = filter_var($_POST['idHero'] ?? null, FILTER_VALIDATE_INT);
        if ($heroId === false || $heroId === null || $heroId <= 0) {
            http_response_code(400);
            sendJson(['state' => 'error_invalid_request']);
        }

        $requestCityId = (int)$ownedCity['id_city'];
        $playerId = (int)$player['id_player'];
        $db->beginTransaction();
        try {
            $heroStmt = $db->prepare(
                'SELECT id_hero, id_city, id_player, in_city FROM city_hero ' .
                'WHERE id_hero = ? FOR UPDATE'
            );
            $heroStmt->execute([$heroId]);
            $hero = $heroStmt->fetch(PDO::FETCH_ASSOC);
            if (!$hero) {
                $db->rollBack();
                sendJson(['state' => 'error_hero_not_found']);
            }

            $garrisonStmt = $db->prepare(
                'SELECT id_city, id_player, status, ord FROM city_garrison WHERE id_hero = ? FOR UPDATE'
            );
            $garrisonStmt->execute([$heroId]);
            $garrisonRow = $garrisonStmt->fetch(PDO::FETCH_ASSOC);
            if (!$garrisonRow) {
                $db->rollBack();
                sendJson(['state' => 'error_hero_not_in_garrison']);
            }

            $hostCityId = (int)$garrisonRow['id_city'];
            $homeCityId = (int)$hero['id_city'];
            $isHostOwner = $hostCityId === $requestCityId &&
                (int)$ownedCity['id_player'] === $playerId;
            $isHeroOwner = (int)$hero['id_player'] === $playerId;
            if (!$isHostOwner && !$isHeroOwner) {
                $db->rollBack();
                http_response_code(403);
                sendJson(['state' => 'error_auth']);
            }
            if ($garrisonRow['status'] !== 'stationed') {
                $db->rollBack();
                sendJson(['state' => 'error_garrison_busy']);
            }

            $activeBattleStmt = $db->prepare(
                "SELECT 1 FROM world_battel WHERE state = 'marching' AND " .
                "(id_city = ? OR (x_coord, y_coord) = " .
                "(SELECT x, y FROM city WHERE id_city = ?)) LIMIT 1"
            );
            $activeBattleStmt->execute([$hostCityId, $hostCityId]);
            if ($activeBattleStmt->fetchColumn() !== false) {
                $db->rollBack();
                sendJson(['state' => 'error_garrison_engaged']);
            }

            if ($hostCityId === $homeCityId) {
                $db->prepare('DELETE FROM city_garrison WHERE id_hero = ? AND id_city = ?')
                    ->execute([$heroId, $hostCityId]);
                $responseCityId = $hostCityId;
                $garrison = CityGarrisonService::getCityGarrison($db, $hostCityId);
            } else {
                $coordinatesStmt = $db->prepare(
                    'SELECT host.x AS host_x, host.y AS host_y, home.x AS home_x, home.y AS home_y ' .
                    'FROM city host JOIN city home ON home.id_city = ? WHERE host.id_city = ?'
                );
                $coordinatesStmt->execute([$homeCityId, $hostCityId]);
                $coordinates = $coordinatesStmt->fetch(PDO::FETCH_ASSOC);
                if (!$coordinates) {
                    throw new RuntimeException('Garrison return cities are unavailable');
                }
                $armyStmt = $db->prepare('SELECT * FROM hero_army WHERE id_hero = ?');
                $armyStmt->execute([$heroId]);
                $army = $armyStmt->fetch(PDO::FETCH_ASSOC) ?: [];
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
                $travelDuration = WorldController::getJoinMarchDuration(
                    (int)$coordinates['host_x'],
                    (int)$coordinates['host_y'],
                    (int)$coordinates['home_x'],
                    (int)$coordinates['home_y'],
                    $army,
                    0,
                    false,
                    $config
                );
                $returnAt = time() + $travelDuration;
                $db->prepare(
                    "UPDATE city_garrison SET status = 'returning', return_at = ?, battle_id = NULL " .
                    "WHERE id_hero = ?"
                )->execute([$returnAt, $heroId]);
                $responseCityId = $isHostOwner ? $hostCityId : $homeCityId;
                $garrison = $isHostOwner
                    ? CityGarrisonService::getCityGarrison($db, $hostCityId)
                    : CityGarrisonService::getPlayerGarrisons($db, $playerId);
            }

            $heroDataStmt = $db->prepare('SELECT * FROM city_hero WHERE id_hero = ?');
            $heroDataStmt->execute([$heroId]);
            $heroData = $heroDataStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            foreach ($heroData as $key => $value) {
                if (is_numeric($value)) $heroData[$key] = (int)$value;
            }
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) $db->rollBack();
            throw $error;
        }

        sendJson([
            'state' => 'ok',
            'Garrison' => $garrison,
            'Hero' => $heroData,
            'idCity' => $responseCityId
        ]);
    }

    private static function reorderCityGarrison($db, array $player, array $ownedCity): void {
        $heroId = filter_var($_POST['idHero'] ?? null, FILTER_VALIDATE_INT);
        $direction = $_POST['Direction'] ?? '';
        if ($heroId === false || $heroId === null || $heroId <= 0 ||
            !in_array($direction, ['up', 'down'], true)) {
            http_response_code(400);
            sendJson(['state' => 'error_invalid_request']);
        }

        $cityId = (int)$ownedCity['id_city'];
        $playerId = (int)$player['id_player'];
        $db->beginTransaction();
        try {
            $cityLock = $db->prepare(
                'SELECT id_city FROM city WHERE id_city = ? AND id_player = ? FOR UPDATE'
            );
            $cityLock->execute([$cityId, $playerId]);
            if ($cityLock->fetchColumn() === false) {
                $db->rollBack();
                http_response_code(403);
                sendJson(['state' => 'error_auth']);
            }

            $heroesStmt = $db->prepare(
                'SELECT id_hero, ord, status, id_player FROM city_garrison ' .
                'WHERE id_city = ? ORDER BY ord ASC, id_hero ASC FOR UPDATE'
            );
            $heroesStmt->execute([$cityId]);
            $heroes = $heroesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $selectedIndex = null;
            $ownedIndexes = [];
            foreach ($heroes as $index => $hero) {
                if ((int)$hero['id_player'] !== $playerId) continue;
                if ((int)$hero['id_hero'] === $heroId) $selectedIndex = count($ownedIndexes);
                $ownedIndexes[] = $index;
            }

            if ($selectedIndex === null) {
                $db->rollBack();
                sendJson(['state' => 'error_hero_not_in_garrison']);
            }
            $selectedPosition = $ownedIndexes[$selectedIndex];
            if ($heroes[$selectedPosition]['status'] !== 'stationed') {
                $db->rollBack();
                sendJson(['state' => 'error_garrison_busy']);
            }

            $neighborIndex = $selectedIndex + ($direction === 'up' ? -1 : 1);
            if (!isset($ownedIndexes[$neighborIndex])) {
                $db->rollBack();
                sendJson(['state' => 'error_no_neighbor']);
            }
            $neighborPosition = $ownedIndexes[$neighborIndex];
            if ($heroes[$neighborPosition]['status'] !== 'stationed') {
                $db->rollBack();
                sendJson(['state' => 'error_garrison_busy']);
            }

            $temporary = $heroes[$selectedPosition];
            $heroes[$selectedPosition] = $heroes[$neighborPosition];
            $heroes[$neighborPosition] = $temporary;

            $update = $db->prepare(
                'UPDATE city_garrison SET ord = ? WHERE id_city = ? AND id_hero = ?'
            );
            foreach ($heroes as $order => $hero) {
                $update->execute([$order, $cityId, (int)$hero['id_hero']]);
            }

            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }

        sendJson(['state' => 'ok']);
    }

    public static function getCityWounded($db) {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            sendJson(['state' => 'error_method_not_allowed']);
        }
        $cityId = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 1;
        $stmt = $db->prepare("SELECT * FROM city_wounded WHERE id_city = ?");
        $stmt->execute([$cityId]);
        $w = $stmt->fetch();
        if (!$w) {
            $w = ['army_a' => 0, 'army_b' => 0, 'army_c' => 0, 'army_d' => 0, 'army_e' => 0, 'army_f' => 0];
        } else {
            foreach ($w as $k => $v) { if (is_numeric($v)) $w[$k] = (int)$v; }
        }
        sendJson($w);
    }

    public static function cureCityWounded(PDO $db, array $player, array $ownedCity): array {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            return ['state' => 'error_method_not_allowed'];
        }

        $typeMap = [
            'army_a' => 18,
            'army_b' => 500,
            'army_c' => 600,
            'army_d' => 30,
            'army_e' => 120,
            'army_f' => 450
        ];
        $armyType = is_scalar($_REQUEST['armyType'] ?? null)
            ? trim((string)$_REQUEST['armyType'])
            : '';
        if (ctype_digit($armyType)) {
            $armyType = [
                '1' => 'army_a',
                '2' => 'army_b',
                '3' => 'army_c',
                '4' => 'army_d',
                '5' => 'army_e',
                '6' => 'army_f'
            ][$armyType] ?? '';
        }
        if (!isset($typeMap[$armyType])) {
            return ['state' => 'error_0'];
        }

        $playerId = (int)$player['id_player'];
        $cityId = (int)$ownedCity['id_city'];
        $lockClause = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';

        $db->beginTransaction();
        try {
            $cityStmt = $db->prepare(
                "SELECT `coin` FROM `city` WHERE `id_city` = ? AND `id_player` = ?$lockClause"
            );
            $cityStmt->execute([$cityId, $playerId]);
            $city = $cityStmt->fetch(PDO::FETCH_ASSOC);
            if (!$city) {
                $db->rollBack();
                return ['state' => 'error_auth'];
            }

            $column = $armyType;
            $woundedStmt = $db->prepare(
                "SELECT `$column` FROM `city_wounded` WHERE `id_city` = ?$lockClause"
            );
            $woundedStmt->execute([$cityId]);
            $woundedCount = (int)$woundedStmt->fetchColumn();
            if ($woundedCount <= 0) {
                $db->rollBack();
                return ['state' => 'error_0'];
            }

            $coinAmount = $woundedCount * $typeMap[$armyType];
            if ((int)$city['coin'] < $coinAmount) {
                $db->rollBack();
                return ['state' => 'error_1', 'coinAmount' => $coinAmount];
            }

            $cityUpdate = $db->prepare(
                "UPDATE `city`
                 SET `$column` = `$column` + ?, `coin` = `coin` - ?
                 WHERE `id_city` = ? AND `id_player` = ? AND `coin` >= ?"
            );
            $cityUpdate->execute([$woundedCount, $coinAmount, $cityId, $playerId, $coinAmount]);
            if ($cityUpdate->rowCount() !== 1) {
                $db->rollBack();
                return ['state' => 'error_1', 'coinAmount' => $coinAmount];
            }

            $woundedUpdate = $db->prepare(
                "UPDATE `city_wounded` SET `$column` = `$column` - ?
                 WHERE `id_city` = ? AND `$column` >= ?"
            );
            $woundedUpdate->execute([$woundedCount, $cityId, $woundedCount]);
            if ($woundedUpdate->rowCount() !== 1) {
                throw new RuntimeException('Wounded troop count changed during treatment');
            }

            $cityStmt = $db->prepare("SELECT * FROM `city` WHERE `id_city` = ? AND `id_player` = ?");
            $cityStmt->execute([$cityId, $playerId]);
            $freshCity = $cityStmt->fetch(PDO::FETCH_ASSOC);
            foreach ($freshCity as $key => $value) {
                if (is_numeric($value)) {
                    $freshCity[$key] = (int)$value;
                }
            }

            $woundedStmt = $db->prepare("SELECT * FROM `city_wounded` WHERE `id_city` = ?");
            $woundedStmt->execute([$cityId]);
            $cityWounded = $woundedStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            foreach ($cityWounded as $key => $value) {
                if (is_numeric($value)) {
                    $cityWounded[$key] = (int)$value;
                }
            }

            $db->commit();
            return [
                'state' => 'ok',
                'City' => $freshCity,
                'cityRes' => $freshCity,
                'cityWounded' => $cityWounded,
                'healedAmount' => $woundedCount,
                'coinAmount' => $coinAmount
            ];
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    public static function applyWoundedArmyDismissal(PDO $db, int $cityId, string $armyType): int {
        $validUnits = ['army_a', 'army_b', 'army_c', 'army_d', 'army_e', 'army_f'];
        if ($cityId <= 0 || !in_array($armyType, $validUnits, true)) {
            return 0;
        }

        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $lockClause = $driver === 'sqlite' ? '' : ' FOR UPDATE';
        $woundedStmt = $db->prepare(
            "SELECT `{$armyType}` FROM `city_wounded` WHERE `id_city` = ?" . $lockClause
        );
        $woundedStmt->execute([$cityId]);
        $amount = (int)$woundedStmt->fetchColumn();
        if ($amount <= 0) {
            return 0;
        }

        $cityStmt = $db->prepare(
            'SELECT `pop`, `pop_max` FROM `city` WHERE `id_city` = ?' . $lockClause
        );
        $cityStmt->execute([$cityId]);
        if (!$cityStmt->fetch(PDO::FETCH_ASSOC)) {
            return 0;
        }

        $dismiss = $db->prepare(
            "UPDATE `city_wounded` SET `{$armyType}` = 0 WHERE `id_city` = ? AND `{$armyType}` >= ?"
        );
        $dismiss->execute([$cityId, $amount]);
        if ($dismiss->rowCount() !== 1) {
            return 0;
        }

        $restorePopulation = $db->prepare(
            'UPDATE `city` SET `pop` = CASE WHEN `pop` + ? > `pop_max` THEN `pop_max` ELSE `pop` + ? END ' .
            'WHERE `id_city` = ?'
        );
        $restorePopulation->execute([$amount, $amount, $cityId]);

        return $amount;
    }

    public static function fireCityWounded($db, array $ownedCity) {
        $cityId = (int)($ownedCity['id_city'] ?? 0);
        $armyType = isset($_REQUEST['armyType']) ? trim($_REQUEST['armyType']) : '';
        if ($cityId <= 0 || !in_array($armyType, ['army_a', 'army_b', 'army_c', 'army_d', 'army_e', 'army_f'], true)) {
            sendJson(['state' => 'error_invalid_request', 'msg' => 'بيانات طرد الجنود الجرحى غير صحيحة']);
        }

        $db->beginTransaction();
        try {
            ProductionService::recalculateCityProduction($db, $cityId);
            $dismissed = self::applyWoundedArmyDismissal($db, $cityId, $armyType);
            if ($dismissed <= 0) {
                $db->rollBack();
                sendJson(['state' => 'error_wounded_amount', 'msg' => 'لا توجد قوات جريحة من هذا النوع لطردها']);
            }

            ProductionService::recalculateCityProduction($db, $cityId);
            $cityStmt = $db->prepare('SELECT * FROM `city` WHERE `id_city` = ?');
            $cityStmt->execute([$cityId]);
            $city = $cityStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $woundedStmt = $db->prepare('SELECT * FROM `city_wounded` WHERE `id_city` = ?');
            $woundedStmt->execute([$cityId]);
            $cityWounded = $woundedStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            foreach ($city as $key => $value) {
                if (is_numeric($value)) {
                    $city[$key] = (int)$value;
                }
            }
            foreach ($cityWounded as $key => $value) {
                if (is_numeric($value)) {
                    $cityWounded[$key] = (int)$value;
                }
            }
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[CityController] Wounded army dismissal failed: ' . $error->getMessage());
            throw $error;
        }

        sendJson([
            'state' => 'ok',
            'City' => $city,
            'cityRes' => $city,
            'cityWounded' => $cityWounded,
            'dismissedAmount' => $dismissed
        ]);
    }

    public static function cancelUpgradeing($db) {
        $player = requireAuthenticatedPlayer($db);
        $playerId = (int)$player['id_player'];
        $cityId = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 1;
        $idWorking = isset($_REQUEST['idWorking']) ? (int)$_REQUEST['idWorking'] : 0;
        requirePlayerOwnedCity($db, $player, $cityId);

        $refund = [];
        $db->beginTransaction();
        try {
            $delete = $db->prepare("DELETE FROM timed_task WHERE id_task = ? AND id_city = ?");
            $delete->execute([$idWorking, $cityId]);
            if ($delete->rowCount() === 1) {
                $refund = ResourceService::creditCityUpToLimit($db, $cityId, $playerId, [
                    'food' => 1000,
                    'wood' => 1000,
                    'stone' => 1000,
                    'metal' => 1000
                ]);
            }
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }

        $freshStmt = $db->prepare("SELECT * FROM city WHERE id_city = ? AND id_player = ?");
        $freshStmt->execute([$cityId, $playerId]);
        $freshCity = $freshStmt->fetch();
        foreach ($freshCity as $k => $v) { if (is_numeric($v)) $freshCity[$k] = (int)$v; }

        sendJson(['state' => 'ok', 'City' => $freshCity, 'cityRes' => $freshCity, 'refund' => $refund]);
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

    public static function getSovereignty($db, array $player, array $ownedCity): void {
        $cityId = (int)$ownedCity['id_city'];
        $playerId = (int)$player['id_player'];

        $sovereignStmt = $db->prepare(
            'SELECT sovereign.id_city, sovereign.name, sovereign.x, sovereign.y, owner.name AS owner_name ' .
            'FROM city_tributary relation ' .
            'JOIN city sovereign ON sovereign.id_city = relation.id_sovereign_city ' .
            'JOIN player owner ON owner.id_player = sovereign.id_player ' .
            'WHERE relation.id_city = ? LIMIT 1'
        );
        $sovereignStmt->execute([$cityId]);
        $sovereign = $sovereignStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        $tributariesStmt = $db->prepare(
            'SELECT tributary.id_city, tributary.name, tributary.x, tributary.y, tributary.lvl, ' .
            'tributary.id_player, owner.name AS owner_name, relation.colonized_at ' .
            'FROM city_tributary relation ' .
            'JOIN city tributary ON tributary.id_city = relation.id_city ' .
            'JOIN player owner ON owner.id_player = tributary.id_player ' .
            'WHERE relation.id_sovereign_city = ? ORDER BY relation.colonized_at, tributary.id_city'
        );
        $tributariesStmt->execute([$cityId]);

        $balanceStmt = $db->prepare(
            'SELECT food, wood, stone, metal FROM city_tributary_balance WHERE id_sovereign_city = ?'
        );
        $balanceStmt->execute([$cityId]);
        $balance = $balanceStmt->fetch(PDO::FETCH_ASSOC) ?: [
            'food' => 0,
            'wood' => 0,
            'stone' => 0,
            'metal' => 0
        ];

        $cityCountStmt = $db->prepare(
            'SELECT COUNT(*), MIN(id_city) FROM city WHERE id_player = ?'
        );
        $cityCountStmt->execute([$playerId]);
        $citySummary = $cityCountStmt->fetch(PDO::FETCH_NUM) ?: [0, 0];
        $cityCount = (int)$citySummary[0];
        $primaryCityId = (int)$citySummary[1];

        sendJson([
            'state' => 'ok',
            'sovereign' => $sovereign,
            'tributaries' => $tributariesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'balance' => array_map('intval', $balance),
            'cityCount' => $cityCount,
            'canAbandon' => $cityCount > 1 && $cityId !== $primaryCityId
        ]);
    }

    public static function abandonCity($db, array $player, array $ownedCity): void {
        $playerId = (int)$player['id_player'];
        $cityId = (int)$ownedCity['id_city'];
        $password = $_REQUEST['password'] ?? '';
        if (!is_string($password) || $password === '' || strlen($password) > 72) {
            sendJson(['state' => 'error_password']);
        }

        $db->beginTransaction();
        try {
            $playerLock = $db->prepare('SELECT id_player FROM player WHERE id_player = ? FOR UPDATE');
            $playerLock->execute([$playerId]);
            if ($playerLock->fetchColumn() === false) {
                $db->rollBack();
                sendJson(['state' => 'error_auth']);
            }

            $cityLock = $db->prepare(
                'SELECT id_city, x, y FROM city WHERE id_city = ? AND id_player = ? FOR UPDATE'
            );
            $cityLock->execute([$cityId, $playerId]);
            $city = $cityLock->fetch(PDO::FETCH_ASSOC);
            if (!$city) {
                $db->rollBack();
                sendJson(['state' => 'error_auth']);
            }

            $passwordStmt = $db->prepare('SELECT password FROM user_auth WHERE id_player = ? LIMIT 1');
            $passwordStmt->execute([$playerId]);
            $passwordHash = $passwordStmt->fetchColumn();
            if (!is_string($passwordHash) || !password_verify($password, $passwordHash)) {
                $db->rollBack();
                sendJson(['state' => 'error_password']);
            }

            $citySummaryStmt = $db->prepare(
                'SELECT COUNT(*), MIN(id_city) FROM city WHERE id_player = ?'
            );
            $citySummaryStmt->execute([$playerId]);
            $citySummary = $citySummaryStmt->fetch(PDO::FETCH_NUM) ?: [0, 0];
            if ((int)$citySummary[0] <= 1) {
                $db->rollBack();
                sendJson(['state' => 'error_last_city']);
            }
            if ((int)$citySummary[1] === $cityId) {
                $db->rollBack();
                sendJson(['state' => 'error_main_city']);
            }

            $heroMovementStmt = $db->prepare(
                'SELECT 1 FROM city_hero WHERE id_city = ? AND id_player = ? AND in_city = 0 LIMIT 1'
            );
            $heroMovementStmt->execute([$cityId, $playerId]);
            $activeBattleStmt = $db->prepare(
                "SELECT 1 FROM world_battel WHERE state = 'marching' " .
                'AND (id_city = ? OR (x_coord = ? AND y_coord = ?)) LIMIT 1'
            );
            $activeBattleStmt->execute([$cityId, (int)$city['x'], (int)$city['y']]);
            if ($heroMovementStmt->fetchColumn() !== false || $activeBattleStmt->fetchColumn() !== false) {
                $db->rollBack();
                sendJson(['state' => 'error_city_busy']);
            }

            $guestGarrisonStmt = $db->prepare(
                'SELECT g.id_hero, g.id_player, g.status, h.id_city AS home_city_id ' .
                'FROM city_garrison g JOIN city_hero h ON h.id_hero = g.id_hero ' .
                'WHERE g.id_city = ? AND h.id_city <> ? FOR UPDATE'
            );
            $guestGarrisonStmt->execute([$cityId, $cityId]);
            $guestGarrisons = $guestGarrisonStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($guestGarrisons as $guestGarrison) {
                if ($guestGarrison['status'] === 'engaged') {
                    $db->rollBack();
                    sendJson(['state' => 'error_city_busy']);
                }
                $db->prepare(
                    'UPDATE city_hero SET in_city = 1 ' .
                    'WHERE id_hero = ? AND id_player = ? AND in_city = 0'
                )->execute([(int)$guestGarrison['id_hero'], (int)$guestGarrison['id_player']]);
                $db->prepare(
                    'DELETE FROM city_garrison WHERE id_hero = ? AND id_city = ?'
                )->execute([(int)$guestGarrison['id_hero'], $cityId]);
            }

            $activeTradeStmt = $db->prepare(
                "SELECT 1 FROM city_market_trans WHERE status = 'marching' " .
                'AND (id_city_from = ? OR id_city_to = ?) LIMIT 1'
            );
            $activeTradeStmt->execute([$cityId, $cityId]);
            if ($activeTradeStmt->fetchColumn() !== false) {
                $db->rollBack();
                sendJson(['state' => 'error_city_busy']);
            }

            $tributaryCountStmt = $db->prepare(
                'SELECT COUNT(*) FROM city_tributary WHERE id_sovereign_city = ?'
            );
            $tributaryCountStmt->execute([$cityId]);
            $releasedTributaryCount = (int)$tributaryCountStmt->fetchColumn();

            $wildStmt = $db->prepare(
                'SELECT x_coord, y_coord FROM city_barray WHERE id_city = ?'
            );
            $wildStmt->execute([$cityId]);
            $releasedWilds = $wildStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $heroStmt = $db->prepare('SELECT id_hero FROM city_hero WHERE id_city = ? AND id_player = ?');
            $heroStmt->execute([$cityId, $playerId]);
            $heroIds = array_map('intval', $heroStmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
            if ($heroIds) {
                $placeholders = implode(',', array_fill(0, count($heroIds), '?'));
                $db->prepare("DELETE FROM hero_army WHERE id_hero IN ($placeholders)")->execute($heroIds);
                $db->prepare("DELETE FROM hero_medal WHERE id_hero IN ($placeholders)")->execute($heroIds);
                $db->prepare(
                    "UPDATE player_equip SET id_hero = 0, on_hero = 0 " .
                    "WHERE id_player = ? AND id_hero IN ($placeholders)"
                )->execute(array_merge([$playerId], $heroIds));
            }

            foreach ([
                'city_barray',
                'city_jop',
                'city_wounded',
                'city_storage',
                'city_theater'
            ] as $table) {
                $db->prepare("DELETE FROM `$table` WHERE id_city = ?")->execute([$cityId]);
            }

            $db->prepare('DELETE FROM market_offer WHERE id_city = ?')->execute([$cityId]);
            $deleteCityStmt = $db->prepare('DELETE FROM city WHERE id_city = ? AND id_player = ?');
            $deleteCityStmt->execute([$cityId, $playerId]);
            if ($deleteCityStmt->rowCount() !== 1) {
                throw new RuntimeException('City abandonment did not remove the owned city');
            }

            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[CityController] City abandonment failed: ' . $error->getMessage());
            throw $error;
        }

        sendJson([
            'state' => 'ok',
            'idCity' => $cityId,
            'releasedTributaryCount' => $releasedTributaryCount,
            'releasedWilds' => $releasedWilds
        ]);
    }
}
