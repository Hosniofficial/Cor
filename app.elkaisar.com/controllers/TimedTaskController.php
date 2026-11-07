<?php
// TimedTaskController.php - Handles active countdown lists for buildings, studies, armies, and jobs

class TimedTaskController {
    public static function handle($uri, $db) {
        switch ($uri) {
            case '/api/ATimedTask/getCityBuildingTasks':
                self::getCityBuildingTasks($db);
                return true;
            case '/api/ATimedTask/getCityStudyTasks':
                self::getCityStudyTasks($db);
                return true;
            case '/api/ATimedTask/getCityArmyTasks':
                self::getCityArmyTasks($db);
                return true;
            case '/api/ATimedTask/getCityJopTasks':
                self::getCityJopTasks($db);
                return true;
        }
        return false;
    }

    public static function getCityBuildingTasks($db) {
        $cityId = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 1;
        $stmt = $db->prepare("SELECT * FROM `timed_task` WHERE `task_type` = 'building' AND `id_city` = ?");
        $stmt->execute([$cityId]);
        $tasks = $stmt->fetchAll() ?: [];
        $list = [];
        foreach ($tasks as $t) {
            $details = !empty($t['details']) ? json_decode($t['details'], true) : [];
            $list[] = [
                'id'           => (int)$t['id_task'],
                'id_city'      => (int)$t['id_city'],
                'type'         => isset($details['type']) ? (int)$details['type'] : 1,
                'place'        => $t['item_id'],
                'lvl_to'       => (int)$t['lvl_to'],
                'time_start'   => (int)$t['start_time'],
                'time_end'     => (int)$t['time_arrive'],
                'time_end_org' => (int)$t['time_arrive']
            ];
        }
        sendJson($list);
    }

    public static function getCityStudyTasks($db) {
        $cityId = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 1;
        $stmt = $db->prepare("SELECT * FROM `timed_task` WHERE `task_type` = 'study' AND `id_city` = ?");
        $stmt->execute([$cityId]);
        $tasks = $stmt->fetchAll() ?: [];
        $list = [];
        foreach ($tasks as $t) {
            $details = !empty($t['details']) ? json_decode($t['details'], true) : [];
            $list[] = [
                'id'           => (int)$t['id_task'],
                'id_city'      => (int)$t['id_city'],
                'study'        => $t['item_id'],
                'study_in'     => isset($details['study_in']) ? $details['study_in'] : 'academy',
                'lvl_to'       => (int)$t['lvl_to'],
                'time_start'   => (int)$t['start_time'],
                'time_end'     => (int)$t['time_arrive'],
                'time_end_org' => (int)$t['time_arrive']
            ];
        }
        sendJson($list);
    }

    public static function getCityArmyTasks($db) {
        $cityId = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 1;
        $stmt = $db->prepare("SELECT * FROM `timed_task` WHERE `task_type` = 'army' AND `id_city` = ?");
        $stmt->execute([$cityId]);
        $tasks = $stmt->fetchAll() ?: [];
        $list = [];
        foreach ($tasks as $t) {
            $details = !empty($t['details']) ? json_decode($t['details'], true) : [];
            $list[] = [
                'id'         => (int)$t['id_task'],
                'id_city'    => (int)$t['id_city'],
                'place'      => isset($details['place']) ? $details['place'] : '',
                'army_type'  => $t['item_id'],
                'amount'     => (int)$t['lvl_to'],
                'time_start' => (int)$t['start_time'],
                'time_end'   => (int)$t['time_arrive'],
                'acce'       => 0
            ];
        }
        sendJson($list);
    }

    public static function getCityJopTasks($db) {
        $cityId = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 1;
        $stmt = $db->prepare("SELECT * FROM `timed_task` WHERE `task_type` = 'jop' AND `id_city` = ?");
        $stmt->execute([$cityId]);
        $tasks = $stmt->fetchAll() ?: [];
        $list = [];
        foreach ($tasks as $t) {
            $details = !empty($t['details']) ? json_decode($t['details'], true) : [];
            $list[] = [
                'id'           => (int)$t['id_task'],
                'id_city'      => (int)$t['id_city'],
                'jop_place'    => isset($details['jop_place']) ? $details['jop_place'] : $t['item_id'],
                'lvl_to'       => (int)$t['lvl_to'],
                'time_start'   => (int)$t['start_time'],
                'time_end'     => (int)$t['time_arrive'],
                'time_end_org' => (int)$t['time_arrive']
            ];
        }
        sendJson($list);
    }
}
