-- Elkaisar Database Schema
-- Select the target database in the mysql command instead of hard-coding it here.

-- 1. Players
CREATE TABLE IF NOT EXISTS `player` (
    `id_player` INT AUTO_INCREMENT PRIMARY KEY,
    `id_user` INT NOT NULL DEFAULT 1,
    `name` VARCHAR(50) NOT NULL,
    `prestige` INT NOT NULL DEFAULT 0,
    `honor` INT NOT NULL DEFAULT 0,
    `gold` INT NOT NULL DEFAULT 50,
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

-- 1a. Account credentials and optional unique usernames (legacy accounts may not have one).
CREATE TABLE IF NOT EXISTS `user_auth` (
    `id_user` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) DEFAULT NULL,
    `email` VARCHAR(100) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `id_player` INT NOT NULL,
    `last_server_id` INT DEFAULT NULL,
    `last_server_entered_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_user_auth_username` (`username`),
    UNIQUE KEY `uq_user_auth_email` (`email`),
    KEY `idx_user_auth_player` (`id_player`)
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

-- 3. Guilds, memberships, invitations, and relations
CREATE TABLE IF NOT EXISTS `guild` (
    `id_guild` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL UNIQUE,
    `id_leader` INT NOT NULL,
    `leader_name` VARCHAR(50) NOT NULL,
    `lvl` INT NOT NULL DEFAULT 1,
    `prestige` BIGINT NOT NULL DEFAULT 0,
    `honor` BIGINT NOT NULL DEFAULT 0,
    `mem_num` INT NOT NULL DEFAULT 1,
    `mem_max` INT NOT NULL DEFAULT 50,
    `slog_top` VARCHAR(100) NOT NULL DEFAULT '1',
    `slog_cnt` VARCHAR(100) NOT NULL DEFAULT '1',
    `slog_btm` VARCHAR(100) NOT NULL DEFAULT '1',
    `word` VARCHAR(255) NOT NULL DEFAULT '',
    `food` BIGINT NOT NULL DEFAULT 0,
    `wood` BIGINT NOT NULL DEFAULT 0,
    `stone` BIGINT NOT NULL DEFAULT 0,
    `metal` BIGINT NOT NULL DEFAULT 0,
    `coin` BIGINT NOT NULL DEFAULT 0,
    `total_prize_share` INT NOT NULL DEFAULT 10,
    KEY `idx_guild_prestige` (`prestige`),
    CONSTRAINT `fk_guild_leader` FOREIGN KEY (`id_leader`) REFERENCES `player` (`id_player`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `guild_member` (
    `id_guild` INT NOT NULL,
    `id_player` INT NOT NULL,
    `rank` INT NOT NULL DEFAULT 0,
    `prize_share` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id_guild`, `id_player`),
    UNIQUE KEY `uq_guild_member_player` (`id_player`),
    CONSTRAINT `fk_guild_member_guild` FOREIGN KEY (`id_guild`) REFERENCES `guild` (`id_guild`) ON DELETE CASCADE,
    CONSTRAINT `fk_guild_member_player` FOREIGN KEY (`id_player`) REFERENCES `player` (`id_player`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `guild_req` (
    `id_guild` INT NOT NULL,
    `id_player` INT NOT NULL,
    `type` VARCHAR(10) NOT NULL DEFAULT 'req',
    `time` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id_guild`, `id_player`),
    KEY `idx_guild_req_player_type` (`id_player`, `type`),
    CONSTRAINT `fk_guild_req_guild` FOREIGN KEY (`id_guild`) REFERENCES `guild` (`id_guild`) ON DELETE CASCADE,
    CONSTRAINT `fk_guild_req_player` FOREIGN KEY (`id_player`) REFERENCES `player` (`id_player`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `guild_allay` (
    `id_guild_1` INT NOT NULL,
    `id_guild_2` INT NOT NULL,
    `state` INT NOT NULL DEFAULT 2,
    `time` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id_guild_1`, `id_guild_2`),
    CONSTRAINT `fk_guild_allay_1` FOREIGN KEY (`id_guild_1`) REFERENCES `guild` (`id_guild`) ON DELETE CASCADE,
    CONSTRAINT `fk_guild_allay_2` FOREIGN KEY (`id_guild_2`) REFERENCES `guild` (`id_guild`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. City Table
CREATE TABLE IF NOT EXISTS `city` (
    `id_city` INT AUTO_INCREMENT PRIMARY KEY,
    `id_player` INT NOT NULL,
    `x` INT NOT NULL DEFAULT 0,
    `y` INT NOT NULL DEFAULT 0,
    `name` VARCHAR(50) NOT NULL DEFAULT 'العاصمة 1',
    `flag` VARCHAR(20) NOT NULL DEFAULT '',
    `flag_expires_at` INT UNSIGNED NOT NULL DEFAULT 0,
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
    `coin` BIGINT NOT NULL DEFAULT 100000,
    `food` BIGINT NOT NULL DEFAULT 50000,
    `wood` BIGINT NOT NULL DEFAULT 50000,
    `stone` BIGINT NOT NULL DEFAULT 50000,
    `metal` BIGINT NOT NULL DEFAULT 50000,
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

-- 5. Market offers and city resource transports
CREATE TABLE IF NOT EXISTS `market_offer` (
    `id_offer` INT AUTO_INCREMENT PRIMARY KEY,
    `id_player` INT NOT NULL,
    `id_city` INT NOT NULL,
    `offer_type` VARCHAR(10) NOT NULL,
    `res_type` VARCHAR(20) NOT NULL,
    `amount` BIGINT NOT NULL,
    `price_per_unit` DECIMAL(10,4) NOT NULL,
    `done` BIGINT NOT NULL DEFAULT 0,
    `status` VARCHAR(20) NOT NULL DEFAULT 'active',
    `created_at` INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `city_market_trans` (
    `id_trans` INT AUTO_INCREMENT PRIMARY KEY,
    `id_player` INT NOT NULL,
    `id_city_from` INT NOT NULL,
    `id_city_to` INT NOT NULL,
    `resource` VARCHAR(20) NOT NULL,
    `amount` BIGINT NOT NULL,
    `unit_price` DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
    `time_start` INT NOT NULL,
    `time_arrive` INT NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'marching'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. City Buildings
CREATE TABLE IF NOT EXISTS `city_building` (
    `id_city` INT NOT NULL,
    `place` VARCHAR(40) NOT NULL,
    `type` INT NOT NULL DEFAULT 0,
    `lvl` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id_city`, `place`),
    FOREIGN KEY (`id_city`) REFERENCES `city`(`id_city`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Player Education (Researches)
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

-- 8. City Heroes
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

-- 9. Player Items
CREATE TABLE IF NOT EXISTS `player_item` (
    `id_player` INT NOT NULL,
    `id_item` VARCHAR(60) NOT NULL,
    `amount` INT NOT NULL DEFAULT 0,
    `bound` VARCHAR(5) NOT NULL DEFAULT 'T',
    PRIMARY KEY (`id_player`, `id_item`),
    FOREIGN KEY (`id_player`) REFERENCES `player`(`id_player`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `player_exchange_usage` (
    `id_player` INT NOT NULL,
    `id_exchange` INT NOT NULL,
    `usage_date` DATE NOT NULL,
    `trade_count` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id_player`, `id_exchange`, `usage_date`),
    KEY `idx_player_exchange_usage_date` (`usage_date`, `id_exchange`),
    FOREIGN KEY (`id_player`) REFERENCES `player`(`id_player`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `exchange_server_usage` (
    `id_exchange` INT NOT NULL PRIMARY KEY,
    `trade_count` INT NOT NULL DEFAULT 0
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

-- 10. Player Quests
CREATE TABLE IF NOT EXISTS `player_quest` (
    `id_player` INT NOT NULL,
    `id_quest` VARCHAR(60) NOT NULL,
    `done` INT NOT NULL DEFAULT 0,
    `time_done` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id_player`, `id_quest`),
    FOREIGN KEY (`id_player`) REFERENCES `player`(`id_player`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
    `time_back` INT NOT NULL DEFAULT 0,
    `state` VARCHAR(32) NOT NULL DEFAULT 'marching',
    KEY `idx_world_battel_return` (`state`, `time_back`),
    KEY `idx_world_battel_player_return` (`id_player`, `state`, `time_back`),
    KEY `idx_world_battel_hero_return` (`id_hero`, `state`, `time_back`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `god_gate_player` (
    `id_player` INT NOT NULL PRIMARY KEY,
    `gate_1` INT NOT NULL DEFAULT 0,
    `gate_2` INT NOT NULL DEFAULT 0,
    `gate_3` INT NOT NULL DEFAULT 0,
    `gate_4` INT NOT NULL DEFAULT 0,
    `points` INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- 11. Timed Tasks (Building upgrades, training, etc.)
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

-- Local message, report, and notification data used by the web client
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
    `detail` LONGTEXT,
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
    `spy_for` VARCHAR(50) NOT NULL DEFAULT 'city',
    `content` LONGTEXT,
    KEY `idx_spy_report_player_seen` (`id_player`, `seen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Runtime and administration tables required by the current application code.
CREATE TABLE IF NOT EXISTS `admin_user` (
    `id_admin` INT NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `role` ENUM('super_admin', 'admin', 'supervisor', 'moderator') NOT NULL DEFAULT 'moderator',
    `permissions` TEXT DEFAULT NULL,
    `status` TINYINT(1) NOT NULL DEFAULT 1,
    `last_login` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_admin`),
    UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `admin_log` (
    `id_log` INT NOT NULL AUTO_INCREMENT,
    `id_admin` INT NOT NULL,
    `admin_name` VARCHAR(100) NOT NULL,
    `action` VARCHAR(50) NOT NULL,
    `target_type` VARCHAR(50) NOT NULL,
    `target_id` INT DEFAULT NULL,
    `target_name` VARCHAR(100) DEFAULT NULL,
    `details` TEXT DEFAULT NULL,
    `ip_address` VARCHAR(50) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_log`),
    KEY `idx_admin` (`id_admin`),
    KEY `idx_target` (`target_type`, `target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `auth_rate_limit` (
    `scope` VARCHAR(20) NOT NULL,
    `key_hash` CHAR(64) NOT NULL,
    `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `window_started_at` DATETIME NOT NULL,
    `locked_until` DATETIME DEFAULT NULL,
    PRIMARY KEY (`scope`, `key_hash`),
    KEY `idx_auth_rate_limit_expiry` (`locked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `arena_player` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `id_player` INT NOT NULL,
    `rank` INT NOT NULL DEFAULT 0,
    `points` INT NOT NULL DEFAULT 0,
    `attempts` INT NOT NULL DEFAULT 10,
    `wins` INT NOT NULL DEFAULT 0,
    `losses` INT NOT NULL DEFAULT 0,
    `hero_list` TEXT DEFAULT NULL,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `idx_player` (`id_player`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `arena_history` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `attacker_id` INT NOT NULL,
    `defender_id` INT NOT NULL,
    `winner_id` INT NOT NULL,
    `atk_pts` INT NOT NULL DEFAULT 0,
    `def_pts` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_attacker` (`attacker_id`),
    KEY `idx_defender` (`defender_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `army_training` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `id_city` INT NOT NULL,
    `id_player` INT NOT NULL,
    `army_type` VARCHAR(30) NOT NULL,
    `amount` INT NOT NULL DEFAULT 0,
    `start_time` INT NOT NULL DEFAULT 0,
    `end_time` INT NOT NULL DEFAULT 0,
    `done` TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_city` (`id_city`),
    KEY `idx_done` (`done`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `battel_log` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `id_world_battel` INT DEFAULT NULL,
    `attacker_id` INT NOT NULL,
    `defender_id` INT NOT NULL,
    `winner` VARCHAR(20) NOT NULL DEFAULT 'attacker',
    `atk_losses` INT NOT NULL DEFAULT 0,
    `def_losses` INT NOT NULL DEFAULT 0,
    `loot_food` INT NOT NULL DEFAULT 0,
    `loot_wood` INT NOT NULL DEFAULT 0,
    `loot_stone` INT NOT NULL DEFAULT 0,
    `loot_metal` INT NOT NULL DEFAULT 0,
    `loot_coin` INT NOT NULL DEFAULT 0,
    `honor_gained` INT NOT NULL DEFAULT 0,
    `detail` LONGTEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_attacker` (`attacker_id`),
    KEY `idx_defender` (`defender_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `city_theater` (
    `id_city` INT NOT NULL,
    `last_update` INT NOT NULL DEFAULT 0,
    `refresh_time` INT NOT NULL DEFAULT 3600,
    `heroes` TEXT NOT NULL,
    PRIMARY KEY (`id_city`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hero_medal` (
    `id_hero` INT NOT NULL,
    `id_player` INT NOT NULL,
    `medal_ceasro` INT NOT NULL DEFAULT 0,
    `medal_den` INT NOT NULL DEFAULT 0,
    `medal_leo` INT NOT NULL DEFAULT 0,
    `ceaser_eagle` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id_hero`),
    KEY `idx_player` (`id_player`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `player_resource_slot` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `id_player` INT NOT NULL,
    `slot_type` VARCHAR(30) NOT NULL,
    `amount` INT NOT NULL DEFAULT 0,
    `expires_at` INT DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_player` (`id_player`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
