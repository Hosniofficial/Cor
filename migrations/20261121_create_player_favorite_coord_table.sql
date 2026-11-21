-- 20261121_create_player_favorite_coord_table.sql
-- Creates the player_favorite_coord table for saving/bookmarking important map coordinates.

CREATE TABLE IF NOT EXISTS `player_favorite_coord` (
    `id_fav` INT AUTO_INCREMENT PRIMARY KEY,
    `id_player` INT NOT NULL,
    `title` VARCHAR(50) NOT NULL,
    `x` INT NOT NULL,
    `y` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_player_fav` (`id_player`),
    UNIQUE KEY `uq_player_coord` (`id_player`, `x`, `y`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
