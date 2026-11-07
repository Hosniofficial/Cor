-- Elkaisar Database Schema
USE elkaisar_db;

-- 1. Players
CREATE TABLE IF NOT EXISTS `player` (
    `id_player` INT AUTO_INCREMENT PRIMARY KEY,
    `id_user` INT NOT NULL DEFAULT 1,
    `name` VARCHAR(50) NOT NULL,
    `prestige` INT NOT NULL DEFAULT 0,
    `honor` INT NOT NULL DEFAULT 0,
    `gold` INT NOT NULL DEFAULT 100,
    `avatar` INT NOT NULL DEFAULT 1,
    `guild` VARCHAR(50) DEFAULT NULL,
    `id_guild` INT DEFAULT NULL,
    `lang` VARCHAR(10) NOT NULL DEFAULT 'ar',
    `city_flag` VARCHAR(10) NOT NULL DEFAULT '0',
    `porm` INT NOT NULL DEFAULT 0,
    `chat_panne` INT NOT NULL DEFAULT 0,
    `online` TINYINT(1) NOT NULL DEFAULT 1,
    `last_seen` INT NOT NULL DEFAULT 0,
    `panned` INT NOT NULL DEFAULT 0,
    `p_token` VARCHAR(255) NOT NULL DEFAULT '',
    `rank` INT NOT NULL DEFAULT 1,
    `user_group` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Player State (buffs, peace, protection, etc.)
CREATE TABLE IF NOT EXISTS `player_state` (
    `id_player` INT PRIMARY KEY,
    `motiv` INT NOT NULL DEFAULT 0,
    `prot_pop` INT NOT NULL DEFAULT 0,
    `peace` INT NOT NULL DEFAULT 0,
    `medical` INT NOT NULL DEFAULT 0,
    `wheat` INT NOT NULL DEFAULT 0,
    `stone` INT NOT NULL DEFAULT 0,
    `metal` INT NOT NULL DEFAULT 0,
    `coin` INT NOT NULL DEFAULT 0,
    `wood` INT NOT NULL DEFAULT 0,
    `attack_10` INT NOT NULL DEFAULT 0,
    `defence_10` INT NOT NULL DEFAULT 0,
    FOREIGN KEY (`id_player`) REFERENCES `player`(`id_player`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. City Table
CREATE TABLE IF NOT EXISTS `city` (
    `id_city` INT AUTO_INCREMENT PRIMARY KEY,
    `id_player` INT NOT NULL,
    `x` INT NOT NULL DEFAULT 0,
    `y` INT NOT NULL DEFAULT 0,
    `name` VARCHAR(50) NOT NULL DEFAULT 'المدينة 1',
    `flag` VARCHAR(20) NOT NULL DEFAULT '',
    `lvl` INT NOT NULL DEFAULT 0,
    `console` INT DEFAULT NULL,
    `taxs` INT NOT NULL DEFAULT 10,
    `loy` INT NOT NULL DEFAULT 90,
    `loy_max` INT NOT NULL DEFAULT 90,
    `loy_lss` INT NOT NULL DEFAULT 0,
    `dis_loy` INT NOT NULL DEFAULT 0,
    `helper` INT NOT NULL DEFAULT 0,
    `pop_cap` INT NOT NULL DEFAULT 1000,
    `pop_max` INT NOT NULL DEFAULT 900,
    `pop` INT NOT NULL DEFAULT 900,
    `pop_LSS` INT NOT NULL DEFAULT 0,
    `pop_state` INT NOT NULL DEFAULT 0,
    `coin` INT NOT NULL DEFAULT 100000,
    `food` INT NOT NULL DEFAULT 50000,
    `wood` INT NOT NULL DEFAULT 50000,
    `stone` INT NOT NULL DEFAULT 50000,
    `metal` INT NOT NULL DEFAULT 50000,
    `coin_in` INT NOT NULL DEFAULT 100,
    `food_in` INT NOT NULL DEFAULT 100,
    `wood_in` INT NOT NULL DEFAULT 100,
    `stone_in` INT NOT NULL DEFAULT 100,
    `metal_in` INT NOT NULL DEFAULT 100,
    `coin_out` INT NOT NULL DEFAULT 0,
    `food_out` INT NOT NULL DEFAULT 0,
    `wood_out` INT NOT NULL DEFAULT 0,
    `stone_out` INT NOT NULL DEFAULT 0,
    `metal_out` INT NOT NULL DEFAULT 0,
    `food_cap` INT NOT NULL DEFAULT 100000,
    `wood_cap` INT NOT NULL DEFAULT 100000,
    `stone_cap` INT NOT NULL DEFAULT 100000,
    `metal_cap` INT NOT NULL DEFAULT 100000,
    `coin_cap` INT NOT NULL DEFAULT 1000000,
    `army_a` INT NOT NULL DEFAULT 0,
    `army_b` INT NOT NULL DEFAULT 0,
    `army_c` INT NOT NULL DEFAULT 0,
    `army_d` INT NOT NULL DEFAULT 0,
    `army_e` INT NOT NULL DEFAULT 0,
    `army_f` INT NOT NULL DEFAULT 0,
    `wall_a` INT NOT NULL DEFAULT 0,
    `wall_b` INT NOT NULL DEFAULT 0,
    `wall_c` INT NOT NULL DEFAULT 0,
    `wall_cap` INT NOT NULL DEFAULT 5000,
    `spies` INT NOT NULL DEFAULT 0,
    `LS` INT NOT NULL DEFAULT 0,
    FOREIGN KEY (`id_player`) REFERENCES `player`(`id_player`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. City Buildings
CREATE TABLE IF NOT EXISTS `city_building` (
    `id_city` INT NOT NULL,
    `place` VARCHAR(40) NOT NULL,
    `type` INT NOT NULL DEFAULT 0,
    `lvl` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id_city`, `place`),
    FOREIGN KEY (`id_city`) REFERENCES `city`(`id_city`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Player Education (Researches)
CREATE TABLE IF NOT EXISTS `player_edu` (
    `id_player` INT PRIMARY KEY,
    `farming` INT NOT NULL DEFAULT 0,
    `wooding` INT NOT NULL DEFAULT 0,
    `stoning` INT NOT NULL DEFAULT 0,
    `mining` INT NOT NULL DEFAULT 0,
    `accounting` INT NOT NULL DEFAULT 0,
    `storing` INT NOT NULL DEFAULT 0,
    `building` INT NOT NULL DEFAULT 0,
    `scholership` INT NOT NULL DEFAULT 0,
    `maintenace` INT NOT NULL DEFAULT 0,
    `infantry` INT NOT NULL DEFAULT 0,
    `riding` INT NOT NULL DEFAULT 0,
    `army` INT NOT NULL DEFAULT 0,
    `spying` INT NOT NULL DEFAULT 0,
    `leader` INT NOT NULL DEFAULT 0,
    `safe` INT NOT NULL DEFAULT 0,
    `medicine` INT NOT NULL DEFAULT 0,
    `logistic` INT NOT NULL DEFAULT 0,
    `navigating` INT NOT NULL DEFAULT 0,
    `supplying` INT NOT NULL DEFAULT 0,
    FOREIGN KEY (`id_player`) REFERENCES `player`(`id_player`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. City Heroes
CREATE TABLE IF NOT EXISTS `city_hero` (
    `id_hero` INT AUTO_INCREMENT PRIMARY KEY,
    `id_city` INT NOT NULL,
    `id_player` INT NOT NULL,
    `name` VARCHAR(50) NOT NULL,
    `lvl` INT NOT NULL DEFAULT 1,
    `ord` INT NOT NULL DEFAULT 0,
    `ultra_p` INT NOT NULL DEFAULT 1,
    `exp` INT NOT NULL DEFAULT 0,
    `loyal` INT NOT NULL DEFAULT 70,
    `power` INT NOT NULL DEFAULT 50,
    `power_max` INT NOT NULL DEFAULT 50,
    `avatar` INT NOT NULL DEFAULT 1,
    `in_city` INT NOT NULL DEFAULT 1,
    `b_lvl` INT NOT NULL DEFAULT 1,
    `p_b_a` INT NOT NULL DEFAULT 10,
    `p_b_b` INT NOT NULL DEFAULT 10,
    `p_b_c` INT NOT NULL DEFAULT 10,
    `points` INT NOT NULL DEFAULT 0,
    `point_a` INT NOT NULL DEFAULT 10,
    `point_b` INT NOT NULL DEFAULT 10,
    `point_c` INT NOT NULL DEFAULT 10,
    `point_a_plus` INT NOT NULL DEFAULT 0,
    `point_b_plus` INT NOT NULL DEFAULT 0,
    `point_c_plus` INT NOT NULL DEFAULT 0,
    `console` INT NOT NULL DEFAULT 0,
    `cap` INT NOT NULL DEFAULT 10000,
    `power_ls` INT NOT NULL DEFAULT 0,
    FOREIGN KEY (`id_city`) REFERENCES `city`(`id_city`) ON DELETE CASCADE,
    FOREIGN KEY (`id_player`) REFERENCES `player`(`id_player`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Player Items
CREATE TABLE IF NOT EXISTS `player_item` (
    `id_player` INT NOT NULL,
    `id_item` VARCHAR(60) NOT NULL,
    `amount` INT NOT NULL DEFAULT 0,
    `bound` VARCHAR(5) NOT NULL DEFAULT 'T',
    PRIMARY KEY (`id_player`, `id_item`),
    FOREIGN KEY (`id_player`) REFERENCES `player`(`id_player`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Player Quests
CREATE TABLE IF NOT EXISTS `player_quest` (
    `id_player` INT NOT NULL,
    `id_quest` VARCHAR(60) NOT NULL,
    `done` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id_player`, `id_quest`),
    FOREIGN KEY (`id_player`) REFERENCES `player`(`id_player`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Timed Tasks (Building upgrades, training, etc.)
CREATE TABLE IF NOT EXISTS `timed_task` (
    `id_task` INT AUTO_INCREMENT PRIMARY KEY,
    `id_city` INT NOT NULL,
    `id_player` INT NOT NULL,
    `task_type` VARCHAR(20) NOT NULL, -- 'building', 'army', 'study', 'jop'
    `item_id` VARCHAR(50) NOT NULL,
    `lvl_to` INT NOT NULL DEFAULT 1,
    `start_time` INT NOT NULL,
    `time_arrive` INT NOT NULL,
    `details` TEXT DEFAULT NULL,
    FOREIGN KEY (`id_city`) REFERENCES `city`(`id_city`) ON DELETE CASCADE,
    FOREIGN KEY (`id_player`) REFERENCES `player`(`id_player`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
