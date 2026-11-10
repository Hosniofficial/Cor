-- Add unique account usernames while keeping existing email-only accounts valid.
-- Back up the target database before applying this migration.

CREATE TABLE IF NOT EXISTS `user_auth` (
    `id_user` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) DEFAULT NULL,
    `email` VARCHAR(100) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `id_player` INT NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_user_auth_username` (`username`),
    UNIQUE KEY `uq_user_auth_email` (`email`),
    KEY `idx_user_auth_player` (`id_player`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @auth_username_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'user_auth'
      AND COLUMN_NAME = 'username'
);
SET @auth_username_ddl = IF(
    @auth_username_exists = 0,
    'ALTER TABLE `user_auth` ADD COLUMN `username` VARCHAR(50) DEFAULT NULL AFTER `id_user`',
    'SELECT 1'
);
PREPARE auth_username_stmt FROM @auth_username_ddl;
EXECUTE auth_username_stmt;
DEALLOCATE PREPARE auth_username_stmt;

SET @auth_username_index_exists = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'user_auth'
      AND INDEX_NAME = 'uq_user_auth_username'
);
SET @auth_username_index_ddl = IF(
    @auth_username_index_exists = 0,
    'ALTER TABLE `user_auth` ADD UNIQUE KEY `uq_user_auth_username` (`username`)',
    'SELECT 1'
);
PREPARE auth_username_index_stmt FROM @auth_username_index_ddl;
EXECUTE auth_username_index_stmt;
DEALLOCATE PREPARE auth_username_index_stmt;
