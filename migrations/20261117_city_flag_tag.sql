ALTER TABLE `city`
    ADD COLUMN `flag_tag` VARCHAR(3) NOT NULL DEFAULT '' AFTER `flag_expires_at`;
