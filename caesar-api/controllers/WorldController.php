<?php
// WorldController.php - Handles world map tiles, unit data, colonizers, and battles on map units
require_once __DIR__ . '/../PlayerAuth.php';
require_once __DIR__ . '/../services/HeroPowerService.php';
require_once __DIR__ . '/../services/HeroDeploymentService.php';
require_once __DIR__ . '/../services/CityBuildingService.php';

class WorldController {
    public const MAX_BATTLE_PARTICIPANTS_PER_SIDE = 250;
    public const MAX_DAILY_FREE_REAP_TIMES = 1;
    public const MAX_DAILY_EXTRA_REAP_TIMES = 5;
    public const PRIZE_RATE_SCALE = 1000;

    public static function handle($uri, $db) {
        switch ($uri) {
            case '/api/AWorld/getCityData':
                self::getCityData($db);
                return true;
            case '/api/AWorld/BuildNewCity':
                self::buildNewCity($db);
                return true;
            case '/api/AWorld/getBarrayConolizer':
            case '/api/AWorld/getBarrayColonizer':
                self::getBarrayColonizer($db);
                return true;
            case '/api/ABattelRunning/getBattels':
                self::getBattels($db);
                return true;
            case '/api/ADominant/getArmyCapitalDominant':
            case '/api/dominant.php':
            case '/api/dominant':
                self::getArmyCapitalDominant($db);
                return true;
            case '/api/ABattelRunning/getUnitBattel':
                self::getUnitBattel($db);
                return true;
            case '/api/ADominant/getCityColonized':
            case '/api/ADominant/getCityColonizer':
                sendJson([]);
                return true;
            case '/api/ABattelRunning/getLeavingHero':
                self::getLeavingHero($db);
                return true;
            case '/api/ABattelRunning/getGarrisonHeros':
                self::getGarrisonHeros($db);
                return true;
            case '/api/ABattelRunning/getSpyRuning':
                self::getSpyRunning($db);
                return true;
            case '/api/ABattelRunning/getHeroBack':
                self::getHeroBack($db);
                return true;
            case '/api/ASpy/start':
                self::startSpy($db);
                return true;
            case '/api/ASpy/cancel':
                self::cancelSpy($db);
                return true;
            case '/api/AWorldUnit/getWorldUnitDominator':
                self::getBarrayColonizer($db);
                return true;
            case '/api/AWorldUnit/supportByHero':
                self::supportByHero($db);
                return true;
            case '/api/AWorldUnit/transHero':
                sendJson(['state' => 'ok']);
                return true;
            case '/api/AWorldUnit/plundePrize':
            case '/api/Guild/harvestDominance':
                self::harvestDominance($db);
                return true;
            case '/api/AWorldUnit/checkReapCharge':
            case '/api/Guild/checkReapCharge':
                self::checkReapCharge($db);
                return true;
            case '/api/AWorld/refreshWorldUnitLvl':
                self::refreshWorldUnitLvl($db);
                return true;
            case '/api/AWorld/getUnitViewPrizes':
            case '/api/AWorldUnit/getUnitViewPrizes':
                self::getUnitViewPrizes();
                return true;
            case '/api/AWorld/getGuildAttackQue':
                self::getGuildAttackQue($db);
                return true;
            case '/api/AWorld/getWorldUnitRank':
                self::getWorldUnitRank($db);
                return true;
            case '/api/ABattel/applyForRoleInAttQue':
                self::applyForRoleInAttQue($db);
                return true;
            case '/api/ABattelRuning/refreshBattelData':
                self::refreshBattelData($db);
                return true;
            case '/api/ABattel/joinBattel':
                self::joinBattel($db);
                return true;
            case '/api/ADominant/abondonColonizedCity':
            case '/api/ADominant/fireColonizer':
                sendJson(['state' => 'ok']);
                return true;
            case '/api/Guild/getGuildDominance':
                self::getGuildDominance($db);
                return true;
            case '/Battel/abort':
            case '/api/Battel/abort':
                sendJson(['state' => 'ok']);
                return true;
            case '/Battel/getAllWorldBattels':
            case '/api/Battel/getAllWorldBattels':
                self::getBattels($db);
                return true;
            case '/World/getWorldCity':
            case '/api/World/getWorldCity':
                self::getCityData($db);
                return true;
            case '/Chat/sendMsg':
            case '/api/Chat/sendMsg':
            case '/Chat/sendPrivate':
            case '/api/Chat/sendPrivate':
                sendJson(['state' => 'ok']);
                return true;
        }
        return false;
    }

    public static function getArmyCapitalDominant($db = null) {
        $worldFile = self::resolveJsonPath('WorldBarrary.json');
        $unitFile = self::resolveJsonPath('worldUnitData.json');

        if (!$worldFile || !is_readable($worldFile) || !$unitFile || !is_readable($unitFile)) {
            error_log('[WorldController] Army capital source data is unavailable');
            http_response_code(500);
            sendJson(['state' => 'error', 'msg' => 'Army capital source data is unavailable']);
        }

        $worldData = json_decode(file_get_contents($worldFile), true);
        $unitData = json_decode(file_get_contents($unitFile), true);
        if (!is_string($worldData) || !is_array($unitData)) {
            error_log('[WorldController] Army capital source data is invalid');
            http_response_code(500);
            sendJson(['state' => 'error', 'msg' => 'Army capital source data is invalid']);
        }

        $capitalTypes = [150, 151, 152, 153, 154, 155, 105];
        $capitalByType = [];
        foreach (explode(',', $worldData) as $record) {
            $fields = explode('_', $record);
            if (count($fields) !== 6) {
                continue;
            }

            $type = (int)$fields[2];
            if (!in_array($type, $capitalTypes, true)) {
                continue;
            }

            $capitalByType[$type] = [
                'x' => (int)$fields[0],
                'y' => (int)$fields[1],
            ];
        }

        $dominanceByCoord = [];
        if (!$db) {
            try {
                if (function_exists('getDB')) {
                    $db = getDB();
                }
            } catch (\Throwable $e) {
                $db = null;
            }
        }

        if ($db instanceof PDO) {
            try {
                $stmt = $db->query(
                    'SELECT gd.x_coord, gd.y_coord, gd.unit_type, gd.conquered_at, gd.id_guild, gd.id_player, ' .
                    'g.name AS GuildName, p.name AS PlayerName ' .
                    'FROM guild_dominance gd ' .
                    'LEFT JOIN guild g ON g.id_guild = gd.id_guild ' .
                    'LEFT JOIN player p ON p.id_player = gd.id_player'
                );
                if ($stmt) {
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $key = (int)$row['x_coord'] . '_' . (int)$row['y_coord'];
                        $dominanceByCoord[$key] = $row;
                    }
                }
            } catch (\Throwable $e) {
                error_log('[WorldController] getArmyCapitalDominant query failed: ' . $e->getMessage());
            }
        }

        $capitals = [];
        foreach ($capitalTypes as $type) {
            if (!isset($capitalByType[$type], $unitData[$type])
                || !is_array($unitData[$type])
                || !isset($unitData[$type]['Title'])
                || !is_string($unitData[$type]['Title'])) {
                error_log('[WorldController] Army capital data is incomplete for type ' . $type);
                http_response_code(500);
                sendJson(['state' => 'error', 'msg' => 'Army capital data is incomplete']);
            }

            $x = (int)$capitalByType[$type]['x'];
            $y = (int)$capitalByType[$type]['y'];
            $coordKey = $x . '_' . $y;

            $rank = [];
            if (isset($dominanceByCoord[$coordKey])) {
                $dom = $dominanceByCoord[$coordKey];
                $guildName = trim((string)($dom['GuildName'] ?? ''));
                $playerName = trim((string)($dom['PlayerName'] ?? ''));

                if ($guildName !== '' && $playerName !== '') {
                    $displayName = $guildName . ' (' . $playerName . ')';
                } elseif ($guildName !== '') {
                    $displayName = $guildName;
                } elseif ($playerName !== '') {
                    $displayName = $playerName;
                } else {
                    $displayName = '---';
                }

                $conqueredAt = (int)($dom['conquered_at'] ?? 0);
                $rank[] = [
                    'name' => $displayName,
                    'GuildName' => $guildName,
                    'PlayerName' => $playerName,
                    'time_stamp' => $conqueredAt,
                    'd_sum' => max(0, time() - $conqueredAt),
                    'id_guild' => (int)($dom['id_guild'] ?? 0),
                    'id_player' => (int)($dom['id_player'] ?? 0),
                ];
            }

            $capitals[] = [
                'title' => $unitData[$type]['Title'],
                'x' => $x,
                'y' => $y,
                'type' => $type,
                'rank' => $rank,
            ];
        }

        sendJson([
            'state' => 'ok',
            'type' => 'domain',
            'dominanceDataAvailable' => true,
            'unit' => $capitals,
        ]);
    }

    public static function getCityData($db) {
        $x = isset($_REQUEST['xCoord']) ? (int)$_REQUEST['xCoord'] : 0;
        $y = isset($_REQUEST['yCoord']) ? (int)$_REQUEST['yCoord'] : 0;
        $stmt = $db->prepare("SELECT c.name as CityName, c.id_city, c.id_player, p.name as PlayerName, p.prestige, p.avatar, p.guild as GuildName FROM city c JOIN player p ON c.id_player = p.id_player WHERE c.x = ? AND c.y = ? LIMIT 1");
        $stmt->execute([$x, $y]);
        $cityData = $stmt->fetch();
        if ($cityData) {
            $cityData['id_player'] = (int)$cityData['id_player'];
            $cityData['id_city']   = (int)$cityData['id_city'];
            $cityData['prestige']  = (int)$cityData['prestige'];
            $cityData['avatar']    = (int)$cityData['avatar'];
            $cityData['GuildName'] = $cityData['GuildName'] ?: 'بدون تحالف';
        } else {
            $cityData = [
                'id_player'  => 0,
                'id_city'    => 0,
                'PlayerName' => 'حاكم مجهول',
                'CityName'   => 'مدينة مهجورة',
                'prestige'   => 0,
                'GuildName'  => 'بدون تحالف',
                'avatar'     => 0
            ];
        }
        sendJson($cityData);
    }

    public static function getBarrayColonizer($db) {
        $x = isset($_REQUEST['xCoord']) ? (int)$_REQUEST['xCoord'] : 0;
        $y = isset($_REQUEST['yCoord']) ? (int)$_REQUEST['yCoord'] : 0;
        $stmt = $db->prepare("SELECT c.name as CityName, c.x, c.y, p.name as PlayerName, p.guild as GuildName 
                              FROM city_barray b 
                              JOIN city c ON b.id_city = c.id_city 
                              JOIN player p ON b.id_player = p.id_player 
                              WHERE b.x_coord = ? AND b.y_coord = ? LIMIT 1");
        $stmt->execute([$x, $y]);
        $row = $stmt->fetch();
        if ($row) {
            $row['x'] = (int)$row['x'];
            $row['y'] = (int)$row['y'];
            $row['GuildName'] = $row['GuildName'] ?: 'بدون تحالف';
            sendJson([$row]);
        }
        sendJson([]);
    }

    public static function startSpy($db) {
        $player = requireAuthenticatedPlayer($db);
        $idCity = filter_var($_REQUEST['idCity'] ?? null, FILTER_VALIDATE_INT);
        $spyNum = filter_var($_REQUEST['spyNum'] ?? null, FILTER_VALIDATE_INT);
        $xCoord = filter_var($_REQUEST['xCoord'] ?? null, FILTER_VALIDATE_INT);
        $yCoord = filter_var($_REQUEST['yCoord'] ?? null, FILTER_VALIDATE_INT);

        if (!$idCity || !$spyNum || $spyNum <= 0 || $xCoord === false || $yCoord === false
            || $xCoord < 0 || $xCoord >= 500 || $yCoord < 0 || $yCoord >= 500) {
            sendJson(['state' => 'error_1']);
            return;
        }

        $ownedCity = requirePlayerOwnedCity($db, $player, $idCity);
        $playerId = (int)$player['id_player'];
        $cityId = (int)$ownedCity['id_city'];

        $lock = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("SELECT * FROM `city` WHERE `id_city` = ? AND `id_player` = ?{$lock}");
            $stmt->execute([$cityId, $playerId]);
            $city = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$city || (int)$city['spies'] < $spyNum) {
                $db->rollBack();
                sendJson(['state' => 'error_spies_not_enough']);
                return;
            }

            $deduct = $db->prepare("UPDATE `city` SET `spies` = `spies` - ? WHERE `id_city` = ? AND `id_player` = ?");
            $deduct->execute([$spyNum, $cityId, $playerId]);

            $targetCityStmt = $db->prepare(
                'SELECT id_city, id_player, name, x, y, food, wood, stone, metal, coin ' .
                'FROM city WHERE x = ? AND y = ? LIMIT 1'
            );
            $targetCityStmt->execute([$xCoord, $yCoord]);
            $targetCity = $targetCityStmt->fetch(PDO::FETCH_ASSOC);

            $targetUnit = self::getStaticWorldUnit($xCoord, $yCoord);
            $targetType = (int)($targetUnit['ut'] ?? 0);
            $targetLevel = (int)($targetUnit['lvl'] ?? 0);
            $spyFor = $targetCity ? 'city' : 'barrary';
            $distance = abs((int)$ownedCity['x'] - $xCoord) + abs((int)$ownedCity['y'] - $yCoord);
            $duration = max(60, min(3600, $distance * 10));
            $now = time();
            $details = json_encode([
                'x_to' => $xCoord,
                'y_to' => $yCoord,
                'spy_num' => $spyNum,
                'spy_for' => $spyFor,
                'target_type' => $targetType,
                'target_level' => $targetLevel,
                'target_city_id' => $targetCity ? (int)$targetCity['id_city'] : 0,
                'target_player_id' => $targetCity ? (int)$targetCity['id_player'] : 0,
            ], JSON_UNESCAPED_UNICODE);

            $taskStmt = $db->prepare(
                'INSERT INTO timed_task ' .
                '(id_city, id_player, task_type, item_id, lvl_to, start_time, time_arrive, details) ' .
                'VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $taskStmt->execute([
                $cityId,
                $playerId,
                'spy',
                'spy',
                $spyNum,
                $now,
                $now + $duration,
                $details,
            ]);

            $stmt->execute([$cityId, $playerId]);
            $updatedCity = $stmt->fetch(PDO::FETCH_ASSOC);
            $db->commit();

            foreach ($updatedCity as $k => $v) {
                if (is_numeric($v)) $updatedCity[$k] = (int)$v;
            }

            sendJson([
                'state' => 'ok',
                'City'  => $updatedCity
            ]);
            return;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            sendJson(['state' => 'error_db']);
            return;
        }
    }

    public static function cancelSpy($db) {
        $player = requireAuthenticatedPlayer($db);
        $idCity = filter_var($_REQUEST['idCity'] ?? null, FILTER_VALIDATE_INT);
        $idSpy = filter_var($_REQUEST['idSpy'] ?? null, FILTER_VALIDATE_INT);
        $ownedCity = requirePlayerOwnedCity($db, $player, $idCity);
        $playerId = (int)$player['id_player'];
        $cityId = (int)$ownedCity['id_city'];

        $db->beginTransaction();
        try {
            $lock = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
            $taskStmt = $db->prepare(
                "SELECT id_task, id_city, id_player, lvl_to FROM timed_task " .
                "WHERE id_task = ? AND id_city = ? AND id_player = ? AND task_type = 'spy'{$lock}"
            );
            $taskStmt->execute([$idSpy, $cityId, $playerId]);
            $task = $taskStmt->fetch(PDO::FETCH_ASSOC);
            if (!$task) {
                $db->rollBack();
                sendJson(['state' => 'error_spy_not_found']);
                return;
            }

            $refund = (int)$task['lvl_to'];
            $db->prepare(
                'UPDATE city SET spies = spies + ? WHERE id_city = ? AND id_player = ?'
            )->execute([$refund, $cityId, $playerId]);
            $db->prepare("DELETE FROM timed_task WHERE id_task = ? AND task_type = 'spy'")
                ->execute([(int)$task['id_task']]);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            sendJson(['state' => 'error_db']);
            return;
        }

        $stmt = $db->prepare("SELECT * FROM `city` WHERE `id_city` = ? AND `id_player` = ?");
        $stmt->execute([$cityId, $playerId]);
        $city = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($city) {
            foreach ($city as $k => $v) {
                if (is_numeric($v)) $city[$k] = (int)$v;
            }
        }

        sendJson([
            'state' => 'ok',
            'City'  => $city ?: $ownedCity
        ]);
    }

    public static function getSpyRunning($db) {
        $player = requireAuthenticatedPlayer($db);
        $playerId = (int)$player['id_player'];
        $stmt = $db->prepare(
            "SELECT id_task, id_city, lvl_to, time_arrive, details " .
            "FROM timed_task WHERE id_player = ? AND task_type = 'spy' " .
            "ORDER BY time_arrive ASC"
        );
        $stmt->execute([$playerId]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $running = [];
        foreach ($tasks as $task) {
            $details = json_decode((string)($task['details'] ?? ''), true);
            if (!is_array($details)) {
                continue;
            }
            $running[] = [
                'id_spy' => (int)$task['id_task'],
                'id_city' => (int)$task['id_city'],
                'x_to' => (int)($details['x_to'] ?? 0),
                'y_to' => (int)($details['y_to'] ?? 0),
                'spy_num' => (int)($details['spy_num'] ?? $task['lvl_to']),
                'time_arrive' => (int)$task['time_arrive'],
                'spy_for' => (string)($details['spy_for'] ?? 'barrary'),
            ];
        }
        sendJson($running);
    }

    public static function getBattels($db) {
        $stmt = $db->query("SELECT b.*, p.name as PlayerName, c.name as CityName,
                                   (SELECT COUNT(*) FROM world_battel_member m
                                    WHERE m.id_battel = b.id_battel AND m.side = 1
                                      AND m.state IN ('marching', 'won', 'lost')) AS attackNum,
                                   (SELECT COUNT(*) FROM world_battel_member m
                                    WHERE m.id_battel = b.id_battel AND m.side = 0
                                      AND m.state IN ('marching', 'won', 'lost')) AS defenceNum,
                                   (b.time_arrive <= UNIX_TIMESTAMP()) AS battle_pending
                            FROM world_battel b 
                            JOIN player p ON b.id_player = p.id_player 
                            JOIN city c ON b.id_city = c.id_city 
                            WHERE b.state = 'marching'
                            ORDER BY b.id_battel DESC");
        $rows = $stmt->fetchAll() ?: [];
        foreach ($rows as &$r) {
            foreach (['id_battel', 'id_player', 'id_city', 'id_hero', 'task', 'x_coord', 'y_coord', 'time_start', 'time_arrive', 'battle_pending'] as $col) {
                if (isset($r[$col])) $r[$col] = (int)$r[$col];
            }
            $r['attackNum']  = (int)$r['attackNum'];
            $r['defenceNum'] = (int)$r['defenceNum'];
            // Strip origin coordinates — exposing x_city/y_city reveals troop movements to all players.
            unset($r['x_city'], $r['y_city']);
        }
        sendJson($rows);
    }

    public static function getUnitBattel($db) {
        $x = filter_var($_REQUEST['xCoord'] ?? null, FILTER_VALIDATE_INT);
        $y = filter_var($_REQUEST['yCoord'] ?? null, FILTER_VALIDATE_INT);
        if ($x === false || $y === false || $x === null || $y === null || $x < 0 || $x > 499 || $y < 0 || $y > 499) {
            http_response_code(400);
            sendJson(['state' => 'error_invalid_coordinates']);
            return;
        }

        $stmt = $db->prepare("SELECT b.*, p.name AS PlayerName, c.name AS CityName,
                                     (SELECT COUNT(*) FROM world_battel_member m
                                      WHERE m.id_battel = b.id_battel AND m.side = 1
                                        AND m.state IN ('marching', 'won', 'lost')) AS attackNum,
                                     (SELECT COUNT(*) FROM world_battel_member m
                                      WHERE m.id_battel = b.id_battel AND m.side = 0
                                        AND m.state IN ('marching', 'won', 'lost')) AS defenceNum,
                                     (b.time_arrive <= UNIX_TIMESTAMP()) AS battle_pending
                              FROM world_battel b
                              JOIN player p ON p.id_player = b.id_player
                              JOIN city c ON c.id_city = b.id_city
                              WHERE b.x_coord = ? AND b.y_coord = ?
                                AND b.state = 'marching'
                              ORDER BY b.id_battel DESC");
        $stmt->execute([$x, $y]);
        $rows = $stmt->fetchAll() ?: [];
        foreach ($rows as &$row) {
            foreach (['id_battel', 'id_player', 'id_city', 'id_hero', 'task', 'x_coord', 'y_coord', 'time_start', 'time_arrive', 'battle_pending'] as $column) {
                if (isset($row[$column])) $row[$column] = (int)$row[$column];
            }
            $row['attackNum']  = (int)$row['attackNum'];
            $row['defenceNum'] = (int)$row['defenceNum'];
            // Strip origin coordinates — exposing x_city/y_city reveals troop movements to all players.
            unset($row['x_city'], $row['y_city']);
        }
        sendJson($rows);
    }

    public static function refreshBattelData($db) {
        $idBattle = filter_var($_REQUEST['idBattel'] ?? null, FILTER_VALIDATE_INT);
        if ($idBattle === false || $idBattle === null || $idBattle <= 0) {
            http_response_code(400);
            sendJson(['state' => 'error_invalid_battle']);
            return;
        }

        $stmt = $db->prepare("SELECT b.id_battel, (b.time_arrive <= UNIX_TIMESTAMP()) AS battle_pending,
                                     (SELECT COUNT(*) FROM world_battel_member m
                                      WHERE m.id_battel = b.id_battel AND m.side = 1
                                        AND m.state IN ('marching', 'won', 'lost')) AS attackNum,
                                     (SELECT COUNT(*) FROM world_battel_member m
                                      WHERE m.id_battel = b.id_battel AND m.side = 0
                                        AND m.state IN ('marching', 'won', 'lost')) AS defenceNum
                              FROM world_battel b
                              WHERE b.id_battel = ? AND b.state = 'marching'
                              LIMIT 1");
        $stmt->execute([$idBattle]);
        $battle = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$battle) {
            http_response_code(404);
            sendJson(['state' => 'error_battle_not_found']);
            return;
        }
        sendJson([
            'state' => 'ok',
            'attackNum' => (int)$battle['attackNum'],
            'defenceNum' => (int)$battle['defenceNum'],
            'battle_pending' => (int)$battle['battle_pending']
        ]);
    }

    private static function resolveJsonPath($filename) {
        $candidates = [
            dirname(__DIR__, 2) . '/caesar-assets/api/js-0.0.24/json/' . $filename,
            dirname(__DIR__, 2) . '/caesar-assets/web/js-3.4.113/json/' . $filename,
            dirname(__DIR__, 2) . '/caesar-assets/api/js-3.4.113/json/' . $filename,
            __DIR__ . '/../../caesar-assets/api/js-0.0.24/json/' . $filename,
            __DIR__ . '/../../caesar-assets/web/js-3.4.113/json/' . $filename,
            __DIR__ . '/../../caesar-assets/api/js-3.4.113/json/' . $filename,
            __DIR__ . '/../js-3.4.113/json/' . $filename,
        ];
        foreach ($candidates as $cand) {
            if (is_readable($cand)) {
                return $cand;
            }
        }
        return false;
    }

    private static function getStaticWorldUnit($x, $y) {
        static $units = null;
        if ($units === null) {
            $path = self::resolveJsonPath('WorldBarrary.json') ?: (__DIR__ . '/../../caesar-assets/api/js-0.0.24/json/WorldBarrary.json');
            $raw = is_readable($path) ? file_get_contents($path) : false;
            $map = $raw === false ? null : json_decode($raw, true);
            if (!is_string($map)) {
                throw new RuntimeException('World map source data is unavailable or invalid');
            }
            $units = [];
            foreach (explode(',', $map) as $entry) {
                $parts = explode('_', $entry);
                if (count($parts) >= 6) {
                    $units[$parts[0] . '_' . $parts[1]] = [
                        'ut' => (int)$parts[2],
                        'lvl' => (int)$parts[3],
                        'subtype' => (int)$parts[5]
                    ];
                }
            }
        }
        return $units[$x . '_' . $y] ?? null;
    }

    private static function getWorldUnitTypeData() {
        static $unitData = null;
        if ($unitData === null) {
            $path = self::resolveJsonPath('worldUnitData.json');
            $raw = ($path && is_readable($path)) ? file_get_contents($path) : false;
            $unitData = $raw === false ? null : json_decode($raw, true);
            if (!is_array($unitData)) {
                throw new RuntimeException('World unit requirements are unavailable or invalid');
            }
        }
        return $unitData;
    }

    private static function isGuildDominanceTarget(int $unitType): bool {
        return ($unitType >= 180 && $unitType <= 182)
            || ($unitType >= 184 && $unitType <= 186)
            || ($unitType >= 150 && $unitType <= 155)
            || $unitType === 105;
    }

    public static function canJoinGuildDefense(
        int $unitType,
        int $dominantGuildId,
        int $attackingGuildId,
        int $joiningGuildId
    ): bool {
        return self::isGuildDominanceTarget($unitType)
            && $dominantGuildId > 0
            && $attackingGuildId > 0
            && $dominantGuildId !== $attackingGuildId
            && $joiningGuildId === $dominantGuildId;
    }

    public static function getGuildDefenseJoinMarchDuration($x1, $y1, $x2, $y2, $army, $config): int {
        return self::getJoinMarchDuration($x1, $y1, $x2, $y2, $army, 0, false, $config);
    }

    public static function getJoinMarchDuration($x1, $y1, $x2, $y2, $army, $unitType, $isCity, $config, $applySpeedMultiplier = true) {
        $deltaX = abs((int)$x1 - (int)$x2);
        $deltaY = abs((int)$y1 - (int)$y2);
        if ($deltaX > 249) $deltaX = 500 - $deltaX;
        if ($deltaY > 249) $deltaY = 500 - $deltaY;
        $distance = floor(sqrt($deltaX * $deltaX + $deltaY * $deltaY)) * 6000;

        $speedByType = [1 => 300, 2 => 900, 3 => 600, 4 => 250, 5 => 150, 6 => 100];
        $speeds = [];
        foreach (['f_1', 'f_2', 'f_3', 'b_1', 'b_2', 'b_3'] as $slot) {
            $type = (int)($army[$slot . '_type'] ?? 0);
            $count = (int)($army[$slot . '_num'] ?? 0);
            if ($count > 0 && isset($speedByType[$type])) $speeds[] = $speedByType[$type];
        }
        $speed = $speeds ? min($speeds) : 900;
        $duration = max(1, (int)ceil($distance / $speed));
        $unitType = (int)$unitType;
        if ($unitType === 177) {
            $duration = 300;
        } elseif ($unitType >= 184 && $unitType <= 186) {
            $duration = 3600;
        } elseif (($unitType >= 180 && $unitType <= 182) || ($unitType >= 231 && $unitType <= 236)) {
            $duration = 900;
        } elseif ($unitType >= 71 && $unitType <= 80) {
            $campMinimum = max(120, (int)($config['camp_rally_duration_sec'] ?? 120));
            $duration = max($duration, $campMinimum);
        } elseif ($unitType === 70
            || ($unitType >= 81 && $unitType <= 105)
            || ($unitType >= 150 && $unitType <= 155)
            || ($unitType >= 175 && $unitType <= 177)
            || ($unitType >= 200 && $unitType <= 205)) {
            $duration = (int)($config['camp_rally_duration_sec'] ?? 120);
        } elseif ($isCity || ($unitType >= 60 && $unitType <= 63)) {
            $duration = max((int)($config['city_attack_min_duration_sec'] ?? 900), $duration);
        }
        if ($applySpeedMultiplier) {
            $duration = (int)ceil($duration / max(0.1, (float)($config['march_speed_multiplier'] ?? 1)));
        }
        return max(1, $duration);
    }

    public static function getReturnMarchDuration($x1, $y1, $x2, $y2, $army, $unitType, $config) {
        $duration = self::getJoinMarchDuration($x1, $y1, $x2, $y2, $army, 0, false, $config, false);
        $unitType = (int)$unitType;
        $hasMaximumReturnDuration = ($unitType >= 70 && $unitType <= 105)
            || ($unitType >= 150 && $unitType <= 155)
            || ($unitType >= 175 && $unitType <= 176)
            || ($unitType >= 200 && $unitType <= 205);
        if (!$hasMaximumReturnDuration) {
            return $duration;
        }

        $configuredMaximum = (int)($config['camp_rally_duration_sec'] ?? 120);
        return min($duration, max(1, $configuredMaximum));
    }

    public static function joinBattel($db) {
        $player = requireAuthenticatedPlayer($db);
        $idBattle = filter_var($_REQUEST['idBattel'] ?? null, FILTER_VALIDATE_INT);
        $idHero = filter_var($_REQUEST['idHero'] ?? null, FILTER_VALIDATE_INT);
        $side = filter_var($_REQUEST['side'] ?? null, FILTER_VALIDATE_INT);
        if ($idBattle === false || $idBattle === null || $idBattle <= 0
            || $idHero === false || $idHero === null || $idHero <= 0
            || ($side !== 0 && $side !== 1)) {
            sendJson(['state' => 'error_8_1']);
            return;
        }

        $playerId = (int)$player['id_player'];
        $now = time();
        $lock = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
        $db->beginTransaction();
        try {
            $battleStmt = $db->prepare(
                "SELECT b.*, p.id_guild, p.name AS PlayerName " .
                "FROM world_battel b JOIN player p ON p.id_player = b.id_player " .
                "WHERE b.id_battel = ? AND b.state = 'marching' AND b.time_arrive > ?{$lock}"
            );
            $battleStmt->execute([$idBattle, $now]);
            $battle = $battleStmt->fetch(PDO::FETCH_ASSOC);
            if (!$battle) {
                $db->rollBack();
                sendJson(['state' => 'error_2']);
                return;
            }
            $battleCityStmt = $db->prepare('SELECT name FROM city WHERE id_city = ? LIMIT 1');
            $battleCityStmt->execute([(int)$battle['id_city']]);
            $battle['CityName'] = $battleCityStmt->fetchColumn();
            if ($battle['CityName'] === false) {
                $db->rollBack();
                sendJson(['state' => 'error_2']);
                return;
            }

            $heroStmt = $db->prepare(
                "SELECT h.*, c.id_city, c.x AS x_city, c.y AS y_city, c.attack_cooldown_until, p.id_guild " .
                "FROM city_hero h JOIN city c ON c.id_city = h.id_city " .
                "JOIN player p ON p.id_player = h.id_player " .
                "WHERE h.id_hero = ? AND h.id_player = ? AND h.in_city = 1{$lock}"
            );
            $heroStmt->execute([$idHero, $playerId]);
            $hero = $heroStmt->fetch(PDO::FETCH_ASSOC);
            if (!$hero || (int)$hero['console'] !== 0) {
                $db->rollBack();
                sendJson(['state' => 'error_1']);
                return;
            }

            if ($side === 0 && !empty($hero['attack_cooldown_until']) && (int)$hero['attack_cooldown_until'] > $now) {
                $db->rollBack();
                sendJson([
                    'state' => 'error_city_move_cooldown',
                    'msg' => 'لا يمكن إرسال أو استخدام القوات بعد تغيير موقع المدينة ولمدة 24 ساعة من وقت النقل'
                ]);
                return;
            }

            $armyStmt = $db->prepare("SELECT * FROM hero_army WHERE id_hero = ?{$lock}");
            $armyStmt->execute([$idHero]);
            $army = $armyStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $troops = 0;
            foreach (['f_1', 'f_2', 'f_3', 'b_1', 'b_2', 'b_3'] as $slot) {
                $troops += (int)($army[$slot . '_num'] ?? 0);
            }
            if ($troops <= 0) {
                $db->rollBack();
                sendJson(['state' => 'error_8_1']);
                return;
            }

            $typeData = self::getWorldUnitTypeData();
            $unitType = 0;
            $unitLevel = 1;
            $subtype = 0;
            $targetCityStmt = $db->prepare('SELECT id_city, id_player, lvl FROM city WHERE x = ? AND y = ? LIMIT 1');
            $targetCityStmt->execute([(int)$battle['x_coord'], (int)$battle['y_coord']]);
            $targetCity = $targetCityStmt->fetch(PDO::FETCH_ASSOC);
            if ($targetCity) {
                $unitType = 60;
                $unitLevel = max(1, (int)$targetCity['lvl']);
                if ($side === 0) {
                    $canDefend = (int)$targetCity['id_player'] === $playerId;
                    if (!$canDefend) {
                        $ownerStmt = $db->prepare('SELECT id_guild FROM player WHERE id_player = ? LIMIT 1');
                        $ownerStmt->execute([(int)$targetCity['id_player']]);
                        $ownerGuild = (int)$ownerStmt->fetchColumn();
                        $canDefend = $ownerGuild > 0 && (int)$hero['id_guild'] === $ownerGuild;
                    }
                    if (!$canDefend) {
                        $sovereignStmt = $db->prepare(
                            'SELECT 1 FROM city_tributary WHERE id_city = ? AND id_sovereign_city = ? LIMIT 1'
                        );
                        $sovereignStmt->execute([(int)$targetCity['id_city'], (int)$hero['id_city']]);
                        $canDefend = $sovereignStmt->fetchColumn() !== false;
                    }
                    if (!$canDefend) {
                        $db->rollBack();
                        sendJson(['state' => 'error_5']);
                        return;
                    }
                }
            } else {
                $barrayStmt = $db->prepare('SELECT Type, lvl, id_player FROM city_barray WHERE x_coord = ? AND y_coord = ? LIMIT 1');
                $barrayStmt->execute([(int)$battle['x_coord'], (int)$battle['y_coord']]);
                $barray = $barrayStmt->fetch(PDO::FETCH_ASSOC);
                if ($barray) {
                    $unitType = (int)$barray['Type'];
                    $unitLevel = max(1, (int)$barray['lvl']);
                } else {
                    $worldUnit = self::getStaticWorldUnit((int)$battle['x_coord'], (int)$battle['y_coord']);
                    if ($worldUnit) {
                        $unitType = (int)$worldUnit['ut'];
                        $unitLevel = max(1, (int)$worldUnit['lvl']);
                        $subtype = (int)$worldUnit['subtype'];
                        $stateStmt = $db->prepare(
                            'SELECT base_lvl, current_lvl, next_reset_at FROM world_unit_state ' .
                            'WHERE x_coord = ? AND y_coord = ? AND unit_type = ? AND unit_subtype = ? LIMIT 1'
                        );
                        $stateStmt->execute([(int)$battle['x_coord'], (int)$battle['y_coord'], $unitType, $subtype]);
                        $worldState = $stateStmt->fetch(PDO::FETCH_ASSOC);
                        if ($worldState) {
                            $unitLevel = (int)$worldState['next_reset_at'] > 0 && (int)$worldState['next_reset_at'] <= $now
                                ? (int)$worldState['base_lvl']
                                : (int)$worldState['current_lvl'];
                        }
                    }
                }
            }
            if (!$unitType) {
                $db->rollBack();
                sendJson(['state' => 'error_8_1']);
                return;
            }

            if ($side === 1) {
                $hostGuild = (int)$battle['id_guild'];
                if (($hostGuild > 0 && (int)$hero['id_guild'] !== $hostGuild)
                    || ($hostGuild <= 0 && (int)$battle['id_player'] !== $playerId)) {
                    $db->rollBack();
                    sendJson(['state' => 'error_5']);
                    return;
                }
            } elseif (!$targetCity) {
                if (!self::isGuildDominanceTarget($unitType)) {
                    $db->rollBack();
                    sendJson(['state' => 'error_5']);
                    return;
                }
                $dominanceStmt = $db->prepare(
                    'SELECT gd.id_guild FROM guild_dominance gd ' .
                    'JOIN guild_member gm ON gm.id_guild = gd.id_guild AND gm.id_player = ? ' .
                    "WHERE gd.x_coord = ? AND gd.y_coord = ?{$lock}"
                );
                $dominanceStmt->execute([
                    $playerId,
                    (int)$battle['x_coord'],
                    (int)$battle['y_coord']
                ]);
                $dominantGuildId = (int)$dominanceStmt->fetchColumn();
                if (!self::canJoinGuildDefense(
                    $unitType,
                    $dominantGuildId,
                    (int)$battle['id_guild'],
                    (int)$hero['id_guild']
                )) {
                    $db->rollBack();
                    sendJson(['state' => 'error_5']);
                    return;
                }
            }

            $unitTypeData = $typeData[(string)$unitType] ?? [];
            $hero = HeroPowerService::recover($db, $hero, $now);
            $requiredPower = HeroPowerService::getRequiredPower(
                $side === 0 && !$targetCity ? 3 : (int)$battle['task'],
                (int)($unitTypeData['reqFitness'] ?? 0)
            );
            if ((int)$hero['power'] < $requiredPower) {
                $db->commit();
                sendJson(['state' => 'error_7']);
                return;
            }
            $configStmt = $db->query(
                "SELECT config_key, config_value FROM server_config " .
                "WHERE config_key IN ('march_speed_multiplier', 'camp_rally_duration_sec', 'city_attack_min_duration_sec')"
            );
            $config = [
                'march_speed_multiplier' => 1,
                'camp_rally_duration_sec' => 120,
                'city_attack_min_duration_sec' => 900
            ];
            foreach ($configStmt->fetchAll(PDO::FETCH_ASSOC) as $configRow) {
                $config[$configRow['config_key']] = (float)$configRow['config_value'];
            }
            $joinDuration = $side === 0 && !$targetCity
                ? self::getGuildDefenseJoinMarchDuration(
                    (int)$hero['x_city'],
                    (int)$hero['y_city'],
                    (int)$battle['x_coord'],
                    (int)$battle['y_coord'],
                    $army,
                    $config
                )
                : self::getJoinMarchDuration(
                    (int)$hero['x_city'],
                    (int)$hero['y_city'],
                    (int)$battle['x_coord'],
                    (int)$battle['y_coord'],
                    $army,
                    $unitType,
                    $unitType === 60,
                    $config
                );
            if ($now + $joinDuration > (int)$battle['time_arrive']) {
                $db->rollBack();
                sendJson(['state' => 'error_2']);
                return;
            }

            if (!HeroDeploymentService::hasAvailableOutsideSlot(
                $db,
                (int)$hero['id_city'],
                $playerId
            )) {
                $db->rollBack();
                sendJson(['state' => 'error_9']);
                return;
            }

            $countStmt = $db->prepare(
                "SELECT COUNT(*) FROM world_battel_member WHERE id_battel = ? AND side = ? AND state = 'marching'"
            );
            $countStmt->execute([$idBattle, $side]);
            if ((int)$countStmt->fetchColumn() >= self::MAX_BATTLE_PARTICIPANTS_PER_SIDE) {
                $db->rollBack();
                sendJson(['state' => 'error_3']);
                return;
            }

            foreach ($unitTypeData['JoinReq'] ?? [] as $requirement) {
                $item = (string)($requirement['Item'] ?? '');
                $amount = (int)($requirement['amount'] ?? 0);
                if ($item === '' || $amount <= 0) {
                    throw new RuntimeException('Invalid world-unit join requirement');
                }
                $itemStmt = $db->prepare(
                    'UPDATE player_item SET amount = amount - ? WHERE id_player = ? AND id_item = ? AND amount >= ?'
                );
                $itemStmt->execute([$amount, $playerId, $item, $amount]);
                if ($itemStmt->rowCount() !== 1) {
                    $db->rollBack();
                    sendJson(['state' => 'error_6']);
                    return;
                }
            }

            $arriveAt = $now + $joinDuration;
            $returnDuration = self::getReturnMarchDuration(
                (int)$hero['x_city'],
                (int)$hero['y_city'],
                (int)$battle['x_coord'],
                (int)$battle['y_coord'],
                $army,
                $unitType,
                $config
            );
            $heroUpdate = $db->prepare(
                'UPDATE city_hero SET in_city = 0, power = GREATEST(0, power - ?), power_ls = ? ' .
                'WHERE id_hero = ? AND id_player = ? AND in_city = 1 AND power >= ?'
            );
            $heroUpdate->execute([
                HeroPowerService::ACTION_COST,
                $now,
                $idHero,
                $playerId,
                $requiredPower
            ]);
            if ($heroUpdate->rowCount() !== 1) {
                $db->rollBack();
                sendJson(['state' => 'error_7']);
                return;
            }
            $insert = $db->prepare(
                "INSERT INTO world_battel_member " .
                "(id_battel, id_player, id_city, id_hero, side, task, x_city, y_city, time_start, time_arrive, return_duration, state) " .
                "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'marching')"
            );
            $insert->execute([
                $idBattle, $playerId, (int)$hero['id_city'], $idHero, $side, (int)$battle['task'],
                (int)$hero['x_city'], (int)$hero['y_city'], $now, $arriveAt, $returnDuration
            ]);
            if ($side === 0 && $targetCity) {
                $db->prepare(
                    "UPDATE city_garrison SET status = 'engaged', battle_id = ? " .
                    "WHERE id_hero = ? AND id_city = ? AND status = 'stationed'"
                )->execute([$idBattle, $idHero, (int)$targetCity['id_city']]);
            }

            $countsStmt = $db->prepare(
                "SELECT side, COUNT(*) AS amount FROM world_battel_member WHERE id_battel = ? AND state IN ('marching', 'won', 'lost') GROUP BY side"
            );
            $countsStmt->execute([$idBattle]);
            $counts = [0 => 0, 1 => 0];
            foreach ($countsStmt->fetchAll(PDO::FETCH_ASSOC) as $countRow) {
                $counts[(int)$countRow['side']] = (int)$countRow['amount'];
            }
            $db->commit();

            sendJson([
                'state' => 'ok',
                'unit_type' => $unitType,
                'unit_lvl' => $unitLevel,
                'hero_power' => max(0, (int)$hero['power'] - HeroPowerService::ACTION_COST),
                'hero_power_ls' => $now,
                'Battel' => [
                    'id_battel' => $idBattle,
                    'id_player' => (int)$battle['id_player'],
                    'PlayerName' => $battle['PlayerName'],
                    'CityName' => $battle['CityName'],
                    'x_coord' => (int)$battle['x_coord'],
                    'y_coord' => (int)$battle['y_coord'],
                    'x_city' => (int)$battle['x_city'],
                    'y_city' => (int)$battle['y_city'],
                    'task' => (int)$battle['task'],
                    'time_start' => (int)$battle['time_start'],
                    'time_arrive' => (int)$battle['time_arrive'],
                    'attackNum' => $counts[1],
                    'defenceNum' => $counts[0]
                ]
            ]);
        } catch (Throwable $error) {
            if ($db->inTransaction()) $db->rollBack();
            throw $error;
        }
    }

    const MAX_CITIES = 10;

    public static function buildNewCity($db) {
        $player   = requireAuthenticatedPlayer($db);
        $idCity   = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 0;
        $xCoord   = isset($_REQUEST['xCoord']) ? (int)$_REQUEST['xCoord'] : 0;
        $yCoord   = isset($_REQUEST['yCoord']) ? (int)$_REQUEST['yCoord'] : 0;
        $cityName = normalizeGameDisplayName($_REQUEST['CityName'] ?? null);

        if ($cityName === null) {
            sendJson(['state' => 'error_0', 'msg' => 'اسم المدينة غير صالح']);
            return;
        }

        $city = requirePlayerOwnedCity($db, $player, $idCity);
        $idPlayer = (int)$player['id_player'];

        $db->beginTransaction();
        $coordinateLockName = null;
        $coordinateLockAcquired = false;
        try {
            $playerLockStmt = $db->prepare("SELECT porm FROM player WHERE id_player = ? FOR UPDATE");
            $playerLockStmt->execute([$idPlayer]);
            $lockedPorm = $playerLockStmt->fetchColumn();
            if ($lockedPorm === false) {
                http_response_code(403);
                sendJson(['state' => 'error_auth']);
            }
            $player['porm'] = (int)$lockedPorm;

            $cityLockStmt = $db->prepare("SELECT * FROM city WHERE id_city = ? AND id_player = ? FOR UPDATE");
            $cityLockStmt->execute([$idCity, $idPlayer]);
            $city = $cityLockStmt->fetch(PDO::FETCH_ASSOC);
            if (!$city) {
                http_response_code(403);
                sendJson(['state' => 'error_auth']);
            }

            // 2. Count current cities & enforce hard cap (10 cities maximum)
            $cCountStmt = $db->prepare("SELECT COUNT(*) FROM city WHERE id_player = ?");
            $cCountStmt->execute([$idPlayer]);
            $cityCount = (int)$cCountStmt->fetchColumn();

            if ($cityCount >= self::MAX_CITIES) {
                sendJson(['state' => 'error_1', 'msg' => 'لقد بلغت الحد الأقصى للمدن المسموح به (10 مدن)']);
                return;
            }

            // Each promotion rank unlocks one additional city.
            $porm = (int)($player['porm'] ?? 0);
            $reqPorm = $cityCount;
            if ($porm < $reqPorm) {
                sendJson(['state' => 'error_1', 'msg' => "رتبتك لا تكفى لبناء مدينة جديدة (الرتبة المطلوبة: $reqPorm)"]);
                return;
            }

            // City construction has a fixed cost of 10,000 per resource.
            $reqRes = 10000;
            if ($city['food'] < $reqRes || $city['wood'] < $reqRes || $city['stone'] < $reqRes || $city['metal'] < $reqRes || $city['coin'] < $reqRes) {
                sendJson(['state' => 'error_2', 'msg' => 'لا توجد موارد كافية لبناء المدينة']);
                return;
            }

            // 5. Validate map coordinates & check if target spot is empty
            if ($xCoord < 0 || $xCoord >= 500 || $yCoord < 0 || $yCoord >= 500) {
                sendJson(['state' => 'error_3', 'msg' => 'الإحداثيات المحددة خارج حدود خريطة العالم']);
                return;
            }

            // The legacy city schema has no unique index for map coordinates.
            $coordinateLockName = "world_city_{$xCoord}_{$yCoord}";
            $coordinateLockStmt = $db->prepare("SELECT GET_LOCK(?, 5)");
            $coordinateLockStmt->execute([$coordinateLockName]);
            $coordinateLockAcquired = (int)$coordinateLockStmt->fetchColumn() === 1;
            if (!$coordinateLockAcquired) {
                http_response_code(503);
                sendJson(['state' => 'error_world_busy']);
            }

            // 5a. Check if occupied by any city
            $checkStmt = $db->prepare("SELECT id_city FROM city WHERE x = ? AND y = ?");
            $checkStmt->execute([$xCoord, $yCoord]);
            if ($checkStmt->fetch()) {
                sendJson(['state' => 'error_3', 'msg' => 'المكان مشغول بالفعل بمدينة أخرى']);
                return;
            }

            // 5b. Check if occupied by colonized wild
            $barStmt = $db->prepare("SELECT id_barray FROM city_barray WHERE x_coord = ? AND y_coord = ?");
            $barStmt->execute([$xCoord, $yCoord]);
            if ($barStmt->fetch()) {
                sendJson(['state' => 'error_3', 'msg' => 'المكان مشغول بالفعل ببرية مستعمرة']);
                return;
            }

            // 5c. Check WorldBarrary.json: ensure tile is empty (ut = 0)
            $wbPath = self::resolveJsonPath('WorldBarrary.json');
            if ($wbPath && file_exists($wbPath)) {
                $wbContent = file_get_contents($wbPath);
                if (preg_match('/(^|[,"]\s*)' . $xCoord . '_' . $yCoord . '_([1-9][0-9]*)/', $wbContent)) {
                    sendJson(['state' => 'error_3', 'msg' => 'المكان غير خالٍ (يوجد معلم جغرافي أو برية في هذا الموضع)']);
                    return;
                }
            }

            // 6. Deduct resources from originating city
            $deductStmt = $db->prepare("UPDATE city SET food = food - ?, wood = wood - ?, stone = stone - ?, metal = metal - ?, coin = coin - ? WHERE id_city = ? AND id_player = ? AND food >= ? AND wood >= ? AND stone >= ? AND metal >= ? AND coin >= ?");
            $deductStmt->execute([$reqRes, $reqRes, $reqRes, $reqRes, $reqRes, $idCity, $idPlayer, $reqRes, $reqRes, $reqRes, $reqRes, $reqRes]);
            if ($deductStmt->rowCount() !== 1) {
                $db->rollBack();
                $db->prepare("SELECT RELEASE_LOCK(?)")->execute([$coordinateLockName]);
                $coordinateLockAcquired = false;
                sendJson(['state' => 'error_2', 'msg' => 'لا توجد موارد كافية لبناء المدينة']);
            }

            // 7. Insert new city record with initial expansion lvl = 0
            $insStmt = $db->prepare("INSERT INTO city (id_player, name, x, y, lvl, food, wood, stone, metal, coin, food_cap, wood_cap, stone_cap, metal_cap, coin_cap, pop, pop_max, pop_cap, loy, dis_loy, taxs, helper, pop_LSS)
                                     VALUES (?, ?, ?, ?, 0, 10000, 10000, 10000, 10000, 10000, 100000, 100000, 100000, 100000, 100000, 100, 100, 100, 100, 0, 10, 0, ?)");
            $insStmt->execute([$idPlayer, $cityName, $xCoord, $yCoord, time()]);
            $newCityId = (int)$db->lastInsertId();

            // 8. Insert standard starter city building slots (excluding disabled naval placeholders)
            CityBuildingService::initializeCityBuildings($db, $newCityId, false);

            // 9. Initialize city_jop with standard 10 base jobs and 100% rates
            $db->prepare("INSERT INTO city_jop (id_city, id_player, food, wood, stone, metal, food_rate, wood_rate, stone_rate, metal_rate) 
                          VALUES (?, ?, 10, 10, 10, 10, 100, 100, 100, 100) 
                          ON DUPLICATE KEY UPDATE id_city=id_city")
               ->execute([$newCityId, $idPlayer]);

            // 10. Initialize city_storage
            $db->prepare("INSERT INTO city_storage (id_city, food_perc, wood_perc, stone_perc, metal_perc) 
                          VALUES (?, 25, 25, 25, 25) 
                          ON DUPLICATE KEY UPDATE id_city=id_city")
               ->execute([$newCityId]);

            // 11. Recalculate production for both originating city and the new city
            require_once __DIR__ . '/../services/ProductionService.php';
            ProductionService::recalculateCityProduction($db, $newCityId);
            ProductionService::recalculateCityProduction($db, $idCity);

            // 12. Fetch new city data and format integer values
            $getNewStmt = $db->prepare("SELECT * FROM city WHERE id_city = ?");
            $getNewStmt->execute([$newCityId]);
            $newCity = $getNewStmt->fetch();

            foreach ($newCity as $f => $v) {
                if (is_numeric($v)) $newCity[$f] = (int)$v;
            }

            $db->commit();
            $db->prepare("SELECT RELEASE_LOCK(?)")->execute([$coordinateLockName]);
            $coordinateLockAcquired = false;
            sendJson(['state' => 'ok', 'City' => $newCity]);
        } catch (Throwable $error) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if ($coordinateLockAcquired) {
                $db->prepare("SELECT RELEASE_LOCK(?)")->execute([$coordinateLockName]);
            }
            throw $error;
        }
    }

    public static function getLeavingHero($db) {
        $player = requireAuthenticatedPlayer($db);
        $stmt = $db->prepare("SELECT m.id_battel, m.id_hero, m.task, m.x_city, m.y_city,
                                     b.x_coord, b.y_coord, m.time_arrive AS time_end,
                                     (m.time_arrive <= UNIX_TIMESTAMP()) AS battle_pending
                              FROM world_battel_member m
                              JOIN world_battel b ON b.id_battel = m.id_battel
                              WHERE m.id_player = ? AND m.state = 'marching'
                              ORDER BY m.time_arrive ASC");
        $stmt->execute([(int)$player['id_player']]);
        $rows = $stmt->fetchAll() ?: [];
        foreach ($rows as &$row) {
            foreach (['id_battel', 'id_hero', 'task', 'x_city', 'y_city', 'x_coord', 'y_coord', 'time_end', 'battle_pending'] as $column) {
                $row[$column] = (int)$row[$column];
            }
        }
        sendJson($rows);
    }

    public static function getGarrisonHeros($db): void {
        $player = requireAuthenticatedPlayer($db);
        require_once __DIR__ . '/../services/CityGarrisonService.php';
        sendJson(CityGarrisonService::getPlayerGarrisons($db, (int)$player['id_player']));
    }

    public static function supportByHero($db): void {
        $player = requireAuthenticatedPlayer($db);
        require_once __DIR__ . '/../services/CityGarrisonService.php';
        $heroId = filter_var($_POST['idHero'] ?? null, FILTER_VALIDATE_INT);
        $x = filter_var($_POST['xTo'] ?? null, FILTER_VALIDATE_INT);
        $y = filter_var($_POST['yTo'] ?? null, FILTER_VALIDATE_INT);
        if ($heroId === false || $heroId === null || $heroId <= 0 ||
            $x === false || $x === null || $x < 0 || $x > 499 ||
            $y === false || $y === null || $y < 0 || $y > 499) {
            http_response_code(400);
            sendJson(['state' => 'error_invalid_request']);
        }

        $playerId = (int)$player['id_player'];
        $db->beginTransaction();
        try {
            $heroStmt = $db->prepare(
                'SELECT h.*, c.x AS x_city, c.y AS y_city, p.id_guild ' .
                'FROM city_hero h JOIN city c ON c.id_city = h.id_city ' .
                'JOIN player p ON p.id_player = h.id_player ' .
                'WHERE h.id_hero = ? AND h.id_player = ? FOR UPDATE'
            );
            $heroStmt->execute([$heroId, $playerId]);
            $hero = $heroStmt->fetch(PDO::FETCH_ASSOC);
            if (!$hero) {
                $db->rollBack();
                sendJson(['state' => 'error_0']);
            }
            if ((int)$hero['in_city'] !== 1) {
                $db->rollBack();
                sendJson(['state' => 'error_2']);
            }
            if ((int)$hero['console'] !== 0) {
                $db->rollBack();
                sendJson(['state' => 'error_3']);
            }

            $targetStmt = $db->prepare(
                'SELECT c.id_city, c.id_player, p.id_guild FROM city c ' .
                'JOIN player p ON p.id_player = c.id_player WHERE c.x = ? AND c.y = ? LIMIT 1'
            );
            $targetStmt->execute([$x, $y]);
            $target = $targetStmt->fetch(PDO::FETCH_ASSOC);
            if (!$target) {
                $db->rollBack();
                sendJson(['state' => 'error_1']);
            }
            $targetCityId = (int)$target['id_city'];
            if ($targetCityId === (int)$hero['id_city']) {
                $db->rollBack();
                sendJson(['state' => 'error_3']);
            }

            if (!HeroDeploymentService::hasAvailableOutsideSlot(
                $db,
                (int)$hero['id_city'],
                $playerId,
                [$targetCityId]
            )) {
                $db->rollBack();
                sendJson(['state' => 'error_9']);
            }

            $lockedTargetStmt = $db->prepare(
                'SELECT c.id_city, c.id_player, p.id_guild FROM city c ' .
                'JOIN player p ON p.id_player = c.id_player WHERE c.x = ? AND c.y = ? LIMIT 1 FOR UPDATE'
            );
            $lockedTargetStmt->execute([$x, $y]);
            $target = $lockedTargetStmt->fetch(PDO::FETCH_ASSOC);
            if (!$target || (int)$target['id_city'] !== $targetCityId) {
                $db->rollBack();
                sendJson(['state' => 'error_1']);
            }

            $canSupport = (int)$target['id_player'] === $playerId ||
                ((int)$hero['id_guild'] > 0 &&
                    (int)$hero['id_guild'] === (int)$target['id_guild']);
            if (!$canSupport) {
                $sovereignStmt = $db->prepare(
                    'SELECT 1 FROM city_tributary WHERE id_city = ? AND id_sovereign_city = ? LIMIT 1'
                );
                $sovereignStmt->execute([(int)$target['id_city'], (int)$hero['id_city']]);
                $canSupport = $sovereignStmt->fetchColumn() !== false;
            }
            if (!$canSupport) {
                $db->rollBack();
                sendJson(['state' => 'error_3']);
            }

            $countStmt = $db->prepare('SELECT COUNT(*) FROM city_garrison WHERE id_city = ?');
            $countStmt->execute([(int)$target['id_city']]);
            if ((int)$countStmt->fetchColumn() >= CityGarrisonService::MAX_CITY_GARRISON) {
                $db->rollBack();
                sendJson(['state' => 'error_3']);
            }

            $existingStmt = $db->prepare(
                'SELECT id_hero FROM city_garrison WHERE id_hero = ? FOR UPDATE'
            );
            $existingStmt->execute([$heroId]);
            if ($existingStmt->fetchColumn() !== false) {
                $db->rollBack();
                sendJson(['state' => 'error_3']);
            }

            $armyStmt = $db->prepare('SELECT * FROM hero_army WHERE id_hero = ?');
            $armyStmt->execute([$heroId]);
            $army = $armyStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $troops = 0;
            foreach (['f_1', 'f_2', 'f_3', 'b_1', 'b_2', 'b_3'] as $slot) {
                $troops += max(0, (int)($army[$slot . '_num'] ?? 0));
            }
            if ($troops <= 0) {
                $db->rollBack();
                sendJson(['state' => 'error_3']);
            }
            $configStmt = $db->query(
                "SELECT config_key, config_value FROM server_config " .
                "WHERE config_key IN ('march_speed_multiplier', 'camp_rally_duration_sec', 'city_attack_min_duration_sec')"
            );
            $config = [
                'march_speed_multiplier' => 1,
                'camp_rally_duration_sec' => 120,
                'city_attack_min_duration_sec' => 900
            ];
            foreach ($configStmt->fetchAll(PDO::FETCH_ASSOC) as $configRow) {
                $config[$configRow['config_key']] = (float)$configRow['config_value'];
            }
            $travelDuration = self::getJoinMarchDuration(
                (int)$hero['x_city'],
                (int)$hero['y_city'],
                $x,
                $y,
                $army,
                0,
                false,
                $config
            );
            $arriveAt = time() + $travelDuration;

            $orderStmt = $db->prepare(
                'SELECT COALESCE(MAX(ord), -1) + 1 FROM city_garrison WHERE id_city = ?'
            );
            $orderStmt->execute([(int)$target['id_city']]);
            $order = (int)$orderStmt->fetchColumn();
            $db->prepare(
                "INSERT INTO city_garrison (id_hero, id_city, id_player, ord, status, time_arrive) " .
                "VALUES (?, ?, ?, ?, 'marching', ?)"
            )->execute([$heroId, (int)$target['id_city'], $playerId, $order, $arriveAt]);
            $heroUpdate = $db->prepare(
                'UPDATE city_hero SET in_city = 0 WHERE id_hero = ? AND id_player = ? AND in_city = 1'
            );
            $heroUpdate->execute([$heroId, $playerId]);
            if ($heroUpdate->rowCount() !== 1) {
                throw new RuntimeException('Supporting hero was not available at dispatch');
            }
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) $db->rollBack();
            throw $error;
        }

        sendJson(['state' => 'ok', 'time_arrive' => $arriveAt]);
    }

    public static function getHeroBack($db) {
        $player = requireAuthenticatedPlayer($db);
        $stmt = $db->prepare("SELECT m.id_hero, m.task AS Task, b.x_coord AS xFrom,
                                     b.y_coord AS yFrom, m.x_city AS xTo, m.y_city AS yTo,
                                     CASE WHEN m.state = 'marching'
                                          THEN GREATEST(m.time_arrive, UNIX_TIMESTAMP()) + COALESCE(m.return_duration, 1)
                                          ELSE m.time_back END AS time_back,
                                     (m.state = 'marching') AS battle_pending
                              FROM world_battel_member m
                              JOIN world_battel b ON b.id_battel = m.id_battel
                              WHERE m.id_player = ? AND (
                                    (m.state IN ('won', 'lost') AND m.time_back > UNIX_TIMESTAMP())
                                    OR (m.state = 'marching' AND m.time_arrive <= UNIX_TIMESTAMP())
                              )
                              ORDER BY CASE WHEN m.state = 'marching'
                                            THEN GREATEST(m.time_arrive, UNIX_TIMESTAMP()) + COALESCE(m.return_duration, 1)
                                            ELSE m.time_back END ASC");
        $stmt->execute([(int)$player['id_player']]);
        $rows = $stmt->fetchAll() ?: [];
        foreach ($rows as &$row) {
            foreach (['id_hero', 'Task', 'xFrom', 'yFrom', 'xTo', 'yTo', 'time_back', 'battle_pending'] as $column) {
                $row[$column] = (int)$row[$column];
            }
        }
        sendJson($rows);
    }

    public static function refreshWorldUnitLvl($db) {
        $x = filter_var($_REQUEST['xCoord'] ?? null, FILTER_VALIDATE_INT);
        $y = filter_var($_REQUEST['yCoord'] ?? null, FILTER_VALIDATE_INT);
        if ($x === false || $y === false || $x === null || $y === null || $x < 0 || $x > 499 || $y < 0 || $y > 499) {
            http_response_code(400);
            sendJson(['state' => 'error_invalid_coordinates']);
            return;
        }

        $unitType = filter_var($_REQUEST['unitType'] ?? null, FILTER_VALIDATE_INT);
        $reqLvl = filter_var($_REQUEST['lvl'] ?? null, FILTER_VALIDATE_INT);
        if ($unitType === false || $reqLvl === false || $unitType === null || $reqLvl === null) {
            http_response_code(400);
            sendJson(['state' => 'error_invalid_world_unit']);
            return;
        }

        // Resolve the requested level from the server-owned map state.
        $barStmt = $db->prepare("SELECT Type, lvl FROM city_barray WHERE x_coord = ? AND y_coord = ? LIMIT 1");
        $barStmt->execute([$x, $y]);
        $barRow = $barStmt->fetch(PDO::FETCH_ASSOC);

        if ($barRow) {
            $unitType = (int)$barRow['Type'];
            $reqLvl = (int)$barRow['lvl'];
        } else {
            $worldUnit = self::getStaticWorldUnit($x, $y);
            if (!$worldUnit) {
                http_response_code(404);
                sendJson(['state' => 'error_world_unit_not_found']);
                return;
            }
            $unitType = (int)$worldUnit['ut'];
            $reqLvl = max(1, (int)$worldUnit['lvl']);
            $stateStmt = $db->prepare(
                'SELECT base_lvl, current_lvl, next_reset_at FROM world_unit_state ' .
                'WHERE x_coord = ? AND y_coord = ? AND unit_type = ? AND unit_subtype = ? LIMIT 1'
            );
            $stateStmt->execute([$x, $y, $unitType, (int)$worldUnit['subtype']]);
            $worldState = $stateStmt->fetch(PDO::FETCH_ASSOC);
            if ($worldState) {
                $reqLvl = (int)$worldState['next_reset_at'] > 0 && (int)$worldState['next_reset_at'] <= time()
                    ? (int)$worldState['base_lvl']
                    : (int)$worldState['current_lvl'];
            }
        }

        $unitDataPath = self::resolveJsonPath('worldUnitData.json');
        $unitArmyPath = self::resolveJsonPath('UnitArmy.json');
        static $cachedUnitData = null;
        static $cachedUnitArmy = null;
        if ($cachedUnitData === null || $cachedUnitArmy === null) {
            $rawUnitData = ($unitDataPath && is_readable($unitDataPath)) ? file_get_contents($unitDataPath) : false;
            $rawUnitArmy = ($unitArmyPath && is_readable($unitArmyPath)) ? file_get_contents($unitArmyPath) : false;
            $cachedUnitData = $rawUnitData === false ? null : json_decode($rawUnitData, true);
            $cachedUnitArmy = $rawUnitArmy === false ? null : json_decode($rawUnitArmy, true);
        }

        if (!is_array($cachedUnitData) || !is_array($cachedUnitArmy)) {
            error_log('[WorldController] World unit army data is unavailable or invalid');
            http_response_code(500);
            sendJson(['state' => 'error_world_unit_data_unavailable']);
            return;
        }

        $unitTypeKey = (string)$unitType;
        if ($unitType <= 0 || $reqLvl <= 0 || !isset($cachedUnitData[$unitTypeKey])) {
            http_response_code(404);
            sendJson(['state' => 'error_world_unit_not_found']);
            return;
        }
        $unitTypeData = $cachedUnitData[$unitTypeKey];
        $maxLevel = (int)($unitTypeData['maxLvl'] ?? 0);
        if ($maxLevel > 0) {
            $reqLvl = min($reqLvl, $maxLevel);
        }
        $levelKey = (string)$reqLvl;

        $armyData = $cachedUnitArmy[$unitTypeKey][$levelKey] ?? [];
        $army = [
            'army_a' => (int)($armyData['army_a'] ?? $armyData['1'] ?? 0),
            'army_b' => (int)($armyData['army_b'] ?? $armyData['2'] ?? 0),
            'army_c' => (int)($armyData['army_c'] ?? $armyData['3'] ?? 0),
            'army_d' => (int)($armyData['army_d'] ?? $armyData['4'] ?? 0),
            'army_e' => (int)($armyData['army_e'] ?? $armyData['5'] ?? 0),
            'army_f' => (int)($armyData['army_f'] ?? $armyData['6'] ?? 0),
        ];

        sendJson([
            'state' => 'ok',
            'l' => $reqLvl,
            'ut' => $unitType,
            'army' => $army,
            'equip' => []
        ]);
    }

    public static function getUnitViewPrizes() {
        $path = dirname(__DIR__, 2) . '/caesar-private/data/world-unit-prizes.json';
        if (!file_exists($path) || !is_readable($path)) {
            $path = __DIR__ . '/../../caesar-private/data/world-unit-prizes.json';
        }
        if (!file_exists($path) || !is_readable($path)) {
            http_response_code(500);
            sendJson(['state' => 'error_prize_data_unavailable']);
            return;
        }

        $raw = file_get_contents($path);
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            http_response_code(500);
            sendJson(['state' => 'error_invalid_prize_data']);
            return;
        }

        $unitType = filter_var($_REQUEST['unitType'] ?? null, FILTER_VALIDATE_INT);
        $lvl = filter_var($_REQUEST['lvl'] ?? null, FILTER_VALIDATE_INT);
        $prizeFor = $_REQUEST['prizeFor'] ?? null;

        $sanitize = function($list, $filterType = null, $filterLvl = null) {
            $result = [];
            if (!is_array($list)) return $result;
            $isWilderness = $filterType !== null && (
                ($filterType >= 1 && $filterType <= 10) ||
                ($filterType >= 30 && $filterType <= 59)
            );
            foreach ($list as $p) {
                $uType = (int)($p['unitType'] ?? 0);
                $pLvl = (int)($p['lvl'] ?? 0);
                if ($filterType !== null && $uType !== $filterType) continue;
                if ($filterLvl !== null && $pLvl !== $filterLvl) continue;
                $prize = (string)($p['prize'] ?? '');
                if ($isWilderness && strncmp($prize, 'coin_', 5) === 0) {
                    continue;
                }
                $result[] = [
                    'unitType'   => $uType,
                    'lvl'        => $pLvl,
                    'prize'      => $prize,
                    'amount_min' => (int)($p['amount_min'] ?? 1),
                    'amount_max' => (int)($p['amount_max'] ?? 1),
                ];
            }
            return $result;
        };

        if ($unitType !== null && $unitType !== false) {
            $targetBucket = $prizeFor && isset($data[$prizeFor]) ? $prizeFor : 'Win';
            $sanitized = $targetBucket === 'Lose'
                ? []
                : $sanitize($data[$targetBucket] ?? [], (int)$unitType, $lvl !== false ? $lvl : null);
            $allBuckets = ['Lose' => []];
            foreach (['Win', 'Sp', 'Plunder'] as $bucketName) {
                $allBuckets[$bucketName] = $sanitize(
                    $data[$bucketName] ?? [],
                    (int)$unitType,
                    $lvl !== false ? $lvl : null
                );
            }
            sendJson([
                'state'    => 'ok',
                'unitType' => (int)$unitType,
                'prizeFor' => $targetBucket,
                'prizes'   => $sanitized,
                'buckets'  => $allBuckets,
            ]);
            return;
        }

        // Require unitType on demand to prevent bulk prize data dumps
        http_response_code(400);
        sendJson([
            'state'    => 'error_unit_type_required',
            'prizes'   => []
        ]);
    }

    // ── Player Authentication Helper (supports both Token & $_SESSION for tests) ──
    public static function authenticatePlayer($db, bool $require = true): ?array {
        // 1. Try token authentication first (live game uses AJAX token)
        $token = $_POST['authToken'] ?? $_GET['authToken'] ?? $_POST['token'] ?? $_GET['token'] ?? '';
        if (is_string($token) && $token !== '') {
            try {
                $stmt = $db->prepare('SELECT * FROM player WHERE p_token = ? LIMIT 1');
                $stmt->execute([$token]);
                $player = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($player) {
                    return $player;
                }
            } catch (\Throwable $e) {}
        }

        // 2. Fallback to $_SESSION['id_player'] for unit test suites and session environments
        $idPlayer = (int)($_SESSION['id_player'] ?? 0);
        if ($idPlayer > 0) {
            try {
                $stmt = $db->prepare('SELECT * FROM player WHERE id_player = ? LIMIT 1');
                $stmt->execute([$idPlayer]);
                $player = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($player) {
                    return $player;
                }
            } catch (\Throwable $e) {}

            try {
                $stmt = $db->prepare('SELECT id_guild FROM player WHERE id_player = ? LIMIT 1');
                $stmt->execute([$idPlayer]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                $idGuild = (int)($row['id_guild'] ?? $_SESSION['id_guild'] ?? 0);
                return [
                    'id_player' => $idPlayer,
                    'id_guild' => $idGuild,
                    'name' => $_SESSION['player_name'] ?? 'Test Player',
                ];
            } catch (\Throwable $e) {}

            return [
                'id_player' => $idPlayer,
                'id_guild' => (int)($_SESSION['id_guild'] ?? 0),
                'name' => 'Player_' . $idPlayer,
            ];
        }

        if ($require) {
            http_response_code(401);
            sendJson(['state' => 'not_logged_in']);
        }

        return null;
    }

    private static function loadWorldUnitPrizes(): array {
        $path = dirname(__DIR__, 2) . '/caesar-private/data/world-unit-prizes.json';
        if (!file_exists($path) || !is_readable($path)) {
            $path = __DIR__ . '/../../caesar-private/data/world-unit-prizes.json';
        }
        if (!file_exists($path) || !is_readable($path)) {
            return [];
        }
        $decoded = json_decode((string)file_get_contents($path), true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function getPlunderEntriesForUnit(int $unitType, int $unitLvl = 1): array {
        if ($unitType <= 0) {
            return [];
        }
        $prizeData = self::loadWorldUnitPrizes();
        $list = $prizeData['Plunder'] ?? [];
        if (!is_array($list) || empty($list)) {
            return [];
        }
        $typeRows = [];
        foreach ($list as $row) {
            if ((int)($row['unitType'] ?? 0) === $unitType) {
                $typeRows[] = $row;
            }
        }
        if (empty($typeRows)) {
            return [];
        }
        $exact = [];
        foreach ($typeRows as $row) {
            if ((int)($row['lvl'] ?? 0) === $unitLvl) {
                $exact[] = $row;
            }
        }
        if (!empty($exact)) {
            return $exact;
        }
        $clampedLvl = max(1, $unitLvl);
        $fallbackLvl = (int)($typeRows[0]['lvl'] ?? 1);
        foreach ($typeRows as $row) {
            $rLvl = max(1, (int)($row['lvl'] ?? 1));
            if ($rLvl <= $clampedLvl && $rLvl >= $fallbackLvl) {
                $fallbackLvl = $rLvl;
            }
        }
        $matched = [];
        foreach ($typeRows as $row) {
            if (max(1, (int)($row['lvl'] ?? 1)) === $fallbackLvl) {
                $matched[] = $row;
            }
        }
        return $matched;
    }

    private static function rollPlunderPrizes(array $entries, int $amountMultiplier = 1): array {
        if (empty($entries)) {
            return [];
        }
        $mult = max(1, $amountMultiplier);
        $totals = [];
        foreach ($entries as $entry) {
            $itemKey = (string)($entry['prize'] ?? '');
            if ($itemKey === '') continue;
            $rate = max(0, min(self::PRIZE_RATE_SCALE, (int)($entry['win_rate'] ?? 0)));
            if (mt_rand(1, self::PRIZE_RATE_SCALE) > $rate) {
                continue;
            }
            $min = max(1, (int)($entry['amount_min'] ?? 1));
            $max = max($min, (int)($entry['amount_max'] ?? $min));
            $amount = mt_rand($min, $max) * $mult;
            if ($amount <= 0) continue;
            $totals[$itemKey] = ($totals[$itemKey] ?? 0) + $amount;
        }
        $result = [];
        foreach ($totals as $itemKey => $amount) {
            $result[] = ['Item' => $itemKey, 'amount' => $amount];
        }
        return $result;
    }

    private static function getDominanceRepeatItemKey(int $unitType): ?string {
        $map = [
            180 => 'bronze_horn',
            181 => 'silver_horn',
            182 => 'gold_horn',
            184 => 'bronze_order',
            185 => 'silver_order',
            186 => 'gold_order',
        ];
        return $map[$unitType] ?? null;
    }

    private static function fetchPlayerHarvestState($db, int $xCoord, int $yCoord, int $idPlayer, int $unitType, bool $forUpdate = false): array {
        $lockClause = $forUpdate ? ' FOR UPDATE' : '';
        $harvestRow = false;
        try {
            $stmt = $db->prepare(
                'SELECT harvested_at, harvest_count, window_started_at FROM guild_dominance_harvest ' .
                'WHERE x_coord = ? AND y_coord = ? AND id_player = ? LIMIT 1' . $lockClause
            );
            $stmt->execute([$xCoord, $yCoord, $idPlayer]);
            $harvestRow = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            try {
                $stmt = $db->prepare(
                    'SELECT harvested_at FROM guild_dominance_harvest ' .
                    'WHERE x_coord = ? AND y_coord = ? AND id_player = ? LIMIT 1' . $lockClause
                );
                $stmt->execute([$xCoord, $yCoord, $idPlayer]);
                $harvestRow = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (\Throwable $ignored) {}
        }

        $now = time();
        $lastHarvestAt = $harvestRow ? (int)($harvestRow['harvested_at'] ?? 0) : 0;
        $windowStartedAt = $harvestRow ? (int)($harvestRow['window_started_at'] ?? 0) : 0;
        if ($windowStartedAt <= 0) {
            $windowStartedAt = $lastHarvestAt;
        }
        $rawCount = $harvestRow ? (int)($harvestRow['harvest_count'] ?? 0) : 0;
        if ($rawCount <= 0 && $lastHarvestAt > 0) {
            $rawCount = 1;
        }

        $elapsed = $windowStartedAt > 0 ? max(0, $now - $windowStartedAt) : 86400;
        $inWindow = ($windowStartedAt > 0 && $elapsed < 86400);
        $dailyCount = $inWindow ? $rawCount : 0;
        $remainingSec = $inWindow ? max(0, 86400 - $elapsed) : 0;

        $freeLimit = self::MAX_DAILY_FREE_REAP_TIMES;
        $extraLimit = self::MAX_DAILY_EXTRA_REAP_TIMES;
        $maxLimit = $freeLimit + $extraLimit;
        $isFree = $dailyCount < $freeLimit;
        $reachDailyLimit = $dailyCount >= $maxLimit;
        $nextExtraIndex = max(1, $dailyCount - $freeLimit + 1);
        $coinCost = $isFree ? 0 : (10 * $nextExtraIndex);

        return [
            'now'              => $now,
            'inWindow'         => $inWindow,
            'windowStartedAt'  => $windowStartedAt,
            'lastHarvestAt'    => $lastHarvestAt,
            'dailyCount'       => $dailyCount,
            'dailyFreeLimit'   => $freeLimit,
            'dailyExtraLimit'  => $extraLimit,
            'dailyMaxLimit'    => $maxLimit,
            'chargeCount'      => max(0, $dailyCount - $freeLimit),
            'remainingSec'     => $remainingSec,
            'canHarvestFree'   => $isFree,
            'canRepeatHarvest' => !$isFree && !$reachDailyLimit,
            'reachDailyLimit'  => $reachDailyLimit,
            'reapNeedPay'      => !$isFree && !$reachDailyLimit,
            'nextExtraIndex'   => $nextExtraIndex,
            'coinCost'         => $coinCost,
            'requiredItemKey'  => self::getDominanceRepeatItemKey($unitType),
        ];
    }

    // ── Guild Dominance: get all dominated castles/queens ─────────────────────
    public static function getGuildDominance($db) {
        $player = self::authenticatePlayer($db, true);
        $idPlayer = (int)$player['id_player'];
        $idGuild = (int)($player['id_guild'] ?? 0);

        // All dominated units
        $stmt2 = $db->prepare(
            "SELECT gd.*, p.name AS player_name, g.name AS guild_name
             FROM guild_dominance gd
             LEFT JOIN player p ON p.id_player = gd.id_player
             LEFT JOIN guild g  ON g.id_guild  = gd.id_guild
             ORDER BY gd.unit_type, gd.conquered_at DESC"
        );
        $stmt2->execute();
        $rows = $stmt2->fetchAll();

        $units = [];
        foreach ($rows as $r) {
            // Per-player cooldown: check guild_dominance_harvest for this specific player
            $remainingSec = 86400; // default: not ready
            $canHarvest = false;
            $canRepeat = false;
            $dailyCount = 0;
            $dailyMax = self::MAX_DAILY_FREE_REAP_TIMES + self::MAX_DAILY_EXTRA_REAP_TIMES;
            if ($idGuild > 0 && (int)$r['id_guild'] === $idGuild) {
                $hState = self::fetchPlayerHarvestState(
                    $db,
                    (int)$r['x_coord'],
                    (int)$r['y_coord'],
                    $idPlayer,
                    (int)$r['unit_type'],
                    false
                );
                $remainingSec = $hState['remainingSec'];
                $canHarvest = $hState['canHarvestFree'];
                $canRepeat = $hState['canRepeatHarvest'];
                $dailyCount = $hState['dailyCount'];
            }
            $units[] = [
                'x_coord'            => (int)$r['x_coord'],
                'y_coord'            => (int)$r['y_coord'],
                'unit_type'          => (int)$r['unit_type'],
                'unit_lvl'           => (int)$r['unit_lvl'],
                'id_guild'           => (int)$r['id_guild'],
                'id_player'          => (int)$r['id_player'],
                'guild_name'         => (string)($r['guild_name'] ?? ''),
                'player_name'        => (string)($r['player_name'] ?? ''),
                'conquered_at'       => (int)$r['conquered_at'],
                'last_harvest_at'    => (int)$r['last_harvest_at'],
                'daily_food'         => (int)$r['daily_food'],
                'daily_wood'         => (int)$r['daily_wood'],
                'daily_stone'        => (int)$r['daily_stone'],
                'daily_metal'        => (int)$r['daily_metal'],
                'daily_coin'         => (int)$r['daily_coin'],
                'remaining_sec'      => $remainingSec,
                'can_harvest'        => $canHarvest,
                'can_repeat_harvest' => $canRepeat,
                'daily_harvest_count'=> $dailyCount,
                'daily_max_harvests' => $dailyMax,
            ];
        }
        sendJson(['state' => 'ok', 'units' => $units, 'id_guild' => $idGuild]);
    }

    public static function getWorldUnitRank($db): void {
        $xCoord = filter_var($_GET['xCoord'] ?? null, FILTER_VALIDATE_INT);
        $yCoord = filter_var($_GET['yCoord'] ?? null, FILTER_VALIDATE_INT);
        $unitType = filter_var($_GET['unitType'] ?? null, FILTER_VALIDATE_INT);
        if ($xCoord === false || $yCoord === false || $unitType === false
            || $xCoord === null || $yCoord === null || $unitType === null
            || $xCoord < 0 || $xCoord >= 500 || $yCoord < 0 || $yCoord >= 500
            || !self::isGuildDominanceTarget($unitType)) {
            sendJson([]);
            return;
        }

        // ── Authenticate via token or $_SESSION fallback ──
        $authPlayer = self::authenticatePlayer($db, false);
        $idPlayer = (int)($authPlayer['id_player'] ?? 0);
        $playerGuildId = (int)($authPlayer['id_guild'] ?? 0);

        $stmt = $db->prepare(
            'SELECT gd.id_guild, gd.unit_type, gd.conquered_at, p.name AS PlayerName, g.name AS GuildName ' .
            'FROM guild_dominance gd ' .
            'LEFT JOIN player p ON p.id_player = gd.id_player ' .
            'LEFT JOIN guild g ON g.id_guild = gd.id_guild ' .
            'WHERE gd.x_coord = ? AND gd.y_coord = ? AND gd.unit_type = ? LIMIT 1'
        );
        $stmt->execute([$xCoord, $yCoord, $unitType]);
        $dominance = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$dominance) {
            sendJson([]);
            return;
        }

        // ── Per-player harvest cooldown & repeat plunder status ──────────────────
        $remainingSec = 0;
        $canHarvest = false;
        $canRepeat = false;
        $reachLimit = false;
        $dailyCount = 0;
        $dailyMax = self::MAX_DAILY_FREE_REAP_TIMES + self::MAX_DAILY_EXTRA_REAP_TIMES;
        $nextCoin = 0;
        $nextItem = self::getDominanceRepeatItemKey((int)$unitType);
        $dominantGuildId = (int)$dominance['id_guild'];
        if ($idPlayer > 0 && $playerGuildId > 0 && $playerGuildId === $dominantGuildId) {
            $hState = self::fetchPlayerHarvestState($db, $xCoord, $yCoord, $idPlayer, (int)$unitType, false);
            $remainingSec = $hState['remainingSec'];
            $canHarvest = $hState['canHarvestFree'];
            $canRepeat = $hState['canRepeatHarvest'];
            $reachLimit = $hState['reachDailyLimit'];
            $dailyCount = $hState['dailyCount'];
            $nextCoin = $hState['coinCost'];
        }

        sendJson([[
            'PlayerName'            => (string)($dominance['PlayerName'] ?? ''),
            'GuildName'             => (string)($dominance['GuildName'] ?? ''),
            'id_guild'              => $dominantGuildId,
            'playerGuildId'         => $playerGuildId,
            'totalDuration'         => max(0, time() - (int)$dominance['conquered_at']),
            'remaining_sec'         => $remainingSec,
            'can_harvest'           => $canHarvest,
            'can_repeat_harvest'    => $canRepeat,
            'reach_daily_reap_limit'=> $reachLimit,
            'daily_harvest_count'   => $dailyCount,
            'daily_max_harvests'    => $dailyMax,
            'next_reap_coin'        => $nextCoin,
            'next_reap_item'        => $nextItem,
        ]]);
    }

    public static function checkReapCharge($db): void {
        $player = self::authenticatePlayer($db, true);
        $idPlayer = (int)$player['id_player'];
        $playerGuildId = (int)($player['id_guild'] ?? 0);
        if (!$idPlayer || !$playerGuildId) {
            http_response_code(401);
            sendJson(['state' => 'not_logged_in', 'success' => false]);
            return;
        }

        $xCoord = (int)($_REQUEST['xCoord'] ?? 0);
        $yCoord = (int)($_REQUEST['yCoord'] ?? 0);

        $gdStmt = $db->prepare(
            'SELECT gd.id_guild, gd.unit_type, gd.unit_lvl FROM guild_dominance gd ' .
            'WHERE gd.x_coord = ? AND gd.y_coord = ? LIMIT 1'
        );
        $gdStmt->execute([$xCoord, $yCoord]);
        $gd = $gdStmt->fetch(PDO::FETCH_ASSOC);
        if (!$gd) {
            sendJson(['state' => 'not_dominated', 'success' => false]);
            return;
        }
        if ((int)$gd['id_guild'] !== $playerGuildId) {
            sendJson(['state' => 'not_your_guild', 'success' => false]);
            return;
        }

        $unitType = (int)($gd['unit_type'] ?? $_REQUEST['unitType'] ?? 0);
        $unitLvl = max(1, (int)($gd['unit_lvl'] ?? 1));
        $hState = self::fetchPlayerHarvestState($db, $xCoord, $yCoord, $idPlayer, $unitType, false);

        $playerGold = (int)($player['gold'] ?? 0);
        if (!array_key_exists('gold', $player)) {
            try {
                $gStmt = $db->prepare('SELECT gold FROM player WHERE id_player = ? LIMIT 1');
                $gStmt->execute([$idPlayer]);
                $gVal = $gStmt->fetchColumn();
                if ($gVal !== false) $playerGold = (int)$gVal;
            } catch (\Throwable $ignored) {}
        }

        $reqItemKey = $hState['requiredItemKey'];
        $reqOwned = 0;
        if ($reqItemKey !== null) {
            try {
                $iStmt = $db->prepare('SELECT amount FROM player_item WHERE id_player = ? AND id_item = ? LIMIT 1');
                $iStmt->execute([$idPlayer, $reqItemKey]);
                $iVal = $iStmt->fetchColumn();
                if ($iVal !== false) $reqOwned = (int)$iVal;
            } catch (\Throwable $ignored) {}
        }

        $plunderEntries = self::getPlunderEntriesForUnit($unitType, $unitLvl);
        $previewMult = $hState['canHarvestFree'] ? 1 : (1 + $hState['nextExtraIndex']);
        $previewItems = array_map(static function ($row) use ($previewMult) {
            return [
                'Item'       => (string)($row['prize'] ?? ''),
                'amount_min' => max(1, (int)($row['amount_min'] ?? 1)) * $previewMult,
                'amount_max' => max(1, (int)($row['amount_max'] ?? 1)) * $previewMult,
            ];
        }, $plunderEntries);

        sendJson([
            'state'                => 'ok',
            'success'              => true,
            'unitType'             => $unitType,
            'reapNeedPay'          => $hState['reapNeedPay'],
            'reachDailyReapLimit'  => $hState['reachDailyLimit'],
            'dailyFreeLimit'       => $hState['dailyFreeLimit'],
            'dailyExtraLimit'      => $hState['dailyExtraLimit'],
            'dailyHarvestCount'    => $hState['dailyCount'],
            'dailyMaxLimit'        => $hState['dailyMaxLimit'],
            'dailyChargeReapCount' => $hState['chargeCount'],
            'remainingFreeCount'   => max(0, $hState['dailyFreeLimit'] - $hState['dailyCount']),
            'remainingExtraCount'  => max(0, $hState['dailyExtraLimit'] - $hState['chargeCount']),
            'remaining_sec'        => $hState['remainingSec'],
            'coin'                 => $hState['coinCost'],
            'playerGold'           => $playerGold,
            'requiredItem'         => $reqItemKey !== null ? [
                'Item'   => $reqItemKey,
                'amount' => 1,
                'owned'  => $reqOwned,
            ] : null,
            'previewMultiplier'    => $previewMult,
            'previewItems'         => $previewItems,
        ]);
    }

    // ── Guild Dominance: harvest daily rewards & repeat plunder ───────────────
    public static function harvestDominance($db) {
        $player = self::authenticatePlayer($db, true);
        $idPlayer = (int)$player['id_player'];
        $playerGuildId = (int)($player['id_guild'] ?? 0);

        if (!$idPlayer || !$playerGuildId) {
            http_response_code(401);
            sendJson(['state' => 'not_logged_in']);
            return;
        }

        $xCoord = (int)($_REQUEST['xCoord'] ?? 0);
        $yCoord = (int)($_REQUEST['yCoord'] ?? 0);
        $payMethod = trim((string)($_REQUEST['payMethod'] ?? ''));

        $db->beginTransaction();
        try {
            // 1. Fetch dominance record (without locking the player row to avoid deadlocks)
            $gdStmt = $db->prepare(
                'SELECT gd.id_guild, gd.unit_type, gd.unit_lvl, gd.daily_food, gd.daily_wood, gd.daily_stone, gd.daily_metal, gd.daily_coin ' .
                'FROM guild_dominance gd ' .
                'WHERE gd.x_coord = ? AND gd.y_coord = ? LIMIT 1 FOR UPDATE'
            );
            $gdStmt->execute([$xCoord, $yCoord]);
            $gd = $gdStmt->fetch(PDO::FETCH_ASSOC);

            if (!$gd) {
                $db->rollBack();
                sendJson(['state' => 'not_dominated']);
                return;
            }
            if ((int)$gd['id_guild'] !== $playerGuildId) {
                $db->rollBack();
                sendJson(['state' => 'not_your_guild']);
                return;
            }

            $unitType = (int)($gd['unit_type'] ?? $_REQUEST['unitType'] ?? 0);
            $unitLvl = max(1, (int)($gd['unit_lvl'] ?? 1));

            // 2. Check per-player harvest window & daily repeat count
            $hState = self::fetchPlayerHarvestState($db, $xCoord, $yCoord, $idPlayer, $unitType, true);
            $now = $hState['now'];
            $isFree = $hState['canHarvestFree'];
            $plunderEntries = self::getPlunderEntriesForUnit($unitType, $unitLvl);

            if (!$isFree) {
                if ($hState['reachDailyLimit']) {
                    $db->rollBack();
                    sendJson([
                        'state'               => 'daily_reap_limit',
                        'reachDailyReapLimit' => true,
                        'remaining_sec'       => $hState['remainingSec'],
                        'dailyHarvestCount'   => $hState['dailyCount'],
                        'dailyMaxLimit'       => $hState['dailyMaxLimit'],
                    ]);
                    return;
                }

                if ($payMethod === '') {
                    $db->rollBack();
                    sendJson([
                        'state'               => 'harvest_cooldown',
                        'remaining_sec'       => $hState['remainingSec'],
                        'reapNeedPay'         => true,
                        'reachDailyReapLimit' => false,
                        'coin'                => $hState['coinCost'],
                        'dailyHarvestCount'   => $hState['dailyCount'],
                        'dailyMaxLimit'       => $hState['dailyMaxLimit'],
                        'dailyChargeReapCount'=> $hState['chargeCount'],
                        'requiredItem'        => $hState['requiredItemKey'] !== null ? [
                            'Item'   => $hState['requiredItemKey'],
                            'amount' => 1,
                        ] : null,
                    ]);
                    return;
                }

                if ($payMethod === 'gold') {
                    $goldCost = $hState['coinCost'];
                    $goldStmt = $db->prepare('UPDATE player SET gold = gold - ? WHERE id_player = ? AND gold >= ?');
                    $goldStmt->execute([$goldCost, $idPlayer, $goldCost]);
                    if ($goldStmt->rowCount() !== 1) {
                        $db->rollBack();
                        sendJson(['state' => 'not_enough_gold', 'requiredGold' => $goldCost]);
                        return;
                    }
                } elseif ($payMethod === 'item') {
                    $reqItem = $hState['requiredItemKey'];
                    if ($reqItem === null) {
                        $db->rollBack();
                        sendJson(['state' => 'error_invalid_item']);
                        return;
                    }
                    $deductStmt = $db->prepare(
                        'UPDATE player_item SET amount = amount - 1 WHERE id_player = ? AND id_item = ? AND amount >= 1'
                    );
                    $deductStmt->execute([$idPlayer, $reqItem]);
                    if ($deductStmt->rowCount() !== 1) {
                        $db->rollBack();
                        sendJson(['state' => 'not_enough_items', 'requiredItem' => $reqItem, 'requiredAmount' => 1]);
                        return;
                    }
                    $db->prepare(
                        'DELETE FROM player_item WHERE id_player = ? AND id_item = ? AND amount <= 0'
                    )->execute([$idPlayer, $reqItem]);
                } else {
                    $db->rollBack();
                    sendJson(['state' => 'error_invalid_pay_method']);
                    return;
                }
            }

            // 3. On free daily harvest, find player's first city & credit daily resources
            $resources = [
                'food'  => 0,
                'wood'  => 0,
                'stone' => 0,
                'metal' => 0,
                'coin'  => 0,
            ];
            if ($isFree) {
                $cityStmt = $db->prepare(
                    'SELECT id_city FROM city WHERE id_player = ? ORDER BY id_city ASC LIMIT 1 FOR UPDATE'
                );
                $cityStmt->execute([$idPlayer]);
                $cityId = (int)$cityStmt->fetchColumn();
                if ($cityId <= 0) {
                    $db->rollBack();
                    sendJson(['state' => 'no_city']);
                    return;
                }

                $resources = [
                    'food'  => (int)$gd['daily_food'],
                    'wood'  => (int)$gd['daily_wood'],
                    'stone' => (int)$gd['daily_stone'],
                    'metal' => (int)$gd['daily_metal'],
                    'coin'  => (int)$gd['daily_coin'],
                ];
                $sets = implode(', ', array_map(fn($key) => "$key = $key + ?", array_keys($resources)));
                $db->prepare("UPDATE city SET $sets WHERE id_city = ? AND id_player = ?")
                    ->execute(array_merge(array_values($resources), [$cityId, $idPlayer]));
            }

            // 4. Roll Plunder prizes from raw Plunder table (keeping high PRIZE_RATE_SCALE = 1000)
            $plunderMult = $isFree ? 1 : (1 + $hState['nextExtraIndex']);
            $prizeList = self::rollPlunderPrizes($plunderEntries, $plunderMult);
            if (!empty($prizeList)) {
                $itemStmt = $db->prepare(
                    'INSERT INTO player_item (id_player, id_item, amount) VALUES (?, ?, ?) ' .
                    'ON DUPLICATE KEY UPDATE amount = amount + VALUES(amount)'
                );
                foreach ($prizeList as $prizeRow) {
                    $itemStmt->execute([$idPlayer, $prizeRow['Item'], (int)$prizeRow['amount']]);
                }
            }

            // 5. Record this player's harvest timestamp & daily window count
            $newWindowStart = $hState['inWindow'] ? $hState['windowStartedAt'] : $now;
            $newHarvestCount = $hState['inWindow'] ? ($hState['dailyCount'] + 1) : 1;
            try {
                $db->prepare(
                    'INSERT INTO guild_dominance_harvest (x_coord, y_coord, id_player, id_guild, harvested_at, harvest_count, window_started_at) ' .
                    'VALUES (?, ?, ?, ?, ?, ?, ?) ' .
                    'ON DUPLICATE KEY UPDATE id_guild = VALUES(id_guild), harvested_at = VALUES(harvested_at), ' .
                    'harvest_count = VALUES(harvest_count), window_started_at = VALUES(window_started_at)'
                )->execute([$xCoord, $yCoord, $idPlayer, $playerGuildId, $now, $newHarvestCount, $newWindowStart]);
            } catch (\Throwable $e) {
                $db->prepare(
                    'INSERT INTO guild_dominance_harvest (x_coord, y_coord, id_player, id_guild, harvested_at) ' .
                    'VALUES (?, ?, ?, ?, ?) ' .
                    'ON DUPLICATE KEY UPDATE id_guild = VALUES(id_guild), harvested_at = VALUES(harvested_at)'
                )->execute([$xCoord, $yCoord, $idPlayer, $playerGuildId, $now]);
            }

            $goldRemaining = null;
            if ($payMethod === 'gold') {
                try {
                    $gStmt = $db->prepare('SELECT gold FROM player WHERE id_player = ? LIMIT 1');
                    $gStmt->execute([$idPlayer]);
                    $gVal = $gStmt->fetchColumn();
                    if ($gVal !== false) $goldRemaining = (int)$gVal;
                } catch (\Throwable $ignored) {}
            }

            $db->commit();
        } catch (\Throwable $error) {
            if ($db->inTransaction()) $db->rollBack();
            throw $error;
        }

        $response = [
            'state'         => 'ok',
            'harvested'     => $resources,
            'PrizeList'     => $prizeList,
            'isRepeat'      => !$isFree,
            'payMethod'     => $payMethod !== '' ? $payMethod : 'free',
            'harvestCount'  => $newHarvestCount,
            'dailyMaxLimit' => self::MAX_DAILY_FREE_REAP_TIMES + self::MAX_DAILY_EXTRA_REAP_TIMES,
        ];
        if ($payMethod === 'gold') {
            $response['goldCost'] = $hState['coinCost'];
            if ($goldRemaining !== null) {
                $response['goldRemaining'] = $goldRemaining;
            }
        } elseif ($payMethod === 'item') {
            $response['consumedItem'] = ['Item' => $hState['requiredItemKey'], 'amount' => 1];
        }
        sendJson($response);
    }

    // ══════════════════════════════════════════════════════════════════════════════
    // طابور هجوم القلاع (184-186)
    // ══════════════════════════════════════════════════════════════════════════════

    /**
     * GET /api/AWorld/getGuildAttackQue
     * يعيد قائمة طابور الهجوم الحالية للقلعة (حتى 10 أحلاف).
     */
    public static function getGuildAttackQue($db): void {
        $xCoord = (int)($_GET['xCoord'] ?? 0);
        $yCoord = (int)($_GET['yCoord'] ?? 0);
        if (!$xCoord || !$yCoord) {
            sendJson([]);
            return;
        }
        $rows = $db->prepare(
            'SELECT id_guild, guild_name, slot, time_start, time_end ' .
            'FROM castle_attack_queue ' .
            'WHERE x_coord = ? AND y_coord = ? ' .
            'ORDER BY slot ASC LIMIT 10'
        );
        $rows->execute([$xCoord, $yCoord]);
        $list = [];
        foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $list[] = [
                'GuildName'  => $row['guild_name'],
                'id_guild'   => (int)$row['id_guild'],
                'slot'       => (int)$row['slot'],
                'time_start' => (int)$row['time_start'],
                'time_end'   => (int)$row['time_end'],
            ];
        }
        sendJson($list);
    }

    /**
     * POST /api/ABattel/applyForRoleInAttQue
     * يسجل حلف اللاعب في طابور هجوم القلعة.
     * الحد الأقصى 10 أحلاف. تُحسب النوافذ الزمنية بناءً على ترتيب الانضمام:
     * كل حلف يحصل على 24 ساعة ابتداءً من الساعة 22:00 بعد (slot-1) أيام.
     */
    public static function applyForRoleInAttQue($db): void {
        $token  = $_POST['token']  ?? '';
        $server = $_POST['server'] ?? '';
        $xCoord = (int)($_POST['xCoord'] ?? 0);
        $yCoord = (int)($_POST['yCoord'] ?? 0);

        if (!$token || !$xCoord || !$yCoord) {
            sendJson(['state' => 'error_0']);
            return;
        }

        // التحقق من اللاعب وحلفه ورتبته
        $playerStmt = $db->prepare(
            'SELECT p.id_player, p.id_guild, g.name AS guild_name, gm.rank AS guild_rank ' .
            'FROM player p ' .
            'JOIN guild_member gm ON gm.id_player = p.id_player ' .
            'JOIN guild g ON g.id_guild = gm.id_guild ' .
            'WHERE p.token = ? AND p.id_server = ? LIMIT 1'
        );
        $playerStmt->execute([$token, $server]);
        $player = $playerStmt->fetch(PDO::FETCH_ASSOC);

        if (!$player || !$player['id_guild']) {
            sendJson(['state' => 'error_3']); // ليس في حلف
            return;
        }
        // شرط الرتبة: قسطور (3) أو أعلى
        if ((int)$player['guild_rank'] < 3) {
            sendJson(['state' => 'error_3']);
            return;
        }

        $guildId   = (int)$player['id_guild'];
        $guildName = $player['guild_name'];
        $now       = time();

        // التحقق من عدم وجود تسجيل مسبق لهذا الحلف
        $existingStmt = $db->prepare(
            'SELECT id FROM castle_attack_queue WHERE x_coord = ? AND y_coord = ? AND id_guild = ? LIMIT 1'
        );
        $existingStmt->execute([$xCoord, $yCoord, $guildId]);
        if ($existingStmt->fetch()) {
            sendJson(['state' => 'error_0']); // تم حجز دور بالفعل
            return;
        }

        // حساب الدور التالي المتاح
        $countStmt = $db->prepare(
            'SELECT COUNT(*) AS cnt FROM castle_attack_queue WHERE x_coord = ? AND y_coord = ?'
        );
        $countStmt->execute([$xCoord, $yCoord]);
        $count = (int)($countStmt->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0);

        if ($count >= 10) {
            sendJson(['state' => 'error_1']); // لا يوجد مكان خالى، أقصى عدد 10
            return;
        }

        $slot = $count + 1;

        // حساب النافذة الزمنية: الساعة 22:00 بتوقيت السيرفر + (slot-1) أيام
        $todayAt22 = mktime(22, 0, 0);
        if ($now >= $todayAt22) {
            $todayAt22 += 86400; // إذا تجاوزنا 22:00 اليوم، ابدأ من الغد
        }
        $windowStart = $todayAt22 + ($slot - 1) * 86400;
        $windowEnd   = $windowStart + 86400;

        $db->prepare(
            'INSERT INTO castle_attack_queue ' .
            '(x_coord, y_coord, id_guild, guild_name, slot, time_start, time_end, applied_at) ' .
            'VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$xCoord, $yCoord, $guildId, $guildName, $slot, $windowStart, $windowEnd, $now]);

        // إعادة قائمة الطابور المحدثة
        $queueStmt = $db->prepare(
            'SELECT id_guild, guild_name, slot, time_start, time_end ' .
            'FROM castle_attack_queue WHERE x_coord = ? AND y_coord = ? ORDER BY slot ASC LIMIT 10'
        );
        $queueStmt->execute([$xCoord, $yCoord]);
        $queueList = [];
        foreach ($queueStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $queueList[] = [
                'GuildName'  => $row['guild_name'],
                'id_guild'   => (int)$row['id_guild'],
                'slot'       => (int)$row['slot'],
                'time_start' => (int)$row['time_start'],
                'time_end'   => (int)$row['time_end'],
            ];
        }
        sendJson(['state' => 'ok', 'QueueList' => $queueList]);
    }
}
