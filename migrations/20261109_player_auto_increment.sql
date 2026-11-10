-- Reset the player sequence safely. MariaDB keeps AUTO_INCREMENT above the
-- signed INT limit when restoring some dumps, which makes the next insert fail.
-- On non-empty tables, MariaDB keeps the next value above the existing IDs.
ALTER TABLE `player` AUTO_INCREMENT = 1;
ALTER TABLE `city` ALTER `name` SET DEFAULT 'العاصمة 1';
