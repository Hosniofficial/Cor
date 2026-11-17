-- Large city armies can produce hourly food upkeep beyond the signed INT range.
ALTER TABLE `city`
    MODIFY `food_out` BIGINT NOT NULL DEFAULT 0;
