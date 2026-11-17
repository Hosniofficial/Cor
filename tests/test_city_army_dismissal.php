<?php

require_once __DIR__ . '/../caesar-api/controllers/CityController.php';

function expectDismissal(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE city (
    id_city INTEGER PRIMARY KEY,
    army_a INTEGER NOT NULL,
    wall_a INTEGER NOT NULL,
    pop INTEGER NOT NULL,
    pop_max INTEGER NOT NULL
)');
$db->exec('CREATE TABLE city_wounded (
    id_city INTEGER PRIMARY KEY,
    army_a INTEGER NOT NULL,
    army_b INTEGER NOT NULL,
    army_c INTEGER NOT NULL,
    army_d INTEGER NOT NULL,
    army_e INTEGER NOT NULL,
    army_f INTEGER NOT NULL
)');
$db->exec('INSERT INTO city (id_city, army_a, wall_a, pop, pop_max) VALUES (3, 3, 2, 99, 100)');
$db->exec('INSERT INTO city_wounded (id_city, army_a, army_b, army_c, army_d, army_e, army_f) VALUES (3, 20, 5, 0, 0, 0, 0)');

$db->beginTransaction();
$dismissed = CityController::applyArmyDismissal($db, 3, 'army_a', 1);
$db->commit();
$city = $db->query('SELECT * FROM city WHERE id_city = 3')->fetch(PDO::FETCH_ASSOC);
expectDismissal(
    $dismissed && (int)$city['army_a'] === 2 && (int)$city['pop'] === 100,
    'Dismissed troops should return one resident without exceeding population capacity'
);

$db->beginTransaction();
$dismissed = CityController::applyArmyDismissal($db, 3, 'army_a', 3);
$db->commit();
$city = $db->query('SELECT * FROM city WHERE id_city = 3')->fetch(PDO::FETCH_ASSOC);
expectDismissal(
    !$dismissed && (int)$city['army_a'] === 2 && (int)$city['pop'] === 100,
    'Requesting more troops than exist must not remove troops or restore population'
);

$db->beginTransaction();
$dismissed = CityController::applyArmyDismissal($db, 3, 'wall_a', 1);
$db->commit();
$city = $db->query('SELECT * FROM city WHERE id_city = 3')->fetch(PDO::FETCH_ASSOC);
expectDismissal(
    $dismissed && (int)$city['wall_a'] === 1 && (int)$city['pop'] === 100,
    'Dismissing wall defenses must not restore residents'
);

$db->exec('UPDATE city SET pop = 98 WHERE id_city = 3');
$db->beginTransaction();
$dismissedWounded = CityController::applyWoundedArmyDismissal($db, 3, 'army_a');
$db->commit();
$city = $db->query('SELECT * FROM city WHERE id_city = 3')->fetch(PDO::FETCH_ASSOC);
$wounded = $db->query('SELECT * FROM city_wounded WHERE id_city = 3')->fetch(PDO::FETCH_ASSOC);
expectDismissal(
    $dismissedWounded === 20
        && (int)$city['pop'] === 100
        && (int)$wounded['army_a'] === 0
        && (int)$wounded['army_b'] === 5,
    'Dismissed wounded troops should restore only their population up to capacity'
);

$db->beginTransaction();
$dismissedWounded = CityController::applyWoundedArmyDismissal($db, 3, 'army_b');
$db->commit();
$wounded = $db->query('SELECT * FROM city_wounded WHERE id_city = 3')->fetch(PDO::FETCH_ASSOC);
expectDismissal(
    $dismissedWounded === 5
        && (int)$wounded['army_b'] === 0
        && (int)$db->query('SELECT pop FROM city WHERE id_city = 3')->fetchColumn() === 100,
    'Dismissal must still succeed when population is already at its maximum'
);

$db->beginTransaction();
$dismissedWounded = CityController::applyWoundedArmyDismissal($db, 3, 'army_a');
$db->rollBack();
expectDismissal($dismissedWounded === 0, 'An empty wounded unit type must not change the city');

$db->beginTransaction();
$dismissed = CityController::applyArmyDismissal($db, 3, 'unknown', 1);
$db->rollBack();
expectDismissal(!$dismissed, 'Unknown unit columns must be rejected');

echo "City army dismissal tests passed\n";
