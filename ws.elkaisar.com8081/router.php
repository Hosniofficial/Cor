<?php
// Router for ws.elkaisar.com:8081
// Handles both HTTP REST API calls and serves as WebSocket upgrade endpoint
// WebSocket is handled by upgrading; HTTP requests are served from api/ folder

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = rtrim($uri, '/');

// Try exact file
$file = __DIR__ . $uri;
if (file_exists($file) && !is_dir($file)) {
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    // .html files contain JSON — serve as text/plain to prevent jQuery auto-parse
    $ct = ($ext === 'html') ? 'text/plain' : 'application/octet-stream';
    header('Content-Type: ' . $ct);
    readfile($file);
    exit;
}

// Try .html extension
$htmlFile = __DIR__ . $uri . '.html';
if (file_exists($htmlFile)) {
    header('Content-Type: text/plain');
    readfile($htmlFile);
    exit;
}

http_response_code(404);
header('Content-Type: application/json');
echo json_encode(['error' => 'Not found', 'path' => $uri]);
