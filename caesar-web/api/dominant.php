<?php
// api/dominant.php - Dominant Army Capitals & Colonizer API
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../caesar-api/db.php';
require_once __DIR__ . '/../../caesar-api/controllers/WorldController.php';

try {
    $db = function_exists('getDB') ? getDB() : null;
    WorldController::getArmyCapitalDominant($db);
} catch (\Throwable $e) {
    echo json_encode([
        'state' => 'error',
        'msg'   => 'Failed to load capital dominance: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
exit;
