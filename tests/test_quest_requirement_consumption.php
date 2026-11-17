<?php

require_once __DIR__ . '/../caesar-api/controllers/QuestController.php';

function expectQuestConsumption(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE player_item (
    id_player INTEGER NOT NULL,
    id_item TEXT NOT NULL,
    amount INTEGER NOT NULL,
    PRIMARY KEY (id_player, id_item)
)');
$db->exec('CREATE TABLE city (
    id_city INTEGER PRIMARY KEY,
    id_player INTEGER NOT NULL,
    coin INTEGER NOT NULL
)');
$db->exec("INSERT INTO player_item (id_player, id_item, amount) VALUES (7, 'luxury_1', 10), (7, 'luxury_2', 5)");
$db->exec('INSERT INTO city (id_city, id_player, coin) VALUES (3, 7, 30000)');

$consumeRequirements = new ReflectionMethod(QuestController::class, 'consumeRequirements');
$consumeRequirements->setAccessible(true);
$requirements = [
    ['type' => 'item', 'item' => 'luxury_1', 'amount' => 10],
    ['type' => 'item', 'item' => 'luxury_2', 'amount' => 5],
    ['type' => 'resource', 'resourceType' => 'coin', 'amount' => 20000],
    ['type' => 'prestige', 'amount' => 2000]
];

$db->beginTransaction();
$consumed = $consumeRequirements->invoke(null, $db, 7, 3, $requirements);
expectQuestConsumption($consumed, 'Valid item and resource requirements should be consumed');
$db->commit();

$items = $db->query("SELECT id_item, amount FROM player_item WHERE id_player = 7 ORDER BY id_item")->fetchAll(PDO::FETCH_KEY_PAIR);
$coin = (int)$db->query('SELECT coin FROM city WHERE id_city = 3')->fetchColumn();
expectQuestConsumption(
    (int)$items['luxury_1'] === 0 && (int)$items['luxury_2'] === 0 && $coin === 10000,
    'Promotion materials and city resources should be deducted, while non-consumable requirements remain untouched'
);

$db->beginTransaction();
$insufficient = $consumeRequirements->invoke(null, $db, 7, 3, [
    ['type' => 'item', 'item' => 'luxury_2', 'amount' => 1],
    ['type' => 'resource', 'resourceType' => 'coin', 'amount' => 10001]
]);
expectQuestConsumption(!$insufficient, 'Insufficient resources should stop requirement consumption');
$db->rollBack();

$afterRollback = (int)$db->query("SELECT amount FROM player_item WHERE id_player = 7 AND id_item = 'luxury_2'")->fetchColumn();
$coinAfterRollback = (int)$db->query('SELECT coin FROM city WHERE id_city = 3')->fetchColumn();
expectQuestConsumption(
    $afterRollback === 0 && $coinAfterRollback === 10000,
    'The caller transaction should roll back earlier deductions when a later requirement fails'
);

echo "Quest requirement consumption tests passed\n";
