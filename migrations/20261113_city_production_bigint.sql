-- Hourly production and upkeep can exceed signed INT for large populations or armies.
-- Widen the rate columns without changing values, defaults, or nullability.
ALTER TABLE `city`
    MODIFY `coin_in` BIGINT NOT NULL DEFAULT 100,
    MODIFY `food_in` BIGINT NOT NULL DEFAULT 100,
    MODIFY `wood_in` BIGINT NOT NULL DEFAULT 100,
    MODIFY `stone_in` BIGINT NOT NULL DEFAULT 100,
    MODIFY `metal_in` BIGINT NOT NULL DEFAULT 100,
    MODIFY `coin_out` BIGINT NOT NULL DEFAULT 0,
    MODIFY `food_out` BIGINT NOT NULL DEFAULT 0,
    MODIFY `wood_out` BIGINT NOT NULL DEFAULT 0,
    MODIFY `stone_out` BIGINT NOT NULL DEFAULT 0,
    MODIFY `metal_out` BIGINT NOT NULL DEFAULT 0;
