<?php
require_once __DIR__ . '/../caesar-api/services/BattleAnalyzerService.php';

$db = new PDO('sqlite::memory:');
$db->exec('CREATE TABLE report_player (
    id_report INTEGER PRIMARY KEY,
    id_player INTEGER,
    x INTEGER,
    y INTEGER,
    type INTEGER,
    lvl INTEGER,
    time_stamp TEXT,
    detail TEXT NOT NULL
)');
$db->exec('CREATE TABLE player (
    id_player INTEGER PRIMARY KEY,
    name TEXT,
    user_group INTEGER,
    prestige INTEGER,
    avatar INTEGER
)');
$db->exec("INSERT INTO player VALUES (2, 'Player', 0, 0, 1)");

$solStats = [
    ['att' => 0, 'hp' => 0],
    ['att' => 8, 'hp' => 60],
    ['att' => 30, 'hp' => 250],
    ['att' => 25, 'hp' => 400],
    ['att' => 9, 'hp' => 45],
    ['att' => 19, 'hp' => 100],
    ['att' => 40, 'hp' => 600],
    ['att' => 10, 'hp' => 80]
];
$member = static function (int $id, int $side, int $unitType): array {
    return [
        'id_member' => $id,
        'side' => $side,
        'hero' => [
            'id_hero' => $id,
            'lvl' => 10,
            'point_b' => 3,
            'point_b_plus' => 1,
            'point_c' => 2,
            'point_c_plus' => 0,
            'medalDenActive' => false,
            'medalLeoActive' => false
        ],
        'army' => [
            'f_1_type' => $unitType,
            'f_1_num' => 10,
            'f_2_type' => 0,
            'f_2_num' => 0,
            'f_3_type' => 0,
            'f_3_num' => 0,
            'b_1_type' => 0,
            'b_1_num' => 0,
            'b_2_type' => 0,
            'b_2_num' => 0,
            'b_3_type' => 0,
            'b_3_num' => 0
        ],
        'equipmentBonuses' => []
    ];
};
$detail = [
    'general_data' => ['side_win' => 1],
    'heros' => [
        [
            'id_hero' => 22,
            'id_player' => 2,
            'h_name' => 'Defender',
            'side' => 0,
            'f_1_type' => 1,
            'f_1_pre' => 10,
            'f_1_post' => 8,
            'f_1_loss' => 2
        ],
        [
            'id_hero' => 11,
            'id_player' => 3,
            'h_name' => 'Attacker',
            'side' => 1,
            'f_1_type' => 4,
            'f_1_pre' => 10,
            'f_1_post' => 0,
            'f_1_loss' => 10
        ]
    ],
    'rounds_log' => ['الجولة 1: خسائر المدافعين 10، وخسائر المهاجمين 2.'],
    'combat_snapshot' => [
        'version' => 1,
        'reportHeroId' => 22,
        'attackers' => [$member(11, 1, 4)],
        'defendingMembers' => [[
            ...$member(22, 0, 1),
            'equipmentBonuses' => [[
                'part' => 'shield',
                'attack' => 0,
                'defence' => 5,
                'vitality' => 7,
                'damage' => 0,
                'sp_attr' => 3
            ]]
        ]],
        'npcDefenderTroops' => [2 => 2],
        'solStats' => $solStats,
        'commanderAttackBonus' => 0,
        'npcCombatPowerMultiplier' => 1,
        'wallFortifications' => ['traps' => 0, 'towers' => 0, 'stones' => 0]
    ]
];
$insert = $db->prepare('INSERT INTO report_player (id_report, id_player, x, y, type, lvl, time_stamp, detail)
    VALUES (?, 2, 1, 1, 44, 1, CURRENT_TIMESTAMP, ?)');
$insert->execute([1, json_encode($detail)]);
$insert->execute([2, json_encode([
    'general_data' => ['side_win' => 1],
    'heros' => [['id_hero' => 22, 'side' => 0]]
])]);

$analysis = BattleAnalyzerService::analyzeReport($db, 1);
if (
    $analysis['won'] !== true
    || $analysis['attacker_won'] !== true
    || $analysis['player_side'] !== 0
    || $analysis['player_won'] !== false
    || $analysis['player_army']['total_pre'] !== 10
    || $analysis['attacker_hero']['name'] !== 'Attacker'
    || !$analysis['simulation_available']
    || !$analysis['equipment']['has_shield']
) {
    throw new RuntimeException('Report analysis did not preserve the report owner side and historical equipment.');
}
$legacyAnalysis = BattleAnalyzerService::analyzeReport($db, 2);
if ($legacyAnalysis['simulation_available'] || $legacyAnalysis['equipment']['data_available']) {
    throw new RuntimeException('Legacy reports without snapshots must be marked as incomplete.');
}

$result = BattleAnalyzerService::simulateSandbox($db, 1, [
    'enable_barrage' => true,
    'enable_shield' => false
]);
if ($result['player_side'] !== 0 || !is_array($result['remaining_army'])) {
    throw new RuntimeException('Simulation did not return the report owner defender army.');
}
if (!array_filter($result['rounds_log'], static function ($line) {
    return strpos($line, 'وابل السهام') !== false;
})) {
    throw new RuntimeException('The requested sandbox feature was not applied to the snapshot.');
}
if (array_filter($result['rounds_log'], static function ($line) {
    return strpos($line, 'الدرع') !== false;
})) {
    throw new RuntimeException('The disabled shield feature was not removed from the snapshot.');
}

try {
    BattleAnalyzerService::simulateSandbox($db, 2, []);
    throw new RuntimeException('Legacy reports without snapshots must not be simulated.');
} catch (RuntimeException $error) {
    if (strpos($error->getMessage(), 'لم تُحفظ لقطة بيانات القتال التاريخية') === false) {
        throw $error;
    }
}

echo "Battle analyzer snapshot tests passed.\n";
