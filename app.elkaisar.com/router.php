<?php
// router.php - Lightweight modular front controller for app.elkaisar.com

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/PlayerAuth.php';
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
require_once __DIR__ . '/controllers/ItemController.php';
require_once __DIR__ . '/controllers/GuildController.php';
require_once __DIR__ . '/controllers/MarketController.php';
require_once __DIR__ . '/controllers/ArenaController.php';
require_once __DIR__ . '/controllers/GodGateController.php';
require_once __DIR__ . '/controllers/TeamController.php';

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
if (preg_match('#^/(?:api|home/HLogIn)/.+\.html$#i', $uri) === 1) {
    $uri = substr($uri, 0, -5);
}

if (strpos($uri, '/api/') === 0
    || strpos($uri, '/World/') === 0
    || strpos($uri, '/Chat/') === 0
    || strpos($uri, '/Battel/') === 0
    || $uri === '/home/HLogIn/playerEnterServerWeb') {
    $db = getDB();
    requireAuthenticatedPlayer($db);
}

// Battle & Battle Report API Forwarding
if ($uri === '/api/battelReport.php' || $uri === '/api/battelReport') {
    require_once __DIR__ . '/../web.elkaisar.com/api/battelReport.php';
    exit;
}
if ($uri === '/api/battel.php' || $uri === '/api/battel') {
    require_once __DIR__ . '/../web.elkaisar.com/api/battel.php';
    exit;
}

error_log(sprintf('[API] %s %s', $_SERVER['REQUEST_METHOD'], $uri));

$db = isset($db) ? $db : getDB();

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
    ItemController::class,
    GuildController::class,
    MarketController::class,
    ArenaController::class,
    GodGateController::class,
    TeamController::class,
];

foreach ($controllers as $ctrl) {
    if ($ctrl::handle($uri, $db)) {
        exit;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Static Files & Legacy HTML Fallback
// ─────────────────────────────────────────────────────────────────────────────

$documentRoot = realpath(__DIR__);
$file = realpath($documentRoot . DIRECTORY_SEPARATOR . ltrim($uri, '/\\'));
$isInsideDocumentRoot = $file !== false
    && str_starts_with(strtolower($file), strtolower($documentRoot . DIRECTORY_SEPARATOR));

if ($isInsideDocumentRoot && is_file($file)) {
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    $mimeTypes = [
        'json' => 'application/json; charset=utf-8',
        'js'   => 'application/javascript; charset=utf-8',
        'css'  => 'text/css; charset=utf-8',
        'html' => 'text/plain; charset=utf-8',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'webp' => 'image/webp',
        'ico'  => 'image/x-icon',
    ];
    if (!isset($mimeTypes[$ext])) {
        http_response_code(404);
        exit;
    }

    header('Content-Type: ' . $mimeTypes[$ext]);
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
