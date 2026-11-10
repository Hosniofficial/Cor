<?php
// HeroController.php - Handles heroes, hero armies, hero medals, troop transfers, and mayor assignments

require_once __DIR__ . '/../services/ProductionService.php';

class HeroController {
    private static $solMap = [
        'army_a' => 1, 'army_b' => 2, 'army_c' => 3, 'army_d' => 4, 'army_e' => 5, 'army_f' => 6,
        1 => 'army_a', 2 => 'army_b', 3 => 'army_c', 4 => 'army_d', 5 => 'army_e', 6 => 'army_f'
    ];
    private static $soldierCap = [0, 1, 3, 6, 1, 4, 8];

    public static function handle($uri, $db) {
        if (strpos($uri, '/api/AHero') === 0 || strpos($uri, '/api/ACityHero/') === 0) {
            $player = requireAuthenticatedPlayer($db);
            $theaterSelection = $uri === '/api/ACityHero/addFromTheater';

            if ($uri === '/api/ACityHero/getCityHero' || $uri === '/api/ACityHero/getCityHeroArmy') {
                if (isset($_POST['idCity']) || isset($_GET['idCity'])) {
                    requirePlayerOwnedCity($db, $player);
                }
            } elseif (strpos($uri, '/api/ACityHero/') === 0
                || in_array($uri, [
                    '/api/AHeroArmy/transArmyFromCityToHero',
                    '/api/AHeroArmy/transArmyFromHeroToCity',
                    '/api/AHero/addConsole',
                    '/api/AHero/removeConsole',
                ], true)) {
                requirePlayerOwnedCity($db, $player);
            } elseif (isset($_POST['idCity']) || isset($_GET['idCity'])) {
                requirePlayerOwnedCity($db, $player);
            }

            if (!$theaterSelection) {
                foreach (['idHero', 'idHeroFrom', 'idHeroTo', 'idHeroLeft', 'idHeroRight'] as $key) {
                    $heroId = $_REQUEST[$key] ?? null;
                    if ($heroId !== null) {
                        requirePlayerOwnedHero($db, $player, $heroId);
                    }
                }
            }

            $equipId = $_REQUEST['idEquip'] ?? null;
            if ($equipId !== null) {
                requirePlayerOwnedEquip($db, $player, $equipId);
            }
        }

        switch ($uri) {
            case '/api/ACityHero/getCityHero':
                self::getCityHero($db);
                return true;
            case '/api/ACityHero/getCityHeroArmy':
                self::getCityHeroArmy($db);
                return true;
            case '/api/AHeroArmy/refreshHeroArmy':
                self::refreshHeroArmy($db);
                return true;
            case '/api/AHero/addConsole':
                self::addConsole($db);
                return true;
            case '/api/AHero/removeConsole':
                self::removeConsole($db);
                return true;
            case '/api/ACityHero/getCityHeroMedal':
                self::getCityHeroMedal($db);
                return true;
            case '/api/AHeroArmy/transArmyFromCityToHero':
                self::transArmyFromCityToHero($db);
                return true;
            case '/api/AHeroArmy/transArmyFromHeroToCity':
                self::transArmyFromHeroToCity($db);
                return true;
            case '/api/AHeroArmy/transArmyFromHeroToHero':
                self::transArmyFromHeroToHero($db);
                return true;
            case '/api/AHeroArmy/swapHeroArmy':
                self::swapHeroArmy($db);
                return true;
            case '/api/AHeroArmy/clearHeroArmy':
                self::clearHeroArmy($db);
                return true;
            case '/api/AHeroEquip/putEquipOnHero':
                self::putEquipOnHero($db);
                return true;
            case '/api/AHeroEquip/putEquipOffHero':
                self::putEquipOffHero($db);
                return true;
            case '/api/AHero/upgradeHeroLvl':
                self::upgradeHeroLvl($db);
                return true;
            case '/api/AHero/setHeroPoints':
                self::setHeroPoints($db);
                return true;
            case '/api/AHero/resetHeroPoints':
                self::resetHeroPoints($db);
                return true;
            case '/api/AHero/addExp':
                self::addExp($db);
                return true;
            case '/api/AHero/addLoy':
                self::addLoy($db);
                return true;
            case '/api/AHero/addPower':
                self::addPower($db);
                return true;
            case '/api/AHero/fireHero':
                self::fireHero($db);
                return true;
            case '/api/AHero/educate':
            case '/api/AHero/reOrderHero':
                sendJson(['state' => 'ok']);
                return true;
            case '/api/ACityHero/refreshHeroTheater':
                self::refreshHeroTheater($db, false);
                return true;
            case '/api/ACityHero/refreshHeroTheaterWithLetter':
                self::refreshHeroTheater($db, true);
                return true;
            case '/api/ACityHero/addFromTheater':
                self::addFromTheater($db);
                return true;
            case '/api/AHeroMedal/activateCaeserMedal':
            case '/api/AHeroMedal/activateCiceroMedal':
            case '/api/AHeroMedal/activateDentatusMedal':
            case '/api/AHeroMedal/activateLeonidasMedal':
                self::activateHeroMedal($uri, $db);
                return true;
        }
        return false;
    }

    public static function getCityHero($db) {
        $player = requireAuthenticatedPlayer($db);
        $idCity = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 0;
        if ($idCity > 0) {
            requirePlayerOwnedCity($db, $player, $idCity);
            $stmt = $db->prepare("SELECT * FROM `city_hero` WHERE id_city = ? ORDER BY `ord` ASC, `id_hero` ASC");
            $stmt->execute([$idCity]);
        } else {
            $stmt = $db->prepare("SELECT * FROM `city_hero` WHERE id_player = ? ORDER BY `ord` ASC, `id_hero` ASC");
            $stmt->execute([(int)$player['id_player']]);
        }
        $heroes = $stmt->fetchAll() ?: [];
        foreach ($heroes as &$h) {
            foreach ($h as $k => $v) {
                if (is_numeric($v)) $h[$k] = (int)$v;
            }
        }
        sendJson($heroes);
    }

    public static function getCityHeroArmy($db) {
        $player = requireAuthenticatedPlayer($db);
        $idCity = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 0;
        if ($idCity > 0) {
            requirePlayerOwnedCity($db, $player, $idCity);
            $stmt = $db->prepare("SELECT ch.id_hero, ch.id_player,
                                         COALESCE(ha.f_1_type, 0) as f_1_type, COALESCE(ha.f_1_num, 0) as f_1_num,
                                         COALESCE(ha.f_2_type, 0) as f_2_type, COALESCE(ha.f_2_num, 0) as f_2_num,
                                         COALESCE(ha.f_3_type, 0) as f_3_type, COALESCE(ha.f_3_num, 0) as f_3_num,
                                         COALESCE(ha.b_1_type, 0) as b_1_type, COALESCE(ha.b_1_num, 0) as b_1_num,
                                         COALESCE(ha.b_2_type, 0) as b_2_type, COALESCE(ha.b_2_num, 0) as b_2_num,
                                         COALESCE(ha.b_3_type, 0) as b_3_type, COALESCE(ha.b_3_num, 0) as b_3_num,
                                         ha.is_trans
                                  FROM `city_hero` ch
                                  LEFT JOIN `hero_army` ha ON ch.id_hero = ha.id_hero
                                  WHERE ch.id_city = ?
                                  ORDER BY ch.ord ASC, ch.id_hero ASC");
            $stmt->execute([$idCity]);
        } else {
            $stmt = $db->prepare("SELECT ch.id_hero, ch.id_player,
                                             COALESCE(ha.f_1_type, 0) as f_1_type, COALESCE(ha.f_1_num, 0) as f_1_num,
                                             COALESCE(ha.f_2_type, 0) as f_2_type, COALESCE(ha.f_2_num, 0) as f_2_num,
                                             COALESCE(ha.f_3_type, 0) as f_3_type, COALESCE(ha.f_3_num, 0) as f_3_num,
                                             COALESCE(ha.b_1_type, 0) as b_1_type, COALESCE(ha.b_1_num, 0) as b_1_num,
                                             COALESCE(ha.b_2_type, 0) as b_2_type, COALESCE(ha.b_2_num, 0) as b_2_num,
                                             COALESCE(ha.b_3_type, 0) as b_3_type, COALESCE(ha.b_3_num, 0) as b_3_num,
                                             ha.is_trans
                                      FROM `city_hero` ch
                                      LEFT JOIN `hero_army` ha ON ch.id_hero = ha.id_hero
                                      WHERE ch.id_player = ?
                                      ORDER BY ch.ord ASC, ch.id_hero ASC");
            $stmt->execute([(int)$player['id_player']]);
        }
        $rows = $stmt->fetchAll() ?: [];
        foreach ($rows as &$r) {
            foreach (['id_hero', 'id_player', 'f_1_type', 'f_1_num', 'f_2_type', 'f_2_num', 'f_3_type', 'f_3_num', 'b_1_type', 'b_1_num', 'b_2_type', 'b_2_num', 'b_3_type', 'b_3_num'] as $col) {
                if (isset($r[$col])) $r[$col] = (int)$r[$col];
            }
        }
        sendJson($rows);
    }

    private static function getOrCreateHeroArmy($db, $idHero) {
        $stmt = $db->prepare("SELECT * FROM `hero_army` WHERE id_hero = ?");
        $stmt->execute([$idHero]);
        $army = $stmt->fetch();
        if (!$army) {
            $db->prepare("INSERT IGNORE INTO `hero_army` (id_hero, id_player) VALUES (?, 1)")->execute([$idHero]);
            $stmt->execute([$idHero]);
            $army = $stmt->fetch();
        }
        if ($army) {
            foreach (['id_hero', 'id_player', 'f_1_type', 'f_1_num', 'f_2_type', 'f_2_num', 'f_3_type', 'f_3_num', 'b_1_type', 'b_1_num', 'b_2_type', 'b_2_num', 'b_3_type', 'b_3_num'] as $col) {
                $army[$col] = (int)$army[$col];
            }
        }
        return $army;
    }

    public static function calculateHeroMaxCap($db, $hero) {
        $idPlayer = (int)($hero['id_player'] ?? 1);
        $idHero   = (int)($hero['id_hero'] ?? 0);

        $pointA = (int)($hero['point_a'] ?? 20) + (int)($hero['point_a_plus'] ?? 0);
        $baseCap = 30000 + ($pointA * 500);

        // Leadership study: +3% of base cap per level
        $eduStmt = $db->prepare("SELECT leader FROM player_edu WHERE id_player = ?");
        $eduStmt->execute([$idPlayer]);
        $leaderLvl = (int)$eduStmt->fetchColumn();
        $afterStudy = (int)ceil($baseCap * ($leaderLvl * 3 / 100.0));

        // Medal buffs: +25% of point_a bonus each
        $mStmt = $db->prepare("SELECT medal_ceasro, ceaser_eagle FROM hero_medal WHERE id_hero = ?");
        $mStmt->execute([$idHero]);
        $medal = $mStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $now = time();

        $afterMedalCap = (!empty($medal['medal_ceasro']) && (int)$medal['medal_ceasro'] > $now) ? (int)floor($pointA * 500 * 0.25) : 0;
        $afterEagleCap = (!empty($medal['ceaser_eagle']) && (int)$medal['ceaser_eagle'] > $now) ? (int)floor($pointA * 500 * 0.25) : 0;

        return (int)($baseCap + $afterStudy + $afterMedalCap + $afterEagleCap);
    }

    public static function refreshHeroArmy($db) {
        $idHero = (int)($_GET['idHero'] ?? $_POST['idHero'] ?? 0);
        $army = self::getOrCreateHeroArmy($db, $idHero);
        sendJson(['state' => 'ok', 'HeroArmy' => $army]);
    }

    public static function transArmyFromCityToHero($db) {
        $idHero    = (int)($_REQUEST['idHero'] ?? 0);
        $amount    = (int)($_REQUEST['amount'] ?? 0);
        $idCity    = (int)($_REQUEST['idCity'] ?? 0);
        $heroPlace = trim($_REQUEST['ArmyPlace'] ?? ''); // e.g. 'f_1'
        $armyType  = trim($_REQUEST['ArmyType'] ?? '');  // e.g. 'army_a' or '1'

        $validPlaces = ['f_1', 'f_2', 'f_3', 'b_1', 'b_2', 'b_3'];
        if ($idHero <= 0 || $amount <= 0 || !in_array($heroPlace, $validPlaces)) {
            sendJson(['state' => 'error_3']);
        }

        // Determine column and soldier type
        $armyCol = is_numeric($armyType) ? (self::$solMap[(int)$armyType] ?? '') : $armyType;
        $solType = is_numeric($armyType) ? (int)$armyType : (self::$solMap[$armyType] ?? 0);
        if (empty($armyCol) || $solType <= 0) {
            sendJson(['state' => 'error_3']);
        }

        // Fetch City
        if ($idCity <= 0) {
            $cStmt = $db->query("SELECT id_city FROM city ORDER BY id_city ASC LIMIT 1");
            $idCity = (int)$cStmt->fetchColumn() ?: 1;
        }
        $cStmt = $db->prepare("SELECT * FROM city WHERE id_city = ?");
        $cStmt->execute([$idCity]);
        $city = $cStmt->fetch();
        if (!$city || (int)$city[$armyCol] < $amount) {
            sendJson(['state' => 'error_6']); // not enough army in city
        }

        // Fetch Hero
        $hStmt = $db->prepare("SELECT * FROM city_hero WHERE id_hero = ?");
        $hStmt->execute([$idHero]);
        $hero = $hStmt->fetch();
        if (!$hero) {
            sendJson(['state' => 'error_5']);
        }

        $heroArmy = self::getOrCreateHeroArmy($db, $idHero);
        $currType = (int)$heroArmy[$heroPlace . '_type'];
        $currNum  = (int)$heroArmy[$heroPlace . '_num'];

        if ($currNum > 0 && $currType !== $solType) {
            sendJson(['state' => 'error_3']); // cannot mix types in same slot
        }

        // Authentic Capacity check matching getHeroMaxCap & soldier_cap in obfuscated.js
        $heroMaxCap = self::calculateHeroMaxCap($db, $hero);
        $currentTotalCap = 0;
        foreach ($validPlaces as $p) {
            $t = (int)$heroArmy[$p . '_type'];
            $n = (int)$heroArmy[$p . '_num'];
            $currentTotalCap += (self::$soldierCap[$t] ?? 1) * $n;
        }
        $addedCap = (self::$soldierCap[$solType] ?? 1) * $amount;
        if (($currentTotalCap + $addedCap) > $heroMaxCap) {
            sendJson(['state' => 'error_4', 'maxCap' => $heroMaxCap, 'msg' => 'تجاوزت سعة قيادة البطل للجيوش']); // capacity exceeded
            return;
        }

        // Deduct from City
        $db->prepare("UPDATE city SET `{$armyCol}` = `{$armyCol}` - ? WHERE id_city = ?")->execute([$amount, $idCity]);
        $cStmt->execute([$idCity]);
        $updatedCity = $cStmt->fetch();
        foreach ($updatedCity as $k => $v) { if (is_numeric($v)) $updatedCity[$k] = (int)$v; }

        // Add to Hero Army
        $newNum = $currNum + $amount;
        $db->prepare("UPDATE hero_army SET `{$heroPlace}_type` = ?, `{$heroPlace}_num` = ? WHERE id_hero = ?")
           ->execute([$solType, $newNum, $idHero]);

        ProductionService::recalculateCityProduction($db, $idCity);
        $cStmt->execute([$idCity]);
        $updatedCity = $cStmt->fetch();
        foreach ($updatedCity as $k => $v) { if (is_numeric($v)) $updatedCity[$k] = (int)$v; }

        $updatedHeroArmy = self::getOrCreateHeroArmy($db, $idHero);

        sendJson([
            'state'    => 'ok',
            'City'     => $updatedCity,
            'HeroArmy' => $updatedHeroArmy
        ]);
    }

    public static function transArmyFromHeroToCity($db) {
        $idHero    = (int)($_REQUEST['idHero'] ?? 0);
        $amount    = (int)($_REQUEST['amount'] ?? 0);
        $idCity    = (int)($_REQUEST['idCity'] ?? 0);
        $heroPlace = trim($_REQUEST['ArmyPlace'] ?? ''); // e.g. 'f_1'

        $validPlaces = ['f_1', 'f_2', 'f_3', 'b_1', 'b_2', 'b_3'];
        if ($idHero <= 0 || $amount <= 0 || !in_array($heroPlace, $validPlaces)) {
            sendJson(['state' => 'error_3']);
        }

        if ($idCity <= 0) {
            $cStmt = $db->query("SELECT id_city FROM city ORDER BY id_city ASC LIMIT 1");
            $idCity = (int)$cStmt->fetchColumn() ?: 1;
        }

        $heroArmy = self::getOrCreateHeroArmy($db, $idHero);
        $slotType = (int)$heroArmy[$heroPlace . '_type'];
        $slotNum  = (int)$heroArmy[$heroPlace . '_num'];

        if ($slotNum < $amount || $slotType <= 0) {
            sendJson(['state' => 'error_3']);
        }

        $armyCol = self::$solMap[$slotType] ?? '';
        if (empty($armyCol)) {
            sendJson(['state' => 'error_3']);
        }

        // Update Hero Army
        $newNum  = $slotNum - $amount;
        $newType = ($newNum > 0) ? $slotType : 0;
        $db->prepare("UPDATE hero_army SET `{$heroPlace}_type` = ?, `{$heroPlace}_num` = ? WHERE id_hero = ?")
           ->execute([$newType, $newNum, $idHero]);

        // Add to City
        $db->prepare("UPDATE city SET `{$armyCol}` = `{$armyCol}` + ? WHERE id_city = ?")->execute([$amount, $idCity]);

        ProductionService::recalculateCityProduction($db, $idCity);
        $cStmt = $db->prepare("SELECT * FROM city WHERE id_city = ?");
        $cStmt->execute([$idCity]);
        $updatedCity = $cStmt->fetch();
        foreach ($updatedCity as $k => $v) { if (is_numeric($v)) $updatedCity[$k] = (int)$v; }

        $updatedHeroArmy = self::getOrCreateHeroArmy($db, $idHero);

        sendJson([
            'state'    => 'ok',
            'City'     => $updatedCity,
            'HeroArmy' => $updatedHeroArmy
        ]);
    }

    public static function transArmyFromHeroToHero($db) {
        $idHeroFrom    = (int)($_REQUEST['idHeroFrom'] ?? 0);
        $idHeroTo      = (int)($_REQUEST['idHeroTo'] ?? 0);
        $armyPlaceFrom = trim($_REQUEST['ArmyPlaceFrom'] ?? '');
        $armyPlaceTo   = trim($_REQUEST['ArmyPlaceTo'] ?? '');
        $amount        = (int)($_REQUEST['amount'] ?? 0);

        $validPlaces = ['f_1', 'f_2', 'f_3', 'b_1', 'b_2', 'b_3'];
        if ($idHeroFrom <= 0 || $idHeroTo <= 0 || $amount <= 0 || !in_array($armyPlaceFrom, $validPlaces) || !in_array($armyPlaceTo, $validPlaces)) {
            sendJson(['state' => 'error_3']);
        }

        $armyFrom = self::getOrCreateHeroArmy($db, $idHeroFrom);
        $armyTo   = self::getOrCreateHeroArmy($db, $idHeroTo);

        $fromType = (int)$armyFrom[$armyPlaceFrom . '_type'];
        $fromNum  = (int)$armyFrom[$armyPlaceFrom . '_num'];
        $toType   = (int)$armyTo[$armyPlaceTo . '_type'];
        $toNum    = (int)$armyTo[$armyPlaceTo . '_num'];

        if ($fromNum < $amount || $fromType <= 0) {
            sendJson(['state' => 'error_3']);
        }

        if ($toNum > 0 && $toType !== $fromType) {
            sendJson(['state' => 'error_3']);
        }

        // Deduct from From
        $newFromNum  = $fromNum - $amount;
        $newFromType = ($newFromNum > 0) ? $fromType : 0;
        $db->prepare("UPDATE hero_army SET `{$armyPlaceFrom}_type` = ?, `{$armyPlaceFrom}_num` = ? WHERE id_hero = ?")
           ->execute([$newFromType, $newFromNum, $idHeroFrom]);

        // Add to To
        $newToNum = $toNum + $amount;
        $db->prepare("UPDATE hero_army SET `{$armyPlaceTo}_type` = ?, `{$armyPlaceTo}_num` = ? WHERE id_hero = ?")
           ->execute([$fromType, $newToNum, $idHeroTo]);

        sendJson([
            'state'        => 'ok',
            'HeroArmyFrom' => self::getOrCreateHeroArmy($db, $idHeroFrom),
            'HeroArmyTo'   => self::getOrCreateHeroArmy($db, $idHeroTo)
        ]);
    }

    public static function swapHeroArmy($db) {
        $idRight = (int)($_REQUEST['idHeroRight'] ?? 0);
        $idLeft  = (int)($_REQUEST['idHeroLeft'] ?? 0);

        if ($idRight <= 0 || $idLeft <= 0) {
            sendJson(['state' => 'error_2']);
        }

        $armyRight = self::getOrCreateHeroArmy($db, $idRight);
        $armyLeft  = self::getOrCreateHeroArmy($db, $idLeft);

        $slots = ['f_1', 'f_2', 'f_3', 'b_1', 'b_2', 'b_3'];
        foreach ($slots as $s) {
            $db->prepare("UPDATE hero_army SET `{$s}_type` = ?, `{$s}_num` = ? WHERE id_hero = ?")
               ->execute([(int)$armyLeft[$s . '_type'], (int)$armyLeft[$s . '_num'], $idRight]);
            $db->prepare("UPDATE hero_army SET `{$s}_type` = ?, `{$s}_num` = ? WHERE id_hero = ?")
               ->execute([(int)$armyRight[$s . '_type'], (int)$armyRight[$s . '_num'], $idLeft]);
        }

        sendJson([
            'state'         => 'ok',
            'HeroArmyRight' => self::getOrCreateHeroArmy($db, $idRight),
            'HeroArmyLeft'  => self::getOrCreateHeroArmy($db, $idLeft)
        ]);
    }

    public static function clearHeroArmy($db) {
        $idHero = (int)($_REQUEST['idHero'] ?? 0);
        $idCity = (int)($_REQUEST['idCity'] ?? 0);

        if ($idHero <= 0) {
            sendJson(['state' => 'error_1']);
        }

        if ($idCity <= 0) {
            $cStmt = $db->query("SELECT id_city FROM city ORDER BY id_city ASC LIMIT 1");
            $idCity = (int)$cStmt->fetchColumn() ?: 1;
        }

        $army = self::getOrCreateHeroArmy($db, $idHero);
        $slots = ['f_1', 'f_2', 'f_3', 'b_1', 'b_2', 'b_3'];

        foreach ($slots as $s) {
            $t = (int)$army[$s . '_type'];
            $n = (int)$army[$s . '_num'];
            if ($t > 0 && $n > 0) {
                $col = self::$solMap[$t] ?? '';
                if (!empty($col)) {
                    $db->prepare("UPDATE city SET `{$col}` = `{$col}` + ? WHERE id_city = ?")->execute([$n, $idCity]);
                }
            }
            $db->prepare("UPDATE hero_army SET `{$s}_type` = 0, `{$s}_num` = 0 WHERE id_hero = ?")->execute([$idHero]);
        }

        $cStmt = $db->prepare("SELECT * FROM city WHERE id_city = ?");
        $cStmt->execute([$idCity]);
        $updatedCity = $cStmt->fetch();
        foreach ($updatedCity as $k => $v) { if (is_numeric($v)) $updatedCity[$k] = (int)$v; }

        sendJson([
            'state'    => 'ok',
            'City'     => $updatedCity,
            'HeroArmy' => self::getOrCreateHeroArmy($db, $idHero)
        ]);
    }

    public static function addConsole($db) {
        $idHero = (int)($_POST['idHero'] ?? $_GET['idHero'] ?? 0);
        $hStmt = $db->prepare("SELECT * FROM `city_hero` WHERE id_hero = ?");
        $hStmt->execute([$idHero]);
        $hero = $hStmt->fetch();
        if (!$hero) {
            sendJson(['state' => 'error_1']);
        }
        $idCity = (int)$hero['id_city'];
        $db->prepare("UPDATE `city_hero` SET console = 0 WHERE id_city = ?")->execute([$idCity]);
        $db->prepare("UPDATE `city_hero` SET console = 1 WHERE id_hero = ?")->execute([$idHero]);
        $db->prepare("UPDATE `city` SET console = ? WHERE id_city = ?")->execute([$idHero, $idCity]);
        ProductionService::recalculateCityProduction($db, $idCity);

        $cStmt = $db->prepare("SELECT * FROM `city` WHERE id_city = ?");
        $cStmt->execute([$idCity]);
        $city = $cStmt->fetch() ?: [];
        foreach ($city as $k => $v) { if (is_numeric($v)) $city[$k] = (int)$v; }

        $hlStmt = $db->prepare("SELECT * FROM `city_hero` WHERE id_city = ?");
        $hlStmt->execute([$idCity]);
        $heroList = $hlStmt->fetchAll() ?: [];
        foreach ($heroList as &$h) {
            foreach ($h as $k => $v) { if (is_numeric($v)) $h[$k] = (int)$v; }
        }

        sendJson([
            'state'    => 'ok',
            'City'     => $city,
            'HeroList' => $heroList
        ]);
    }

    public static function removeConsole($db) {
        $idCity = (int)($_POST['idCity'] ?? $_GET['idCity'] ?? 0);
        $db->prepare("UPDATE `city_hero` SET console = 0 WHERE id_city = ?")->execute([$idCity]);
        $db->prepare("UPDATE `city` SET console = NULL WHERE id_city = ?")->execute([$idCity]);
        ProductionService::recalculateCityProduction($db, $idCity);

        $cStmt = $db->prepare("SELECT * FROM `city` WHERE id_city = ?");
        $cStmt->execute([$idCity]);
        $city = $cStmt->fetch() ?: [];
        foreach ($city as $k => $v) { if (is_numeric($v)) $city[$k] = (int)$v; }

        $hlStmt = $db->prepare("SELECT * FROM `city_hero` WHERE id_city = ?");
        $hlStmt->execute([$idCity]);
        $heroList = $hlStmt->fetchAll() ?: [];
        foreach ($heroList as &$h) {
            foreach ($h as $k => $v) { if (is_numeric($v)) $h[$k] = (int)$v; }
        }

        sendJson([
            'state'    => 'ok',
            'City'     => $city,
            'HeroList' => $heroList
        ]);
    }

    public static function getCityHeroMedal($db) {
        $idCity = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 1;
        
        $db->exec("CREATE TABLE IF NOT EXISTS `hero_medal` (
            `id_hero` int(11) NOT NULL,
            `id_player` int(11) NOT NULL,
            `medal_ceasro` int(11) NOT NULL DEFAULT 0,
            `medal_den` int(11) NOT NULL DEFAULT 0,
            `medal_leo` int(11) NOT NULL DEFAULT 0,
            `ceaser_eagle` int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id_hero`),
            KEY `id_player` (`id_player`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        $stmt = $db->prepare("SELECT ch.id_hero, ch.id_player, 
                                     COALESCE(hm.medal_ceasro, 0) as medal_ceasro, 
                                     COALESCE(hm.medal_den, 0) as medal_den, 
                                     COALESCE(hm.medal_leo, 0) as medal_leo, 
                                     COALESCE(hm.ceaser_eagle, 0) as ceaser_eagle 
                              FROM city_hero ch 
                              LEFT JOIN hero_medal hm ON ch.id_hero = hm.id_hero 
                              WHERE ch.id_city = ?");
        $stmt->execute([$idCity]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$r) {
            foreach ($r as $k => $v) {
                if (is_numeric($v)) $r[$k] = (int)$v;
            }
        }
        sendJson($rows);
    }

    public static function putEquipOnHero($db) {
        $idEquip = isset($_REQUEST['idEquip']) ? (int)$_REQUEST['idEquip'] : 0;
        $idHero  = isset($_REQUEST['idHero']) ? (int)$_REQUEST['idHero'] : 0;

        $eStmt = $db->prepare("SELECT * FROM player_equip WHERE id_equip = ?");
        $eStmt->execute([$idEquip]);
        $equip = $eStmt->fetch();
        if (!$equip) {
            sendJson(['state' => 'error_0']);
            return;
        }

        $hStmt = $db->prepare("SELECT * FROM city_hero WHERE id_hero = ?");
        $hStmt->execute([$idHero]);
        $hero = $hStmt->fetch();
        if (!$hero) {
            sendJson(['state' => 'error_1']);
            return;
        }

        $part = $equip['part'];
        $affected = [];

        // If hero already has equip in that part, unequip old one
        $oldStmt = $db->prepare("SELECT id_equip FROM player_equip WHERE id_hero = ? AND part = ? AND on_hero = 1");
        $oldStmt->execute([$idHero, $part]);
        $oldEquips = $oldStmt->fetchAll();
        foreach ($oldEquips as $old) {
            $db->prepare("UPDATE player_equip SET id_hero = 0, on_hero = 0 WHERE id_equip = ?")->execute([$old['id_equip']]);
            $affected[] = [
                'id_equip' => (int)$old['id_equip'],
                'id_hero'  => 0,
                'on_hero'  => 0
            ];
        }

        // Equip new piece
        $db->prepare("UPDATE player_equip SET id_hero = ?, on_hero = 1 WHERE id_equip = ?")->execute([$idHero, $idEquip]);
        $affected[] = [
            'id_equip' => $idEquip,
            'id_hero'  => $idHero,
            'on_hero'  => 1
        ];

        sendJson(['state' => 'ok', 'PlayerEquip' => $affected]);
    }

    public static function putEquipOffHero($db) {
        $idEquip = isset($_REQUEST['idEquip']) ? (int)$_REQUEST['idEquip'] : 0;

        $eStmt = $db->prepare("SELECT * FROM player_equip WHERE id_equip = ?");
        $eStmt->execute([$idEquip]);
        $equip = $eStmt->fetch();
        if (!$equip) {
            sendJson(['state' => 'error_0']);
            return;
        }

        $db->prepare("UPDATE player_equip SET id_hero = 0, on_hero = 0 WHERE id_equip = ?")->execute([$idEquip]);

        sendJson([
            'state' => 'ok',
            'PlayerEquip' => [
                [
                    'id_equip' => $idEquip,
                    'id_hero'  => 0,
                    'on_hero'  => 0
                ]
            ]
        ]);
    }

    public static function upgradeHeroLvl($db) {
        $idHero = isset($_REQUEST['idHero']) ? (int)$_REQUEST['idHero'] : 0;
        $stmt = $db->prepare("SELECT * FROM city_hero WHERE id_hero = ?");
        $stmt->execute([$idHero]);
        $hero = $stmt->fetch();
        if (!$hero) {
            sendJson(['state' => 'error_0']);
        }
        $currLvl = (int)$hero['lvl'];
        
        // Exact original scholarship formula from obfuscated.js:18415-18429
        $idPlayer = (int)$hero['id_player'];
        $eduStmt = $db->prepare("SELECT scholership FROM player_edu WHERE id_player = ?");
        $eduStmt->execute([$idPlayer]);
        $scholership = (int)$eduStmt->fetchColumn();

        $studyEffect = 0.0;
        if ($scholership >= 20) {
            $studyEffect = 0.5 + ($scholership - 20) * 0.015;
        } elseif ($scholership >= 10) {
            $studyEffect = 0.3 + ($scholership - 10) * 0.02;
        } else {
            $studyEffect = $scholership * 0.03;
        }

        $reqExp = (int)floor(pow($currLvl, 2) * (1.0 - $studyEffect) * 125);
        if ((int)$hero['exp'] < $reqExp) {
            sendJson(['state' => 'not_enough_exp', 'reqExp' => $reqExp, 'currExp' => (int)$hero['exp']]);
        }
        $newLvl = $currLvl + 1;
        $newExp = (int)$hero['exp'] - $reqExp;
        $ptsGained = max(1, (int)$hero['ultra_p']);
        $newPoints = (int)$hero['points'] + $ptsGained;
        $newPowerMax = 50 + $newLvl * 2;
        $db->prepare("UPDATE city_hero SET lvl = ?, exp = ?, points = ?, power_max = ? WHERE id_hero = ?")
           ->execute([$newLvl, $newExp, $newPoints, $newPowerMax, $idHero]);

        $uHero = $db->query("SELECT * FROM city_hero WHERE id_hero = $idHero")->fetch();
        foreach ($uHero as $k => $v) { if (is_numeric($v)) $uHero[$k] = (int)$v; }

        $idCity = (int)$uHero['id_city'];
        $cStmt = $db->prepare("SELECT * FROM city WHERE id_city = ?");
        $cStmt->execute([$idCity]);
        $city = $cStmt->fetch();
        foreach ($city as $k => $v) { if (is_numeric($v)) $city[$k] = (int)$v; }

        sendJson(['state' => 'ok', 'Hero' => $uHero, 'City' => $city]);
    }

    public static function setHeroPoints($db) {
        $idHero = isset($_REQUEST['idHero']) ? (int)$_REQUEST['idHero'] : 0;
        $pA = max(0, (int)($_REQUEST['pointA'] ?? 0));
        $pB = max(0, (int)($_REQUEST['pointB'] ?? 0));
        $pC = max(0, (int)($_REQUEST['pointC'] ?? 0));
        $totalAdd = $pA + $pB + $pC;

        $stmt = $db->prepare("SELECT * FROM city_hero WHERE id_hero = ?");
        $stmt->execute([$idHero]);
        $hero = $stmt->fetch();
        if (!$hero || (int)$hero['points'] < $totalAdd) {
            sendJson(['state' => 'error']);
        }

        $newPA = (int)$hero['point_a'] + $pA;
        $newPB = (int)$hero['point_b'] + $pB;
        $newPC = (int)$hero['point_c'] + $pC;
        $remPoints = (int)$hero['points'] - $totalAdd;

        $db->prepare("UPDATE city_hero SET point_a = ?, point_b = ?, point_c = ?, points = ? WHERE id_hero = ?")
           ->execute([$newPA, $newPB, $newPC, $remPoints, $idHero]);

        $uHero = $db->query("SELECT * FROM city_hero WHERE id_hero = $idHero")->fetch();
        foreach ($uHero as $k => $v) { if (is_numeric($v)) $uHero[$k] = (int)$v; }

        $idCity = (int)$uHero['id_city'];
        $city = $db->query("SELECT * FROM city WHERE id_city = $idCity")->fetch();
        foreach ($city as $k => $v) { if (is_numeric($v)) $city[$k] = (int)$v; }

        sendJson(['state' => 'ok', 'Hero' => $uHero, 'CityRes' => $city, 'City' => $city]);
    }

    public static function resetHeroPoints($db) {
        $idHero = isset($_REQUEST['idHero']) ? (int)$_REQUEST['idHero'] : 0;
        $stmt = $db->prepare("SELECT h.*, c.id_player FROM city_hero h JOIN city c ON h.id_city = c.id_city WHERE h.id_hero = ?");
        $stmt->execute([$idHero]);
        $hero = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$hero) {
            sendJson(['state' => 'error_0', 'msg' => 'البطل ليس ملك لك']);
            return;
        }

        $playerId = (int)$hero['id_player'];
        $reqItemAmount = max(1, (int)floor(((int)$hero['lvl']) / 10) + 1);

        // Deduct retreat_point if player owns it
        $hasItem = (int)$db->query("SELECT amount FROM player_item WHERE id_player = $playerId AND id_item = 'retreat_point'")->fetchColumn();
        if ($hasItem > 0) {
            ItemController::deductPlayerItem($db, $playerId, 'retreat_point', min($hasItem, $reqItemAmount));
        }

        $diffA = max(0, (int)$hero['point_a'] - (int)$hero['p_b_a']);
        $diffB = max(0, (int)$hero['point_b'] - (int)$hero['p_b_b']);
        $diffC = max(0, (int)$hero['point_c'] - (int)$hero['p_b_c']);
        $refunded = $diffA + $diffB + $diffC;

        $newPoints = (int)$hero['points'] + $refunded;
        $db->prepare("UPDATE city_hero SET point_a = p_b_a, point_b = p_b_b, point_c = p_b_c, points = ? WHERE id_hero = ?")
           ->execute([$newPoints, $idHero]);

        $uHero = $db->query("SELECT * FROM city_hero WHERE id_hero = $idHero")->fetch(PDO::FETCH_ASSOC);
        foreach ($uHero as $k => $v) { if (is_numeric($v)) $uHero[$k] = (int)$v; }

        $idCity = (int)$uHero['id_city'];
        $city = $db->query("SELECT * FROM city WHERE id_city = $idCity")->fetch(PDO::FETCH_ASSOC);
        foreach ($city as $k => $v) { if (is_numeric($v)) $city[$k] = (int)$v; }

        sendJson([
            'state'       => 'ok',
            'Hero'        => $uHero,
            'City'        => $city,
            'CityRes'     => $city,
            'cityStorage' => ['foodPerc' => 25, 'woodPerc' => 25, 'stonePerc' => 25, 'metalPerc' => 25]
        ]);
    }

    public static function addExp($db) {
        $idHero = isset($_REQUEST['idHero']) ? (int)$_REQUEST['idHero'] : 0;
        $item = isset($_REQUEST['itemToUse']) ? trim($_REQUEST['itemToUse']) : '';

        $stmt = $db->prepare("SELECT h.*, c.id_player FROM city_hero h JOIN city c ON h.id_city = c.id_city WHERE h.id_hero = ?");
        $stmt->execute([$idHero]);
        $hero = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$hero) {
            sendJson(['state' => 'error_0', 'msg' => 'البطل ليس ملك لك']);
            return;
        }

        $playerId = (int)$hero['id_player'];

        $expMap = [
            'exp_hero_8'    => 1000,
            'exp_hero_30'   => 5000,
            'exp_hero_100'  => 20000,
            'hero_exp_10k'  => 10000,
            'hero_exp_50k'  => 50000,
            'hero_exp_100k' => 100000,
            'hero_train'    => 5000
        ];
        $expGain = isset($expMap[$item]) ? $expMap[$item] : 1000;

        $hasItem = (int)$db->query("SELECT amount FROM player_item WHERE id_player = $playerId AND id_item = '$item'")->fetchColumn();
        if ($hasItem <= 0) {
            sendJson(['state' => 'error_2', 'msg' => 'لا تمتلك مواد كافية']);
            return;
        }

        ItemController::deductPlayerItem($db, $playerId, $item, 1);

        $db->prepare("UPDATE city_hero SET exp = exp + ? WHERE id_hero = ?")->execute([$expGain, $idHero]);

        $uHero = $db->query("SELECT * FROM city_hero WHERE id_hero = $idHero")->fetch(PDO::FETCH_ASSOC);
        foreach ($uHero as $k => $v) { if (is_numeric($v)) $uHero[$k] = (int)$v; }

        sendJson(['state' => 'ok', 'Exp' => $expGain, 'Hero' => $uHero]);
    }

    public static function addLoy($db) {
        $idHero = isset($_REQUEST['idHero']) ? (int)$_REQUEST['idHero'] : 0;
        $item   = isset($_REQUEST['itemToUse']) ? trim($_REQUEST['itemToUse']) : '';

        $stmt = $db->prepare("SELECT h.*, c.id_player FROM city_hero h JOIN city c ON h.id_city = c.id_city WHERE h.id_hero = ?");
        $stmt->execute([$idHero]);
        $hero = $stmt->fetch();
        if (!$hero) {
            sendJson(['state' => 'error_0', 'msg' => 'البطل ليس ملك لك']);
            return;
        }

        $playerId = (int)$hero['id_player'];
        $currLoy = (int)($hero['loyal'] ?? 0);
        if ($currLoy >= 100) {
            sendJson(['state' => 'error_1', 'msg' => 'البطل فى اعلى قدرة ولاء ممكنة']);
            return;
        }

        $loyMap = [
            'luxury_1'   => 5,
            'luxury_2'   => 10,
            'luxury_3'   => 15,
            'luxury_4'   => 20,
            'luxury_5'   => 25,
            'luxury_6'   => 30,
            'luxury_7'   => 35,
            'luxury_8'   => 40,
            'luxury_9'   => 50,
            'bronze_cup' => 10,
            'silver_cup' => 20,
            'gold_cup'   => 30
        ];
        $loyGain = isset($loyMap[$item]) ? $loyMap[$item] : 10;

        // Check if player has item
        $hasItem = (int)$db->query("SELECT amount FROM player_item WHERE id_player = $playerId AND id_item = '$item'")->fetchColumn();
        if ($hasItem <= 0) {
            sendJson(['state' => 'error_2', 'msg' => 'لا تمتلك مواد كافية']);
            return;
        }

        ItemController::deductPlayerItem($db, $playerId, $item, 1);

        $db->prepare("UPDATE city_hero SET loyal = LEAST(100, loyal + ?) WHERE id_hero = ?")->execute([$loyGain, $idHero]);

        $uHero = $db->query("SELECT * FROM city_hero WHERE id_hero = $idHero")->fetch();
        foreach ($uHero as $k => $v) { if (is_numeric($v)) $uHero[$k] = (int)$v; }
        sendJson(['state' => 'ok', 'Hero' => $uHero, 'loy' => $loyGain]);
    }

    public static function addPower($db) {
        $idHero = isset($_REQUEST['idHero']) ? (int)$_REQUEST['idHero'] : 0;
        $item = isset($_REQUEST['itemToUse']) ? trim($_REQUEST['itemToUse']) : '';

        $stmt = $db->prepare("SELECT h.*, c.id_player FROM city_hero h JOIN city c ON h.id_city = c.id_city WHERE h.id_hero = ?");
        $stmt->execute([$idHero]);
        $hero = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$hero) {
            sendJson(['state' => 'error_0', 'msg' => 'البطل ليس ملك لك']);
            return;
        }

        $playerId = (int)$hero['id_player'];
        $power = (int)$hero['power'];
        $powerMax = (int)$hero['power_max'];

        if ($power >= $powerMax) {
            sendJson(['state' => 'error_1', 'msg' => 'البطل فى اعلى قدرة بدنية ممكنة']);
            return;
        }

        $powMap = [
            'bread' => 20, 'milk' => 25, 'fruit' => 30, 'meat' => 50,
            'hero_power_20' => 20, 'hero_power_50' => 50, 'hero_power_100' => 100
        ];
        $powGain = isset($powMap[$item]) ? $powMap[$item] : 30;

        $hasItem = (int)$db->query("SELECT amount FROM player_item WHERE id_player = $playerId AND id_item = '$item'")->fetchColumn();
        if ($hasItem <= 0) {
            sendJson(['state' => 'error_2', 'msg' => 'لا تمتلك مواد كافية']);
            return;
        }

        ItemController::deductPlayerItem($db, $playerId, $item, 1);

        $db->prepare("UPDATE city_hero SET power = LEAST(power_max, power + ?) WHERE id_hero = ?")->execute([$powGain, $idHero]);

        $uHero = $db->query("SELECT * FROM city_hero WHERE id_hero = $idHero")->fetch(PDO::FETCH_ASSOC);
        foreach ($uHero as $k => $v) { if (is_numeric($v)) $uHero[$k] = (int)$v; }

        sendJson(['state' => 'ok', 'power' => $powGain, 'Hero' => $uHero]);
    }

    public static function activateHeroMedal($uri, $db) {
        $idHero = isset($_REQUEST['idHero']) ? (int)$_REQUEST['idHero'] : 0;
        
        $hStmt = $db->prepare("SELECT h.*, c.id_player, c.id_city FROM city_hero h JOIN city c ON h.id_city = c.id_city WHERE h.id_hero = ?");
        $hStmt->execute([$idHero]);
        $hero = $hStmt->fetch(PDO::FETCH_ASSOC);
        if (!$hero) {
            sendJson(['state' => 'error_0', 'msg' => 'البطل ليس ملك لك']);
            return;
        }

        $playerId = (int)$hero['id_player'];
        $idCity   = (int)$hero['id_city'];

        $db->exec("CREATE TABLE IF NOT EXISTS `hero_medal` (
            `id_hero` int(11) NOT NULL,
            `id_player` int(11) NOT NULL,
            `medal_ceasro` int(11) NOT NULL DEFAULT 0,
            `medal_den` int(11) NOT NULL DEFAULT 0,
            `medal_leo` int(11) NOT NULL DEFAULT 0,
            `ceaser_eagle` int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id_hero`),
            KEY `id_player` (`id_player`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        $db->prepare("INSERT IGNORE INTO `hero_medal` (`id_hero`, `id_player`) VALUES (?, ?)")->execute([$idHero, $playerId]);

        $medalItem = '';
        $statField = '';
        if (strpos($uri, 'activateCiceroMedal') !== false) {
            $medalItem = 'medal_ceasro';
            $statField = 'point_a';
        } else if (strpos($uri, 'activateDentatusMedal') !== false) {
            $medalItem = 'medal_den';
            $statField = 'point_b';
        } else if (strpos($uri, 'activateLeonidasMedal') !== false) {
            $medalItem = 'medal_leo';
            $statField = 'point_c';
        } else if (strpos($uri, 'activateCaeserMedal') !== false) {
            $medalItem = 'ceaser_eagle';
            $statField = 'all';
        }

        $hasItem = (int)$db->query("SELECT amount FROM player_item WHERE id_player = $playerId AND id_item = '$medalItem'")->fetchColumn();
        if ($hasItem <= 0) {
            sendJson(['state' => 'error_1', 'msg' => 'لا تمتلك مواد كافية']);
            return;
        }

        ItemController::deductPlayerItem($db, $playerId, $medalItem, 1);

        $db->prepare("UPDATE `hero_medal` SET `$medalItem` = `$medalItem` + 1 WHERE `id_hero` = ?")->execute([$idHero]);

        if ($statField === 'point_a') {
            $db->prepare("UPDATE city_hero SET point_a = point_a + 5, p_b_a = p_b_a + 5 WHERE id_hero = ?")->execute([$idHero]);
        } else if ($statField === 'point_b') {
            $db->prepare("UPDATE city_hero SET point_b = point_b + 5, p_b_b = p_b_b + 5 WHERE id_hero = ?")->execute([$idHero]);
        } else if ($statField === 'point_c') {
            $db->prepare("UPDATE city_hero SET point_c = point_c + 5, p_b_c = p_b_c + 5 WHERE id_hero = ?")->execute([$idHero]);
        } else if ($statField === 'all') {
            $db->prepare("UPDATE city_hero SET point_a = point_a + 5, p_b_a = p_b_a + 5, point_b = point_b + 5, p_b_b = p_b_b + 5, point_c = point_c + 5, p_b_c = p_b_c + 5 WHERE id_hero = ?")->execute([$idHero]);
        }

        $freshMedal = $db->query("SELECT * FROM hero_medal WHERE id_hero = $idHero")->fetch(PDO::FETCH_ASSOC);
        foreach ($freshMedal as $k => $v) { if (is_numeric($v)) $freshMedal[$k] = (int)$v; }

        $freshCity = $db->query("SELECT * FROM city WHERE id_city = $idCity")->fetch(PDO::FETCH_ASSOC);
        foreach ($freshCity as $k => $v) { if (is_numeric($v)) $freshCity[$k] = (int)$v; }

        sendJson([
            'state'     => 'ok',
            'HeroMedal' => $freshMedal,
            'CityRes'   => $freshCity
        ]);
    }

    public static function fireHero($db) {
        $idHero = isset($_REQUEST['idHero']) ? (int)$_REQUEST['idHero'] : 0;
        $db->prepare("DELETE FROM hero_army WHERE id_hero = ?")->execute([$idHero]);
        $db->prepare("UPDATE player_equip SET id_hero = 0, on_hero = 0 WHERE id_hero = ?")->execute([$idHero]);
        $db->prepare("DELETE FROM city_hero WHERE id_hero = ?")->execute([$idHero]);
        sendJson(['state' => 'ok']);
    }

    public static function refreshHeroTheater($db, $withLetter = false) {
        $idCity = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 1;
        $now = time();
        $refreshPeriod = 3600; // 1-hour periodic cycle

        // Ensure city_theater table exists
        $db->exec("CREATE TABLE IF NOT EXISTS `city_theater` (
            `id_city` int(11) NOT NULL,
            `last_update` int(11) NOT NULL DEFAULT 0,
            `refresh_time` int(11) NOT NULL DEFAULT 3600,
            `heroes` text NOT NULL,
            PRIMARY KEY (`id_city`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Check if existing pool is still valid within the current 1-hour window
        if (!$withLetter) {
            $stmt = $db->prepare("SELECT * FROM `city_theater` WHERE `id_city` = ?");
            $stmt->execute([$idCity]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row && ($now < (int)$row['last_update'] + (int)$row['refresh_time'])) {
                $existingList = json_decode($row['heroes'], true);
                if (is_array($existingList) && count($existingList) > 0) {
                    sendJson([
                        'lastUpdate'  => (int)$row['last_update'],
                        'refreshTime' => (int)$row['refresh_time'],
                        'HeroList'    => $existingList
                    ]);
                    return;
                }
            }
        }

        // Determine available hero slots based on Theater building level (Building Type 6)
        $bStmt = $db->prepare("SELECT `lvl` FROM `city_building` WHERE `id_city` = ? AND `type` = 6 LIMIT 1");
        $bStmt->execute([$idCity]);
        $theaterLvl = (int)$bStmt->fetchColumn();
        $slotCount = min(10, max(3, $theaterLvl > 0 ? $theaterLvl : 6));

        // If refreshing via recruitment letter (رسالة تجنيد), deduct 1 rec_letter from inventory
        if ($withLetter) {
            $cStmt = $db->prepare("SELECT `id_player` FROM `city` WHERE `id_city` = ?");
            $cStmt->execute([$idCity]);
            $playerId = (int)$cStmt->fetchColumn() ?: 1;
            require_once __DIR__ . '/ItemController.php';
            ItemController::deductPlayerItem($db, $playerId, 'rec_letter', 1);
        }

        $names = ["ماكسيموس", "اشرف", "مصطفى", "اليكس", "اليسا", "بطليموس", "كليوباترا", "هكس", "ماجد", "يويليوس", "مارس", "ماكس", "صلاح الدين", "سيورس", "سيزار", "اغسطس", "جلادياتور"];
        $heroList = [];
        $baseLvl = $withLetter ? rand(6, 14) : rand(1, 5);

        for ($i = 0; $i < $slotCount; $i++) {
            $nameIdx = rand(0, count($names) - 1);
            $lvl = $withLetter ? ($baseLvl + rand(0, 3)) : ($baseLvl + rand(0, 2));
            $ultra = $withLetter ? rand(2, 5) : (rand(1, 10) == 1 ? 2 : 1);
            $avatar = rand(0, 9); // 10 avatars in HeroAvatar
            
            // Ultra heroes receive elevated base stats (+5 per potential star above 1)
            $ultraStatBonus = max(0, ($ultra - 1) * 5);

            $heroList[] = [
                'id_hero'    => $i + 1,
                'name'       => $nameIdx,
                'hero_name'  => $nameIdx,
                'lvl'        => $lvl,
                'hero_lvl'   => $lvl,
                'avatar'     => $avatar,
                'hero_image' => $avatar,
                'ultra_p'    => $ultra,
                'point_a'    => $lvl + rand(18, 24) + $ultraStatBonus,
                'point_b'    => $lvl + rand(15, 22) + $ultraStatBonus,
                'point_c'    => $lvl + rand(15, 22) + $ultraStatBonus,
                'salary'     => $lvl * 10,
                'coin_fee'   => $lvl * 1000
            ];
        }

        // Save pool in city_theater
        $save = $db->prepare("INSERT INTO `city_theater` (`id_city`, `last_update`, `refresh_time`, `heroes`) 
                              VALUES (?, ?, ?, ?) 
                              ON DUPLICATE KEY UPDATE `last_update` = VALUES(`last_update`), `refresh_time` = VALUES(`refresh_time`), `heroes` = VALUES(`heroes`)");
        $save->execute([$idCity, $now, $refreshPeriod, json_encode($heroList, JSON_UNESCAPED_UNICODE)]);

        sendJson([
            'lastUpdate'  => $now,
            'refreshTime' => $refreshPeriod,
            'HeroList'    => $heroList
        ]);
    }

    public static function addFromTheater($db) {
        $idCity = isset($_REQUEST['idCity']) ? (int)$_REQUEST['idCity'] : 1;
        $idHeroTheater = isset($_REQUEST['idHero']) ? (int)$_REQUEST['idHero'] : 1;

        $cStmt = $db->prepare("SELECT * FROM `city` WHERE `id_city` = ?");
        $cStmt->execute([$idCity]);
        $city = $cStmt->fetch(PDO::FETCH_ASSOC);
        if (!$city) {
            sendJson(['state' => 'error_0', 'msg' => 'المدينة غير موجودة']);
            return;
        }
        $idPlayer = (int)$city['id_player'];

        // Fetch city_theater
        $tStmt = $db->prepare("SELECT * FROM `city_theater` WHERE `id_city` = ?");
        $tStmt->execute([$idCity]);
        $theaterRow = $tStmt->fetch(PDO::FETCH_ASSOC);
        $heroList = $theaterRow ? (json_decode($theaterRow['heroes'], true) ?: []) : [];

        // Find the selected hero in the pool
        $selectedHero = null;
        $selectedIdx = -1;
        foreach ($heroList as $idx => $h) {
            if ((int)$h['id_hero'] === $idHeroTheater) {
                $selectedHero = $h;
                $selectedIdx = $idx;
                break;
            }
        }

        if (!$selectedHero) {
            sendJson(['state' => 'error_0', 'msg' => 'البطل غير موجود بالمسرح أو تم تجنيده']);
            return;
        }

        $lvl     = (int)($selectedHero['lvl'] ?? 1);
        $ultra   = (int)($selectedHero['ultra_p'] ?? 1);
        $coinFee = (int)($selectedHero['coin_fee'] ?? ($lvl * 1000));

        // Check if city has enough sestersius coins
        if ((int)$city['coin'] < $coinFee) {
            sendJson(['state' => 'error_no_coin', 'msg' => 'لا يوجد سسترسس كافى تجنيد البطل']);
            return;
        }

        // Check Palace level hero capacity: Max heroes = max(3, Palace Level)
        $pStmt = $db->prepare("SELECT `lvl` FROM `city_building` WHERE `id_city` = ? AND `type` = 10 LIMIT 1");
        $pStmt->execute([$idCity]);
        $palaceLvl = (int)$pStmt->fetchColumn();
        $maxHeroes = max(3, $palaceLvl > 0 ? $palaceLvl : 3);

        $cntStmt = $db->prepare("SELECT COUNT(*) FROM `city_hero` WHERE `id_city` = ?");
        $cntStmt->execute([$idCity]);
        $curHeroCount = (int)$cntStmt->fetchColumn();

        if ($curHeroCount >= $maxHeroes) {
            sendJson([
                'state'    => 'error_1',
                'MaxCount' => $maxHeroes,
                'msg'      => "لا يمكنك امتلاك اكثر من {$maxHeroes} بطل فى المدينة"
            ]);
            return;
        }

        // Deduct coin fee
        $db->prepare("UPDATE `city` SET `coin` = GREATEST(0, `coin` - ?) WHERE `id_city` = ?")
           ->execute([$coinFee, $idCity]);

        $names = ["ماكسيموس", "اشرف", "مصطفى", "اليكس", "اليسا", "بطليموس", "كليوباترا", "هكس", "ماجد", "يويليوس", "مارس", "ماكس", "صلاح الدين", "سيورس", "سيزار", "اغسطس", "جلادياتور"];
        $nameIdx = (int)($selectedHero['name'] ?? 0);
        $heroName = $names[$nameIdx % count($names)];
        $avatar   = (int)($selectedHero['avatar'] ?? 0);
        $pointA   = (int)($selectedHero['point_a'] ?? ($lvl + 20));
        $pointB   = (int)($selectedHero['point_b'] ?? ($lvl + 15));
        $pointC   = (int)($selectedHero['point_c'] ?? ($lvl + 16));

        // Insert new recruited hero into city_hero with authentic stats
        $ins = $db->prepare("INSERT INTO `city_hero` 
            (`id_city`, `id_player`, `name`, `lvl`, `avatar`, `exp`, `ultra_p`, `point_a`, `point_b`, `point_c`, `p_b_a`, `p_b_b`, `p_b_c`, `power`, `power_max`, `loyal`) 
            VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, 100, 100, 80)");
        $ins->execute([$idCity, $idPlayer, $heroName, $lvl, $avatar, $ultra, $pointA, $pointB, $pointC, $pointA, $pointB, $pointC]);
        $newHeroId = (int)$db->lastInsertId();

        // Assign hero army record
        $db->prepare("INSERT IGNORE INTO `hero_army` (`id_hero`, `id_player`) VALUES (?, ?)")
           ->execute([$newHeroId, $idPlayer]);

        // Remove recruited hero from the current theater pool so he cannot be recruited twice
        if ($selectedIdx >= 0) {
            array_splice($heroList, $selectedIdx, 1);
            $db->prepare("UPDATE `city_theater` SET `heroes` = ? WHERE `id_city` = ?")
               ->execute([json_encode($heroList, JSON_UNESCAPED_UNICODE), $idCity]);
        }

        // Return updated city data and remaining theater heroes
        $freshCity = $db->query("SELECT * FROM `city` WHERE `id_city` = $idCity")->fetch(PDO::FETCH_ASSOC);
        foreach ($freshCity as $k => $v) {
            if (is_numeric($v)) $freshCity[$k] = (int)$v;
        }

        sendJson([
            'state'        => 'ok',
            'City'         => $freshCity,
            'TheaterHeros' => $heroList
        ]);
    }
}
