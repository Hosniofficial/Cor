<?php

require_once __DIR__ . '/../caesar-api/services/BattleReportService.php';

$legacyDetail = [
    'heros' => [
        ['side' => 1, 'f_1_pre' => '134', 'f_1_post' => '126'],
        ['side' => 0, 'f_1_pre' => '120', 'f_1_post' => '0'],
    ],
    'hero_list' => [
        ['Hero' => ['side' => 1], 'pre' => ['0' => 134], 'post' => ['0' => 126]],
        ['Hero' => ['side' => 0], 'pre' => ['0' => 120], 'post' => ['0' => 0]],
    ],
    'round_list' => [
        ['Heros' => [
            '3' => ['ArmyBlocks' => ['0' => ['dead' => 6]]],
            '0' => ['ArmyBlocks' => ['0' => ['dead' => 0]]],
        ]],
    ],
];
$normalized = BattleReportService::addReplayDeaths($legacyDetail);
if ($normalized['hero_list'][0]['dead']['0'] !== 8
    || $normalized['hero_list'][1]['dead']['0'] !== 120) {
    throw new RuntimeException('Legacy replay losses did not match the message report counts');
}

$currentDetail = $legacyDetail;
$currentDetail['heros'][0]['f_1_dead'] = '7';
$normalized = BattleReportService::addReplayDeaths($currentDetail);
if ($normalized['hero_list'][0]['dead']['0'] !== 7) {
    throw new RuntimeException('Explicit combat deaths were not preferred');
}

$roundOnlyDetail = [
    'hero_list' => [
        ['Hero' => ['side' => 1], 'pre' => [], 'post' => []],
    ],
    'round_list' => [
        ['Heros' => ['3' => ['ArmyBlocks' => ['0' => ['dead' => 4]]]]],
        ['Heros' => ['3' => ['ArmyBlocks' => ['0' => ['dead' => 5]]]]],
    ],
];
$normalized = BattleReportService::addReplayDeaths($roundOnlyDetail);
if ($normalized['hero_list'][0]['dead']['0'] !== 9) {
    throw new RuntimeException('Replay round deaths were not used when report troop totals were absent');
}

echo "Battle report death tests passed\n";
