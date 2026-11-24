-- Migration: Per-player harvest tracking for guild dominance
-- Each guild member can collect their own daily harvest independently (once per 24h per player per location)
-- يُنفَّذ مرة واحدة على قاعدة البيانات

CREATE TABLE IF NOT EXISTS `guild_dominance_harvest` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `x_coord`      INT           NOT NULL,
  `y_coord`      INT           NOT NULL,
  `id_player`    INT UNSIGNED  NOT NULL COMMENT 'اللاعب الذي جمع الغنيمة',
  `id_guild`     INT UNSIGNED  NOT NULL COMMENT 'الحلف وقت الجمع للتحقق',
  `harvested_at` INT UNSIGNED  NOT NULL DEFAULT 0 COMMENT 'Unix timestamp آخر جمع لهذا اللاعب',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_player_location` (`x_coord`, `y_coord`, `id_player`),
  KEY `idx_location`  (`x_coord`, `y_coord`),
  KEY `idx_player`    (`id_player`),
  KEY `idx_guild`     (`id_guild`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='سجل الغنيمة اليومية لكل لاعب على حدة لكل موقع محتل';
