<?php

$teamControllerResponse = null;

function sendJson($data): void {
    $GLOBALS['teamControllerResponse'] = $data;
}

require_once __DIR__ . '/../caesar-api/controllers/TeamController.php';

if (!TeamController::handle('/api/ATeam/getPlayerTeam', null)) {
    throw new RuntimeException('getPlayerTeam was not handled');
}

$expected = [
    'Team' => false,
    'TeamMember' => [],
    'TeamInv' => [],
    'TeamReq' => []
];

if (http_response_code() !== 200 || $teamControllerResponse !== $expected) {
    throw new RuntimeException('getPlayerTeam did not return the successful no-team response');
}

echo "Team controller tests passed\n";
