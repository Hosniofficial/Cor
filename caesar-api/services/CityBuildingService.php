<?php

/**
 * Shared city building slot definitions and starter-city initializer.
 * Excludes disabled naval placeholder slots (seaport type 19, lighthouse type 20)
 * that have no buildings or functionality in the game UI.
 */
final class CityBuildingService {
    public const FIXED_STARTER_BUILDINGS = [
        'palace' => ['type' => 12, 'lvl' => 1],
        'wall'   => ['type' => 13, 'lvl' => 0],
        'farm'   => ['type' => 16, 'lvl' => 1],
        'wood'   => ['type' => 15, 'lvl' => 1],
        'stone'  => ['type' => 18, 'lvl' => 1],
        'mine'   => ['type' => 17, 'lvl' => 1],
    ];

    public const CONSTRUCTIBLE_PLACES = [
        'above_palace_1', 'above_palace_2', 'above_palace_3', 'above_palace_4', 'above_palace_5', 'above_palace_6',
        'around_wood_1', 'around_wood_2', 'around_wood_3',
        'hill_1', 'hill_2', 'hill_3', 'hill_4', 'hill_5', 'hill_6', 'hill_7', 'hill_8', 'hill_9', 'hill_10', 'hill_11', 'hill_12',
        'light_house_1', 'light_house_2', 'light_house_3', 'light_house_4', 'light_house_5', 'light_house_6', 'light_house_7', 'light_house_8', 'light_house_9', 'light_house_10',
        'under_palace_1', 'under_palace_2', 'under_palace_3', 'under_palace_4', 'under_palace_5', 'under_palace_6', 'under_palace_7', 'under_palace_8', 'under_palace_9', 'under_palace_10', 'under_palace_11', 'under_palace_12',
        'under_wall_1', 'under_wall_2', 'under_wall_3', 'under_wall_4', 'under_wall_5', 'under_wall_6', 'under_wall_7', 'under_wall_8', 'under_wall_9', 'under_wall_10', 'under_wall_11', 'under_wall_12',
    ];

    public static function getStandardPlaces(): array {
        return array_merge(array_keys(self::FIXED_STARTER_BUILDINGS), self::CONSTRUCTIBLE_PLACES);
    }

    public static function isConstructiblePlace(string $place): bool {
        return in_array($place, self::CONSTRUCTIBLE_PLACES, true);
    }

    public static function initializeCityBuildings($db, int $cityId, bool $includeStarterCottage = false): void {
        $starterBuildings = self::FIXED_STARTER_BUILDINGS;
        if ($includeStarterCottage) {
            $starterBuildings['light_house_1'] = ['type' => 1, 'lvl' => 1];
        }

        $bStmt = $db->prepare(
            "INSERT INTO `city_building` (`id_city`, `place`, `type`, `lvl`) VALUES (?, ?, ?, ?) " .
            "ON DUPLICATE KEY UPDATE `type` = VALUES(`type`), `lvl` = VALUES(`lvl`)"
        );
        foreach (self::getStandardPlaces() as $place) {
            $type = $starterBuildings[$place]['type'] ?? 0;
            $lvl  = $starterBuildings[$place]['lvl'] ?? 0;
            $bStmt->execute([$cityId, $place, $type, $lvl]);
        }
    }
}
