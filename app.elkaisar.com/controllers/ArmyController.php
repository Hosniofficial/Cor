<?php
// ArmyController.php - Handles army recruitment, army building batches, and troop speed-ups

require_once __DIR__ . '/../services/TaskService.php';

class ArmyController {
    public static function handle($uri, $db) {
        if (preg_match('~^/api/(?:AArmy|AArmyBuild|AArmyBatch|ACityArmy)/~', $uri)) {
            $player = requireAuthenticatedPlayer($db);
            if ($uri === '/api/AArmyBuild/speedUpArmy'
                || $uri === '/api/ACityArmy/speedUpArmy'
                || $uri === '/api/AArmyBatch/speedUpBatches') {
                $taskId = $_POST['idTask'] ?? $_GET['idTask'] ?? $_POST['idBatch'] ?? $_GET['idBatch'] ?? 0;
                $task = requirePlayerOwnedTask($db, $player, $taskId);
                $cityId = $_POST['idCity'] ?? $_GET['idCity'] ?? null;
                if ($cityId !== null && (int)$task['id_city'] !== (int)$cityId) {
                    http_response_code(403);
                    sendJson(['state' => 'error_auth']);
                }
            } else {
                $city = requirePlayerOwnedCity($db, $player);
                if ($uri === '/api/AArmyBatch/cancelBatch') {
                    $taskId = $_REQUEST['idTask'] ?? $_REQUEST['idWorking'] ?? 0;
                    if ((int)$taskId > 0) {
                        $task = requirePlayerOwnedTask($db, $player, $taskId);
                        if ((int)$task['id_city'] !== (int)$city['id_city']) {
                            http_response_code(403);
                            sendJson(['state' => 'error_auth']);
                        }
                    }
                }
            }
        } elseif ($uri === '/api/APlayerEdu/speedUpStudyTask') {
            $player = requireAuthenticatedPlayer($db);
            $city = requirePlayerOwnedCity($db, $player);
            $taskId = $_REQUEST['idTask'] ?? 0;
            $task = requirePlayerOwnedTask($db, $player, $taskId);
            if ((int)$task['id_city'] !== (int)$city['id_city']) {
                http_response_code(403);
                sendJson(['state' => 'error_auth']);
            }
        }

        switch ($uri) {
            case '/api/AArmy/getArmyBuilding':
            case '/api/AArmyBuild/getArmyBuilding':
                self::getArmyBuilding($db);
                return true;
            case '/api/AArmyBuild/buildArmy':
                self::buildArmy($db);
                return true;
            case '/api/AArmyBuild/speedUpArmy':
            case '/api/ACityArmy/speedUpArmy':
            case '/api/AArmyBatch/speedUpBatches':
                self::speedUpArmy($db);
                return true;
            case '/api/AArmyBatch/cancelBatch':
                self::cancelBatch($db);
                return true;
            case '/api/APlayerEdu/speedUpStudyTask':
                self::speedUpStudyTask($db);
                return true;
        }
        return false;
    }

    public static function getArmyBuilding($db) {
        $cityId = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 1;
        $stmt = $db->prepare("SELECT spies, army_a, army_b, army_c, army_d, army_e, army_f, wall_a, wall_b, wall_c, wall_cap FROM city WHERE id_city = ?");
        $stmt->execute([$cityId]);
        $army = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        sendJson(['state' => 'ok', 'Army' => $army]);
    }

    public static function buildArmy($db) {
        $cityId    = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 1;
        $armyType  = isset($_REQUEST['armyType']) ? trim($_REQUEST['armyType']) : 'army_a';
        $amount    = isset($_REQUEST['amount']) ? (int)$_REQUEST['amount'] : 0;
        $place     = isset($_REQUEST['buildingPlace']) ? trim($_REQUEST['buildingPlace']) : '';

        if ($amount <= 0) {
            sendJson(['state' => 'error_4']);
        }

        $unitDefs = [
            'spies'  => [
                'food' => 600,  'wood' => 150,  'stone' => 0,    'metal' => 350,  'coin' => 90,  'pop' => 1, 'time' => 60,   'space' => 0, 'is_wall' => false,
                'cond' => ['place_type' => 4, 'place_lvl' => 1, 'study' => 'riding', 'study_lvl' => 1]
            ],
            'army_a' => [
                'food' => 150,  'wood' => 500,  'stone' => 0,    'metal' => 100,  'coin' => 18,  'pop' => 1, 'time' => 50,   'space' => 0, 'is_wall' => false,
                'cond' => ['place_type' => 3, 'place_lvl' => 1, 'study' => 'infantry', 'study_lvl' => 1]
            ],
            'army_b' => [
                'food' => 1500, 'wood' => 800,  'stone' => 0,    'metal' => 750,  'coin' => 500, 'pop' => 3, 'time' => 300,  'space' => 0, 'is_wall' => false,
                'cond' => ['place_type' => 4, 'place_lvl' => 5, 'study' => 'riding', 'study_lvl' => 3]
            ],
            'army_c' => [
                'food' => 2000, 'wood' => 500,  'stone' => 0,    'metal' => 2500, 'coin' => 600, 'pop' => 6, 'time' => 500,  'space' => 0, 'is_wall' => false,
                'cond' => ['place_type' => 3, 'place_lvl' => 9, 'study' => 'infantry', 'study_lvl' => 6]
            ],
            'army_d' => [
                'food' => 300,  'wood' => 350,  'stone' => 0,    'metal' => 300,  'coin' => 30,  'pop' => 1, 'time' => 120,  'space' => 0, 'is_wall' => false,
                'cond' => ['place_type' => 3, 'place_lvl' => 2, 'study' => 'infantry', 'study_lvl' => 2]
            ],
            'army_e' => [
                'food' => 1000, 'wood' => 1200, 'stone' => 0,    'metal' => 800,  'coin' => 120, 'pop' => 4, 'time' => 180,  'space' => 0, 'is_wall' => false,
                'cond' => ['place_type' => 5, 'place_lvl' => 3, 'study' => 'army', 'study_lvl' => 1]
            ],
            'army_f' => [
                'food' => 3000, 'wood' => 3000, 'stone' => 6000, 'metal' => 1200, 'coin' => 450, 'pop' => 8, 'time' => 1000, 'space' => 0, 'is_wall' => false,
                'cond' => ['place_type' => 5, 'place_lvl' => 7, 'study' => 'army', 'study_lvl' => 6]
            ],
            'wall_a' => [
                'food' => 50,   'wood' => 500,  'stone' => 100,  'metal' => 50,   'coin' => 0,   'pop' => 0, 'time' => 60,   'space' => 1, 'is_wall' => true,
                'cond' => ['place_type' => 13, 'place_lvl' => 1, 'study' => 'safe', 'study_lvl' => 1]
            ],
            'wall_b' => [
                'food' => 200,  'wood' => 2000, 'stone' => 1000, 'metal' => 500,  'coin' => 0,   'pop' => 0, 'time' => 180,  'space' => 3, 'is_wall' => true,
                'cond' => ['place_type' => 13, 'place_lvl' => 3, 'study' => 'safe', 'study_lvl' => 2]
            ],
            'wall_c' => [
                'food' => 600,  'wood' => 0,    'stone' => 8000, 'metal' => 0,    'coin' => 0,   'pop' => 0, 'time' => 600,  'space' => 5, 'is_wall' => true,
                'cond' => ['place_type' => 13, 'place_lvl' => 5, 'study' => 'safe', 'study_lvl' => 6]
            ],
        ];

        if (!isset($unitDefs[$armyType])) {
            sendJson(['state' => 'error_4']);
        }

        $u = $unitDefs[$armyType];

        $player = requireAuthenticatedPlayer($db);
        $playerId = (int)$player['id_player'];
        $db->beginTransaction();
        $reject = static function(array $response) use ($db): void {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            sendJson($response);
        };

        try {
            $stmt = $db->prepare("SELECT * FROM city WHERE id_city = ? AND id_player = ? FOR UPDATE");
            $stmt->execute([$cityId, $playerId]);
            $city = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$city) {
                $reject(['state' => 'error_no_city']);
            }

            ProductionService::recalculateCityProduction($db, $cityId);
            $stmt->execute([$cityId, $playerId]);
            $city = $stmt->fetch(PDO::FETCH_ASSOC);

            // Check the selected building and its level, matching the client's per-building queue limit.
            $reqBType = (int)$u['cond']['place_type'];
            $reqBLvl = (int)$u['cond']['place_lvl'];
            if ($place === '') {
                $reject(['state' => 'error_7_1', 'msg' => 'مستوى المبنى غير كافى']);
            }
            $bStmt = $db->prepare(
                "SELECT `lvl` FROM `city_building` WHERE `id_city` = ? AND `place` = ? AND `type` = ? FOR UPDATE"
            );
            $bStmt->execute([$cityId, $place, $reqBType]);
            $buildingLvl = (int)$bStmt->fetchColumn();
            if ($buildingLvl < $reqBLvl) {
                $reject(['state' => 'error_7_1', 'msg' => 'مستوى المبنى غير كافى']);
            }

            // Enforce the original client rule on the server: at most 10 queues and no more than building level.
            $queueStmt = $db->prepare(
                "SELECT `details` FROM `timed_task` WHERE `id_city` = ? AND `task_type` IN ('army', 'wall')"
            );
            $queueStmt->execute([$cityId]);
            $workingCount = 0;
            foreach ($queueStmt->fetchAll(PDO::FETCH_COLUMN) as $detailsJson) {
                $details = is_string($detailsJson) ? json_decode($detailsJson, true) : null;
                if (is_array($details) && ($details['place'] ?? null) === $place) {
                    $workingCount++;
                }
            }
            if ($workingCount >= min(10, $buildingLvl)) {
                $reject(['state' => 'error_4']);
            }

            // Check Study Prerequisites
            $studyCol = preg_replace('/[^a-zA-Z0-9_]/', '', $u['cond']['study']);
            $reqSLvl = (int)$u['cond']['study_lvl'];
            $eduStmt = $db->prepare("SELECT `{$studyCol}` FROM player_edu WHERE id_player = ?");
            $eduStmt->execute([$playerId]);
            $hasSLvl = (int)$eduStmt->fetchColumn();

            if ($hasSLvl < $reqSLvl) {
                $reject(['state' => 'error_6_2', 'msg' => 'مستوى الدراسة غير كافى']);
            }

            // Wall space and population are dynamic caps, not fixed batch-size limits.
            if ($u['is_wall']) {
                $usedSpace = ((int)($city['wall_a'] ?? 0)) +
                    ((int)($city['wall_b'] ?? 0) * 3) +
                    ((int)($city['wall_c'] ?? 0) * 5);
                $availableSpace = max(0, max(5000, (int)($city['wall_cap'] ?? 5000)) - $usedSpace);
                if ($amount > intdiv($availableSpace, $u['space'])) {
                    $reject(['state' => 'error_1']);
                }
            } elseif ($u['pop'] > 0 && $amount > intdiv((int)$city['pop'], $u['pop'])) {
                $reject(['state' => 'error_2']);
            }

            $costFood = $u['food'] * $amount;
            $costWood = $u['wood'] * $amount;
            $costStone = $u['stone'] * $amount;
            $costMetal = $u['metal'] * $amount;
            $costCoin = $u['coin'] * $amount;
            $costPop = $u['pop'] * $amount;
            if (
                (int)$city['food'] < $costFood ||
                (int)$city['wood'] < $costWood ||
                (int)$city['stone'] < $costStone ||
                (int)$city['metal'] < $costMetal ||
                (int)$city['coin'] < $costCoin
            ) {
                $reject(['state' => 'error_5', 'msg' => 'الموارد غير كافية']);
            }

            $upStmt = $db->prepare("UPDATE `city` SET
                `food` = `food` - ?,
                `wood` = `wood` - ?,
                `stone` = `stone` - ?,
                `metal` = `metal` - ?,
                `coin` = `coin` - ?,
                `pop` = `pop` - ?
                WHERE `id_city` = ? AND `id_player` = ?");
            $upStmt->execute([$costFood, $costWood, $costStone, $costMetal, $costCoin, $costPop, $cityId, $playerId]);

            $duration = max(10, min(1800, (int)ceil(($amount * $u['time']) / 25)));
            $now = time();
            $arrive = $now + $duration;
            $taskType = $u['is_wall'] ? 'wall' : 'army';

            $stmt = $db->prepare("INSERT INTO `timed_task` (`id_city`, `id_player`, `task_type`, `item_id`, `lvl_to`, `start_time`, `time_arrive`, `details`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$cityId, $playerId, $taskType, $armyType, $amount, $now, $arrive, json_encode(['place' => $place])]);
            $taskId = (int)$db->lastInsertId();

            $db->prepare("INSERT INTO `army_training` (`id_city`, `id_player`, `army_type`, `amount`, `start_time`, `end_time`, `done`) VALUES (?, ?, ?, ?, ?, ?, 0)")
               ->execute([$cityId, $playerId, $armyType, $amount, $now, $arrive]);

            ProductionService::recalculateCityProduction($db, $cityId);

            $cStmt = $db->prepare("SELECT * FROM city WHERE id_city = ?");
            $cStmt->execute([$cityId]);
            $updatedCity = $cStmt->fetch(PDO::FETCH_ASSOC);
            foreach ($updatedCity as $k => $v) {
                if (is_numeric($v)) {
                    $updatedCity[$k] = (int)$v;
                }
            }
            $db->commit();

            sendJson([
                'state'       => 'ok',
                'City'        => $updatedCity,
                'armyBatches' => [
                    [
                        'id'         => $taskId,
                        'id_city'    => $cityId,
                        'place'      => $place,
                        'army_type'  => $armyType,
                        'amount'     => $amount,
                        'time_start' => $now,
                        'time_end'   => $arrive,
                        'acce'       => 0
                    ]
                ]
            ]);
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    public static function speedUpArmy($db) {
        $taskId    = isset($_REQUEST['idTask']) ? (int)$_REQUEST['idTask'] : (int)($_REQUEST['idBatch'] ?? 0);
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

                // Military Recruitment & Troop Training Speedup Items
                $speedItems = [
                    'train_acce_15'  => ['sec' => 900,   'pct' => 0.0],  // 15 minutes
                    'train_acce_1h'  => ['sec' => 3600,  'pct' => 0.0],  // 1 hour
                    'train_acce_3h'  => ['sec' => 10800, 'pct' => 0.0],  // 3 hours
                    'train_acce_30'  => ['sec' => 0,     'pct' => 0.30], // 30% reduction
                    'train_acce_30p' => ['sec' => 0,     'pct' => 0.30], // 30% reduction
                    'train_acce_50'  => ['sec' => 0,     'pct' => 0.50], // 50% reduction
                    'coach'          => ['sec' => 0,     'pct' => 0.30], // 30% reduction
                    'speed_15m'      => ['sec' => 900,   'pct' => 0.0],  // 15 minutes
                    'speed_1h'       => ['sec' => 3600,  'pct' => 0.0],  // 1 hour
                    'speed_2_5h'     => ['sec' => 9000,  'pct' => 0.0],  // 2.5 hours
                    'speed_8h'       => ['sec' => 28800, 'pct' => 0.0],  // 8 hours
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
                $db->prepare("UPDATE `army_training` SET `end_time` = ? WHERE `id_city` = ? AND `army_type` = ? AND `done` = 0")
                   ->execute([$newArrive, $task['id_city'], $task['item_id']]);

                if ($newArrive <= $now) {
                    TaskService::processCompletedTasks($db);
                }
            }
        }
        sendJson(['state' => 'ok', 'reduced' => $reduceSec, 'armyBatches' => []]);
    }

    public static function cancelBatch($db) {
        $taskId = isset($_REQUEST['idTask']) ? (int)$_REQUEST['idTask'] : (isset($_REQUEST['idWorking']) ? (int)$_REQUEST['idWorking'] : 0);
        $cityId = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 1;
        if ($taskId > 0) {
            $db->prepare("DELETE FROM timed_task WHERE id_task = ? AND id_city = ?")->execute([$taskId, $cityId]);
            $db->prepare("UPDATE city SET food = LEAST(food_cap, food + 500) WHERE id_city = ?")->execute([$cityId]);
        }
        $freshCity = $db->query("SELECT * FROM city WHERE id_city = $cityId")->fetch();
        foreach ($freshCity as $k => $v) { if (is_numeric($v)) $freshCity[$k] = (int)$v; }
        sendJson(['state' => 'ok', 'City' => $freshCity, 'cityRes' => $freshCity]);
    }

    public static function speedUpStudyTask($db) {
        require_once __DIR__ . '/CityController.php';
        CityController::speedUpStudy($db);
    }
}
