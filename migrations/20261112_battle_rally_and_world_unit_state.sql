ALTER TABLE `world_battel`
    ADD COLUMN `return_duration` INT NOT NULL DEFAULT 0 AFTER `time_back`;

UPDATE `world_battel`
SET `return_duration` = GREATEST(1, `time_arrive` - `time_start`)
WHERE `return_duration` = 0;

CREATE TABLE `world_battel_member` (
    `id_member` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `id_battel` INT NOT NULL,
    `id_player` INT NOT NULL,
    `id_city` INT NOT NULL,
    `id_hero` INT NOT NULL,
    `side` TINYINT NOT NULL DEFAULT 1,
    `task` INT NOT NULL DEFAULT 0,
    `x_city` INT NOT NULL DEFAULT 0,
    `y_city` INT NOT NULL DEFAULT 0,
    `time_start` INT NOT NULL DEFAULT 0,
    `time_arrive` INT NOT NULL DEFAULT 0,
    `time_back` INT NOT NULL DEFAULT 0,
    `return_duration` INT NOT NULL DEFAULT 0,
    `state` VARCHAR(32) NOT NULL DEFAULT 'marching',
    UNIQUE KEY `uq_world_battel_member_hero` (`id_battel`, `id_hero`),
    KEY `idx_world_battel_member_arrival` (`id_battel`, `side`, `state`, `time_arrive`),
    KEY `idx_world_battel_member_player` (`id_player`, `state`, `time_arrive`),
    KEY `idx_world_battel_member_return` (`id_player`, `state`, `time_back`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `world_battel_member`
    (`id_battel`, `id_player`, `id_city`, `id_hero`, `side`, `task`, `x_city`, `y_city`,
     `time_start`, `time_arrive`, `time_back`, `return_duration`, `state`)
SELECT `id_battel`, `id_player`, `id_city`, `id_hero`, 1, `task`, `x_city`, `y_city`,
       `time_start`, `time_arrive`, `time_back`, `return_duration`, `state`
FROM `world_battel`;

CREATE TABLE `world_unit_state` (
    `x_coord` SMALLINT NOT NULL,
    `y_coord` SMALLINT NOT NULL,
    `unit_type` SMALLINT NOT NULL,
    `unit_subtype` SMALLINT NOT NULL DEFAULT 0,
    `base_lvl` SMALLINT NOT NULL,
    `current_lvl` SMALLINT NOT NULL,
    `max_lvl` SMALLINT NOT NULL,
    `next_reset_at` INT NOT NULL DEFAULT 0,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`x_coord`, `y_coord`),
    KEY `idx_world_unit_state_reset` (`next_reset_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
