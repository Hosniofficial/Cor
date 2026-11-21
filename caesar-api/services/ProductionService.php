<?php
// ProductionService.php - Recalculates city resource production, caps, and labor rates
require_once __DIR__ . '/ServerConfigService.php';

class ProductionService {
    public static function getCityStorageData($db, int $cityId): array {
        $buildingStmt = $db->prepare(
            "SELECT `lvl` FROM `city_building` WHERE `id_city` = ? AND `type` = 2"
        );
        $buildingStmt->execute([$cityId]);
        $storeLevels = $buildingStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

        $upgradeCaps = [
            80000, 160000, 320000, 640000, 1280000, 2560000, 5120000, 10240000, 20480000, 40960000,
            49152000, 51200000, 53248000, 55296000, 57344000, 59392000, 61440000, 63488000, 65536000, 67584000,
            69632000, 71680000, 73728000, 75776000, 77824000, 79872000, 81920000, 83968000, 86016000, 88064000
        ];
        $totalCap = 0;
        foreach ($storeLevels as $storeLvl) {
            $storeLvl = (int)$storeLvl;
            if ($storeLvl > 0) {
                $totalCap += $upgradeCaps[min($storeLvl, count($upgradeCaps)) - 1];
            }
        }

        $storageStmt = $db->prepare(
            "SELECT `food_perc`, `wood_perc`, `stone_perc`, `metal_perc` FROM `city_storage` WHERE `id_city` = ?"
        );
        $storageStmt->execute([$cityId]);
        $storage = $storageStmt->fetch(PDO::FETCH_ASSOC) ?: [
            'food_perc' => 25,
            'wood_perc' => 25,
            'stone_perc' => 25,
            'metal_perc' => 25
        ];
        $resourceCaps = [
            'food_cap' => (int)floor($totalCap * (int)$storage['food_perc'] / 100),
            'wood_cap' => (int)floor($totalCap * (int)$storage['wood_perc'] / 100),
            'stone_cap' => (int)floor($totalCap * (int)$storage['stone_perc'] / 100),
            'metal_cap' => (int)floor($totalCap * (int)$storage['metal_perc'] / 100),
        ];

        return [
            'total_cap' => $totalCap,
            'food_storage_ratio' => (int)$storage['food_perc'],
            'wood_storage_ratio' => (int)$storage['wood_perc'],
            'stone_storage_ratio' => (int)$storage['stone_perc'],
            'metal_storage_ratio' => (int)$storage['metal_perc']
        ] + $resourceCaps;
    }

    public static function recalculateCityProduction($db, $cityId) {
        if ($cityId <= 0) return;

        $stmt = $db->prepare("SELECT * FROM city WHERE id_city = ?");
        $stmt->execute([$cityId]);
        $city = $stmt->fetch();
        if (!$city) return;
        $playerId = (int)$city['id_player'];

        // 1. Building Levels
        $bStmt = $db->prepare("SELECT place, type, lvl FROM city_building WHERE id_city = ?");
        $bStmt->execute([$cityId]);
        $buildings = $bStmt->fetchAll() ?: [];

        $jopTable = [
            10, 30, 60, 100, 150, 200,
            300, 500, 750, 1000, 1200, 1250,
            1300, 1350, 1400, 1450, 1500, 1550,
            1600, 1650, 1700, 1750, 1800, 1850,
            1900, 1950, 2000, 2050, 2100, 2150
        ];
        $getCap = function($lvl) use ($jopTable) {
            if ($lvl <= 0) return 0;
            if ($lvl <= 30) return $jopTable[$lvl - 1];
            return 2150 + ($lvl - 30) * 50;
        };

        $maxFarmCap  = 0;
        $maxWoodCap  = 0;
        $maxStoneCap = 0;
        $maxMineCap  = 0;
        $cottageLvls = 0;
        $palaceLvl   = 1;
        $wallLvl     = 0;

        foreach ($buildings as $b) {
            $type = (int)$b['type'];
            $lvl  = (int)$b['lvl'];
            if ($lvl <= 0) continue;

            if ($type === 16) { // Farm
                $maxFarmCap += $getCap($lvl);
            } elseif ($type === 15) { // Wood
                $maxWoodCap += $getCap($lvl);
            } elseif ($type === 18) { // Stone
                $maxStoneCap += $getCap($lvl);
            } elseif ($type === 17) { // Mine
                $maxMineCap += $getCap($lvl);
            } elseif ($type === 1) { // Cottage
                $cottageLvls += $lvl;
            } elseif ($type === 12) { // Palace
                $palaceLvl = max($palaceLvl, $lvl);
            } elseif ($type === 13 || $b['place'] === 'wall') { // Wall
                $wallLvl = max($wallLvl, $lvl);
            }
        }

        // Population
        $loy = (int)$city['loy'];
        $popCap = 1000 + ($cottageLvls * 250);
        $environment = strtolower((string)(getenv('CAESAR_ENV') ?: 'development'));
        $configuredDb = getenv('CAESAR_DB_NAME');
        $isTestDatabase = in_array($environment, ['development', 'test'], true)
            && ($configuredDb === false || $configuredDb === '' || $configuredDb === 'caesar_test');
        $storedPopCap = (int)($city['pop_cap'] ?? 0);
        // Test databases can opt in with a stored cap of at least one million; production always uses building capacity.
        if ($isTestDatabase && $storedPopCap >= 1000000) {
            $popCap = max($popCap, $storedPopCap);
        }
        $popMax = (int)round($popCap * ($loy / 100.0));
        $currentPop = max(0, min($popMax, (int)$city['pop']));
        $now = time();
        $populationLastSaved = (int)($city['pop_LSS'] ?? 0);

        if ($populationLastSaved <= 0 || $populationLastSaved > $now) {
            $populationLastSaved = $now;
        } else {
            $populationTicks = intdiv($now - $populationLastSaved, 360);
            for ($tick = 0; $tick < $populationTicks && $currentPop < $popMax; $tick++) {
                $growth = max(1, (int)round(($popMax - $currentPop) * 0.05));
                $currentPop = min($popMax, $currentPop + $growth);
            }
            $populationLastSaved += $populationTicks * 360;
        }

        // 2. City Jobs Record
        $jStmt = $db->prepare("SELECT * FROM city_jop WHERE id_city = ?");
        $jStmt->execute([$cityId]);
        $jop = $jStmt->fetch();

        if (!$jop) {
            $ins = $db->prepare("INSERT INTO city_jop (id_city, id_player, food, wood, stone, metal, food_rate, wood_rate, stone_rate, metal_rate) VALUES (?, ?, ?, ?, ?, ?, 100, 100, 100, 100)");
            $ins->execute([$cityId, $playerId, max(10, $maxFarmCap), max(10, $maxWoodCap), max(10, $maxStoneCap), max(10, $maxMineCap)]);
            $jStmt->execute([$cityId]);
            $jop = $jStmt->fetch();
        } else {
            $newFood  = max((int)$jop['food'],  $maxFarmCap);
            $newWood  = max((int)$jop['wood'],  $maxWoodCap);
            $newStone = max((int)$jop['stone'], $maxStoneCap);
            $newMetal = max((int)$jop['metal'], $maxMineCap);

            if ($newFood !== (int)$jop['food'] || $newWood !== (int)$jop['wood'] || $newStone !== (int)$jop['stone'] || $newMetal !== (int)$jop['metal']) {
                $upJ = $db->prepare("UPDATE city_jop SET food = ?, wood = ?, stone = ?, metal = ? WHERE id_city = ?");
                $upJ->execute([$newFood, $newWood, $newStone, $newMetal, $cityId]);
                $jop['food']  = $newFood;
                $jop['wood']  = $newWood;
                $jop['stone'] = $newStone;
                $jop['metal'] = $newMetal;
            }
        }

        // 3. Effective Production & Labor Allocation
        $foodRate  = (int)($jop['food_rate'] ?? 100);
        $woodRate  = (int)($jop['wood_rate'] ?? 100);
        $stoneRate = (int)($jop['stone_rate'] ?? 100);
        $metalRate = (int)($jop['metal_rate'] ?? 100);

        // Desired workers for each resource based on job capacity and employment rate percentage
        $foodDesired  = (int)floor(((int)$jop['food'])  * ($foodRate  / 100.0));
        $woodDesired  = (int)floor(((int)$jop['wood'])  * ($woodRate  / 100.0));
        $stoneDesired = (int)floor(((int)$jop['stone']) * ($stoneRate / 100.0));
        $metalDesired = (int)floor(((int)$jop['metal']) * ($metalRate / 100.0));

        // When population is plentiful, each industry employs its full desired workforce.
        // When population is below total demand, workers follow the original client allocation order:
        // (Food -> Wood -> Stone -> Metal) as defined in caesar-client.js Palace.getTotalProduction & Palace.productionReport
        $foodEff  = min($foodDesired, $currentPop);
        $remPop1  = max(0, $currentPop - $foodEff);
        $woodEff  = min($woodDesired, $remPop1);
        $remPop2  = max(0, $remPop1 - $woodEff);
        $stoneEff = min($stoneDesired, $remPop2);
        $remPop3  = max(0, $remPop2 - $stoneEff);
        $metalEff = min($metalDesired, $remPop3);

        // 10 resources per worker per hour base production
        $foodBase  = $foodEff  * 10;
        $woodBase  = $woodEff  * 10;
        $stoneBase = $stoneEff * 10;
        $metalBase = $metalEff * 10;

        // Education Bonuses (+10% per level)
        $eStmt = $db->prepare("SELECT * FROM player_edu WHERE id_player = ?");
        $eStmt->execute([$playerId]);
        $edu = $eStmt->fetch() ?: [];

        $farmingLvl = (int)($edu['farming'] ?? 0);
        $woodingLvl = (int)($edu['wooding'] ?? 0);
        $stoningLvl = (int)($edu['stoning'] ?? 0);
        $miningLvl  = (int)($edu['mining']  ?? 0);
        $acctLvl    = (int)($edu['accounting'] ?? 0);

        $foodEduBonus  = $farmingLvl * 0.1;
        $woodEduBonus  = $woodingLvl * 0.1;
        $stoneEduBonus = $stoningLvl * 0.1;
        $metalEduBonus = $miningLvl  * 0.1;

        // Console hero bonus: (point_a + point_a_plus) / 200
        $cEff = 0.0;
        if (!empty($city['console'])) {
            $hStmt = $db->prepare("SELECT point_a, point_a_plus FROM city_hero WHERE id_hero = ?");
            $hStmt->execute([(int)$city['console']]);
            $hero = $hStmt->fetch();
            if ($hero) {
                $cEff = ((int)$hero['point_a'] + (int)$hero['point_a_plus']) / 200.0;
                $mStmt = $db->prepare("SELECT medal_ceasro FROM hero_medal WHERE id_hero = ?");
                $mStmt->execute([(int)$city['console']]);
                $medalCeasro = (int)$mStmt->fetchColumn();
                if ($medalCeasro > time()) {
                    $cEff *= 1.25;
                }
            }
        }

        // Wilds / Barray Bonus (+3% per level of each colonized wild unit, stacking additively)
        // Authentic types matching worldUnitData.json & caesar-client.js WorldUnit:
        // River / Lake: Types 1..10 or 231 (Food)
        // Woods / Forest: Types 50..59 or 232 (Wood)
        // Desert / Sand / Quarry: Types 40..49 or 233 (Stone)
        // Mountain / Hills / Mine: Types 30..39 or 234 (Metal)
        $barStmt = $db->prepare("SELECT * FROM city_barray WHERE id_city = ?");
        $barStmt->execute([$cityId]);
        $barrays = $barStmt->fetchAll() ?: [];
        $foodWildBonus = 0.0; $woodWildBonus = 0.0; $stoneWildBonus = 0.0; $metalWildBonus = 0.0;
        foreach ($barrays as $bar) {
            $ut  = (int)$bar['Type'];
            $lvl = max(1, (int)$bar['lvl']);
            $ratePerLvl = 0.03; // Exact +3% per wilderness level
            if (($ut >= 1 && $ut <= 10) || $ut === 231) {
                $foodWildBonus += $ratePerLvl * $lvl;
            } elseif (($ut >= 50 && $ut <= 59) || $ut === 232) {
                $woodWildBonus += $ratePerLvl * $lvl;
            } elseif (($ut >= 40 && $ut <= 49) || $ut === 233) {
                $stoneWildBonus += $ratePerLvl * $lvl;
            } elseif (($ut >= 30 && $ut <= 39) || $ut === 234) {
                $metalWildBonus += $ratePerLvl * $lvl;
            }
        }

        // Statue / Monument & Morale Buffs from player_state (+25% each when active)
        $sStmt = $db->prepare("SELECT * FROM `player_state` WHERE `id_player` = ? LIMIT 1");
        $sStmt->execute([$playerId]);
        $state = $sStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $now = time();

        $foodStatueBuff  = (!empty($state['wheat']) && (int)$state['wheat'] > $now) ? 0.25 : 0.0;
        $woodStatueBuff  = (!empty($state['wood'])  && (int)$state['wood']  > $now) ? 0.25 : 0.0;
        $stoneStatueBuff = (!empty($state['stone']) && (int)$state['stone'] > $now) ? 0.25 : 0.0;
        $metalStatueBuff = (!empty($state['metal']) && (int)$state['metal'] > $now) ? 0.25 : 0.0;
        $coinStatueBuff  = (!empty($state['coin'])  && (int)$state['coin']  > $now) ? 0.25 : 0.0;

        // Morale / Motivation Speech Buff (+25% across all primary resource production)
        if (!empty($state['motiv']) && (int)$state['motiv'] > $now) {
            $foodStatueBuff  += 0.25;
            $woodStatueBuff  += 0.25;
            $stoneStatueBuff += 0.25;
            $metalStatueBuff += 0.25;
        }

        // Total Inflow Calculation: Base + Hero Support + Studies Support + Wilds Support + State Buffs
        // Exactly matching Palace.getTotalProduction & Palace.productionReport in caesar-client.js
        $foodProd  = $foodBase  + (int)floor($foodBase  * ($cEff + $foodEduBonus  + $foodWildBonus  + $foodStatueBuff));
        $woodProd  = $woodBase  + (int)floor($woodBase  * ($cEff + $woodEduBonus  + $woodWildBonus  + $woodStatueBuff));
        $stoneProd = $stoneBase + (int)floor($stoneBase * ($cEff + $stoneEduBonus + $stoneWildBonus + $stoneStatueBuff));
        $metalProd = $metalBase + (int)floor($metalBase * ($cEff + $metalEduBonus + $metalWildBonus + $metalStatueBuff));

        // Taxes / Coins
        $taxs = (int)$city['taxs'];
        $coinProd = max(100, (int)round($currentPop * ($taxs / 100.0) * 10 * (1.0 + $acctLvl * 0.1) * (1.0 + $coinStatueBuff)));
        $productionMultiplier = max(0.1, ServerConfigService::getFloat($db, 'resource_production_multiplier', 1.0));
        $foodProd = (int)floor($foodProd * $productionMultiplier);
        $woodProd = (int)floor($woodProd * $productionMultiplier);
        $stoneProd = (int)floor($stoneProd * $productionMultiplier);
        $metalProd = (int)floor($metalProd * $productionMultiplier);
        $coinProd = (int)floor($coinProd * $productionMultiplier);

        $storage = self::getCityStorageData($db, $cityId);
        $foodCap  = $storage['food_cap'];
        $woodCap  = $storage['wood_cap'];
        $stoneCap = $storage['stone_cap'];
        $metalCap = $storage['metal_cap'];
        $coinCap  = max(500000000, max((int)($city['coin_cap']  ?? 0), 10000000 + $palaceLvl * 5000000));
        $wallCap  = max(0, $wallLvl * 5000);

        // 4. Hourly Military Food Consumption (Army Upkeep)
        // Exact eating rates matching the original army base data.
        $cityArmyFoodOut =
            ((int)($city['army_a'] ?? 0) * 4) +
            ((int)($city['army_b'] ?? 0) * 18) +
            ((int)($city['army_c'] ?? 0) * 36) +
            ((int)($city['army_d'] ?? 0) * 5) +
            ((int)($city['army_e'] ?? 0) * 20) +
            ((int)($city['army_f'] ?? 0) * 150) +
            ((int)($city['spies']  ?? 0) * 6);

        // Stationed Heroes Upkeep & Hero Armies Food Consumption
        $heroTroopsFoodOut = 0;
        $heroSalaryCoinOut = 0;
        $unitEating = [
            1 => 4,   // مشاة
            2 => 18,  // فرسان
            3 => 5,   // رماة
            4 => 36,  // مدرعين
            5 => 20,  // مقاليع
            6 => 150  // مجانيق
        ];

        $hStmt = $db->prepare("SELECT h.id_hero, h.lvl, a.*
                               FROM city_hero h
                               LEFT JOIN hero_army a ON h.id_hero = a.id_hero
                               WHERE h.id_city = ? AND (h.in_city = 1 OR h.in_city IS NULL)");
        $hStmt->execute([$cityId]);
        $cityHeroes = $hStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($cityHeroes as $h) {
            $hLvl = max(1, (int)($h['lvl'] ?? 1));
            $heroSalaryCoinOut += $hLvl * 20;

            $slots = ['f_1', 'f_2', 'f_3', 'b_1', 'b_2', 'b_3'];
            foreach ($slots as $slot) {
                $st = (int)($h[$slot . '_type'] ?? 0);
                $sn = (int)($h[$slot . '_num']  ?? 0);
                if ($sn > 0 && isset($unitEating[$st])) {
                    $heroTroopsFoodOut += $sn * $unitEating[$st];
                }
            }
        }

        $totalFoodOut = $cityArmyFoodOut + $heroTroopsFoodOut;
        $totalCoinOut = $heroSalaryCoinOut;

        // Save back to city table
        $upC = $db->prepare("UPDATE `city` SET
            `pop_cap` = ?, `pop_max` = ?, `pop` = ?, `pop_LSS` = ?,
            `food_in` = ?, `wood_in` = ?, `stone_in` = ?, `metal_in` = ?, `coin_in` = ?,
            `food_out` = ?, `coin_out` = ?,
            `wall_cap` = ?,
            `food_cap` = ?, `wood_cap` = ?, `stone_cap` = ?, `metal_cap` = ?, `coin_cap` = ?
            WHERE `id_city` = ?");
        $upC->execute([
            $popCap, $popMax, $currentPop, $populationLastSaved,
            $foodProd, $woodProd, $stoneProd, $metalProd, $coinProd,
            $totalFoodOut, $totalCoinOut,
            $wallCap,
            $foodCap, $woodCap, $stoneCap, $metalCap, $coinCap,
            $cityId
        ]);
    }

    public static function recalculateCityProductionForPlayer($db, int $cityId, int $playerId): bool {
        $ownershipStmt = $db->prepare(
            "SELECT `id_city` FROM `city` WHERE `id_city` = ? AND `id_player` = ? FOR UPDATE"
        );
        $ownershipStmt->execute([$cityId, $playerId]);
        if (!$ownershipStmt->fetchColumn()) {
            return false;
        }

        self::recalculateCityProduction($db, $cityId);
        return true;
    }

    public static function processStarvationAndDesertion($db, &$city) {
        $cityId = (int)$city['id_city'];
        $idPlayer = (int)$city['id_player'];
        $cityName = $city['name'] ?? 'المدينة';

        // 1. Food Starvation & Desertion
        if ((int)$city['food'] <= 0 && ((int)$city['food_in'] - (int)$city['food_out']) < 0) {
            $unitNames = [
                'army_a' => 'مشاة خفيف',
                'army_b' => 'فرسان',
                'army_c' => 'مدرعون',
                'army_d' => 'رماة سهام',
                'army_e' => 'مقاليع',
                'army_f' => 'مجانيق'
            ];
            $heroArmyTypes = [
                1 => 'army_a',
                2 => 'army_b',
                3 => 'army_c',
                4 => 'army_d',
                5 => 'army_e',
                6 => 'army_f'
            ];

            $desertedCityTroops = [];
            $totalDeserted = 0;

            foreach ($unitNames as $col => $name) {
                $curCount = (int)($city[$col] ?? 0);
                if ($curCount > 0) {
                    $loss = max(1, (int)ceil($curCount * 0.03));
                    $desertedCityTroops[] = [
                        'type' => $col,
                        'name' => $name,
                        'amount' => $loss
                    ];
                    $totalDeserted += $loss;
                    $city[$col] = max(0, $curCount - $loss);
                }
            }

            // Also check hero army in this city
            $hStmt = $db->prepare("SELECT h.id_hero, h.name as hname, a.*
                                   FROM city_hero h
                                   JOIN hero_army a ON h.id_hero = a.id_hero
                                   WHERE h.id_city = ? AND (h.in_city = 1 OR h.in_city IS NULL)");
            $hStmt->execute([$cityId]);
            $heroes = $hStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $slots = ['f_1', 'f_2', 'f_3', 'b_1', 'b_2', 'b_3'];
            foreach ($heroes as $h) {
                $hId = (int)$h['id_hero'];
                $hUpdated = false;
                $upCols = [];
                $upVals = [];

                foreach ($slots as $slot) {
                    $sNum = (int)($h[$slot . '_num'] ?? 0);
                    if ($sNum > 0) {
                        $hLoss = max(1, (int)ceil($sNum * 0.03));
                        $totalDeserted += $hLoss;
                        $heroArmyType = $heroArmyTypes[(int)($h[$slot . '_type'] ?? 0)] ?? null;
                        $desertedCityTroops[] = [
                            'type' => $heroArmyType,
                            'name' => $heroArmyType ? $unitNames[$heroArmyType] : 'قوات البطل',
                            'heroName' => (string)$h['hname'],
                            'amount' => $hLoss
                        ];
                        $upCols[] = "{$slot}_num = ?";
                        $upVals[] = max(0, $sNum - $hLoss);
                        $hUpdated = true;
                    }
                }

                if ($hUpdated && !empty($upCols)) {
                    $upVals[] = $hId;
                    $db->prepare("UPDATE hero_army SET " . implode(', ', $upCols) . " WHERE id_hero = ?")->execute($upVals);
                }
            }

            if ($totalDeserted > 0) {
                // Update city army columns
                $db->prepare("UPDATE `city` SET `army_a` = ?, `army_b` = ?, `army_c` = ?, `army_d` = ?, `army_e` = ?, `army_f` = ? WHERE `id_city` = ?")
                   ->execute([$city['army_a'], $city['army_b'], $city['army_c'], $city['army_d'], $city['army_e'], $city['army_f'], $cityId]);

                // Recalculate production with lower food consumption
                self::recalculateCityProduction($db, $cityId);

                // Throttle check: at most once per 15 minutes in msg_diff
                $recentCheck = $db->prepare("SELECT COUNT(*) FROM `msg_diff` WHERE `id_player` = ? AND `subject` LIKE '%نقص الغذاء%' AND `time_stamp` > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
                $recentCheck->execute([$idPlayer]);
                if ((int)$recentCheck->fetchColumn() === 0) {
                    $subject = "هروب القوات بسبب نقص الغذاء في مدينة [{$cityName}]";
                    $body = json_encode([
                        'format' => 'system_event_v1',
                        'type' => 'desertion',
                        'cityName' => $cityName,
                        'total' => $totalDeserted,
                        'units' => $desertedCityTroops
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
                    $db->prepare("INSERT INTO `msg_diff` (`id_player`, `subject`, `body`, `seen`, `time_stamp`) VALUES (?, ?, ?, 0, NOW())")
                       ->execute([$idPlayer, $subject, $body]);
                }
            }
        }

        // 2. Coin Starvation & Hero Loyalty Drop
        if ((int)$city['coin'] <= 0 && ((int)$city['coin_in'] - (int)$city['coin_out']) < 0) {
            $loyaltyStmt = $db->prepare(
                "SELECT `id_hero`, `name`, `avatar`, `loyal`
                 FROM `city_hero`
                 WHERE `id_city` = ? AND (`in_city` = 1 OR `in_city` IS NULL)"
            );
            $loyaltyStmt->execute([$cityId]);
            $affectedHeroes = $loyaltyStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $db->prepare("UPDATE city_hero SET loyal = GREATEST(0, loyal - 2) WHERE id_city = ? AND (in_city = 1 OR in_city IS NULL)")->execute([$cityId]);

            $heroLoyalty = [];
            $desertedHeroes = [];
            foreach ($affectedHeroes as $hero) {
                $loyalty = max(0, (int)$hero['loyal'] - 2);
                $heroLoyalty[] = [
                    'idHero' => (int)$hero['id_hero'],
                    'name' => (string)$hero['name'],
                    'avatar' => (int)$hero['avatar'],
                    'loyalty' => $loyalty,
                    'deserted' => $loyalty === 0
                ];
                if ($loyalty === 0) {
                    $desertedHeroes[] = $hero;
                }
            }

            foreach ($desertedHeroes as $zh) {
                $db->prepare("UPDATE city_hero SET in_city = 0 WHERE id_hero = ?")->execute([(int)$zh['id_hero']]);
            }

            // Recalculate coin out
            self::recalculateCityProduction($db, $cityId);

            // Throttle check
            $recentCoinCheck = $db->prepare("SELECT COUNT(*) FROM `msg_diff` WHERE `id_player` = ? AND `subject` LIKE '%ولاء%' AND `time_stamp` > DATE_SUB(NOW(), INTERVAL 15 MINUTE)");
            $recentCoinCheck->execute([$idPlayer]);
            if ((int)$recentCoinCheck->fetchColumn() === 0) {
                $subject = "انخفاض معنويات وولاء الأبطال في مدينة [{$cityName}]";
                $body = json_encode([
                    'format' => 'system_event_v1',
                    'type' => 'hero_loyalty',
                    'cityName' => $cityName,
                    'heroes' => $heroLoyalty
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
                $db->prepare("INSERT INTO `msg_diff` (`id_player`, `subject`, `body`, `seen`, `time_stamp`) VALUES (?, ?, ?, 0, NOW())")
                   ->execute([$idPlayer, $subject, $body]);
            }
        }
    }
}

// Global procedural wrapper for backwards compatibility
if (!function_exists('recalculateCityProduction')) {
    function recalculateCityProduction($db, $cityId) {
        ProductionService::recalculateCityProduction($db, $cityId);
    }
}
