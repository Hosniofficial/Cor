<?php

require_once __DIR__ . '/../caesar-api/services/BattleService.php';

$power = BattleService::getEquipPowerData('blue', 'sword', 1);
if ($power === null || (int)($power['attack'] ?? 0) !== 20 || (int)($power['damage'] ?? 0) !== 60) {
    throw new RuntimeException('BattleService did not load the expected equipment power data');
}

if (BattleService::getEquipPowerData('missing', 'sword', 1) !== null) {
    throw new RuntimeException('Unknown equipment unexpectedly returned power data');
}

echo "Battle equipment power test passed\n";
