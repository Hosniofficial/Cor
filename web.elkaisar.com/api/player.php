<?php
// Dynamic player.php for web.elkaisar.com
require_once __DIR__ . '/../../app.elkaisar.com/db.php';

header('Content-Type: text/plain; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$db = getDB();

// 1. Return player's cities
if (isset($_POST['get_city']) || isset($_GET['get_city'])) {
    $token = $_POST['token'] ?? $_GET['token'] ?? '';
    if (!is_string($token) || $token === '') {
        http_response_code(401);
        echo json_encode(['state' => 'error_auth']);
        exit;
    }

    $playerStmt = $db->prepare("SELECT id_player FROM `player` WHERE `p_token` = ? LIMIT 1");
    $playerStmt->execute([$token]);
    $idPlayer = (int)$playerStmt->fetchColumn();
    if ($idPlayer <= 0) {
        http_response_code(401);
        echo json_encode(['state' => 'error_auth']);
        exit;
    }

    $requestedPlayerId = $_POST['id_player'] ?? $_GET['id_player'] ?? $idPlayer;
    $requestedPlayerId = filter_var($requestedPlayerId, FILTER_VALIDATE_INT);
    if ($requestedPlayerId === false || $requestedPlayerId !== $idPlayer) {
        http_response_code(403);
        echo json_encode(['state' => 'error_player_owner']);
        exit;
    }

    $stmt = $db->prepare("SELECT * FROM `city` WHERE `id_player` = ?");
    $stmt->execute([$idPlayer]);
    $cities = $stmt->fetchAll();
    foreach ($cities as &$c) {
        foreach ($c as $k => $v) {
            if (is_numeric($v)) $c[$k] = (int)$v;
        }
    }
    echo json_encode($cities, JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. Server Data
if (isset($_GET['SERVER_DATA'])) {
    $stmt   = $db->query("SELECT count(*) FROM `player`");
    $pCount = (int)$stmt->fetchColumn();
    $cStmt  = $db->query("SELECT count(*) FROM `city`");
    $cCount = (int)$cStmt->fetchColumn();
    $gStmt  = $db->query("SELECT count(*) FROM `guild`");
    $gCount = (int)$gStmt->fetchColumn();
    $hStmt  = $db->query("SELECT count(*) FROM `city_hero`");
    $hCount = (int)$hStmt->fetchColumn();

    echo json_encode([
        'id'          => 1,
        'player_num'  => max(1, $pCount),
        'guild_num'   => max(1, $gCount),
        'city_num'    => max(1, $cCount),
        'hero_num'    => max(1, $hCount),
        'open_status' => 1,
        'under_main'  => 0,
        'port'        => '8082'
    ]);
    exit;
}

// 3. Current server timestamp test
if (isset($_POST['test_time']) || isset($_GET['test_time'])) {
    echo time();
    exit;
}

// 4. Default: return server info
echo json_encode([
    'id'          => 1,
    'player_num'  => 1,
    'guild_num'   => 0,
    'city_num'    => 1,
    'hero_num'    => 1,
    'open_status' => 1,
    'under_main'  => 0,
    'port'        => '8082'
]);
