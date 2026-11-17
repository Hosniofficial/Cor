<?php

require_once __DIR__ . '/../caesar-api/services/HeroPowerService.php';

final class HeroPowerRecoveryTestStatement {
    public $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function execute($params) {
        $this->db->updates[] = $params;
    }
}

final class HeroPowerRecoveryTestDb {
    public $updates = [];

    public function prepare($sql) {
        if ($sql !== 'UPDATE city_hero SET power = ?, power_max = ?, power_ls = ? WHERE id_hero = ? AND id_player = ?') {
            throw new RuntimeException('Unexpected hero-power update query');
        }
        return new HeroPowerRecoveryTestStatement($this);
    }
}

function assertHeroPowerState($expectedPower, $expectedSync, $hero, $now, $expectUpdate) {
    $db = new HeroPowerRecoveryTestDb();
    $result = HeroPowerService::recover($db, $hero, $now);
    if ($result['power'] !== $expectedPower || $result['power_ls'] !== $expectedSync) {
        throw new RuntimeException('Hero power recovery returned an unexpected state');
    }
    $expectedMax = (int)($hero['power_max'] ?? HeroPowerService::getMaxPower((int)($hero['lvl'] ?? 1)));
    if ($result['power_max'] !== $expectedMax) {
        throw new RuntimeException('Hero power recovery should preserve the stored maximum');
    }
    if (count($db->updates) !== ($expectUpdate ? 1 : 0)) {
        throw new RuntimeException('Hero power recovery persisted an unexpected number of updates');
    }
}

$baseHero = ['id_hero' => 1, 'id_player' => 2, 'lvl' => 50, 'power' => 40, 'power_max' => 100, 'power_ls' => 1000];
assertHeroPowerState(40, 1000, $baseHero, 1119, false);
assertHeroPowerState(42, 1240, $baseHero, 1241, true);
assertHeroPowerState(100, 1500, array_merge($baseHero, ['power' => 99]), 1500, true);
assertHeroPowerState(40, 1500, array_merge($baseHero, ['power_ls' => 0]), 1500, true);
assertHeroPowerState(100, 1500, array_merge($baseHero, ['power' => 100]), 1500, true);

if (HeroPowerService::getRequiredPower(0, 40) !== 40
    || HeroPowerService::getRequiredPower(0, 0) !== 10
    || HeroPowerService::getRequiredPower(1, 40) !== 0) {
    throw new RuntimeException('Conquest should bypass the fitness threshold while normal missions retain it');
}
assertHeroPowerState(80, 1500, array_merge($baseHero, [
    'lvl' => 1,
    'power' => 80,
    'power_max' => 100,
    'power_ls' => 1500
]), 1500, false);

foreach ([1 => 52, 2 => 54, 10 => 70, 50 => 150, 100 => 250] as $level => $expectedMax) {
    if (HeroPowerService::getMaxPower($level) !== $expectedMax) {
        throw new RuntimeException("Hero level-up fitness cap should match the original formula at level {$level}");
    }
}

echo "Hero fitness recovery tests passed.\n";
