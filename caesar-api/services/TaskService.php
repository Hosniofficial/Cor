<?php
// TaskService.php - Processes and finishes completed timed tasks (buildings, studies, armies, jobs)

require_once __DIR__ . '/ProductionService.php';

class TaskService {
    private static function notifySpyCompleted(int $playerId, int $spyNum): void {
        if (!function_exists('curl_init') || $playerId <= 0) {
            return;
        }
        $token = getenv('CAESAR_INTERNAL_API_TOKEN') ?: '';
        $headers = ['Content-Type: application/json'];
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . trim($token);
        }
        $ch = curl_init('http://127.0.0.1:8081/api/internal/notify_mail');
        if ($ch === false) {
            return;
        }
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'player_ids' => [$playerId],
            'class_path' => 'Battel.Spy',
            'data' => ['spy_num' => $spyNum],
        ], JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 500);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 300);
        curl_exec($ch);
        curl_close($ch);
    }

    public static function processCompletedTasks($db) {
        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) {
            $db->beginTransaction();
        }

        try {
            $now = time();
            $spyNotifications = [];
            $stmt = $db->prepare(
                "SELECT * FROM `timed_task` WHERE `time_arrive` <= ? " .
                "AND `task_type` NOT IN ('battle', 'battle_processing') " .
                "ORDER BY `id_task` FOR UPDATE"
            );
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

                if (in_array($bType, [19, 20], true)) {
                    error_log("[TaskService] Canceled unsupported naval building task {$taskId}");
                    $db->prepare("DELETE FROM `timed_task` WHERE `id_task` = ?")
                       ->execute([$taskId]);
                    continue;
                }

                if ($bType > 0) {
                    $up = $db->prepare("INSERT INTO `city_building` (`id_city`, `place`, `type`, `lvl`) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE `type` = VALUES(`type`), `lvl` = VALUES(`lvl`)");
                    $up->execute([$cityId, $itemId, $bType, $lvlTo]);
                } else {
                    $up = $db->prepare("UPDATE `city_building` SET `lvl` = ? WHERE `id_city` = ? AND `place` = ?");
                    $up->execute([$lvlTo, $cityId, $itemId]);
                }

                // Prestige rewards match the original TaskService policy.
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

                    // Prestige rewards match the original TaskService policy.
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

                    // Unit prestige yields match the original TaskService policy.
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

                    // Job prestige rewards match the original TaskService policy.
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
            } elseif ($type === 'spy') {
                $db->prepare(
                    'UPDATE city SET spies = spies + ? WHERE id_city = ?'
                )->execute([$lvlTo, $cityId]);

                $xTarget = (int)($details['x_to'] ?? 0);
                $yTarget = (int)($details['y_to'] ?? 0);
                $spyFor = (string)($details['spy_for'] ?? 'barrary');
                $targetType = (int)($details['target_type'] ?? 0);
                $targetLevel = (int)($details['target_level'] ?? 0);
                $targetPlayerId = (int)($details['target_player_id'] ?? 0);

                if ($spyFor === 'city') {
                    $targetCityId = (int)($details['target_city_id'] ?? 0);
                    $targetStmt = $db->prepare(
                        'SELECT id_city, id_player, name, x, y, food, wood, stone, metal, coin FROM city WHERE id_city = ? LIMIT 1'
                    );
                    $targetStmt->execute([$targetCityId]);
                    $targetCity = $targetStmt->fetch() ?: [];

                    $report = [
                        'side' => 'attacker',
                        'food_res' => (int)($targetCity['food'] ?? 0),
                        'wood_res' => (int)($targetCity['wood'] ?? 0),
                        'stone_res' => (int)($targetCity['stone'] ?? 0),
                        'metal_res' => (int)($targetCity['metal'] ?? 0),
                        'coin_res' => (int)($targetCity['coin'] ?? 0),
                    ];
                    foreach ([
                        'palace',
                        'hill_1', 'hill_2', 'hill_3', 'hill_4', 'hill_5', 'hill_6',
                        'hill_7', 'hill_8', 'hill_9', 'hill_10', 'hill_11', 'hill_12',
                        'under_wall_1', 'under_wall_2', 'under_wall_3', 'under_wall_4',
                        'under_wall_5', 'under_wall_6', 'under_wall_7', 'under_wall_8',
                        'under_wall_9', 'under_wall_10', 'under_wall_11', 'under_wall_12',
                        'under_palace_1', 'under_palace_2', 'under_palace_3',
                        'under_palace_4', 'under_palace_5', 'under_palace_6',
                        'under_palace_7', 'under_palace_8', 'under_palace_9',
                        'under_palace_10', 'under_palace_11', 'under_palace_12',
                        'light_house_1', 'light_house_2', 'light_house_3', 'light_house_4',
                        'light_house_5', 'light_house_6', 'light_house_7', 'light_house_8',
                        'light_house_9', 'light_house_10',
                        'around_wood_1', 'around_wood_2', 'around_wood_3',
                        'above_palace_1', 'above_palace_2', 'above_palace_3',
                        'above_palace_4', 'above_palace_5', 'above_palace_6',
                    ] as $buildingKey) {
                        $report[$buildingKey] = '0-0';
                    }
                    if ($targetCityId > 0) {
                        $buildingStmt = $db->prepare(
                            'SELECT place, type, lvl FROM city_building WHERE id_city = ?'
                        );
                        $buildingStmt->execute([$targetCityId]);
                        foreach ($buildingStmt->fetchAll() ?: [] as $building) {
                            $place = (string)($building['place'] ?? '');
                            if ($place !== '') {
                                $report[$place] = (int)$building['type'] . '-' . (int)$building['lvl'];
                            }
                            if ((int)$building['type'] === 12) {
                                $report['palace'] = '12-' . (int)$building['lvl'];
                            }
                        }
                    }
                } else {
                    $armyType = $targetType;
                    $armyLevel = $targetLevel;
                    $barStmt = $db->prepare(
                        'SELECT Type, lvl FROM city_barray WHERE x_coord = ? AND y_coord = ? LIMIT 1'
                    );
                    $barStmt->execute([$xTarget, $yTarget]);
                    $barRow = $barStmt->fetch();
                    if ($barRow) {
                        $armyType = (int)$barRow['Type'];
                        $armyLevel = (int)$barRow['lvl'];
                    } else {
                        $mapCandidates = [
                            dirname(__DIR__, 2) . '/caesar-assets/api/js-0.0.24/json/WorldBarrary.json',
                            dirname(__DIR__, 2) . '/caesar-assets/web/js-3.4.113/json/WorldBarrary.json',
                            dirname(__DIR__, 2) . '/caesar-assets/api/js-3.4.113/json/WorldBarrary.json',
                        ];
                        foreach ($mapCandidates as $mapPath) {
                            if (!is_readable($mapPath)) continue;
                            $map = json_decode(file_get_contents($mapPath), true);
                            if (!is_string($map)) continue;
                            foreach (explode(',', $map) as $entry) {
                                $parts = explode('_', $entry);
                                if (count($parts) >= 6
                                    && (int)$parts[0] === $xTarget
                                    && (int)$parts[1] === $yTarget) {
                                    $armyType = (int)$parts[2];
                                    $armyLevel = (int)$parts[3];
                                    break 2;
                                }
                            }
                        }
                    }

                    $armyData = [];
                    $unitArmyCandidates = [
                        dirname(__DIR__, 2) . '/caesar-assets/api/js-0.0.24/json/UnitArmy.json',
                        dirname(__DIR__, 2) . '/caesar-assets/web/js-3.4.113/json/UnitArmy.json',
                        dirname(__DIR__, 2) . '/caesar-assets/api/js-3.4.113/json/UnitArmy.json',
                    ];
                    foreach ($unitArmyCandidates as $armyPath) {
                        if (!is_readable($armyPath)) continue;
                        $unitArmy = json_decode(file_get_contents($armyPath), true);
                        if (is_array($unitArmy)) {
                            $armyData = $unitArmy[(string)$armyType][(string)$armyLevel] ?? [];
                            break;
                        }
                    }
                    $report = [
                        'side' => 'attacker',
                        'army_a' => (int)($armyData['army_a'] ?? $armyData['1'] ?? 0),
                        'army_b' => (int)($armyData['army_b'] ?? $armyData['2'] ?? 0),
                        'army_c' => (int)($armyData['army_c'] ?? $armyData['3'] ?? 0),
                        'army_d' => (int)($armyData['army_d'] ?? $armyData['4'] ?? 0),
                        'army_e' => (int)($armyData['army_e'] ?? $armyData['5'] ?? 0),
                        'army_f' => (int)($armyData['army_f'] ?? $armyData['6'] ?? 0),
                    ];
                    $targetType = $armyType;
                    $targetLevel = $armyLevel;
                }

                $db->prepare(
                    'INSERT INTO spy_report ' .
                    '(id_player, seen, victim, x, y, time_stamp, type, t, l, spy_for, content) ' .
                    'VALUES (?, 0, ?, ?, ?, NOW(), ?, ?, ?, ?, ?)'
                )->execute([
                    $playerId,
                    $targetPlayerId,
                    $xTarget,
                    $yTarget,
                    $targetType,
                    $targetType,
                    $targetLevel,
                    $spyFor,
                    json_encode($report, JSON_UNESCAPED_UNICODE),
                ]);
                $spyNotifications[] = [
                    'player_id' => $playerId,
                    'spy_num' => $lvlTo,
                ];
            }

                // Delete completed task
                $del = $db->prepare("DELETE FROM `timed_task` WHERE `id_task` = ?");
                $del->execute([$taskId]);
            }

            if ($ownsTransaction) {
                $db->commit();
            }
            if ($ownsTransaction) {
                foreach ($spyNotifications as $notification) {
                    self::notifySpyCompleted(
                        (int)$notification['player_id'],
                        (int)$notification['spy_num']
                    );
                }
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
