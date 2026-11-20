-- Remove obsolete equipment-drop controls; equipment is obtained through crafting/exchange.
DELETE FROM `server_config`
WHERE `config_key` = 'equipment_drop_chance_percent';

-- Preserve event history but stop active legacy boosts from advertising a disabled mechanic.
UPDATE `server_events`
SET `is_active` = 0
WHERE `event_type` = 'equipment_drop_boost'
  AND `is_active` <> 0;

UPDATE `server_config`
SET `description_ar` = 'مضاعف عام لفرص الحصول على جوائز أهداف الخريطة'
WHERE `config_key` = 'global_drop_rate_multiplier';
