<?php
// caesar-web/api/city.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../caesar-api/db.php';
require_once __DIR__ . '/../../caesar-api/PlayerAuth.php';
require_once __DIR__ . '/../../caesar-api/services/ProductionService.php';

$db = getDB();
$player = requireAuthenticatedPlayer($db);
$cityId = (int)($_REQUEST['id_city'] ?? 0);
$playerId = (int)$player['id_player'];

if ($cityId <= 0) {
    echo json_encode(['state' => 'error_no_city'], JSON_UNESCAPED_UNICODE);
    exit;
}

$db->beginTransaction();
if (!ProductionService::recalculateCityProductionForPlayer($db, $cityId, $playerId)) {
    $db->rollBack();
    echo json_encode(['state' => 'error_no_city'], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $db->prepare(
    "SELECT `id_city`, `food`, `wood`, `stone`, `metal`, `coin`, `pop`, `wall_cap`, `wall_a`, `wall_b`, `wall_c` " .
    "FROM `city` WHERE `id_city` = ? AND `id_player` = ? LIMIT 1"
);
$stmt->execute([$cityId, $playerId]);
$city = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$city) {
    $db->rollBack();
    echo json_encode(['state' => 'error_no_city'], JSON_UNESCAPED_UNICODE);
    exit;
}

$db->commit();

foreach ($city as $k => $v) {
    if (is_numeric($v)) {
        $city[$k] = (int)$v;
    }
}

echo json_encode($city, JSON_UNESCAPED_UNICODE);
exit;
