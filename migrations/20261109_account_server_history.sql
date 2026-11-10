-- Store each account's most recently entered game server.
-- Back up the target database before applying this migration.

SET @last_server_id_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'user_auth'
      AND COLUMN_NAME = 'last_server_id'
);
SET @last_server_id_ddl = IF(
    @last_server_id_exists = 0,
    'ALTER TABLE `user_auth` ADD COLUMN `last_server_id` INT DEFAULT NULL AFTER `id_player`',
    'SELECT 1'
);
PREPARE last_server_id_stmt FROM @last_server_id_ddl;
EXECUTE last_server_id_stmt;
DEALLOCATE PREPARE last_server_id_stmt;

SET @last_server_entered_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'user_auth'
      AND COLUMN_NAME = 'last_server_entered_at'
);
SET @last_server_entered_ddl = IF(
    @last_server_entered_exists = 0,
    'ALTER TABLE `user_auth` ADD COLUMN `last_server_entered_at` TIMESTAMP NULL DEFAULT NULL AFTER `last_server_id`',
    'SELECT 1'
);
PREPARE last_server_entered_stmt FROM @last_server_entered_ddl;
EXECUTE last_server_entered_stmt;
DEALLOCATE PREPARE last_server_entered_stmt;
