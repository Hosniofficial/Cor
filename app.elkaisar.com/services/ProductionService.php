<?php
// ProductionService.php - Recalculates city resource production, caps, and labor rates

class ProductionService {
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
        $storeLvls   = 0;
        $palaceLvl   = 1;

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
            } elseif ($type === 2) { // Store / Warehouse
                $storeLvls += $lvl;
            } elseif ($type === 12) { // Palace
                $palaceLvl = max($palaceLvl, $lvl);
            }
        }

        // Population
        $loy = (int)$city['loy'];
        $popCap = 1000 + ($cottageLvls * 250);
        $popMax = (int)round($popCap * ($loy / 100.0));
        $currentPop = max(500, min($popMax, (int)$city['pop']));

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

        // 3. Effective Production
        $foodRate  = (int)($jop['food_rate'] ?? 100);
        $woodRate  = (int)($jop['wood_rate'] ?? 100);
        $stoneRate = (int)($jop['stone_rate'] ?? 100);
        $metalRate = (int)($jop['metal_rate'] ?? 100);

        $foodEff  = (int)floor(((int)$jop['food'])  * ($foodRate  / 100.0));
        $woodEff  = (int)floor(((int)$jop['wood'])  * ($woodRate  / 100.0));
        $stoneEff = (int)floor(((int)$jop['stone']) * ($stoneRate / 100.0));
        $metalEff = (int)floor(((int)$jop['metal']) * ($metalRate / 100.0));

        // 10 resources per worker per hour
        $foodProd  = max(100, $foodEff  * 10);
        $woodProd  = max(100, $woodEff  * 10);
        $stoneProd = max(100, $stoneEff * 10);
        $metalProd = max(100, $metalEff * 10);

        // Education Bonuses (+10% per level)
        $eStmt = $db->prepare("SELECT * FROM player_edu WHERE id_player = ?");
        $eStmt->execute([$playerId]);
        $edu = $eStmt->fetch() ?: [];

        $farmingLvl = (int)($edu['farming'] ?? 0);
        $woodingLvl = (int)($edu['wooding'] ?? 0);
        $stoningLvl = (int)($edu['stoning'] ?? 0);
        $miningLvl  = (int)($edu['mining']  ?? 0);
        $acctLvl    = (int)($edu['accounting'] ?? 0);

        $foodProd  = (int)round($foodProd  * (1.0 + $farmingLvl * 0.1));
        $woodProd  = (int)round($woodProd  * (1.0 + $woodingLvl * 0.1));
        $stoneProd = (int)round($stoneProd * (1.0 + $stoningLvl * 0.1));
        $metalProd = (int)round($metalProd * (1.0 + $miningLvl  * 0.1));

        // Console hero bonus
        if (!empty($city['console'])) {
            $hStmt = $db->prepare("SELECT point_a, point_a_plus FROM city_hero WHERE id_hero = ?");
            $hStmt->execute([(int)$city['console']]);
            $hero = $hStmt->fetch();
            if ($hero) {
                $cEff = ((int)$hero['point_a'] + (int)$hero['point_a_plus']) / 200.0;
                $foodProd  = (int)round($foodProd  * (1.0 + $cEff));
                $woodProd  = (int)round($woodProd  * (1.0 + $cEff));
                $stoneProd = (int)round($stoneProd * (1.0 + $cEff));
                $metalProd = (int)round($metalProd * (1.0 + $cEff));
            }
        }

        // Wilds / Barray Bonus (+3% per level)
        $barStmt = $db->prepare("SELECT * FROM city_barray WHERE id_city = ?");
        $barStmt->execute([$cityId]);
        $barrays = $barStmt->fetchAll() ?: [];
        $foodBonus = 0.0; $woodBonus = 0.0; $stoneBonus = 0.0; $metalBonus = 0.0;
        foreach ($barrays as $bar) {
            $ut = (int)$bar['Type'];
            $lvl = max(1, (int)$bar['lvl']);
            if ($ut >= 1 && $ut <= 10) $foodBonus += 0.03 * $lvl;
            elseif ($ut >= 50 && $ut <= 59) $woodBonus += 0.03 * $lvl;
            elseif ($ut >= 40 && $ut <= 49) $stoneBonus += 0.03 * $lvl;
            elseif ($ut >= 30 && $ut <= 39) $metalBonus += 0.03 * $lvl;
        }
        $foodProd  = (int)round($foodProd  * (1.0 + $foodBonus));
        $woodProd  = (int)round($woodProd  * (1.0 + $woodBonus));
        $stoneProd = (int)round($stoneProd * (1.0 + $stoneBonus));
        $metalProd = (int)round($metalProd * (1.0 + $metalBonus));

        // Taxes / Coins
        $taxs = (int)$city['taxs'];
        $coinProd = max(100, (int)round($currentPop * ($taxs / 100.0) * 10 * (1.0 + $acctLvl * 0.1)));

        // Storage Caps: Base cap is generous and preserves any higher existing database caps (from gifts/injection)
        $foodCap  = max(100000000, max((int)($city['food_cap']  ?? 0), 1000000 + $storeLvls * 1000000));
        $woodCap  = max(100000000, max((int)($city['wood_cap']  ?? 0), 1000000 + $storeLvls * 1000000));
        $stoneCap = max(100000000, max((int)($city['stone_cap'] ?? 0), 1000000 + $storeLvls * 1000000));
        $metalCap = max(100000000, max((int)($city['metal_cap'] ?? 0), 1000000 + $storeLvls * 1000000));
        $coinCap  = max(500000000, max((int)($city['coin_cap']  ?? 0), 10000000 + $palaceLvl * 5000000));

        // Save back to city table
        $upC = $db->prepare("UPDATE `city` SET 
            `pop_cap` = ?, `pop_max` = ?, `pop` = ?,
            `food_in` = ?, `wood_in` = ?, `stone_in` = ?, `metal_in` = ?, `coin_in` = ?,
            `food_cap` = ?, `wood_cap` = ?, `stone_cap` = ?, `metal_cap` = ?, `coin_cap` = ?
            WHERE `id_city` = ?");
        $upC->execute([
            $popCap, $popMax, $currentPop,
            $foodProd, $woodProd, $stoneProd, $metalProd, $coinProd,
            $foodCap, $woodCap, $stoneCap, $metalCap, $coinCap,
            $cityId
        ]);
    }
}

// Global procedural wrapper for backwards compatibility
if (!function_exists('recalculateCityProduction')) {
    function recalculateCityProduction($db, $cityId) {
        ProductionService::recalculateCityProduction($db, $cityId);
    }
}
