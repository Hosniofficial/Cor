ALTER TABLE `world_battel`
    ADD COLUMN `time_back` INT NOT NULL DEFAULT 0 AFTER `time_arrive`;

CREATE INDEX `idx_world_battel_return` ON `world_battel` (`state`, `time_back`);
CREATE INDEX `idx_world_battel_player_return` ON `world_battel` (`id_player`, `state`, `time_back`);
CREATE INDEX `idx_world_battel_hero_return` ON `world_battel` (`id_hero`, `state`, `time_back`);
