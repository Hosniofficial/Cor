-- Add runtime and administration tables required by the current application code.
-- Safe to apply repeatedly. This migration creates schema only; it inserts no rows.

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
