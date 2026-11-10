<?php
// Router for web.elkaisar.com - PHP built-in server router
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$documentRoot = realpath(__DIR__);
$file = realpath($documentRoot . DIRECTORY_SEPARATOR . ltrim($uri, '/\\'));
$isInsideDocumentRoot = $file !== false
    && str_starts_with(strtolower($file), strtolower($documentRoot . DIRECTORY_SEPARATOR));

// Start new visitors on the branded entry page.
if ($uri === '/' || $uri === '') {
    require __DIR__ . '/index.php';
    exit;
}

// Check if directory has index.php
$file = $isInsideDocumentRoot ? $file : false;
if ($file !== false && is_dir($file)) {
    $indexFile = rtrim($file, '/\\') . DIRECTORY_SEPARATOR . 'index.php';
    if (file_exists($indexFile)) {
        header('Content-Type: text/html; charset=utf-8');
        require $indexFile;
        exit;
    }
}

// Check if file exists as-is
if ($file !== false && is_file($file)) {
    // Set correct content type for known extensions
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    $mimeTypes = [
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'json' => 'application/json',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'woff' => 'font/woff',
        'woff2'=> 'font/woff2',
        'ttf'  => 'font/ttf',
        'html' => 'text/html',
    ];
    if ($ext !== 'php' && !isset($mimeTypes[$ext])) {
        http_response_code(404);
        exit;
    }
    if (isset($mimeTypes[$ext])) {
        header('Content-Type: ' . $mimeTypes[$ext]);
    }
    if ($ext === 'php') {
        require $file;
    } else {
        readfile($file);
    }
    exit;
}

// Handle non-existent static assets (images, fonts, scripts, css) gracefully
// Return explicit 404 with warning so missing images are clearly identified without redirect loops
$uriExt = strtolower(pathinfo($uri, PATHINFO_EXTENSION));
$isImg = in_array($uriExt, ['png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'webp']) || strpos($uri, '/images/') === 0;
if ($isImg) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Missing-Image: ' . $uri);
    error_log('[WEB] Missing image: ' . json_encode($uri, JSON_INVALID_UTF8_SUBSTITUTE));
    echo "404 Not Found (Missing Image): " . $uri;
    exit;
}
if (in_array($uriExt, ['css', 'js', 'json', 'woff', 'woff2', 'ttf', 'mp3', 'ogg', 'wav'])) {
    http_response_code(404);
    exit;
}

// If an API endpoint was requested but file not found in web.elkaisar.com, forward to the API server.
if (strpos($uri, '/api/') === 0) {
    $apiBaseUrl = rtrim(getenv('ELKAISAR_API_URL') ?: 'http://127.0.0.1:8092', '/');
    $apiUrl = $apiBaseUrl . $_SERVER['REQUEST_URI'];
    $method = $_SERVER['REQUEST_METHOD'];

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, !empty($_POST) ? http_build_query($_POST) : file_get_contents('php://input'));
    }

    $headers = [];
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strtolower($name) !== 'host') {
                $headers[] = "$name: $value";
            }
        }
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        error_log('[API proxy] Upstream request failed: ' . $error);
        http_response_code(502);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['state' => 'error', 'msg' => 'API server unavailable']);
        exit;
    }

    http_response_code($httpCode);
    header('Content-Type: ' . ($contentType ?: 'application/json; charset=utf-8'));
    echo $response;
    exit;
}

// Default: serve server.php
require __DIR__ . '/server.php';
