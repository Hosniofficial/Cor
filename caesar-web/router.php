<?php
// Router for caesar-web - PHP built-in server router
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Start new visitors on the branded entry page.
if ($uri === '/' || $uri === '') {
    require __DIR__ . '/index.php';
    exit;
}

// Cocos2d Battle Replay API & Page Routes
if ($uri === '/GetBattle' || $uri === '/GetBattle.php') {
    require __DIR__ . '/GetBattle.php';
    exit;
}
if (strpos($uri, '/BattleReplay/') === 0 || $uri === '/battel.php') {
    require __DIR__ . '/battel.php';
    exit;
}

$isAdminRequest = $uri === '/admin' || strpos($uri, '/admin/') === 0;
$documentRoot = realpath(__DIR__);
$relativeUri = $uri;
if ($isAdminRequest) {
    $documentRoot = realpath(dirname(__DIR__) . '/caesar-admin');
    $relativeUri = ltrim(substr($uri, strlen('/admin')), '/\\');
    if ($relativeUri === '') {
        $relativeUri = 'index.php';
    }
}

$file = $documentRoot === false
    ? false
    : realpath($documentRoot . DIRECTORY_SEPARATOR . ltrim($relativeUri, '/\\'));
$isInsideDocumentRoot = $file !== false
    && ($file === $documentRoot
        || str_starts_with(strtolower($file), strtolower($documentRoot . DIRECTORY_SEPARATOR)));

if (!$isInsideDocumentRoot && !$isAdminRequest) {
    $assetRoot = realpath(dirname(__DIR__) . '/caesar-assets/web');
    $assetFile = $assetRoot === false
        ? false
        : realpath($assetRoot . DIRECTORY_SEPARATOR . ltrim($uri, '/\\'));
    if ($assetFile === false && $assetRoot !== false) {
        $replayCandidate = realpath($assetRoot . DIRECTORY_SEPARATOR . 'battle-replay' . DIRECTORY_SEPARATOR . ltrim($uri, '/\\'));
        if ($replayCandidate !== false) {
            $assetFile = $replayCandidate;
        }
    }
    if ($assetFile !== false
        && ($assetFile === $assetRoot
            || str_starts_with(strtolower($assetFile), strtolower($assetRoot . DIRECTORY_SEPARATOR)))) {
        $documentRoot = $assetRoot;
        $file = $assetFile;
        $isInsideDocumentRoot = true;
    }
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
        'css'   => 'text/css',
        'js'    => 'application/javascript',
        'json'  => 'application/json',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'svg'   => 'image/svg+xml',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf',
        'html'  => 'text/html',
        'plist' => 'application/xml',
        'fnt'   => 'text/plain',
        'mp3'   => 'audio/mpeg',
        'ogg'   => 'audio/ogg',
        'wav'   => 'audio/wav',
        'ico'   => 'image/x-icon',
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
        $compressibleExtensions = ['css', 'js', 'json', 'svg', 'html', 'xml', 'plist', 'fnt'];
        if (in_array($ext, $compressibleExtensions, true)) {
            header('Vary: Accept-Encoding');
            $acceptEncoding = strtolower($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '');
            $acceptsGzip = preg_match('/(?:^|,)\s*gzip(?:\s*;\s*q=([0-9.]+))?(?:,|$)/', $acceptEncoding, $gzipMatch) === 1
                && (!isset($gzipMatch[1]) || (float)$gzipMatch[1] > 0);
            if ($acceptsGzip && function_exists('gzencode')) {
                $contents = file_get_contents($file);
                if ($contents !== false) {
                    $compressedContents = gzencode($contents, 1);
                    if ($compressedContents !== false && strlen($compressedContents) < strlen($contents)) {
                        header('Content-Encoding: gzip');
                        header('Content-Length: ' . strlen($compressedContents));
                        echo $compressedContents;
                        exit;
                    }
                    echo $contents;
                    exit;
                }
            }
        }
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
if (in_array($uriExt, ['css', 'js', 'json', 'woff', 'woff2', 'ttf', 'mp3', 'ogg', 'wav', 'plist', 'fnt', 'ico'])) {
    http_response_code(404);
    exit;
}

if ($isAdminRequest) {
    http_response_code(404);
    exit;
}

// If an API endpoint is not served locally, forward it to caesar-api.
if (strpos($uri, '/api/') === 0) {
    $apiBaseUrl = rtrim(getenv('CAESAR_API_URL') ?: 'http://127.0.0.1:8092', '/');
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
