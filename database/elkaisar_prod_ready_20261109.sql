-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: elkaisar_prod_ready_20261109
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `admin_log`
--

DROP TABLE IF EXISTS `admin_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_log` (
  `id_log` int(11) NOT NULL AUTO_INCREMENT,
  `id_admin` int(11) NOT NULL,
  `admin_name` varchar(100) NOT NULL,
  `action` varchar(50) NOT NULL,
  `target_type` varchar(50) NOT NULL,
  `target_id` int(11) DEFAULT NULL,
  `target_name` varchar(100) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_log`),
  KEY `idx_admin` (`id_admin`),
  KEY `idx_target` (`target_type`,`target_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `admin_user`
--

DROP TABLE IF EXISTS `admin_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_user` (
  `id_admin` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `role` enum('super_admin','admin','supervisor','moderator') NOT NULL DEFAULT 'moderator',
  `permissions` text DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_admin`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `arena_history`
--

DROP TABLE IF EXISTS `arena_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `arena_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `attacker_id` int(11) NOT NULL,
  `defender_id` int(11) NOT NULL,
  `winner_id` int(11) NOT NULL,
  `atk_pts` int(11) NOT NULL DEFAULT 0,
  `def_pts` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_attacker` (`attacker_id`),
  KEY `idx_defender` (`defender_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `arena_player`
--

DROP TABLE IF EXISTS `arena_player`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `arena_player` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_player` int(11) NOT NULL,
  `rank` int(11) NOT NULL DEFAULT 0,
  `points` int(11) NOT NULL DEFAULT 0,
  `attempts` int(11) NOT NULL DEFAULT 10,
  `wins` int(11) NOT NULL DEFAULT 0,
  `losses` int(11) NOT NULL DEFAULT 0,
  `hero_list` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_player` (`id_player`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `army_training`
--

DROP TABLE IF EXISTS `army_training`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `army_training` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_city` int(11) NOT NULL,
  `id_player` int(11) NOT NULL,
  `army_type` varchar(30) NOT NULL,
  `amount` int(11) NOT NULL DEFAULT 0,
  `start_time` int(11) NOT NULL DEFAULT 0,
  `end_time` int(11) NOT NULL DEFAULT 0,
  `done` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_city` (`id_city`),
  KEY `idx_done` (`done`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `auth_rate_limit`
--

DROP TABLE IF EXISTS `auth_rate_limit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auth_rate_limit` (
  `scope` varchar(20) NOT NULL,
  `key_hash` char(64) NOT NULL,
  `attempts` int(10) unsigned NOT NULL DEFAULT 0,
  `window_started_at` datetime NOT NULL,
  `locked_until` datetime DEFAULT NULL,
  PRIMARY KEY (`scope`,`key_hash`),
  KEY `idx_auth_rate_limit_expiry` (`locked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `battel_log`
--

DROP TABLE IF EXISTS `battel_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `battel_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_world_battel` int(11) DEFAULT NULL,
  `attacker_id` int(11) NOT NULL,
  `defender_id` int(11) NOT NULL,
  `winner` varchar(20) NOT NULL DEFAULT 'attacker',
  `atk_losses` int(11) NOT NULL DEFAULT 0,
  `def_losses` int(11) NOT NULL DEFAULT 0,
  `loot_food` int(11) NOT NULL DEFAULT 0,
  `loot_wood` int(11) NOT NULL DEFAULT 0,
  `loot_stone` int(11) NOT NULL DEFAULT 0,
  `loot_metal` int(11) NOT NULL DEFAULT 0,
  `loot_coin` int(11) NOT NULL DEFAULT 0,
  `honor_gained` int(11) NOT NULL DEFAULT 0,
  `detail` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_attacker` (`attacker_id`),
  KEY `idx_defender` (`defender_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `city`
--

DROP TABLE IF EXISTS `city`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `city` (
  `id_city` int(11) NOT NULL AUTO_INCREMENT,
  `id_player` int(11) NOT NULL,
  `x` int(11) NOT NULL DEFAULT 0,
  `y` int(11) NOT NULL DEFAULT 0,
  `name` varchar(50) NOT NULL DEFAULT 'العاصمة 1',
  `flag` varchar(20) NOT NULL DEFAULT '',
  `lvl` int(11) NOT NULL DEFAULT 0,
  `console` int(11) DEFAULT NULL,
  `taxs` int(11) NOT NULL DEFAULT 10,
  `loy` int(11) NOT NULL DEFAULT 90,
  `loy_max` int(11) NOT NULL DEFAULT 90,
  `loy_lss` int(11) NOT NULL DEFAULT 0,
  `dis_loy` int(11) NOT NULL DEFAULT 0,
  `helper` int(11) NOT NULL DEFAULT 0,
  `pop_cap` int(11) NOT NULL DEFAULT 1000,
  `pop_max` int(11) NOT NULL DEFAULT 900,
  `pop` int(11) NOT NULL DEFAULT 900,
  `pop_LSS` int(11) NOT NULL DEFAULT 0,
  `pop_state` int(11) NOT NULL DEFAULT 0,
  `coin` int(11) NOT NULL DEFAULT 100000,
  `food` int(11) NOT NULL DEFAULT 50000,
  `wood` int(11) NOT NULL DEFAULT 50000,
  `stone` int(11) NOT NULL DEFAULT 50000,
  `metal` int(11) NOT NULL DEFAULT 50000,
  `coin_in` int(11) NOT NULL DEFAULT 100,
  `food_in` int(11) NOT NULL DEFAULT 100,
  `wood_in` int(11) NOT NULL DEFAULT 100,
  `stone_in` int(11) NOT NULL DEFAULT 100,
  `metal_in` int(11) NOT NULL DEFAULT 100,
  `coin_out` int(11) NOT NULL DEFAULT 0,
  `food_out` int(11) NOT NULL DEFAULT 0,
  `wood_out` int(11) NOT NULL DEFAULT 0,
  `stone_out` int(11) NOT NULL DEFAULT 0,
  `metal_out` int(11) NOT NULL DEFAULT 0,
  `food_cap` int(11) NOT NULL DEFAULT 100000,
  `wood_cap` int(11) NOT NULL DEFAULT 100000,
  `stone_cap` int(11) NOT NULL DEFAULT 100000,
  `metal_cap` int(11) NOT NULL DEFAULT 100000,
  `coin_cap` int(11) NOT NULL DEFAULT 1000000,
  `army_a` int(11) NOT NULL DEFAULT 0,
  `army_b` int(11) NOT NULL DEFAULT 0,
  `army_c` int(11) NOT NULL DEFAULT 0,
  `army_d` int(11) NOT NULL DEFAULT 0,
  `army_e` int(11) NOT NULL DEFAULT 0,
  `army_f` int(11) NOT NULL DEFAULT 0,
  `wall_a` int(11) NOT NULL DEFAULT 0,
  `wall_b` int(11) NOT NULL DEFAULT 0,
  `wall_c` int(11) NOT NULL DEFAULT 0,
  `wall_cap` int(11) NOT NULL DEFAULT 5000,
  `spies` int(11) NOT NULL DEFAULT 0,
  `LS` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_city`),
  KEY `id_player` (`id_player`),
  CONSTRAINT `city_ibfk_1` FOREIGN KEY (`id_player`) REFERENCES `player` (`id_player`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `city_barray`
--

DROP TABLE IF EXISTS `city_barray`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `city_barray` (
  `id_barray` int(11) NOT NULL AUTO_INCREMENT,
  `id_city` int(11) NOT NULL,
  `id_player` int(11) NOT NULL,
  `x_coord` int(11) NOT NULL,
  `y_coord` int(11) NOT NULL,
  `Type` int(11) NOT NULL,
  `lvl` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_barray`),
  UNIQUE KEY `uq_city_barray_coord` (`x_coord`,`y_coord`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `city_building`
--

DROP TABLE IF EXISTS `city_building`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `city_building` (
  `id_city` int(11) NOT NULL,
  `place` varchar(40) NOT NULL,
  `type` int(11) NOT NULL DEFAULT 0,
  `lvl` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_city`,`place`),
  CONSTRAINT `city_building_ibfk_1` FOREIGN KEY (`id_city`) REFERENCES `city` (`id_city`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `city_hero`
--

DROP TABLE IF EXISTS `city_hero`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `city_hero` (
  `id_hero` int(11) NOT NULL AUTO_INCREMENT,
  `id_city` int(11) NOT NULL,
  `id_player` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `lvl` int(11) NOT NULL DEFAULT 1,
  `ord` int(11) NOT NULL DEFAULT 0,
  `ultra_p` int(11) NOT NULL DEFAULT 1,
  `exp` int(11) NOT NULL DEFAULT 0,
  `loyal` int(11) NOT NULL DEFAULT 70,
  `power` int(11) NOT NULL DEFAULT 50,
  `power_max` int(11) NOT NULL DEFAULT 50,
  `avatar` int(11) NOT NULL DEFAULT 1,
  `in_city` int(11) NOT NULL DEFAULT 1,
  `b_lvl` int(11) NOT NULL DEFAULT 1,
  `p_b_a` int(11) NOT NULL DEFAULT 10,
  `p_b_b` int(11) NOT NULL DEFAULT 10,
  `p_b_c` int(11) NOT NULL DEFAULT 10,
  `points` int(11) NOT NULL DEFAULT 0,
  `point_a` int(11) NOT NULL DEFAULT 10,
  `point_b` int(11) NOT NULL DEFAULT 10,
  `point_c` int(11) NOT NULL DEFAULT 10,
  `point_a_plus` int(11) NOT NULL DEFAULT 0,
  `point_b_plus` int(11) NOT NULL DEFAULT 0,
  `point_c_plus` int(11) NOT NULL DEFAULT 0,
  `console` int(11) NOT NULL DEFAULT 0,
  `cap` int(11) NOT NULL DEFAULT 10000,
  `power_ls` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_hero`),
  KEY `id_city` (`id_city`),
  KEY `id_player` (`id_player`),
  CONSTRAINT `city_hero_ibfk_1` FOREIGN KEY (`id_city`) REFERENCES `city` (`id_city`) ON DELETE CASCADE,
  CONSTRAINT `city_hero_ibfk_2` FOREIGN KEY (`id_player`) REFERENCES `player` (`id_player`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `city_jop`
--

DROP TABLE IF EXISTS `city_jop`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `city_jop` (
  `id_city` int(11) NOT NULL,
  `id_player` int(11) NOT NULL,
  `food` int(11) NOT NULL DEFAULT 10,
  `wood` int(11) NOT NULL DEFAULT 10,
  `stone` int(11) NOT NULL DEFAULT 10,
  `metal` int(11) NOT NULL DEFAULT 10,
  `food_rate` int(11) NOT NULL DEFAULT 100,
  `wood_rate` int(11) NOT NULL DEFAULT 100,
  `stone_rate` int(11) NOT NULL DEFAULT 100,
  `metal_rate` int(11) NOT NULL DEFAULT 100,
  PRIMARY KEY (`id_city`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `city_market_trans`
--

DROP TABLE IF EXISTS `city_market_trans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `city_market_trans` (
  `id_trans` int(11) NOT NULL AUTO_INCREMENT,
  `id_player` int(11) NOT NULL,
  `id_city_from` int(11) NOT NULL,
  `id_city_to` int(11) NOT NULL,
  `resource` varchar(20) NOT NULL,
  `amount` int(11) NOT NULL,
  `unit_price` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `time_start` int(11) NOT NULL,
  `time_arrive` int(11) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'marching',
  PRIMARY KEY (`id_trans`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `city_storage`
--

DROP TABLE IF EXISTS `city_storage`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `city_storage` (
  `id_city` int(11) NOT NULL,
  `food_perc` int(11) NOT NULL DEFAULT 25,
  `wood_perc` int(11) NOT NULL DEFAULT 25,
  `stone_perc` int(11) NOT NULL DEFAULT 25,
  `metal_perc` int(11) NOT NULL DEFAULT 25,
  PRIMARY KEY (`id_city`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `city_theater`
--

DROP TABLE IF EXISTS `city_theater`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `city_theater` (
  `id_city` int(11) NOT NULL,
  `last_update` int(11) NOT NULL DEFAULT 0,
  `refresh_time` int(11) NOT NULL DEFAULT 3600,
  `heroes` text NOT NULL,
  PRIMARY KEY (`id_city`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `city_wounded`
--

DROP TABLE IF EXISTS `city_wounded`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `city_wounded` (
  `id_city` int(11) NOT NULL,
  `army_a` int(11) NOT NULL DEFAULT 0,
  `army_b` int(11) NOT NULL DEFAULT 0,
  `army_c` int(11) NOT NULL DEFAULT 0,
  `army_d` int(11) NOT NULL DEFAULT 0,
  `army_e` int(11) NOT NULL DEFAULT 0,
  `army_f` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_city`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `exchange_server_usage`
--

DROP TABLE IF EXISTS `exchange_server_usage`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `exchange_server_usage` (
  `id_exchange` int(11) NOT NULL,
  `trade_count` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_exchange`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `god_gate_player`
--

DROP TABLE IF EXISTS `god_gate_player`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `god_gate_player` (
  `id_player` int(11) NOT NULL,
  `gate_1` int(11) NOT NULL DEFAULT 0,
  `gate_2` int(11) NOT NULL DEFAULT 0,
  `gate_3` int(11) NOT NULL DEFAULT 0,
  `gate_4` int(11) NOT NULL DEFAULT 0,
  `points` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_player`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `guild`
--

DROP TABLE IF EXISTS `guild`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `guild` (
  `id_guild` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `id_leader` int(11) NOT NULL,
  `leader_name` varchar(50) NOT NULL,
  `lvl` int(11) NOT NULL DEFAULT 1,
  `prestige` bigint(20) NOT NULL DEFAULT 0,
  `honor` bigint(20) NOT NULL DEFAULT 0,
  `mem_num` int(11) NOT NULL DEFAULT 1,
  `mem_max` int(11) NOT NULL DEFAULT 50,
  `slog_top` varchar(100) NOT NULL DEFAULT '1',
  `slog_cnt` varchar(100) NOT NULL DEFAULT '1',
  `slog_btm` varchar(100) NOT NULL DEFAULT '1',
  `word` varchar(255) NOT NULL DEFAULT '',
  `food` bigint(20) NOT NULL DEFAULT 0,
  `wood` bigint(20) NOT NULL DEFAULT 0,
  `stone` bigint(20) NOT NULL DEFAULT 0,
  `metal` bigint(20) NOT NULL DEFAULT 0,
  `coin` bigint(20) NOT NULL DEFAULT 0,
  `total_prize_share` int(11) NOT NULL DEFAULT 10,
  PRIMARY KEY (`id_guild`),
  UNIQUE KEY `name` (`name`),
  KEY `idx_guild_prestige` (`prestige`),
  KEY `fk_guild_leader` (`id_leader`),
  CONSTRAINT `fk_guild_leader` FOREIGN KEY (`id_leader`) REFERENCES `player` (`id_player`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `guild_allay`
--

DROP TABLE IF EXISTS `guild_allay`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `guild_allay` (
  `id_guild_1` int(11) NOT NULL,
  `id_guild_2` int(11) NOT NULL,
  `state` int(11) NOT NULL DEFAULT 2,
  `time` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_guild_1`,`id_guild_2`),
  KEY `fk_guild_allay_2` (`id_guild_2`),
  CONSTRAINT `fk_guild_allay_1` FOREIGN KEY (`id_guild_1`) REFERENCES `guild` (`id_guild`) ON DELETE CASCADE,
  CONSTRAINT `fk_guild_allay_2` FOREIGN KEY (`id_guild_2`) REFERENCES `guild` (`id_guild`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `guild_member`
--

DROP TABLE IF EXISTS `guild_member`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `guild_member` (
  `id_guild` int(11) NOT NULL,
  `id_player` int(11) NOT NULL,
  `rank` int(11) NOT NULL DEFAULT 0,
  `prize_share` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_guild`,`id_player`),
  UNIQUE KEY `uq_guild_member_player` (`id_player`),
  CONSTRAINT `fk_guild_member_guild` FOREIGN KEY (`id_guild`) REFERENCES `guild` (`id_guild`) ON DELETE CASCADE,
  CONSTRAINT `fk_guild_member_player` FOREIGN KEY (`id_player`) REFERENCES `player` (`id_player`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `guild_req`
--

DROP TABLE IF EXISTS `guild_req`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `guild_req` (
  `id_guild` int(11) NOT NULL,
  `id_player` int(11) NOT NULL,
  `type` varchar(10) NOT NULL DEFAULT 'req',
  `time` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_guild`,`id_player`),
  KEY `idx_guild_req_player_type` (`id_player`,`type`),
  CONSTRAINT `fk_guild_req_guild` FOREIGN KEY (`id_guild`) REFERENCES `guild` (`id_guild`) ON DELETE CASCADE,
  CONSTRAINT `fk_guild_req_player` FOREIGN KEY (`id_player`) REFERENCES `player` (`id_player`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hero_army`
--

DROP TABLE IF EXISTS `hero_army`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hero_army` (
  `id_hero` int(11) NOT NULL,
  `id_player` int(11) NOT NULL,
  `f_1_type` int(11) NOT NULL DEFAULT 0,
  `f_1_num` int(11) NOT NULL DEFAULT 0,
  `f_2_type` int(11) NOT NULL DEFAULT 0,
  `f_2_num` int(11) NOT NULL DEFAULT 0,
  `f_3_type` int(11) NOT NULL DEFAULT 0,
  `f_3_num` int(11) NOT NULL DEFAULT 0,
  `b_1_type` int(11) NOT NULL DEFAULT 0,
  `b_1_num` int(11) NOT NULL DEFAULT 0,
  `b_2_type` int(11) NOT NULL DEFAULT 0,
  `b_2_num` int(11) NOT NULL DEFAULT 0,
  `b_3_type` int(11) NOT NULL DEFAULT 0,
  `b_3_num` int(11) NOT NULL DEFAULT 0,
  `is_trans` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id_hero`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hero_medal`
--

DROP TABLE IF EXISTS `hero_medal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hero_medal` (
  `id_hero` int(11) NOT NULL,
  `id_player` int(11) NOT NULL,
  `medal_ceasro` int(11) NOT NULL DEFAULT 0,
  `medal_den` int(11) NOT NULL DEFAULT 0,
  `medal_leo` int(11) NOT NULL DEFAULT 0,
  `ceaser_eagle` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_hero`),
  KEY `idx_player` (`id_player`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `market_offer`
--

DROP TABLE IF EXISTS `market_offer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `market_offer` (
  `id_offer` int(11) NOT NULL AUTO_INCREMENT,
  `id_player` int(11) NOT NULL,
  `id_city` int(11) NOT NULL,
  `offer_type` varchar(10) NOT NULL,
  `res_type` varchar(20) NOT NULL,
  `amount` int(11) NOT NULL,
  `price_per_unit` decimal(10,4) NOT NULL,
  `done` int(11) NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_offer`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `msg_diff`
--

DROP TABLE IF EXISTS `msg_diff`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `msg_diff` (
  `id_msg` int(11) NOT NULL AUTO_INCREMENT,
  `id_player` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL DEFAULT '',
  `body` mediumtext NOT NULL,
  `seen` tinyint(1) NOT NULL DEFAULT 0,
  `time_stamp` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_msg`),
  KEY `idx_msg_diff_player_seen` (`id_player`,`seen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `msg_income`
--

DROP TABLE IF EXISTS `msg_income`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `msg_income` (
  `id_msg` int(11) NOT NULL AUTO_INCREMENT,
  `id_player` int(11) NOT NULL,
  `from_` int(11) NOT NULL DEFAULT 0,
  `from_name` varchar(50) NOT NULL DEFAULT '',
  `subject` varchar(255) NOT NULL DEFAULT '',
  `body` mediumtext NOT NULL,
  `seen` tinyint(1) NOT NULL DEFAULT 0,
  `time_stamp` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_msg`),
  KEY `idx_msg_income_player_seen` (`id_player`,`seen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `msg_out`
--

DROP TABLE IF EXISTS `msg_out`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `msg_out` (
  `id_msg` int(11) NOT NULL AUTO_INCREMENT,
  `id_player` int(11) NOT NULL,
  `to_player` int(11) NOT NULL DEFAULT 0,
  `to_name` varchar(50) NOT NULL DEFAULT '',
  `subject` varchar(255) NOT NULL DEFAULT '',
  `body` mediumtext NOT NULL,
  `time_stamp` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_msg`),
  KEY `idx_msg_out_player` (`id_player`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `player`
--

DROP TABLE IF EXISTS `player`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `player` (
  `id_player` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL DEFAULT 1,
  `name` varchar(50) NOT NULL,
  `prestige` int(11) NOT NULL DEFAULT 0,
  `honor` int(11) NOT NULL DEFAULT 0,
  `gold` int(11) NOT NULL DEFAULT 50,
  `avatar` int(11) NOT NULL DEFAULT 1,
  `guild` varchar(50) DEFAULT NULL,
  `id_guild` int(11) DEFAULT NULL,
  `lang` varchar(10) NOT NULL DEFAULT 'ar',
  `city_flag` varchar(10) NOT NULL DEFAULT '0',
  `porm` int(11) NOT NULL DEFAULT 0,
  `chat_panne` int(11) NOT NULL DEFAULT 0,
  `online` tinyint(1) NOT NULL DEFAULT 1,
  `last_seen` int(11) NOT NULL DEFAULT 0,
  `panned` int(11) NOT NULL DEFAULT 0,
  `p_token` varchar(255) NOT NULL DEFAULT '',
  `rank` int(11) NOT NULL DEFAULT 1,
  `user_group` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_player`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `player_edu`
--

DROP TABLE IF EXISTS `player_edu`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `player_edu` (
  `id_player` int(11) NOT NULL,
  `farming` int(11) NOT NULL DEFAULT 0,
  `wooding` int(11) NOT NULL DEFAULT 0,
  `stoning` int(11) NOT NULL DEFAULT 0,
  `mining` int(11) NOT NULL DEFAULT 0,
  `accounting` int(11) NOT NULL DEFAULT 0,
  `storing` int(11) NOT NULL DEFAULT 0,
  `building` int(11) NOT NULL DEFAULT 0,
  `scholership` int(11) NOT NULL DEFAULT 0,
  `maintenace` int(11) NOT NULL DEFAULT 0,
  `infantry` int(11) NOT NULL DEFAULT 0,
  `riding` int(11) NOT NULL DEFAULT 0,
  `army` int(11) NOT NULL DEFAULT 0,
  `spying` int(11) NOT NULL DEFAULT 0,
  `leader` int(11) NOT NULL DEFAULT 0,
  `safe` int(11) NOT NULL DEFAULT 0,
  `medicine` int(11) NOT NULL DEFAULT 0,
  `logistic` int(11) NOT NULL DEFAULT 0,
  `navigating` int(11) NOT NULL DEFAULT 0,
  `supplying` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_player`),
  CONSTRAINT `player_edu_ibfk_1` FOREIGN KEY (`id_player`) REFERENCES `player` (`id_player`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `player_equip`
--

DROP TABLE IF EXISTS `player_equip`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `player_equip` (
  `id_equip` int(11) NOT NULL AUTO_INCREMENT,
  `id_player` int(11) NOT NULL,
  `id_hero` int(11) DEFAULT 0,
  `type` varchar(50) NOT NULL,
  `part` varchar(50) NOT NULL,
  `lvl` int(11) DEFAULT 1,
  `on_hero` tinyint(4) DEFAULT 0,
  PRIMARY KEY (`id_equip`),
  KEY `idx_player_equip_player` (`id_player`),
  KEY `idx_player_equip_hero` (`id_hero`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `player_exchange_usage`
--

DROP TABLE IF EXISTS `player_exchange_usage`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `player_exchange_usage` (
  `id_player` int(11) NOT NULL,
  `id_exchange` int(11) NOT NULL,
  `usage_date` date NOT NULL,
  `trade_count` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_player`,`id_exchange`,`usage_date`),
  KEY `idx_player_exchange_usage_date` (`usage_date`,`id_exchange`),
  CONSTRAINT `player_exchange_usage_ibfk_1` FOREIGN KEY (`id_player`) REFERENCES `player` (`id_player`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `player_item`
--

DROP TABLE IF EXISTS `player_item`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `player_item` (
  `id_player` int(11) NOT NULL,
  `id_item` varchar(60) NOT NULL,
  `amount` int(11) NOT NULL DEFAULT 0,
  `bound` varchar(5) NOT NULL DEFAULT 'T',
  PRIMARY KEY (`id_player`,`id_item`),
  CONSTRAINT `player_item_ibfk_1` FOREIGN KEY (`id_player`) REFERENCES `player` (`id_player`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `player_quest`
--

DROP TABLE IF EXISTS `player_quest`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `player_quest` (
  `id_player` int(11) NOT NULL,
  `id_quest` varchar(60) NOT NULL,
  `done` int(11) NOT NULL DEFAULT 0,
  `time_done` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_player`,`id_quest`),
  CONSTRAINT `player_quest_ibfk_1` FOREIGN KEY (`id_player`) REFERENCES `player` (`id_player`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `player_resource_slot`
--

DROP TABLE IF EXISTS `player_resource_slot`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `player_resource_slot` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_player` int(11) NOT NULL,
  `slot_type` varchar(30) NOT NULL,
  `amount` int(11) NOT NULL DEFAULT 0,
  `expires_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_player` (`id_player`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `player_state`
--

DROP TABLE IF EXISTS `player_state`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `player_state` (
  `id_player` int(11) NOT NULL,
  `motiv` int(11) NOT NULL DEFAULT 0,
  `prot_pop` int(11) NOT NULL DEFAULT 0,
  `peace` int(11) NOT NULL DEFAULT 0,
  `medical` int(11) NOT NULL DEFAULT 0,
  `wheat` int(11) NOT NULL DEFAULT 0,
  `stone` int(11) NOT NULL DEFAULT 0,
  `metal` int(11) NOT NULL DEFAULT 0,
  `coin` int(11) NOT NULL DEFAULT 0,
  `wood` int(11) NOT NULL DEFAULT 0,
  `attack_10` int(11) NOT NULL DEFAULT 0,
  `defence_10` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_player`),
  CONSTRAINT `player_state_ibfk_1` FOREIGN KEY (`id_player`) REFERENCES `player` (`id_player`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `report_player`
--

DROP TABLE IF EXISTS `report_player`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `report_player` (
  `id_report` int(11) NOT NULL AUTO_INCREMENT,
  `id_player` int(11) NOT NULL,
  `seen` tinyint(1) NOT NULL DEFAULT 0,
  `x` int(11) NOT NULL DEFAULT 0,
  `y` int(11) NOT NULL DEFAULT 0,
  `time_stamp` datetime NOT NULL DEFAULT current_timestamp(),
  `type` int(11) NOT NULL DEFAULT 0,
  `t` int(11) NOT NULL DEFAULT 0,
  `lvl` int(11) NOT NULL DEFAULT 0,
  `detail` longtext DEFAULT NULL,
  PRIMARY KEY (`id_report`),
  KEY `idx_report_player_seen` (`id_player`,`seen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `spy_report`
--

DROP TABLE IF EXISTS `spy_report`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `spy_report` (
  `id_report` int(11) NOT NULL AUTO_INCREMENT,
  `id_player` int(11) NOT NULL,
  `seen` tinyint(1) NOT NULL DEFAULT 0,
  `victim` int(11) NOT NULL DEFAULT 0,
  `x` int(11) NOT NULL DEFAULT 0,
  `y` int(11) NOT NULL DEFAULT 0,
  `time_stamp` datetime NOT NULL DEFAULT current_timestamp(),
  `type` int(11) NOT NULL DEFAULT 0,
  `t` int(11) NOT NULL DEFAULT 0,
  `l` int(11) NOT NULL DEFAULT 0,
  `spy_for` varchar(50) NOT NULL DEFAULT 'city',
  `content` longtext DEFAULT NULL,
  PRIMARY KEY (`id_report`),
  KEY `idx_spy_report_player_seen` (`id_player`,`seen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `timed_task`
--

DROP TABLE IF EXISTS `timed_task`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `timed_task` (
  `id_task` int(11) NOT NULL AUTO_INCREMENT,
  `id_city` int(11) NOT NULL,
  `id_player` int(11) NOT NULL,
  `task_type` varchar(20) NOT NULL,
  `item_id` varchar(50) NOT NULL,
  `lvl_to` int(11) NOT NULL DEFAULT 1,
  `start_time` int(11) NOT NULL,
  `time_arrive` int(11) NOT NULL,
  `details` text DEFAULT NULL,
  PRIMARY KEY (`id_task`),
  KEY `id_city` (`id_city`),
  KEY `id_player` (`id_player`),
  CONSTRAINT `timed_task_ibfk_1` FOREIGN KEY (`id_city`) REFERENCES `city` (`id_city`) ON DELETE CASCADE,
  CONSTRAINT `timed_task_ibfk_2` FOREIGN KEY (`id_player`) REFERENCES `player` (`id_player`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_auth`
--

DROP TABLE IF EXISTS `user_auth`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_auth` (
  `id_user` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `id_player` int(11) NOT NULL,
  `last_server_id` int(11) DEFAULT NULL,
  `last_server_entered_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_user`),
  UNIQUE KEY `uq_user_auth_email` (`email`),
  UNIQUE KEY `uq_user_auth_username` (`username`),
  KEY `idx_user_auth_player` (`id_player`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `world_battel`
--

DROP TABLE IF EXISTS `world_battel`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `world_battel` (
  `id_battel` int(11) NOT NULL AUTO_INCREMENT,
  `id_player` int(11) NOT NULL,
  `id_city` int(11) NOT NULL,
  `id_hero` int(11) NOT NULL,
  `task` int(11) NOT NULL DEFAULT 0,
  `x_city` int(11) NOT NULL DEFAULT 0,
  `y_city` int(11) NOT NULL DEFAULT 0,
  `x_coord` int(11) NOT NULL DEFAULT 0,
  `y_coord` int(11) NOT NULL DEFAULT 0,
  `time_start` int(11) NOT NULL DEFAULT 0,
  `time_arrive` int(11) NOT NULL DEFAULT 0,
  `state` varchar(32) NOT NULL DEFAULT 'marching',
  PRIMARY KEY (`id_battel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping events for database 'elkaisar_prod_ready_20261109'
--

--
-- Dumping routines for database 'elkaisar_prod_ready_20261109'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-09 13:29:20
