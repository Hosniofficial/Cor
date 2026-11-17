<?php

require_once __DIR__ . '/../caesar-api/controllers/WorldController.php';

function expectReturnDuration(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$army = ['f_1_type' => 6, 'f_1_num' => 1];
$config = ['camp_rally_duration_sec' => 120];

expectReturnDuration(
    WorldController::getReturnMarchDuration(0, 0, 1, 0, $army, 101, $config) === 60,
    'Close fixed-duration targets must return according to actual distance'
);
expectReturnDuration(
    WorldController::getReturnMarchDuration(0, 0, 3, 0, $army, 101, $config) === 120,
    'Fixed-duration targets must cap long return trips at the configured maximum'
);
expectReturnDuration(
    WorldController::getReturnMarchDuration(0, 0, 3, 0, $army, 177, $config) === 180,
    'Target 177 must not receive the fixed-duration return cap'
);
expectReturnDuration(
    WorldController::getReturnMarchDuration(0, 0, 3, 0, $army, 200, $config) === 180,
    'Other target types must retain distance-based return times without a maximum'
);

echo "World return duration tests passed\n";
