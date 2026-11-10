-- Apply once to an existing database before enabling market endpoints.
-- Safe to re-run when the market tables already have the current columns.
-- Select the intended database first and take a backup before applying.

CREATE TABLE IF NOT EXISTS `market_offer` (
    `id_offer` INT AUTO_INCREMENT PRIMARY KEY,
    `id_player` INT NOT NULL,
    `id_city` INT NOT NULL,
    `offer_type` VARCHAR(10) NOT NULL,
    `res_type` VARCHAR(20) NOT NULL,
    `amount` INT NOT NULL,
    `price_per_unit` DECIMAL(10,4) NOT NULL,
    `done` INT NOT NULL DEFAULT 0,
    `status` VARCHAR(20) NOT NULL DEFAULT 'active',
    `created_at` INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @market_done_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'market_offer'
      AND COLUMN_NAME = 'done'
);
SET @market_done_ddl = IF(
    @market_done_exists = 0,
    'ALTER TABLE `market_offer` ADD COLUMN `done` INT NOT NULL DEFAULT 0',
    'SELECT 1'
);
PREPARE market_done_stmt FROM @market_done_ddl;
EXECUTE market_done_stmt;
DEALLOCATE PREPARE market_done_stmt;

SET @market_status_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'market_offer'
      AND COLUMN_NAME = 'status'
);
SET @market_status_ddl = IF(
    @market_status_exists = 0,
    'ALTER TABLE `market_offer` ADD COLUMN `status` VARCHAR(20) NOT NULL DEFAULT ''active''',
    'SELECT 1'
);
PREPARE market_status_stmt FROM @market_status_ddl;
EXECUTE market_status_stmt;
DEALLOCATE PREPARE market_status_stmt;

CREATE TABLE IF NOT EXISTS `city_market_trans` (
    `id_trans` INT AUTO_INCREMENT PRIMARY KEY,
    `id_player` INT NOT NULL,
    `id_city_from` INT NOT NULL,
    `id_city_to` INT NOT NULL,
    `resource` VARCHAR(20) NOT NULL,
    `amount` INT NOT NULL,
    `unit_price` DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
    `time_start` INT NOT NULL,
    `time_arrive` INT NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'marching'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
