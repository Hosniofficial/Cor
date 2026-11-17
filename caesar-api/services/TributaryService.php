<?php

class TributaryService {
    public const TRIBUTE_PERCENT = 20;

    public static function calculatePayment(int $hourlyProduction, int $elapsedSeconds, int $remainder): array {
        $denominator = intdiv(3600 * 100, self::TRIBUTE_PERCENT);
        $hourlyProduction = max(0, $hourlyProduction);
        $elapsedSeconds = max(0, $elapsedSeconds);
        $remainder = max(0, min($denominator - 1, $remainder));

        $numerator = ($hourlyProduction * $elapsedSeconds) + $remainder;
        return [
            'amount' => intdiv($numerator, $denominator),
            'remainder' => $numerator % $denominator
        ];
    }

    public static function settleCityTribute(PDO $db, int $cityId, int $now, array $city): array {
        $zero = ['food' => 0, 'wood' => 0, 'stone' => 0, 'metal' => 0];
        $relationStmt = $db->prepare(
            'SELECT id_sovereign_city, last_tribute_at, food_remainder, wood_remainder, ' .
            'stone_remainder, metal_remainder FROM city_tributary WHERE id_city = ? FOR UPDATE'
        );
        $relationStmt->execute([$cityId]);
        $relation = $relationStmt->fetch(PDO::FETCH_ASSOC);
        if (!$relation) {
            return $zero;
        }

        $elapsed = max(0, $now - (int)$relation['last_tribute_at']);
        if ($elapsed === 0) {
            return $zero;
        }

        $amounts = [];
        $remainders = [];
        foreach (['food', 'wood', 'stone', 'metal'] as $resource) {
            $payment = self::calculatePayment(
                (int)($city[$resource . '_in'] ?? 0),
                $elapsed,
                (int)$relation[$resource . '_remainder']
            );
            $amounts[$resource] = $payment['amount'];
            $remainders[] = $payment['remainder'];
        }

        $db->prepare(
            'UPDATE city_tributary SET last_tribute_at = ?, food_remainder = ?, wood_remainder = ?, ' .
            'stone_remainder = ?, metal_remainder = ? WHERE id_city = ?'
        )->execute(array_merge([$now], $remainders, [$cityId]));

        if (array_sum($amounts) > 0) {
            $db->prepare(
                'UPDATE city_tributary_balance SET food = food + ?, wood = wood + ?, ' .
                'stone = stone + ?, metal = metal + ? WHERE id_sovereign_city = ?'
            )->execute([
                $amounts['food'],
                $amounts['wood'],
                $amounts['stone'],
                $amounts['metal'],
                (int)$relation['id_sovereign_city']
            ]);
        }

        return $amounts;
    }
}
