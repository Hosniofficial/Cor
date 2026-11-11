-- City balances and market quantities support up to one trillion per resource.
-- Signed BIGINT also leaves room for future increases while preserving integer arithmetic.
ALTER TABLE `city`
    MODIFY `food` BIGINT NOT NULL DEFAULT 50000,
    MODIFY `wood` BIGINT NOT NULL DEFAULT 50000,
    MODIFY `stone` BIGINT NOT NULL DEFAULT 50000,
    MODIFY `metal` BIGINT NOT NULL DEFAULT 50000,
    MODIFY `coin` BIGINT NOT NULL DEFAULT 100000;

ALTER TABLE `market_offer`
    MODIFY `amount` BIGINT NOT NULL,
    MODIFY `done` BIGINT NOT NULL DEFAULT 0;

ALTER TABLE `city_market_trans`
    MODIFY `amount` BIGINT NOT NULL;
