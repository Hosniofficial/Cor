<?php
// Database connection helper for Elkaisar Backend
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $environment = strtolower((string)(getenv('ELKAISAR_ENV') ?: 'development'));
        if (!in_array($environment, ['development', 'test', 'production'], true)) {
            error_log('[Database] ELKAISAR_ENV must be development, test, or production');
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['state' => 'error', 'msg' => 'Database configuration is incomplete']);
            exit;
        }
        $isProduction = $environment === 'production';
        $host = getenv('ELKAISAR_DB_HOST') ?: ($isProduction ? '' : '127.0.0.1');
        $portValue = getenv('ELKAISAR_DB_PORT') ?: '3306';
        $db = getenv('ELKAISAR_DB_NAME');
        $db = $db === false || $db === ''
            ? ($isProduction ? '' : 'elkaisar_prod_ready_20261109')
            : $db;
        $user = getenv('ELKAISAR_DB_USER') ?: ($isProduction ? '' : 'root');
        $pass = getenv('ELKAISAR_DB_PASSWORD');
        $pass = $pass === false ? '' : $pass;
        $missingSettings = [];
        if ($isProduction) {
            foreach ([
                'ELKAISAR_DB_HOST' => $host,
                'ELKAISAR_DB_NAME' => $db,
                'ELKAISAR_DB_USER' => $user,
                'ELKAISAR_DB_PASSWORD' => $pass,
            ] as $setting => $value) {
                if ($value === '') {
                    $missingSettings[] = $setting;
                }
            }
        }
        $port = filter_var($portValue, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 65535],
        ]);
        if ($missingSettings || $port === false) {
            $reason = $missingSettings
                ? 'Missing required settings: ' . implode(', ', $missingSettings)
                : 'ELKAISAR_DB_PORT must be between 1 and 65535';
            error_log('[Database] ' . $reason);
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['state' => 'error', 'msg' => 'Database configuration is incomplete']);
            exit;
        }
        $dsn  = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
        } catch (\PDOException $e) {
            error_log('[Database] Connection failed: ' . $e->getMessage());
            http_response_code(503);
            header('Content-Type: application/json');
            echo json_encode(['state' => 'error', 'msg' => 'Database connection unavailable']);
            exit;
        }
    }
    return $pdo;
}

function findAvailableWorldCoordinates($db) {
    static $blockedTiles = null;
    if ($blockedTiles === null) {
        $worldMapPath = __DIR__ . '/../web.elkaisar.com/js-3.4.113/json/WorldBarrary.json';
        if (!is_readable($worldMapPath)) {
            throw new RuntimeException('World map data is unavailable for city placement');
        }

        $worldMap = json_decode(file_get_contents($worldMapPath), true);
        if (!is_string($worldMap)) {
            throw new RuntimeException('World map data is invalid for city placement');
        }

        $blockedTiles = [];
        foreach (explode(',', $worldMap) as $entry) {
            $fields = explode('_', $entry, 3);
            if (count($fields) === 3 && (int)$fields[2] > 0) {
                $blockedTiles[$fields[0] . '_' . $fields[1]] = true;
            }
        }
    }

    $occupiedStmt = $db->prepare(
        'SELECT 1 FROM `city` WHERE `x` = ? AND `y` = ?
         UNION ALL
         SELECT 1 FROM `city_barray` WHERE `x_coord` = ? AND `y_coord` = ?
         LIMIT 1'
    );
    for ($attempt = 0; $attempt < 500; $attempt++) {
        $x = random_int(50, 450);
        $y = random_int(50, 450);
        if (isset($blockedTiles[$x . '_' . $y])) {
            continue;
        }

        $occupiedStmt->execute([$x, $y, $x, $y]);
        if (!$occupiedStmt->fetchColumn()) {
            return ['x' => $x, 'y' => $y];
        }
    }

    throw new RuntimeException('No unoccupied land tile is available for city placement');
}

// Function to initialize a fresh player from scratch
function createFreshPlayer($name = 'قيصر الشرق', $avatar = 1) {
    $db = getDB();
    $avatar = max(1, min(12, (int)$avatar));
    $cityName = 'العاصمة 1';

    // 1. Create Player
    $stmt = $db->prepare("INSERT INTO `player` (`name`, `prestige`, `honor`, `gold`, `avatar`, `p_token`) VALUES (?, 100, 0, 50, ?, ?)");
    $token = bin2hex(random_bytes(16));
    $stmt->execute([$name, $avatar, $token]);
    $playerId = (int)$db->lastInsertId();

    // 2. Create Player State
    $stmt = $db->prepare("INSERT INTO `player_state` (`id_player`, `motiv`, `peace`) VALUES (?, ?, ?)");
    $stmt->execute([$playerId, time() + 86400 * 7, time() + 86400 * 7]); // 7 days peace protection for beginners

    // 3. Create Player Education (all 0)
    $stmt = $db->prepare("INSERT INTO `player_edu` (`id_player`) VALUES (?)");
    $stmt->execute([$playerId]);

    // 4. Create Initial City
    $coordinates = findAvailableWorldCoordinates($db);
    $randX = $coordinates['x'];
    $randY = $coordinates['y'];
    $stmt = $db->prepare("INSERT INTO `city` (
        `id_player`, `x`, `y`, `name`, `lvl`,
        `food`, `wood`, `stone`, `metal`, `coin`,
        `pop`, `pop_max`, `pop_cap`, `loy`, `loy_max`, `LS`, `pop_LSS`
    ) VALUES (
        ?, ?, ?, ?, 0,
        10000, 10000, 10000, 10000, 10000,
        100, 100, 200, 100, 100, ?, ?
    )");
    $cityCreatedAt = time();
    $stmt->execute([$playerId, $randX, $randY, $cityName, $cityCreatedAt, $cityCreatedAt]);
    $cityId = (int)$db->lastInsertId();

    // 5. Default starter buildings
    $starterBuildings = [
        'palace'        => ['type' => 12, 'lvl' => 1],
        'farm'          => ['type' => 16, 'lvl' => 1],
        'wood'          => ['type' => 15, 'lvl' => 1],
        'mine'          => ['type' => 17, 'lvl' => 1],
        'stone'         => ['type' => 18, 'lvl' => 1],
        'wall'          => ['type' => 13, 'lvl' => 0],
        'seaport'       => ['type' => 19, 'lvl' => 0],
        'lighthouse'    => ['type' => 20, 'lvl' => 0],
        'light_house_1' => ['type' => 1,  'lvl' => 1], // Cottage 1
    ];

    $allPlaces = [
        'palace', 'wall', 'seaport', 'wood', 'farm', 'mine', 'lighthouse', 'stone',
        'light_house_1', 'light_house_2', 'light_house_3', 'light_house_4', 'light_house_5',
        'light_house_6', 'light_house_7', 'light_house_8', 'light_house_9', 'light_house_10',
        'under_palace_1', 'under_palace_2', 'under_palace_3', 'under_palace_4', 'under_palace_5',
        'under_palace_6', 'under_palace_7', 'under_palace_8', 'under_palace_9', 'under_palace_10',
        'under_palace_11', 'under_palace_12',
        'above_palace_1', 'above_palace_2', 'above_palace_3', 'above_palace_4', 'above_palace_5', 'above_palace_6',
        'hill_1', 'hill_2', 'hill_3', 'hill_4', 'hill_5', 'hill_6', 'hill_7', 'hill_8', 'hill_9',
        'hill_10', 'hill_11', 'hill_12',
        'under_wall_1', 'under_wall_2', 'under_wall_3', 'under_wall_4', 'under_wall_5', 'under_wall_6',
        'under_wall_7', 'under_wall_8', 'under_wall_9', 'under_wall_10', 'under_wall_11', 'under_wall_12',
        'around_wood_1', 'around_wood_2', 'around_wood_3'
    ];

    $bStmt = $db->prepare("INSERT INTO `city_building` (`id_city`, `place`, `type`, `lvl`) VALUES (?, ?, ?, ?)");
    foreach ($allPlaces as $place) {
        $type = isset($starterBuildings[$place]) ? $starterBuildings[$place]['type'] : 0;
        $lvl  = isset($starterBuildings[$place]) ? $starterBuildings[$place]['lvl'] : 0;
        $bStmt->execute([$cityId, $place, $type, $lvl]);
    }

    // 6. Starter Hero
    $hStmt = $db->prepare("INSERT INTO `city_hero` (`id_city`, `id_player`, `name`, `lvl`, `power`, `power_max`, `avatar`) VALUES (?, ?, 'القائد الأول', 1, 50, 50, 1)");
    $hStmt->execute([$cityId, $playerId]);

    // 7. Starter Items (initial pack)
    $starterItems = [
        ['id_item' => 'beginner_back_1', 'amount' => 1],
        ['id_item' => 'motiv_60',        'amount' => 1],
        ['id_item' => 'change_name',     'amount' => 1],
        ['id_item' => 'trumpet',         'amount' => 10],
        ['id_item' => 'taffy',           'amount' => 1],
        ['id_item' => 'archit_a',        'amount' => 1],
        ['id_item' => 'archit_b',        'amount' => 1],
        ['id_item' => 'bread',           'amount' => 5],
        ['id_item' => 'wheat_1',         'amount' => 1],
        ['id_item' => 'wood_1',          'amount' => 1],
        ['id_item' => 'stone_1',         'amount' => 1],
        ['id_item' => 'metal_1',         'amount' => 1],
        ['id_item' => 'exp_hero_8',      'amount' => 1],
        ['id_item' => 'tagned_3p',       'amount' => 1],
        ['id_item' => 'army_all_1',      'amount' => 3],
        ['id_item' => 'food_c',          'amount' => 1],
        ['id_item' => 'wood_c',          'amount' => 1],
        ['id_item' => 'stone_c',         'amount' => 1],
        ['id_item' => 'metal_b',         'amount' => 2],
        ['id_item' => 'prestige_slogan', 'amount' => 1],
    ];
    $iStmt = $db->prepare("INSERT INTO `player_item` (`id_player`, `id_item`, `amount`) VALUES (?, ?, ?)");
    foreach ($starterItems as $item) {
        $iStmt->execute([$playerId, $item['id_item'], $item['amount']]);
    }

    return [
        'playerId' => $playerId,
        'cityId'   => $cityId,
        'token'    => $token
    ];
}
