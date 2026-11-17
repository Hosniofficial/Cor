<?php

class BattleReportService {
    public static function addReplayDeaths(array $detail): array {
        if (empty($detail['hero_list']) || !is_array($detail['hero_list'])) {
            return $detail;
        }

        $slotToBlock = ['f_1' => '0', 'f_2' => '1', 'f_3' => '2', 'b_1' => '3', 'b_2' => '4', 'b_3' => '5'];
        foreach ($detail['hero_list'] as &$hero) {
            if (isset($hero['dead']) && is_array($hero['dead'])) {
                continue;
            }

            $side = (int)($hero['Hero']['side'] ?? 0);
            $sourceHero = null;
            foreach (($detail['heros'] ?? []) as $candidate) {
                if ((int)($candidate['side'] ?? -1) === $side) {
                    $sourceHero = $candidate;
                    break;
                }
            }

            $heroIndex = $side === 1 ? '3' : '0';
            $dead = [];
            foreach ($slotToBlock as $slot => $position) {
                $sourceDead = $sourceHero[$slot . '_dead'] ?? null;
                if ($sourceDead !== null && $sourceDead !== '' && is_numeric($sourceDead)) {
                    $dead[$position] = max(0, (int)$sourceDead);
                    continue;
                }

                $pre = $sourceHero[$slot . '_pre'] ?? $hero['pre'][$position] ?? null;
                $post = $sourceHero[$slot . '_post'] ?? $hero['post'][$position] ?? null;
                if (is_numeric($pre) && is_numeric($post)) {
                    $dead[$position] = max(0, (int)$pre - (int)$post);
                    continue;
                }

                $dead[$position] = 0;
                foreach (($detail['round_list'] ?? []) as $round) {
                    $block = $round['Heros'][$heroIndex]['ArmyBlocks'][$position] ?? null;
                    if (is_array($block) && isset($block['dead']) && is_numeric($block['dead'])) {
                        $dead[$position] += max(0, (int)$block['dead']);
                    }
                }
            }
            $hero['dead'] = $dead;
        }
        unset($hero);

        return $detail;
    }
}
