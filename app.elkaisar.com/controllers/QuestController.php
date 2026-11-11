<?php
// QuestController.php - Reads quest status and grants quest rewards.

require_once __DIR__ . '/../services/ResourceService.php';

class QuestController {
    private static function getDailyResetTimestamp(): int {
        $cairoTime = new DateTimeImmutable('now', new DateTimeZone('Africa/Cairo'));
        return $cairoTime->setTime(0, 0)->getTimestamp();
    }

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

    private static function resolvePlayerId($db) {
        $player = requireAuthenticatedPlayer($db);
        return (int)$player['id_player'];
    }

    public static function getPlayerQuest($db) {
        $playerId = self::resolvePlayerId($db);

        // Load base quest definitions
        $questFile = __DIR__ . '/../js-0.0.24/json/questBase.json';
        $quests = file_exists($questFile) ? json_decode(file_get_contents($questFile), true) : [];

        // Daily quests reset at midnight in Cairo, regardless of the server timezone.
        $todayMidnight = self::getDailyResetTimestamp();
        $refreshQuests = [];
        if (is_array($quests)) {
            foreach ($quests as $qId => $qData) {
                if (!empty($qData['refresh'])) {
                    $refreshQuests[] = (string)$qId;
                }
            }
        }

        if (!empty($refreshQuests)) {
            // Reset daily quests that were claimed on a previous day or have time_done = 0
            $placeholders = implode(',', array_fill(0, count($refreshQuests), '?'));
            $resetParams = array_merge([$playerId, $todayMidnight], $refreshQuests);
            $db->prepare("UPDATE `player_quest` SET `done` = 0 WHERE `id_player` = ? AND `time_done` < ? AND `id_quest` IN ($placeholders)")
               ->execute($resetParams);
        }

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
        $player = requireAuthenticatedPlayer($db);
        $playerId = (int)$player['id_player'];
        $questId = $_POST['idQuest'] ?? $_GET['idQuest'] ?? '';
        $cityId = $_POST['idCity'] ?? $_GET['idCity'] ?? 0;
        if (!is_string($questId) || $questId === '' || !is_scalar($cityId) || (int)$cityId <= 0) {
            http_response_code(400);
            sendJson(['state' => 'error_1']);
        }

        $questFile = __DIR__ . '/../js-0.0.24/json/questBase.json';
        $questData = json_decode((string)file_get_contents($questFile), true);
        if (!is_array($questData) || !isset($questData[$questId]) || !is_array($questData[$questId])) {
            sendJson(['state' => 'error_1']);
        }
        $quest = $questData[$questId];
        $daily = !empty($quest['refresh']);
        $now = time();
        $todayMidnight = self::getDailyResetTimestamp();
        $result = ['state' => 'error_4'];

        $db->beginTransaction();
        try {
            $cityStmt = $db->prepare('SELECT * FROM `city` WHERE `id_city` = ? AND `id_player` = ? FOR UPDATE');
            $cityStmt->execute([(int)$cityId, $playerId]);
            $city = $cityStmt->fetch(PDO::FETCH_ASSOC);
            if (!$city) {
                $db->rollBack();
                http_response_code(403);
                sendJson(['state' => 'error_auth']);
            }

            $playerStmt = $db->prepare('SELECT * FROM `player` WHERE `id_player` = ? FOR UPDATE');
            $playerStmt->execute([$playerId]);
            $player = $playerStmt->fetch(PDO::FETCH_ASSOC);

            $db->prepare(
                'INSERT IGNORE INTO `player_quest` (`id_player`, `id_quest`, `done`, `time_done`) VALUES (?, ?, 0, 0)'
            )->execute([$playerId, $questId]);
            $questStateStmt = $db->prepare(
                'SELECT `done`, `time_done` FROM `player_quest` WHERE `id_player` = ? AND `id_quest` = ? FOR UPDATE'
            );
            $questStateStmt->execute([$playerId, $questId]);
            $questState = $questStateStmt->fetch(PDO::FETCH_ASSOC);

            $alreadyClaimedToday = (int)$questState['done'] === 1
                && (!$daily || (int)$questState['time_done'] >= $todayMidnight);
            if ($alreadyClaimedToday) {
                $db->rollBack();
                sendJson(['state' => 'error_2']);
            }

            if (!self::checkShowConditions($db, $playerId, $quest['showCond'] ?? [])) {
                $db->rollBack();
                sendJson(['state' => 'error_1']);
            }
            if (!self::checkRequirements($db, $player, $city, $quest['ListOfNeed'] ?? [])) {
                $db->rollBack();
                sendJson(['state' => 'error_4']);
            }
            if (!self::isValidRewardList($quest['Reword'] ?? null)) {
                throw new RuntimeException("Invalid reward definition for quest {$questId}");
            }

            foreach ($quest['Reword'] as $reward) {
                self::grantReward($db, $playerId, (int)$cityId, $reward);
            }

            $db->prepare(
                'UPDATE `player_quest` SET `done` = 1, `time_done` = ? WHERE `id_player` = ? AND `id_quest` = ?'
            )->execute([$now, $playerId, $questId]);
            $db->commit();
            $result = ['state' => 'ok'];
        } catch (ResourceLimitException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            sendJson([
                'state' => 'error_resource_limit',
                'msg' => 'ستتجاوز مكافأة المهمة الحد الأقصى لموارد المدينة'
            ]);
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        sendJson($result);
    }

    private static function checkShowConditions(PDO $db, int $playerId, array $conditions): bool {
        foreach ($conditions as $condition) {
            if (($condition['type'] ?? '') !== 'quest' || empty($condition['id_quest'])) {
                return false;
            }
            $stmt = $db->prepare(
                'SELECT `done`, `time_done` FROM `player_quest` WHERE `id_player` = ? AND `id_quest` = ?'
            );
            $stmt->execute([$playerId, (string)$condition['id_quest']]);
            $state = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$state || (int)$state['done'] !== 1) {
                return false;
            }
        }
        return true;
    }

    private static function checkRequirements(PDO $db, array $player, array $city, array $requirements): bool {
        $state = null;
        foreach ($requirements as $requirement) {
            $type = $requirement['type'] ?? '';
            $amount = (int)($requirement['amount'] ?? 0);
            if ($type === 'building') {
                $stmt = $db->prepare('SELECT MAX(`lvl`) FROM `city_building` WHERE `id_city` = ? AND `type` = ?');
                $stmt->execute([(int)$city['id_city'], (int)($requirement['buildingType'] ?? 0)]);
                if ((int)$stmt->fetchColumn() < (int)($requirement['lvl'] ?? 0)) {
                    return false;
                }
            } elseif ($type === 'promotion') {
                if ((int)$player['porm'] < (int)($requirement['promotion'] ?? 0)) {
                    return false;
                }
            } elseif ($type === 'prestige') {
                if ((int)$player['prestige'] < $amount) {
                    return false;
                }
            } elseif ($type === 'item') {
                $stmt = $db->prepare('SELECT `amount` FROM `player_item` WHERE `id_player` = ? AND `id_item` = ?');
                $stmt->execute([(int)$player['id_player'], (string)($requirement['item'] ?? '')]);
                if ((int)$stmt->fetchColumn() < $amount) {
                    return false;
                }
            } elseif ($type === 'resource') {
                $resource = $requirement['resourceType'] ?? '';
                if (!in_array($resource, ['food', 'wood', 'stone', 'metal', 'coin'], true)
                    || (int)$city[$resource] < $amount) {
                    return false;
                }
            } elseif ($type === 'population') {
                if ((int)$city['pop'] < $amount) {
                    return false;
                }
            } elseif ($type === 'playerState') {
                $stateFor = $requirement['stateFor'] ?? '';
                if (!in_array($stateFor, ['motiv', 'peace', 'medical', 'wheat', 'stone', 'metal', 'coin', 'wood', 'attack_10', 'defence_10'], true)) {
                    return false;
                }
                if ($state === null) {
                    $stmt = $db->prepare('SELECT * FROM `player_state` WHERE `id_player` = ?');
                    $stmt->execute([(int)$player['id_player']]);
                    $state = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
                }
                if ((int)($state[$stateFor] ?? 0) < time()) {
                    return false;
                }
            } else {
                return false;
            }
        }
        return true;
    }

    private static function isValidRewardList($rewards): bool {
        if (!is_array($rewards)) {
            return false;
        }
        foreach ($rewards as $reward) {
            $type = $reward['type'] ?? '';
            $amount = (int)($reward['amount'] ?? 0);
            if ($type === 'promotion') {
                continue;
            }
            if ($amount < 0) {
                return false;
            }
            if ($type === 'resource') {
                if (!in_array($reward['resourceType'] ?? '', ResourceService::CITY_RESOURCES, true)
                    || !is_int($reward['amount'] ?? null)
                    || $reward['amount'] > ResourceService::MAX_CITY_BALANCE) {
                    return false;
                }
                continue;
            }
            if ($type === 'item' && !empty($reward['item']) && strlen((string)$reward['item']) <= 60) {
                continue;
            }
            if ($type === 'population') {
                continue;
            }
            if ($type === 'jop' && in_array($reward['jop_for'] ?? $reward['jopFor'] ?? '', ['food', 'wood', 'stone', 'metal'], true)) {
                continue;
            }
            if ($type === 'equip' && !empty($reward['equip']) && !empty($reward['part'])
                && strlen((string)$reward['equip']) <= 50 && strlen((string)$reward['part']) <= 50) {
                continue;
            }
            return false;
        }
        return true;
    }

    private static function grantReward(PDO $db, int $playerId, int $cityId, array $reward): void {
        $type = $reward['type'];
        $amount = (int)($reward['amount'] ?? 0);
        if ($type === 'resource') {
            $resource = $reward['resourceType'];
            if (!ResourceService::creditCity($db, $cityId, $playerId, [$resource => $amount])) {
                throw new ResourceLimitException('Quest resource reward exceeds city balance limit');
            }
        } elseif ($type === 'item') {
            $db->prepare(
                'INSERT INTO `player_item` (`id_player`, `id_item`, `amount`) VALUES (?, ?, ?) ' .
                'ON DUPLICATE KEY UPDATE `amount` = `amount` + VALUES(`amount`)'
            )->execute([$playerId, (string)$reward['item'], $amount]);
        } elseif ($type === 'population') {
            $db->prepare('UPDATE `city` SET `pop` = `pop` + ? WHERE `id_city` = ? AND `id_player` = ?')
                ->execute([$amount, $cityId, $playerId]);
        } elseif ($type === 'prestige') {
            $db->prepare('UPDATE `player` SET `prestige` = `prestige` + ? WHERE `id_player` = ?')
                ->execute([$amount, $playerId]);
        } elseif ($type === 'jop') {
            $job = $reward['jop_for'] ?? $reward['jopFor'];
            $db->prepare(
                'INSERT IGNORE INTO `city_jop` (`id_city`, `id_player`, `food`, `wood`, `stone`, `metal`) VALUES (?, ?, 0, 0, 0, 0)'
            )->execute([$cityId, $playerId]);
            $db->prepare("UPDATE `city_jop` SET `$job` = `$job` + ? WHERE `id_city` = ? AND `id_player` = ?")
                ->execute([$amount, $cityId, $playerId]);
        } elseif ($type === 'equip') {
            $stmt = $db->prepare(
                'INSERT INTO `player_equip` (`id_player`, `id_hero`, `type`, `part`, `lvl`, `on_hero`) VALUES (?, 0, ?, ?, ?, 0)'
            );
            for ($i = 0; $i < $amount; $i++) {
                $stmt->execute([$playerId, (string)$reward['equip'], (string)$reward['part'], max(1, (int)($reward['lvl'] ?? 1))]);
            }
        } elseif ($type === 'promotion') {
            $db->prepare('UPDATE `player` SET `porm` = `porm` + 1 WHERE `id_player` = ?')->execute([$playerId]);
        }
    }
}
