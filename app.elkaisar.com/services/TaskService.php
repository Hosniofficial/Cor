<?php
// TaskService.php - Processes and finishes completed timed tasks (buildings, studies, armies, jobs)

require_once __DIR__ . '/ProductionService.php';

class TaskService {
    public static function processCompletedTasks($db) {
        $now = time();
        $stmt = $db->prepare("SELECT * FROM `timed_task` WHERE `time_arrive` <= ?");
        $stmt->execute([$now]);
        $done = $stmt->fetchAll() ?: [];

        foreach ($done as $t) {
            $taskId   = (int)$t['id_task'];
            $cityId   = (int)$t['id_city'];
            $playerId = (int)$t['id_player'];
            $type     = $t['task_type'];
            $itemId   = $t['item_id'];
            $lvlTo    = (int)$t['lvl_to'];
            $details  = !empty($t['details']) ? json_decode($t['details'], true) : [];

            if ($type === 'building') {
                $bType = isset($details['type']) ? (int)$details['type'] : 0;
                if ($bType > 0) {
                    $up = $db->prepare("INSERT INTO `city_building` (`id_city`, `place`, `type`, `lvl`) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE `type` = VALUES(`type`), `lvl` = VALUES(`lvl`)");
                    $up->execute([$cityId, $itemId, $bType, $lvlTo]);
                } else {
                    $up = $db->prepare("UPDATE `city_building` SET `lvl` = ? WHERE `id_city` = ? AND `place` = ?");
                    $up->execute([$lvlTo, $cityId, $itemId]);
                }
                ProductionService::recalculateCityProduction($db, $cityId);
            } elseif ($type === 'study') {
                $validStudies = ['farming', 'wooding', 'stoning', 'mining', 'accounting', 'storing', 'building', 'scholership', 'maintenace', 'infantry', 'riding', 'army', 'spying', 'leader', 'safe', 'medicine', 'logistic', 'navigating', 'supplying'];
                if (in_array($itemId, $validStudies)) {
                    $up = $db->prepare("UPDATE `player_edu` SET `{$itemId}` = ? WHERE `id_player` = ?");
                    $up->execute([$lvlTo, $playerId]);
                }
                ProductionService::recalculateCityProduction($db, $cityId);
            } elseif ($type === 'army') {
                $validArmies = ['army_a', 'army_b', 'army_c', 'army_d', 'army_e', 'army_f'];
                if (in_array($itemId, $validArmies)) {
                    $up = $db->prepare("UPDATE `city` SET `{$itemId}` = `{$itemId}` + ? WHERE `id_city` = ?");
                    $up->execute([$lvlTo, $cityId]);
                }
                ProductionService::recalculateCityProduction($db, $cityId);
            } elseif ($type === 'jop') {
                $validJops = ['farm' => 'food', 'wood' => 'wood', 'stone' => 'stone', 'mine' => 'metal'];
                $col = $validJops[$itemId] ?? $itemId;
                if (in_array($col, ['food', 'wood', 'stone', 'metal'])) {
                    $up = $db->prepare("INSERT INTO `city_jop` (`id_city`, `id_player`, `{$col}`) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE `{$col}` = `{$col}` + VALUES(`{$col}`)");
                    $up->execute([$cityId, $playerId, $lvlTo]);
                }
                ProductionService::recalculateCityProduction($db, $cityId);
            }

            // Delete completed task
            $del = $db->prepare("DELETE FROM `timed_task` WHERE `id_task` = ?");
            $del->execute([$taskId]);
        }
    }
}

// Global procedural wrapper for backwards compatibility
if (!function_exists('processCompletedTasks')) {
    function processCompletedTasks($db) {
        TaskService::processCompletedTasks($db);
    }
}
