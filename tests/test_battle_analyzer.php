<?php
require_once __DIR__ . '/../caesar-api/db.php';
require_once __DIR__ . '/../caesar-api/services/BattleAnalyzerService.php';

$db = getDB();
$recent = BattleAnalyzerService::getRecentBattles($db, 5);
echo "Recent battles count: " . count($recent) . "\n";
foreach ($recent as $b) {
    echo "Battle #{$b['id_report']} | {$b['hero_name']} vs {$b['enemy_name']} | Won: " . ($b['won'] ? 'YES' : 'NO') . " | Rounds: {$b['rounds_count']} | Barrage: " . ($b['has_barrage'] ? 'YES' : 'NO') . "\n";
}

echo "\n--- Analyzing Report 109 ---\n";
$analysis = BattleAnalyzerService::analyzeReport($db, 109);
echo "Verdict: " . $analysis['verdict']['title'] . "\n";
echo "Summary: " . $analysis['verdict']['summary'] . "\n";
echo "Attacker total pre: " . $analysis['attacker_army']['total_pre'] . " | Loss: " . $analysis['attacker_army']['total_loss'] . "\n";
echo "Defender total pre: " . $analysis['defender_army']['total_pre'] . " | Loss: " . $analysis['defender_army']['total_loss'] . "\n";
echo "Tactical notes: " . implode(" | ", $analysis['equipment']['tactical_notes']) . "\n";

echo "\n--- Testing Sandbox Simulation on Report 109 with Barrage (+25% archer attack) ---\n";
$sim = BattleAnalyzerService::simulateSandbox($db, 109, ['enable_barrage' => true, 'enable_shield' => false]);
echo "Sim state: " . $sim['state'] . "\n";
echo "Sim rounds: " . $sim['simulated_rounds'] . "\n";
echo "Sim total losses: " . $sim['total_losses'] . "\n";
echo "Original losses in Report 109: " . $analysis['attacker_army']['total_loss'] . "\n";
echo "Troops saved: " . ($analysis['attacker_army']['total_loss'] - $sim['total_losses']) . "\n";
