<?php

final class HeroDeploymentService {
    public const PLAZA_BUILDING_TYPE = 11;
    public const MAX_HEROES_OUTSIDE_CITY = 10;

    public static function getOutsideLimit(int $plazaLevel): int {
        return min(self::MAX_HEROES_OUTSIDE_CITY, max(0, $plazaLevel));
    }

    public static function hasAvailableSlot(int $plazaLevel, int $outsideCount): bool {
        return max(0, $outsideCount) < self::getOutsideLimit($plazaLevel);
    }

    public static function hasAvailableOutsideSlot(
        PDO $db,
        int $cityId,
        int $playerId,
        array $additionalCityIds = []
    ): bool {
        $cityIds = array_values(array_unique(array_filter(
            array_map('intval', array_merge([$cityId], $additionalCityIds)),
            static function(int $id): bool {
                return $id > 0;
            }
        )));
        sort($cityIds, SORT_NUMERIC);

        $cityLock = $db->prepare('SELECT id_city FROM city WHERE id_city = ? FOR UPDATE');
        foreach ($cityIds as $lockedCityId) {
            $cityLock->execute([$lockedCityId]);
            if ($cityLock->fetchColumn() === false) {
                throw new RuntimeException('Hero deployment city is unavailable');
            }
        }

        $plazaStmt = $db->prepare(
            'SELECT COALESCE(MAX(lvl), 0) FROM city_building WHERE id_city = ? AND type = ?'
        );
        $plazaStmt->execute([$cityId, self::PLAZA_BUILDING_TYPE]);
        $plazaLevel = (int)$plazaStmt->fetchColumn();

        $outsideCountStmt = $db->prepare(
            'SELECT COUNT(*) FROM city_hero h ' .
            'WHERE h.id_city = ? AND h.id_player = ? AND h.in_city = 0 AND h.loyal > 0 ' .
            'AND NOT EXISTS (' .
                "SELECT 1 FROM city_garrison g WHERE g.id_hero = h.id_hero " .
                "AND g.id_city = h.id_city AND g.status = 'engaged'" .
            ')'
        );
        $outsideCountStmt->execute([$cityId, $playerId]);

        return self::hasAvailableSlot($plazaLevel, (int)$outsideCountStmt->fetchColumn());
    }
}
