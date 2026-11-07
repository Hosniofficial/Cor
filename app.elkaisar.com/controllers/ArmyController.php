<?php
// ArmyController.php - Handles army recruitment, army building batches, and troop speed-ups

require_once __DIR__ . '/../services/TaskService.php';

class ArmyController {
    public static function handle($uri, $db) {
        switch ($uri) {
            case '/api/AArmyBuild/buildArmy':
                self::buildArmy($db);
                return true;
            case '/api/AArmyBuild/speedUpArmy':
            case '/api/ACityArmy/speedUpArmy':
                self::speedUpArmy($db);
                return true;
        }
        return false;
    }

    public static function buildArmy($db) {
        $cityId    = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 1;
        $armyType  = isset($_REQUEST['armyType']) ? trim($_REQUEST['armyType']) : 'army_a';
        $amount    = isset($_REQUEST['amount']) ? (int)$_REQUEST['amount'] : 0;
        $place     = isset($_REQUEST['buildingPlace']) ? trim($_REQUEST['buildingPlace']) : '';

        if ($amount <= 0) {
            sendJson(['state' => 'error_4']);
        }

        $stmt = $db->prepare("SELECT * FROM city WHERE id_city = ?");
        $stmt->execute([$cityId]);
        $city = $stmt->fetch();
        $playerId = (int)($city['id_player'] ?? 1);

        $duration = max(15, min(900, (int)ceil($amount * 2)));
        $now = time();
        $arrive = $now + $duration;

        $stmt = $db->prepare("UPDATE `city` SET `food` = GREATEST(0, `food` - ?), `pop` = GREATEST(0, `pop` - ?) WHERE `id_city` = ?");
        $stmt->execute([$amount * 10, $amount, $cityId]);

        $stmt = $db->prepare("INSERT INTO `timed_task` (`id_city`, `id_player`, `task_type`, `item_id`, `lvl_to`, `start_time`, `time_arrive`, `details`) VALUES (?, ?, 'army', ?, ?, ?, ?, ?)");
        $stmt->execute([$cityId, $playerId, $armyType, $amount, $now, $arrive, json_encode(['place' => $place])]);
        $taskId = (int)$db->lastInsertId();

        $cStmt = $db->prepare("SELECT * FROM city WHERE id_city = ?");
        $cStmt->execute([$cityId]);
        $updatedCity = $cStmt->fetch();
        foreach ($updatedCity as $k => $v) { if (is_numeric($v)) $updatedCity[$k] = (int)$v; }

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
    }

    public static function speedUpArmy($db) {
        $taskId = isset($_REQUEST['idTask']) ? (int)$_REQUEST['idTask'] : 0;
        if ($taskId > 0) {
            $stmt = $db->prepare("UPDATE `timed_task` SET `time_arrive` = UNIX_TIMESTAMP() WHERE `id_task` = ?");
            $stmt->execute([$taskId]);
            TaskService::processCompletedTasks($db);
        }
        sendJson(['state' => 'ok', 'armyBatches' => []]);
    }
}
