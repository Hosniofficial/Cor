CREATE TABLE IF NOT EXISTS `city_garrison` (
    `id_hero` INT NOT NULL PRIMARY KEY,
    `id_city` INT NOT NULL,
    `id_player` INT NOT NULL,
    `ord` INT NOT NULL DEFAULT 0,
    `status` VARCHAR(16) NOT NULL DEFAULT 'stationed',
    `time_arrive` INT NOT NULL DEFAULT 0,
    `return_at` INT NOT NULL DEFAULT 0,
    `battle_id` INT DEFAULT NULL,
    KEY `idx_city_garrison_city_order` (`id_city`, `status`, `ord`, `id_hero`),
    KEY `idx_city_garrison_player_status` (`id_player`, `status`, `return_at`),
    KEY `idx_city_garrison_battle` (`battle_id`, `status`),
    CONSTRAINT `fk_city_garrison_city`
        FOREIGN KEY (`id_city`) REFERENCES `city` (`id_city`) ON DELETE CASCADE,
    CONSTRAINT `fk_city_garrison_hero`
        FOREIGN KEY (`id_hero`) REFERENCES `city_hero` (`id_hero`) ON DELETE CASCADE,
    CONSTRAINT `fk_city_garrison_player`
        FOREIGN KEY (`id_player`) REFERENCES `player` (`id_player`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `city_garrison` (`id_hero`, `id_city`, `id_player`, `ord`, `status`, `time_arrive`)
SELECT `id_hero`, `id_city`, `id_player`, `ord`, 'stationed', UNIX_TIMESTAMP()
FROM `city_hero`
WHERE `console` = 0 AND `in_city` = 1;
