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
    WorldController::getReturnMarchDuration(0, 0, 3, 0, $army, 200, $config) === 120,
    'Statue targets (200-205) must receive the fixed-duration return cap'
);
expectReturnDuration(
    WorldController::getReturnMarchDuration(0, 0, 3, 0, $army, 180, $config) === 180,
    'Kingdoms (180-182) must retain distance-based return times without a maximum'
);
expectReturnDuration(
    WorldController::getReturnMarchDuration(0, 0, 3, 0, $army, 184, $config) === 180,
    'Repel Castles (184-186) must retain distance-based return times without a maximum'
);
expectReturnDuration(
    WorldController::getGuildDefenseJoinMarchDuration(0, 0, 1, 0, $army, $config) === 60,
    'Guild defenders must use actual travel time, not the target rally duration'
);
expectReturnDuration(
    WorldController::getJoinMarchDuration(0, 0, 1, 0, $army, 71, false, $config) === 120,
    'Camps must retain a 120-second minimum march duration'
);
expectReturnDuration(
    WorldController::getJoinMarchDuration(0, 0, 3, 0, $army, 80, false, $config) === 180,
    'Distant camps must use the longer distance-based duration'
);
expectReturnDuration(
    WorldController::canJoinGuildDefense(184, 9, 10, 9),
    'A member of the controlling guild may join defence against a different guild'
);
expectReturnDuration(
    !WorldController::canJoinGuildDefense(184, 9, 9, 9),
    'The controlling guild must not join defence against its own attack'
);
expectReturnDuration(
    !WorldController::canJoinGuildDefense(184, 9, 10, 11),
    'A member of a non-controlling guild must not join the defence'
);
expectReturnDuration(
    !WorldController::canJoinGuildDefense(60, 9, 10, 9),
    'Guild castle defence must not authorize ordinary player-city battles'
);

echo "World return duration tests passed\n";
