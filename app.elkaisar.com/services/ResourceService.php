<?php

final class ResourceLimitException extends RuntimeException {
}

final class ResourceService {
    public const MAX_CITY_BALANCE = 1000000000000;
    public const CITY_RESOURCES = ['food', 'wood', 'stone', 'metal', 'coin'];

    public static function parseAmount($value): ?int {
        if (!is_int($value) && !is_string($value)) {
            return null;
        }

        $raw = (string)$value;
        if ($raw === '' || !ctype_digit($raw)) {
            return null;
        }

        $normalized = ltrim($raw, '0');
        if ($normalized === '') {
            return 0;
        }
        $max = (string)self::MAX_CITY_BALANCE;
        if (strlen($normalized) > strlen($max)
            || (strlen($normalized) === strlen($max) && strcmp($normalized, $max) > 0)) {
            return null;
        }

        return (int)$normalized;
    }

    public static function creditCity(PDO $db, int $cityId, ?int $playerId, array $credits): bool {
        $credits = array_filter(
            $credits,
            static fn($amount): bool => $amount !== 0
        );
        if ($cityId <= 0 || !$credits) {
            return $cityId > 0;
        }

        $sets = [];
        $conditions = [];
        $params = [];
        foreach ($credits as $resource => $amount) {
            if (!in_array($resource, self::CITY_RESOURCES, true)
                || !is_int($amount) || $amount < 0 || $amount > self::MAX_CITY_BALANCE) {
                throw new InvalidArgumentException('Invalid city resource credit');
            }
            $sets[] = "`$resource` = `$resource` + ?";
            $conditions[] = "`$resource` <= ?";
            $params[] = $amount;
        }

        $where = '`id_city` = ?';
        if ($playerId !== null) {
            $where .= ' AND `id_player` = ?';
        }
        $params[] = $cityId;
        if ($playerId !== null) {
            $params[] = $playerId;
        }
        foreach ($credits as $amount) {
            $params[] = self::MAX_CITY_BALANCE - $amount;
        }

        $stmt = $db->prepare(
            'UPDATE `city` SET ' . implode(', ', $sets) .
            ' WHERE ' . $where . ' AND ' . implode(' AND ', $conditions)
        );
        $stmt->execute($params);
        return $stmt->rowCount() === 1;
    }

    public static function creditCityUpToLimit(PDO $db, int $cityId, ?int $playerId, array $requested): array {
        $resources = [];
        foreach ($requested as $resource => $amount) {
            if (!in_array($resource, self::CITY_RESOURCES, true)
                || !is_int($amount) || $amount < 0 || $amount > self::MAX_CITY_BALANCE) {
                throw new InvalidArgumentException('Invalid city resource credit');
            }
            if ($amount > 0) {
                $resources[] = $resource;
            }
        }
        if ($cityId <= 0 || !$resources) {
            return [];
        }

        $ownsTransaction = !$db->inTransaction();
        if ($ownsTransaction) {
            $db->beginTransaction();
        }
        try {
            $where = '`id_city` = ?';
            $params = [$cityId];
            if ($playerId !== null) {
                $where .= ' AND `id_player` = ?';
                $params[] = $playerId;
            }
            $columns = implode(', ', array_map(static fn($resource): string => "`$resource`", $resources));
            $stmt = $db->prepare("SELECT $columns FROM `city` WHERE $where FOR UPDATE");
            $stmt->execute($params);
            $city = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$city) {
                throw new RuntimeException('City resource recipient not found');
            }

            $credited = [];
            $sets = [];
            $updateParams = [];
            foreach ($resources as $resource) {
                $current = (int)$city[$resource];
                $gain = min($requested[$resource], max(0, self::MAX_CITY_BALANCE - $current));
                $credited[$resource] = $gain;
                if ($gain > 0) {
                    $sets[] = "`$resource` = `$resource` + ?";
                    $updateParams[] = $gain;
                }
            }
            if ($sets) {
                $updateParams = array_merge($updateParams, $params);
                $db->prepare('UPDATE `city` SET ' . implode(', ', $sets) . " WHERE $where")
                    ->execute($updateParams);
            }
            if ($ownsTransaction) {
                $db->commit();
            }
            return $credited;
        } catch (Throwable $error) {
            if ($ownsTransaction && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $error;
        }
    }
}
