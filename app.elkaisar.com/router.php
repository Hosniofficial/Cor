<?php
// router.php - Lightweight modular front controller for app.elkaisar.com

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/services/ProductionService.php';
require_once __DIR__ . '/services/TaskService.php';

// Controllers
require_once __DIR__ . '/controllers/PlayerController.php';
require_once __DIR__ . '/controllers/CityController.php';
require_once __DIR__ . '/controllers/HeroController.php';
require_once __DIR__ . '/controllers/ArmyController.php';
require_once __DIR__ . '/controllers/TimedTaskController.php';
require_once __DIR__ . '/controllers/QuestController.php';
require_once __DIR__ . '/controllers/WorldController.php';
require_once __DIR__ . '/controllers/RankingController.php';

// CORS Headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/');

// Log incoming requests for debugging
file_put_contents(__DIR__ . '/api.log', date('Y-m-d H:i:s') . " [" . $_SERVER['REQUEST_METHOD'] . "] " . $uri . " | POST: " . json_encode($_POST) . " | GET: " . json_encode($_GET) . "\n", FILE_APPEND);

// Response helper
if (!function_exists('sendJson')) {
    function sendJson($data) {
        header('Content-Type: text/plain; charset=utf-8');
        echo is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$db = getDB();

// Automatically process any due tasks on each request
TaskService::processCompletedTasks($db);

// Dispatch to Controllers
$controllers = [
    PlayerController::class,
    CityController::class,
    HeroController::class,
    ArmyController::class,
    TimedTaskController::class,
    QuestController::class,
    WorldController::class,
    RankingController::class,
];

foreach ($controllers as $ctrl) {
    if ($ctrl::handle($uri, $db)) {
        exit;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Static Files & Legacy HTML Fallback
// ─────────────────────────────────────────────────────────────────────────────

$file = __DIR__ . $uri;
if (file_exists($file) && !is_dir($file)) {
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    $mimeTypes = [
        'json' => 'application/json; charset=utf-8',
        'js'   => 'application/javascript; charset=utf-8',
        'css'  => 'text/css; charset=utf-8',
        'html' => 'text/plain; charset=utf-8',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
    ];
    $ct = isset($mimeTypes[$ext]) ? $mimeTypes[$ext] : 'text/plain; charset=utf-8';
    header('Content-Type: ' . $ct);
    readfile($file);
    exit;
}

$htmlFile = __DIR__ . $uri . '.html';
if (file_exists($htmlFile)) {
    header('Content-Type: text/plain; charset=utf-8');
    readfile($htmlFile);
    exit;
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo json_encode(['error' => 'Not found', 'path' => $uri]);
