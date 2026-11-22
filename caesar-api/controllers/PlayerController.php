<?php
// PlayerController.php - Handles player auth, profile state, items, education, luck wheel, and equipment
require_once __DIR__ . '/../services/ServerConfigService.php';

class PlayerController {
    public static function handle($uri, $db) {
        switch ($uri) {
            case '/home/HLogIn/playerEnterServerWeb':
                self::playerEnterServerWeb($db);
                return true;
            case '/api/APlayer/getPlayerState':
                self::getPlayerState($db);
                return true;
            case '/api/APlayer/getPlayerInfo':
            case '/api/APlayer/refreshPlayerData':
                self::refreshPlayerData($db);
                return true;
            case '/api/APlayer/translateChatMessage':
                self::translateChatMessage();
                return true;
            case '/api/APlayer/playLuckWheel':
                self::playLuckWheel($db);
                return true;
            case '/api/APlayerEdu/getPlayerEduLvl':
                self::getPlayerEduLvl($db);
                return true;
            case '/api/APlayerEdu/upgradeStudyLvl':
                self::upgradeStudyLvl($db);
                return true;
            case '/api/APlayerItem/getPlayerItems':
                self::getPlayerItems($db);
                return true;
            case '/api/APlayerEquip/getPlayerEquip':
                self::getPlayerEquip($db);
                return true;
            case '/api/APlayer/changePlayerName':
                self::changePlayerName($db);
                return true;
            case '/api/APlayer/changePlayerAvatar':
                self::changePlayerAvatar($db);
                return true;
            case '/api/APlayer/changePlayerFlag':
                self::changePlayerFlag($db);
                return true;
            case '/api/APlayer/getAllNotif':
                self::getAllNotif($db);
                return true;
            case '/api/APlayer/getFavoriteCoords':
            case '/api/AWorld/getFavoriteCoords':
                self::getFavoriteCoords($db);
                return true;
            case '/api/APlayer/addFavoriteCoord':
            case '/api/AWorld/addFavoriteCoord':
                self::addFavoriteCoord($db);
                return true;
            case '/api/APlayer/deleteFavoriteCoord':
            case '/api/AWorld/deleteFavoriteCoord':
                self::deleteFavoriteCoord($db);
                return true;
            case '/api/APlayer/modifyFavoriteCoordTitle':
            case '/api/AWorld/modifyFavoriteCoordTitle':
                self::modifyFavoriteCoordTitle($db);
                return true;
            case '/api/APlayer/chatPann':
                sendJson(['state' => 'ok']);
                return true;
        }
        return false;
    }

    public static function resolvePlayer($db) {
        return requireAuthenticatedPlayer($db);
    }

    public static function playerEnterServerWeb($db) {
        $player = self::resolvePlayer($db);

        // Get player's cities
        $cStmt = $db->prepare("SELECT `id_city` FROM `city` WHERE `id_player` = ?");
        $cStmt->execute([$player['id_player']]);
        $cityIds = $cStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $response = [
            'state'     => 'ok',
            'Player'    => [
                'id_user'    => (int)$player['id_user'],
                'id_player'  => (int)$player['id_player'],
                'user_group' => (int)$player['user_group'],
                'prestige'   => (int)$player['prestige'],
                'honor'      => (int)$player['honor'],
                'gold'       => (int)$player['gold'],
                'avatar'     => (int)$player['avatar'],
                'guild'      => $player['guild'],
                'id_guild'   => $player['id_guild'] ? (int)$player['id_guild'] : null,
                'name'       => $player['name'],
                'lang'       => $player['lang'],
                'city_flag'  => $player['city_flag'],
                'porm'       => (int)$player['porm'],
                'chat_panne' => (int)$player['chat_panne'],
                'online'     => 1,
                'last_seen'  => time(),
                'panned'     => (int)$player['panned'],
                'rank'       => (int)$player['rank'],
                'guild_data' => false
            ],
            'Server'    => [
                'id'          => 1,
                'player_num'  => 1,
                'guild_num'   => 0,
                'city_num'    => count($cityIds),
                'hero_num'    => 1,
                'open_status' => 1,
                'under_main'  => 0,
                'port'        => '8082'
            ],
            'JsVersion'  => '-0.0.24',
            'WsPort'     => '8081',
            'WsHost'     => 'localhost',
            'AuthToken'  => $player['p_token'],
            'idServer'   => '1',
            'idCities'   => array_map('intval', $cityIds),
            'PayLink'    => '',
            'RechCode'   => 'local'
        ];
        sendJson($response);
    }

    public static function getPlayerState($db) {
        $p = self::resolvePlayer($db);
        $idPlayer = (int)$p['id_player'];
        $stmt = $db->prepare("SELECT * FROM `player_state` WHERE `id_player` = ? LIMIT 1");
        $stmt->execute([$idPlayer]);
        $state = $stmt->fetch();
        if ($state) {
            foreach ($state as $k => $v) { $state[$k] = (int)$v; }
            sendJson($state);
            return;
        }
        sendJson(['state' => 'ok']);
    }

    public static function refreshPlayerData($db) {
        $p = self::resolvePlayer($db);
        if ($p) {
            unset($p['p_token']);
            $p['id_player'] = (int)$p['id_player'];
            $p['prestige']  = (int)$p['prestige'];
            $p['gold']      = (int)$p['gold'];
            $p['honor']     = (int)$p['honor'];
            sendJson($p);
            return;
        }
        sendJson(['state' => 'error']);
    }

    public static function getAllNotif($db) {
        $player = self::resolvePlayer($db);
        $playerId = (int)$player['id_player'];
        $stmt = $db->prepare(
            'SELECT
                (SELECT COUNT(*) FROM `msg_income` WHERE `id_player` = ? AND `seen` = 0) AS `msg_in`,
                (SELECT COUNT(*) FROM `msg_diff` WHERE `id_player` = ? AND `seen` = 0) AS `msg_diff`,
                (SELECT COUNT(*) FROM `report_player` WHERE `id_player` = ? AND `seen` = 0) AS `msg_report`,
                (SELECT COUNT(*) FROM `spy_report` WHERE `id_player` = ? AND `seen` = 0) AS `spy_report`'
        );
        $stmt->execute([$playerId, $playerId, $playerId, $playerId]);
        $notifications = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        foreach ($notifications as $name => $count) {
            $notifications[$name] = (int)$count;
        }

        sendJson($notifications);
    }

    public static function translateChatMessage() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            sendJson(['state' => 'error_method_not_allowed']);
        }

        $text = $_POST['text'] ?? null;
        $target = $_POST['target'] ?? null;
        if (!is_string($text)
            || trim($text) === ''
            || mb_strlen($text, 'UTF-8') > 5000
            || !is_string($target)
            || preg_match('/\A[a-z]{2,3}(?:-[A-Z]{2})?\z/', $target) !== 1) {
            http_response_code(400);
            sendJson(['state' => 'error_invalid_translation_request']);
        }

        $apiKey = getenv('CAESAR_GOOGLE_TRANSLATE_API_KEY');
        if (!is_string($apiKey) || trim($apiKey) === '') {
            error_log('[PlayerController] Chat translation is not configured');
            http_response_code(503);
            sendJson(['state' => 'error_translation_unavailable']);
        }

        $requestBody = json_encode([
            'q' => $text,
            'target' => $target,
            'format' => 'text',
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        $curl = curl_init('https://translation.googleapis.com/language/translate/v2');
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $requestBody,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Goog-Api-Key: ' . trim($apiKey),
            ],
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 8,
        ]);

        $response = curl_exec($curl);
        $httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = curl_error($curl);
        curl_close($curl);

        if ($response === false) {
            error_log('[PlayerController] Chat translation request failed: ' . $curlError);
            http_response_code(502);
            sendJson(['state' => 'error_translation_unavailable']);
        }

        $result = json_decode($response, true);
        $translatedText = $result['data']['translations'][0]['translatedText'] ?? null;
        if ($httpCode < 200 || $httpCode >= 300 || !is_string($translatedText)) {
            error_log('[PlayerController] Translation provider returned HTTP ' . $httpCode);
            http_response_code(502);
            sendJson(['state' => 'error_translation_unavailable']);
        }

        sendJson([
            'state' => 'ok',
            'translatedText' => $translatedText,
        ]);
    }

    public static function generateLuckWheelPrizes() {
        return [
            ['Prize' => 'archit_a',     'amount' => 2, 'weight' => 350,  'rate' => '3.5%'],
            ['Prize' => 'food_c',       'amount' => 1, 'weight' => 1200, 'rate' => '12.0%'],
            ['Prize' => 'archit_b',     'amount' => 1, 'weight' => 250,  'rate' => '2.5%'],
            ['Prize' => 'wood_c',       'amount' => 1, 'weight' => 1200, 'rate' => '12.0%'],
            ['Prize' => 'gold_10',      'amount' => 1, 'weight' => 30,   'rate' => '0.3%'],
            ['Prize' => 'stone_c',      'amount' => 1, 'weight' => 1200, 'rate' => '12.0%'],
            ['Prize' => 'coin_d',       'amount' => 1, 'weight' => 800,  'rate' => '8.0%'],
            ['Prize' => 'metal_c',      'amount' => 1, 'weight' => 1200, 'rate' => '12.0%'],
            ['Prize' => 'archit_c',     'amount' => 1, 'weight' => 150,  'rate' => '1.5%'],
            ['Prize' => 'food_d',       'amount' => 1, 'weight' => 600,  'rate' => '6.0%'],
            ['Prize' => 'shopping_car', 'amount' => 1, 'weight' => 400,  'rate' => '4.0%'],
            ['Prize' => 'wood_d',       'amount' => 1, 'weight' => 600,  'rate' => '6.0%'],
            ['Prize' => 'gold_25',      'amount' => 1, 'weight' => 15,   'rate' => '0.15%'],
            ['Prize' => 'stone_d',      'amount' => 1, 'weight' => 600,  'rate' => '6.0%'],
            ['Prize' => 'trumpet',      'amount' => 5, 'weight' => 500,  'rate' => '5.0%'],
            ['Prize' => 'metal_d',      'amount' => 1, 'weight' => 600,  'rate' => '6.0%'],
            ['Prize' => 'peace',        'amount' => 1, 'weight' => 5,    'rate' => '0.05%'],
            ['Prize' => 'necklace_1',   'amount' => 1, 'weight' => 150,  'rate' => '1.5%'],
            ['Prize' => 'necklace_2',   'amount' => 1, 'weight' => 100,  'rate' => '1.0%'],
            ['Prize' => 'necklace_3',   'amount' => 1, 'weight' => 50,   'rate' => '0.5%']
        ];
    }

    public static function playLuckWheel($db) {
        $prizes = self::generateLuckWheelPrizes();

        $amount = max(1, min(100, (int)($_POST['amount'] ?? 1)));

        // Resolve player
        $player = self::resolvePlayer($db);
        $idPlayer = (int)$player['id_player'];

        $db->beginTransaction();
        try {
            // Check available tickets
            $itemStmt = $db->prepare("SELECT `amount` FROM `player_item` WHERE `id_player` = ? AND `id_item` = 'luck_play' FOR UPDATE");
            $itemStmt->execute([$idPlayer]);
            $itemRow = $itemStmt->fetch();
            $currentTickets = $itemRow ? (int)$itemRow['amount'] : 0;

            $ticketsToDeduct = min($currentTickets, $amount);
            $remainingSpins = $amount - $ticketsToDeduct;
            $goldNeeded = $remainingSpins * 5;

            // Check gold if tickets aren't enough
            if ($goldNeeded > 0) {
                $pStmt = $db->prepare("SELECT `gold` FROM `player` WHERE `id_player` = ? FOR UPDATE");
                $pStmt->execute([$idPlayer]);
                $pRow = $pStmt->fetch();
                $currentGold = $pRow ? (int)$pRow['gold'] : 0;

                if ($currentGold < $goldNeeded) {
                    $db->rollBack();
                    sendJson(['state' => 'error_no_matrial']);
                    return;
                }

                // Deduct gold
                $db->prepare("UPDATE `player` SET `gold` = `gold` - ? WHERE `id_player` = ?")->execute([$goldNeeded, $idPlayer]);
            }

            // Deduct tickets
            if ($ticketsToDeduct > 0) {
                $db->prepare("UPDATE `player_item` SET `amount` = `amount` - ? WHERE `id_player` = ? AND `id_item` = 'luck_play'")->execute([$ticketsToDeduct, $idPlayer]);
            }

            // Draw prizes
            $totalWeight = array_sum(array_column($prizes, 'weight'));
            $winIndices = [];
            $wonPrizes = [];
            $rewardStmt = $db->prepare(
                'INSERT INTO `player_item` (`id_player`, `id_item`, `amount`) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE `amount` = `amount` + VALUES(`amount`)'
            );

            for ($spin = 0; $spin < $amount; $spin++) {
                $rand = random_int(1, $totalWeight);
                $cumulative = 0;
                $winIndex = 0;
                foreach ($prizes as $idx => $prize) {
                    $cumulative += $prize['weight'];
                    if ($rand <= $cumulative) {
                        $winIndex = $idx;
                        break;
                    }
                }
                $winIndices[] = $winIndex;
                $wonPrize = $prizes[$winIndex];
                $wonPrizes[] = $wonPrize;
                $rewardStmt->execute([$idPlayer, $wonPrize['Prize'], $wonPrize['amount']]);
            }

            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('[PlayerController] Luck wheel transaction failed: ' . $error->getMessage());
            throw $error;
        }

        sendJson([
            'state'        => 'ok',
            'winIndex'     => $winIndices[count($winIndices) - 1],
            'winIndices'   => $winIndices,
            'wonPrizes'    => $wonPrizes,
            'amount'       => $amount,
            'usedTickets'  => $ticketsToDeduct,
            'usedGold'     => $goldNeeded,
            'Prize'        => $prizes
        ]);
        return;
    }

    public static function getPlayerEduLvl($db) {
        $p = self::resolvePlayer($db);
        $idPlayer = (int)$p['id_player'];
        $stmt = $db->prepare("SELECT * FROM `player_edu` WHERE `id_player` = ? LIMIT 1");
        $stmt->execute([$idPlayer]);
        $edu = $stmt->fetch();
        if ($edu) {
            foreach ($edu as $k => $v) { $edu[$k] = (int)$v; }
            sendJson($edu);
        }
        sendJson(['state' => 'ok']);
    }

    private static $eduReqs = null;

    public static function getStudyRequirements($studyName, $lvl) {
        if (self::$eduReqs === null) {
            $candidates = [
                __DIR__ . '/../js-3.4.113/json/education.json',
                dirname(__DIR__) . '/js-3.4.113/json/education.json',
                dirname(__DIR__, 2) . '/caesar-assets/web/js-3.4.113/json/education.json'
            ];
            self::$eduReqs = [];
            foreach ($candidates as $cand) {
                if (file_exists($cand)) {
                    self::$eduReqs = json_decode(file_get_contents($cand), true) ?: [];
                    break;
                }
            }
        }

        if (isset(self::$eduReqs[$studyName])) {
            $s = self::$eduReqs[$studyName];
            $lvlReq = $s['lvl_req'][$lvl - 1] ?? null;
            $timeReq = $s['time_req'][$lvl - 1] ?? ($lvl * 60);
            if ($lvlReq) {
                return [
                    'food'  => (int)($lvlReq['food']  ?? 0),
                    'wood'  => (int)($lvlReq['wood']  ?? 0),
                    'stone' => (int)($lvlReq['stone'] ?? 0),
                    'metal' => (int)($lvlReq['metal'] ?? 0),
                    'coin'  => (int)($lvlReq['coin']  ?? 0),
                    'time'  => max(15, (int)$timeReq)
                ];
            }
        }
        return null;
    }

    public static function upgradeStudyLvl($db) {
        $cityId  = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 0;
        if ($cityId <= 0) {
            sendJson(['state' => 'error_city']);
        }
        $idStudy = isset($_REQUEST['idStudy']) ? trim($_REQUEST['idStudy']) : (isset($_REQUEST['study']) ? trim($_REQUEST['study']) : '');

        $player = self::resolvePlayer($db);
        $stmt = $db->prepare("SELECT c.id_player FROM city c WHERE c.id_city = ?");
        $stmt->execute([$cityId]);
        $cityPlayerId = (int)$stmt->fetchColumn();
        if ($cityPlayerId !== (int)$player['id_player']) {
            http_response_code(403);
            sendJson(['state' => 'error_city_owner']);
        }
        $playerId = $cityPlayerId;

        $stmt = $db->prepare("SELECT * FROM player_edu WHERE id_player = ?");
        $stmt->execute([$playerId]);
        $edu = $stmt->fetch();
        $currLvl = isset($edu[$idStudy]) ? (int)$edu[$idStudy] : 0;
        $nextLvl = $currLvl + 1;

        if ($nextLvl > 30) {
            sendJson(['state' => 'error_max_lvl', 'msg' => 'الدراسة وصلت للحد الأقصى']);
            return;
        }

        $req = self::getStudyRequirements($idStudy, $nextLvl);
        $costFood  = (int)($req['food']  ?? 0);
        $costWood  = (int)($req['wood']  ?? 0);
        $costStone = (int)($req['stone'] ?? 0);
        $costMetal = (int)($req['metal'] ?? 0);
        $costCoin  = (int)($req['coin']  ?? ($nextLvl * 500));
        // 1. Building Prerequisite: Academy (type 9) for military studies, University (type 8) for civil studies
        $academyStudies = ['infantry', 'riding', 'army', 'spying', 'leader', 'safe', 'medicine', 'logistic', 'navigating', 'supplying'];
        $studyPlace = in_array($idStudy, $academyStudies, true) ? 'acad' : 'uni';
        $reqBType = $studyPlace === 'acad' ? 9 : 8;

        $bStmt = $db->prepare("SELECT MAX(lvl) FROM city_building WHERE id_city = ? AND type = ?");
        $bStmt->execute([$cityId, $reqBType]);
        $hasBLvl = (int)$bStmt->fetchColumn();
        if ($hasBLvl < $nextLvl) {
            sendJson(['state' => 'error_cond', 'msg' => "تتطلب الدراسة مستوى مبنى التعليم {$nextLvl} على الأقل"]);
            return;
        }

        $duration = max(15, (int)ceil(
            (int)($req['time'] ?? ($nextLvl * 60))
            * max(0.1, 1 - ($hasBLvl * 0.03))
            / max(0.1, ServerConfigService::getFloat($db, 'study_speed_multiplier', 1.0))
        ));

        $lawItem = null;
        if ($nextLvl >= 25) {
            $lawItem = 'law_3';
        } elseif ($nextLvl >= 20) {
            $lawItem = 'law_2';
        } elseif ($nextLvl >= 10) {
            $lawItem = 'law_1';
        }

        if ($lawItem !== null) {
            $lawStmt = $db->prepare('SELECT amount FROM player_item WHERE id_player = ? AND id_item = ?');
            $lawStmt->execute([$playerId, $lawItem]);
            if ((int)$lawStmt->fetchColumn() <= 0) {
                $lawMessages = [
                    'law_1' => 'تتطلب الدراسة من مستوى 10 وجود قانون دراكو (law_1)',
                    'law_2' => 'تتطلب الدراسة من مستوى 20 وجود جدول الثاني عشر (law_2)',
                    'law_3' => 'تتطلب الدراسة من مستوى 25 وجود قانون روماني (law_3)',
                ];
                sendJson(['state' => 'error_item', 'msg' => $lawMessages[$lawItem]]);
                return;
            }
        }

        // 3. Resource Check
        $cStmt = $db->prepare("SELECT * FROM city WHERE id_city = ?");
        $cStmt->execute([$cityId]);
        $city = $cStmt->fetch(PDO::FETCH_ASSOC);

        if ((int)$city['food'] < $costFood || (int)$city['wood'] < $costWood || (int)$city['stone'] < $costStone || (int)$city['metal'] < $costMetal || (int)$city['coin'] < $costCoin) {
            sendJson(['state' => 'error_res', 'msg' => 'الموارد المتوفرة بالمدينة غير كافية للبحث']);
            return;
        }

        $now = time();
        $arrive = $now + $duration;
        $insufficientResources = false;
        $taskId = 0;
        ItemController::runItemTransaction($db, function () use (
            $db,
            $cityId,
            $playerId,
            $costFood,
            $costWood,
            $costStone,
            $costMetal,
            $costCoin,
            &$insufficientResources,
            $lawItem,
            $idStudy,
            $nextLvl,
            $now,
            $arrive,
            $studyPlace,
            &$taskId
        ) {
            $resourceStmt = $db->prepare(
                'UPDATE `city` SET
                    `food` = `food` - ?,
                    `wood` = `wood` - ?,
                    `stone` = `stone` - ?,
                    `metal` = `metal` - ?,
                    `coin` = `coin` - ?
                 WHERE `id_city` = ? AND `id_player` = ?
                    AND `food` >= ? AND `wood` >= ? AND `stone` >= ?
                    AND `metal` >= ? AND `coin` >= ?'
            );
            $resourceStmt->execute([
                $costFood, $costWood, $costStone, $costMetal, $costCoin,
                $cityId, $playerId,
                $costFood, $costWood, $costStone, $costMetal, $costCoin,
            ]);
            if ($resourceStmt->rowCount() !== 1) {
                $insufficientResources = true;
                return;
            }

            if ($lawItem !== null) {
                ItemController::deductPlayerItem($db, $playerId, $lawItem, 1);
            }

            $taskStmt = $db->prepare(
                "INSERT INTO `timed_task` (`id_city`, `id_player`, `task_type`, `item_id`, `lvl_to`, `start_time`, `time_arrive`, `details`)"
                . " VALUES (?, ?, 'study', ?, ?, ?, ?, ?)"
            );
            $taskStmt->execute([
                $cityId,
                $playerId,
                $idStudy,
                $nextLvl,
                $now,
                $arrive,
                json_encode(['study_in' => $studyPlace]),
            ]);
            $taskId = (int)$db->lastInsertId();
        });

        if ($insufficientResources) {
            sendJson(['state' => 'error_res', 'msg' => 'الموارد المتوفرة بالمدينة غير كافية للبحث']);
            return;
        }

        $cStmt = $db->prepare("SELECT * FROM city WHERE id_city = ?");
        $cStmt->execute([$cityId]);
        $cityRes = $cStmt->fetch();
        foreach ($cityRes as $k => $v) { if (is_numeric($v)) $cityRes[$k] = (int)$v; }

        sendJson([
            'state'   => 'ok',
            'cityRes' => $cityRes,
            'list'    => [
                [
                    'id'           => $taskId,
                    'id_city'      => $cityId,
                    'study'        => $idStudy,
                    'study_in'     => $studyPlace,
                    'lvl_to'       => $nextLvl,
                    'time_start'   => $now,
                    'time_end'     => $arrive,
                    'time_end_org' => $arrive
                ]
            ]
        ]);
    }

    public static function getPlayerItems($db) {
        $p = self::resolvePlayer($db);
        $idPlayer = (int)$p['id_player'];
        $stmt = $db->prepare("SELECT * FROM `player_item` WHERE `id_player` = ? ORDER BY `id_item` ASC");
        $stmt->execute([$idPlayer]);
        $items = $stmt->fetchAll() ?: [];
        foreach ($items as &$it) {
            $it['id_player'] = (int)$it['id_player'];
            $it['amount']    = (int)$it['amount'];
        }
        sendJson($items);
    }

    public static function getPlayerEquip($db) {
        $p = self::resolvePlayer($db);
        $idPlayer = (int)$p['id_player'];
        $eqStmt = $db->prepare("SELECT id_equip, id_player, id_hero, type, part, lvl, on_hero FROM player_equip WHERE id_player = ?");
        $eqStmt->execute([$idPlayer]);
        $equips = $eqStmt->fetchAll() ?: [];
        foreach ($equips as &$eq) {
            $eq['id_equip']  = (int)$eq['id_equip'];
            $eq['id_player'] = (int)$eq['id_player'];
            $eq['id_hero']   = (int)$eq['id_hero'];
            $eq['lvl']       = (int)$eq['lvl'];
            $eq['on_hero']   = (int)$eq['on_hero'];
        }
        sendJson($equips);
    }

    public static function changePlayerName($db) {
        $newName = normalizeGameDisplayName($_REQUEST['NewName'] ?? null);
        if ($newName === null || preg_match('/\A.{3,10}\z/us', $newName) !== 1) {
            sendJson(['state' => 'error_name']);
        }
        $player = self::resolvePlayer($db);
        $idPlayer = (int)$player['id_player'];
        if ($newName === (string)$player['name']) {
            sendJson(['state' => 'error_same_name']);
        }

        $db->beginTransaction();
        try {
            $existingName = $db->prepare(
                'SELECT `id_player` FROM `player` WHERE `name` = ? AND `id_player` <> ? LIMIT 1'
            );
            $existingName->execute([$newName, $idPlayer]);
            if ($existingName->fetchColumn() !== false) {
                $db->rollBack();
                sendJson(['state' => 'error_2']);
            }

            $item = $db->prepare(
                'UPDATE `player_item`
                 SET `amount` = `amount` - 1
                 WHERE `id_player` = ? AND `id_item` = ? AND `amount` >= 1'
            );
            $item->execute([$idPlayer, 'change_name']);
            if ($item->rowCount() !== 1) {
                $db->rollBack();
                sendJson(['state' => 'error_no_item']);
            }

            $db->prepare(
                'DELETE FROM `player_item`
                 WHERE `id_player` = ? AND `id_item` = ? AND `amount` <= 0'
            )->execute([$idPlayer, 'change_name']);
            $db->prepare('UPDATE `player` SET `name` = ? WHERE `id_player` = ?')
                ->execute([$newName, $idPlayer]);
            $db->commit();
        } catch (PDOException $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if ($error->getCode() === '23000') {
                sendJson(['state' => 'error_2']);
            }
            throw $error;
        }

        sendJson(['state' => 'ok', 'name' => $newName]);
    }

    public static function changePlayerAvatar($db) {
        $player = self::resolvePlayer($db);
        $avatar = filter_var(
            $_REQUEST['image_index'] ?? $_REQUEST['avatar'] ?? null,
            FILTER_VALIDATE_INT
        );
        if ($avatar === false || $avatar === null || $avatar < 0 || $avatar > 19) {
            sendJson(['state' => 'error_invalid_avatar']);
        }

        $db->prepare('UPDATE `player` SET `avatar` = ? WHERE `id_player` = ?')
            ->execute([$avatar, (int)$player['id_player']]);
        sendJson(['state' => 'ok', 'avatar' => $avatar]);
    }

    public static function changePlayerFlag($db) {
        $flag = trim((string)($_REQUEST['flag'] ?? $_REQUEST['Flag'] ?? ''));
        if (preg_match('/\A\p{L}{3}\z/u', $flag) !== 1) {
            sendJson(['state' => 'error_flag_length']);
            return;
        }
        $flag = mb_strtoupper($flag, 'UTF-8');
        if (preg_match('/\A\p{L}{3}\z/u', $flag) !== 1) {
            sendJson(['state' => 'error_flag_length']);
            return;
        }
        $player = self::resolvePlayer($db);
        $idPlayer = (int)$player['id_player'];

        $db->beginTransaction();
        try {
            $deduct = $db->prepare(
                'UPDATE `player_item`
                 SET `amount` = `amount` - 1
                 WHERE `id_player` = ? AND `id_item` = ? AND `amount` >= 1'
            );
            $deduct->execute([$idPlayer, 'flag']);
            if ($deduct->rowCount() !== 1) {
                $db->rollBack();
                sendJson(['state' => 'error_no_item']);
                return;
            }

            $db->prepare(
                'DELETE FROM `player_item`
                 WHERE `id_player` = ? AND `id_item` = ? AND `amount` <= 0'
            )->execute([$idPlayer, 'flag']);

            $db->prepare('UPDATE `player` SET `city_flag` = ? WHERE `id_player` = ?')
               ->execute([$flag, $idPlayer]);
            $db->commit();

            sendJson([
                'state'     => 'ok',
                'city_flag' => $flag,
                'id_player' => $idPlayer
            ]);
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }

    public static function getFavoriteCoords($db) {
        $player = self::resolvePlayer($db);
        $playerId = (int)$player['id_player'];

        $stmt = $db->prepare("SELECT `id_fav`, `id_player`, `title`, `x`, `y`, `created_at` FROM `player_favorite_coord` WHERE `id_player` = ? ORDER BY `id_fav` DESC");
        $stmt->execute([$playerId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$r) {
            $r['id_fav'] = (int)$r['id_fav'];
            $r['id_player'] = (int)$r['id_player'];
            $r['x'] = (int)$r['x'];
            $r['y'] = (int)$r['y'];
        }
        unset($r);
        sendJson(['state' => 'ok', 'list' => $rows]);
    }

    public static function addFavoriteCoord($db) {
        $player = self::resolvePlayer($db);
        $playerId = (int)$player['id_player'];

        $x = filter_var($_REQUEST['x'] ?? null, FILTER_VALIDATE_INT);
        $y = filter_var($_REQUEST['y'] ?? null, FILTER_VALIDATE_INT);
        $rawTitle = trim((string)($_REQUEST['title'] ?? ''));

        if ($x === false || $y === false || $x === null || $y === null || $x < 0 || $x > 499 || $y < 0 || $y > 499) {
            sendJson(['state' => 'error_coord', 'msg' => 'الإحداثيات غير صحيحة']);
        }

        $currentCityId = filter_var($_REQUEST['id_city'] ?? $_REQUEST['idCity'] ?? null, FILTER_VALIDATE_INT);
        if ($currentCityId === false || $currentCityId === null || $currentCityId <= 0) {
            sendJson(['state' => 'error_invalid_city', 'msg' => 'المدينة الحالية غير صحيحة']);
        }

        $currentCityStmt = $db->prepare(
            "SELECT `x`, `y` FROM `city` WHERE `id_city` = ? AND `id_player` = ? LIMIT 1"
        );
        $currentCityStmt->execute([$currentCityId, $playerId]);
        $currentCity = $currentCityStmt->fetch(PDO::FETCH_ASSOC);
        if (!$currentCity) {
            sendJson(['state' => 'error_auth', 'msg' => 'المدينة الحالية غير مملوكة للاعب']);
        }
        if ((int)$currentCity['x'] === $x && (int)$currentCity['y'] === $y) {
            sendJson(['state' => 'error_own_city', 'msg' => 'لا يمكنك إضافة إحداثيات مدينتك إلى المفضلة']);
        }

        // Limit favorites count (max 50 per player)
        $countStmt = $db->prepare("SELECT COUNT(*) FROM `player_favorite_coord` WHERE `id_player` = ?");
        $countStmt->execute([$playerId]);
        if ((int)$countStmt->fetchColumn() >= 50) {
            sendJson(['state' => 'error_limit', 'msg' => 'وصلت للحد الأقصى لعدد الإحداثيات المفضلة (50). احذف بعضها لإضافة المزيد']);
        }

        // Check if already in favorites
        $checkStmt = $db->prepare("SELECT `id_fav` FROM `player_favorite_coord` WHERE `id_player` = ? AND `x` = ? AND `y` = ?");
        $checkStmt->execute([$playerId, $x, $y]);
        $existingId = $checkStmt->fetchColumn();
        if ($existingId) {
            sendJson(['state' => 'error_exists', 'msg' => 'هذه الإحداثيات مضافة بالفعل في المفضلة لديك']);
        }

        $title = $rawTitle !== '' ? mb_substr($rawTitle, 0, 50) : "إحداثية [{$x}, {$y}]";

        $ins = $db->prepare("INSERT INTO `player_favorite_coord` (`id_player`, `title`, `x`, `y`) VALUES (?, ?, ?, ?)");
        $ins->execute([$playerId, $title, $x, $y]);
        $idFav = (int)$db->lastInsertId();

        $stmt = $db->prepare("SELECT `id_fav`, `id_player`, `title`, `x`, `y`, `created_at` FROM `player_favorite_coord` WHERE `id_player` = ? ORDER BY `id_fav` DESC");
        $stmt->execute([$playerId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$r) {
            $r['id_fav'] = (int)$r['id_fav'];
            $r['id_player'] = (int)$r['id_player'];
            $r['x'] = (int)$r['x'];
            $r['y'] = (int)$r['y'];
        }
        unset($r);

        sendJson(['state' => 'ok', 'id_fav' => $idFav, 'list' => $rows]);
    }

    public static function deleteFavoriteCoord($db) {
        $player = self::resolvePlayer($db);
        $playerId = (int)$player['id_player'];

        $idFav = filter_var($_REQUEST['id_fav'] ?? null, FILTER_VALIDATE_INT);
        if ($idFav === false || $idFav === null || $idFav <= 0) {
            sendJson(['state' => 'error_invalid', 'msg' => 'معرف الإحداثية غير صحيح']);
        }

        $del = $db->prepare("DELETE FROM `player_favorite_coord` WHERE `id_fav` = ? AND `id_player` = ?");
        $del->execute([$idFav, $playerId]);

        $stmt = $db->prepare("SELECT `id_fav`, `id_player`, `title`, `x`, `y`, `created_at` FROM `player_favorite_coord` WHERE `id_player` = ? ORDER BY `id_fav` DESC");
        $stmt->execute([$playerId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$r) {
            $r['id_fav'] = (int)$r['id_fav'];
            $r['id_player'] = (int)$r['id_player'];
            $r['x'] = (int)$r['x'];
            $r['y'] = (int)$r['y'];
        }
        unset($r);

        sendJson(['state' => 'ok', 'list' => $rows]);
    }

    public static function modifyFavoriteCoordTitle($db) {
        $player = self::resolvePlayer($db);
        $playerId = (int)$player['id_player'];

        $idFav = filter_var($_REQUEST['id_fav'] ?? null, FILTER_VALIDATE_INT);
        $newTitle = trim((string)($_REQUEST['title'] ?? ''));

        if ($idFav === false || $idFav === null || $idFav <= 0 || $newTitle === '') {
            sendJson(['state' => 'error_invalid', 'msg' => 'بيانات التعديل غير صحيحة']);
        }

        $title = mb_substr($newTitle, 0, 50);
        $up = $db->prepare("UPDATE `player_favorite_coord` SET `title` = ? WHERE `id_fav` = ? AND `id_player` = ?");
        $up->execute([$title, $idFav, $playerId]);

        sendJson(['state' => 'ok', 'title' => $title]);
    }
}
