-- Add tables used by the game API but absent from the compact schema.
-- Select the intended database and take a backup before applying.

CREATE TABLE IF NOT EXISTS `city_barray` (
    `id_barray` INT AUTO_INCREMENT PRIMARY KEY,
    `id_city` INT NOT NULL,
    `id_player` INT NOT NULL,
    `x_coord` INT NOT NULL,
    `y_coord` INT NOT NULL,
    `Type` INT NOT NULL,
    `lvl` INT NOT NULL DEFAULT 1,
    UNIQUE KEY `uq_city_barray_coord` (`x_coord`, `y_coord`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `city_jop` (
    `id_city` INT NOT NULL PRIMARY KEY,
    `id_player` INT NOT NULL,
    `food` INT NOT NULL DEFAULT 10,
    `wood` INT NOT NULL DEFAULT 10,
    `stone` INT NOT NULL DEFAULT 10,
    `metal` INT NOT NULL DEFAULT 10,
    `food_rate` INT NOT NULL DEFAULT 100,
    `wood_rate` INT NOT NULL DEFAULT 100,
    `stone_rate` INT NOT NULL DEFAULT 100,
    `metal_rate` INT NOT NULL DEFAULT 100
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hero_army` (
    `id_hero` INT NOT NULL PRIMARY KEY,
    `id_player` INT NOT NULL,
    `f_1_type` INT NOT NULL DEFAULT 0,
    `f_1_num` INT NOT NULL DEFAULT 0,
    `f_2_type` INT NOT NULL DEFAULT 0,
    `f_2_num` INT NOT NULL DEFAULT 0,
    `f_3_type` INT NOT NULL DEFAULT 0,
    `f_3_num` INT NOT NULL DEFAULT 0,
    `b_1_type` INT NOT NULL DEFAULT 0,
    `b_1_num` INT NOT NULL DEFAULT 0,
    `b_2_type` INT NOT NULL DEFAULT 0,
    `b_2_num` INT NOT NULL DEFAULT 0,
    `b_3_type` INT NOT NULL DEFAULT 0,
    `b_3_num` INT NOT NULL DEFAULT 0,
    `is_trans` VARCHAR(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `player_equip` (
    `id_equip` INT AUTO_INCREMENT PRIMARY KEY,
    `id_player` INT NOT NULL,
    `id_hero` INT DEFAULT 0,
    `type` VARCHAR(50) NOT NULL,
    `part` VARCHAR(50) NOT NULL,
    `lvl` INT DEFAULT 1,
    `on_hero` TINYINT(4) DEFAULT 0,
    KEY `idx_player_equip_player` (`id_player`),
    KEY `idx_player_equip_hero` (`id_hero`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `world_battel` (
    `id_battel` INT AUTO_INCREMENT PRIMARY KEY,
    `id_player` INT NOT NULL,
    `id_city` INT NOT NULL,
    `id_hero` INT NOT NULL,
    `task` INT NOT NULL DEFAULT 0,
    `x_city` INT NOT NULL DEFAULT 0,
    `y_city` INT NOT NULL DEFAULT 0,
    `x_coord` INT NOT NULL DEFAULT 0,
    `y_coord` INT NOT NULL DEFAULT 0,
    `time_start` INT NOT NULL DEFAULT 0,
    `time_arrive` INT NOT NULL DEFAULT 0,
    `state` VARCHAR(32) NOT NULL DEFAULT 'marching'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `god_gate_player` (
    `id_player` INT NOT NULL PRIMARY KEY,
    `gate_1` INT NOT NULL DEFAULT 0,
    `gate_2` INT NOT NULL DEFAULT 0,
    `gate_3` INT NOT NULL DEFAULT 0,
    `gate_4` INT NOT NULL DEFAULT 0,
    `points` INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `city_wounded` (
    `id_city` INT NOT NULL PRIMARY KEY,
    `army_a` INT NOT NULL DEFAULT 0,
    `army_b` INT NOT NULL DEFAULT 0,
    `army_c` INT NOT NULL DEFAULT 0,
    `army_d` INT NOT NULL DEFAULT 0,
    `army_e` INT NOT NULL DEFAULT 0,
    `army_f` INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `city_storage` (
    `id_city` INT NOT NULL PRIMARY KEY,
    `food_perc` INT NOT NULL DEFAULT 25,
    `wood_perc` INT NOT NULL DEFAULT 25,
    `stone_perc` INT NOT NULL DEFAULT 25,
    `metal_perc` INT NOT NULL DEFAULT 25
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `msg_income` (
    `id_msg` INT AUTO_INCREMENT PRIMARY KEY,
    `id_player` INT NOT NULL,
    `from_` INT NOT NULL DEFAULT 0,
    `from_name` VARCHAR(50) NOT NULL DEFAULT '',
    `subject` VARCHAR(255) NOT NULL DEFAULT '',
    `body` MEDIUMTEXT NOT NULL,
    `seen` TINYINT(1) NOT NULL DEFAULT 0,
    `time_stamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_msg_income_player_seen` (`id_player`, `seen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `msg_out` (
    `id_msg` INT AUTO_INCREMENT PRIMARY KEY,
    `id_player` INT NOT NULL,
    `to_player` INT NOT NULL DEFAULT 0,
    `to_name` VARCHAR(50) NOT NULL DEFAULT '',
    `subject` VARCHAR(255) NOT NULL DEFAULT '',
    `body` MEDIUMTEXT NOT NULL,
    `time_stamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_msg_out_player` (`id_player`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `msg_diff` (
    `id_msg` INT AUTO_INCREMENT PRIMARY KEY,
    `id_player` INT NOT NULL,
    `subject` VARCHAR(255) NOT NULL DEFAULT '',
    `body` MEDIUMTEXT NOT NULL,
    `seen` TINYINT(1) NOT NULL DEFAULT 0,
    `time_stamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_msg_diff_player_seen` (`id_player`, `seen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `report_player` (
    `id_report` INT AUTO_INCREMENT PRIMARY KEY,
    `id_player` INT NOT NULL,
    `seen` TINYINT(1) NOT NULL DEFAULT 0,
    `x` INT NOT NULL DEFAULT 0,
    `y` INT NOT NULL DEFAULT 0,
    `time_stamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `type` INT NOT NULL DEFAULT 0,
    `t` INT NOT NULL DEFAULT 0,
    `lvl` INT NOT NULL DEFAULT 0,
    `detail` MEDIUMTEXT,
    KEY `idx_report_player_seen` (`id_player`, `seen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `spy_report` (
    `id_report` INT AUTO_INCREMENT PRIMARY KEY,
    `id_player` INT NOT NULL,
    `seen` TINYINT(1) NOT NULL DEFAULT 0,
    `victim` INT NOT NULL DEFAULT 0,
    `x` INT NOT NULL DEFAULT 0,
    `y` INT NOT NULL DEFAULT 0,
    `time_stamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `type` INT NOT NULL DEFAULT 0,
    `t` INT NOT NULL DEFAULT 0,
    `l` INT NOT NULL DEFAULT 0,
    `spy_for` INT NOT NULL DEFAULT 0,
    `content` MEDIUMTEXT,
    KEY `idx_spy_report_player_seen` (`id_player`, `seen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @has_player_quest_time_done = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'player_quest'
      AND COLUMN_NAME = 'time_done'
);
SET @player_quest_migration = IF(
    @has_player_quest_time_done = 0,
    'ALTER TABLE `player_quest` ADD COLUMN `time_done` INT NOT NULL DEFAULT 0',
    'SELECT 1'
);
PREPARE player_quest_migration_stmt FROM @player_quest_migration;
EXECUTE player_quest_migration_stmt;
DEALLOCATE PREPARE player_quest_migration_stmt;
