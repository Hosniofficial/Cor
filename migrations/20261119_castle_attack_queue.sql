-- Migration: Castle Attack Queue
-- طابور هجوم القلاع (184-186)
-- كل قلعة لها طابور بحد أقصى 10 أحلاف
-- تُفتح نافذة كل حلف الساعة 22:00 لمدة 24 ساعة حسب ترتيبه

CREATE TABLE IF NOT EXISTS `castle_attack_queue` (
  `id`         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `x_coord`    INT              NOT NULL,
  `y_coord`    INT              NOT NULL,
  `id_guild`   INT UNSIGNED     NOT NULL,
  `guild_name` VARCHAR(64)      NOT NULL DEFAULT '',
  `slot`       TINYINT UNSIGNED NOT NULL COMMENT 'ترتيب الحلف في الطابور (1-10)',
  `time_start` INT UNSIGNED     NOT NULL DEFAULT 0 COMMENT 'بداية نافذة الهجوم (Unix)',
  `time_end`   INT UNSIGNED     NOT NULL DEFAULT 0 COMMENT 'نهاية نافذة الهجوم (Unix)',
  `applied_at` INT UNSIGNED     NOT NULL DEFAULT 0 COMMENT 'وقت تقديم الطلب',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_guild_per_castle` (`x_coord`, `y_coord`, `id_guild`),
  KEY `idx_castle`  (`x_coord`, `y_coord`),
  KEY `idx_guild`   (`id_guild`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='طابور هجوم الأحلاف على القلاع المتمردة';
