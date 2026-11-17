<?php

require_once __DIR__ . '/../caesar-api/controllers/CityController.php';

function expectHealing(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE city (
    id_city INTEGER PRIMARY KEY,
    id_player INTEGER NOT NULL,
    coin INTEGER NOT NULL,
    army_a INTEGER NOT NULL DEFAULT 0,
    army_b INTEGER NOT NULL DEFAULT 0,
    army_c INTEGER NOT NULL DEFAULT 0,
    army_d INTEGER NOT NULL DEFAULT 0,
    army_e INTEGER NOT NULL DEFAULT 0,
    army_f INTEGER NOT NULL DEFAULT 0
)');
$db->exec('CREATE TABLE city_wounded (
    id_city INTEGER PRIMARY KEY,
    army_a INTEGER NOT NULL DEFAULT 0,
    army_b INTEGER NOT NULL DEFAULT 0,
    army_c INTEGER NOT NULL DEFAULT 0,
    army_d INTEGER NOT NULL DEFAULT 0,
    army_e INTEGER NOT NULL DEFAULT 0,
    army_f INTEGER NOT NULL DEFAULT 0
)');
$db->exec('INSERT INTO city (id_city, id_player, coin) VALUES (3, 7, 5000)');
$db->exec('INSERT INTO city_wounded (id_city, army_a, army_b, army_c, army_d, army_e, army_f)
    VALUES (3, 2, 3, 0, 0, 0, 1)');
$_SERVER['REQUEST_METHOD'] = 'POST';

$_REQUEST['armyType'] = 'army_b';
$response = CityController::cureCityWounded($db, ['id_player' => 7], ['id_city' => 3]);
$city = $db->query('SELECT * FROM city WHERE id_city = 3')->fetch(PDO::FETCH_ASSOC);
$wounded = $db->query('SELECT * FROM city_wounded WHERE id_city = 3')->fetch(PDO::FETCH_ASSOC);
expectHealing(
    $response['state'] === 'ok'
        && $response['healedAmount'] === 3
        && $response['coinAmount'] === 1500
        && (int)$city['coin'] === 3500
        && (int)$city['army_b'] === 3
        && (int)$wounded['army_b'] === 0
        && (int)$wounded['army_a'] === 2,
    'Healing should charge the correct fee and restore only the selected troop type'
);

$db->exec('UPDATE city SET coin = 100 WHERE id_city = 3');
$_REQUEST['armyType'] = 'army_f';
$response = CityController::cureCityWounded($db, ['id_player' => 7], ['id_city' => 3]);
$city = $db->query('SELECT * FROM city WHERE id_city = 3')->fetch(PDO::FETCH_ASSOC);
$wounded = $db->query('SELECT * FROM city_wounded WHERE id_city = 3')->fetch(PDO::FETCH_ASSOC);
expectHealing(
    $response['state'] === 'error_1'
        && $response['coinAmount'] === 450
        && (int)$city['coin'] === 100
        && (int)$city['army_f'] === 0
        && (int)$wounded['army_f'] === 1,
    'Insufficient coin should not restore or consume wounded troops'
);

$_REQUEST['armyType'] = 'army_unknown';
$response = CityController::cureCityWounded($db, ['id_player' => 7], ['id_city' => 3]);
expectHealing($response['state'] === 'error_0', 'Unknown troop types should be rejected');

echo "City wounded healing tests passed\n";
