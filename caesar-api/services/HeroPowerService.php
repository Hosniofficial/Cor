<?php

final class HeroPowerService {
    public const ACTION_COST = 10;
    public const RECOVERY_INTERVAL_SECONDS = 120;
    public const CONQUEST_TASK_ID = 1;

    public static function getMaxPower(int $level): int {
        return 50 + (2 * max(0, $level));
    }

    public static function getRequiredPower(int $task, int $targetRequiredPower = 0): int {
        return $task === self::CONQUEST_TASK_ID
            ? 0
            : max(self::ACTION_COST, $targetRequiredPower);
    }

    public static function recover($db, array $hero, int $now): array {
        $powerMax = max(0, (int)($hero['power_max'] ?? self::getMaxPower((int)($hero['lvl'] ?? 1))));
        $power = max(0, min((int)$hero['power'], $powerMax));
        $powerLastSync = (int)($hero['power_ls'] ?? 0);

        if ($power >= $powerMax || $powerLastSync <= 0) {
            $powerLastSync = $now;
        } elseif ($now > $powerLastSync) {
            $recoveredPoints = intdiv($now - $powerLastSync, self::RECOVERY_INTERVAL_SECONDS);
            if ($recoveredPoints > 0) {
                $power = min($powerMax, $power + $recoveredPoints);
                $powerLastSync = $power >= $powerMax
                    ? $now
                    : $powerLastSync + ($recoveredPoints * self::RECOVERY_INTERVAL_SECONDS);
            }
        }

        if ((int)$hero['power'] !== $power
            || (int)($hero['power_max'] ?? 0) !== $powerMax
            || (int)($hero['power_ls'] ?? 0) !== $powerLastSync) {
            $stmt = $db->prepare(
                'UPDATE city_hero SET power = ?, power_max = ?, power_ls = ? ' .
                'WHERE id_hero = ? AND id_player = ?'
            );
            $stmt->execute([
                $power,
                $powerMax,
                $powerLastSync,
                (int)$hero['id_hero'],
                (int)$hero['id_player']
            ]);
        }

        $hero['power'] = $power;
        $hero['power_max'] = $powerMax;
        $hero['power_ls'] = $powerLastSync;
        return $hero;
    }
}
