CREATE TABLE IF NOT EXISTS `god_gate` (
    `id_player` INT NOT NULL,
    `gate` TINYINT NOT NULL,
    `score` INT NOT NULL DEFAULT 0,
    `cell_1_type` VARCHAR(20) NOT NULL,
    `cell_1_score` INT NOT NULL DEFAULT 1,
    `c_1_s` TINYINT NOT NULL DEFAULT 1,
    `cell_2_type` VARCHAR(20) NOT NULL,
    `cell_2_score` INT NOT NULL DEFAULT 1,
    `c_2_s` TINYINT NOT NULL DEFAULT 1,
    `cell_3_type` VARCHAR(20) NOT NULL,
    `cell_3_score` INT NOT NULL DEFAULT 1,
    `c_3_s` TINYINT NOT NULL DEFAULT 1,
    `cell_4_type` VARCHAR(20) NOT NULL,
    `cell_4_score` INT NOT NULL DEFAULT 1,
    `c_4_s` TINYINT NOT NULL DEFAULT 2,
    PRIMARY KEY (`id_player`, `gate`),
    CONSTRAINT `fk_god_gate_player_cells`
        FOREIGN KEY (`id_player`) REFERENCES `player` (`id_player`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
