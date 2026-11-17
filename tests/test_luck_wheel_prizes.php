<?php

require_once __DIR__ . '/../caesar-api/PlayerAuth.php';
require_once __DIR__ . '/../caesar-api/controllers/PlayerController.php';

$first = PlayerController::generateLuckWheelPrizes();
$second = PlayerController::generateLuckWheelPrizes();
$prizeNames = array_column($first, 'Prize');
$totalWeight = array_sum(array_column($first, 'weight'));

if (count($first) !== 20 || $first !== $second || $totalWeight !== 10000) {
    throw new RuntimeException('Luck-wheel prizes must match the stable 20-entry weighted table');
}
if (!in_array('gold_10', $prizeNames, true)
    || !in_array('gold_25', $prizeNames, true)
    || in_array('gold_bar', $prizeNames, true)) {
    throw new RuntimeException('Luck-wheel prize entries must match the original reward table');
}

echo "Luck-wheel prize table tests passed\n";
