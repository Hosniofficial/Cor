<?php
// api/dominant.php - Dominant Army Capitals & Colonizer API
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

if (isset($_GET['GET_ARMY_CAPITAL_DOMINANT']) || isset($_POST['GET_ARMY_CAPITAL_DOMINANT'])) {
    echo json_encode([
        'state' => 'ok',
        'type'  => 'domain',
        'unit'  => []
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['state' => 'ok', 'list' => []], JSON_UNESCAPED_UNICODE);
exit;
