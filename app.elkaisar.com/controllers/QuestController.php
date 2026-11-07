<?php
// QuestController.php - Handles quests list, progression, and claiming quest rewards

require_once __DIR__ . '/../services/ProductionService.php';

class QuestController {
    public static function handle($uri, $db) {
        switch ($uri) {
            case '/api/APlayerQuest/getPlayerQuest':
                self::getPlayerQuest($db);
                return true;
            case '/api/APlayerQuest/acceptQuest':
                self::acceptQuest($db);
                return true;
        }
        return false;
    }

    public static function getPlayerQuest($db) {
        $stmt = $db->query("SELECT id_player FROM player ORDER BY id_player ASC LIMIT 1");
        $playerId = (int)$stmt->fetchColumn() ?: 1;

        // Load base quest definitions
        $questFile = __DIR__ . '/../js-0.0.24/json/questBase.json';
        $quests = file_exists($questFile) ? json_decode(file_get_contents($questFile), true) : [];

        // Fetch done quests from MySQL
        $stmt = $db->prepare("SELECT id_quest, done FROM player_quest WHERE id_player = ?");
        $stmt->execute([$playerId]);
        $doneMap = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

        $list = [];
        if (is_array($quests)) {
            foreach ($quests as $qId => $qData) {
                $list[] = [
                    'id_player' => $playerId,
                    'id_quest'  => (string)$qId,
                    'done'      => isset($doneMap[$qId]) ? (int)$doneMap[$qId] : 0
                ];
            }
        }

        sendJson($list);
    }

    public static function acceptQuest($db) {
        $cityId  = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 1;
        $idQuest = isset($_REQUEST['idQuest']) ? trim($_REQUEST['idQuest']) : '';

        $stmt = $db->query("SELECT id_player FROM player ORDER BY id_player ASC LIMIT 1");
        $playerId = (int)$stmt->fetchColumn() ?: 1;

        if (empty($idQuest)) {
            sendJson(['state' => 'error_1']);
        }

        // Check if already completed in DB
        $stmt = $db->prepare("SELECT done FROM player_quest WHERE id_player = ? AND id_quest = ?");
        $stmt->execute([$playerId, $idQuest]);
        $isDone = (int)$stmt->fetchColumn();
        if ($isDone === 1) {
            sendJson(['state' => 'error_2']); // Already completed
        }

        // Load quest definitions to grant real rewards and deduct requirements
        $questFile = __DIR__ . '/../js-0.0.24/json/questBase.json';
        $quests = file_exists($questFile) ? json_decode(file_get_contents($questFile), true) : [];
        $questData = isset($quests[$idQuest]) ? $quests[$idQuest] : null;

        if (!$questData) {
            // Fallback: mark as done
            $up = $db->prepare("INSERT INTO `player_quest` (`id_player`, `id_quest`, `done`) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE `done` = 1");
            $up->execute([$playerId, $idQuest]);
            sendJson(['state' => 'ok']);
        }

        // 1. Deduct needed resources/items if required by quest
        if (!empty($questData['ListOfNeed'])) {
            foreach ($questData['ListOfNeed'] as $need) {
                $nType = $need['type'] ?? '';
                $nAmt  = (int)($need['amount'] ?? 0);
                if ($nType === 'resource' && $nAmt > 0) {
                    $resKey = $need['resourceType'] ?? '';
                    if (in_array($resKey, ['food', 'wood', 'stone', 'metal', 'coin'])) {
                        $db->prepare("UPDATE `city` SET `{$resKey}` = GREATEST(0, `{$resKey}` - ?) WHERE `id_city` = ?")->execute([$nAmt, $cityId]);
                    }
                } elseif ($nType === 'item' && $nAmt > 0) {
                    $itemKey = $need['item'] ?? '';
                    if (!empty($itemKey)) {
                        $db->prepare("UPDATE `player_item` SET `amount` = GREATEST(0, `amount` - ?) WHERE `id_player` = ? AND `id_item` = ?")->execute([$nAmt, $playerId, $itemKey]);
                    }
                }
            }
        }

        // 2. Grant rewards
        if (!empty($questData['Reword'])) {
            foreach ($questData['Reword'] as $r) {
                $rType = $r['type'] ?? '';
                $amount = (int)($r['amount'] ?? 0);

                if ($rType === 'resource' && $amount > 0) {
                    $resKey = $r['resourceType'] ?? '';
                    if (in_array($resKey, ['food', 'wood', 'stone', 'metal', 'coin'])) {
                        $db->prepare("UPDATE `city` SET `{$resKey}` = LEAST(`{$resKey}_cap`, `{$resKey}` + ?) WHERE `id_city` = ?")->execute([$amount, $cityId]);
                    }
                } elseif ($rType === 'item' && $amount > 0) {
                    $itemKey = $r['item'] ?? '';
                    if (!empty($itemKey)) {
                        $db->prepare("INSERT INTO `player_item` (`id_player`, `id_item`, `amount`) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE `amount` = `amount` + VALUES(`amount`)")->execute([$playerId, $itemKey, $amount]);
                    }
                } elseif ($rType === 'population' && $amount > 0) {
                    $db->prepare("UPDATE `city` SET `pop` = LEAST(`pop_max`, `pop` + ?) WHERE `id_city` = ?")->execute([$amount, $cityId]);
                } elseif ($rType === 'prestige' && $amount > 0) {
                    $db->prepare("UPDATE `player` SET `prestige` = `prestige` + ? WHERE `id_player` = ?")->execute([$amount, $playerId]);
                } elseif ($rType === 'gold' && $amount > 0) {
                    $db->prepare("UPDATE `player` SET `gold` = `gold` + ? WHERE `id_player` = ?")->execute([$amount, $playerId]);
                } elseif ($rType === 'promotion') {
                    $db->prepare("UPDATE `player` SET `porm` = `porm` + 1 WHERE `id_player` = ?")->execute([$playerId]);
                } elseif ($rType === 'jop' && $amount > 0) {
                    $jopFor = $r['jop_for'] ?? $r['jopFor'] ?? '';
                    if ($jopFor === 'farm') $jopFor = 'food';
                    if ($jopFor === 'mine') $jopFor = 'metal';
                    if (in_array($jopFor, ['food', 'wood', 'stone', 'metal'])) {
                        $db->prepare("UPDATE `city_jop` SET `{$jopFor}` = `{$jopFor}` + ? WHERE `id_city` = ?")->execute([$amount, $cityId]);
                        ProductionService::recalculateCityProduction($db, $cityId);
                    }
                }
            }
        }

        // 3. Mark quest as done in MySQL
        $up = $db->prepare("INSERT INTO `player_quest` (`id_player`, `id_quest`, `done`) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE `done` = 1");
        $up->execute([$playerId, $idQuest]);

        sendJson(['state' => 'ok']);
    }
}
