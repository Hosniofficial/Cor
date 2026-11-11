-- migrations/20261111_server_config_liveops.sql
-- LiveOps & Dynamic Server Configuration for Call of Rome / Elkaisar

CREATE TABLE IF NOT EXISTS `server_config` (
  `config_key` VARCHAR(64) NOT NULL,
  `category` VARCHAR(32) NOT NULL DEFAULT 'general',
  `config_value` TEXT NOT NULL,
  `value_type` ENUM('string', 'number', 'boolean', 'json') NOT NULL DEFAULT 'string',
  `title_ar` VARCHAR(128) NOT NULL,
  `description_ar` VARCHAR(255) NULL,
  `updated_by` INT(11) NULL DEFAULT 0,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`config_key`),
  KEY `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `server_events` (
  `id_event` INT(11) NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(128) NOT NULL,
  `event_type` VARCHAR(32) NOT NULL,
  `multiplier` DECIMAL(5,2) NOT NULL DEFAULT 1.00,
  `time_start` INT(11) NOT NULL,
  `time_end` INT(11) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `extra_data` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_event`),
  KEY `idx_active_window` (`is_active`, `time_start`, `time_end`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert or update default configuration keys
INSERT INTO `server_config` (`config_key`, `category`, `config_value`, `value_type`, `title_ar`, `description_ar`) VALUES
-- 1. Camps & Reset Schedules
('camp_reset_interval_hours', 'camps', '12', 'number', 'فترة رسيت المعسكرات والمناورات (ساعات)', 'تكرار إعادة تعيين وتصفير المعسكرات والمناورات وعواصم الجيوش بالـ UTC'),
('gang_reset_interval_hours', 'camps', '6', 'number', 'فترة رسيت عصابات قرطاجة والساندرز (ساعات)', 'تكرار إعادة تعيين عصابات قرطاجة والصاعقة الباسلة بالـ UTC'),
('squad_reset_interval_hours', 'camps', '4', 'number', 'فترة رسيت الفرق والكتائب واللصوص (ساعات)', 'تكرار إعادة تعيين كتائب وسرايا الجيش واللصوص بالـ UTC'),
('camp_max_attack_per_player', 'camps', '10', 'number', 'الحد الأقصى لهجمات اللاعب على المعسكر', 'عدد الهجمات المسموح بها لكل لاعب على كل معسكر قبل إغلاقه حتى الرسيت القادم'),

-- 2. Drop Rates & Wilderness Luxuries
('wilderness_luxury_drop_multiplier', 'drops', '1.0', 'number', 'مضاعف سقوط أدوات الرفاهية بالبراري', 'مضاعف نسبة الحصول على أدوات الرفاهية (الذهب، الخبز، الجواهر، الخرائط) من البراري'),
('global_drop_rate_multiplier', 'drops', '1.0', 'number', 'مضاعف السقوط العام بالسيرفر', 'مضاعف عام لفرص سقوط المواد والعتاد من كافة الأهداف على الخريطة'),
('equipment_drop_chance_percent', 'drops', '25', 'number', 'نسبة سقوط عتاد الأبطال من المعسكرات (%)', 'فرصة سقوط عتاد البطل من المعسكرات والكتائب عند الفوز'),

-- 3. Server Rates & Starter Economy
('starter_player_gold', 'economy', '50', 'number', 'الذهب المبدئي للاعب الجديد', 'رصيد الذهب الممنوح تلقائياً عند تسجيل لاعب جديد لأول مرة'),
('starter_protection_hours', 'economy', '168', 'number', 'مدة حماية المبتدئين (ساعات)', 'المدة التي تمنع الهجوم على مدينة اللاعب الجديد فور إنشائها (168 ساعة = 7 أيام)'),
('resource_production_multiplier', 'economy', '1.0', 'number', 'مضاعف إنتاج الموارد بالمدن', 'مضاعف سرعة إنتاج المزارع والمناجم ومقالع الأحجار ومناشر الخشب'),
('march_speed_multiplier', 'economy', '1.0', 'number', 'مضاعف سرعة سير الجيوش', 'مضاعف تسريع تحرك الأبطال والجيوش على الخريطة (1.0 = عادي، 2.0 = ضعف السرعة)'),
('building_speed_multiplier', 'economy', '1.0', 'number', 'مضاعف سرعة البناء والترقية', 'مضاعف تسريع بناء وتطوير المباني بالمدن'),
('study_speed_multiplier', 'economy', '1.0', 'number', 'مضاعف سرعة أبحاث الأكاديمية', 'مضاعف تسريع الدراسات والأبحاث التقنية'),
('recruit_speed_multiplier', 'economy', '1.0', 'number', 'مضاعف سرعة تجنيد القوات', 'مضاعف سرعة تدريب المشاة والفرسان والرماة بالمصانع والثكنات'),

-- 4. Battle Rules & Rally
('camp_rally_duration_sec', 'battle', '120', 'number', 'مدة معارك المعسكرات والمناورات (ثواني)', 'المهلة الثابتة للمعركة لتجمع الحلفاء قبل بدء القتال بالمعسكرات والمناورات'),
('city_attack_min_duration_sec', 'battle', '900', 'number', 'الحد الأدنى لزمن الهجوم على المدن (ثواني)', 'الحد الأدنى لمسيرة الهجوم على مدن اللاعبين لمنع المباغتة (900 ثانية = 15 دقيقة)'),
('wounded_recovery_rate', 'battle', '0.70', 'number', 'نسبة إسعاف ونقل الجرحى للمشفى', 'النسبة المئوية لاستعادة الجنود ونقلهم لمشفى المدينة بعد المعارك مع الوحوش والمعسكرات'),

-- 5. Broadcasts & Maintenance
('maintenance_mode', 'system', 'false', 'boolean', 'وضع الصيانة العامة للسيرفر', 'عند التفعيل، يُمنع دخول اللاعبين العاديين ويُسمح فقط للإدارة والمشرفين'),
('maintenance_message', 'system', 'السيرفر حالياً في وضع الصيانة الدورية لتحديث الأنظمة. سنعود قريباً!', 'string', 'رسالة الصيانة المعروضة للاعبين', 'نص الرسالة التي تظهر في شاشة تسجيل الدخول عند تفعيل الصيانة'),
('server_truce_active', 'system', 'false', 'boolean', 'تفعيل الهدنة العامة بالسيرفر', 'عند التفعيل، يُمنع إرسال أي هجوم على مدن اللاعبين ويتم إلغاء الهجمات القائمة'),
('live_banner_text', 'system', '', 'string', 'نص الشريط الإعلاني اللحظي (Marquee)', 'رسالة تظهر في شريط علوي بارز لجميع اللاعبين المتصلين لحظياً'),
('live_banner_color', 'system', '#dfba6b', 'string', 'لون الشريط الإعلاني', 'اللون المستخدم في تمييز الشريط الإعلاني العلوي')

ON DUPLICATE KEY UPDATE
  `title_ar` = VALUES(`title_ar`),
  `description_ar` = VALUES(`description_ar`);
