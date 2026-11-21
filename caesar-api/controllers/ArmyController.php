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
            $taskId = $_REQUEST['idTask'] ?? 0;
            $task = requirePlayerOwnedTask($db, $player, $taskId);
            if (($task['task_type'] ?? '') !== 'study') {
                http_response_code(403);
                sendJson(['state' => 'error_auth']);
            }
            $city = requirePlayerOwnedCity($db, $player, (int)$task['id_city']);
            $_REQUEST['idCity'] = (int)$city['id_city'];
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
        $divideBy  = isset($_REQUEST['divideBy']) ? trim($_REQUEST['divideBy']) : 'none';

        if ($amount <= 0) {
            sendJson(['state' => 'error_4']);
        }
        if (!in_array($divideBy, ['none', 'time', 'amount'], true)) {
            sendJson(['state' => 'error_3']);
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

            $reqBType = (int)$u['cond']['place_type'];
            $reqBLvl = (int)$u['cond']['place_lvl'];
            if ($place === '') {
                $reject(['state' => 'error_7_1', 'msg' => 'مستوى المبنى غير كافى']);
            }
            $buildingStmt = $db->prepare(
                "SELECT `place`, `lvl` FROM `city_building` WHERE `id_city` = ? AND `type` = ? ORDER BY `place` FOR UPDATE"
            );
            $buildingStmt->execute([$cityId, $reqBType]);
            $buildings = $buildingStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $selectedBuildingExists = false;
            $maxBuildingLvl = 0;
            foreach ($buildings as $building) {
                if ((string)$building['place'] === $place) {
                    $selectedBuildingExists = true;
                }
                $maxBuildingLvl = max($maxBuildingLvl, (int)$building['lvl']);
            }
            if (!$selectedBuildingExists) {
                $reject(['state' => 'error_7_1', 'msg' => 'مستوى المبنى غير كافى']);
            }

            $queueStmt = $db->prepare(
                "SELECT `details`, `time_arrive` FROM `timed_task` WHERE `id_city` = ? AND `task_type` IN ('army', 'wall') FOR UPDATE"
            );
            $queueStmt->execute([$cityId]);
            $now = time();
            $queueCounts = [];
            foreach ($queueStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $queuedTask) {
                if ((int)$queuedTask['time_arrive'] <= $now) {
                    continue;
                }
                $details = !empty($queuedTask['details']) ? json_decode($queuedTask['details'], true) : [];
                $queuedPlace = is_array($details) ? (string)($details['place'] ?? '') : '';
                if ($queuedPlace !== '') {
                    $queueCounts[$queuedPlace] = ($queueCounts[$queuedPlace] ?? 0) + 1;
                }
            }

            $studyCol = preg_replace('/[^a-zA-Z0-9_]/', '', $u['cond']['study']);
            $reqSLvl = (int)$u['cond']['study_lvl'];
            $eduStmt = $db->prepare("SELECT `{$studyCol}` FROM player_edu WHERE id_player = ?");
            $eduStmt->execute([$playerId]);
            $hasSLvl = (int)$eduStmt->fetchColumn();

            if ($hasSLvl < $reqSLvl) {
                $reject(['state' => 'error_6_2', 'msg' => 'مستوى الدراسة غير كافى']);
            }

            $availableBuildings = [];
            $qualifiedBuildingCount = 0;
            $selectedBuildingLvl = 0;
            foreach ($buildings as $building) {
                $buildingPlace = (string)$building['place'];
                $buildingLvl = (int)$building['lvl'];
                if ($buildingPlace === $place) {
                    $selectedBuildingLvl = $buildingLvl;
                }
                if ($buildingLvl < $reqBLvl) {
                    continue;
                }
                $qualifiedBuildingCount++;
                if ($divideBy === 'none' && $buildingPlace !== $place) {
                    continue;
                }

                $workingCount = $queueCounts[$buildingPlace] ?? 0;
                if ($workingCount >= min(10, $buildingLvl)) {
                    continue;
                }
                if ($divideBy === 'time' && $workingCount > 0) {
                    continue;
                }
                $availableBuildings[] = [
                    'place' => $buildingPlace,
                    'lvl' => $buildingLvl
                ];
            }
            if ($divideBy === 'none' && $selectedBuildingLvl < $reqBLvl) {
                $reject(['state' => 'error_7_1', 'msg' => 'مستوى المبنى غير كافى']);
            }
            if ($qualifiedBuildingCount === 0) {
                $reject(['state' => 'error_7_1', 'msg' => 'لا يوجد مبنى بالمستوى المطلوب']);
            }
            if (!$availableBuildings) {
                if ($divideBy === 'time') {
                    $reject([
                        'state' => 'error_no_available_building',
                        'msg' => 'لا توجد ثكنة متاحة بدون دفعات تصنيع نشطة'
                    ]);
                }
                if ($divideBy === 'amount') {
                    $reject(['state' => 'error_7']);
                }
                $reject(['state' => 'error_4']);
            }

            $assignments = [];
            if ($divideBy === 'none') {
                $assignments[$place] = $amount;
            } else {
                $buildingCount = count($availableBuildings);
                $amountPerBuilding = intdiv($amount, $buildingCount);
                $remainder = $amount % $buildingCount;
                foreach ($availableBuildings as $index => $building) {
                    $buildingAmount = $amountPerBuilding + ($index < $remainder ? 1 : 0);
                    if ($buildingAmount > 0) {
                        $assignments[$building['place']] = $buildingAmount;
                    }
                }
            }

            // Wall space and population are dynamic caps, not fixed batch-size limits.
            if ($u['is_wall']) {
                $wallCapacity = max(0, $maxBuildingLvl * 5000);
                $builtSpace = ((int)($city['wall_a'] ?? 0) * 1) +
                    ((int)($city['wall_b'] ?? 0) * 3) +
                    ((int)($city['wall_c'] ?? 0) * 5);
                $wallTaskStmt = $db->prepare(
                    "SELECT `item_id`, `lvl_to` FROM `timed_task` WHERE `id_city` = ? AND `task_type` = 'wall'"
                );
                $wallTaskStmt->execute([$cityId]);
                $queuedSpace = 0;
                $spaceWeights = ['wall_a' => 1, 'wall_b' => 3, 'wall_c' => 5];
                foreach ($wallTaskStmt->fetchAll(PDO::FETCH_ASSOC) as $wallTask) {
                    $queuedType = (string)$wallTask['item_id'];
                    $queuedAmount = max(0, (int)$wallTask['lvl_to']);
                    $spaceWeight = $spaceWeights[$queuedType] ?? ($unitDefs[$queuedType]['space'] ?? 1);
                    $queuedSpace += $queuedAmount * $spaceWeight;
                }
                $totalUsedSpace = $builtSpace + $queuedSpace;
                $availableSpace = max(0, $wallCapacity - $totalUsedSpace);
                $unitSpace = (int)($u['space'] ?? 1);
                $maxBuildable = ($unitSpace > 0) ? intdiv($availableSpace, $unitSpace) : 0;
                if ($amount > $maxBuildable || $availableSpace <= 0) {
                    $reject(['state' => 'error_1', 'msg' => 'مساحة السور لا تكفي للعدد المطلوب']);
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

            $taskType = $u['is_wall'] ? 'wall' : 'army';

            $stmt = $db->prepare("INSERT INTO `timed_task` (`id_city`, `id_player`, `task_type`, `item_id`, `lvl_to`, `start_time`, `time_arrive`, `details`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $trainingStmt = $db->prepare(
                "INSERT INTO `army_training` (`id_city`, `id_player`, `army_type`, `amount`, `start_time`, `end_time`, `done`) VALUES (?, ?, ?, ?, ?, ?, 0)"
            );
            $armyBatches = [];
            foreach ($assignments as $batchPlace => $batchAmount) {
                $duration = max((int)$u['time'], $batchAmount * (int)$u['time']);
                $arrive = $now + $duration;
                $detailsJson = json_encode(['place' => $batchPlace], JSON_UNESCAPED_UNICODE);
                if (!is_string($detailsJson)) {
                    throw new RuntimeException('Failed to encode army task details');
                }
                $stmt->execute([$cityId, $playerId, $taskType, $armyType, $batchAmount, $now, $arrive, $detailsJson]);
                $taskId = (int)$db->lastInsertId();
                $trainingStmt->execute([$cityId, $playerId, $armyType, $batchAmount, $now, $arrive]);
                $armyBatches[] = [
                    'id'         => $taskId,
                    'id_city'    => $cityId,
                    'place'      => $batchPlace,
                    'army_type'  => $armyType,
                    'amount'     => $batchAmount,
                    'time_start' => $now,
                    'time_end'   => $arrive,
                    'acce'       => 0
                ];
            }

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
                'armyBatches' => $armyBatches
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
        $speedItems = [
            'train_acce_15'  => ['sec' => 900,   'pct' => 0.0],
            'train_acce_1h'  => ['sec' => 3600,  'pct' => 0.0],
            'train_acce_3h'  => ['sec' => 10800, 'pct' => 0.0],
            'train_acce_30'  => ['sec' => 0,     'pct' => 0.30],
            'train_acce_30p' => ['sec' => 0,     'pct' => 0.30],
            'train_acce_50'  => ['sec' => 0,     'pct' => 0.50],
            'wall_acce'      => ['sec' => 0,     'pct' => 0.50],
            'coach'          => ['sec' => 0,     'pct' => 0.30],
            'speed_15m'      => ['sec' => 900,   'pct' => 0.0],
            'speed_1h'       => ['sec' => 3600,  'pct' => 0.0],
            'speed_2_5h'     => ['sec' => 9000,  'pct' => 0.0],
            'speed_8h'       => ['sec' => 28800, 'pct' => 0.0],
        ];

        if ($taskId <= 0) {
            sendJson(['state' => 'error_task_not_found']);
        }
        if (!isset($speedItems[$itemToUse])) {
            sendJson(['state' => 'error_invalid_speed_item']);
        }

        $db->beginTransaction();
        $reject = static function(array $response) use ($db): void {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            sendJson($response);
        };

        try {
            $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
            $lockClause = $driver === 'sqlite' ? '' : ' FOR UPDATE';
            $taskStmt = $db->prepare("SELECT * FROM `timed_task` WHERE `id_task` = ?" . $lockClause);
            $taskStmt->execute([$taskId]);
            $task = $taskStmt->fetch(PDO::FETCH_ASSOC);
            if (!$task || !in_array($task['task_type'], ['army', 'wall'], true)) {
                $reject(['state' => 'error_task_not_found']);
            }

            $taskDetails = !empty($task['details']) ? json_decode($task['details'], true) : [];
            $taskDetails = is_array($taskDetails) ? $taskDetails : [];
            if (!empty($taskDetails['acce'])) {
                $reject(['state' => 'error_already_sped_up']);
            }

            $isWallTask = $task['task_type'] === 'wall';
            if (($itemToUse === 'wall_acce') !== $isWallTask) {
                $reject(['state' => 'error_invalid_speed_item']);
            }

            $cityId = (int)$task['id_city'];
            $playerId = (int)$task['id_player'];
            $matchingPlaces = [];
            if ($isWallTask) {
                $queueStmt = $db->prepare(
                    "SELECT * FROM `timed_task` WHERE `id_city` = ? AND `task_type` = 'wall'" . $lockClause
                );
                $queueStmt->execute([$cityId]);
            } else {
                $place = (string)($taskDetails['place'] ?? '');
                $buildingTypeStmt = $db->prepare(
                    "SELECT `type` FROM `city_building` WHERE `id_city` = ? AND `place` = ?"
                );
                $buildingTypeStmt->execute([$cityId, $place]);
                $buildingType = (int)$buildingTypeStmt->fetchColumn();
                if ($place === '' || $buildingType <= 0) {
                    $reject(['state' => 'error_task_not_found']);
                }

                $placeStmt = $db->prepare(
                    "SELECT `place` FROM `city_building` WHERE `id_city` = ? AND `type` = ?"
                );
                $placeStmt->execute([$cityId, $buildingType]);
                $matchingPlaces = array_fill_keys($placeStmt->fetchAll(PDO::FETCH_COLUMN), true);

                $queueStmt = $db->prepare(
                    "SELECT * FROM `timed_task` WHERE `id_city` = ? AND `task_type` = 'army'" . $lockClause
                );
                $queueStmt->execute([$cityId]);
            }

            $candidateTasks = [];
            foreach ($queueStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $candidate) {
                $details = !empty($candidate['details']) ? json_decode($candidate['details'], true) : [];
                $details = is_array($details) ? $details : [];
                if ($isWallTask || isset($matchingPlaces[(string)($details['place'] ?? '')])) {
                    $candidate['details_data'] = $details;
                    $candidateTasks[] = $candidate;
                }
            }

            $acceleratedTask = false;
            foreach ($candidateTasks as $candidate) {
                if ((int)$candidate['id_task'] === $taskId) {
                    $acceleratedTask = $candidate;
                    break;
                }
            }
            if ($acceleratedTask === false) {
                $reject(['state' => 'error_task_not_found']);
            }

            $itemStmt = $db->prepare(
                "UPDATE `player_item` SET `amount` = `amount` - 1 " .
                "WHERE `id_player` = ? AND `id_item` = ? AND `amount` > 0"
            );
            $itemStmt->execute([$playerId, $itemToUse]);
            if ($itemStmt->rowCount() !== 1) {
                $reject(['state' => 'error_no_item', 'msg' => 'لا تمتلك المادة المحددة للتسريع']);
            }

            $now = time();
            $speed = $speedItems[$itemToUse];
            $updatedTaskCount = 0;
            $affectedBatchCount = 0;
            foreach ($candidateTasks as $candidate) {
                $details = $candidate['details_data'];
                if (!empty($details['acce'])) {
                    continue;
                }

                $oldArrive = (int)$candidate['time_arrive'];
                $remaining = max(0, $oldArrive - $now);
                $reduction = $speed['pct'] > 0
                    ? (int)ceil($remaining * $speed['pct'])
                    : (int)$speed['sec'];
                $newArrive = max($now, $oldArrive - $reduction);
                $details['acce'] = 1;
                $detailsJson = json_encode($details, JSON_UNESCAPED_UNICODE);
                if (!is_string($detailsJson)) {
                    throw new RuntimeException('Failed to encode accelerated army task details');
                }

                $db->prepare(
                    "UPDATE `timed_task` SET `time_arrive` = ?, `details` = ? WHERE `id_task` = ?"
                )->execute([$newArrive, $detailsJson, (int)$candidate['id_task']]);
                $db->prepare(
                    "UPDATE `army_training` SET `end_time` = ? " .
                    "WHERE `id_city` = ? AND `army_type` = ? AND `amount` = ? AND `start_time` = ? AND `done` = 0"
                )->execute([
                    $newArrive,
                    $cityId,
                    $candidate['item_id'],
                    (int)$candidate['lvl_to'],
                    (int)$candidate['start_time']
                ]);
                $affectedBatchCount++;
                if ($newArrive <= $now) {
                    $updatedTaskCount++;
                }
            }

            if ($updatedTaskCount > 0) {
                TaskService::processCompletedTasks($db);
            }

            $listStmt = $db->prepare(
                "SELECT * FROM `timed_task` WHERE `id_city` = ? AND `task_type` IN ('army', 'wall')"
            );
            $listStmt->execute([$cityId]);
            $armyBatches = [];
            foreach ($listStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $activeTask) {
                $details = !empty($activeTask['details']) ? json_decode($activeTask['details'], true) : [];
                $details = is_array($details) ? $details : [];
                $armyBatches[] = [
                    'id'         => (int)$activeTask['id_task'],
                    'id_city'    => (int)$activeTask['id_city'],
                    'place'      => isset($details['place']) ? $details['place'] : '',
                    'army_type'  => $activeTask['item_id'],
                    'amount'     => (int)$activeTask['lvl_to'],
                    'time_start' => (int)$activeTask['start_time'],
                    'time_end'   => (int)$activeTask['time_arrive'],
                    'acce'       => !empty($details['acce']) ? 1 : 0
                ];
            }

            $db->commit();
            sendJson([
                'state' => 'ok',
                'affectedBatches' => $affectedBatchCount,
                'completedBatches' => $updatedTaskCount,
                'armyBatches' => $armyBatches
            ]);
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    public static function cancelBatch($db) {
        $player = requireAuthenticatedPlayer($db);
        $playerId = (int)$player['id_player'];
        $taskId = isset($_REQUEST['idTask']) ? (int)$_REQUEST['idTask'] : (isset($_REQUEST['idWorking']) ? (int)$_REQUEST['idWorking'] : 0);
        $cityId = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 1;
        requirePlayerOwnedCity($db, $player, $cityId);
        $refund = 0;
        if ($taskId > 0) {
            $db->beginTransaction();
            try {
                $delete = $db->prepare("DELETE FROM timed_task WHERE id_task = ? AND id_city = ?");
                $delete->execute([$taskId, $cityId]);
                if ($delete->rowCount() === 1) {
                    $credited = ResourceService::creditCityUpToLimit($db, $cityId, $playerId, ['food' => 500]);
                    $refund = $credited['food'] ?? 0;
                }
                $db->commit();
            } catch (Throwable $error) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $error;
            }
        }
        $freshStmt = $db->prepare("SELECT * FROM city WHERE id_city = ? AND id_player = ?");
        $freshStmt->execute([$cityId, $playerId]);
        $freshCity = $freshStmt->fetch();
        foreach ($freshCity as $k => $v) { if (is_numeric($v)) $freshCity[$k] = (int)$v; }
        sendJson(['state' => 'ok', 'City' => $freshCity, 'cityRes' => $freshCity, 'refund' => ['food' => $refund]]);
    }

    public static function speedUpStudyTask($db) {
        require_once __DIR__ . '/CityController.php';
        CityController::speedUpStudy($db);
    }
}
