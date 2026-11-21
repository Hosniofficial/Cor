-- Migration: Add detailed resource columns and acce to city_market_trans
ALTER TABLE `city_market_trans`
    ADD COLUMN `food` BIGINT NOT NULL DEFAULT 0 AFTER `amount`,
    ADD COLUMN `wood` BIGINT NOT NULL DEFAULT 0 AFTER `food`,
    ADD COLUMN `stone` BIGINT NOT NULL DEFAULT 0 AFTER `wood`,
    ADD COLUMN `metal` BIGINT NOT NULL DEFAULT 0 AFTER `stone`,
    ADD COLUMN `coin` BIGINT NOT NULL DEFAULT 0 AFTER `metal`,
    ADD COLUMN `acce` TINYINT NOT NULL DEFAULT 0 AFTER `status`;
