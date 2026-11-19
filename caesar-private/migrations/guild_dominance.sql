-- جدول سيطرة الأحلاف على القلاع والممالك
-- يُنفَّذ مرة واحدة على قاعدة البيانات

CREATE TABLE IF NOT EXISTS `guild_dominance` (
  `x_coord`         INT          NOT NULL,
  `y_coord`         INT          NOT NULL,
  `unit_type`       SMALLINT     NOT NULL COMMENT '180-182 ممالك | 184-186 قلاع',
  `unit_lvl`        TINYINT      NOT NULL DEFAULT 1,
  `id_guild`        INT UNSIGNED NOT NULL,
  `id_player`       INT UNSIGNED NOT NULL COMMENT 'اللاعب الذي فتح القلعة',
  `conquered_at`    INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Unix timestamp',
  `last_harvest_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'آخر حصاد يومي',
  `daily_food`      BIGINT       NOT NULL DEFAULT 0,
  `daily_wood`      BIGINT       NOT NULL DEFAULT 0,
  `daily_stone`     BIGINT       NOT NULL DEFAULT 0,
  `daily_metal`     BIGINT       NOT NULL DEFAULT 0,
  `daily_coin`      BIGINT       NOT NULL DEFAULT 0,
  PRIMARY KEY (`x_coord`, `y_coord`),
  KEY `idx_guild`  (`id_guild`),
  KEY `idx_player` (`id_player`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='سيطرة الأحلاف على القلاع والممالك مع حقوق الحصاد اليومي';
