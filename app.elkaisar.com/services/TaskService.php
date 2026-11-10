<?php
// TaskService.php - Processes and finishes completed timed tasks (buildings, studies, armies, jobs)

require_once __DIR__ . '/ProductionService.php';

class TaskService {
    public static function processCompletedTasks($db) {
        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) {
            $db->beginTransaction();
        }

        try {
            $now = time();
            $stmt = $db->prepare("SELECT * FROM `timed_task` WHERE `time_arrive` <= ? ORDER BY `id_task` FOR UPDATE");
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
                if ($bType <= 0) {
                    $bStmt = $db->prepare("SELECT `type` FROM `city_building` WHERE `id_city` = ? AND `place` = ?");
                    $bStmt->execute([$cityId, $itemId]);
                    $bType = (int)$bStmt->fetchColumn();
                }

                if ($bType > 0) {
                    $up = $db->prepare("INSERT INTO `city_building` (`id_city`, `place`, `type`, `lvl`) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE `type` = VALUES(`type`), `lvl` = VALUES(`lvl`)");
                    $up->execute([$cityId, $itemId, $bType, $lvlTo]);
                } else {
                    $up = $db->prepare("UPDATE `city_building` SET `lvl` = ? WHERE `id_city` = ? AND `place` = ?");
                    $up->execute([$lvlTo, $cityId, $itemId]);
                }

                // Provisional prestige policy; the client only confirms hero EXP is twice server-supplied prestige.
                // Strategic buildings (Palace 10, Wall 11, Worship 12, Academy 6, Embassy 13) give 25 per level
                // Standard production/military buildings give 15 per level
                $majorBuildings = [6, 10, 11, 12, 13];
                $buildingFactor = in_array($bType, $majorBuildings) ? 25 : 15;
                $prestigeGain = max(10, (int)round($lvlTo * $buildingFactor));
                $db->prepare("UPDATE `player` SET `prestige` = `prestige` + ? WHERE `id_player` = ?")
                   ->execute([$prestigeGain, $playerId]);

                // Mayor (Console Hero) gains 2x Prestige as Experience
                $cStmt = $db->prepare("SELECT `console` FROM `city` WHERE `id_city` = ?");
                $cStmt->execute([$cityId]);
                $consoleHeroId = (int)$cStmt->fetchColumn();
                if ($consoleHeroId > 0) {
                    $db->prepare("UPDATE `city_hero` SET `exp` = `exp` + ? WHERE `id_hero` = ?")
                       ->execute([$prestigeGain * 2, $consoleHeroId]);
                }

                ProductionService::recalculateCityProduction($db, $cityId);
            } elseif ($type === 'study') {
                $validStudies = ['farming', 'wooding', 'stoning', 'mining', 'accounting', 'storing', 'building', 'scholership', 'maintenace', 'infantry', 'riding', 'army', 'spying', 'leader', 'safe', 'medicine', 'logistic', 'navigating', 'supplying'];
                if (in_array($itemId, $validStudies)) {
                    $up = $db->prepare("UPDATE `player_edu` SET `{$itemId}` = ? WHERE `id_player` = ?");
                    $up->execute([$lvlTo, $playerId]);

                    // Provisional prestige policy; these per-level values are not verified by the available client code.
                    $militaryStudies = ['infantry', 'riding', 'army', 'maintenace', 'leader', 'safe', 'medicine', 'logistic'];
                    $studyFactor = in_array($itemId, $militaryStudies) ? 35 : 25;
                    $prestigeGain = max(20, (int)round($lvlTo * $studyFactor));
                    $db->prepare("UPDATE `player` SET `prestige` = `prestige` + ? WHERE `id_player` = ?")
                       ->execute([$prestigeGain, $playerId]);

                    // Mayor (Console Hero) gains 2x Prestige as Experience
                    $cStmt = $db->prepare("SELECT `console` FROM `city` WHERE `id_city` = ?");
                    $cStmt->execute([$cityId]);
                    $consoleHeroId = (int)$cStmt->fetchColumn();
                    if ($consoleHeroId <= 0) {
                        $pStmt = $db->prepare("SELECT `console` FROM `city` WHERE `id_player` = ? AND `console` > 0 LIMIT 1");
                        $pStmt->execute([$playerId]);
                        $consoleHeroId = (int)$pStmt->fetchColumn();
                    }
                    if ($consoleHeroId > 0) {
                        $db->prepare("UPDATE `city_hero` SET `exp` = `exp` + ? WHERE `id_hero` = ?")
                           ->execute([$prestigeGain * 2, $consoleHeroId]);
                    }
                }
                ProductionService::recalculateCityProduction($db, $cityId);
            } elseif ($type === 'army' || $type === 'wall') {
                $unitPrestige = [
                    'army_a' => 1,
                    'army_b' => 5,
                    'army_c' => 8,
                    'army_d' => 2,
                    'army_e' => 10,
                    'army_f' => 30,
                    'spies'  => 2,
                    'wall_a' => 1,
                    'wall_b' => 5,
                    'wall_c' => 12
                ];

                $validUnits = array_keys($unitPrestige);
                if (in_array($itemId, $validUnits)) {
                    $up = $db->prepare("UPDATE `city` SET `{$itemId}` = `{$itemId}` + ? WHERE `id_city` = ?");
                    $up->execute([$lvlTo, $cityId]);

                    // Provisional prestige policy; unit prestige values need confirmation from the original server.
                    $pYield = $unitPrestige[$itemId] ?? 1;
                    $prestigeGain = max(1, $lvlTo * $pYield);
                    $db->prepare("UPDATE `player` SET `prestige` = `prestige` + ? WHERE `id_player` = ?")
                       ->execute([$prestigeGain, $playerId]);

                    // Mayor (Console Hero) gains 2x Prestige as Experience
                    $cStmt = $db->prepare("SELECT `console` FROM `city` WHERE `id_city` = ?");
                    $cStmt->execute([$cityId]);
                    $consoleHeroId = (int)$cStmt->fetchColumn();
                    if ($consoleHeroId > 0) {
                        $db->prepare("UPDATE `city_hero` SET `exp` = `exp` + ? WHERE `id_hero` = ?")
                           ->execute([$prestigeGain * 2, $consoleHeroId]);
                    }
                }
                ProductionService::recalculateCityProduction($db, $cityId);
            } elseif ($type === 'jop') {
                $validJops = ['farm' => 'food', 'wood' => 'wood', 'stone' => 'stone', 'mine' => 'metal'];
                $col = $validJops[$itemId] ?? $itemId;
                if (in_array($col, ['food', 'wood', 'stone', 'metal'])) {
                    $up = $db->prepare("INSERT INTO `city_jop` (`id_city`, `id_player`, `{$col}`) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE `{$col}` = `{$col}` + VALUES(`{$col}`)");
                    $up->execute([$cityId, $playerId, $lvlTo]);

                    // Provisional prestige policy; the available client does not define job prestige values.
                    $prestigeGain = max(5, (int)floor($lvlTo * 0.05));
                    $db->prepare("UPDATE `player` SET `prestige` = `prestige` + ? WHERE `id_player` = ?")
                       ->execute([$prestigeGain, $playerId]);

                    // Mayor (Console Hero) gains 2x Prestige as Experience
                    $cStmt = $db->prepare("SELECT `console` FROM `city` WHERE `id_city` = ?");
                    $cStmt->execute([$cityId]);
                    $consoleHeroId = (int)$cStmt->fetchColumn();
                    if ($consoleHeroId > 0) {
                        $db->prepare("UPDATE `city_hero` SET `exp` = `exp` + ? WHERE `id_hero` = ?")
                           ->execute([$prestigeGain * 2, $consoleHeroId]);
                    }
                }
                ProductionService::recalculateCityProduction($db, $cityId);
            }

                // Delete completed task
                $del = $db->prepare("DELETE FROM `timed_task` WHERE `id_task` = ?");
                $del->execute([$taskId]);
            }

            if ($ownsTransaction) {
                $db->commit();
            }
        } catch (Throwable $e) {
            if ($ownsTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}

// Global procedural wrapper for backwards compatibility
if (!function_exists('processCompletedTasks')) {
    function processCompletedTasks($db) {
        TaskService::processCompletedTasks($db);
    }
}
