CREATE TABLE IF NOT EXISTS `city_tributary` (
    `id_city` INT NOT NULL PRIMARY KEY,
    `id_sovereign_city` INT NOT NULL,
    `colonized_at` INT NOT NULL,
    `last_tribute_at` INT NOT NULL,
    `food_remainder` INT UNSIGNED NOT NULL DEFAULT 0,
    `wood_remainder` INT UNSIGNED NOT NULL DEFAULT 0,
    `stone_remainder` INT UNSIGNED NOT NULL DEFAULT 0,
    `metal_remainder` INT UNSIGNED NOT NULL DEFAULT 0,
    KEY `idx_city_tributary_sovereign` (`id_sovereign_city`),
    CONSTRAINT `fk_city_tributary_city`
        FOREIGN KEY (`id_city`) REFERENCES `city` (`id_city`) ON DELETE CASCADE,
    CONSTRAINT `fk_city_tributary_sovereign_city`
        FOREIGN KEY (`id_sovereign_city`) REFERENCES `city` (`id_city`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `city_tributary_balance` (
    `id_sovereign_city` INT NOT NULL PRIMARY KEY,
    `food` BIGINT NOT NULL DEFAULT 0,
    `wood` BIGINT NOT NULL DEFAULT 0,
    `stone` BIGINT NOT NULL DEFAULT 0,
    `metal` BIGINT NOT NULL DEFAULT 0,
    CONSTRAINT `fk_city_tributary_balance_city`
        FOREIGN KEY (`id_sovereign_city`) REFERENCES `city` (`id_city`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
