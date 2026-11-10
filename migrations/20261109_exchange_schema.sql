-- Persist daily per-player and configured server-wide exchange limits.
-- Safe to apply repeatedly. Back up the target database before applying.

CREATE TABLE IF NOT EXISTS `player_exchange_usage` (
    `id_player` INT NOT NULL,
    `id_exchange` INT NOT NULL,
    `usage_date` DATE NOT NULL,
    `trade_count` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id_player`, `id_exchange`, `usage_date`),
    KEY `idx_player_exchange_usage_date` (`usage_date`, `id_exchange`),
    CONSTRAINT `fk_player_exchange_usage_player`
        FOREIGN KEY (`id_player`) REFERENCES `player` (`id_player`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `exchange_server_usage` (
    `id_exchange` INT NOT NULL PRIMARY KEY,
    `trade_count` INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
