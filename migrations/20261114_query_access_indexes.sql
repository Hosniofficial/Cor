-- Add indexes for repeated filtering, ordering, and join patterns in the API and WS server.

CREATE INDEX `idx_player_guild_honor`
    ON `player` (`id_guild`, `honor`);
CREATE INDEX `idx_player_ranking`
    ON `player` (`prestige`, `honor`);
CREATE INDEX `idx_city_coordinates`
    ON `city` (`x`, `y`);
CREATE INDEX `idx_city_ranking`
    ON `city` (`lvl`, `pop`);
CREATE INDEX `idx_guild_member_rank`
    ON `guild_member` (`id_guild`, `rank`);
CREATE INDEX `idx_city_barray_city`
    ON `city_barray` (`id_city`);
CREATE INDEX `idx_market_offer_city_status`
    ON `market_offer` (`id_city`, `status`, `id_offer`);
CREATE INDEX `idx_market_offer_book`
    ON `market_offer` (`res_type`, `offer_type`, `status`, `price_per_unit`, `id_offer`);
CREATE INDEX `idx_city_market_trans_destination`
    ON `city_market_trans` (`id_city_to`, `status`, `time_arrive`);
CREATE INDEX `idx_city_market_trans_origin`
    ON `city_market_trans` (`id_city_from`, `status`);
CREATE INDEX `idx_city_hero_order`
    ON `city_hero` (`id_city`, `ord`, `id_hero`);
CREATE INDEX `idx_city_hero_player_level`
    ON `city_hero` (`id_player`, `lvl`);
CREATE INDEX `idx_city_hero_ranking`
    ON `city_hero` (`lvl`);
CREATE INDEX `idx_city_garrison_status_arrival`
    ON `city_garrison` (`status`, `time_arrive`);
CREATE INDEX `idx_city_garrison_status_return`
    ON `city_garrison` (`status`, `return_at`);
CREATE INDEX `idx_world_battel_coordinates_state`
    ON `world_battel` (`x_coord`, `y_coord`, `state`);
CREATE INDEX `idx_world_battel_state_arrival`
    ON `world_battel` (`state`, `time_arrive`, `id_battel`);
CREATE INDEX `idx_world_battel_member_hero_state`
    ON `world_battel_member` (`id_hero`, `state`);
CREATE INDEX `idx_world_battel_member_state_return`
    ON `world_battel_member` (`state`, `time_back`, `id_member`);
CREATE INDEX `idx_arena_player_rank`
    ON `arena_player` (`rank`, `points`, `id_player`);
CREATE INDEX `idx_msg_diff_player_time`
    ON `msg_diff` (`id_player`, `time_stamp`);
