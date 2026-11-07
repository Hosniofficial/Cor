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
        }
        return false;
    }

    public static function getCityHero($db) {
        $stmt = $db->query("SELECT * FROM `city_hero` ORDER BY `id_hero` ASC");
        $heroes = $stmt->fetchAll() ?: [];
        foreach ($heroes as &$h) {
            foreach ($h as $k => $v) {
                if (is_numeric($v)) $h[$k] = (int)$v;
            }
        }
        sendJson($heroes);
    }

    public static function getCityHeroArmy($db) {
        $stmt = $db->query("SELECT * FROM `hero_army` ORDER BY id_hero ASC");
        $rows = $stmt->fetchAll() ?: [];
        foreach ($rows as &$r) {
            foreach (['id_hero', 'id_player', 'f_1_type', 'f_1_num', 'f_2_type', 'f_2_num', 'f_3_type', 'f_3_num', 'b_1_type', 'b_1_num', 'b_2_type', 'b_2_num', 'b_3_type', 'b_3_num'] as $col) {
                $r[$col] = (int)$r[$col];
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

        // Capacity check
        $heroMaxCap = (int)(30000 + ((int)$hero['point_a'] + (int)($hero['point_a_plus'] ?? 0)) * 500);
        $currentTotalCap = 0;
        foreach ($validPlaces as $p) {
            $t = (int)$heroArmy[$p . '_type'];
            $n = (int)$heroArmy[$p . '_num'];
            $currentTotalCap += (self::$soldierCap[$t] ?? 1) * $n;
        }
        $addedCap = (self::$soldierCap[$solType] ?? 1) * $amount;
        if (($currentTotalCap + $addedCap) > $heroMaxCap * 10) { // generous upper limit to avoid blocking
            sendJson(['state' => 'error_4']); // capacity exceeded
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
        $stmt = $db->query("SELECT id_hero FROM `city_hero` ORDER BY id_hero ASC");
        $rows = $stmt->fetchAll() ?: [];
        $res = [];
        foreach ($rows as $r) {
            $res[] = [
                'id_hero'      => (int)$r['id_hero'],
                'medal_ceasro' => 0,
                'medal_den'    => 0,
                'medal_leo'    => 0,
                'ceaser_eagle' => 0
            ];
        }
        sendJson($res);
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
}
