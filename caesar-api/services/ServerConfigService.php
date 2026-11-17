<?php
// caesar-api/services/ServerConfigService.php
// LiveOps dynamic configuration service for CAESER OF ARABS.

class ServerConfigService {
    private static $cache = null;
    private static $cacheTimestamp = 0;
    private static $cacheTtl = 30; // 30 seconds local in-memory cache TTL
    private const DEFINITIONS = [
        'camp_reset_interval_hours' => ['number', 1, 48],
        'gang_reset_interval_hours' => ['number', 1, 24],
        'squad_reset_interval_hours' => ['number', 1, 24],
        'wilderness_luxury_drop_multiplier' => ['number', 0.1, 10],
        'global_drop_rate_multiplier' => ['number', 0.1, 10],
        'equipment_drop_chance_percent' => ['number', 0, 100],
        'starter_player_gold' => ['number', 0, 10000],
        'starter_protection_hours' => ['number', 0, 720],
        'starter_food' => ['number', 0, 10000000],
        'starter_wood' => ['number', 0, 10000000],
        'starter_stone' => ['number', 0, 10000000],
        'starter_metal' => ['number', 0, 10000000],
        'starter_coin' => ['number', 0, 10000000],
        'resource_production_multiplier' => ['number', 0.1, 20],
        'march_speed_multiplier' => ['number', 0.1, 10],
        'building_speed_multiplier' => ['number', 0.1, 20],
        'study_speed_multiplier' => ['number', 0.1, 20],
        'camp_rally_duration_sec' => ['number', 10, 1800],
        'city_attack_min_duration_sec' => ['number', 60, 3600],
        'wounded_recovery_rate' => ['number', 0, 1],
        'maintenance_mode' => ['boolean'],
        'maintenance_message' => ['string', 1, 255],
        'server_truce_active' => ['boolean'],
        'live_banner_text' => ['string', 0, 255],
        'live_banner_color' => ['color']
    ];

    /**
     * Load all server configs into memory cache
     */
    public static function loadAll($db, $force = false) {
        $now = time();
        if (!$force && self::$cache !== null && ($now - self::$cacheTimestamp) < self::$cacheTtl) {
            return self::$cache;
        }

        try {
            $stmt = $db->query("SELECT `config_key`, `category`, `config_value`, `value_type`, `title_ar`, `description_ar` FROM `server_config`");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $configs = [];
            foreach ($rows as $r) {
                $val = $r['config_value'];
                if ($r['value_type'] === 'number') {
                    $val = strpos($val, '.') !== false ? (float)$val : (int)$val;
                } elseif ($r['value_type'] === 'boolean') {
                    $val = filter_var($val, FILTER_VALIDATE_BOOLEAN);
                } elseif ($r['value_type'] === 'json') {
                    $val = json_decode($val, true);
                }
                $configs[$r['config_key']] = [
                    'value'          => $val,
                    'raw_value'      => $r['config_value'],
                    'category'       => $r['category'],
                    'value_type'     => $r['value_type'],
                    'title_ar'       => $r['title_ar'],
                    'description_ar' => $r['description_ar']
                ];
            }
            self::$cache = $configs;
            self::$cacheTimestamp = $now;
        } catch (Throwable $e) {
            error_log('[ServerConfigService Error]: ' . $e->getMessage());
            throw new RuntimeException('Unable to load server configuration', 0, $e);
        }
        return self::$cache;
    }

    /**
     * Get a typed config value by key
     */
    public static function get($db, $key, $default = null) {
        $configs = self::loadAll($db);
        if (isset($configs[$key])) {
            return $configs[$key]['value'];
        }
        return $default;
    }

    public static function getInt($db, $key, $default = 0) {
        return (int)self::get($db, $key, $default);
    }

    public static function getFloat($db, $key, $default = 1.0) {
        return (float)self::get($db, $key, $default);
    }

    public static function getBool($db, $key, $default = false) {
        return (bool)self::get($db, $key, $default);
    }

    /**
     * Update a config value in the database and flush local cache
     */
    public static function set($db, $key, $value, $updatedBy = 0) {
        if (!isset(self::DEFINITIONS[$key])) {
            throw new InvalidArgumentException('Unknown server configuration key: ' . $key);
        }
        $definition = self::DEFINITIONS[$key];
        $type = $definition[0];
        if ($type === 'number') {
            if (!is_scalar($value) || !is_numeric($value)) {
                throw new InvalidArgumentException('Configuration value must be numeric: ' . $key);
            }
            $number = (float)$value;
            $integerKeys = [
                'camp_reset_interval_hours', 'gang_reset_interval_hours', 'squad_reset_interval_hours',
                'equipment_drop_chance_percent', 'starter_player_gold', 'starter_food',
                'starter_wood', 'starter_stone', 'starter_metal', 'starter_coin',
                'starter_protection_hours', 'camp_rally_duration_sec', 'city_attack_min_duration_sec'
            ];
            if (!is_finite($number) || $number < $definition[1] || $number > $definition[2]
                || (in_array($key, $integerKeys, true) && floor($number) !== $number)) {
                throw new InvalidArgumentException('Configuration value is outside its allowed range: ' . $key);
            }
            $strVal = (string)$number;
        } elseif ($type === 'boolean') {
            if (!in_array($value, [true, false, 1, 0, '1', '0', 'true', 'false'], true)) {
                throw new InvalidArgumentException('Configuration value must be boolean: ' . $key);
            }
            $strVal = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
        } elseif ($type === 'color') {
            if (!is_string($value) || preg_match('/\A#[0-9a-fA-F]{6}\z/', $value) !== 1) {
                throw new InvalidArgumentException('Configuration color must be a six-digit hex value');
            }
            $strVal = strtolower($value);
        } else {
            if (!is_string($value) || strlen($value) < $definition[1] || strlen($value) > $definition[2]) {
                throw new InvalidArgumentException('Configuration text length is invalid: ' . $key);
            }
            $strVal = $value;
        }
        $stmt = $db->prepare("UPDATE `server_config` SET `config_value` = ?, `updated_by` = ?, `updated_at` = NOW() WHERE `config_key` = ?");
        $stmt->execute([$strVal, $updatedBy, $key]);
        if ($stmt->rowCount() === 0) {
            $check = $db->prepare("SELECT 1 FROM `server_config` WHERE `config_key` = ? LIMIT 1");
            $check->execute([$key]);
            if (!$check->fetchColumn()) {
                throw new RuntimeException('Server configuration key is missing from the database: ' . $key);
            }
        }
        self::$cache = null; // Flush cache
        return true;
    }

    /**
     * Get active scheduled multiplier events (e.g. happy hour, drop boost, truce)
     */
    public static function getActiveEvents($db) {
        $now = time();
        try {
            $stmt = $db->prepare("SELECT * FROM `server_events` WHERE `is_active` = 1 AND `time_start` <= ? AND `time_end` >= ? ORDER BY `id_event` DESC");
            $stmt->execute([$now, $now]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('[ServerConfigService Events Error]: ' . $e->getMessage());
            throw new RuntimeException('Unable to load active server events', 0, $e);
        }
    }
}
