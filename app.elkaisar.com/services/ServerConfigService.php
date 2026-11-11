<?php
// app.elkaisar.com/services/ServerConfigService.php
// LiveOps Dynamic Configuration Service for Elkaisar

class ServerConfigService {
    private static $cache = null;
    private static $cacheTimestamp = 0;
    private static $cacheTtl = 30; // 30 seconds local in-memory cache TTL

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
        } catch (Exception $e) {
            error_log('[ServerConfigService Error]: ' . $e->getMessage());
            if (self::$cache === null) {
                self::$cache = [];
            }
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
        $strVal = is_bool($value) ? ($value ? 'true' : 'false') : (string)$value;
        $stmt = $db->prepare("UPDATE `server_config` SET `config_value` = ?, `updated_by` = ?, `updated_at` = NOW() WHERE `config_key` = ?");
        $stmt->execute([$strVal, $updatedBy, $key]);
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
        } catch (Exception $e) {
            return [];
        }
    }
}
