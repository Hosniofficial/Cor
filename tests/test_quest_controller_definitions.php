<?php

class QuestControllerTestResponse extends RuntimeException {
    public $payload;

    public function __construct($payload) {
        $this->payload = $payload;
    }
}

function sendJson($data): void {
    throw new QuestControllerTestResponse($data);
}

require_once __DIR__ . '/../caesar-api/PlayerAuth.php';
require_once __DIR__ . '/../caesar-api/controllers/QuestController.php';

$loader = new ReflectionMethod(QuestController::class, 'loadQuestDefinitions');
$loader->setAccessible(true);
$quests = $loader->invoke(null);

if (!is_array($quests) || !isset($quests['cottage_1'])) {
    throw new RuntimeException('Quest definitions should load from the current assets directory');
}

$validator = new ReflectionMethod(QuestController::class, 'isValidRewardList');
$validator->setAccessible(true);
$containsPrestigeReward = false;
foreach ($quests as $questId => $quest) {
    $rewards = $quest['Reword'] ?? null;
    if (!$validator->invoke(null, $rewards)) {
        throw new RuntimeException("Quest {$questId} contains a reward type the server does not support");
    }
    foreach (is_array($rewards) ? $rewards : [] as $reward) {
        if (($reward['type'] ?? '') === 'prestige') {
            $containsPrestigeReward = true;
        }
    }
}
if (!$containsPrestigeReward
    || !$validator->invoke(null, [['type' => 'prestige', 'amount' => 1]])) {
    throw new RuntimeException('Prestige rewards should pass quest reward validation');
}

echo "Quest definition loading test passed\n";
