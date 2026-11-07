<?php
// Router for web.elkaisar.com - PHP built-in server router
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Serve server.php for root
if ($uri === '/' || $uri === '') {
    require __DIR__ . '/server.php';
    exit;
}

// Check if file exists as-is
$file = __DIR__ . $uri;
if (file_exists($file) && !is_dir($file)) {
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
        'php'  => 'text/html',
    ];
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

// Default: serve server.php
require __DIR__ . '/server.php';
