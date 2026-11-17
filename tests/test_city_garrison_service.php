<?php

require_once __DIR__ . '/../caesar-api/services/CityGarrisonService.php';

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE city_garrison (
    id_hero INTEGER, id_city INTEGER, id_player INTEGER, ord INTEGER,
    status TEXT, time_arrive INTEGER, return_at INTEGER, battle_id INTEGER
)');
$db->exec('CREATE TABLE city_hero (
    id_hero INTEGER, id_city INTEGER, id_player INTEGER, name TEXT,
    lvl INTEGER, avatar INTEGER, in_city INTEGER
)');
$db->exec('CREATE TABLE player (id_player INTEGER, name TEXT)');
$db->exec('CREATE TABLE city (id_city INTEGER, id_player INTEGER, name TEXT, x INTEGER, y INTEGER)');
$db->exec('CREATE TABLE hero_army (
    id_hero INTEGER, id_player INTEGER,
    f_1_type INTEGER, f_1_num INTEGER, f_2_type INTEGER, f_2_num INTEGER,
    f_3_type INTEGER, f_3_num INTEGER, b_1_type INTEGER, b_1_num INTEGER,
    b_2_type INTEGER, b_2_num INTEGER, b_3_type INTEGER, b_3_num INTEGER
)');
$db->exec("INSERT INTO city_garrison VALUES (19, 3, 8, 0, 'stationed', 100, 0, NULL)");
$db->exec("INSERT INTO city_hero VALUES (19, 7, 8, 'Hero One', 4, 1, 0)");
$db->exec("INSERT INTO player VALUES (8, 'Player One')");
$db->exec("INSERT INTO city VALUES (3, 7, 'Host City', 10, 20)");
$db->exec('INSERT INTO hero_army VALUES (19, 8, 1, 10, 2, 2, 0, 0, 3, 3, 4, 1, 0, 0)');

$garrison = CityGarrisonService::getCityGarrison($db, 3);
if (count($garrison) !== 1
    || $garrison[0]['troop_count'] !== 16
    || $garrison[0]['Army']['f_1_type'] !== 1
    || $garrison[0]['Army']['f_1_num'] !== 10
    || $garrison[0]['Army']['b_2_type'] !== 4
    || $garrison[0]['Army']['b_2_num'] !== 1
    || $garrison[0]['HeroName'] !== 'Hero One'
    || $garrison[0]['LordName'] !== 'Player One'
    || $garrison[0]['isAlly'] !== true) {
    throw new RuntimeException('Plaza garrison data did not include correct hero, owner, or troop count');
}

echo "City garrison service tests passed\n";
