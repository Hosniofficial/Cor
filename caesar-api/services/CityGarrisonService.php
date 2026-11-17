<?php

final class CityGarrisonService {
    public const MAX_CITY_GARRISON = 250;

    public static function getCityGarrison(PDO $db, int $cityId): array {
        $stmt = $db->prepare(
            'SELECT g.id_hero, g.id_city, g.id_player, g.ord, g.status, g.time_arrive, g.return_at, ' .
            'g.battle_id, h.id_city AS home_city_id, h.name AS HeroName, h.lvl, h.avatar, h.in_city, ' .
            'p.name AS LordName, host.id_player AS host_player_id, ' .
            '(COALESCE(a.f_1_num, 0) + COALESCE(a.f_2_num, 0) + COALESCE(a.f_3_num, 0) + ' .
            'COALESCE(a.b_1_num, 0) + COALESCE(a.b_2_num, 0) + COALESCE(a.b_3_num, 0)) AS troop_count, ' .
            'a.f_1_type AS army_f_1_type, a.f_1_num AS army_f_1_num, ' .
            'a.f_2_type AS army_f_2_type, a.f_2_num AS army_f_2_num, ' .
            'a.f_3_type AS army_f_3_type, a.f_3_num AS army_f_3_num, ' .
            'a.b_1_type AS army_b_1_type, a.b_1_num AS army_b_1_num, ' .
            'a.b_2_type AS army_b_2_type, a.b_2_num AS army_b_2_num, ' .
            'a.b_3_type AS army_b_3_type, a.b_3_num AS army_b_3_num ' .
            'FROM city_garrison g ' .
            'JOIN city_hero h ON h.id_hero = g.id_hero AND h.id_player = g.id_player ' .
            'JOIN player p ON p.id_player = g.id_player ' .
            'JOIN city host ON host.id_city = g.id_city ' .
            'LEFT JOIN hero_army a ON a.id_hero = g.id_hero AND a.id_player = g.id_player ' .
            'WHERE g.id_city = ? ORDER BY g.ord ASC, g.id_hero ASC'
        );
        $stmt->execute([$cityId]);
        $heroes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($heroes as &$hero) {
            foreach ($hero as $key => $value) {
                if (is_numeric($value)) {
                    $hero[$key] = (int)$value;
                }
            }
            $hero['Army'] = [];
            foreach (['f_1', 'f_2', 'f_3', 'b_1', 'b_2', 'b_3'] as $slot) {
                $hero['Army'][$slot . '_type'] = (int)($hero['army_' . $slot . '_type'] ?? 0);
                $hero['Army'][$slot . '_num'] = (int)($hero['army_' . $slot . '_num'] ?? 0);
                unset($hero['army_' . $slot . '_type'], $hero['army_' . $slot . '_num']);
            }
            $hero['isAlly'] = $hero['id_player'] !== $hero['host_player_id'];
        }
        unset($hero);

        return $heroes;
    }

    public static function getPlayerGarrisons(PDO $db, int $playerId): array {
        $stmt = $db->prepare(
            'SELECT g.id_hero, g.id_city, g.id_player, g.status, g.time_arrive, g.return_at, ' .
            'h.id_city AS home_city_id, h.name AS HeroName, h.lvl, h.avatar, h.in_city, ' .
            'host.name AS CityName, host.x AS x_coord, host.y AS y_coord ' .
            'FROM city_garrison g ' .
            'JOIN city_hero h ON h.id_hero = g.id_hero AND h.id_player = g.id_player ' .
            'JOIN city host ON host.id_city = g.id_city ' .
            'WHERE g.id_player = ? AND g.id_city <> h.id_city ' .
            'ORDER BY g.time_arrive ASC, g.id_hero ASC'
        );
        $stmt->execute([$playerId]);
        $heroes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($heroes as &$hero) {
            foreach ($hero as $key => $value) {
                if (is_numeric($value)) {
                    $hero[$key] = (int)$value;
                }
            }
            $hero['Task'] = 5;
            $hero['time_back'] = $hero['return_at'] ?: 0;
        }
        unset($hero);

        return $heroes;
    }

}
