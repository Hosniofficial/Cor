<?php
// caesar-api/services/BattleAnalyzerService.php
// Tactical Battle Combat Inspector & Deep Analytics Engine

class BattleAnalyzerService {
    // Unit metadata: Arabic names and base stats
    public static $UNIT_NAMES = [
        1 => 'مشاة',
        2 => 'فرسان',
        3 => 'مدرعين',
        4 => 'رماة',
        5 => 'مقاليع',
        6 => 'منجنيق',
        7 => 'جواسيس'
    ];

    public static $UNIT_STATS = [
        1 => ['att' => 8,  'hp' => 60],
        2 => ['att' => 30, 'hp' => 250],
        3 => ['att' => 25, 'hp' => 400],
        4 => ['att' => 9,  'hp' => 45],
        5 => ['att' => 19, 'hp' => 100],
        6 => ['att' => 40, 'hp' => 600],
        7 => ['att' => 10, 'hp' => 80]
    ];

    public static $SLOTS = ['f_1', 'f_2', 'f_3', 'b_1', 'b_2', 'b_3'];

    /**
     * Get recent battles with high-level summary
     */
    public static function getRecentBattles(PDO $db, int $limit = 25): array {
        $stmt = $db->prepare("
            SELECT rp.id_report, rp.id_player, p.name AS player_name, rp.x, rp.y,
                   rp.time_stamp, rp.type, rp.lvl, rp.detail
            FROM `report_player` rp
            LEFT JOIN `player` p ON p.id_player = rp.id_player
            ORDER BY rp.id_report DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $battles = [];
        foreach ($rows as $row) {
            $detail = json_decode($row['detail'] ?? '{}', true) ?: [];
            $heros = $detail['heros'] ?? [];
            $reportHero = $heros[0] ?? [];
            $reportSide = (int)($reportHero['side'] ?? 1);
            $atkHero = $reportSide === 1 ? $reportHero : ($heros[1] ?? []);
            $defHero = $reportSide === 1 ? ($heros[1] ?? []) : $reportHero;
            $enemyHero = $reportSide === 1 ? $defHero : $atkHero;
            $roundsLog = $detail['rounds_log'] ?? $detail['rounds'] ?? [];

            $reportCombatMember = self::findCombatSnapshotMember(
                $detail,
                (int)($reportHero['id_hero'] ?? 0)
            );
            $equipmentInfo = $reportCombatMember
                ? self::parseEquipmentSnapshot($reportCombatMember)
                : null;

            // Calculate casualties
            $atkPre = 0; $atkPost = 0; $atkLoss = 0;
            $defPre = 0; $defPost = 0; $defLoss = 0;

            foreach (self::$SLOTS as $s) {
                $atkPre += (int)($atkHero[$s . '_pre'] ?? 0);
                $atkPost += (int)($atkHero[$s . '_post'] ?? 0);
                $atkLoss += (int)($atkHero[$s . '_loss'] ?? 0);

                $defPre += (int)($defHero[$s . '_pre'] ?? 0);
                $defPost += (int)($defHero[$s . '_post'] ?? 0);
                $defLoss += (int)($defHero[$s . '_loss'] ?? 0);
            }

            $roundsCount = max(1, count(array_filter($roundsLog, function($l) {
                return is_string($l) && strpos($l, 'الجولة') === 0;
            })));

            $attackerWon = ($defPost === 0 && $defPre > 0)
                || ($atkPost > 0 && $defPost <= $defPre * 0.1);
            $recordedSideWin = $detail['general_data']['side_win'] ?? null;
            $won = is_numeric($recordedSideWin)
                ? ((int)$recordedSideWin === ($reportSide === 1 ? 1 : 0))
                : ($reportSide === 1 ? $attackerWon : !$attackerWon);

            $battles[] = [
                'id_report'     => (int)$row['id_report'],
                'id_player'     => (int)$row['id_player'],
                'player_name'   => $row['player_name'] ?: ($reportHero['h_name'] ?? 'لاعب'),
                'hero_name'     => $reportHero['h_name'] ?? 'بطل',
                'enemy_name'    => $enemyHero['h_name'] ?? 'جيش العدو',
                'x'             => (int)$row['x'],
                'y'             => (int)$row['y'],
                'time_stamp'    => $row['time_stamp'],
                'won'           => $won,
                'rounds_count'  => $roundsCount,
                'atk_pre'       => $atkPre,
                'atk_loss'      => $atkLoss,
                'def_pre'       => $defPre,
                'def_loss'      => $defLoss,
                'has_barrage'   => $equipmentInfo['has_barrage'] ?? null,
                'has_shield'    => $equipmentInfo['has_shield'] ?? null
            ];
        }

        return $battles;
    }

    /**
     * Perform deep algorithmic analysis of a specific report
     */
    public static function analyzeReport(PDO $db, int $idReport): array {
        $stmt = $db->prepare("
            SELECT rp.*, p.name AS player_name, p.user_group, p.prestige, p.avatar AS player_avatar
            FROM `report_player` rp
            LEFT JOIN `player` p ON p.id_player = rp.id_player
            WHERE rp.id_report = ?
            LIMIT 1
        ");
        $stmt->execute([$idReport]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new RuntimeException("التقرير رقم {$idReport} غير موجود في قاعدة البيانات.");
        }

        $detail = json_decode($row['detail'] ?? '{}', true) ?: [];
        $heros = $detail['heros'] ?? [];
        $reportHero = $heros[0] ?? [];
        $reportSide = (int)($reportHero['side'] ?? 1);
        $atkHero = $reportSide === 1 ? $reportHero : ($heros[1] ?? []);
        $defHero = $reportSide === 1 ? ($heros[1] ?? []) : $reportHero;
        $roundsLog = $detail['rounds_log'] ?? $detail['rounds'] ?? [];

        // 1. Parse Troop Formations
        $attackerArmy = self::parseSideArmy($atkHero);
        $defenderArmy = self::parseSideArmy($defHero);
        $playerArmy = $reportSide === 1 ? $attackerArmy : $defenderArmy;

        // 2. Parse Equipment & Tactical Perks
        $combatMember = self::findCombatSnapshotMember(
            $detail,
            (int)($reportHero['id_hero'] ?? 0)
        );
        $equipInfo = $combatMember
            ? self::parseEquipmentSnapshot($combatMember)
            : self::unavailableEquipmentInfo();

        // 3. Parse Rounds Progression
        $roundsTable = self::parseRoundsLog($roundsLog);
        $roundsCount = count($roundsTable);

        // 4. Calculate Total Health and Combat Power
        $atkTotalHp = $attackerArmy['total_hp'];
        $defTotalHp = $defenderArmy['total_hp'];
        $atkTotalTroops = $attackerArmy['total_pre'];
        $defTotalTroops = $defenderArmy['total_pre'];

        $recordedSideWin = $detail['general_data']['side_win'] ?? null;
        $attackerWon = is_numeric($recordedSideWin)
            ? (int)$recordedSideWin === 1
            : ($defenderArmy['total_post'] === 0 && $defTotalTroops > 0);
        $playerWon = $reportSide === 1 ? $attackerWon : !$attackerWon;

        // 5. Intelligent Tactical Diagnosis (التشخيص الآلي)
        $verdict = self::generateTacticalVerdict([
            'won'            => $attackerWon,
            'rounds_count'   => $roundsCount,
            'atk_hp'         => $atkTotalHp,
            'def_hp'         => $defTotalHp,
            'atk_troops'     => $atkTotalTroops,
            'def_troops'     => $defTotalTroops,
            'atk_loss'       => $attackerArmy['total_loss'],
            'def_loss'       => $defenderArmy['total_loss'],
            'atk_hero_name'  => $atkHero['h_name'] ?? 'بطل المهاجم',
            'def_hero_name'  => $defHero['h_name'] ?? 'بطل المدافع',
            'equip'          => $equipInfo,
            'attacker_army'  => $attackerArmy,
            'defender_army'  => $defenderArmy,
            'rounds_table'   => $roundsTable
        ]);

        return [
            'report_id'      => (int)$row['id_report'],
            'time_stamp'     => $row['time_stamp'],
            'coords'         => ['x' => (int)$row['x'], 'y' => (int)$row['y']],
            'target_type'    => (int)$row['type'],
            'target_lvl'     => (int)$row['lvl'],
            'player'         => [
                'id_player'   => (int)$row['id_player'],
                'name'        => $row['player_name'] ?: ($reportHero['h_name'] ?? 'لاعب'),
                'avatar'      => (int)($row['player_avatar'] ?? $reportHero['avatar'] ?? 1),
                'prestige'    => (int)($row['prestige'] ?? 0)
            ],
            'won'            => $attackerWon,
            'attacker_won'   => $attackerWon,
            'player_won'     => $playerWon,
            'player_side'    => $reportSide,
            'player_army'    => $playerArmy,
            'simulation_available' => $combatMember !== null,
            'rounds_count'   => $roundsCount,
            'attacker_hero'  => [
                'name'        => $atkHero['h_name'] ?? 'بطل المهاجم',
                'avatar'      => (int)($atkHero['avatar'] ?? 1),
                'id_player'   => (int)($atkHero['id_player'] ?? $row['id_player']),
                'xp_gained'   => (int)($atkHero['xp'] ?? 0)
            ],
            'defender_hero'  => [
                'name'        => $defHero['h_name'] ?? 'بطل النظام',
                'avatar'      => (int)($defHero['avatar'] ?? 2),
                'id_player'   => (int)($defHero['id_player'] ?? 0)
            ],
            'attacker_army'  => $attackerArmy,
            'defender_army'  => $defenderArmy,
            'equipment'      => $equipInfo,
            'rounds_table'   => $roundsTable,
            'raw_rounds_log' => $roundsLog,
            'verdict'        => $verdict,
            'prize'          => $detail['prize'] ?? []
        ];
    }

    /**
     * Parse hero army slots into structured data with HP and counts
     */
    private static function parseSideArmy(array $heroData): array {
        $slots = [];
        $byType = [];
        $totalPre = 0; $totalPost = 0; $totalLoss = 0; $totalInjured = 0;
        $totalHp = 0; $totalAtt = 0;

        foreach (self::$SLOTS as $s) {
            $type = (int)($heroData[$s . '_type'] ?? 0);
            $pre = (int)($heroData[$s . '_pre'] ?? 0);
            $post = (int)($heroData[$s . '_post'] ?? 0);
            $loss = (int)($heroData[$s . '_loss'] ?? ($pre - $post));
            $inj = (int)($heroData[$s . '_injured'] ?? 0);

            $unitName = self::$UNIT_NAMES[$type] ?? ($type > 0 ? "وحدة $type" : '-');
            $hpPerUnit = self::$UNIT_STATS[$type]['hp'] ?? 0;
            $attPerUnit = self::$UNIT_STATS[$type]['att'] ?? 0;

            $slotHp = $pre * $hpPerUnit;
            $slotAtt = $pre * $attPerUnit;

            $slots[$s] = [
                'slot'       => $s,
                'type'       => $type,
                'name'       => $unitName,
                'pre'        => $pre,
                'post'       => $post,
                'loss'       => $loss,
                'injured'    => $inj,
                'hp'         => $slotHp,
                'att'        => $slotAtt
            ];

            if ($type > 0 && $pre > 0) {
                if (!isset($byType[$type])) {
                    $byType[$type] = [
                        'type' => $type,
                        'name' => $unitName,
                        'pre' => 0, 'post' => 0, 'loss' => 0, 'injured' => 0, 'hp' => 0, 'att' => 0
                    ];
                }
                $byType[$type]['pre'] += $pre;
                $byType[$type]['post'] += $post;
                $byType[$type]['loss'] += $loss;
                $byType[$type]['injured'] += $inj;
                $byType[$type]['hp'] += $slotHp;
                $byType[$type]['att'] += $slotAtt;
            }

            $totalPre += $pre;
            $totalPost += $post;
            $totalLoss += $loss;
            $totalInjured += $inj;
            $totalHp += $slotHp;
            $totalAtt += $slotAtt;
        }

        return [
            'slots'          => $slots,
            'by_type'        => array_values($byType),
            'total_pre'      => $totalPre,
            'total_post'     => $totalPost,
            'total_loss'     => $totalLoss,
            'total_injured'  => $totalInjured,
            'total_hp'       => $totalHp,
            'total_att'      => $totalAtt
        ];
    }

    private static function findCombatSnapshotMember(array $detail, int $idHero): ?array {
        $snapshot = $detail['combat_snapshot'] ?? null;
        if (
            $idHero <= 0
            || !is_array($snapshot)
            || (int)($snapshot['version'] ?? 0) !== 1
            || (int)($snapshot['reportHeroId'] ?? 0) !== $idHero
            || !is_array($snapshot['attackers'] ?? null)
            || !is_array($snapshot['defendingMembers'] ?? null)
            || !is_array($snapshot['npcDefenderTroops'] ?? null)
            || !is_array($snapshot['solStats'] ?? null)
            || !is_array($snapshot['wallFortifications'] ?? null)
        ) {
            return null;
        }

        foreach (array_merge($snapshot['attackers'], $snapshot['defendingMembers']) as $member) {
            if (
                is_array($member)
                && (int)($member['hero']['id_hero'] ?? 0) === $idHero
                && is_array($member['army'] ?? null)
                && is_array($member['equipmentBonuses'] ?? null)
            ) {
                return $member;
            }
        }

        return null;
    }

    private static function parseEquipmentSnapshot(array $member): array {
        $bonuses = $member['equipmentBonuses'];
        $result = [
            'data_available'   => true,
            'pieces_count'     => count($bonuses),
            'attack'           => 0,
            'defence'          => 0,
            'vitality'         => 0,
            'damage'           => 0,
            'has_barrage'      => false,
            'barrage_count'    => 0,
            'has_shield'       => false,
            'shield_count'     => 0,
            'tactical_notes'   => [],
            'equipped_items'   => $bonuses
        ];

        foreach ($bonuses as $bonus) {
            if (!is_array($bonus)) continue;
            foreach (['attack', 'defence', 'vitality', 'damage'] as $stat) {
                $result[$stat] += (int)($bonus[$stat] ?? 0);
            }
            $specialAttribute = (int)($bonus['sp_attr'] ?? 0);
            if ($specialAttribute === 1 || $specialAttribute === 2) {
                $result['has_barrage'] = true;
                $result['barrage_count']++;
            } elseif ($specialAttribute === 3) {
                $result['has_shield'] = true;
                $result['shield_count']++;
            }
        }

        return $result;
    }

    private static function unavailableEquipmentInfo(): array {
        return [
            'data_available' => false,
            'pieces_count' => 0,
            'attack' => 0,
            'defence' => 0,
            'vitality' => 0,
            'damage' => 0,
            'has_barrage' => null,
            'barrage_count' => 0,
            'has_shield' => null,
            'shield_count' => 0,
            'tactical_notes' => [],
            'equipped_items' => []
        ];
    }

    /**
     * Parse rounds log into structured table
     */
    private static function parseRoundsLog(array $roundsLog): array {
        $table = [];
        foreach ($roundsLog as $line) {
            if (!is_string($line) || strpos($line, 'الجولة') !== 0) continue;

            $roundNum = 1;
            if (preg_match('/^الجولة\s*(\d+):/u', $line, $m)) {
                $roundNum = (int)$m[1];
            }

            $defLoss = 0;
            $atkLoss = 0;
            $defDetail = '';
            $atkDetail = '';

            if (preg_match('/خسائر المدافعين\s*(\d+)(?:\s*\((.*?)\))?/u', $line, $m)) {
                $defLoss = (int)$m[1];
                $defDetail = self::formatTroopCasualtyDetail($m[2] ?? '');
            }
            if (preg_match('/خسائر المهاجمين\s*(\d+)(?:\s*\((.*?)\))?/u', $line, $m)) {
                $atkLoss = (int)$m[1];
                $atkDetail = self::formatTroopCasualtyDetail($m[2] ?? '');
            }

            $table[] = [
                'round'        => $roundNum,
                'def_loss'     => $defLoss,
                'def_detail'   => $defDetail,
                'atk_loss'     => $atkLoss,
                'atk_detail'   => $atkDetail,
                'raw_text'     => $line
            ];
        }

        return $table;
    }

    private static function formatTroopCasualtyDetail(string $detailRaw): string {
        if (!$detailRaw) return '';
        // Replace "الوحدة 1" with "مشاة", etc.
        $formatted = $detailRaw;
        foreach (self::$UNIT_NAMES as $type => $name) {
            $formatted = preg_replace("/الوحدة\s*$type/u", $name, $formatted);
        }
        return $formatted;
    }

    /**
     * Generate smart diagnostic verdict in Arabic
     */
    private static function generateTacticalVerdict(array $ctx): array {
        $won = $ctx['won'];
        $rounds = $ctx['rounds_count'];
        $atkLoss = $ctx['atk_loss'];
        $defLoss = $ctx['def_loss'];
        $equip = $ctx['equip'];
        $title = $won ? 'انتصار صاحب التقرير' : 'هزيمة صاحب التقرير';
        $type = $won ? 'success' : 'danger';
        $summary = "يسجل التقرير {$rounds} جولة؛ خسائر المهاجم {$atkLoss} وخسائر المدافع {$defLoss}.";
        $reasons = [
            'النتيجة وعدد الخسائر مستخرجة من بيانات التقرير المسجلة.'
        ];
        $recommendations = [];

        if ($equip['data_available']) {
            if ($equip['has_barrage']) {
                $reasons[] = "لقطة القتال تسجل تفعيل وابل السهام ({$equip['barrage_count']}x).";
            }
            if ($equip['has_shield']) {
                $reasons[] = "لقطة القتال تسجل تفعيل الدرع التكتيكي ({$equip['shield_count']}x).";
            }
        } else {
            $reasons[] = 'لا تتوفر لقطة تاريخية للعتاد والخواص في هذا التقرير.';
        }

        return [
            'title'           => $title,
            'type'            => $type,
            'summary'         => $summary,
            'reasons'         => $reasons,
            'recommendations' => $recommendations
        ];
    }

    /**
     * Run "What-If" sandbox simulation
     */
    public static function simulateSandbox(PDO $db, int $idReport, array $options): array {
        $stmt = $db->prepare("SELECT detail FROM report_player WHERE id_report = ?");
        $stmt->execute([$idReport]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException("التقرير غير موجود.");

        $detail = json_decode($row['detail'] ?? '{}', true) ?: [];
        $snapshot = $detail['combat_snapshot'] ?? null;
        $reportHeroId = (int)($detail['heros'][0]['id_hero'] ?? 0);
        $combatMember = self::findCombatSnapshotMember($detail, $reportHeroId);
        if (!$combatMember || !is_array($snapshot)) {
            throw new RuntimeException('لا يمكن محاكاة هذا التقرير؛ لم تُحفظ لقطة بيانات القتال التاريخية.');
        }

        $attackers = $snapshot['attackers'];
        $defendingMembers = $snapshot['defendingMembers'];
        $memberSide = (int)($combatMember['side'] ?? 0);
        $members = $memberSide === 1 ? $attackers : $defendingMembers;
        $targetIndex = null;
        foreach ($members as $index => $member) {
            if ((int)($member['hero']['id_hero'] ?? 0) === $reportHeroId) {
                $targetIndex = $index;
                break;
            }
        }
        if ($targetIndex === null) {
            throw new RuntimeException('لم يُعثر على صاحب التقرير ضمن لقطة القتال.');
        }

        $enableBarrage = !empty($options['enable_barrage']);
        $enableShield = !empty($options['enable_shield']);
        $equips = $members[$targetIndex]['equipmentBonuses'];
        foreach ($equips as &$equipment) {
            if (in_array((int)($equipment['sp_attr'] ?? 0), [1, 2, 3], true)) {
                $equipment['sp_attr'] = 0;
            }
        }
        unset($equipment);

        if ($enableBarrage) {
            $equips[] = ['part' => 'sandbox', 'attack' => 0, 'defence' => 0, 'vitality' => 0, 'damage' => 0, 'sp_attr' => 1];
        }
        if ($enableShield) {
            $equips[] = ['part' => 'sandbox', 'attack' => 0, 'defence' => 0, 'vitality' => 0, 'damage' => 0, 'sp_attr' => 3];
        }
        $members[$targetIndex]['equipmentBonuses'] = $equips;
        if ($memberSide === 1) {
            $attackers = $members;
        } else {
            $defendingMembers = $members;
        }

        $cliPayload = [
            'attackers'                 => $attackers,
            'npcDefenderTroops'         => $snapshot['npcDefenderTroops'],
            'solStats'                  => $snapshot['solStats'],
            'commanderAttackBonus'      => $snapshot['commanderAttackBonus'] ?? 0,
            'npcCombatPowerMultiplier'  => $snapshot['npcCombatPowerMultiplier'] ?? 1,
            'wallFortifications'       => $snapshot['wallFortifications'],
            'defendingMembers'          => $defendingMembers
        ];

        // Execute via simulate-cli.js
        $cliScript = dirname(__DIR__, 2) . '/caesar-shared/simulate-cli.js';
        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];

        $process = proc_open('node ' . escapeshellarg($cliScript), $descriptorspec, $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException("تعذر تشغيل محاكي المعارك عبر Node.");
        }

        $input = json_encode($cliPayload);
        if ($input === false) {
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            throw new RuntimeException('تعذر تجهيز بيانات المحاكاة: ' . json_last_error_msg());
        }
        fwrite($pipes[0], $input);
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            throw new RuntimeException("فشل محاكي المعارك: " . ($stderr ?: $stdout));
        }

        $simRes = json_decode($stdout, true);
        if (($simRes['state'] ?? '') !== 'ok') {
            throw new RuntimeException("خطأ في نتيجة المحاكي: " . ($simRes['message'] ?? 'غير معروف'));
        }

        $res = $simRes['result'];
        $remainingMembers = $memberSide === 1
            ? ($res['remainingAttackers'] ?? [])
            : ($res['remainingDefenders'] ?? []);
        $remainingMember = null;
        foreach ($remainingMembers as $result) {
            if ((int)($result['member']['hero']['id_hero'] ?? 0) === $reportHeroId) {
                $remainingMember = $result;
                break;
            }
        }
        if (!is_array($remainingMember['army'] ?? null)) {
            throw new RuntimeException('لم تُرجع المحاكاة جيش صاحب التقرير.');
        }
        $remArmy = $remainingMember['army'];
        $originalArmy = $combatMember['army'];

        $simLosses = 0;
        foreach (self::$SLOTS as $s) {
            $origPre = (int)($originalArmy[$s . '_num'] ?? 0);
            $remPost = (int)($remArmy[$s] ?? 0);
            $simLosses += max(0, $origPre - $remPost);
        }

        return [
            'state'             => 'ok',
            'simulated_rounds'  => $res['roundsCount'],
            'attackers_won'     => $res['attackersWon'],
            'player_won'        => $memberSide === 1
                ? $res['attackersWon']
                : !$res['attackersWon'],
            'rounds_log'        => $res['roundsLog'],
            'total_losses'      => $simLosses,
            'remaining_army'    => $remArmy,
            'player_side'       => $memberSide
        ];
    }
}
