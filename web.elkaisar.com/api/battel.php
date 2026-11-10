<?php
// web.elkaisar.com/api/battel.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../app.elkaisar.com/db.php';
require_once __DIR__ . '/../../app.elkaisar.com/PlayerAuth.php';

$db = getDB();
$player = requireAuthenticatedPlayer($db);

if (isset($_GET['GET_BATTEL_FIELD_DATA']) || isset($_POST['GET_BATTEL_FIELD_DATA'])) {
    $x = (int)($_GET['x_coord'] ?? 0);
    $y = (int)($_GET['y_coord'] ?? 0);
    $stmt = $db->prepare("SELECT `id_battel`, `x_city`, `y_city`, 'مدينة القيصر' as `city_name`, 'البطل سيزر' as `player_name` FROM `world_battel` WHERE `x_coord` = ? AND `y_coord` = ?");
    $stmt->execute([$x, $y]);
    $battles = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    echo json_encode($battles, JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_GET['GET_BATTEL_FIELD_DETAIL']) || isset($_POST['GET_BATTEL_FIELD_DETAIL'])) {
    echo json_encode([
        'time_start'  => (string)time(),
        'time_end'    => (string)(time() + 3600),
        'attack_num'  => 1,
        'defence_num' => 0
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_GET['GET_BATTEL_FIXED_DATA']) || isset($_POST['GET_BATTEL_FIXED_DATA'])) {
    echo json_encode([
        'time_start' => (string)time(),
        'time_end'   => (string)(time() + 3600),
        'name'       => 'البطل سيزر'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([]);
exit;
