-- Migration: Track daily repeat plunder count and 24h cycle window per player/location
-- يضيف حقلي عدد مرات الغنيمة وبداية دورة الـ24 ساعة لدعم تكرار الغنيمة اليومية بالذهب أو العناصر

ALTER TABLE `guild_dominance_harvest`
  ADD COLUMN IF NOT EXISTS `harvest_count` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'عدد مرات الغنيمة خلال دورة الـ24 ساعة',
  ADD COLUMN IF NOT EXISTS `window_started_at` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'بداية دورة الـ24 ساعة (Unix timestamp)';
