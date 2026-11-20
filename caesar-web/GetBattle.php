<?php
// caesar-web/GetBattle.php - Serves Battle Replay JSON for Cocos2d engine
require_once __DIR__ . '/../caesar-api/db.php';
require_once __DIR__ . '/../caesar-api/services/BattleReportService.php';

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    exit;
}

$db = getDB();

$rawReplayId = $_GET['ReplayID'] ?? $_GET['report'] ?? $_GET['id_report'] ?? '';
$idReport = 0;

if (is_numeric($rawReplayId) && (int)$rawReplayId > 0) {
    $idReport = (int)$rawReplayId;
} elseif (preg_match('/report[=_](\d+)/i', $rawReplayId, $m)) {
    $idReport = (int)$m[1];
} elseif (preg_match('/(\d+)/', $rawReplayId, $m)) {
    $idReport = (int)$m[1];
}

$reportRow = null;
if ($idReport > 0) {
    $stmt = $db->prepare("SELECT * FROM `report_player` WHERE `id_report` = ? LIMIT 1");
    $stmt->execute([$idReport]);
    $reportRow = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$reportRow) {
    $stmt = $db->query("SELECT * FROM `report_player` ORDER BY `id_report` DESC LIMIT 1");
    $reportRow = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$reportRow) {
    http_response_code(404);
    echo json_encode(['state' => 'not_found', 'msg' => 'Battle report not found'], JSON_UNESCAPED_UNICODE);
    exit;
}

$detail = json_decode($reportRow['detail'] ?? '{}', true) ?: [];

// If report exists but has no round_list (e.g. from ws-server or legacy), synthesize it!
if (empty($detail['round_list']) && !empty($reportRow)) {
    $detail = synthesizeBattleReplay($reportRow, $detail);
    // Cache the synthesized detail back to DB
    $db->prepare("UPDATE `report_player` SET `detail` = ? WHERE `id_report` = ?")
       ->execute([json_encode($detail, JSON_UNESCAPED_UNICODE), $reportRow['id_report']]);
}

// Format final response
$detail = BattleReportService::addReplayDeaths($detail);
$detail['id_battle'] = (int)($reportRow['id_report'] ?? $detail['id_battle'] ?? 1);
$detail['id_battle_char'] = (string)($detail['encId'] ?? $detail['id_battle']);
$detail['id_server'] = (int)($_GET['server'] ?? 1);
$detail['time_stamp'] = strtotime($reportRow['time_stamp'] ?? 'now') ?: time();
$detail['seen'] = true;

echo json_encode(['BattleReplay' => $detail], JSON_UNESCAPED_UNICODE);
exit;

function synthesizeBattleReplay(array $reportRow, array $detail): array {
    $slotToBlock = ['f_1' => 0, 'f_2' => 1, 'f_3' => 2, 'b_1' => 3, 'b_2' => 4, 'b_3' => 5];
    $heros = $detail['heros'] ?? [];
    $atkHeroRaw = $heros[0] ?? [];
    $defHeroRaw = $heros[1] ?? [];
    $idPlayer = (int)($reportRow['id_player'] ?? $atkHeroRaw['id_player'] ?? 1);
    $defHeroId = 200000 + (int)($reportRow['t'] ?? 1) * 100 + (int)($reportRow['lvl'] ?? 1);
    $atkHeroId = (int)($atkHeroRaw['id_hero'] ?? 1);

    $atkSlots = [];
    $defSlots = [];
    $atkTypes = [];
    $atkPre = [];
    $atkPost = [];
    $defTypes = [];
    $defPre = [];
    $defPost = [];

    foreach ($slotToBlock as $slotKey => $blockIdx) {
        $atkType = (int)($atkHeroRaw[$slotKey . '_type'] ?? 0);
        $atkP = (int)($atkHeroRaw[$slotKey . '_pre'] ?? 0);
        $atkC = (int)($atkHeroRaw[$slotKey . '_post'] ?? 0);
        $atkD = max(0, (int)($atkHeroRaw[$slotKey . '_dead'] ?? ($atkP - $atkC)));
        $atkSlots[$slotKey] = [
            'type' => $atkType,
            'pre' => $atkP,
            'cur' => $atkC,
            'combatCur' => max(0, $atkP - $atkD)
        ];
        $atkTypes[(string)$blockIdx] = $atkType;
        $atkPre[(string)$blockIdx] = $atkP;
        $atkPost[(string)$blockIdx] = $atkC;

        $defType = (int)($defHeroRaw[$slotKey . '_type'] ?? 0);
        $defP = (int)($defHeroRaw[$slotKey . '_pre'] ?? 0);
        $defC = (int)($defHeroRaw[$slotKey . '_post'] ?? 0);
        $defD = max(0, (int)($defHeroRaw[$slotKey . '_dead'] ?? ($defP - $defC)));
        $defSlots[$slotKey] = [
            'type' => $defType,
            'pre' => $defP,
            'cur' => $defC,
            'combatCur' => max(0, $defP - $defD)
        ];
        $defTypes[(string)$blockIdx] = $defType;
        $defPre[(string)$blockIdx] = $defP;
        $defPost[(string)$blockIdx] = $defC;
    }

    $roundsCount = max(1, count($detail['rounds'] ?? [1]));
    $roundList = [];
    $curAtk = $atkSlots;
    $curDef = $defSlots;
    foreach ($curAtk as &$slot) {
        $slot['cur'] = $slot['pre'];
    }
    unset($slot);
    foreach ($curDef as &$slot) {
        $slot['cur'] = $slot['pre'];
    }
    unset($slot);

    for ($r = 1; $r <= $roundsCount; $r++) {
        $roundAtkBlocks = [];
        $roundDefBlocks = [];
        $roundAtkLosses = 0;
        $roundDefLosses = 0;

        foreach ($slotToBlock as $slotKey => $blockIdx) {
            // Attacker
            $totDeadAtk = max(0, $atkSlots[$slotKey]['pre'] - $atkSlots[$slotKey]['combatCur']);
            $deadThisRoundAtk = ($r === $roundsCount)
                ? max(0, $curAtk[$slotKey]['cur'] - $atkSlots[$slotKey]['combatCur'])
                : (int)round($totDeadAtk / $roundsCount);
            $deadThisRoundAtk = max(0, min($curAtk[$slotKey]['cur'], $deadThisRoundAtk));
            $roundAtkBlocks[(string)$blockIdx] = [
                'unit'     => (int)$curAtk[$slotKey]['cur'],
                'dead'     => (int)$deadThisRoundAtk,
                'armyType' => (int)$curAtk[$slotKey]['type']
            ];
            $curAtk[$slotKey]['cur'] -= $deadThisRoundAtk;
            $roundAtkLosses += $deadThisRoundAtk;

            // Defender
            $totDeadDef = max(0, $defSlots[$slotKey]['pre'] - $defSlots[$slotKey]['combatCur']);
            $deadThisRoundDef = ($r === $roundsCount)
                ? max(0, $curDef[$slotKey]['cur'] - $defSlots[$slotKey]['combatCur'])
                : (int)round($totDeadDef / $roundsCount);
            $deadThisRoundDef = max(0, min($curDef[$slotKey]['cur'], $deadThisRoundDef));
            $roundDefBlocks[(string)$blockIdx] = [
                'unit'     => (int)$curDef[$slotKey]['cur'],
                'dead'     => (int)$deadThisRoundDef,
                'armyType' => (int)$curDef[$slotKey]['type']
            ];
            $curDef[$slotKey]['cur'] -= $deadThisRoundDef;
            $roundDefLosses += $deadThisRoundDef;
        }

        $attCellIdx = 0;
        foreach ($slotToBlock as $slotKey => $blockIdx) {
            if ($roundAtkBlocks[(string)$blockIdx]['unit'] > 0) { $attCellIdx = $blockIdx; break; }
        }
        $defCellIdx = 0;
        foreach ($slotToBlock as $slotKey => $blockIdx) {
            if ($roundDefBlocks[(string)$blockIdx]['unit'] > 0) { $defCellIdx = $blockIdx; break; }
        }

        $attacks = [];
        if ($roundDefLosses > 0) {
            $attacks[] = [
                'HeroAttIndex' => 3,
                'CellAttIndex' => $attCellIdx,
                'HeroDefIndex' => 0,
                'CellDefIndex' => $defCellIdx,
                'AttackType'   => 0,
                'totalDead'    => (int)$roundDefLosses,
                'hitSkillType' => 1
            ];
        }
        if ($roundAtkLosses > 0) {
            $attacks[] = [
                'HeroAttIndex' => 0,
                'CellAttIndex' => $defCellIdx,
                'HeroDefIndex' => 3,
                'CellDefIndex' => $attCellIdx,
                'AttackType'   => 0,
                'totalDead'    => (int)$roundAtkLosses,
                'hitSkillType' => 1
            ];
        }

        $roundList[] = [
            'Heros' => [
                '0' => [
                    'idHero'     => $defHeroId,
                    'HeroIndex'  => 0,
                    'side'       => 0,
                    'ArmyBlocks' => $roundDefBlocks
                ],
                '3' => [
                    'idHero'     => $atkHeroId,
                    'HeroIndex'  => 3,
                    'side'       => 1,
                    'ArmyBlocks' => $roundAtkBlocks
                ]
            ],
            'Attacks' => $attacks
        ];
    }

    $detail['round_list'] = $roundList;
    $detail['hero_list'] = [
        [
            'Hero' => [
                'id_hero'   => $atkHeroId,
                'id_city'   => 0,
                'id_player' => $idPlayer,
                'name'      => $atkHeroRaw['h_name'] ?? 'البطل سيزر',
                'lvl'       => 1,
                'avatar'    => (int)($atkHeroRaw['avatar'] ?? 1),
                'side'      => 1
            ],
            'City' => [],
            'type' => $atkTypes,
            'pre'  => $atkPre,
            'post' => $atkPost
        ],
        [
            'Hero' => [
                'id_hero'   => $defHeroId,
                'id_city'   => 0,
                'id_player' => 0,
                'name'      => $defHeroRaw['h_name'] ?? 'حامية الأعداء',
                'lvl'       => (int)($reportRow['lvl'] ?? 1),
                'avatar'    => 2,
                'side'      => 0
            ],
            'City' => [],
            'type' => $defTypes,
            'pre'  => $defPre,
            'post' => $defPost
        ]
    ];
    $detail['player_list'] = [
        (string)$idPlayer => [
            'ItemPrize' => $detail['prize']['matrial'] ?? [],
            'Player' => [
                'id_player' => $idPlayer,
                'name'      => $detail['general_data']['p_name'] ?? 'اللاعب',
                'avatar'    => (int)($atkHeroRaw['avatar'] ?? 1)
            ],
            'ResourcePrize' => [
                'food'  => (int)($detail['prize']['resource']['food'] ?? 0),
                'wood'  => (int)($detail['prize']['resource']['wood'] ?? 0),
                'stone' => (int)($detail['prize']['resource']['stone'] ?? 0),
                'metal' => (int)($detail['prize']['resource']['metal'] ?? 0),
                'coin'  => (int)($detail['prize']['resource']['coin'] ?? 0)
            ]
        ],
        '0' => [
            'ItemPrize' => [],
            'Player' => [
                'id_player' => 0,
                'name'      => $defHeroRaw['h_name'] ?? 'حامية الأعداء',
                'avatar'    => 2
            ],
            'ResourcePrize' => [
                'food'  => 0, 'wood' => 0, 'stone' => 0, 'metal' => 0, 'coin' => 0
            ]
        ]
    ];
    $detail['battle_data'] = [
        'id_battle'     => (int)$reportRow['id_report'],
        'id_hero'       => $atkHeroId,
        'id_player'     => $idPlayer,
        'id_player_def' => 0,
        'time_start'    => strtotime($reportRow['time_stamp'] ?? 'now') ?: time(),
        'time_end'      => strtotime($reportRow['time_stamp'] ?? 'now') ?: time(),
        'x_coord'       => (int)($reportRow['x'] ?? 150),
        'y_coord'       => (int)($reportRow['y'] ?? 200),
        'x_city'        => (int)($reportRow['x'] ?? 150),
        'y_city'        => (int)($reportRow['y'] ?? 200),
        'task'          => (int)($detail['general_data']['task'] ?? 0),
        'done'          => 0,
        'attackNum'     => 1,
        'defenceNum'    => 0,
        'unit_lvl'      => (int)($reportRow['lvl'] ?? 1),
        'sideWin'       => ((int)($detail['general_data']['side_win'] ?? 0) === 1) ? 1 : 2,
        'rounds'        => $roundsCount
    ];

    return $detail;
}
