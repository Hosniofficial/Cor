<?php
// Dynamic player.php for web.elkaisar.com
require_once __DIR__ . '/../../app.elkaisar.com/db.php';

header('Content-Type: text/plain; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$db = getDB();

// 1. Return player's cities
if (isset($_POST['get_city']) || isset($_GET['get_city'])) {
    $idPlayer = isset($_POST['id_player']) ? (int)$_POST['id_player'] : (isset($_GET['id_player']) ? (int)$_GET['id_player'] : 1);
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
    $stmt = $db->query("SELECT count(*) as p_count FROM `player`");
    $pCount = (int)$stmt->fetchColumn();
    $cStmt = $db->query("SELECT count(*) as c_count FROM `city`");
    $cCount = (int)$cStmt->fetchColumn();
    echo json_encode([
        'id'          => 1,
        'player_num'  => $pCount,
        'guild_num'   => 0,
        'city_num'    => $cCount,
        'hero_num'    => 1,
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
