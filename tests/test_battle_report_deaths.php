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

// Multi-slot / shifted slot test matching battle 123 & 124
$multiSlotDetail = [
    'heros' => [
        [
            'side' => 1,
            'f_1_pre' => '10', 'f_1_post' => '0',
            'f_2_pre' => '10', 'f_2_post' => '0',
            'f_3_pre' => '10', 'f_3_post' => '0',
            'b_2_pre' => '167010', 'b_2_post' => '0'
        ]
    ],
    'hero_list' => [
        [
            'Hero' => ['side' => 1],
            'pre' => ['0' => 30, '1' => 167010, '2' => 0, '3' => 0, '4' => 0, '5' => 0],
            'post' => ['0' => 0, '1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0]
        ]
    ]
];
$normalized = BattleReportService::addReplayDeaths($multiSlotDetail);
if ($normalized['hero_list'][0]['dead']['0'] !== 30
    || $normalized['hero_list'][0]['dead']['1'] !== 167010) {
    throw new RuntimeException('Multi-slot replay losses did not align with actual hero block pre/post values');
}

echo "Battle report death tests passed\n";
