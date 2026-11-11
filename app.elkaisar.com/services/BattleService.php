<?php
// app.elkaisar.com/services/BattleService.php - Backend combat calculations for Elkaisar

class BattleService {

    /**
     * Authentic Army Unit Stats matching obfuscated.js lines 4680–4924
     */
    public static $unitStats = [
        1 => [ // army_a (مشاة)
            'name'        => 'مشاة',
            'key'         => 'army_a',
            'attack'      => 8,
            'defence'     => 8,
            'vit'         => 60,
            'damage'      => 4.5,
            'damage_min'  => 3,
            'damage_max'  => 6,
            'break'       => 1,
            'anti_break'  => 1,
            'strike'      => 3,
            'immunity'    => 2,
            'speed'       => 300,
            'capacity'    => 40,
            'eating'      => 4,
            'atk'         => 8,
            'def'         => 8,
            'hp'          => 60,
            'img'         => 'images/tech/soldier01.jpg'
        ],
        2 => [ // army_b (فرسان)
            'name'        => 'فرسان',
            'key'         => 'army_b',
            'attack'      => 30,
            'defence'     => 20,
            'vit'         => 250,
            'damage'      => 35.5,
            'damage_min'  => 33,
            'damage_max'  => 38,
            'break'       => 10,
            'anti_break'  => 4,
            'strike'      => 1,
            'immunity'    => 2,
            'speed'       => 900,
            'capacity'    => 100,
            'eating'      => 18,
            'atk'         => 30,
            'def'         => 20,
            'hp'          => 250,
            'img'         => 'images/tech/soldier02.jpg'
        ],
        3 => [ // army_c (مدرعين)
            'name'        => 'مدرعين',
            'key'         => 'army_c',
            'attack'      => 25,
            'defence'     => 30,
            'vit'         => 400,
            'damage'      => 50.0,
            'damage_min'  => 40,
            'damage_max'  => 60,
            'break'       => 10,
            'anti_break'  => 5,
            'strike'      => 10,
            'immunity'    => 5,
            'speed'       => 600,
            'capacity'    => 120,
            'eating'      => 36,
            'atk'         => 25,
            'def'         => 30,
            'hp'          => 400,
            'img'         => 'images/tech/soldier03.jpg'
        ],
        4 => [ // army_d (رماة)
            'name'        => 'رماة',
            'key'         => 'army_d',
            'attack'      => 9,
            'defence'     => 5,
            'vit'         => 45,
            'damage'      => 4.0,
            'damage_min'  => 3,
            'damage_max'  => 5,
            'break'       => 2,
            'anti_break'  => 2,
            'strike'      => 3,
            'immunity'    => 2,
            'speed'       => 250,
            'capacity'    => 25,
            'eating'      => 5,
            'atk'         => 9,
            'def'         => 5,
            'hp'          => 45,
            'img'         => 'images/tech/soldier04.jpg'
        ],
        5 => [ // army_e (مقاليع)
            'name'        => 'مقاليع',
            'key'         => 'army_e',
            'attack'      => 19,
            'defence'     => 25,
            'vit'         => 100,
            'damage'      => 19.0,
            'damage_min'  => 18,
            'damage_max'  => 20,
            'break'       => 5,
            'anti_break'  => 2,
            'strike'      => 15,
            'immunity'    => 8,
            'speed'       => 150,
            'capacity'    => 35,
            'eating'      => 20,
            'atk'         => 19,
            'def'         => 25,
            'hp'          => 100,
            'img'         => 'images/tech/soldier05.jpg'
        ],
        6 => [ // army_f (منجنيق)
            'name'        => 'منجنيق',
            'key'         => 'army_f',
            'attack'      => 40,
            'defence'     => 20,
            'vit'         => 600,
            'damage'      => 70.0,
            'damage_min'  => 70,
            'damage_max'  => 70,
            'break'       => 15,
            'anti_break'  => 4,
            'strike'      => 5,
            'immunity'    => 15,
            'speed'       => 100,
            'capacity'    => 75,
            'eating'      => 150,
            'atk'         => 40,
            'def'         => 20,
            'hp'          => 600,
            'img'         => 'images/tech/soldier06.jpg'
        ],
        7 => [ // spies (جواسيس)
            'name'        => 'جواسيس',
            'key'         => 'spies',
            'attack'      => 10,
            'defence'     => 12,
            'vit'         => 80,
            'damage'      => 7.5,
            'damage_min'  => 6,
            'damage_max'  => 9,
            'break'       => 0,
            'anti_break'  => 0,
            'strike'      => 0,
            'immunity'    => 0,
            'speed'       => 200,
            'capacity'    => 1,
            'eating'      => 6,
            'atk'         => 10,
            'def'         => 12,
            'hp'          => 80,
            'img'         => 'images/items/item027.jpg'
        ]
    ];

    /**
     * Authentic Wall Fortifications matching obfuscated.js lines 4925–5037
     */
    public static $wallStats = [
        'wall_a' => [ // كمائن (Ambush Traps)
            'name'       => 'كمائن',
            'vit'        => 0,
            'attack'     => 0,
            'defence'    => 0,
            'damage'     => 0,
            'break'      => 0,
            'anti_break' => 0,
            'strike'     => 0,
            'immunity'   => 0,
            'wall_space' => 1,
            'img'        => 'images/tech/defense01.jpg'
        ],
        'wall_b' => [ // أبراج (Arrow Towers)
            'name'       => 'أبراج',
            'vit'        => 200,
            'attack'     => 18,
            'defence'    => 15,
            'damage'     => 13.5, // 12-15 average
            'break'      => 0,
            'anti_break' => 0,
            'strike'     => 0,
            'immunity'   => 0,
            'wall_space' => 3,
            'img'        => 'images/tech/defense02.jpg'
        ],
        'wall_c' => [ // أحجار متساقطة (Rolling Stones)
            'name'       => 'أحجار متساقطة',
            'vit'        => 0,
            'attack'     => 0,
            'defence'    => 40,
            'damage'     => 50.0, // 40-60 average
            'break'      => 0,
            'anti_break' => 0,
            'strike'     => 0,
            'immunity'   => 0,
            'wall_space' => 5,
            'img'        => 'images/tech/defense03.jpg'
        ]
    ];

    /**
     * Cache for authentic equipment stats loaded from the equipment data file.
     */
    public static $equipPowerCache = null;

    /**
     * Lookup equipment power stats for set, part, and level
     */
    public static function getEquipPowerData($equipType, $part, $lvl) {
        if (self::$equipPowerCache === null) {
            self::$equipPowerCache = [];
            $paths = [
                __DIR__ . '/../data/equipment-power.json',
                __DIR__ . '/../../web.elkaisar.com/api/equip.php'
            ];
            foreach ($paths as $path) {
                if (file_exists($path)) {
                    $raw = file_get_contents($path);
                    $items = json_decode($raw, true);
                    if (is_array($items)) {
                        foreach ($items as $item) {
                            $k = ($item['equip'] ?? '') . '_' . ($item['part'] ?? '') . '_' . ($item['lvl'] ?? 1);
                            self::$equipPowerCache[$k] = $item;
                        }
                    }
                    break;
                }
            }
        }
        $key = $equipType . '_' . $part . '_' . (int)$lvl;
        return self::$equipPowerCache[$key] ?? null;
    }

    /**
     * Executes real combat simulation between player's hero and enemy formation,
     * updates database records, and saves the detailed battle report.
     */
    public static function executeBattle($db, $idPlayer, $idHero = null, $xCoord = 150, $yCoord = 200, $targetLvl = 5, $targetType = 1, $task = 0) {
        // 1. Get Attacker Player & Hero
        $pStmt = $db->prepare("SELECT * FROM `player` WHERE `id_player` = ?");
        $pStmt->execute([$idPlayer]);
        $player = $pStmt->fetch(PDO::FETCH_ASSOC);
        if (!$player) return false;

        if (!$idHero) {
            $hStmt = $db->prepare("SELECT id_hero FROM `city_hero` WHERE `id_player` = ? ORDER BY `lvl` DESC LIMIT 1");
            $hStmt->execute([$idPlayer]);
            $idHero = (int)$hStmt->fetchColumn() ?: 1;
        }

        $heroStmt = $db->prepare("SELECT * FROM `city_hero` WHERE `id_hero` = ?");
        $heroStmt->execute([$idHero]);
        $hero = $heroStmt->fetch(PDO::FETCH_ASSOC);

        // Get Attacker troops from hero_army
        $aStmt = $db->prepare("SELECT * FROM `hero_army` WHERE `id_hero` = ?");
        $aStmt->execute([$idHero]);
        $heroArmy = $aStmt->fetch(PDO::FETCH_ASSOC);

        // If hero has no troops assigned, equip some from city
        if (!$heroArmy || ($heroArmy['f_1_num'] == 0 && $heroArmy['b_1_num'] == 0)) {
            $cityStmt = $db->prepare("SELECT * FROM `city` WHERE `id_player` = ? LIMIT 1");
            $cityStmt->execute([$idPlayer]);
            $city = $cityStmt->fetch(PDO::FETCH_ASSOC);
            $cId = $city ? $city['id_city'] : 1;

            $infQty = min(2000, max(500, (int)($city['army_a'] ?? 1000)));
            $cavQty = min(1000, max(300, (int)($city['army_b'] ?? 500)));
            $arcQty = min(1500, max(400, (int)($city['army_c'] ?? 800)));

            $db->prepare("INSERT INTO `hero_army` (`id_hero`, `id_player`, `f_1_type`, `f_1_num`, `f_2_type`, `f_2_num`, `f_3_type`, `f_3_num`, `b_1_type`, `b_1_num`, `b_2_type`, `b_2_num`, `b_3_type`, `b_3_num`) 
                          VALUES (?, ?, 1, ?, 2, ?, 0, 0, 3, ?, 0, 0, 0, 0)
                          ON DUPLICATE KEY UPDATE `f_1_type`=1, `f_1_num`=?, `f_2_type`=2, `f_2_num`=?, `b_1_type`=3, `b_1_num`=?")
               ->execute([$idHero, $idPlayer, $infQty, $cavQty, $arcQty, $infQty, $cavQty, $arcQty]);

            $aStmt->execute([$idHero]);
            $heroArmy = $aStmt->fetch(PDO::FETCH_ASSOC);
        }

        // 2. Build Attacker Formations
        $attackerSlots = [
            'f_1' => ['type' => (int)$heroArmy['f_1_type'], 'pre' => (int)$heroArmy['f_1_num'], 'cur' => (int)$heroArmy['f_1_num']],
            'f_2' => ['type' => (int)$heroArmy['f_2_type'], 'pre' => (int)$heroArmy['f_2_num'], 'cur' => (int)$heroArmy['f_2_num']],
            'f_3' => ['type' => (int)$heroArmy['f_3_type'], 'pre' => (int)$heroArmy['f_3_num'], 'cur' => (int)$heroArmy['f_3_num']],
            'b_1' => ['type' => (int)$heroArmy['b_1_type'], 'pre' => (int)$heroArmy['b_1_num'], 'cur' => (int)$heroArmy['b_1_num']],
            'b_2' => ['type' => (int)$heroArmy['b_2_type'], 'pre' => (int)$heroArmy['b_2_num'], 'cur' => (int)$heroArmy['b_2_num']],
            'b_3' => ['type' => (int)$heroArmy['b_3_type'], 'pre' => (int)$heroArmy['b_3_num'], 'cur' => (int)$heroArmy['b_3_num']],
        ];

        // 3. Build Defender Formations from UnitArmy.json
        $unitArmyPath = __DIR__ . '/../js-0.0.24/json/UnitArmy.json';
        $unitArmyData = file_exists($unitArmyPath) ? json_decode(file_get_contents($unitArmyPath), true) : [];
        $campKey = (string)$targetType;
        $lvlKey  = (string)$targetLvl;

        $enemyCounts = $unitArmyData[$campKey][$lvlKey] ?? $unitArmyData['70'][$lvlKey] ?? [
            '1' => 150 * $targetLvl,
            '2' => 80 * $targetLvl,
            '3' => 100 * $targetLvl,
            '4' => 60 * $targetLvl,
            '5' => 0,
            '6' => 0
        ];

        $defenderSlots = [
            'f_1' => ['type' => 1, 'pre' => (int)($enemyCounts['1'] ?? $enemyCounts['army_a'] ?? 0), 'cur' => (int)($enemyCounts['1'] ?? $enemyCounts['army_a'] ?? 0)],
            'f_2' => ['type' => 2, 'pre' => (int)($enemyCounts['2'] ?? $enemyCounts['army_b'] ?? 0), 'cur' => (int)($enemyCounts['2'] ?? $enemyCounts['army_b'] ?? 0)],
            'f_3' => ['type' => 4, 'pre' => (int)($enemyCounts['4'] ?? $enemyCounts['army_d'] ?? 0), 'cur' => (int)($enemyCounts['4'] ?? $enemyCounts['army_d'] ?? 0)],
            'b_1' => ['type' => 3, 'pre' => (int)($enemyCounts['3'] ?? $enemyCounts['army_c'] ?? 0), 'cur' => (int)($enemyCounts['3'] ?? $enemyCounts['army_c'] ?? 0)],
            'b_2' => ['type' => 5, 'pre' => (int)($enemyCounts['5'] ?? $enemyCounts['army_e'] ?? 0), 'cur' => (int)($enemyCounts['5'] ?? $enemyCounts['army_e'] ?? 0)],
            'b_3' => ['type' => 6, 'pre' => (int)($enemyCounts['6'] ?? $enemyCounts['army_f'] ?? 0), 'cur' => (int)($enemyCounts['6'] ?? $enemyCounts['army_f'] ?? 0)],
        ];

        // Ensure enemy has at least some units
        $totalEnemyPre = array_sum(array_column($defenderSlots, 'pre'));
        if ($totalEnemyPre <= 0) {
            $defenderSlots['f_1'] = ['type' => 1, 'pre' => 500, 'cur' => 500];
            $defenderSlots['b_1'] = ['type' => 3, 'pre' => 300, 'cur' => 300];
        }

        // Enemy Commander Equipment Bonus Power
        $enemyEquipPower = 0;
        if ($targetType >= 97 && $targetType <= 105) {
            $enemyEquipPower = $targetLvl * 300; // Carthaginian Elite gear
        } elseif ($targetType >= 70 && $targetType <= 96) {
            $enemyEquipPower = $targetLvl * 150; // Legion / Roman military gear
        } elseif ($targetType >= 150 && $targetType <= 236) {
            $enemyEquipPower = $targetLvl * 600; // God & Emperor War gear
        }

        // Check if defending location is a city with wall fortifications (traps, arrow towers, rolling stones)
        $defCity = null;
        $cStmt = $db->prepare("SELECT id_city, id_player, wall_a, wall_b, wall_c FROM `city` WHERE `x` = ? AND `y` = ? LIMIT 1");
        $cStmt->execute([$xCoord, $yCoord]);
        $defCity = $cStmt->fetch(PDO::FETCH_ASSOC);

        $wallTraps  = (int)($defCity['wall_a'] ?? 0);
        $wallTowers = (int)($defCity['wall_b'] ?? 0);
        $wallStones = (int)($defCity['wall_c'] ?? 0);

        // 4. Hero Equipment Combat Bonuses (Primary & Secondary Gear)
        $equippedStmt = $db->prepare("SELECT `type`, `part`, `lvl` FROM `player_equip` WHERE `id_hero` = ? AND `on_hero` = 1");
        $equippedStmt->execute([$idHero]);
        $heroEquips = $equippedStmt->fetchAll(PDO::FETCH_ASSOC);

        $heroEquipBonus = [
            'attack'     => 0,
            'defence'    => 0,
            'vitality'   => 0,
            'damage'     => 0,
            'break'      => 0,
            'anti_break' => 0,
            'strike'     => 0,
            'immunity'   => 0
        ];

        foreach ($heroEquips as $eq) {
            $power = self::getEquipPowerData($eq['type'], $eq['part'], (int)$eq['lvl']);
            if ($power) {
                $heroEquipBonus['attack']     += (int)($power['attack'] ?? 0);
                $heroEquipBonus['defence']    += (int)($power['defence'] ?? 0);
                $heroEquipBonus['vitality']   += (int)($power['vitality'] ?? 0);
                $heroEquipBonus['damage']     += (int)($power['damage'] ?? 0);
                $heroEquipBonus['break']      += (int)($power['break'] ?? 0);
                $heroEquipBonus['anti_break'] += (int)($power['anti_break'] ?? 0);
                $heroEquipBonus['strike']     += (int)($power['strike'] ?? 0);
                $heroEquipBonus['immunity']   += (int)($power['immunity'] ?? 0);
            }
        }

        // Apply equipment bonuses and allocated hero attribute points (Bravery / Defense) with medal buffs
        $idHero = (int)($hero['id_hero'] ?? 0);
        $heroMedals = [];
        if ($idHero > 0) {
            $mStmt = $db->prepare("SELECT medal_den, medal_leo FROM hero_medal WHERE id_hero = ?");
            $mStmt->execute([$idHero]);
            $heroMedals = $mStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        }
        $now = time();

        $rawPointB = (int)($hero['point_b'] ?? 20) + (int)($hero['point_b_plus'] ?? 0);
        $rawPointC = (int)($hero['point_c'] ?? 20) + (int)($hero['point_c_plus'] ?? 0);

        if (!empty($heroMedals['medal_den']) && (int)$heroMedals['medal_den'] > $now) {
            $rawPointB += (int)ceil($rawPointB * 0.25);
        }
        if (!empty($heroMedals['medal_leo']) && (int)$heroMedals['medal_leo'] > $now) {
            $rawPointC += (int)ceil($rawPointC * 0.25);
        }

        $heroAtkMod = 1 + (($rawPointB + (int)($hero['lvl'] ?? 1) * 2 + $heroEquipBonus['attack']) / 100);
        $heroDefMod = 1 + (($rawPointC + (int)($hero['lvl'] ?? 1) * 2 + $heroEquipBonus['defence']) / 100);
        $equipDamageBonus = ($heroEquipBonus['damage'] * 12) + ($heroEquipBonus['break'] * 8) + ($heroEquipBonus['strike'] * 5);
        $equipHpBonus     = ($heroEquipBonus['vitality'] * 10) + ($heroEquipBonus['anti_break'] * 8) + ($heroEquipBonus['immunity'] * 6);

        $roundsLog = [];
        if (!empty($heroEquips)) {
            $roundsLog[] = "معدات البطل (" . count($heroEquips) . " قطع مجهزة) منحت الجيش تعزيزات حربية: هجوم +" . $heroEquipBonus['attack'] . "، دفاع +" . $heroEquipBonus['defence'] . "، حيوية +" . $heroEquipBonus['vitality'] . "، ضرر +" . $heroEquipBonus['damage'] . "!";
        }
        $maxRounds = 3;
        $actualRounds = 0;

        for ($r = 1; $r <= $maxRounds; $r++) {
            $actualRounds = $r;
            $atkRemaining = array_sum(array_column($attackerSlots, 'cur'));
            $defRemaining = array_sum(array_column($defenderSlots, 'cur'));

            if ($atkRemaining <= 0 || $defRemaining <= 0) break;

            // Wall Fortifications: Round 1 Ambush Traps (wall_a)
            if ($r === 1 && $wallTraps > 0) {
                $trapDamage = min($wallTraps * 50, (int)floor($atkRemaining * 25));
                $trapKills = 0;
                foreach ($attackerSlots as &$aSlot) {
                    if ($aSlot['cur'] > 0 && $trapDamage > 0) {
                        $uHp = self::$unitStats[$aSlot['type']]['hp'] ?? 60;
                        $tk = min($aSlot['cur'], (int)floor($trapDamage / $uHp));
                        if ($tk > 0) {
                            $aSlot['cur'] -= $tk;
                            $trapDamage -= $tk * $uHp;
                            $trapKills += $tk;
                        }
                    }
                }
                unset($aSlot);
                if ($trapKills > 0) {
                    $roundsLog[] = "كمائن السور باغتت طليعة المهاجمين وأوقعت $trapKills قتيل قبل بدء الاشتباك!";
                }
            }

            // Calculate Attacker Total Power (Troops with heroAtkMod + Equipment flat damage/break/strike bonus)
            $roundAtkPower = $equipDamageBonus;
            foreach ($attackerSlots as $slot) {
                if ($slot['cur'] > 0 && isset(self::$unitStats[$slot['type']])) {
                    $uAtk = self::$unitStats[$slot['type']]['atk'] ?? 8;
                    $roundAtkPower += $slot['cur'] * $uAtk * $heroAtkMod;
                }
            }

            // Calculate Defender Total Power (Troops + Wall Arrow Towers + Rolling Stones + Commander Equipment)
            $roundDefPower = $enemyEquipPower;
            foreach ($defenderSlots as $slot) {
                if ($slot['cur'] > 0 && isset(self::$unitStats[$slot['type']])) {
                    $uAtk = self::$unitStats[$slot['type']]['atk'] ?? 8;
                    $roundDefPower += $slot['cur'] * $uAtk;
                }
            }

            // Arrow towers (wall_b) continuous ranged barrage
            if ($wallTowers > 0) {
                $towerPower = $wallTowers * 18;
                $roundDefPower += $towerPower;
                $roundsLog[] = "أبراج السور الدفاعية ($wallTowers برج) أطلقت وابل سهام على المهاجمين بقوة $towerPower!";
            }

            // Rolling stones (wall_c) crushing assault
            if ($wallStones > 0) {
                $stonePower = $wallStones * 50;
                $roundDefPower += $stonePower;
                $roundsLog[] = "أحجار السور المتساقطة ($wallStones حجر) سحقت خطوط المهاجمين بقوة $stonePower!";
            }

            // Inflict Damage on Defender
            $damageToDef = $roundAtkPower;
            $defLosses = 0;
            foreach ($defenderSlots as &$dSlot) {
                if ($dSlot['cur'] > 0 && $damageToDef > 0) {
                    $uHp = self::$unitStats[$dSlot['type']]['hp'] ?? 60;
                    $canKill = min($dSlot['cur'], (int)floor($damageToDef / $uHp));
                    if ($canKill <= 0 && $damageToDef > 0) $canKill = 1; // chip damage
                    $dSlot['cur'] = max(0, $dSlot['cur'] - $canKill);
                    $damageToDef -= $canKill * $uHp;
                    $defLosses += $canKill;
                }
            }
            unset($dSlot);

            // Inflict Damage on Attacker (Dampened by Hero Defence Modifier and Equipment Armor/Vitality)
            $damageToAtk = max(0, ($roundDefPower / $heroDefMod) - ($equipHpBonus * 0.05));
            $atkLosses = 0;
            foreach ($attackerSlots as &$aSlot) {
                if ($aSlot['cur'] > 0 && $damageToAtk > 0) {
                    $uHp = self::$unitStats[$aSlot['type']]['hp'] ?? 60;
                    $canKill = min($aSlot['cur'], (int)floor($damageToAtk / $uHp));
                    $aSlot['cur'] = max(0, $aSlot['cur'] - $canKill);
                    $damageToAtk -= $canKill * $uHp;
                    $atkLosses += $canKill;
                }
            }
            unset($aSlot);

            $roundsLog[] = "الجولة $r: ألحقت قواتك $defLosses خسائر في صفوف الأعداء، بينما تكبدت كتائبك $atkLosses مقاتل.";
        }

        // Determine Winner
        $finalDef = array_sum(array_column($defenderSlots, 'cur'));
        $finalAtk = array_sum(array_column($attackerSlots, 'cur'));
        $sideWin = ($finalDef <= 0 || $finalAtk > $finalDef) ? 1 : 2;

        // 5. Calculate Loss Rate & Recovery Mechanics (Exact Elkaisar Rules)
        $recoveryRate = 0.0;
        if ($targetType >= 1 && $targetType <= 59) {
            $recoveryRate = 0.75; // Wilds: 75% auto recovery
        } elseif ($targetType >= 70 && $targetType <= 96) {
            $recoveryRate = 0.65; // Camps & Maneuvers: 65% auto recovery
        } elseif ($targetType >= 97 && $targetType <= 105) {
            $recoveryRate = 0.50; // Carthage & Gangs: 50% auto recovery
        } elseif ($targetType >= 60 && $targetType <= 63) {
            $recoveryRate = 0.00; // PvP: 0% auto recovery
        } else {
            $recoveryRate = 0.60; // Fortresses & Arenas: 60% auto recovery
        }

        // Medicine tech and cure buffs (10% base up to 60%)
        $mStmt = $db->prepare("SELECT medicine FROM player_edu WHERE id_player = ?");
        $mStmt->execute([$idPlayer]);
        $medicineLvl = (int)$mStmt->fetchColumn();
        $woundedRate = min(0.60, 0.10 + ($medicineLvl * 0.05));

        $woundedTroops = ['army_a' => 0, 'army_b' => 0, 'army_c' => 0, 'army_d' => 0, 'army_e' => 0, 'army_f' => 0];
        $solTypeToCol = [1 => 'army_a', 2 => 'army_b', 3 => 'army_c', 4 => 'army_d', 5 => 'army_e', 6 => 'army_f'];

        foreach ($attackerSlots as $k => &$aSlot) {
            $initial = (int)$aSlot['pre'];
            $cur = (int)$aSlot['cur'];
            $fallen = max(0, $initial - $cur);
            if ($fallen > 0) {
                $recovered = (int)floor($fallen * $recoveryRate);
                $permLoss = $fallen - $recovered;
                $aSlot['cur'] = max(0, $initial - $permLoss);

                $woundedCount = (int)floor($permLoss * $woundedRate);
                $uType = $aSlot['type'];
                if ($woundedCount > 0 && isset($solTypeToCol[$uType])) {
                    $woundedTroops[$solTypeToCol[$uType]] += $woundedCount;
                }
            }
        }
        unset($aSlot);

        // Update Database Hero Army with recovered counts
        $db->prepare("UPDATE `hero_army` SET 
            `f_1_num` = ?, `f_2_num` = ?, `f_3_num` = ?, 
            `b_1_num` = ?, `b_2_num` = ?, `b_3_num` = ? 
            WHERE `id_hero` = ?")
           ->execute([
               $attackerSlots['f_1']['cur'], $attackerSlots['f_2']['cur'], $attackerSlots['f_3']['cur'],
               $attackerSlots['b_1']['cur'], $attackerSlots['b_2']['cur'], $attackerSlots['b_3']['cur'],
               $idHero
           ]);

        // Add Wounded to City Hospital
        $totalWounded = array_sum($woundedTroops);
        $cStmt = $db->prepare("SELECT id_city FROM `city` WHERE `id_player` = ? LIMIT 1");
        $cStmt->execute([$idPlayer]);
        $cityId = (int)$cStmt->fetchColumn() ?: 1;

        if ($totalWounded > 0) {
            $db->prepare("INSERT INTO `city_wounded` (`id_city`, `army_a`, `army_b`, `army_c`, `army_d`, `army_e`, `army_f`)
                          VALUES (?, ?, ?, ?, ?, ?, ?)
                          ON DUPLICATE KEY UPDATE 
                          `army_a` = `army_a` + VALUES(`army_a`), `army_b` = `army_b` + VALUES(`army_b`), `army_c` = `army_c` + VALUES(`army_c`),
                          `army_d` = `army_d` + VALUES(`army_d`), `army_e` = `army_e` + VALUES(`army_e`), `army_f` = `army_f` + VALUES(`army_f`)")
               ->execute([$cityId, $woundedTroops['army_a'], $woundedTroops['army_b'], $woundedTroops['army_c'], $woundedTroops['army_d'], $woundedTroops['army_e'], $woundedTroops['army_f']]);
        }

        // 6. Calculate Spoils & Plunder
        $baseLoot = $targetLvl * 15000;
        $spoils = [
            'food'  => $baseLoot + rand(5000, 15000),
            'wood'  => (int)($baseLoot * 0.8) + rand(3000, 10000),
            'stone' => (int)($baseLoot * 0.6) + rand(2000, 8000),
            'metal' => (int)($baseLoot * 0.5) + rand(1000, 6000),
            'coin'  => (int)($baseLoot * 0.3) + rand(500, 3000)
        ];

        // Credit no more than the remaining balance headroom.
        $spoils = ResourceService::creditCityUpToLimit(
            $db,
            $cityId,
            $idPlayer,
            $spoils
        );

        // Drop items from the world-unit prize data file.
        $prizePathCandidates = [
            __DIR__ . '/../data/world-unit-prizes.json',
            dirname(__DIR__) . '/data/world-unit-prizes.json',
            dirname(__DIR__, 2) . '/app.elkaisar.com/data/world-unit-prizes.json'
        ];
        $prizePath = null;
        foreach ($prizePathCandidates as $c) {
            if (file_exists($c)) {
                $prizePath = $c;
                break;
            }
        }

        $droppedItems = [];
        if ($prizePath) {
            $pData = json_decode(file_get_contents($prizePath), true);
            if (!empty($pData['Win'])) {
                foreach ($pData['Win'] as $p) {
                    if ((int)$p['unitType'] === $targetType && (int)$p['lvl'] === $targetLvl) {
                        // win_rate in basis points out of 10,000 (e.g. 1500 = 15%, 375 = 3.75%, 10 = 0.1%)
                        if (rand(1, 10000) <= (int)$p['win_rate']) {
                            $amt = rand((int)$p['amount_min'], (int)$p['amount_max']);
                            if ($amt > 0) {
                                $db->prepare("INSERT INTO `player_item` (`id_player`, `id_item`, `amount`) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE `amount` = `amount` + VALUES(`amount`)")
                                   ->execute([$idPlayer, $p['prize'], $amt]);
                                $droppedItems[] = ['prize' => $p['prize'], 'amount' => $amt];
                            }
                        }
                    }
                }
            }
        }

        // Direct Hero Equipment Drop
        $possibleEquipTypes = [];
        $equipDropChance = 0;
        if ($targetType >= 97 && $targetType <= 105 && $targetLvl >= 3) {
            $equipDropChance = 25; // 25% on Carthage & Gangs
            $possibleEquipTypes = ['mili', 'peace', 'victory', 'mars'];
        } elseif ($targetType >= 70 && $targetType <= 96 && $targetLvl >= 3) {
            $equipDropChance = 18; // 18% on Camps & Maneuvers
            $possibleEquipTypes = ['loy', 'sparta', 'lion'];
        } elseif ($targetType >= 150 && $targetType <= 236) {
            $equipDropChance = 40; // 40% on Arenas & Fortresses
            $possibleEquipTypes = ['fearless', 'thunder', 'holy', 'neptune', 'thor'];
        }

        if (!empty($possibleEquipTypes) && rand(1, 100) <= $equipDropChance) {
            $parts = ['sword', 'shield', 'helmet', 'armor', 'boot', 'belt'];
            $eqType = $possibleEquipTypes[array_rand($possibleEquipTypes)];
            $eqPart = $parts[array_rand($parts)];
            $eqLvl = max(1, min(10, $targetLvl));
            $db->prepare("INSERT INTO `player_equip` (`id_player`, `id_hero`, `type`, `part`, `lvl`, `on_hero`) VALUES (?, 0, ?, ?, ?, 0)")
               ->execute([$idPlayer, $eqType, $eqPart, $eqLvl]);
            $droppedItems[] = ['prize' => "equip_{$eqType}_{$eqPart}", 'amount' => 1];
        }

        // 7. Provisional hero XP and honor/prestige rewards.
        // The available client code does not establish these combat reward coefficients.
        // Target categories:
        // - Carthage Gangs & Rebels (types 97-105)
        // - Maneuvers & Military Camps (types 70-96)
        // - Wilds (types 1-59)
        // - Capitals & Arenas (types 150-236)
        // - Cities / PvP (types 60-63)
        $point_b = ((int)($hero['point_b'] ?? 0)) + ((int)($hero['point_b_plus'] ?? 0));
        $wisdomMultiplier = 1.0 + ($point_b * 0.001);

        $slainDefPower = 0;
        foreach ($defenderSlots as $s) {
            $slainDefPower += max(0, $s['pre'] - $s['cur']) * 2;
        }

        if ($targetType >= 97 && $targetType <= 105) {
            // Carthage Gangs & Rebels (عصابات قرطاجية وقطاع طرق)
            $baseExp = $targetLvl * 4500 + rand(500, 2000) + (int)floor($slainDefPower * 0.25);
            $honorGained = $targetLvl * 150 + (int)floor($slainDefPower * 0.08) + rand(50, 150);
            $prestigeGain = max(50, $targetLvl * 25);
        } elseif ($targetType >= 70 && $targetType <= 96) {
            // Maneuvers & Military Camps (مناورات ومعسكرات)
            $baseExp = $targetLvl * 2800 + rand(300, 1000) + (int)floor($slainDefPower * 0.20);
            $honorGained = $targetLvl * 90 + (int)floor($slainDefPower * 0.05) + rand(20, 80);
            $prestigeGain = max(30, $targetLvl * 18);
        } elseif ($targetType >= 1 && $targetType <= 59) {
            // Wild Lands (البراري)
            $baseExp = $targetLvl * 750 + rand(100, 400) + (int)floor($slainDefPower * 0.12);
            $honorGained = $targetLvl * 40 + (int)floor($slainDefPower * 0.03) + rand(10, 40);
            $prestigeGain = max(10, $targetLvl * 10);
            // Bonus Domination Honor when colonizing / occupying wild (استيلاء)
            if ($task == 1) { // BATTEL_TASK_DOMINATE
                $honorGained += ($targetLvl * 50) + 50;
                $prestigeGain += ($targetLvl * 15);
            }
        } elseif ($targetType >= 150 && $targetType <= 236) {
            // Capitals, Arenas & Fortresses (قلاع وعواصم الجيوش وميادين الحرب)
            $baseExp = $targetLvl * 6000 + rand(1000, 3000) + (int)floor($slainDefPower * 0.30);
            $honorGained = $targetLvl * 220 + (int)floor($slainDefPower * 0.10) + rand(100, 250);
            $prestigeGain = max(100, $targetLvl * 35);
        } else {
            // PvP / City
            $baseExp = max(500, (int)floor($slainDefPower * 0.25));
            $honorGained = max(100, (int)floor($slainDefPower * 0.15));
            $prestigeGain = max(20, $targetLvl * 15);
        }

        $expGained = (int)floor($baseExp * $wisdomMultiplier);

        $db->prepare("UPDATE `city_hero` SET `exp` = `exp` + ?, `power` = LEAST(`power_max`, `power` + 5) WHERE `id_hero` = ?")
           ->execute([$expGained, $idHero]);
        $db->prepare("UPDATE `player` SET `honor` = `honor` + ?, `prestige` = `prestige` + ? WHERE `id_player` = ?")
           ->execute([$honorGained, $prestigeGain, $idPlayer]);

        $idGuild = (int)($player['id_guild'] ?? 0);
        if ($idGuild > 0) {
            $db->prepare("UPDATE `guild` SET `honor` = `honor` + ?, `prestige` = `prestige` + ? WHERE `id_guild` = ?")
               ->execute([$honorGained, $prestigeGain, $idGuild]);
        }

        // Domination (استيلاء على البرية)
        $dominated = false;
        if ($task == 1 && $targetType >= 1 && $targetType <= 59 && $sideWin == 1) {
            $pStmt = $db->prepare("SELECT lvl FROM city_building WHERE id_city = ? AND type = 10 LIMIT 1");
            $pStmt->execute([$cityId]);
            $palaceLvl = (int)$pStmt->fetchColumn() ?: 1;
            $maxWilds = max(1, $palaceLvl);

            $wCnt = (int)$db->query("SELECT COUNT(*) FROM city_barray WHERE id_city = $cityId")->fetchColumn();
            if ($wCnt < $maxWilds) {
                $db->prepare("INSERT INTO city_barray (id_city, id_player, x_coord, y_coord, Type, lvl) 
                              VALUES (?, ?, ?, ?, ?, ?) 
                              ON DUPLICATE KEY UPDATE id_city = VALUES(id_city), id_player = VALUES(id_player), Type = VALUES(Type), lvl = VALUES(lvl)")
                   ->execute([$cityId, $idPlayer, $xCoord, $yCoord, $targetType, $targetLvl]);
                $dominated = true;
                require_once __DIR__ . '/ProductionService.php';
                ProductionService::recalculateCityProduction($db, $cityId);
            }
        }

        // 8. Build Full Detailed Report Structure for report_player
        $reportDetail = [
            'encId'        => '0', // set after insert
            'general_data' => [
                'p_name'    => $player['name'],
                'task'      => $task,
                'lvl'       => $targetLvl,
                'round_num' => $actualRounds,
                'side_win'  => $sideWin
            ],
            'heros' => [
                [
                    'side'      => 1, // Attacker (Real Player Hero)
                    'h_name'    => $hero['name'] ?: 'البطل سيزر',
                    'avatar'    => (int)($hero['avatar'] ?? 1),
                    'id_player' => (int)$idPlayer,
                    'xp'        => $expGained,
                    'f_1_type'  => (string)$attackerSlots['f_1']['type'],
                    'f_1_pre'   => (string)$attackerSlots['f_1']['pre'],
                    'f_1_post'  => (string)$attackerSlots['f_1']['cur'],
                    'f_2_type'  => (string)$attackerSlots['f_2']['type'],
                    'f_2_pre'   => (string)$attackerSlots['f_2']['pre'],
                    'f_2_post'  => (string)$attackerSlots['f_2']['cur'],
                    'f_3_type'  => (string)$attackerSlots['f_3']['type'],
                    'f_3_pre'   => (string)$attackerSlots['f_3']['pre'],
                    'f_3_post'  => (string)$attackerSlots['f_3']['cur'],
                    'b_1_type'  => (string)$attackerSlots['b_1']['type'],
                    'b_1_pre'   => (string)$attackerSlots['b_1']['pre'],
                    'b_1_post'  => (string)$attackerSlots['b_1']['cur'],
                    'b_2_type'  => (string)$attackerSlots['b_2']['type'],
                    'b_2_pre'   => (string)$attackerSlots['b_2']['pre'],
                    'b_2_post'  => (string)$attackerSlots['b_2']['cur'],
                    'b_3_type'  => (string)$attackerSlots['b_3']['type'],
                    'b_3_pre'   => (string)$attackerSlots['b_3']['pre'],
                    'b_3_post'  => (string)$attackerSlots['b_3']['cur'],
                ],
                [
                    'side'      => 0, // Defender (Real Enemy Formation)
                    'h_name'    => "حامية الأعداء [مستوى $targetLvl]",
                    'avatar'    => 2,
                    'id_player' => 0,
                    'xp'        => 0,
                    'f_1_type'  => (string)$defenderSlots['f_1']['type'],
                    'f_1_pre'   => (string)$defenderSlots['f_1']['pre'],
                    'f_1_post'  => (string)$defenderSlots['f_1']['cur'],
                    'f_2_type'  => (string)$defenderSlots['f_2']['type'],
                    'f_2_pre'   => (string)$defenderSlots['f_2']['pre'],
                    'f_2_post'  => (string)$defenderSlots['f_2']['cur'],
                    'f_3_type'  => (string)$defenderSlots['f_3']['type'],
                    'f_3_pre'   => (string)$defenderSlots['f_3']['pre'],
                    'f_3_post'  => (string)$defenderSlots['f_3']['cur'],
                    'b_1_type'  => (string)$defenderSlots['b_1']['type'],
                    'b_1_pre'   => (string)$defenderSlots['b_1']['pre'],
                    'b_1_post'  => (string)$defenderSlots['b_1']['cur'],
                    'b_2_type'  => (string)$defenderSlots['b_2']['type'],
                    'b_2_pre'   => (string)$defenderSlots['b_2']['pre'],
                    'b_2_post'  => (string)$defenderSlots['b_2']['cur'],
                    'b_3_type'  => (string)$defenderSlots['b_3']['type'],
                    'b_3_pre'   => (string)$defenderSlots['b_3']['pre'],
                    'b_3_post'  => (string)$defenderSlots['b_3']['cur'],
                ]
            ],
            'prize' => [
                'resource' => $spoils,
                'matrial'  => !empty($droppedItems) ? $droppedItems : [
                    ['prize' => 'medal_den', 'amount' => 1]
                ],
                'honor'    => $honorGained
            ],
            'rounds'       => $roundsLog,
            'rounds_log'   => $roundsLog
        ];

        // 9. Save Battle Report to report_player
        $insStmt = $db->prepare("INSERT INTO `report_player` (`id_player`, `x`, `y`, `t`, `lvl`, `type`, `seen`, `detail`, `time_stamp`) 
                                 VALUES (?, ?, ?, ?, ?, ?, 0, ?, NOW())");
        $insStmt->execute([
            $idPlayer, $xCoord, $yCoord, $targetType, $targetLvl, 1,
            json_encode($reportDetail, JSON_UNESCAPED_UNICODE)
        ]);
        $reportId = (int)$db->lastInsertId();

        // Update encId with real ID
        $reportDetail['encId'] = (string)$reportId;
        $db->prepare("UPDATE `report_player` SET `detail` = ? WHERE `id_report` = ?")
           ->execute([json_encode($reportDetail, JSON_UNESCAPED_UNICODE), $reportId]);

        // 10. Also log to battel_log table
        $totalAtkPre = array_sum(array_column($attackerSlots, 'pre'));
        $totalAtkCur = array_sum(array_column($attackerSlots, 'cur'));
        $totalDefPre = array_sum(array_column($defenderSlots, 'pre'));
        $totalDefCur = array_sum(array_column($defenderSlots, 'cur'));
        $db->prepare("INSERT INTO `battel_log` (`id_world_battel`, `attacker_id`, `defender_id`, `winner`, `atk_losses`, `def_losses`, `loot_food`, `loot_wood`, `loot_stone`, `loot_metal`, `loot_coin`, `honor_gained`, `detail`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
           ->execute([
               $reportId, $idPlayer, 0, $sideWin,
               max(0, $totalAtkPre - $totalAtkCur), max(0, $totalDefPre - $totalDefCur),
               $spoils['food'], $spoils['wood'], $spoils['stone'], $spoils['metal'], $spoils['coin'],
               $honorGained, json_encode($reportDetail, JSON_UNESCAPED_UNICODE)
           ]);

        return [
            'reportId'     => $reportId,
            'sideWin'      => $sideWin,
            'losses'       => [
                'attacker' => max(0, $totalAtkPre - $totalAtkCur),
                'defender' => max(0, $totalDefPre - $totalDefCur),
                'atkLosses' => max(0, $totalAtkPre - $totalAtkCur),
                'defLosses' => max(0, $totalDefPre - $totalDefCur),
            ],
            'reportDetail' => $reportDetail,
            'reportData'   => $reportDetail
        ];
    }
}
